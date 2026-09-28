<?php
/**
 * Checkout Payment Section — surcharge 180°C (accordéon design system).
 *
 * Copie conforme du template WooCommerce core (v10.9.0). SEULE adaptation 180°C :
 * une classe de style `checkout-payment__methods` ajoutée sur la `<ul>`, en plus
 * des classes natives `wc_payment_methods payment_methods methods` qui restent
 * INTACTES (lues par le JS WC : init_payment_methods / payment_method_selected).
 * L'`aria-label` de la liste vient du core 10.9.0 (a11y : la liste de radios
 * doit être nommée) ; libellé traduit en dur comme partout ailleurs dans ces
 * surcharges, le thème ne s'appuie pas sur le `.po` de WooCommerce.
 *
 * Tout le reste est rigoureusement préservé : le wrapper `#payment`, les hooks
 * `woocommerce_review_order_before_payment` / `_after_payment` (gardés hors AJAX),
 * `woocommerce_review_order_before_submit` / `_after_submit`, le bouton
 * `#place_order`, le `wp_nonce_field( 'woocommerce-process_checkout' )` et le
 * fallback `<noscript>`. Le moteur de paiement, l'update AJAX du récap et la
 * soumission ne sont pas touchés. La mise en accordéon de chaque méthode est
 * portée par `payment-method.php` (markup) + `checkout.css` (style) + `checkout.js`
 * (affordance) ; l'affichage du `.payment_box` reste piloté par WooCommerce.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package 180c
 * @version 10.9.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}
?>
<div id="payment" class="woocommerce-checkout-payment">
	<?php if ( WC()->cart && WC()->cart->needs_payment() ) : ?>
		<ul class="wc_payment_methods payment_methods methods checkout-payment__methods" aria-label="<?php esc_attr_e( 'Moyens de paiement', '180c' ); ?>">
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
				}
			} else {
				echo '<li>';
				wc_print_notice( apply_filters( 'woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) : esc_html__( 'Please fill in your details above to see available payment methods.', 'woocommerce' ) ), 'notice' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
				echo '</li>';
			}
			?>
		</ul>
	<?php endif; ?>
	<div class="form-row place-order">
		<noscript>
			<?php
			/* translators: $1 and $2 opening and closing emphasis tags respectively */
			printf( esc_html__( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate Totals%2$s button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce' ), '<em>', '</em>' );
			?>
			<br/><button type="submit" class="button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>"><?php esc_html_e( 'Update totals', 'woocommerce' ); ?></button>
		</noscript>

		<?php wc_get_template( 'checkout/terms.php' ); ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<?php echo apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="button alt' . esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ) . '" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); // @codingStandardsIgnoreLine ?>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>
</div>
<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
