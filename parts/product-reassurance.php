<?php
/**
 * Bloc réassurance — fiche produit.
 *
 * Rendu sous le bouton d'ajout au panier (hook woocommerce_single_product_summary
 * priorité 35, voir inc/woo/single-product.php). Conditionné au type de produit :
 *   - « Paiement sécurisé » → produits achetables sur le site (is_purchasable :
 *     false pour external) ;
 *   - « Expédition soignée » → produits réellement vendus ET expédiés. NB :
 *     needs_shipping() vaut true pour un external (non virtual), donc on le
 *     combine à is_purchasable() (false pour external) → exclut external ET
 *     virtual/subscription ;
 *   - « Service client » → toujours.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

global $product;

$_180c_is_purchasable = ( $product instanceof WC_Product ) && $product->is_purchasable();
$_180c_is_shippable   = $_180c_is_purchasable && $product->needs_shipping();
?>
<ul class="product-reassurance" role="list">
	<?php if ( $_180c_is_purchasable ) : ?>
		<li class="product-reassurance__item">
			<svg class="product-reassurance__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<path d="M12 3 4 6v5c0 5 3.4 8.3 8 10 4.6-1.7 8-5 8-10V6l-8-3Z" />
				<path d="m9 12 2 2 4-4" />
			</svg>
			<span class="product-reassurance__label"><?php esc_html_e( 'Paiement sécurisé', '180c' ); ?></span>
		</li>
	<?php endif; ?>

	<?php if ( $_180c_is_shippable ) : ?>
		<li class="product-reassurance__item">
			<svg class="product-reassurance__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z" />
				<circle cx="7" cy="18" r="1.6" />
				<circle cx="17.5" cy="18" r="1.6" />
			</svg>
			<span class="product-reassurance__label"><?php esc_html_e( 'Expédition soignée', '180c' ); ?></span>
		</li>
	<?php endif; ?>

	<li class="product-reassurance__item">
		<svg class="product-reassurance__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
			<path d="M21 11.5a8.5 8.5 0 0 1-12.2 7.7L3 21l1.8-5.8A8.5 8.5 0 1 1 21 11.5Z" />
		</svg>
		<span class="product-reassurance__label"><?php esc_html_e( 'Service client à l’écoute', '180c' ); ?></span>
	</li>
</ul>
