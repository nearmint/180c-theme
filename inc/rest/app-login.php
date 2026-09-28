<?php
/**
 * Connexion web depuis le JWT app (auto-login webview).
 *
 * Requête :
 *   POST /?180c_app_login=1
 *   Corps : application/x-www-form-urlencoded, champ `jwt=<token>`
 *   (ou en-tête `Authorization: Bearer <token>` en alternative)
 *
 * Valide le JWT app (signature HS256 + exp), établit une **session cookie web**
 * pour l'utilisateur correspondant, puis répond **200** `{"ok":true}`. Le
 * `WKWebView` de l'app charge ensuite lui-même l'URL de destination (déjà
 * connecté grâce au cookie posé). Aucune redirection côté serveur.
 *
 * Implémenté en `template_redirect` (pas une route REST) : on doit pouvoir poser
 * le cookie d'auth et court-circuiter le rendu de page. Remplace l'auto-login du
 * plugin Simple JWT Login (désactivé).
 *
 * Sécurité : le token n'est **jamais** lu depuis la query string (il finirait
 * dans les logs serveur) — uniquement corps POST ou en-tête `Authorization`. Le
 * token n'est jamais loggé. Token absent/invalide ⇒ **401**.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', '_180c_handle_app_login' );

/**
 * Intercepte `?180c_app_login=1`, valide le JWT (corps POST / en-tête), pose la
 * session cookie web puis répond en JSON. Ne lit jamais le token en query string.
 *
 * @return void
 */
function _180c_handle_app_login() {
	if ( empty( $_GET['180c_app_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Auth par JWT cryptographique ci-dessous, pas par nonce.
		return;
	}

	$jwt = _180c_app_login_read_token();

	$user_id = 0;
	if ( '' !== $jwt ) {
		$payload = _180c_jwt_validate_token( $jwt );
		if ( ! is_wp_error( $payload ) ) {
			$user_id = _180c_jwt_payload_user_id( $payload );
		}
	}

	if ( $user_id <= 0 ) {
		wp_send_json( array( 'ok' => false ), 401 );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, false ); // Session (non persistante).

	wp_send_json( array( 'ok' => true ), 200 );
}

/**
 * Lit le JWT depuis le corps POST (`jwt`) ou l'en-tête `Authorization: Bearer`.
 * Jamais depuis la query string. Renvoie '' si absent.
 *
 * @return string Token brut (validé cryptographiquement par l'appelant).
 */
function _180c_app_login_read_token() {
	// En-tête Authorization: Bearer <token> (alternative au corps).
	$auth_header = '';
	if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		$auth_header = trim( (string) wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
	} elseif ( function_exists( 'apache_request_headers' ) ) {
		$headers = apache_request_headers();
		if ( isset( $headers['Authorization'] ) ) {
			$auth_header = trim( (string) $headers['Authorization'] );
		}
	}
	if ( 0 === stripos( $auth_header, 'bearer ' ) ) {
		return trim( substr( $auth_header, 7 ) );
	}

	// Corps POST `jwt` (application/x-www-form-urlencoded).
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JWT validé cryptographiquement par _180c_jwt_validate_token() ; pas de nonce (client app non-navigateur).
	if ( isset( $_POST['jwt'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return trim( (string) wp_unslash( $_POST['jwt'] ) );
	}

	return '';
}
