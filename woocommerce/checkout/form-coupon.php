<?php
/**
 * Checkout coupon form — surcharge 180°C.
 *
 * Coupon discret en accordéon. Un bouton-déclencheur 180°C (`.checkout__coupon-trigger`)
 * pilote l'ouverture du panneau ; le repli clavier + `aria-expanded` est géré par
 * `src/js/modules/checkout.js` (étape 5).
 *
 * Le panneau EST le `<form class="checkout_coupon">` natif : on conserve la classe
 * `checkout_coupon`, `#coupon_code` et `button[name=apply_coupon]` pour que
 * l'application AJAX native du coupon (WC `wc_checkout_coupons`) fonctionne sans
 * réécriture JS. On ne rend PAS le déclencheur natif `a.showcoupon` : notre
 * propre déclencheur le remplace.
 *
 * Progressive enhancement : aucun `hidden`/`display:none` dans le markup → sans
 * JS le panneau reste accessible. (Avec JS, WC masque le formulaire à l'init et
 * notre module gère le toggle via le bouton.)
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.8.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! wc_coupons_enabled() ) {
	return;
}
?>

<div class="checkout__coupon">

	<button
		type="button"
		class="checkout__coupon-trigger"
		aria-expanded="false"
		aria-controls="coupon-panel"
	>
		<?php esc_html_e( 'Vous avez un code ?', '180c' ); ?>
	</button>

	<form
		class="checkout_coupon woocommerce-form-coupon checkout__coupon-panel"
		id="coupon-panel"
		method="post"
	>
		<p class="checkout__coupon-field form-row">
			<label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Code promo', '180c' ); ?></label>
			<input
				type="text"
				name="coupon_code"
				class="input-text"
				placeholder="<?php esc_attr_e( 'Votre code', '180c' ); ?>"
				id="coupon_code"
				value=""
				autocomplete="off"
			/>
			<button type="submit" class="btn btn--secondary checkout__coupon-apply" name="apply_coupon" value="<?php esc_attr_e( 'Appliquer', '180c' ); ?>">
				<?php esc_html_e( 'Appliquer', '180c' ); ?>
			</button>
		</p>
	</form>

</div>
