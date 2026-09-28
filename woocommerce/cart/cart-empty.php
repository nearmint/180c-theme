<?php
/**
 * Panier vide — surcharge 180°C.
 *
 * Remplace le rendu WooCommerce par défaut (notice + bouton « Return to
 * shop » vers la page boutique WC) par un état vide aux tokens du design
 * system : icône, message Playfair, CTA accent vers la page éditoriale
 * /boutique/ (et non la page produits WC /tous-les-produits/).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

/*
 * On supprime la notice WooCommerce par défaut (wc_empty_cart_message) pour
 * éviter le doublon avec notre message ci-dessous, tout en laissant le hook
 * disponible aux extensions tierces.
 *
 * @hooked wc_empty_cart_message - 10
 */
remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
do_action( 'woocommerce_cart_is_empty' );
?>

<div class="cart-empty">
	<span class="cart-empty__icon" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">
			<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
			<line x1="3" y1="6" x2="21" y2="6"/>
			<path d="M16 10a4 4 0 0 1-8 0"/>
		</svg>
	</span>

	<p class="cart-empty__text"><?php esc_html_e( 'Votre panier est actuellement vide.', '180c' ); ?></p>

	<a class="btn btn--primary cart-empty__cta" href="<?php echo esc_url( home_url( '/boutique/' ) ); ?>">
		<?php esc_html_e( 'Retour à la boutique', '180c' ); ?>
	</a>
</div>
