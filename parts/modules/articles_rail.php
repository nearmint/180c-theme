<?php
/**
 * Template part — Module home : Rail d'articles.
 *
 * Sous-champs ACF :
 *   - title (text)
 *   - mode (radio : recent|category|tag|manual)
 *   - category (taxonomy term object, mode=category)
 *   - tag (taxonomy term object, mode=tag)
 *   - count (number)
 *   - manual_posts (post_object multiple, mode=manual)
 *
 * Rend via le shell mutualisé _180c_render_rail(). Le mode « recent » est
 * mis en cache (transient, TTL 5 min). « Voir tout » pointe vers le terme
 * en mode category/tag.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks/_helpers.php';

$title = get_sub_field( 'title' );
$mode  = get_sub_field( 'mode' ) ?: 'recent';
$count = (int) ( get_sub_field( 'count' ) ?: 8 );

$query_args = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => $count,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
);

$view_all_url = null;

switch ( $mode ) {
	case 'category':
		$category = get_sub_field( 'category' );
		if ( $category ) {
			$term_id                 = is_object( $category ) ? (int) $category->term_id : (int) $category;
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => array( $term_id ),
				),
			);
			$link                    = get_term_link( $term_id, 'category' );
			$view_all_url            = is_wp_error( $link ) ? null : $link;
		}
		break;

	case 'tag':
		$tag = get_sub_field( 'tag' );
		if ( $tag ) {
			$term_id                 = is_object( $tag ) ? (int) $tag->term_id : (int) $tag;
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => array( $term_id ),
				),
			);
			$link                    = get_term_link( $term_id, 'post_tag' );
			$view_all_url            = is_wp_error( $link ) ? null : $link;
		}
		break;

	case 'manual':
		$manual_posts = get_sub_field( 'manual_posts' );
		if ( ! empty( $manual_posts ) ) {
			$post_ids                     = array_map(
				static function ( $p ) {
					return is_object( $p ) ? (int) $p->ID : (int) $p;
				},
				(array) $manual_posts
			);
			$query_args['post__in']       = $post_ids;
			$query_args['orderby']        = 'post__in';
			$query_args['posts_per_page'] = count( $post_ids );
		}
		break;

	case 'recent':
	default:
		// Aucun filtre supplémentaire.
		break;
}

// Cache court sur le mode « recent » (contenu non personnalisé).
$items_html = false;
$cache_key  = '';
if ( 'recent' === $mode ) {
	$cache_key  = '_180c_rail_articles_recent_' . $count;
	$items_html = get_transient( $cache_key );
}

if ( false === $items_html ) {
	$query      = new WP_Query( $query_args );
	$items_html = array();

	while ( $query->have_posts() ) {
		$query->the_post();
		$card = _180c_block_render_article_card( get_post(), 'sm' );
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

// « Voir tout » par défaut (mode non filtré) → Home Gazette (page `la-gazette`).
// Les modes category/tag conservent le lien du terme calculé plus haut.
if ( ! $view_all_url ) {
	$gazette      = get_page_by_path( 'la-gazette' );
	$view_all_url = $gazette instanceof WP_Post ? get_permalink( $gazette ) : home_url( '/la-gazette/' );
}

_180c_render_rail(
	array(
		'title'        => $title,
		'items_html'   => $items_html,
		'view_all_url' => $view_all_url,
		'region_label' => $title ? $title : __( 'Articles', '180c' ),
		'modifier'     => 'articles_rail',
	)
);
