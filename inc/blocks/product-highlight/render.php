<?php
/**
 * Bloc 180c/product-highlight — rendu serveur.
 *
 * Mise en avant d'un produit WooCommerce (image non croppée + titre + prix +
 * CTA). Remplace le shortcode [produit]. Résolution par `product_id`, repli
 * sur `sku`. No-op propre si WooCommerce est inactif ou si aucun produit publié
 * n'est résolu (placeholder éditeur pour les contributeurs).
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/_helpers.php';

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

$product_id = isset( $attributes['product_id'] ) ? (int) $attributes['product_id'] : 0;
$sku        = isset( $attributes['sku'] ) ? sanitize_text_field( (string) $attributes['sku'] ) : '';

$product = _180c_block_resolve_product( $product_id, $sku );

if ( ! $product instanceof WC_Product ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-product-highlight block-180c-product-highlight--placeholder"><p>'
			. esc_html__( 'Mise en avant produit : renseignez un ID ou un SKU de produit publié dans les réglages du bloc.', '180c' )
			. '</p></div>';
	}
	return;
}

$url         = $product->get_permalink();
$title       = $product->get_name();
$price_html  = $product->get_price_html();
$thumb_id    = $product->get_image_id();
$is_external = $product->is_type( 'external' );

// CTA : on pointe toujours vers la fiche INTERNE (cart-first standard).
$cta_label = $is_external
	? __( 'En savoir plus', '180c' )
	: __( 'Voir le produit', '180c' );

// Image non croppée : taille « large », ratio naturel préservé (CSS).
$thumb_html = '';
if ( $thumb_id ) {
	$thumb_html = wp_get_attachment_image(
		$thumb_id,
		'large',
		false,
		array(
			'class'    => 'block-180c-product-highlight__image',
			'loading'  => 'lazy',
			'decoding' => 'async',
		)
	);
}

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-product-highlight' ) );
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes échappe ses attributs. ?>>
	<?php if ( $thumb_html ) : ?>
		<a class="block-180c-product-highlight__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image échappe ses attributs. ?>
		</a>
	<?php endif; ?>
	<div class="block-180c-product-highlight__body">
		<h3 class="block-180c-product-highlight__title">
			<a class="block-180c-product-highlight__title-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
		</h3>
		<?php if ( $price_html ) : ?>
			<div class="block-180c-product-highlight__price"><?php echo wp_kses_post( $price_html ); ?></div>
		<?php endif; ?>
		<a class="btn btn--primary block-180c-product-highlight__cta" href="<?php echo esc_url( $url ); ?>">
			<?php echo esc_html( $cta_label ); ?>
		</a>
	</div>
</div>
