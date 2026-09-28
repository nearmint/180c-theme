<?php
/**
 * Anti-énumération des comptes sur les formulaires web.
 *
 * Le cœur WordPress et WooCommerce distinguent, dans leurs messages d'erreur,
 * un identifiant inconnu d'un mot de passe faux, et « aucun compte pour cette
 * adresse » d'un envoi réussi du lien de réinitialisation. Ce module rend ces
 * réponses indiscernables sur :
 *
 *  - la connexion (wp-login.php, formulaire WooCommerce, /connexion/) ;
 *  - le mot de passe perdu de wp-login.php ;
 *  - le mot de passe perdu de WooCommerce.
 *
 * Les requêtes REST (dont les routes JWT lues par les apps via `rest_route`),
 * XML-RPC et WP-CLI ne sont pas touchées : leurs codes et messages restent ceux
 * du cœur et des extensions, dont dépendent les apps.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Message unique d'échec de connexion.
 *
 * @return string
 */
function _180c_generic_login_error(): string {
	return __( 'Identifiants incorrects.', '180c' );
}

/**
 * Indique si la requête courante est un formulaire web (et non REST, XML-RPC,
 * WP-CLI ou une route JWT).
 *
 * @return bool
 */
function _180c_is_web_form_request(): bool {
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return false;
	}

	if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
		return false;
	}

	// Simple JWT Login répond sur `?rest_route=/simple-jwt-login/…` : la
	// constante REST_REQUEST n'est posée qu'au moment du dispatch.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule, aucun effet.
	if ( isset( $_GET['rest_route'] ) ) {
		return false;
	}

	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- comparaison de préfixe uniquement.

	return ! str_contains( $uri, '/' . rest_get_url_prefix() . '/' );
}

/**
 * Remplace les erreurs de connexion qui révèlent l'existence d'un compte.
 *
 * Priorité 100 : après la vérification du mot de passe (20), le 2FA (30) et
 * son échec fermé (31). Seuls les codes révélateurs sont remplacés ; les
 * autres (champs vides, blocage, 2FA) sont conservés tels quels. Si l'IP est
 * bloquée, le message de blocage est rendu pour tout identifiant, existant ou
 * non — sinon il n'apparaîtrait que pour les comptes existants.
 *
 * @param WP_User|WP_Error|null $user Résultat des filtres précédents.
 * @return WP_User|WP_Error|null
 */
function _180c_normalize_login_errors( $user ) {
	if ( ! is_wp_error( $user ) || ! _180c_is_web_form_request() ) {
		return $user;
	}

	$revealing = array( 'invalid_username', 'invalid_email', 'incorrect_password', '_180c_too_many_attempts' );
	if ( ! array_intersect( $revealing, $user->get_error_codes() ) ) {
		return $user;
	}

	if ( function_exists( '_180c_is_ip_blocked' ) && _180c_is_ip_blocked( _180c_get_client_ip() ) ) {
		return new WP_Error( '_180c_too_many_attempts', __( 'Trop de tentatives de connexion. Veuillez réessayer dans 1 heure.', '180c' ) );
	}

	return new WP_Error( 'incorrect_password', _180c_generic_login_error() );
}
add_filter( 'authenticate', '_180c_normalize_login_errors', 100 );

/**
 * Mot de passe perdu de wp-login.php : réponse identique, compte existant ou non.
 *
 * Le cœur ajoute l'erreur `invalidcombo` APRÈS le filtre `lostpassword_errors` :
 * elle ne peut pas être retirée par filtre. On traite donc la soumission
 * ici, avant le cœur, et on redirige toujours vers la confirmation standard.
 * Un champ vide garde le comportement natif (message non révélateur).
 *
 * @return void
 */
function _180c_uniform_wp_login_lostpassword(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- comparaison stricte.
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp-login.php n'emploie pas de nonce sur ce formulaire.
	$login = isset( $_POST['user_login'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) ) : '';
	if ( '' === $login ) {
		return;
	}

	retrieve_password( $login );

	$redirect = ! empty( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : 'wp-login.php?checkemail=confirm'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validé par wp_validate_redirect() ci-dessous ; wp-login.php n'emploie pas de nonce ici.
	wp_safe_redirect( wp_validate_redirect( $redirect, 'wp-login.php?checkemail=confirm' ) );
	exit;
}
add_action( 'login_form_lostpassword', '_180c_uniform_wp_login_lostpassword', 1 );
add_action( 'login_form_retrievepassword', '_180c_uniform_wp_login_lostpassword', 1 );

/**
 * Mot de passe perdu de WooCommerce : réponse identique, compte existant ou non.
 *
 * `WC_Form_Handler::process_lost_password()` (priorité 20 sur `wp_loaded`)
 * redirige et s'arrête en cas de succès ; s'il rend la main, c'est qu'il a
 * posé une notice d'erreur. Si le champ était renseigné et le nonce valide,
 * cette erreur révèle l'absence de compte : on la remplace par la redirection
 * de succès de WooCommerce.
 *
 * @return void
 */
function _180c_uniform_wc_lostpassword(): void {
	if ( ! isset( $_POST['wc_reset_password'], $_POST['user_login'], $_POST['woocommerce-lost-password-nonce'] ) || ! function_exists( 'wc_get_account_endpoint_url' ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce-lost-password-nonce'] ) );
	$login = trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) );
	if ( '' === $login || ! wp_verify_nonce( $nonce, 'lost_password' ) ) {
		return;
	}

	if ( function_exists( 'wc_clear_notices' ) ) {
		wc_clear_notices();
	}

	wp_safe_redirect( add_query_arg( 'reset-link-sent', 'true', wc_get_account_endpoint_url( 'lost-password' ) ) );
	exit;
}
add_action( 'wp_loaded', '_180c_uniform_wc_lostpassword', 21 );
