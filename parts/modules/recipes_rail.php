<?php
/**
 * Template part — Module home : Rail de recettes.
 *
 * Sous-champs ACF :
 *   - title (text)
 *   - mode (radio : recent|category|season|current_season|type|manual)
 *   - recipe_category (taxonomy term object → recipe_publication, mode=category)
 *   - season (taxonomy term object, recipe_season, mode=season)
 *   - type (taxonomy term object → recipe_category / type de plat, mode=type)
 *   - count (number)
 *   - exclude_displayed (true_false, mode!=manual) — dédoublonnage inter-modules
 *   - manual_recipes (post_object multiple, post_type=recipe, mode=manual)
 *
 * Rend via le shell mutualisé _180c_render_rail(). Le mode « recent » est
 * mis en cache (transient, TTL 5 min) sauf si le dédoublonnage est actif
 * (sortie dépendante du contexte de page). « Voir tout » pointe vers le terme
 * en mode category/season/current_season/type.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks/_helpers.php';

$title = get_sub_field( 'title' );

// Sous-champs normalisés → résolution mutualisée (cf. inc/home-modules.php),
// partagée avec l'endpoint REST 180c/v1/home-recettes (zéro duplication).
$sub = array(
	'mode'              => get_sub_field( 'mode' ),
	'count'             => get_sub_field( 'count' ),
	'recipe_category'   => get_sub_field( 'recipe_category' ),
	'season'            => get_sub_field( 'season' ),
	'type'              => get_sub_field( 'type' ),
	'manual_recipes'    => get_sub_field( 'manual_recipes' ),
	'exclude_displayed' => get_sub_field( 'exclude_displayed' ),
);

$recipe_ids = _180c_resolve_recipe_rail_ids( $sub );
if ( empty( $recipe_ids ) ) {
	return;
}

$items_html = array();
foreach ( $recipe_ids as $recipe_id ) {
	$card = _180c_block_render_recipe_card( get_post( $recipe_id ), 'sm' );
	if ( $card ) {
		$items_html[] = $card;
	}
}

if ( empty( $items_html ) ) {
	return;
}

_180c_render_rail(
	array(
		'title'        => $title,
		'items_html'   => $items_html,
		'view_all_url' => _180c_recipe_rail_view_all_url( $sub ),
		'region_label' => $title ? $title : __( 'Recettes', '180c' ),
		'modifier'     => 'recipes_rail',
	)
);
