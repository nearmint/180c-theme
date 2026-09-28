<?php
/**
 * Endpoint REST du centre de notifications de l'app.
 *
 *   GET /wp-json/180c/v1/notifications
 *
 * Ne remonte que les notifications réellement parties (meta _180c_onesignal_id
 * non vide), triées par date d'envoi décroissante. Aucune donnée personnelle
 * n'est exposée : les push sont déjà arrivés sur les devices, donc
 * permission_callback => __return_true.
 *
 * Cache : transient versionné, TTL 5 min, invalidé à chaque envoi réussi via
 * _180c_notif_purge_feed_cache() (bump du numéro de version du feed).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retourne le numéro de version courant du feed (partie de la clé de cache).
 *
 * @return int
 */
function _180c_notif_feed_version() {
	return (int) get_option( '_180c_notif_feed_version', 1 );
}

/**
 * Invalide tout le cache du feed en incrémentant sa version.
 *
 * Appelée après chaque envoi réussi. Bien plus fiable qu'une énumération des
 * transients (dont les clés dépendent des paramètres de requête).
 *
 * @return void
 */
function _180c_notif_purge_feed_cache() {
	update_option( '_180c_notif_feed_version', _180c_notif_feed_version() + 1, false );
}

/**
 * Enregistre la route REST du feed de notifications.
 *
 * @return void
 */
function _180c_rest_register_notifications() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/notifications',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_get_notifications',
			'permission_callback' => '__return_true',
			'args'                => array(

				/*
				 * `validate_callback` explicite : déclarer un `sanitize_callback`
				 * prive l'argument du `rest_parse_request_arg` que le cœur assigne
				 * par défaut (class-wp-rest-request.php:858-861), et c'est lui qui
				 * applique le schéma. Sans cette ligne, `page=0` et
				 * `per_page=9999` repartaient en 200, rattrapés en aval par le
				 * clamp du callback.
				 *
				 * Les deux applications envoient page ≥ 1 et per_page = 20
				 * (NotificationsRepository.swift:45, NotificationCenter.kt:85) :
				 * aucune requête existante ne bascule en 400.
				 */
				'page'     => array(
					'type'              => 'integer',
					'default'           => 1,
					'minimum'           => 1,
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'absint',
				),
				'per_page' => array(
					'type'              => 'integer',
					'default'           => 20,
					'minimum'           => 1,
					'maximum'           => 50,
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'absint',
				),
				'since'    => array(
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', '_180c_rest_register_notifications' );

/**
 * GET /notifications — feed paginé des notifications envoyées.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response
 */
function _180c_rest_get_notifications( WP_REST_Request $request ) {
	// Filet : la validation REST est en amont (validate_callback). Les clamps
	// restent en défense de profondeur.
	$page     = max( 1, (int) $request->get_param( 'page' ) );
	$per_page = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ) );

	$since_raw = (string) $request->get_param( 'since' );
	$since_gmt = '';
	if ( '' !== $since_raw ) {
		$ts = rest_parse_date( $since_raw );
		if ( false !== $ts ) {
			$since_gmt = gmdate( 'Y-m-d H:i:s', $ts );
		}
	}

	$cache_key = '_180c_notifications_feed_' . md5(
		wp_json_encode(
			array(
				'page'     => $page,
				'per_page' => $per_page,
				'since'    => $since_gmt,
				'version'  => _180c_notif_feed_version(),
			)
		)
	);

	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return _180c_notif_feed_response( $cached['items'], (int) $cached['total'], (int) $cached['pages'] );
	}

	$meta_query = array(
		'relation'    => 'AND',
		'id_clause'   => array(
			'key'     => '_180c_onesignal_id',
			'value'   => '',
			'compare' => '!=',
		),
		'sent_clause' => array(
			'key'     => '_180c_notif_sent_at',
			'compare' => 'EXISTS',
		),
	);

	if ( '' !== $since_gmt ) {
		$meta_query['since_clause'] = array(
			'key'     => '_180c_notif_sent_at',
			'value'   => $since_gmt,
			'compare' => '>=',
			'type'    => 'DATETIME',
		);
	}

	$query = new WP_Query(
		array(
			'post_type'      => _180C_NOTIF_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => array( 'sent_clause' => 'DESC' ),
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	$items = array();
	foreach ( $query->posts as $post ) {
		$items[] = _180c_notif_feed_item( $post );
	}

	$total = (int) $query->found_posts;
	$pages = (int) $query->max_num_pages;

	set_transient(
		$cache_key,
		array(
			'items' => $items,
			'total' => $total,
			'pages' => $pages,
		),
		5 * MINUTE_IN_SECONDS
	);

	return _180c_notif_feed_response( $items, $total, $pages );
}

/**
 * Construit la réponse REST avec les headers de pagination.
 *
 * @param array[] $items Items formatés.
 * @param int     $total Nombre total de notifications.
 * @param int     $pages Nombre total de pages.
 * @return WP_REST_Response
 */
function _180c_notif_feed_response( $items, $total, $pages ) {
	$response = rest_ensure_response( $items );
	$response->header( 'X-WP-Total', (string) $total );
	$response->header( 'X-WP-TotalPages', (string) $pages );

	return $response;
}

/**
 * Formate une notification pour le feed app.
 *
 * @param WP_Post $post Notification.
 * @return array
 */
function _180c_notif_feed_item( $post ) {
	$post_id = (int) $post->ID;

	// Chaîne de repli commune au payload et au feed : meta -> cible -> null.
	$src       = _180c_notif_resolve_image_url( $post_id );
	$image_url = '' !== $src ? esc_url_raw( $src ) : null;

	$target = _180c_onesignal_build_target( $post_id );

	$sent_at  = (string) get_post_meta( $post_id, '_180c_notif_sent_at', true );
	$sent_iso = '';
	if ( '' !== $sent_at ) {
		$ts       = strtotime( $sent_at . ' UTC' );
		$sent_iso = $ts ? gmdate( 'Y-m-d\TH:i:s\Z', $ts ) : '';
	}

	return array(
		'id'        => $post_id,
		'title'     => get_the_title( $post ),
		'body'      => (string) get_post_meta( $post_id, '_180c_notif_body', true ),
		'image_url' => $image_url,
		'target'    => array(
			'type' => (string) $target['type'],
			'id'   => (int) $target['id'],
			'url'  => (string) $target['url'],
		),
		'sent_at'   => $sent_iso,
	);
}
