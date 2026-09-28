<?php
/**
 * Loop Add to Cart — surcharge 180°C.
 *
 * Bouton via .btn .btn--primary du design system.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     9.2.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

$aria_describedby = isset( $args['aria-describedby_text'] )
	? sprintf( 'aria-describedby="woocommerce_loop_add_to_cart_link_describedby_%s"', esc_attr( $product->get_id() ) )
	: '';

// Classe de base Woo + classes design system.
$base_class = isset( $args['class'] ) ? $args['class'] : 'button';
$btn_class  = $base_class . ' btn btn--primary';

echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	'woocommerce_loop_add_to_cart_link',
	sprintf(
		'<a href="%s" %s data-quantity="%s" class="%s" %s>%s</a>',
		esc_url( $product->add_to_cart_url() ),
		$aria_describedby,
		esc_attr( isset( $args['quantity'] ) ? $args['quantity'] : 1 ),
		esc_attr( $btn_class ),
		isset( $args['attributes'] ) ? wc_implode_html_attributes( $args['attributes'] ) : '',
		esc_html( $product->add_to_cart_text() )
	),
	$product,
	$args
);
?>
<?php if ( isset( $args['aria-describedby_text'] ) ) : ?>
	<span
		id="woocommerce_loop_add_to_cart_link_describedby_<?php echo esc_attr( $product->get_id() ); ?>"
		class="screen-reader-text"
	>
		<?php echo esc_html( $args['aria-describedby_text'] ); ?>
	</span>
<?php endif; ?>
