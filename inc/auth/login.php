<?php
/**
 * Formulaire de login custom — route /connexion/.
 *
 * Routing via rewrite rule + query var `_180c_auth=login`.
 * Le traitement POST est géré dans ce même fichier via l'action `init`.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// Routing — rewrite rule + template include
// ============================================================

/**
 * Enregistre la rewrite rule pour /connexion/.
 *
 * @return void
 */
function _180c_login_rewrite(): void {
	add_rewrite_rule( '^connexion/?$', 'index.php?_180c_auth=login', 'top' );
}
add_action( 'init', '_180c_login_rewrite' );

/**
 * Déclare la query var _180c_auth.
 *
 * @param string[] $vars Query vars existantes.
 * @return string[]
 */
function _180c_login_query_vars( array $vars ): array {
	$vars[] = '_180c_auth';
	return $vars;
}
add_filter( 'query_vars', '_180c_login_query_vars' );

/**
 * Redirige vers le template custom si la query var correspond.
 *
 * @param string $template Chemin template WordPress.
 * @return string
 */
function _180c_login_template_include( string $template ): string {
	$auth = get_query_var( '_180c_auth' );
	if ( 'login' === $auth ) {
		$custom = get_template_directory() . '/inc/auth/views/login.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}
add_filter( 'template_include', '_180c_login_template_include' );

// ============================================================
// Redirection si déjà connecté
// ============================================================

/**
 * Redirige vers Mon Compte si l'utilisateur est déjà connecté.
 *
 * @return void
 */
function _180c_login_redirect_if_logged_in(): void {
	if ( 'login' !== get_query_var( '_180c_auth' ) ) {
		return;
	}
	if ( is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/mon-compte/' ) );
		exit;
	}
}
add_action( 'template_redirect', '_180c_login_redirect_if_logged_in' );

// ============================================================
// Handler POST
// ============================================================

/**
 * Traite la soumission du formulaire de connexion.
 *
 * @return void
 */
function _180c_handle_login_form(): void {
	// S'applique uniquement sur la route /connexion/ en POST.
	if ( 'login' !== get_query_var( '_180c_auth' ) ) {
		return;
	}
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return;
	}

	// Vérification du nonce.
	$nonce = isset( $_POST['_180c_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_180c_login_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, '_180c_login' ) ) {
		_180c_set_auth_error( 'nonce', __( 'Session expirée. Veuillez réessayer.', '180c' ) );
		return;
	}

	$ip = _180c_get_client_ip();

	// Vérification blocage IP.
	if ( _180c_is_ip_blocked( $ip ) ) {
		_180c_set_auth_error( 'blocked', __( 'Trop de tentatives. Réessayez dans 1 heure.', '180c' ) );
		return;
	}

	// Validation Turnstile si nécessaire.
	if ( _180c_should_show_turnstile( $ip ) ) {
		$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
		if ( ! _180c_validate_turnstile( $token ) ) {
			_180c_set_auth_error( 'turnstile', __( 'Vérification de sécurité échouée. Veuillez réessayer.', '180c' ) );
			return;
		}
	}

	// Récupération des champs.
	$log      = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
	$password = isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized — mot de passe raw requis.
	$remember = ! empty( $_POST['rememberme'] );

	// Redirect de destination.
	$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/mon-compte/' );
	$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/mon-compte/' ) );

	if ( '' === $log || '' === $password ) {
		_180c_set_auth_error( 'empty', __( 'Identifiants incorrects.', '180c' ) );
		return;
	}

	// Tentative de connexion.
	$user = wp_signon(
		array(
			'user_login'    => $log,
			'user_password' => $password,
			'remember'      => $remember,
		),
		is_ssl()
	);

	if ( is_wp_error( $user ) ) {
		// Message générique anti-énumération. La tentative est journalisée et
		// le compteur anti-bruteforce incrémenté via le hook `wp_login_failed`
		// déclenché par wp_signon() — pas de double enregistrement ici.
		_180c_set_auth_error( 'credentials', __( 'Identifiants incorrects.', '180c' ) );
		_180c_log(
			'Login failed.',
			array(
				'ip'   => $ip,
				'code' => $user->get_error_code(),
			),
			'warning'
		);
		return;
	}

	// Succès : flag session pour GA4 + redirection.
	_180c_set_auth_success( 'login' );
	wp_safe_redirect( $redirect_to );
	exit;
}
// Hook sur `template_redirect` (et non `init`) : la query var `_180c_auth`
// n'est peuplée qu'après le parsing de la requête principale. Sur `init`,
// get_query_var() renvoie toujours '' → le handler sortait prématurément et
// la connexion n'était jamais traitée. `template_redirect` reste avant tout
// output : wp_signon() peut donc poser ses cookies et rediriger.
add_action( 'template_redirect', '_180c_handle_login_form' );

// ============================================================
// Helpers d'état session (notices)
// ============================================================

/**
 * Stocke un message d'erreur d'auth en session via transient utilisateur.
 *
 * Utilise un cookie de courte durée côté serveur (transient anonymous).
 *
 * @param string $code    Code d'erreur court.
 * @param string $message Message lisible.
 * @return void
 */
function _180c_set_auth_error( string $code, string $message ): void {
	$key = '_180c_auth_error_' . md5( _180c_get_client_ip() );
	set_transient(
		$key,
		array(
			'code'    => $code,
			'message' => $message,
		),
		60
	);
}

/**
 * Récupère et consomme le message d'erreur d'auth.
 *
 * @return array{code: string, message: string}|null
 */
function _180c_get_auth_error(): ?array {
	$key   = '_180c_auth_error_' . md5( _180c_get_client_ip() );
	$error = get_transient( $key );
	if ( is_array( $error ) ) {
		delete_transient( $key );
		return $error;
	}
	return null;
}

/**
 * Stocke un flag de succès d'auth pour GA4.
 *
 * @param string $type 'login' ou 'register'.
 * @return void
 */
function _180c_set_auth_success( string $type ): void {
	$key = '_180c_auth_success_' . md5( _180c_get_client_ip() );
	set_transient( $key, $type, 30 );
}

/**
 * Récupère et consomme le flag de succès d'auth.
 *
 * @return string|null 'login' ou 'register' ou null.
 */
function _180c_get_auth_success(): ?string {
	$key     = '_180c_auth_success_' . md5( _180c_get_client_ip() );
	$success = get_transient( $key );
	if ( is_string( $success ) && '' !== $success ) {
		delete_transient( $key );
		return $success;
	}
	return null;
}
