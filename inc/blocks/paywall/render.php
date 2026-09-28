<?php
/**
 * Bloc 180c/paywall — rendu server-side.
 *
 * Ce bloc est rendu automatiquement depuis single-recipe.php mais peut aussi
 * être inséré manuellement dans un article pour test (inserter=false en prod).
 * La logique de gating est centralisée dans parts/paywall.php.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// Le paywall ne se rend que dans le contexte d'une recette.
if ( ! is_singular( 'recipe' ) && ! current_user_can( 'edit_posts' ) ) {
	return;
}

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-paywall-wrapper' ) );
?>
<div <?php echo $wrapper_attrs; ?>>
	<?php get_template_part( 'parts/paywall' ); ?>
</div>
