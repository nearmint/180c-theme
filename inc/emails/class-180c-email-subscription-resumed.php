<?php
/**
 * Email transactionnel — Confirmation de réactivation d'abonnement.
 *
 * Envoyé à l'abonné qui réactive lui-même son abonnement en pause depuis Mon
 * compte (parcours self-service). Déclenché par l'action custom
 * `_180c_subscription_resumed_via_flow` émise par le handler REST
 * /subscription/reactivate — et NON par une transition WCS générique. Le endpoint
 * REST n'est atteint que par le bouton « Réactiver » (jamais par le paiement d'un
 * renouvellement impayé, qui réactive via le flux natif), et l'action n'est émise
 * que lorsque le statut précédent était `on-hold` (vraie reprise de pause, pas une
 * annulation de résiliation `pending-cancel`).
 *
 * Garde anti-rebond : meta `_180c_resume_email_last_sent` (timestamp) posée sur la
 * souscription — fenêtre de 5 min pour absorber un double-clic.
 *
 * Gabarits (override thème) : woocommerce/emails/180c-subscription-resumed.php
 * et sa variante plain — JAMAIS dans templates/woocommerce/.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

if ( ! class_exists( '_180C_Email_Subscription_Resumed' ) ) :

	/**
	 * Classe d'email de confirmation de réactivation.
	 */
	class _180C_Email_Subscription_Resumed extends WC_Email {

		/**
		 * Date de prochaine facturation formatée (FR), ou null si indisponible.
		 *
		 * @var string|null
		 */
		public ?string $next_payment_date = null;

		/**
		 * URL de découverte des recettes (CTA).
		 *
		 * @var string
		 */
		public string $recipes_url = '';

		/**
		 * Constructeur : identité de l'email + abonnement au déclencheur.
		 */
		public function __construct() {
			$this->id             = '180c_subscription_resumed';
			$this->customer_email = true;
			$this->title          = __( 'Réactivation d\'abonnement (180°C)', '180c' );
			$this->description    = __( 'Email envoyé à l\'abonné qui réactive lui-même son abonnement en pause depuis Mon compte.', '180c' );

			$this->template_html  = 'emails/180c-subscription-resumed.php';
			$this->template_plain = 'emails/plain/180c-subscription-resumed.php';
			$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';

			// Placeholder custom en plus des tokens de site fournis par WC_Email.
			$this->placeholders = array(
				'{subscription_id}' => '',
			);

			// Déclencheur self-service (l'instanciation est garantie par
			// WC()->mailer() appelé juste avant le do_action côté REST).
			add_action( '_180c_subscription_resumed_via_flow', array( $this, 'trigger' ) );

			parent::__construct();
		}

		/**
		 * Objet par défaut (filtrable via les réglages WooCommerce).
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Votre abonnement 180°C a repris', '180c' );
		}

		/**
		 * Titre (heading) par défaut.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Votre abonnement est réactivé', '180c' );
		}

		/**
		 * Déclenche l'envoi pour une souscription réactivée.
		 *
		 * @param int $subscription_id ID de la souscription réactivée.
		 * @return void
		 */
		public function trigger( $subscription_id ) {
			$this->setup_locale();

			$subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( absint( $subscription_id ) ) : null;

			if ( ! $subscription instanceof \WC_Subscription ) {
				$this->restore_locale();
				return;
			}

			// Garde statut : la souscription doit être active au moment du trigger.
			if ( ! $subscription->has_status( 'active' ) ) {
				$this->restore_locale();
				return;
			}

			// Garde « paiement d'impayé » (ceinture) : bail si une commande de
			// renouvellement liée a été payée dans les 5 dernières minutes — la
			// réactivation résulterait du règlement d'un impayé, pas du bouton.
			foreach ( (array) $subscription->get_related_orders( 'ids', 'renewal' ) as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order instanceof \WC_Order && $order->is_paid() ) {
					$paid = $order->get_date_paid();
					if ( $paid instanceof \WC_DateTime && ( time() - $paid->getTimestamp() ) < 5 * MINUTE_IN_SECONDS ) {
						$this->restore_locale();
						return;
					}
				}
			}

			$this->object    = $subscription;
			$this->recipient = $subscription->get_billing_email();

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				$this->restore_locale();
				return;
			}

			// Anti-rebond : absorbe un double-clic sans bloquer un cycle futur.
			$last = (int) $subscription->get_meta( '_180c_resume_email_last_sent' );
			if ( $last && ( time() - $last ) < 5 * MINUTE_IN_SECONDS ) {
				$this->restore_locale();
				return;
			}

			$this->placeholders['{subscription_id}'] = (string) $subscription->get_id();

			// Date de prochaine facturation (cohérence i18n avec l'e-mail cancelled :
			// timestamp + wp_date plutôt que get_date_to_display, qui renvoie « - »
			// quand la date n'est pas définie).
			$next_ts                 = $subscription->get_time( 'next_payment' );
			$this->next_payment_date = ( $next_ts && $next_ts > time() ) ? wp_date( 'j F Y', $next_ts ) : null;

			$this->recipes_url = apply_filters( '180c/recipes_url', home_url( '/recettes/' ) );

			$this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);

			$subscription->update_meta_data( '_180c_resume_email_last_sent', (string) time() );
			$subscription->save();

			$this->restore_locale();
		}

		/**
		 * Rendu HTML.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'subscription'        => $this->object,
					'customer_first_name' => $this->object ? $this->object->get_billing_first_name() : '',
					'next_payment_date'   => $this->next_payment_date,
					'recipes_url'         => $this->recipes_url,
					'email_heading'       => $this->get_heading(),
					'additional_content'  => $this->get_additional_content(),
					'sent_to_admin'       => false,
					'plain_text'          => false,
					'email'               => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Rendu texte brut.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'subscription'        => $this->object,
					'customer_first_name' => $this->object ? $this->object->get_billing_first_name() : '',
					'next_payment_date'   => $this->next_payment_date,
					'recipes_url'         => $this->recipes_url,
					'email_heading'       => $this->get_heading(),
					'additional_content'  => $this->get_additional_content(),
					'sent_to_admin'       => false,
					'plain_text'          => true,
					'email'               => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Contenu additionnel par défaut (zone libre des réglages WooCommerce).
		 *
		 * @return string
		 */
		public function get_default_additional_content() {
			// Vide par défaut : le gabarit porte déjà sa propre signature.
			return '';
		}

		/**
		 * Champs de réglages (WooCommerce > Réglages > E-mails).
		 *
		 * @return void
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __( 'Activer/Désactiver', '180c' ),
					'type'    => 'checkbox',
					'label'   => __( 'Activer cet email', '180c' ),
					'default' => 'yes',
				),
				'subject'            => array(
					'title'       => __( 'Objet', '180c' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: objet par défaut. */
					'description' => sprintf( __( 'Par défaut : %s', '180c' ), $this->get_default_subject() ),
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'            => array(
					'title'       => __( 'Titre', '180c' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: titre par défaut. */
					'description' => sprintf( __( 'Par défaut : %s', '180c' ), $this->get_default_heading() ),
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'additional_content' => array(
					'title'       => __( 'Contenu additionnel', '180c' ),
					'type'        => 'textarea',
					'description' => __( 'Texte ajouté au bas de l\'email.', '180c' ),
					'placeholder' => $this->get_default_additional_content(),
					'default'     => $this->get_default_additional_content(),
				),
				'email_type'         => array(
					'title'       => __( 'Type d\'email', '180c' ),
					'type'        => 'select',
					'description' => __( 'Format des emails à envoyer.', '180c' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
	}

endif;
