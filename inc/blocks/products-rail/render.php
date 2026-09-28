<?php
/**
 * Bloc 180c/products-rail — rendu serveur.
 *
 * Rail produit horizontal (scroll-snap) rendu via le composant product-card
 * mutualisé (variante 'sm') et le shell de rail du Home Builder
 * (_180c_render_rail → markup .home-module rail-180c, flèches desktop chargées
 * par rail.js). Remplace le shortcode [rail_produits]. Source : IDs explicites
 * (prioritaires, ordre respecté) OU slug(s) de catégorie produit. Requête
 * bornée (jamais -1).
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/_helpers.php';

if ( ! class_exists( 'WooCommerce' ) || ! function_exists( '_180c_render_rail' ) ) {
	return;
}

$title = isset( $attributes['title'] ) ? sanitize_text_field( (string) $attributes['title'] ) : '';
$ids   = isset( $attributes['ids'] ) ? (string) $attributes['ids'] : '';
$cat   = isset( $attributes['cat'] ) ? (string) $attributes['cat'] : '';
$limit = isset( $attributes['limit'] ) ? max( 1, min( 24, (int) $attributes['limit'] ) ) : 8;

$id_list = array_values( array_filter( array_map( 'absint', explode( ',', $ids ) ) ) );

$query_args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => $limit,
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
);

if ( ! empty( $id_list ) ) {
	// IDs explicites prioritaires ; on respecte l'ordre saisi.
	$query_args['post__in'] = $id_list;
	$query_args['orderby']  = 'post__in';
} elseif ( '' !== trim( $cat ) ) {
	$slugs = array_values( array_filter( array_map( 'trim', explode( ',', $cat ) ) ) );

	$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $slugs,
		),
	);
} else {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-products-rail block-180c-products-rail--placeholder"><p>'
			. esc_html__( 'Rail de produits : indiquez des IDs produits ou un slug de catégorie dans les réglages du bloc.', '180c' )
			. '</p></div>';
	}
	return;
}

$products = new WP_Query( $query_args );
$items    = array();

while ( $products->have_posts() ) {
	$products->the_post();
	$card = _180c_block_render_product_card( get_post(), array( 'variant' => 'sm' ) );
	if ( $card ) {
		$items[] = $card;
	}
}
wp_reset_postdata();

if ( empty( $items ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-products-rail block-180c-products-rail--placeholder"><p>'
			. esc_html__( 'Rail de produits : aucun produit trouvé.', '180c' )
			. '</p></div>';
	}
	return;
}

$region_label  = '' !== $title ? $title : __( 'Sélection de produits', '180c' );
$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-products-rail' ) );

echo '<div ' . $wrapper_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes échappe ses attributs.
_180c_render_rail(
	array(
		'title'        => $title,
		'items_html'   => $items,
		'region_label' => $region_label,
		'modifier'     => 'products_rail',
	)
);
echo '</div>';
