<?php
/**
 * Flux RSS — inclusion des recettes (en extrait) dans le flux principal.
 *
 * Le flux WordPress principal (/feed/) n'expose nativement que les `post`.
 * Ce module y ajoute le CPT `recipe`, mais en protégeant le contenu premium :
 * seuls les métadonnées + un extrait (chapô) sont diffusés, jamais le déroulé
 * pas-à-pas. Le déroulé reste réservé aux abonnés sur le site.
 *
 * Le déroulé d'une recette vit dans des champs ACF (`steps`) rendus uniquement
 * par single-recipe.php : il n'est donc jamais présent dans `post_content` ni
 * dans le flux. Les filtres ci-dessous sont une protection explicite et
 * défensive : on remplace systématiquement le corps par un teaser + lien.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ajoute le CPT `recipe` au flux principal du site.
 *
 * Ne touche qu'à la requête principale d'un flux générique (`post`). Les flux
 * déjà dédiés à un type précis (post_type archive feed, flux de taxonomie…)
 * conservent leur périmètre.
 *
 * @param WP_Query $query Requête en cours.
 * @return void
 */
function _180c_feed_include_recipes( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_feed() ) {
		return;
	}

	$existing = $query->get( 'post_type' );
	if ( ! empty( $existing ) && 'post' !== $existing ) {
		return;
	}

	$query->set( 'post_type', array( 'post', 'recipe' ) );
	// Sécurité : uniquement les contenus publiés dans le flux (jamais de
	// brouillon / privé / programmé / en attente, même si un autre filtre
	// élargissait post_status).
	$query->set( 'post_status', 'publish' );
}
add_action( 'pre_get_posts', '_180c_feed_include_recipes' );

/**
 * Construit un teaser sûr pour une recette en flux (jamais le déroulé).
 *
 * Priorité : chapô éditorial ACF (`recipe_intro`) → extrait natif. Toujours
 * dépouillé de tout markup susceptible de transporter du contenu premium.
 *
 * @param int $post_id ID de la recette.
 * @return string Teaser HTML (paragraphes), ou chaîne vide.
 */
function _180c_feed_recipe_teaser( $post_id ) {
	if ( function_exists( '_180c_acf' ) ) {
		$intro = (string) _180c_acf( 'recipe_intro', $post_id );
		if ( '' !== trim( $intro ) ) {
			return wpautop( wp_strip_all_tags( $intro ) );
		}
	}

	$excerpt = (string) get_the_excerpt( $post_id );
	if ( '' !== trim( $excerpt ) ) {
		return wpautop( wp_strip_all_tags( $excerpt ) );
	}

	return '';
}

/**
 * Filtre `the_content_feed` : remplace le corps d'une recette par teaser + lien.
 *
 * @param string $content Contenu du flux (déjà passé par `the_content`).
 * @return string
 */
function _180c_feed_gate_recipe_content( $content ) {
	if ( ! is_feed() || 'recipe' !== get_post_type() ) {
		return $content;
	}

	$post_id = (int) get_the_ID();
	$teaser  = _180c_feed_recipe_teaser( $post_id );

	return $teaser . "\n<p><a href=\"" . esc_url( get_permalink( $post_id ) ) . '">'
		. esc_html__( 'Voir la recette complète sur 180°C', '180c' )
		. "</a></p>\n";
}
add_filter( 'the_content_feed', '_180c_feed_gate_recipe_content' );

/**
 * Filtre `the_excerpt_rss` : limite l'extrait RSS d'une recette au teaser.
 *
 * @param string $excerpt Extrait RSS calculé.
 * @return string
 */
function _180c_feed_gate_recipe_excerpt( $excerpt ) {
	if ( ! is_feed() || 'recipe' !== get_post_type() ) {
		return $excerpt;
	}

	$teaser = _180c_feed_recipe_teaser( (int) get_the_ID() );

	return '' !== $teaser ? wp_strip_all_tags( $teaser ) : $excerpt;
}
add_filter( 'the_excerpt_rss', '_180c_feed_gate_recipe_excerpt' );
