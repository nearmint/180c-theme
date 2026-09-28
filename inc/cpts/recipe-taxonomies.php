<?php
/**
 * Taxonomies du CPT « recipe ».
 *
 * 4 taxonomies, toutes exposées en REST (show_in_rest). La création des termes
 * n'est PAS du ressort de cette initiative (éditorial / init migration).
 *
 *  - recipe_category    : hiérarchique — types de plat (Entrée, Plat, Dessert, Apéro…)
 *  - recipe_season      : plate       — Printemps, Été, Automne, Hiver
 *  - recipe_publication : plate       — publications (Cahiers de Delphine, 180°C, 12°5…)
 *  - recipe_tag         : plate       — libres
 *
 * Renommage v1 (init taxonomies) : l'ancienne taxonomie des publications papier
 * est devenue `recipe_publication`, et `recipe_category` héberge désormais les
 * types de plat (données déjà permutées en base, option `_180c_taxo_rename_v1`).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre les 4 taxonomies de la recette.
 *
 * @return void
 */
function _180c_register_recipe_taxonomies() {
	// recipe_category — hiérarchique (type de plat : Entrée, Plat, Dessert…).
	register_taxonomy(
		'recipe_category',
		array( 'recipe' ),
		array(
			'labels'            => array(
				'name'              => _x( 'Types de plat', 'taxonomy general name', '180c' ),
				'singular_name'     => _x( 'Type de plat', 'taxonomy singular name', '180c' ),
				'search_items'      => __( 'Rechercher un type de plat', '180c' ),
				'all_items'         => __( 'Tous les types de plat', '180c' ),
				'parent_item'       => __( 'Type de plat parent', '180c' ),
				'parent_item_colon' => __( 'Type de plat parent :', '180c' ),
				'edit_item'         => __( 'Modifier le type de plat', '180c' ),
				'update_item'       => __( 'Mettre à jour le type de plat', '180c' ),
				'add_new_item'      => __( 'Ajouter un type de plat', '180c' ),
				'new_item_name'     => __( 'Nom du nouveau type de plat', '180c' ),
				'menu_name'         => __( 'Types de plat', '180c' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'recipe_category',
			'rewrite'           => array(
				'slug'         => 'categorie-recette',
				'with_front'   => false,
				'hierarchical' => true,
			),
		)
	);

	// recipe_season — plate (saison).
	register_taxonomy(
		'recipe_season',
		array( 'recipe' ),
		array(
			'labels'            => array(
				'name'          => _x( 'Saisons', 'taxonomy general name', '180c' ),
				'singular_name' => _x( 'Saison', 'taxonomy singular name', '180c' ),
				'search_items'  => __( 'Rechercher une saison', '180c' ),
				'all_items'     => __( 'Toutes les saisons', '180c' ),
				'edit_item'     => __( 'Modifier la saison', '180c' ),
				'update_item'   => __( 'Mettre à jour la saison', '180c' ),
				'add_new_item'  => __( 'Ajouter une saison', '180c' ),
				'new_item_name' => __( 'Nom de la nouvelle saison', '180c' ),
				'menu_name'     => __( 'Saisons', '180c' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'recipe_season',
			'rewrite'           => array(
				'slug'       => 'saison',
				'with_front' => false,
			),
		)
	);

	// recipe_publication — plate (publication d'origine : Cahiers de Delphine, 180°C, 12°5…).
	register_taxonomy(
		'recipe_publication',
		array( 'recipe' ),
		array(
			'labels'            => array(
				'name'          => _x( 'Publications', 'taxonomy general name', '180c' ),
				'singular_name' => _x( 'Publication', 'taxonomy singular name', '180c' ),
				'search_items'  => __( 'Rechercher une publication', '180c' ),
				'all_items'     => __( 'Toutes les publications', '180c' ),
				'edit_item'     => __( 'Modifier la publication', '180c' ),
				'update_item'   => __( 'Mettre à jour la publication', '180c' ),
				'add_new_item'  => __( 'Ajouter une publication', '180c' ),
				'new_item_name' => __( 'Nom de la nouvelle publication', '180c' ),
				'menu_name'     => __( 'Publications', '180c' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'recipe_publication',
			'rewrite'           => array(
				'slug'       => 'publication',
				'with_front' => false,
			),
		)
	);

	// recipe_tag — plate (mots-clés libres).
	register_taxonomy(
		'recipe_tag',
		array( 'recipe' ),
		array(
			'labels'            => array(
				'name'                       => _x( 'Étiquettes de recette', 'taxonomy general name', '180c' ),
				'singular_name'              => _x( 'Étiquette de recette', 'taxonomy singular name', '180c' ),
				'search_items'               => __( 'Rechercher une étiquette', '180c' ),
				'all_items'                  => __( 'Toutes les étiquettes', '180c' ),
				'edit_item'                  => __( 'Modifier l\'étiquette', '180c' ),
				'update_item'                => __( 'Mettre à jour l\'étiquette', '180c' ),
				'add_new_item'               => __( 'Ajouter une étiquette', '180c' ),
				'new_item_name'              => __( 'Nom de la nouvelle étiquette', '180c' ),
				'separate_items_with_commas' => __( 'Séparer les étiquettes par des virgules', '180c' ),
				'add_or_remove_items'        => __( 'Ajouter ou retirer des étiquettes', '180c' ),
				'choose_from_most_used'      => __( 'Choisir parmi les plus utilisées', '180c' ),
				'menu_name'                  => __( 'Étiquettes', '180c' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => 'tag-recette',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', '_180c_register_recipe_taxonomies', 10 );

/**
 * Flush des règles de réécriture une seule fois après le renommage des taxos.
 *
 * Les slugs publics (`publication`, `categorie-recette`, `saison`) doivent être
 * réenregistrés après le renommage de la taxonomie des publications.
 * Idempotent via l'option `_180c_taxo_rewrite_flushed_v1`.
 *
 * @return void
 */
function _180c_taxo_rename_maybe_flush() {
	if ( get_option( '_180c_taxo_rewrite_flushed_v1' ) ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( '_180c_taxo_rewrite_flushed_v1', 1, false );
}
add_action( 'init', '_180c_taxo_rename_maybe_flush', 20 );
