<?php
/**
 * Template part — Module home : Slider de recettes.
 *
 * Format « une recette en vedette à la fois » (photo + panneau texte + frise de
 * pagination). Ne remplace pas `recipes_rail`, qui reste le rail dense de
 * cartes 4:5 : les deux peuvent cohabiter sur la même page.
 *
 * Sous-champs ACF (layout `recipes_slider`) :
 *   - title             (text)       → titre h2, vide = « Les dernières recettes publiées »
 *   - count             (number)     → 1 à 10 recettes (plafond dur côté rendu)
 *   - exclude_displayed (true_false) → dédoublonnage inter-modules
 *   - display           (radio)      → visibilité par plateforme, web_only|app_only|web_app
 *                                      (filtré par _180c_render_home_modules)
 *
 * La source de données est figée sur « les dernières recettes publiées » : on
 * réutilise donc la résolution mutualisée `_180c_resolve_recipe_rail_ids()` en
 * mode `recent`, ce qui apporte aussi le cache court (transient 5 min) et
 * l'alimentation de l'accumulateur de dédoublonnage — zéro logique de requête
 * propre à ce module.
 *
 * Le markup est rendu par `_180c_render_recipes_slider()` (inc/home-modules.php),
 * partagé avec le bloc Gutenberg `180c/recipes-slider`.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$count = (int) get_sub_field( 'count' );
if ( $count < 1 ) {
	$count = 6;
}
$count = min( $count, _180C_RECIPES_SLIDER_MAX );

$sub = array(
	'mode'              => 'recent',
	'count'             => $count,
	'exclude_displayed' => get_sub_field( 'exclude_displayed' ),
);

$recipe_ids = _180c_resolve_recipe_rail_ids( $sub );
if ( empty( $recipe_ids ) ) {
	return;
}

$title = get_sub_field( 'title' );

// Premier module de la page → son visuel d'ouverture est candidat LCP et doit
// être demandé en priorité. Ailleurs, il est sous la ligne de flottaison et
// reste en lazy. L'index ACF est exposé par _180c_render_home_modules().
$block_index = get_query_var( '_180c_block_index' );

_180c_render_recipes_slider(
	array(
		'title'          => $title,
		'recipe_ids'     => $recipe_ids,
		'view_all_url'   => _180c_recipe_rail_view_all_url( $sub ),
		'region_label'   => $title ? $title : __( 'Les dernières recettes publiées', '180c' ),
		'priority_first' => ( '' !== $block_index && 0 === (int) $block_index ),
	)
);
