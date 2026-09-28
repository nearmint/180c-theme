<?php
/**
 * Pattern PHP — « À lire aussi ».
 *
 * Affiche jusqu'à 4 articles recommandés (même catégorie principale OU tags
 * partagés), randomisés à chaque affichage, avec repli sur les articles
 * récents. Rendu via le shell de rail mutualisé _180c_render_rail() (scroll
 * latéral) — strictement identique au rail d'articles du Home Builder
 * (parts/modules/articles_rail.php) : cartes article-card variante 'sm',
 * modifier 'articles_rail'. La section est entièrement masquée s'il n'y a
 * aucun candidat.
 *
 * Inclus depuis single.php via get_template_part( 'patterns/article-related' ).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks/_helpers.php';

$_180c_ar_post_id = (int) get_the_ID();
if ( ! $_180c_ar_post_id || ! function_exists( '_180c_render_rail' ) ) {
	return;
}

$_180c_ar_query = _180c_article_related( $_180c_ar_post_id, 10 );

$_180c_ar_items = array();
while ( $_180c_ar_query->have_posts() ) {
	$_180c_ar_query->the_post();
	$_180c_ar_card = _180c_block_render_article_card( get_post(), 'sm' );
	if ( $_180c_ar_card ) {
		$_180c_ar_items[] = $_180c_ar_card;
	}
}
wp_reset_postdata();

if ( empty( $_180c_ar_items ) ) {
	return;
}

_180c_render_rail(
	array(
		'title'        => __( 'À lire aussi', '180c' ),
		'items_html'   => $_180c_ar_items,
		'region_label' => __( 'À lire aussi', '180c' ),
		'modifier'     => 'articles_rail',
	)
);
