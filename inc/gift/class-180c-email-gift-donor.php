<?php
/**
 * Email transactionnel — confirmation de cadeau (donateur).
 *
 * Envoyé au donateur dès l'achat (action `180c/gift_email_donor`, émise par
 * _180c_gift_on_order_paid()). Récapitule le bénéficiaire, la date d'envoi prévue
 * (ou « immédiat ») et le montant.
 *
 * Gabarits : woocommerce/emails/180c-gift-donor.php (+ plain/).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

if ( ! class_exists( '_180C_Email_Gift_Donor' ) ) :

	/**
	 * Email de confirmation au donateur.
	 */
	class _180C_Email_Gift_Donor extends WC_Email {

		/**
		 * Ligne cadeau courante.
		 *
		 * @var object|null
		 */
		public ?object $gift_row = null;

		/**
		 * Commande cadeau courante.
		 *
		 * @var WC_Order|null
		 */
		public $gift_order = null;

		/**
		 * Résultat du dernier envoi (wp_mail a-t-il accepté le message ?).
		 *
		 * Lu par _180c_gift_send_donor_email().
		 *
		 * @var bool
		 */
		public bool $last_send_ok = false;

		/**
		 * Constructeur.
		 */
		public function __construct() {
			$this->id             = '180c_gift_donor';
			$this->customer_email = true;
			$this->title          = __( 'Cadeau confirmé (180°C)', '180c' );
			$this->description    = __( 'Email de confirmation envoyé au donateur après l’achat d’un abonnement cadeau.', '180c' );

			$this->template_html  = 'emails/180c-gift-donor.php';
			$this->template_plain = 'emails/plain/180c-gift-donor.php';
			$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';

			add_action( '180c/gift_email_donor', array( $this, 'trigger' ), 10, 1 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).

			parent::__construct();
		}

		/**
		 * Objet par défaut.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Votre cadeau 180°C est confirmé', '180c' );
		}

		/**
		 * Titre par défaut.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Merci pour votre cadeau', '180c' );
		}

		/**
		 * Déclenche l'envoi.
		 *
		 * @param int $gift_id ID de la ligne cadeau.
		 * @return void
		 */
		public function trigger( $gift_id ) {
			$this->setup_locale();

			$row   = function_exists( '_180c_gift_get_row' ) ? _180c_gift_get_row( (int) $gift_id ) : null;
			$order = $row ? wc_get_order( (int) $row->order_id ) : null;

			if ( ! $row || ! $order ) {
				$this->restore_locale();
				return;
			}

			$this->gift_row     = $row;
			$this->gift_order   = $order;
			$this->recipient    = $order->get_billing_email();
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
			$row   = $this->gift_row;
			$order = $this->gift_order;

			$send_label = __( 'dès maintenant', '180c' );
			if ( $row && ! empty( $row->send_date ) ) {
				$send_label = wp_date( 'j F Y', strtotime( $row->send_date . ' 00:00:00' ) );
			}

			return array(
				'donor_first_name'     => $order ? $order->get_billing_first_name() : '',
				'recipient_first_name' => $row ? $row->recipient_first_name : '',
				'recipient_email'      => $row ? $row->recipient_email : '',
				'send_label'           => $send_label,
				'amount'               => $order ? $order->get_formatted_order_total() : '',
				'email_heading'        => $this->get_heading(),
				'additional_content'   => $this->get_additional_content(),
				'sent_to_admin'        => false,
				'plain_text'           => $plain_text,
				'email'                => $this,
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
			return __( 'Merci de faire découvrir 180°C autour de vous.', '180c' );
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
