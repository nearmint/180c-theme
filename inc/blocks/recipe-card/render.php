<?php
/**
 * Bloc 180c/recipe-card — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/_helpers.php';

$recipe_id = isset( $attributes['recipe_id'] ) ? (int) $attributes['recipe_id'] : 0;

if ( ! $recipe_id ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-recipe-card block-180c-recipe-card--placeholder"><p>' . esc_html__( 'Carte recette : sélectionnez une recette dans les réglages du bloc.', '180c' ) . '</p></div>';
	}
	return;
}

$recipe = get_post( $recipe_id );

if ( ! $recipe || 'recipe' !== $recipe->post_type || 'publish' !== get_post_status( $recipe ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-recipe-card block-180c-recipe-card--placeholder"><p>' . esc_html__( 'Carte recette : recette introuvable ou non publiée.', '180c' ) . '</p></div>';
	}
	return;
}

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-recipe-card' ) );
?>
<div <?php echo $wrapper_attrs; ?>>
	<?php echo _180c_block_render_recipe_card( $recipe ); ?>
</div>
