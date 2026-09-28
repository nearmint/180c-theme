<?php
/**
 * Durcissements de sécurité transverses.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Restreint la route REST `wp/v2/users` aux requêtes authentifiées (CH-09 / SEC1).
 *
 * Par défaut WordPress expose publiquement la liste des utilisateurs ayant
 * publié (collection `/wp/v2/users`) et chaque profil (`/wp/v2/users/<id>`),
 * ce qui permet d'énumérer les comptes/auteurs. On court-circuite ces routes
 * via `rest_pre_dispatch` quand la requête n'est PAS authentifiée, en
 * renvoyant un 401.
 *
 * Les usages internes authentifiés restent fonctionnels : l'éditeur connecté
 * (cookie) comme les apps mobiles (JWT, résolu en amont par
 * `determine_current_user`) passent `is_user_logged_in()` → aucune restriction.
 * Les pages auteur publiques sont des pages WP classiques, hors REST,
 * et ne sont pas affectées.
 *
 * @param mixed           $result  Réponse pré-calculée (null pour continuer).
 * @param WP_REST_Server  $server  Instance du serveur REST.
 * @param WP_REST_Request $request Requête courante.
 * @return mixed Réponse inchangée, ou WP_Error 401 sur la route users anonyme.
 */
function _180c_restrict_rest_users( $result, $server, $request ) {
	if ( null !== $result ) {
		return $result;
	}

	if ( is_user_logged_in() ) {
		return $result;
	}

	$route = (string) $request->get_route();
	if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
		return new WP_Error(
			'rest_user_cannot_view',
			__( 'Authentification requise pour accéder aux utilisateurs.', '180c' ),
			array( 'status' => 401 )
		);
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', '_180c_restrict_rest_users', 10, 3 );
