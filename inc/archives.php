<?php
/**
 * Archives génériques — logique partagée du template archive.php.
 *
 * Le thème est classique (pas FSE). `archive.php` est le template de repli de
 * la hiérarchie WP : il sert toutes les archives de taxonomie sans template
 * dédié (category, post_tag, recipe_category, recipe_season, recipe_publication,
 * recipe_tag, product_brand) ainsi que les archives de date. Les produits
 * (product_cat/product_tag) restent gérés par WooCommerce ; l'archive du CPT
 * recipe garde son `archive-recipe.php`.
 *
 * Ce fichier regroupe :
 *  - _180c_archives_context()      : eyebrow / titre / description du contexte
 *  - _180c_archives_render_card()  : dispatch de carte selon le type de contenu
 *  - _180c_archives_pre_get_posts(): borne posts_per_page (8/page)
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Résout l'eyebrow, le titre et la description de l'archive courante.
 *
 * Eyebrow = label singulier de la taxonomie (scalable à toute taxonomie) ;
 * titre = nom du terme / libellé de date ; description = description du terme.
 *
 * @return array{eyebrow:string,title:string,description:string}
 */
function _180c_archives_context() {
	$ctx = array(
		'eyebrow'     => '',
		'title'       => '',
		'description' => '',
	);

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$taxonomy = get_taxonomy( $term->taxonomy );
			if ( $taxonomy && ! empty( $taxonomy->labels->singular_name ) ) {
				$ctx['eyebrow'] = $taxonomy->labels->singular_name;
			}
			$ctx['title']       = single_term_title( '', false );
			$ctx['description'] = term_description( $term );
		}
	} elseif ( is_year() || is_month() || is_day() ) {
		$ctx['eyebrow'] = __( 'Archives', '180c' );
		$year           = (int) get_query_var( 'year' );
		$month          = (int) get_query_var( 'monthnum' );
		$day            = (int) get_query_var( 'day' );
		// Midi pour neutraliser tout décalage de fuseau sur le libellé.
		if ( is_day() ) {
			$ctx['title'] = wp_date( 'j F Y', mktime( 12, 0, 0, $month, $day, $year ) );
		} elseif ( is_month() ) {
			$ctx['title'] = wp_date( 'F Y', mktime( 12, 0, 0, $month, 1, $year ) );
		} else {
			$ctx['title'] = (string) $year;
		}
	} elseif ( is_post_type_archive() ) {
		$post_type = get_queried_object();
		if ( $post_type instanceof WP_Post_Type && ! empty( $post_type->labels->singular_name ) ) {
			$ctx['eyebrow'] = $post_type->labels->singular_name;
		}
		$ctx['title'] = post_type_archive_title( '', false );
	}

	if ( '' === $ctx['title'] ) {
		// Repli ultime : titre d'archive natif (déjà localisé par WP).
		$ctx['title'] = wp_strip_all_tags( get_the_archive_title() );
	}

	return $ctx;
}

/**
 * Rend la carte HTML d'un élément d'archive selon son type de contenu.
 *
 * Dispatch : recipe → recipe-card ; product → product-card (part WC) ;
 * post (et défaut) → article-card. Retourne une chaîne vide si aucune carte
 * n'est disponible (l'appelant ignore alors l'élément).
 *
 * @param int $post_id ID du contenu.
 * @return string HTML de la carte, ou '' si indisponible.
 */
function _180c_archives_render_card( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return '';
	}

	switch ( get_post_type( $post_id ) ) {
		case 'recipe':
			// Niveau de titre h2 : l'archive porte un <h1> unique (section-header),
			// les cartes sont les items de second niveau (a11y heading-order).
			return function_exists( '_180c_render_recipe_card' ) ? _180c_render_recipe_card( $post_id, 'md', 'h2' ) : '';

		case 'product':
			// Pas de helper string dédié (non livré) : on tamponne le
			// part product-card existant, qui sait rendre un produit seul.
			ob_start();
			get_template_part(
				'parts/product-card',
				null,
				array(
					'product_id'    => $post_id,
					'heading_level' => 'h2',
				)
			);
			return (string) ob_get_clean();

		case 'post':
		default:
			return function_exists( '_180c_render_article_card' ) ? _180c_render_article_card( $post_id, 'md', 'h2' ) : '';
	}
}

/**
 * Borne le nombre d'éléments par page sur les archives servies par archive.php.
 *
 * 8 éléments/page (cohérent avec la page auteur). Exclut l'archive du
 * CPT recipe (archive-recipe.php gère sa propre requête) et les taxonomies
 * produit (WooCommerce gère sa pagination/colonnes).
 *
 * @param WP_Query $query Requête en cours.
 * @return void
 */
function _180c_archives_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// WooCommerce pilote ses propres archives produit.
	if ( $query->is_tax( array( 'product_cat', 'product_tag', 'product_brand' ) ) ) {
		return;
	}

	// L'archive du CPT recipe a son template + sa requête dédiés.
	if ( $query->is_post_type_archive( 'recipe' ) ) {
		return;
	}

	if ( $query->is_category() || $query->is_tag() || $query->is_tax() || $query->is_date() ) {
		$query->set( 'posts_per_page', 8 );
	}
}
add_action( 'pre_get_posts', '_180c_archives_pre_get_posts' );
