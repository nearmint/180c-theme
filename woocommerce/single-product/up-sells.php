<?php
/**
 * Single Product Up-Sells — surcharge 180°C.
 *
 * Rendu via le composant rail Home Builder mutualisé (_180c_render_rail) :
 * titre « Complétez votre collection », « Voir tout » → page Boutique, scroll
 * latéral. Cartes produit via _180c_block_render_product_card(). Images non
 * croppées (override CSS scopé .home-module--product_upsells dans woo.css).
 * Source = upsells définis sur le produit ; nombre borné à 8 (jamais -1) par le
 * filtre woocommerce_upsell_display_args dans inc/woo/single-product.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.6.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '_180c_block_render_product_card' ) ) {
	require_once get_template_directory() . '/inc/blocks/_helpers.php';
}

if ( ! empty( $upsells ) ) {

	$heading = apply_filters( 'woocommerce_product_upsells_products_heading', __( 'Complétez votre collection', '180c' ) );

	$items_html = array();
	foreach ( $upsells as $upsell ) {
		$card = _180c_block_render_product_card(
			$upsell,
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
				'region_label' => __( 'Compléments suggérés', '180c' ),
				'modifier'     => 'product_upsells',
			)
		);
	}
}

wp_reset_postdata();
