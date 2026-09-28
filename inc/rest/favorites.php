<?php
/**
 * Endpoints REST favoris — CRUD + synchronisation site ↔ app.
 *
 * Routes :
 *   GET    /180c/v1/favorites/
 *   POST   /180c/v1/favorites/
 *   DELETE /180c/v1/favorites/{recipe_id}/
 *   GET    /180c/v1/favorites/count/
 *   POST   /180c/v1/favorites/sync/      (last-write-wins)
 *
 * La logique d'écriture (add/remove + tombstones) vit dans inc/favorites.php ;
 * ce fichier ne fait que valider, authentifier et formater.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', '_180c_rest_register_favorites' );

/**
 * Enregistre les routes REST favoris.
 *
 * @return void
 */
function _180c_rest_register_favorites() {
	// GET + POST /favorites/.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/favorites',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => '_180c_rest_get_favorites',
				'permission_callback' => '_180c_rest_jwt_or_cookie',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => '_180c_rest_add_favorite',
				'permission_callback' => '_180c_rest_jwt_or_cookie',
				'args'                => array(

					/*
					 * `minimum` RETIRÉ, volontairement.
					 *
					 * Il n'était pas appliqué — déclarer un `sanitize_callback`
					 * prive l'argument du `rest_parse_request_arg` que le cœur
					 * assigne par défaut (class-wp-rest-request.php:858-861), et
					 * c'est lui qui applique le schéma.
					 *
					 * Le rendre opposable changerait la réponse envoyée aux apps :
					 * `recipe_id=0` rend depuis toujours 404 `recipe_not_found`,
					 * via `_180c_favorites_is_valid_recipe()`. Un
					 * `validate_callback` ne peut pas préserver ce code — le cœur
					 * aplatit toute erreur de validation en un unique
					 * `rest_invalid_param` 400 (class-wp-rest-request.php:961-968).
					 *
					 * On ne déclare donc que ce qu'on applique : `absint` borne à
					 * zéro, et l'existence de la recette est vérifiée par le
					 * callback.
					 */
					'recipe_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'ID de la recette à ajouter aux favoris.', '180c' ),
					),
				),
			),
		)
	);

	// DELETE /favorites/{recipe_id}/.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/favorites/(?P<recipe_id>\d+)',
		array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => '_180c_rest_delete_favorite',
			'permission_callback' => '_180c_rest_jwt_or_cookie',
			'args'                => array(

				/*
				 * Ici `minimum` DEVIENT opposable, contrairement au POST ci-dessus.
				 * La différence n'est pas une incohérence : `/favorites/0` rend
				 * aujourd'hui 200 `{"id":0,"removed":true}` — un succès mensonger,
				 * aucune ligne ne portant l'ID 0. Il n'y a donc pas de réponse
				 * d'erreur à préserver, et 400 est la réponse juste.
				 *
				 * La regex de la route (`\d+`) borne déjà aux entiers positifs :
				 * `0` est la seule valeur invalide atteignable.
				 */
				'recipe_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'absint',
					'minimum'           => 1,
				),
			),
		)
	);

	// GET /favorites/count/.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/favorites/count',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_count_favorites',
			'permission_callback' => '_180c_rest_jwt_or_cookie',
		)
	);

	// POST /favorites/sync/ — réconciliation last-write-wins (apps hors-ligne).
	register_rest_route(
		_180C_API_NAMESPACE,
		'/favorites/sync',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_rest_sync_favorites',
			'permission_callback' => '_180c_rest_jwt_or_cookie',
			'args'                => array(
				'favorites' => array(
					'required'    => true,
					'type'        => 'array',
					'description' => __( 'Changements locaux du client : { recipe_id, favorited, updated_at }.', '180c' ),
				),
			),
		)
	);
}

/**
 * Vérifie que les tables favoris existent.
 *
 * Retourne WP_Error 503 si une table est absente (fallback gracieux).
 *
 * @return true|WP_Error
 */
function _180c_favorites_table_exists() {
	if ( _180c_favorites_tables_ready() ) {
		return true;
	}

	_180c_log( 'Tables favoris introuvables', array( 'table' => _180c_favorites_table() ), 'error' );

	return new WP_Error(
		'favorites_unavailable',
		__( 'Service favoris temporairement indisponible', '180c' ),
		array( 'status' => 503 )
	);
}

/**
 * Construit la réponse formatée d'un favori à partir d'un recipe_id.
 *
 * @param int    $recipe_id  ID de la recette.
 * @param string $created_at Date d'ajout (DATETIME SQL).
 * @return array|null Tableau de données ou null si post introuvable.
 */
function _180c_favorites_format_item( $recipe_id, $created_at ) {
	$post = get_post( $recipe_id );

	if ( ! $post || 'recipe' !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}

	$image_url = '';
	$image_alt = '';

	$thumbnail_id = (int) get_post_thumbnail_id( $post->ID );
	if ( $thumbnail_id ) {
		$image_src = wp_get_attachment_image_src( $thumbnail_id, 'medium' );
		$image_url = $image_src ? esc_url( $image_src[0] ) : '';
		$image_alt = esc_attr( get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) );
	}

	return array(
		'id'         => (int) $post->ID,
		'title'      => esc_html( get_the_title( $post ) ),
		'slug'       => esc_attr( $post->post_name ),
		'link'       => esc_url( get_permalink( $post ) ),
		'image_url'  => $image_url,
		'image_alt'  => $image_alt,
		'created_at' => esc_html( $created_at ),
	);
}

/**
 * Retourne la liste formatée des favoris actifs d'un utilisateur.
 *
 * @param int $user_id ID utilisateur.
 * @return array[] Favoris formatés (recettes publiées uniquement), récents d'abord.
 */
function _180c_favorites_active_list( $user_id ) {
	global $wpdb;

	$table = _180c_favorites_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT recipe_id, created_at FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id
		)
	);

	$favorites = array();
	foreach ( $rows as $row ) {
		$item = _180c_favorites_format_item( (int) $row->recipe_id, $row->created_at );
		if ( null !== $item ) {
			$favorites[] = $item;
		}
	}

	return $favorites;
}

/**
 * Indique si un ID correspond à une recette publiée.
 *
 * @param int $recipe_id ID candidat.
 * @return bool
 */
function _180c_favorites_is_valid_recipe( $recipe_id ) {
	$post = get_post( $recipe_id );
	return $post && 'recipe' === $post->post_type && 'publish' === $post->post_status;
}

/**
 * GET /favorites/ — Liste les favoris du user authentifié.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_get_favorites( WP_REST_Request $request ) {
	unset( $request );

	$check = _180c_favorites_table_exists();
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	$favorites = _180c_favorites_active_list( get_current_user_id() );

	// `ids` + `count` : shape alignée sur le contrat web (hydratation
	// O(1)). `favorites` (objets complets) reste pour la rétro-compat apps.
	$ids = array_map(
		static function ( $item ) {
			return (int) $item['id'];
		},
		$favorites
	);

	return rest_ensure_response(
		array(
			'favorites' => $favorites,
			'ids'       => $ids,
			'count'     => count( $ids ),
		)
	);
}

/**
 * POST /favorites/ — Ajoute une recette aux favoris du user.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_add_favorite( WP_REST_Request $request ) {
	$check = _180c_favorites_table_exists();
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	$user_id = get_current_user_id();

	// Ce contrôle n'est PAS un filet : c'est LA validation de `recipe_id` sur
	// cette route — voir le commentaire à l'enregistrement.
	$recipe_id = absint( $request->get_param( 'recipe_id' ) );

	if ( ! _180c_favorites_is_valid_recipe( $recipe_id ) ) {
		return new WP_Error(
			'recipe_not_found',
			__( 'Recette introuvable ou non publiée', '180c' ),
			array( 'status' => 404 )
		);
	}

	_180c_favorites_add( $user_id, $recipe_id );

	/**
	 * Déclenché après l'ajout d'un favori.
	 *
	 * @param int $user_id   ID de l'utilisateur.
	 * @param int $recipe_id ID de la recette.
	 */
	do_action( '180c/favorite_added', $user_id, $recipe_id );

	_180c_log(
		'Favori ajouté',
		array(
			'user_id'   => $user_id,
			'recipe_id' => $recipe_id,
		)
	);

	$response = rest_ensure_response(
		array(
			'id'         => $recipe_id,
			'added'      => true,
			// Champs alignés sur le contrat web; `id`/`added` conservés
			// pour la rétro-compat apps.
			'recipe_id'  => $recipe_id,
			'created_at' => _180c_favorites_now_gmt(),
		)
	);
	$response->set_status( 201 );

	return $response;
}

/**
 * DELETE /favorites/{recipe_id}/ — Retire une recette des favoris.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_delete_favorite( WP_REST_Request $request ) {
	$check = _180c_favorites_table_exists();
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	$user_id   = get_current_user_id();
	$recipe_id = absint( $request->get_param( 'recipe_id' ) );

	_180c_favorites_remove( $user_id, $recipe_id );

	/**
	 * Déclenché après la suppression d'un favori.
	 *
	 * @param int $user_id   ID de l'utilisateur.
	 * @param int $recipe_id ID de la recette.
	 */
	do_action( '180c/favorite_removed', $user_id, $recipe_id );

	_180c_log(
		'Favori supprimé',
		array(
			'user_id'   => $user_id,
			'recipe_id' => $recipe_id,
		)
	);

	return rest_ensure_response(
		array(
			'id'      => $recipe_id,
			'removed' => true,
		)
	);
}

/**
 * GET /favorites/count/ — Nombre de favoris du user authentifié.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_count_favorites( WP_REST_Request $request ) {
	unset( $request );

	$check = _180c_favorites_table_exists();
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	global $wpdb;
	$user_id = get_current_user_id();
	$table   = _180c_favorites_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id
		)
	);

	return rest_ensure_response( array( 'count' => $count ) );
}

/**
 * POST /favorites/sync/ — Réconciliation last-write-wins site ↔ app.
 *
 * Le client envoie ses changements locaux dans `favorites` :
 *   [ { "recipe_id": 123, "favorited": true,  "updated_at": "2026-05-20T10:00:00Z" },
 *     { "recipe_id": 456, "favorited": false, "updated_at": "2026-05-21T08:30:00Z" } ]
 *
 * Pour chaque recette, l'horodatage UTC le plus récent (client vs serveur)
 * l'emporte ; un état « retiré » est conservé via une pierre tombale. La réponse
 * renvoie l'ensemble complet des favoris actifs (réconciliés) + l'instant de sync.
 * Les listes de favoris étant petites (max constaté : 322), on renvoie l'état
 * complet plutôt qu'un delta : le client remplace simplement son cache local.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_sync_favorites( WP_REST_Request $request ) {
	$check = _180c_favorites_table_exists();
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	$user_id = get_current_user_id();
	$items   = $request->get_param( 'favorites' );

	// Filet : `favorites` n'a PAS de `sanitize_callback`, il reçoit donc le
	// `rest_parse_request_arg` du cœur et son `type: array` est bien appliqué
	// (un objet est refusé en 400 `rest_invalid_param` avant d'arriver ici).
	// Ce contrôle reste en défense de profondeur.
	if ( ! is_array( $items ) ) {
		return new WP_Error(
			'invalid_payload',
			__( 'Le champ « favorites » doit être un tableau.', '180c' ),
			array( 'status' => 400 )
		);
	}

	// Garde-fou anti-abus (max constaté ~322 favoris / user).
	$max_items = (int) apply_filters( '180c/favorites_sync_max_items', 2000 );
	if ( count( $items ) > $max_items ) {
		return new WP_Error(
			'payload_too_large',
			/* translators: %d: nombre maximal d'éléments */
			sprintf( __( 'Trop d\'éléments à synchroniser (max %d).', '180c' ), $max_items ),
			array( 'status' => 413 )
		);
	}

	global $wpdb;
	$active_table = _180c_favorites_table();
	$tomb_table   = _180c_favorites_tombstones_table();

	// État serveur courant, chargé en mémoire pour comparer sans N requêtes.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$active_rows = $wpdb->get_results( $wpdb->prepare( "SELECT recipe_id, created_at FROM {$active_table} WHERE user_id = %d", $user_id ), OBJECT_K );
	$tomb_rows   = $wpdb->get_results( $wpdb->prepare( "SELECT recipe_id, removed_at FROM {$tomb_table} WHERE user_id = %d", $user_id ), OBJECT_K );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$applied = 0;
	$skipped = 0;

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			++$skipped;
			continue;
		}

		$recipe_id = isset( $item['recipe_id'] ) ? absint( $item['recipe_id'] ) : 0;
		$favorited = rest_sanitize_boolean( $item['favorited'] ?? false );
		$client_ts = _180c_favorites_normalize_ts( $item['updated_at'] ?? null );

		if ( $recipe_id < 1 ) {
			++$skipped;
			continue;
		}

		// Un ajout cible nécessairement une recette publiée ; un retrait est
		// toléré même si la recette n'existe plus (nettoyage d'un favori obsolète).
		if ( $favorited && ! _180c_favorites_is_valid_recipe( $recipe_id ) ) {
			++$skipped;
			continue;
		}

		// Horodatage de l'état serveur pour cette recette.
		if ( isset( $active_rows[ $recipe_id ] ) ) {
			$server_epoch = _180c_favorites_datetime_to_epoch( $active_rows[ $recipe_id ]->created_at );
			$server_state = true;
		} elseif ( isset( $tomb_rows[ $recipe_id ] ) ) {
			$server_epoch = _180c_favorites_datetime_to_epoch( $tomb_rows[ $recipe_id ]->removed_at );
			$server_state = false;
		} else {
			$server_epoch = 0;
			$server_state = null;
		}

		// Last-write-wins : le serveur ne change que si le client est plus récent
		// ET que l'état diffère réellement.
		if ( $client_ts['epoch'] <= $server_epoch || $favorited === $server_state ) {
			++$skipped;
			continue;
		}

		if ( $favorited ) {
			_180c_favorites_add( $user_id, $recipe_id, $client_ts['mysql'] );
		} else {
			_180c_favorites_remove( $user_id, $recipe_id, $client_ts['mysql'] );
		}
		++$applied;
	}

	if ( $applied > 0 ) {
		/**
		 * Déclenché après une synchronisation favoris ayant appliqué ≥ 1 changement.
		 *
		 * @param int $user_id ID de l'utilisateur.
		 * @param int $applied Nombre de changements appliqués.
		 */
		do_action( '180c/favorites_synced', $user_id, $applied );
		_180c_log(
			'Favoris synchronisés',
			array(
				'user_id' => $user_id,
				'applied' => $applied,
				'skipped' => $skipped,
			)
		);
	}

	$favorites = _180c_favorites_active_list( $user_id );

	return rest_ensure_response(
		array(
			'favorites' => $favorites,
			'synced_at' => _180c_favorites_now_gmt(),
			'stats'     => array(
				'applied'      => $applied,
				'skipped'      => $skipped,
				'active_count' => count( $favorites ),
			),
		)
	);
}
