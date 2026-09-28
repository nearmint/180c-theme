<?php
/**
 * Custom post type « 180c_notification » — source de vérité des notifications push.
 *
 * Chaque notification poussée à l'app est stockée ici. Le CPT est privé
 * (public => false), non exposé en REST natif (show_in_rest => false) : le feed
 * app est servi par un endpoint dédié (inc/notifications/rest.php) qui ne
 * remonte que les champs voulus. Les capabilities sont mappées sur « post ».
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Types de cible autorisés pour une notification.
 *
 * `recipe` et `product` sont les seuls types proposés à la saisie (LOT D).
 * `article`, `url` et `none` restent valides en lecture (rétro-compatibilité :
 * des notifications déjà envoyées les portent) mais ne sont plus offerts dans
 * l'interface.
 *
 * @return string[]
 */
function _180c_notif_target_types() {
	return array( 'recipe', 'product', 'article', 'url', 'none' );
}

/**
 * Enregistre le custom post type « 180c_notification ».
 *
 * @return void
 */
function _180c_register_notification_cpt() {
	$labels = array(
		'name'               => _x( 'Notifications', 'Post type general name', '180c' ),
		'singular_name'      => _x( 'Notification', 'Post type singular name', '180c' ),
		'menu_name'          => _x( 'Notifications', 'Admin Menu text', '180c' ),
		'name_admin_bar'     => _x( 'Notification', 'Add New on Toolbar', '180c' ),
		'add_new'            => __( 'Envoyer', '180c' ),
		'add_new_item'       => __( 'Envoyer une notification', '180c' ),
		'new_item'           => __( 'Nouvelle notification', '180c' ),
		'edit_item'          => __( 'Modifier la notification', '180c' ),
		'view_item'          => __( 'Voir la notification', '180c' ),
		'all_items'          => __( 'Toutes les notifications', '180c' ),
		'search_items'       => __( 'Rechercher une notification', '180c' ),
		'not_found'          => __( 'Aucune notification trouvée', '180c' ),
		'not_found_in_trash' => __( 'Aucune notification dans la corbeille', '180c' ),
		'item_published'     => __( 'Notification enregistrée.', '180c' ),
		'item_updated'       => __( 'Notification mise à jour.', '180c' ),
	);

	register_post_type(
		_180C_NOTIF_POST_TYPE,
		array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false,
			'menu_icon'       => 'dashicons-megaphone',
			'menu_position'   => 26,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'hierarchical'    => false,
			'supports'        => array( 'title' ),
		)
	);
}
add_action( 'init', '_180c_register_notification_cpt', 10 );

/**
 * Callback d'autorisation partagé pour les post meta du CPT notification.
 *
 * @param bool   $allowed   Autorisation courante (ignorée : on recalcule).
 * @param string $meta_key  Clé de meta (ignorée).
 * @param int    $object_id ID du post concerné.
 * @return bool
 */
function _180c_notif_meta_auth( $allowed, $meta_key, $object_id ) {
	unset( $allowed, $meta_key );

	return current_user_can( 'edit_post', (int) $object_id );
}

/**
 * Sanitize le type de cible : restreint aux valeurs autorisées.
 *
 * @param string $value Valeur brute.
 * @return string Type valide ou `none` par défaut.
 */
function _180c_notif_sanitize_target_type( $value ) {
	$value = sanitize_key( $value );

	return in_array( $value, _180c_notif_target_types(), true ) ? $value : 'none';
}

/**
 * Sanitize la clé de segment : restreint aux segments connus.
 *
 * @param string $value Valeur brute.
 * @return string Clé de segment valide ou segment par défaut.
 */
function _180c_notif_sanitize_segment( $value ) {
	$value = sanitize_key( $value );

	return in_array( $value, _180c_onesignal_segment_keys(), true ) ? $value : _180c_onesignal_default_segment();
}

/**
 * Enregistre les post meta de la notification.
 *
 * Toutes en show_in_rest => false : elles ne doivent jamais fuiter par l'API
 * native. Le feed app dédié les relit côté serveur.
 *
 * @return void
 */
function _180c_register_notification_meta() {
	$pt        = _180C_NOTIF_POST_TYPE;
	$auth      = '_180c_notif_meta_auth';
	$str_field = 'sanitize_text_field';

	$string_metas = array(
		'_180c_notif_body'            => 'sanitize_textarea_field',
		'_180c_notif_target_type'     => '_180c_notif_sanitize_target_type',
		'_180c_notif_target_url'      => 'esc_url_raw',
		'_180c_notif_segment'         => '_180c_notif_sanitize_segment',
		'_180c_notif_idempotency_key' => $str_field,
		'_180c_onesignal_id'          => $str_field,
		'_180c_notif_sent_at'         => $str_field,
		'_180c_notif_last_error'      => $str_field,
	);

	foreach ( $string_metas as $key => $sanitize ) {
		register_post_meta(
			$pt,
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => $auth,
			)
		);
	}

	$int_metas = array( '_180c_notif_image_id', '_180c_notif_target_id' );

	foreach ( $int_metas as $key ) {
		register_post_meta(
			$pt,
			$key,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
			)
		);
	}
}
add_action( 'init', '_180c_register_notification_meta', 11 );
