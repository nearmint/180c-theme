<?php
/**
 * Template part — Drawer mini-panier off-canvas.
 *
 * Shell statique toujours présent dans le DOM (inclus une fois dans le footer,
 * hors #site-shell pour préserver le position:fixed). Le corps interactif est
 * rendu par _180c_cart_drawer_body() et rafraîchi par le fragment WooCommerce
 * après chaque mutation AJAX.
 *
 * Ouverture / fermeture / focus-trap : src/js/modules/cart.js.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '_180c_cart_drawer_body' ) ) {
	return;
}
?>
<div class="cart-drawer" id="cart-drawer" data-cart-drawer aria-hidden="true">

	<div class="cart-drawer__overlay js-cart-overlay" data-cart-overlay></div>

	<aside
		class="cart-drawer__panel"
		role="dialog"
		aria-modal="true"
		aria-labelledby="cart-drawer-title"
		tabindex="-1"
	>
		<header class="cart-drawer__head">
			<h2 class="cart-drawer__title" id="cart-drawer-title"><?php esc_html_e( 'Votre panier', '180c' ); ?></h2>
			<button type="button" class="cart-drawer__close js-cart-close" aria-label="<?php esc_attr_e( 'Fermer le panier', '180c' ); ?>">
				<?php echo _180c_render_svg_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		</header>

		<?php echo _180c_cart_drawer_body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	</aside><!-- .cart-drawer__panel -->
</div><!-- .cart-drawer -->
