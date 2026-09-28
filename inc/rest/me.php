<?php
/**
 * Endpoint REST — statut de l'utilisateur courant.
 *
 * Route :
 *   GET /180c/v1/me/
 *
 * Renvoie le statut d'abonnement + identité minimale de l'utilisateur
 * authentifié (JWT app ou cookie web). Remplace l'ancienne sonde app
 * `body.contains("access-granted")` (chaîne inexistante côté serveur).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', '_180c_rest_register_me' );

/**
 * Enregistre la route /me.
 *
 * @return void
 */
function _180c_rest_register_me() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/me',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_get_me',
			'permission_callback' => '_180c_rest_jwt_or_cookie',
		)
	);
}

/**
 * GET /me/ — Statut d'abonnement + identité de l'utilisateur courant.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_get_me( WP_REST_Request $request ) {
	unset( $request );

	$user = wp_get_current_user();

	if ( ! $user instanceof WP_User || 0 === (int) $user->ID ) {
		return new WP_Error(
			'not_authenticated',
			__( 'Utilisateur non authentifié', '180c' ),
			array( 'status' => 401 )
		);
	}

	return rest_ensure_response(
		array(
			'id'            => (int) $user->ID,
			'is_subscriber' => _180c_user_is_subscriber( $user->ID ),
			'email'         => $user->user_email,
			'display_name'  => $user->display_name,
		)
	);
}
