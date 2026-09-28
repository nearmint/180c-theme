<?php
/**
 * Email transactionnel — cadeau reçu (bénéficiaire).
 *
 * Envoyé le jour de la livraison du cadeau (action `180c/gift_email_recipient`,
 * émise par _180c_gift_deliver()). Contient le prénom, le message personnel du
 * donateur, le lien d'accès (définition de mot de passe si nouveau compte, sinon
 * connexion) et la période d'accès offerte.
 *
 * Gabarits : woocommerce/emails/180c-gift-recipient.php (+ plain/).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

if ( ! class_exists( '_180C_Email_Gift_Recipient' ) ) :

	/**
	 * Email cadeau au bénéficiaire.
	 */
	class _180C_Email_Gift_Recipient extends WC_Email {

		/**
		 * Données contextuelles de livraison (lien d'accès, dates).
		 *
		 * @var array
		 */
		public array $gift_context = array();

		/**
		 * Ligne cadeau courante.
		 *
		 * @var object|null
		 */
		public ?object $gift_row = null;

		/**
		 * Résultat du dernier envoi (wp_mail a-t-il accepté le message ?).
		 *
		 * Lu par _180c_gift_send_recipient_email() pour distinguer un envoi réel
		 * d'un envoi silencieusement avorté (e-mail désactivé, wp_mail en échec).
		 *
		 * @var bool
		 */
		public bool $last_send_ok = false;

		/**
		 * Constructeur.
		 */
		public function __construct() {
			$this->id             = '180c_gift_recipient';
			$this->customer_email = true;
			$this->title          = __( 'Cadeau reçu (180°C)', '180c' );
			$this->description    = __( 'Email envoyé au bénéficiaire le jour où l’abonnement cadeau lui est offert.', '180c' );

			$this->template_html  = 'emails/180c-gift-recipient.php';
			$this->template_plain = 'emails/plain/180c-gift-recipient.php';
			$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';

			add_action( '180c/gift_email_recipient', array( $this, 'trigger' ), 10, 2 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).

			parent::__construct();
		}

		/**
		 * Objet par défaut.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Un cadeau vous attend : 12 mois de recettes 180°C', '180c' );
		}

		/**
		 * Titre par défaut.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Vous avez reçu un cadeau', '180c' );
		}

		/**
		 * Déclenche l'envoi.
		 *
		 * @param int   $gift_id ID de la ligne cadeau.
		 * @param array $context Contexte de livraison (set_password_url, end_ts…).
		 * @return void
		 */
		public function trigger( $gift_id, $context = array() ) {
			$this->setup_locale();

			$row = function_exists( '_180c_gift_get_row' ) ? _180c_gift_get_row( (int) $gift_id ) : null;

			if ( ! $row || empty( $row->recipient_email ) ) {
				$this->restore_locale();
				return;
			}

			$this->gift_row     = $row;
			$this->gift_context = is_array( $context ) ? $context : array();
			$this->recipient    = $row->recipient_email;
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
			$row     = $this->gift_row;
			$context = $this->gift_context;

			$start_ts = isset( $context['start_ts'] ) ? (int) $context['start_ts'] : time();
			$end_ts   = isset( $context['end_ts'] ) ? (int) $context['end_ts'] : 0;

			return array(
				'first_name'         => $row ? $row->recipient_first_name : '',
				'message'            => $row ? (string) $row->message : '',
				'set_password_url'   => isset( $context['set_password_url'] ) ? (string) $context['set_password_url'] : '',
				'login_url'          => home_url( '/connexion/' ),
				'start_date'         => wp_date( 'j F Y', $start_ts ),
				'end_date'           => $end_ts ? wp_date( 'j F Y', $end_ts ) : '',
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
			return __( 'Bonne découverte, et bon appétit !', '180c' );
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
