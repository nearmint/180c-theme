<?php
/**
 * Simple product add to cart — surcharge 180°C.
 *
 * Restyle uniquement le produit SIMPLE : bouton thème (.btn--primary), ligne
 * quantité + CTA alignée (.product__buy-row), état de stock conservé. Le form
 * `.cart` natif est préservé (action panier, hooks WC). Les produits external
 * et subscription gardent leur template natif.
 *
 * Rupture de stock (`! is_in_stock()`) : on rend explicitement un bouton
 * désactivé « Rupture de stock » (au lieu de masquer tout CTA), afin que
 * l'indisponibilité soit visible et que la barre d'achat sticky (—
 * src/js/modules/product.js, sélecteur .single_add_to_cart_button) en reflète
 * l'état et le libellé.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.2.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product->is_purchasable() ) {
	return;
}

echo wc_get_stock_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

if ( $product->is_in_stock() ) : ?>

	<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

	<form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<div class="product__buy-row">
			<?php
			do_action( 'woocommerce_before_add_to_cart_quantity' );

			woocommerce_quantity_input(
				array(
					'min_value'   => $product->get_min_purchase_quantity(),
					'max_value'   => $product->get_max_purchase_quantity(),
					'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // phpcs:ignore WordPress.Security.NonceVerification.Missing
				)
			);

			do_action( 'woocommerce_after_add_to_cart_quantity' );
			?>

			<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button btn btn--primary product__add-to-cart"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>
		</div>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
	</form>

	<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

<?php else : ?>

	<button
		type="button"
		disabled
		aria-disabled="true"
		class="single_add_to_cart_button btn btn--primary product__add-to-cart is-disabled"
	><?php esc_html_e( 'Rupture de stock', '180c' ); ?></button>

<?php endif; ?>
