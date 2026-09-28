<?php
/**
 * Email transactionnel — confirmation d'une demande de suppression de compte.
 *
 * Envoyé à l'ouverture d'une demande et à chaque renvoi, via l'action
 * `180c/account_deletion_email_confirm` (dispatcher : emails.php).
 *
 * Gabarits : woocommerce/emails/180c-account-deletion-confirm.php (+ plain/).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// Le préfixe de classes imposé par CLAUDE.md (`_180C_`) commence par un
// underscore, ce que ce sniff interdit. Les six classes d'e-mail déjà en place
// dans le thème sont dans le même cas.
// phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

if ( ! class_exists( '_180C_Email_Account_Deletion_Confirm' ) ) :

	/**
	 * Email de confirmation de demande de suppression.
	 */
	class _180C_Email_Account_Deletion_Confirm extends WC_Email {

		/**
		 * Données de l'envoi courant (prénom, URL de confirmation).
		 *
		 * @var array
		 */
		public array $deletion_data = array();

		/**
		 * Résultat du dernier envoi (wp_mail a-t-il accepté le message ?).
		 *
		 * @var bool
		 */
		public bool $last_send_ok = false;

		/**
		 * Constructeur.
		 */
		public function __construct() {
			$this->id             = '180c_account_deletion_confirm';
			$this->customer_email = true;
			$this->title          = __( 'Suppression de compte — confirmation (180°C)', '180c' );
			$this->description    = __( 'Email envoyé pour confirmer une demande de suppression de compte.', '180c' );

			$this->template_html  = 'emails/180c-account-deletion-confirm.php';
			$this->template_plain = 'emails/plain/180c-account-deletion-confirm.php';
			$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';

			add_action( '180c/account_deletion_email_confirm', array( $this, 'trigger' ), 10, 1 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).

			parent::__construct();
		}

		/**
		 * Objet par défaut.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Confirmez la suppression de votre compte 180°C', '180c' );
		}

		/**
		 * Titre par défaut.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Confirmez la suppression de votre compte', '180c' );
		}

		/**
		 * Déclenche l'envoi.
		 *
		 * @param array $data { Données d'envoi.
		 *     @type string $email       Destinataire.
		 *     @type string $first_name  Prénom, éventuellement vide.
		 *     @type string $confirm_url URL de confirmation portant le jeton.
		 * }
		 * @return void
		 */
		public function trigger( $data ) {
			$this->setup_locale();

			$data = is_array( $data ) ? $data : array();

			$this->deletion_data = array(
				'first_name'  => (string) ( $data['first_name'] ?? '' ),
				'confirm_url' => (string) ( $data['confirm_url'] ?? '' ),
			);

			$this->recipient    = (string) ( $data['email'] ?? '' );
			$this->last_send_ok = false;

			if ( $this->is_enabled() && $this->get_recipient() && '' !== $this->deletion_data['confirm_url'] ) {
				$this->last_send_ok = (bool) $this->send(
					$this->get_recipient(),
					$this->get_subject(),
					$this->get_content(),
					$this->get_headers(),
					$this->get_attachments()
				);
			}

			$this->restore_locale();
		}

		/**
		 * Prépare les variables de gabarit.
		 *
		 * @param bool $plain_text Rendu texte brut.
		 * @return array
		 */
		protected function get_template_args( $plain_text ) {
			return array(
				'first_name'         => (string) ( $this->deletion_data['first_name'] ?? '' ),
				'confirm_url'        => (string) ( $this->deletion_data['confirm_url'] ?? '' ),
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => $plain_text,
				'email'              => $this,
			);
		}

		/**
		 * Rendu HTML.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html( $this->template_html, $this->get_template_args( false ), '', $this->template_base );
		}

		/**
		 * Rendu texte brut.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html( $this->template_plain, $this->get_template_args( true ), '', $this->template_base );
		}

		/**
		 * Contenu additionnel par défaut.
		 *
		 * @return string
		 */
		public function get_default_additional_content() {
			return '';
		}

		/**
		 * Champs de réglages (WooCommerce > E-mails).
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
