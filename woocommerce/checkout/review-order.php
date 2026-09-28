<?php
/**
 * Review order table — surcharge 180°C (refonte récap).
 *
 * Récap de commande différencié, lisible sur les trois cas :
 *  - revues seules (produit physique, livraison, pas de récurrent) ;
 *  - abonnement seul (pas de livraison, bloc « Puis chaque {période} ») ;
 *  - panier mixte (les deux).
 *
 * Deux blocs :
 *  1. « Votre commande » (thead + tbody) : lignes produits avec badge nature
 *     (« Abonnement » / « Revue »), groupement visuel en panier mixte, et
 *     checkbox « offrir » native de Subscriptions Gifting préservée (rendue via
 *     les filtres `woocommerce_cart_item_name` / `wc_get_formatted_cart_item_data`).
 *  2. « Récapitulatif paiement » (tfoot) :
 *     - « À payer aujourd'hui » = total panier natif (inclut le 1er versement
 *       de l'abonnement, jamais additionné une 2ᵉ fois) ;
 *     - sous-lignes TVA par taux (« dont … » en TTC, « + … » en HT) ;
 *     - ligne livraison native (sélecteur de méthode) si produit physique ;
 *     - « Puis chaque {période} » + date de 1er renouvellement si abonnement.
 *
 * Données lues via les helpers `_180c_*` (voir inc/woo/order-review.php) : aucun
 * calcul de montant dans le template. Le rendu « totaux récurrents » natif de
 * Subscriptions est détaché côté PHP (`_180c_detach_native_recurring_totals()`),
 * ce template pilote donc seul le bloc récurrent.
 *
 * Gardes et filtres de ligne sont ceux du core 11.0.0 ; seuls le markup et
 * l'organisation du tfoot relèvent d'.
 *
 * Tous les hooks attendus par l'update AJAX natif sont préservés :
 * `woocommerce_review_order_before/after_cart_contents`, filtres
 * `woocommerce_cart_item_*`, `woocommerce_review_order_before/after_shipping`,
 * `woocommerce_review_order_before/after_order_total`, et l'élément racine
 * `<table class="shop_table woocommerce-checkout-review-order-table">`.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rendu d'une ligne d'article du récap (avec badge nature).
 *
 * @param string $cart_item_key Clé de l'article dans le panier.
 * @param array  $cart_item     Données de l'article.
 * @return void
 */
$render_item = static function ( $cart_item_key, $cart_item ) {
	$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

	/**
	 * Filter whether this cart item is visible in the checkout review order table.
	 *
	 * Évalué AVANT le garde (et non en court-circuit) : le core 11.0.0 déclenche
	 * ce filtre pour chaque ligne, y compris celles dont le produit a disparu.
	 *
	 * @since 2.1.0
	 * @param bool   $visible       Whether the cart item is visible. Default true.
	 * @param array  $cart_item     The cart item data.
	 * @param string $cart_item_key The cart item key.
	 */
	$visible = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

	// `instanceof WC_Product` (core 11.0.0) : un produit supprimé de la base
	// laisse un `$cart_item['data']` qui n'est plus un WC_Product.
	if ( ! $_product instanceof WC_Product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! $visible ) {
		return;
	}

	$is_sub      = class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $_product );
	$badge_label = $is_sub ? __( 'Abonnement', '180c' ) : __( 'Revue', '180c' );
	$badge_mod   = $is_sub ? 'subscription' : 'physical';
	?>
	<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item _180c-order-review__item', $cart_item, $cart_item_key ) ); ?>">
		<td class="product-name">
			<span class="checkout-review__badge _180c-order-review__badge checkout-review__badge--<?php echo esc_attr( $badge_mod ); ?> _180c-order-review__badge--<?php echo esc_attr( $badge_mod ); ?>"><?php echo esc_html( $badge_label ); ?></span>
			<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;'; ?>
			<?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</td>
		<td class="product-total">
			<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</td>
	</tr>
	<?php
};

// Partition des articles : abonnement vs produit (pour le groupement en panier mixte).
$groups = array(
	'subscription' => array(),
	'product'      => array(),
);

foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
	$product = $cart_item['data'];
	$is_sub  = $product instanceof WC_Product && class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product );
	$groups[ $is_sub ? 'subscription' : 'product' ][ $cart_item_key ] = $cart_item;
}

$is_grouped = ! empty( $groups['subscription'] ) && ! empty( $groups['product'] );

// Données du bloc paiement (helpers read-only, aucun calcul ici).
$today_total = function_exists( '_180c_get_today_total' ) ? _180c_get_today_total() : WC()->cart->get_total( 'view' );
$tax_lines   = function_exists( '_180c_get_cart_tax_lines' ) ? _180c_get_cart_tax_lines() : array();
$recurring   = function_exists( '_180c_get_recurring_summary' ) ? _180c_get_recurring_summary() : null;
$has_ship    = function_exists( '_180c_cart_has_shipping' ) ? _180c_cart_has_shipping() : ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() );
?>
<table class="shop_table woocommerce-checkout-review-order-table checkout-review _180c-order-review">
	<thead>
		<tr class="_180c-order-review__head">
			<th class="product-name" colspan="2"><?php esc_html_e( 'Votre commande', '180c' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		if ( $is_grouped ) {
			// Panier mixte : groupement visuel produits / abonnement.
			$group_labels = array(
				'product'      => __( 'Produits', '180c' ),
				'subscription' => __( 'Abonnement', '180c' ),
			);
			foreach ( $group_labels as $group_key => $group_label ) {
				if ( empty( $groups[ $group_key ] ) ) {
					continue;
				}
				?>
				<tr class="checkout-review__group _180c-order-review__group">
					<th colspan="2" scope="colgroup"><?php echo esc_html( $group_label ); ?></th>
				</tr>
				<?php
				foreach ( $groups[ $group_key ] as $cart_item_key => $cart_item ) {
					$render_item( $cart_item_key, $cart_item );
				}
			}
		} else {
			// Panier homogène : liste simple.
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$render_item( $cart_item_key, $cart_item );
			}
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</tbody>
	<tfoot>

		<tr class="_180c-order-review__pay-head">
			<th colspan="2"><?php esc_html_e( 'Récapitulatif paiement', '180c' ); ?></th>
		</tr>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<tr class="order-total _180c-order-review__today">
			<th><?php esc_html_e( 'À payer aujourd\'hui', '180c' ); ?></th>
			<td><strong><?php echo wp_kses_post( $today_total ); ?></strong></td>
		</tr>

		<?php foreach ( $tax_lines as $tax_line ) : ?>
			<tr class="_180c-order-review__tax">
				<th><?php echo esc_html( $tax_line['label'] ); ?></th>
				<td><?php echo wp_kses_post( $tax_line['amount'] ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount _180c-order-review__discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee _180c-order-review__fee">
				<th><?php echo esc_html( $fee->name ); ?></th>
				<td><?php wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( $has_ship ) : ?>

			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>

			<?php wc_cart_totals_shipping_html(); ?>

			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>

		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

		<?php if ( null !== $recurring ) : ?>
			<tr class="_180c-order-review__recurring">
				<th><?php echo esc_html( $recurring['label'] ); ?></th>
				<td><?php echo wp_kses_post( $recurring['amount'] ); ?></td>
			</tr>
			<?php if ( ! empty( $recurring['next_date'] ) ) : ?>
				<tr class="_180c-order-review__recurring-date">
					<th colspan="2">
						<?php
						/* translators: %s: date du 1er renouvellement de l'abonnement. */
						printf( esc_html__( 'à partir du %s', '180c' ), esc_html( $recurring['next_date'] ) );
						?>
					</th>
				</tr>
			<?php endif; ?>
		<?php endif; ?>

	</tfoot>
</table>
