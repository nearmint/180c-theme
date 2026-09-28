<?php
/**
 * Bloc 180c/product-card — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/_helpers.php';

$product_id = isset( $attributes['product_id'] ) ? (int) $attributes['product_id'] : 0;

if ( ! $product_id ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-product-card block-180c-product-card--placeholder"><p>' . esc_html__( 'Carte produit : sélectionnez un produit dans les réglages du bloc.', '180c' ) . '</p></div>';
	}
	return;
}

if ( ! function_exists( 'wc_get_product' ) ) {
	return;
}

$product = wc_get_product( $product_id );

if ( ! $product || ! $product->is_purchasable() && 'publish' !== get_post_status( $product_id ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-product-card block-180c-product-card--placeholder"><p>' . esc_html__( 'Carte produit : produit introuvable ou non publié.', '180c' ) . '</p></div>';
	}
	return;
}

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-product-card' ) );
?>
<div <?php echo $wrapper_attrs; ?>>
	<?php echo _180c_block_render_product_card( $product ); ?>
</div>
