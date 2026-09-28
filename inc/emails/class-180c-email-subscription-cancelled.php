<?php
/**
 * Email transactionnel — Confirmation de résiliation d'abonnement.
 *
 * Envoyé à l'abonné lorsqu'il résilie via le parcours de désabonnement
 * self-service (écran 4 du stepper). Déclenché par l'action custom
 * `_180c_subscription_cancelled_via_flow` émise par le handler REST
 * /subscription/cancel — et NON par une transition WCS générique, afin de ne
 * pas emailer sur les résiliations admin/système.
 *
 * Garde anti-doublon : meta `_180c_cancel_email_sent` posée sur la souscription.
 *
 * Gabarits (override thème) : woocommerce/emails/180c-subscription-cancelled.php
 * et sa variante plain — JAMAIS dans templates/woocommerce/.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

if ( ! class_exists( '_180C_Email_Subscription_Cancelled' ) ) :

	/**
	 * Classe d'email de confirmation de résiliation.
	 */
	class _180C_Email_Subscription_Cancelled extends WC_Email {

		/**
		 * Date de fin d'accès formatée (FR), ou null si résiliation immédiate.
		 *
		 * @var string|null
		 */
		public ?string $end_date = null;

		/**
		 * Constructeur : identité de l'email + abonnement au déclencheur.
		 */
		public function __construct() {
			$this->id             = '180c_subscription_cancelled';
			$this->customer_email = true;
			$this->title          = __( 'Résiliation d\'abonnement (180°C)', '180c' );
			$this->description    = __( 'Email envoyé à l\'abonné qui résilie son abonnement via le parcours de désabonnement self-service.', '180c' );

			$this->template_html  = 'emails/180c-subscription-cancelled.php';
			$this->template_plain = 'emails/plain/180c-subscription-cancelled.php';
			$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';

			// Déclencheur self-service (l'instanciation est garantie par
			// WC()->mailer() appelé juste avant le do_action côté REST).
			add_action( '_180c_subscription_cancelled_via_flow', array( $this, 'trigger' ) );

			parent::__construct();
		}

		/**
		 * Objet par défaut (filtrable via les réglages WooCommerce).
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Votre abonnement 180°C a bien été résilié', '180c' );
		}

		/**
		 * Titre (heading) par défaut.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Résiliation confirmée', '180c' );
		}

		/**
		 * Déclenche l'envoi pour une souscription donnée.
		 *
		 * @param int $subscription_id ID de la souscription résiliée.
		 * @return void
		 */
		public function trigger( $subscription_id ) {
			$this->setup_locale();

			$subscription_id = absint( $subscription_id );
			$subscription    = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $subscription_id ) : null;

			if ( ! $subscription instanceof \WC_Subscription ) {
				$this->restore_locale();
				return;
			}

			// Anti-doublon : ne jamais renvoyer si déjà envoyé.
			if ( 'yes' === $subscription->get_meta( '_180c_cancel_email_sent' ) ) {
				$this->restore_locale();
				return;
			}

			$this->object    = $subscription;
			$this->recipient = $subscription->get_billing_email();

			// Date de fin d'accès — même logique que le handler REST (Phase 5).
			$end_ts         = $subscription->get_time( 'end' ) ?: $subscription->get_time( 'next_payment' );
			$this->end_date = ( $end_ts && $end_ts > time() ) ? wp_date( 'j F Y', $end_ts ) : null;

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send(
					$this->get_recipient(),
					$this->get_subject(),
					$this->get_content(),
					$this->get_headers(),
					$this->get_attachments()
				);

				$subscription->update_meta_data( '_180c_cancel_email_sent', 'yes' );
				$subscription->save();
			}

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
					'end_date'            => $this->end_date,
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
					'end_date'            => $this->end_date,
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
			return __( 'Merci d\'avoir fait partie de la communauté 180°C.', '180c' );
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
