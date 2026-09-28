<?php
/**
 * Endpoint REST — opt-in web push de l'utilisateur connecté.
 *
 * Route :
 *   GET|POST /180c/v1/push/account-optin
 *
 * Calqué sur `/newsletter/account-optin` (inc/rest/newsletter.php) : même
 * validateur d'authentification hybride, même forme de réponse `{ optin }`,
 * même meta miroir.
 *
 * CE QUE CETTE META N'EST PAS
 * ---------------------------
 * `_180c_push_web_optin` est un miroir d'AFFICHAGE, jamais une autorisation.
 * La vérité est double et vit ailleurs : la permission accordée au navigateur,
 * et l'état de la subscription côté OneSignal. Un utilisateur peut révoquer la
 * permission depuis les réglages de son navigateur sans que le site en soit
 * informé — la meta resterait à `1` alors qu'il ne reçoit plus rien.
 *
 * Elle sert donc à deux choses, et à rien d'autre :
 *   - rendre l'état initial du toggle sans appel réseau ;
 *   - savoir s'il vaut la peine d'appeler l'API OneSignal pour ce compte lors
 *     d'une synchro de tag (cf. inc/push/onesignal-user.php).
 *
 * Le module JS revalide `Notification.permission` au chargement et corrige
 * l'affichage : c'est là, et non ici, que la divergence est rattrapée.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', '_180c_rest_register_push' );

/**
 * Enregistre la route d'opt-in web push.
 *
 * @return void
 */
function _180c_rest_register_push() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/push/account-optin',
		array(
			'methods'             => 'GET, POST',
			'callback'            => '_180c_rest_push_account_optin',
			'permission_callback' => '_180c_rest_jwt_or_cookie',
			'args'                => array(
				'optin' => array(
					'required' => false,
					'type'     => 'boolean',
				),
			),
		)
	);
}

/**
 * GET|POST /push/account-optin — lit ou bascule l'opt-in web push.
 *
 * GET  → `{ optin }`, état mémorisé côté serveur.
 * POST → applique `{ optin }`, planifie la synchro du tag, renvoie `{ optin }`.
 *
 * Réservé aux abonnés : le web push est un service d'abonné, et un compte non
 * abonné n'a rien à recevoir. La vérification passe par la fonction canonique
 * `_180c_user_is_subscriber()` — on est ici en requête front, avec un
 * utilisateur courant établi, donc dans le cas où elle est pleinement fiable.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_push_account_optin( WP_REST_Request $request ) {
	$user = wp_get_current_user();

	if ( ! $user instanceof WP_User || 0 === (int) $user->ID ) {
		return new WP_Error(
			'not_authenticated',
			__( 'Authentification requise', '180c' ),
			array( 'status' => 401 )
		);
	}

	$user_id = (int) $user->ID;

	if ( ! _180c_user_is_subscriber( $user_id ) ) {
		return new WP_Error(
			'not_subscriber',
			__( 'Les notifications sont réservées aux abonnés.', '180c' ),
			array( 'status' => 403 )
		);
	}

	if ( WP_REST_Server::READABLE === $request->get_method() ) {
		return rest_ensure_response( array( 'optin' => _180c_push_user_has_optin( $user_id ) ) );
	}

	$optin = rest_sanitize_boolean( $request->get_param( 'optin' ) );

	_180c_push_set_user_optin( $user_id, $optin );

	// Synchro différée dans les deux sens. À l'opt-in, elle confirme le tag que
	// le SDK vient de poser côté client ; à l'opt-out, elle n'a rien à faire
	// (la meta étant retombée à 0, la tâche sortira sans appel réseau) — on la
	// planifie quand même, pour que le chemin soit unique et sans exception.
	_180c_push_schedule_sync( $user_id );

	/**
	 * Déclenché après une bascule de l'opt-in web push.
	 *
	 * @param int  $user_id ID du compte.
	 * @param bool $optin   Nouvel état.
	 */
	do_action( '180c/push/optin_changed', $user_id, $optin ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	return rest_ensure_response( array( 'optin' => $optin ) );
}
