<?php
/**
 * Related Products — surcharge 180°C.
 *
 * Rendu via le composant rail Home Builder mutualisé (_180c_render_rail) :
 * titre, « Voir tout » → page Boutique, scroll latéral. Cartes produit via
 * _180c_block_render_product_card(). Images non croppées (override CSS scopé
 * .home-module--product_related dans woo.css). Nombre porté à 10 par le filtre
 * woocommerce_output_related_products_args (inc/woo/single-product.php).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.3.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '_180c_block_render_product_card' ) ) {
	require_once get_template_directory() . '/inc/blocks/_helpers.php';
}

if ( ! empty( $related_products ) ) {

	$heading = apply_filters( 'woocommerce_product_related_products_heading', __( 'Vous aimerez aussi', '180c' ) );

	$items_html = array();
	foreach ( $related_products as $related_product ) {
		$card = _180c_block_render_product_card(
			$related_product,
			array(
				'variant'  => 'sm',
				'show_cta' => true,
			)
		);
		if ( $card ) {
			$items_html[] = $card;
		}
	}

	if ( ! empty( $items_html ) ) {
		$boutique     = get_page_by_path( 'boutique' );
		$view_all_url = $boutique instanceof WP_Post ? get_permalink( $boutique ) : home_url( '/boutique/' );

		_180c_render_rail(
			array(
				'title'        => $heading,
				'items_html'   => $items_html,
				'view_all_url' => $view_all_url,
				'region_label' => __( 'Produits similaires', '180c' ),
				'modifier'     => 'product_related',
			)
		);
	}
}

wp_reset_postdata();
