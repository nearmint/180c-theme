<?php
/**
 * Template part — Module home : Rail de produits.
 *
 * Sous-champs ACF :
 *   - title (text)
 *   - mode (radio : recent|category|manual)
 *   - product_cat (taxonomy term object, product_cat, mode=category)
 *   - count (number)
 *   - manual_products (post_object multiple, post_type=product, mode=manual)
 *
 * Rend via le shell mutualisé _180c_render_rail(). Le mode « recent » est
 * mis en cache (transient, TTL 5 min) — la carte produit ne contient aucune
 * donnée propre à l'utilisateur.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks/_helpers.php';

$title = get_sub_field( 'title' );
$mode  = get_sub_field( 'mode' ) ?: 'recent';
$count = (int) ( get_sub_field( 'count' ) ?: 8 );

$query_args = array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => $count,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
);

$view_all_url = null;

switch ( $mode ) {
	case 'category':
		$product_cat = get_sub_field( 'product_cat' );
		if ( $product_cat ) {
			$term_id                 = is_object( $product_cat ) ? (int) $product_cat->term_id : (int) $product_cat;
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => array( $term_id ),
				),
			);
			$link                    = get_term_link( $term_id, 'product_cat' );
			$view_all_url            = is_wp_error( $link ) ? null : $link;
		}
		break;

	case 'manual':
		$manual_products = get_sub_field( 'manual_products' );
		if ( ! empty( $manual_products ) ) {
			$product_ids                  = array_map(
				static function ( $p ) {
					return is_object( $p ) ? (int) $p->ID : (int) $p;
				},
				(array) $manual_products
			);
			$query_args['post__in']       = $product_ids;
			$query_args['orderby']        = 'post__in';
			$query_args['posts_per_page'] = count( $product_ids );
		}
		break;

	case 'recent':
	default:
		// Catalogue non filtré : on masque la catégorie « Abonnement digital »
		// (alignement avec l'archive Boutique, cf. inc/woo/overrides.php §7c).
		if ( function_exists( '_180c_boutique_hidden_term_ids' ) ) {
			$hidden = _180c_boutique_hidden_term_ids();
			if ( ! empty( $hidden ) ) {
				$query_args['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $hidden,
						'operator' => 'NOT IN',
					),
				);
			}
		}
		break;
}

// Cache court sur le mode « recent » (contenu non personnalisé).
$items_html = false;
$cache_key  = '';
if ( 'recent' === $mode ) {
	$cache_key  = '_180c_rail_products_recent_v2_' . $count;
	$items_html = get_transient( $cache_key );
}

if ( false === $items_html ) {
	$query      = new WP_Query( $query_args );
	$items_html = array();

	while ( $query->have_posts() ) {
		$query->the_post();
		$card = _180c_block_render_product_card( get_post() );
		if ( $card ) {
			$items_html[] = $card;
		}
	}
	wp_reset_postdata();

	if ( $cache_key ) {
		set_transient( $cache_key, $items_html, 5 * MINUTE_IN_SECONDS );
	}
}

if ( empty( $items_html ) ) {
	return;
}

// « Voir tout » par défaut (mode non filtré) → page Boutique (slug `boutique`).
// Le mode category conserve le lien du terme calculé plus haut.
if ( ! $view_all_url ) {
	$boutique     = get_page_by_path( 'boutique' );
	$view_all_url = $boutique instanceof WP_Post ? get_permalink( $boutique ) : home_url( '/boutique/' );
}

_180c_render_rail(
	array(
		'title'        => $title,
		'items_html'   => $items_html,
		'view_all_url' => $view_all_url,
		'region_label' => $title ? $title : __( 'Produits', '180c' ),
		'modifier'     => 'products_rail',
	)
);
