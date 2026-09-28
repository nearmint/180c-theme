<?php
/**
 * Bloc acf/rail-produit — rendu serveur (template ACF).
 *
 * Rail horizontal de produits WooCommerce, inséré dans le flux d'un article.
 * Deux sources au choix :
 *  - « categorie » : les N derniers produits publiés d'un terme product_cat
 *    (tri date DESC, défaut 8, sans lien « Voir tout »).
 *  - « manuel »    : une sélection de produits, dans l'ordre saisi.
 *
 * Les cartes sont rendues via _180c_block_render_product_card() (variante 'sm')
 * et le rail via le shell mutualisé _180c_render_rail() (markup .home-module
 * .rail-180c : scroll-snap + flèches desktop pilotées par rail.js, déjà chargé
 * dès qu'un .home-module est présent dans la page).
 *
 * Contexte ACF disponible : $block, $content, $is_preview, $post_id.
 *
 * Les variables locales sont préfixées `_180c_` : ce template est inclus en
 * portée de fichier par ACF, donc PHPCS (PrefixAllGlobals) les analyse comme
 * des globales.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/_helpers.php';

$_180c_rp_is_preview = ! empty( $is_preview );

/**
 * Placeholder éditeur factorisé (no-op en front).
 *
 * @param bool   $is_preview Contexte d'aperçu éditeur.
 * @param string $message    Message à afficher.
 * @return void
 */
$_180c_rp_placeholder = static function ( $is_preview, $message ) {
	if ( $is_preview ) {
		echo '<div class="block-180c-rail-produit block-180c-rail-produit--placeholder"><p>'
			. esc_html( $message )
			. '</p></div>';
	}
};

if ( ! class_exists( 'WooCommerce' ) || ! function_exists( '_180c_render_rail' ) ) {
	$_180c_rp_placeholder( $_180c_rp_is_preview, __( 'Rail de produits : WooCommerce est requis.', '180c' ) );
	return;
}

$_180c_rp_source = (string) get_field( 'source' );
$_180c_rp_source = in_array( $_180c_rp_source, array( 'categorie', 'manuel' ), true ) ? $_180c_rp_source : 'categorie';

$_180c_rp_query_args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
);

if ( 'manuel' === $_180c_rp_source ) {
	$_180c_rp_ids = array_values( array_filter( array_map( 'absint', (array) get_field( 'produits' ) ) ) );

	if ( empty( $_180c_rp_ids ) ) {
		$_180c_rp_placeholder( $_180c_rp_is_preview, __( 'Rail de produits : sélectionnez au moins un produit.', '180c' ) );
		return;
	}

	$_180c_rp_query_args['post__in']       = $_180c_rp_ids;
	$_180c_rp_query_args['orderby']        = 'post__in';
	$_180c_rp_query_args['posts_per_page'] = count( $_180c_rp_ids );
} else {
	$_180c_rp_term_id = (int) get_field( 'categorie' );

	if ( $_180c_rp_term_id < 1 ) {
		$_180c_rp_placeholder( $_180c_rp_is_preview, __( 'Rail de produits : choisissez une catégorie de produits.', '180c' ) );
		return;
	}

	$_180c_rp_limit = (int) get_field( 'nombre' );
	$_180c_rp_limit = $_180c_rp_limit > 0 ? min( 20, $_180c_rp_limit ) : 8;

	$_180c_rp_query_args['posts_per_page'] = $_180c_rp_limit;
	$_180c_rp_query_args['orderby']        = 'date';
	$_180c_rp_query_args['order']          = 'DESC';
	$_180c_rp_query_args['tax_query']      = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $_180c_rp_term_id,
		),
	);
}

$_180c_rp_products = new WP_Query( $_180c_rp_query_args );
$_180c_rp_items    = array();

while ( $_180c_rp_products->have_posts() ) {
	$_180c_rp_products->the_post();
	$_180c_rp_card = _180c_block_render_product_card( get_post(), array( 'variant' => 'sm' ) );
	if ( '' !== $_180c_rp_card ) {
		$_180c_rp_items[] = $_180c_rp_card;
	}
}
wp_reset_postdata();

if ( empty( $_180c_rp_items ) ) {
	$_180c_rp_placeholder( $_180c_rp_is_preview, __( 'Rail de produits : aucun produit trouvé.', '180c' ) );
	return;
}

$_180c_rp_title = (string) get_field( 'titre' );

// Attributs de wrapper (anchor + className éventuels), alignés sur le modèle
// acf/coordonnees-lieu (les blocs ACF exposent ces valeurs via $block).
$_180c_rp_classes = 'block-180c-rail-produit';
if ( ! empty( $block['className'] ) ) {
	$_180c_rp_classes .= ' ' . $block['className'];
}
$_180c_rp_anchor = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

echo '<div class="' . esc_attr( $_180c_rp_classes ) . '"' . $_180c_rp_anchor . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- classes + anchor échappés ci-dessus.
_180c_render_rail(
	array(
		'title'        => $_180c_rp_title,
		'items_html'   => $_180c_rp_items,
		'region_label' => '' !== $_180c_rp_title ? $_180c_rp_title : __( 'Sélection de produits', '180c' ),
		'modifier'     => 'products_rail',
	)
);
echo '</div>';
