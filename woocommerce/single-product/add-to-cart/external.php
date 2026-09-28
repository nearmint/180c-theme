<?php
/**
 * External / Affiliate product add to cart — surcharge 180°C.
 *
 * Produits vendus hors du site (revues et livres distribués en librairie). Le
 * rendu lui-même vit dans `_180c_product_external_cta()`
 * (inc/woo/single-product.php), source unique du CTA externe :
 *
 *   - `_product_url` pointant vers un autre domaine → lien « Trouver en
 *     librairie » vers le marchand (nouvel onglet) + note nommant le domaine ;
 *   - `_product_url` vide ou pointant sur le site → bouton désactivé portant
 *     la mention « Disponible uniquement en librairie ».
 *
 * Ce template n'est chargé que lorsque `add_to_cart_url()` est NON vide : le
 * cœur sort avant (wc-template-functions.php). Le cas vide est couvert par
 * `_180c_render_external_cta()`, rappelé sur `woocommerce_single_product_summary`.
 *
 * La barre d'achat collante (src/js/modules/product.js) reprend ce CTA via le
 * sélecteur `.product__external-cta` et son attribut `data-sticky-label`.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.2.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

echo _180c_product_external_cta( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML entièrement échappé dans le helper.
