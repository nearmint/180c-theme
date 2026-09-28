<?php
/**
 * Template part : carte produit.
 *
 * Pendant de parts/recipe-card.php / parts/article-card.php pour le CPT
 * `product` (WooCommerce). S'appuie sur _180c_block_render_product_card()
 * (inc/blocks/_helpers.php), qui accepte un WP_Post ou un WC_Product.
 *
 * Usage :
 *   get_template_part( 'parts/product-card', null, array(
 *       'product_id' => 123,
 *       'variant'    => 'sm',   // 'sm' (rail) | 'md' (grille, défaut)
 *       'show_cta'   => true,
 *   ) );
 *
 * @package 180c
 *
 * @var array $args {
 *     @type int    $product_id ID du produit.
 *     @type string $variant       'sm' | 'md' (défaut). `size` accepté en alias.
 *     @type bool   $show_cta      Afficher le CTA panier (défaut true).
 *     @type string $heading_level Niveau du titre de carte : 'h2'|'h3'|'h4' (défaut 'h3').
 * }
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks/_helpers.php';

$_180c_product_id = isset( $args['product_id'] ) ? (int) $args['product_id'] : (int) get_the_ID();

if ( ! $_180c_product_id ) {
	return;
}

$_180c_product_post = get_post( $_180c_product_id );

if ( ! $_180c_product_post ) {
	return;
}

$_180c_card_args = array();
if ( isset( $args['variant'] ) ) {
	$_180c_card_args['variant'] = (string) $args['variant'];
} elseif ( isset( $args['size'] ) ) {
	$_180c_card_args['variant'] = (string) $args['size'];
}
if ( isset( $args['show_cta'] ) ) {
	$_180c_card_args['show_cta'] = (bool) $args['show_cta'];
}
if ( isset( $args['heading_level'] ) ) {
	$_180c_card_args['heading_level'] = (string) $args['heading_level'];
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML déjà échappé dans le helper.
echo _180c_block_render_product_card( $_180c_product_post, $_180c_card_args );
