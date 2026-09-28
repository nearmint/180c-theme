<?php
/**
 * Bloc 180c/recipes-slider — rendu serveur.
 *
 * Jumeau hors Home Builder de parts/modules/recipes_slider.php : mêmes données
 * (dernières recettes publiées), même markup — les deux délèguent à
 * _180c_render_recipes_slider() (inc/home-modules.php). Aucune feuille de style
 * propre : le design vit dans src/css/components/home/recipes-slider.css,
 * chargé par main.css.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$title = isset( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : '';

// Même plafond que le module home : au-delà de 10, la frise de pagination
// devient illisible. `min` protège aussi d'un attribut posté à la main.
$count = isset( $attributes['count'] ) ? (int) $attributes['count'] : 6;
$count = max( 1, min( _180C_RECIPES_SLIDER_MAX, $count ) );

$sub = array(
	'mode'  => 'recent',
	'count' => $count,
);

$recipe_ids = _180c_resolve_recipe_rail_ids( $sub );

if ( empty( $recipe_ids ) ) {
	// Éditeur : signaler l'absence de contenu plutôt que de rendre le vide.
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-recipes-slider block-180c-recipes-slider--placeholder"><p>'
			. esc_html__( 'Slider de recettes : aucune recette publiée.', '180c' )
			. '</p></div>';
	}
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'block-180c-recipes-slider' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé par le core. ?>>
	<?php
	_180c_render_recipes_slider(
		array(
			'title'        => $title,
			'recipe_ids'   => $recipe_ids,
			'view_all_url' => _180c_recipe_rail_view_all_url( $sub ),
			'region_label' => $title ? $title : __( 'Les dernières recettes publiées', '180c' ),
		)
	);
	?>
</div>
