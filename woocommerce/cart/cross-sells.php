<?php
/**
 * Cross-sells du panier — override 180°C.
 *
 * Surcharge de `woocommerce/templates/cart/cross-sells.php`. Le rendu réel est
 * mutualisé dans `_180c_crosssell_module_html()` (inc/woo/cart-crosssells.php) :
 * un encart pleine largeur fond accent (titre + sous-titre + CTA) mettant en
 * avant l'abonnement mensuel, partagé avec le mini-panier latéral.
 *
 * Ce template n'est appelé par `woocommerce_cross_sell_display()` que lorsque
 * le panier porte des cross-sells (donc l'abonnement attribué à un produit
 * présent). Le module se ré-évalue malgré tout via sa propre condition
 * (`_180c_crosssell_is_offered()`) pour rester cohérent avec le mini-panier.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package 180c
 * @version 9.6.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '_180c_crosssell_module_html' ) ) {
	return;
}

echo _180c_crosssell_module_html( 'full' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le renderer.
