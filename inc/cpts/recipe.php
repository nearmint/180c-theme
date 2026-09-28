<?php
/**
 * Enregistrement du CPT « recipe ».
 *
 * Source unique de vérité pour les recettes structurées (web + apps iOS/Android).
 * Les taxonomies sont enregistrées dans recipe-taxonomies.php, les champs ACF
 * dans acf-json/group_recipe_fields.json, et l'exposition REST dans
 * inc/rest/recipe-fields.php.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre le custom post type « recipe ».
 *
 * Permalien des recettes : /recettes/{slug}. L'archive du CPT est volontairement
 * servie sous /toutes-les-recettes/ (et non /recettes/) pour laisser le slug
 * « recettes » à la Page éditoriale « Recettes » (Home Builder, template-home.php) :
 * sans cela la règle de réécriture d'archive capterait /recettes/ avant le
 * catch-all des pages, et la page éditoriale ne serait jamais rendue.
 * get_post_type_archive_link('recipe') renvoie donc /toutes-les-recettes/.
 * show_in_rest pour l'API native (/wp-json/wp/v2/recipe). Capabilities mappées
 * sur « post ».
 *
 * @return void
 */
function _180c_register_recipe_cpt() {
	$labels = array(
		'name'                  => _x( 'Recettes', 'Post type general name', '180c' ),
		'singular_name'         => _x( 'Recette', 'Post type singular name', '180c' ),
		'menu_name'             => _x( 'Recettes', 'Admin Menu text', '180c' ),
		'name_admin_bar'        => _x( 'Recette', 'Add New on Toolbar', '180c' ),
		'add_new'               => __( 'Ajouter', '180c' ),
		'add_new_item'          => __( 'Ajouter une recette', '180c' ),
		'new_item'              => __( 'Nouvelle recette', '180c' ),
		'edit_item'             => __( 'Modifier la recette', '180c' ),
		'view_item'             => __( 'Voir la recette', '180c' ),
		'view_items'            => __( 'Voir les recettes', '180c' ),
		'all_items'             => __( 'Toutes les recettes', '180c' ),
		'search_items'          => __( 'Rechercher une recette', '180c' ),
		'not_found'             => __( 'Aucune recette trouvée', '180c' ),
		'not_found_in_trash'    => __( 'Aucune recette dans la corbeille', '180c' ),
		'featured_image'        => __( 'Image à la une', '180c' ),
		'set_featured_image'    => __( 'Définir l\'image à la une', '180c' ),
		'remove_featured_image' => __( 'Retirer l\'image à la une', '180c' ),
		'use_featured_image'    => __( 'Utiliser comme image à la une', '180c' ),
		'archives'              => __( 'Archives des recettes', '180c' ),
		'item_published'        => __( 'Recette publiée.', '180c' ),
		'item_updated'          => __( 'Recette mise à jour.', '180c' ),
	);

	register_post_type(
		'recipe',
		array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_rest'        => true,
			'has_archive'         => 'toutes-les-recettes',
			'hierarchical'        => false,
			'exclude_from_search' => false,
			'menu_icon'           => 'dashicons-food',
			'menu_position'       => 5,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions' ),
			'taxonomies'          => array( 'recipe_category', 'recipe_season', 'recipe_publication', 'recipe_tag' ),
			'rewrite'             => array(
				'slug'       => 'recettes',
				'with_front' => false,
				'feeds'      => true,
			),
		)
	);
}
add_action( 'init', '_180c_register_recipe_cpt', 10 );

/**
 * Vide les règles de réécriture une seule fois après (ré)enregistrement.
 *
 * Le token est incrémenté à chaque changement de structure CPT/taxonomies
 * pour forcer un nouveau flush sans intervention manuelle sur les permaliens.
 *
 * @return void
 */
function _180c_recipe_maybe_flush_rewrite() {
	$token = '180c-recipe-rewrite-2';

	if ( get_option( '_180c_recipe_rewrite_token' ) === $token ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( '_180c_recipe_rewrite_token', $token );
}
add_action( 'init', '_180c_recipe_maybe_flush_rewrite', 20 );
