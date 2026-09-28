<?php
/**
 * Admin — Page « Outils → Test e-mails ».
 *
 * Enregistre la page d'outil et son rendu. Prévisualisation en lecture seule
 * des e-mails WooCommerce avec les données réelles de l'utilisateur testé
 * (cf. _180C_Email_Tester). Réservé aux `manage_options`. Aucun e-mail envoyé.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

const _180C_EMAIL_TESTER_SLUG = '180c-email-tester';

/**
 * Enregistre la page sous le menu « Outils ».
 *
 * @return void
 */
function _180c_email_tester_register_page(): void {
	$hook = add_submenu_page(
		'tools.php',
		__( 'Test e-mails 180°C', '180c' ),
		__( 'Test e-mails', '180c' ),
		'manage_options',
		_180C_EMAIL_TESTER_SLUG,
		'_180c_email_tester_render_page'
	);

	if ( $hook ) {
		// Intercepte les requêtes de prévisualisation AVANT le chrome admin pour
		// rendre l'e-mail en page autonome (nouvel onglet = l'e-mail, pas la liste).
		add_action( 'load-' . $hook, '_180c_email_tester_maybe_render_raw' );
		add_action( 'load-' . $hook, '_180c_email_tester_enqueue_assets' );
	}
}
add_action( 'admin_menu', '_180c_email_tester_register_page' );

/**
 * Rend l'e-mail prévisualisé en page HTML autonome (puis termine la requête).
 *
 * Déclenché sur `load-{hook}` : à ce stade aucun HTML d'admin n'a encore été
 * émis, on peut donc produire un document complet. Sans paramètre `preview`,
 * la fonction rend la main au callback de page (affichage de la liste).
 *
 * @return void
 */
function _180c_email_tester_maybe_render_raw(): void {
	if ( ! isset( $_GET['preview'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return; // Le callback de page fera le wp_die().
	}

	$nonce = isset( $_GET['_180c_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_180c_nonce'] ) ) : '';

	$email_id    = sanitize_key( wp_unslash( $_GET['preview'] ) );
	$tested_user = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : get_current_user_id();
	if ( ! user_can( $tested_user, 'manage_options' ) ) {
		$tested_user = get_current_user_id();
	}
	$object_id = isset( $_GET['oid'] ) ? absint( $_GET['oid'] ) : 0;

	$nonce_ok   = (bool) wp_verify_nonce( $nonce, 'preview_email' );
	$wc_ready   = class_exists( '_180C_Email_Tester' ) && function_exists( 'WC' ) && WC()->mailer();
	$preview    = null;
	$preview_objects = array(
		'kind'  => '',
		'items' => array(),
	);

	if ( ! $nonce_ok ) {
		$preview = array(
			'ok'           => false,
			'html'         => '',
			'subject'      => '',
			'recipient'    => '',
			'kind'         => '',
			'object_label' => '',
			'message'      => __( 'Lien expiré. Revenez à la liste et relancez la prévisualisation.', '180c' ),
		);
	} elseif ( ! $wc_ready ) {
		$preview = array(
			'ok'      => false,
			'html'    => '',
			'subject' => '',
			'recipient'    => '',
			'kind'         => '',
			'object_label' => '',
			'message'      => __( 'WooCommerce est requis pour cet outil.', '180c' ),
		);
	} else {
		$preview         = _180C_Email_Tester::generate_email_preview( $email_id, $tested_user, $object_id );
		$test_data       = _180C_Email_Tester::get_test_data_for_user( $tested_user );
		$preview_objects = _180C_Email_Tester::get_objects_for_email( $email_id, $test_data );
	}

	require get_template_directory() . '/admin/views/email-tester-raw.php';
	exit;
}

/**
 * Enqueue le CSS de la page (uniquement sur l'écran de l'outil).
 *
 * @return void
 */
function _180c_email_tester_enqueue_assets(): void {
	add_action(
		'admin_enqueue_scripts',
		static function () {
			wp_enqueue_style(
				'180c-email-tester',
				get_template_directory_uri() . '/assets/css/email-tester.css',
				array(),
				defined( '_180C_VERSION' ) ? _180C_VERSION : null
			);
		}
	);
}

/**
 * Callback de rendu : prépare les données puis inclut la vue.
 *
 * @return void
 */
function _180c_email_tester_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ) );
	}

	if ( ! class_exists( '_180C_Email_Tester' ) || ! function_exists( 'WC' ) || ! WC()->mailer() ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Test e-mails 180°C', '180c' ) . '</h1>';
		echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce est requis pour cet outil.', '180c' ) . '</p></div></div>';
		return;
	}

	// Utilisateur testé : courant par défaut, surchargeable parmi les administrateurs.
	$tested_user = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : get_current_user_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! user_can( $tested_user, 'manage_options' ) ) {
		$tested_user = get_current_user_id();
	}

	$all_emails  = _180C_Email_Tester::get_all_wc_emails();
	$test_data   = _180C_Email_Tester::get_test_data_for_user( $tested_user );
	$admin_users = get_users(
		array(
			'role__in' => array( 'administrator' ),
			'number'   => 20,
			'fields'   => array( 'ID', 'user_email', 'display_name' ),
		)
	);

	// La prévisualisation s'ouvre en page autonome (cf. _180c_email_tester_maybe_render_raw).
	require get_template_directory() . '/admin/views/email-tester.php';
}

/**
 * Construit l'URL de prévisualisation d'un e-mail (avec nonce).
 *
 * @param string $email_id    ID de l'e-mail.
 * @param int    $tested_user Utilisateur testé.
 * @param int    $object_id   Objet forcé (0 = défaut).
 * @return string
 */
function _180c_email_tester_preview_url( string $email_id, int $tested_user, int $object_id = 0 ): string {
	$args = array(
		'page'    => _180C_EMAIL_TESTER_SLUG,
		'preview' => $email_id,
		'uid'     => $tested_user,
	);
	if ( $object_id ) {
		$args['oid'] = $object_id;
	}
	$url = add_query_arg( $args, admin_url( 'tools.php' ) );
	return wp_nonce_url( $url, 'preview_email', '_180c_nonce' );
}
