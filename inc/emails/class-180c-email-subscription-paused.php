<?php
/**
 * Email transactionnel — Confirmation de mise en pause d'abonnement.
 *
 * Envoyé à l'abonné qui met lui-même son abonnement en pause depuis Mon compte
 * (parcours self-service). Déclenché par la transition WCS self-service
 * `woocommerce_customer_changed_subscription_to_on-hold` (câblée dans
 * inc/woo/emails.php, qui garantit l'init du mailer) — et NON par une transition
 * de statut générique : cette action n'est émise que par
 * WCS_User_Change_Status_Handler, jamais sur une pause admin ni sur un passage
 * `on-hold` consécutif à un renouvellement impayé.
 *
 * L'accès aux contenus abonnés est coupé immédiatement (WC Memberships passe le
 * membership lié en `paused`), le temps restant étant reporté à la reprise :
 * la copie éditoriale suit la variante « accès suspendu immédiatement ».
 *
 * Garde anti-rebond : meta `_180c_pause_email_last_sent` (timestamp) posée sur la
 * souscription — fenêtre de 5 min pour absorber un double-clic, sans bloquer un
 * futur cycle pause/reprise légitime.
 *
 * Gabarits (override thème) : woocommerce/emails/180c-subscription-paused.php
 * et sa variante plain — JAMAIS dans templates/woocommerce/.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

if ( ! class_exists( '_180C_Email_Subscription_Paused' ) ) :

	/**
	 * Classe d'email de confirmation de mise en pause.
	 */
	class _180C_Email_Subscription_Paused extends WC_Email {

		/**
		 * URL de réactivation (page Mon compte).
		 *
		 * @var string
		 */
		public string $reactivate_url = '';

		/**
		 * Constructeur : identité de l'email.
		 *
		 * Le déclencheur est câblé dans inc/woo/emails.php (via WC()->mailer())
		 * plutôt qu'ici : la requête front qui traite l'URL de pause native WCS
		 * n'initialise pas le mailer, donc cette classe ne serait pas instanciée
		 * si l'on se contentait d'un add_action dans ce constructeur.
		 */
		public function __construct() {
			$this->id             = '180c_subscription_paused';
			$this->customer_email = true;
			$this->title          = __( 'Mise en pause d\'abonnement (180°C)', '180c' );
			$this->description    = __( 'Email envoyé à l\'abonné qui met lui-même son abonnement en pause depuis Mon compte.', '180c' );

			$this->template_html  = 'emails/180c-subscription-paused.php';
			$this->template_plain = 'emails/plain/180c-subscription-paused.php';
			$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';

			// Placeholder custom en plus des tokens de site fournis par WC_Email.
			$this->placeholders = array(
				'{subscription_id}' => '',
			);

			parent::__construct();
		}

		/**
		 * Objet par défaut (filtrable via les réglages WooCommerce).
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Votre abonnement 180°C est en pause', '180c' );
		}

		/**
		 * Titre (heading) par défaut.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Votre abonnement est en pause', '180c' );
		}

		/**
		 * Déclenche l'envoi pour une souscription mise en pause.
		 *
		 * Accepte un objet WC_Subscription (transmis par le hook de transition)
		 * ou un ID (appel défensif).
		 *
		 * @param WC_Subscription|int $subscription Souscription mise en pause (ou son ID).
		 * @return void
		 */
		public function trigger( $subscription ) {
			$this->setup_locale();

			if ( is_numeric( $subscription ) && function_exists( 'wcs_get_subscription' ) ) {
				$subscription = wcs_get_subscription( absint( $subscription ) );
			}

			if ( ! $subscription instanceof \WC_Subscription ) {
				$this->restore_locale();
				return;
			}

			// Garde statut : la souscription doit être en pause au moment du trigger.
			if ( ! $subscription->has_status( 'on-hold' ) ) {
				$this->restore_locale();
				return;
			}

			// Garde impayé (ceinture) : ne jamais confondre pause volontaire et
			// suspension sur renouvellement impayé. Bail si une commande de
			// renouvellement liée est en attente / échouée / en attente.
			foreach ( (array) $subscription->get_related_orders( 'ids', 'renewal' ) as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order instanceof \WC_Order && $order->has_status( array( 'pending', 'failed', 'on-hold' ) ) ) {
					$this->restore_locale();
					return;
				}
			}

			$this->object    = $subscription;
			$this->recipient = $subscription->get_billing_email();

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				$this->restore_locale();
				return;
			}

			// Anti-rebond : absorbe un double-clic sans bloquer un cycle futur.
			$last = (int) $subscription->get_meta( '_180c_pause_email_last_sent' );
			if ( $last && ( time() - $last ) < 5 * MINUTE_IN_SECONDS ) {
				$this->restore_locale();
				return;
			}

			$this->placeholders['{subscription_id}'] = (string) $subscription->get_id();

			$account_url          = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
			$this->reactivate_url = $account_url ? $account_url : home_url( '/mon-compte/' );

			$this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);

			$subscription->update_meta_data( '_180c_pause_email_last_sent', (string) time() );
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
					'reactivate_url'      => $this->reactivate_url,
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
					'reactivate_url'      => $this->reactivate_url,
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
