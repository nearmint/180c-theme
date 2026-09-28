<?php
/**
 * Email transactionnel — Bienvenue après activation d'abonnement.
 *
 * Envoyé à l'abonné lorsque sa souscription passe au statut « active »
 * (paiement complet), pour présenter les avantages. Déclenché par la
 * transition WCS `woocommerce_subscription_status_active` (câblé dans
 * inc/woo/emails.php, qui garantit l'init du mailer).
 *
 * Garde anti-doublon : meta `_180c_welcome_email_sent` posée sur la souscription
 * — évite tout renvoi lors d'une réactivation ou d'un renouvellement futur.
 *
 * Gabarits (override thème) : woocommerce/emails/180c-welcome-subscription.php
 * et sa variante plain — JAMAIS dans templates/woocommerce/.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

if ( ! class_exists( '_180C_Email_Welcome_Subscription' ) ) :

	/**
	 * Classe d'email de bienvenue abonnement.
	 */
	class _180C_Email_Welcome_Subscription extends WC_Email {

		/**
		 * Constructeur : identité de l'email.
		 */
		public function __construct() {
			$this->id             = '180c_welcome_subscription';
			$this->customer_email = true;
			$this->title          = __( 'Bienvenue — Abonnement activé (180°C)', '180c' );
			$this->description    = __( 'Email envoyé au client après l\'activation de son abonnement pour présenter les avantages.', '180c' );

			$this->template_html  = 'emails/180c-welcome-subscription.php';
			$this->template_plain = 'emails/plain/180c-welcome-subscription.php';
			$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';

			parent::__construct();
		}

		/**
		 * Objet par défaut (filtrable via les réglages WooCommerce).
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Bienvenue sur 180°C — votre abonnement est activé', '180c' );
		}

		/**
		 * Titre (heading) par défaut.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Bienvenue sur 180°C', '180c' );
		}

		/**
		 * Déclenche l'envoi pour une souscription activée.
		 *
		 * Accepte un objet WC_Subscription (transmis par le hook de transition)
		 * ou un ID (appel défensif).
		 *
		 * @param WC_Subscription|int $subscription Souscription activée (ou son ID).
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

			// Anti-doublon : ne jamais renvoyer (réactivation / renouvellement).
			if ( 'yes' === $subscription->get_meta( '_180c_welcome_email_sent' ) ) {
				$this->restore_locale();
				return;
			}

			$this->object    = $subscription;
			$this->recipient = $subscription->get_billing_email();

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send(
					$this->get_recipient(),
					$this->get_subject(),
					$this->get_content(),
					$this->get_headers(),
					$this->get_attachments()
				);

				$subscription->update_meta_data( '_180c_welcome_email_sent', 'yes' );
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
			// Vide par défaut : le gabarit porte déjà sa propre ligne de contact.
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
