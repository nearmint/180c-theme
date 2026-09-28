<?php
/**
 * Cart Page — surcharge 180°C.
 *
 * Refonte markup avec classes design system. Logique Woo préservée : gardes,
 * filtres et hooks sont ceux du core 11.0.0, seuls le markup, les classes DS et
 * les libellés FR divergent.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );
?>

<form class="woocommerce-cart-form cart-table__form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">

	<?php do_action( 'woocommerce_before_cart_table' ); ?>

	<table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents cart-table" cellspacing="0">
		<thead class="cart-table__head">
			<tr>
				<th scope="col" class="product-remove"><span class="screen-reader-text"><?php esc_html_e( 'Supprimer l\'article', '180c' ); ?></span></th>
				<th scope="col" class="product-thumbnail"><span class="screen-reader-text"><?php esc_html_e( 'Image', '180c' ); ?></span></th>
				<th scope="col" class="product-name"><?php esc_html_e( 'Produit', '180c' ); ?></th>
				<th scope="col" class="product-price"><?php esc_html_e( 'Prix', '180c' ); ?></th>
				<th scope="col" class="product-quantity"><?php esc_html_e( 'Quantité', '180c' ); ?></th>
				<th scope="col" class="product-subtotal"><?php esc_html_e( 'Sous-total', '180c' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php do_action( 'woocommerce_before_cart_contents' ); ?>

			<?php
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

				/**
				 * Filter whether this cart item is visible in the cart.
				 *
				 * Évalué AVANT le garde (et non en court-circuit dans le `if`) :
				 * le core 11.0.0 déclenche ce filtre pour chaque ligne du panier,
				 * y compris celles dont le produit a disparu.
				 *
				 * @since 2.1.0
				 * @param bool   $_180c_visible Whether the cart item is visible. Default true.
				 * @param array  $cart_item     The cart item data.
				 * @param string $cart_item_key The cart item key.
				 */
				$_180c_visible = apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key );

				// `instanceof WC_Product` (core 11.0.0) : un produit supprimé de la
				// base laisse un `$cart_item['data']` qui n'est plus un WC_Product.
				if ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $_180c_visible ) {
					/**
					 * Filter the product name.
					 *
					 * Calculé DANS le garde depuis le core 11.0.0 : appeler
					 * `get_name()` avant la vérification était fatal sur un produit
					 * absent.
					 *
					 * @since 2.1.0
					 * @param string $product_name  Name of the product in the cart.
					 * @param array  $cart_item     The product in the cart.
					 * @param string $cart_item_key Key for the product in the cart.
					 */
					$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
					$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
					?>
					<tr class="woocommerce-cart-form__cart-item cart-table__row <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>">

						<td class="product-remove">
							<?php
							echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								'woocommerce_cart_item_remove_link',
								sprintf(
									// Icône corbeille mutualisée avec le drawer (_180c_cart_icon).
									'<a role="button" href="%1$s" class="remove cart-table__remove" aria-label="%2$s" data-product_id="%3$s" data-product_sku="%4$s">%5$s</a>',
									esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
									/* translators: %s is the product name */
									esc_attr( sprintf( __( 'Supprimer %s du panier', '180c' ), wp_strip_all_tags( $product_name ) ) ),
									esc_attr( $product_id ),
									esc_attr( $_product->get_sku() ),
									_180c_cart_icon( 'trash' )
								),
								$cart_item_key
							);
							?>
						</td>

						<td class="product-thumbnail">
							<?php
							$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ), $cart_item, $cart_item_key );

							if ( ! $product_permalink ) {
								echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} else {
								printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</td>

						<td role="rowheader" class="product-name cart-table__name" data-title="<?php esc_attr_e( 'Produit', '180c' ); ?>">
							<?php
							if ( ! $product_permalink ) {
								echo wp_kses_post( $product_name . '&nbsp;' );
							} else {
								echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
							}

							do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

							// Meta data.
							echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

							// Backorder notification.
							if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
								echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Disponible sur commande', '180c' ) . '</p>', $product_id ) );
							}
							?>
						</td>

						<td class="product-price cart-table__price" data-title="<?php esc_attr_e( 'Prix', '180c' ); ?>">
							<?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>

						<td class="product-quantity cart-table__quantity" data-title="<?php esc_attr_e( 'Quantité', '180c' ); ?>">
							<?php
							if ( $_product->is_sold_individually() ) {
								$min_quantity = 1;
								$max_quantity = 1;
							} else {
								$min_quantity = 0;
								$max_quantity = $_product->get_max_purchase_quantity();
							}

							$product_quantity = woocommerce_quantity_input(
								array(
									'input_name'   => "cart[{$cart_item_key}][qty]",
									'input_value'  => $cart_item['quantity'],
									'max_value'    => $max_quantity,
									'min_value'    => $min_quantity,
									'product_name' => $product_name,
								),
								$_product,
								false
							);

							echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</td>

						<td class="product-subtotal cart-table__subtotal" data-title="<?php esc_attr_e( 'Sous-total', '180c' ); ?>">
							<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
					</tr>
					<?php
				}
			}
			?>

			<?php do_action( 'woocommerce_cart_contents' ); ?>

			<tr class="cart-table__actions-row">
				<td colspan="6" class="actions cart-table__actions">

					<?php if ( wc_coupons_enabled() ) : ?>
						<div class="coupon cart-table__coupon">
							<label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Code promo :', '180c' ); ?></label>
							<input
								type="text"
								name="coupon_code"
								class="input input-text"
								id="coupon_code"
								value=""
								placeholder="<?php esc_attr_e( 'Code promo', '180c' ); ?>"
							/>
							<button
								type="submit"
								class="btn btn--secondary btn--sm"
								name="apply_coupon"
								value="<?php esc_attr_e( 'Appliquer', '180c' ); ?>"
							><?php esc_html_e( 'Appliquer', '180c' ); ?></button>
							<?php do_action( 'woocommerce_cart_coupon' ); ?>
						</div>
					<?php endif; ?>

					<button
						type="submit"
						class="btn btn--secondary btn--sm cart-table__update"
						name="update_cart"
						value="<?php esc_attr_e( 'Mettre à jour le panier', '180c' ); ?>"
					><?php esc_html_e( 'Mettre à jour', '180c' ); ?></button>

					<?php do_action( 'woocommerce_cart_actions' ); ?>
					<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
				</td>
			</tr>

			<?php do_action( 'woocommerce_after_cart_contents' ); ?>
		</tbody>
	</table>

	<?php do_action( 'woocommerce_after_cart_table' ); ?>
</form>

<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

<div class="cart-collaterals">
	<?php
	/**
	 * Cart collaterals hook.
	 *
	 * @hooked woocommerce_cross_sell_display
	 * @hooked woocommerce_cart_totals - 10
	 */
	do_action( 'woocommerce_cart_collaterals' );
	?>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
