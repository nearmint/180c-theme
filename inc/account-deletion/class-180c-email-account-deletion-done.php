<?php
/**
 * Email transactionnel — compte supprimé.
 *
 * Envoyé APRÈS wp_delete_user(), via l'action `180c/account_deletion_email_done`
 * (dispatcher : emails.php). Le compte n'existe plus à cet instant : toutes les
 * données proviennent de l'instantané pris avant suppression, jamais d'un
 * get_userdata().
 *
 * Gabarits : woocommerce/emails/180c-account-deletion-done.php (+ plain/).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

// Le préfixe de classes imposé par CLAUDE.md (`_180C_`) commence par un
// underscore, ce que ce sniff interdit. Les classes d'e-mail déjà en place dans
// le thème sont dans le même cas.
// phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital

if ( ! class_exists( '_180C_Email_Account_Deletion_Done' ) ) :

	/**
	 * Email de confirmation de suppression.
	 */
	class _180C_Email_Account_Deletion_Done extends WC_Email {

		/**
		 * Données de l'envoi courant (instantané pris avant suppression).
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
			$this->id             = '180c_account_deletion_done';
			$this->customer_email = true;
			$this->title          = __( 'Suppression de compte — confirmation finale (180°C)', '180c' );
			$this->description    = __( 'Email envoyé une fois le compte effectivement supprimé.', '180c' );

			$this->template_html  = 'emails/180c-account-deletion-done.php';
			$this->template_plain = 'emails/plain/180c-account-deletion-done.php';
			$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';

			add_action( '180c/account_deletion_email_done', array( $this, 'trigger' ), 10, 1 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).

			parent::__construct();
		}

		/**
		 * Objet par défaut.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Votre compte 180°C est supprimé', '180c' );
		}

		/**
		 * Titre par défaut.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Votre compte est supprimé', '180c' );
		}

		/**
		 * Déclenche l'envoi.
		 *
		 * @param array $data { Données d'envoi.
		 *     @type string $email              Destinataire.
		 *     @type string $first_name         Prénom, éventuellement vide.
		 *     @type bool   $newsletter_removed Le contact Mailchimp a-t-il été supprimé.
		 * }
		 * @return void
		 */
		public function trigger( $data ) {
			$this->setup_locale();

			$data = is_array( $data ) ? $data : array();

			$this->deletion_data = array(
				'first_name'         => (string) ( $data['first_name'] ?? '' ),
				'newsletter_removed' => ! empty( $data['newsletter_removed'] ),
				'deleted_on'         => date_i18n( 'j F Y' ),
			);

			$this->recipient    = (string) ( $data['email'] ?? '' );
			$this->last_send_ok = false;

			if ( $this->is_enabled() && $this->get_recipient() ) {
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
				'deleted_on'         => (string) ( $this->deletion_data['deleted_on'] ?? '' ),
				'newsletter_removed' => ! empty( $this->deletion_data['newsletter_removed'] ),
				'contact_email'      => _180c_account_deletion_contact_email(),
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
