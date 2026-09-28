<?php
/**
 * Page d'inscription newsletter — configuration, helpers & SEO.
 *
 * Source unique des helpers de rendu de la page `page-newsletter.php` :
 *  - ID de l'auteur mis en avant codé en dur dans une constante filtrable ;
 *  - champ nonce du formulaire public (endpoint /newsletter/public-subscribe) ;
 *  - rendu de l'image hero (ACF, candidat LCP) ;
 *  - requête des dernières recettes (rail) ;
 *  - règle de visibilité du bloc promo abonnement.
 *
 * Le SEO réutilise le module natif (inc/seo/*) via ses points d'extension —
 * voir la section « SEO » en bas de fichier (aucune balise dupliquée).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * ID WordPress de l'auteur mis en avant.
 *
 * Codé en dur, surchargeable via le filtre `_180c_newsletter_author_id`.
 * Tant qu'il vaut 0, le bloc auteur n'est pas rendu.
 *
 * @todo PO : remplacer 0 par l'ID WP réel de l'auteur mis en avant.
 */
if ( ! defined( '_180C_NEWSLETTER_AUTHOR_ID' ) ) {
	define( '_180C_NEWSLETTER_AUTHOR_ID', 0 );
}

/**
 * Slug de la page newsletter (filtrable) — utilisé pour le ciblage SEO.
 *
 * @return string
 */
function _180c_newsletter_page_slug(): string {
	return (string) apply_filters( '_180c_newsletter_page_slug', 'newsletter' );
}

/**
 * Vrai sur la page utilisant le template newsletter.
 *
 * @return bool
 */
function _180c_is_newsletter_page(): bool {
	return is_page_template( 'page-newsletter.php' );
}

/**
 * ID de l'auteur mis en avant (filtrable).
 *
 * @return int
 */
function _180c_newsletter_author_id(): int {
	return (int) apply_filters( '_180c_newsletter_author_id', _180C_NEWSLETTER_AUTHOR_ID );
}

/**
 * Champ nonce caché du formulaire public.
 *
 * Action `180c_newsletter_public`, vérifiée par l'endpoint REST
 * /newsletter/public-subscribe. Sans champ referer (inutile ici).
 *
 * @return void
 */
function _180c_newsletter_nonce_field(): void {
	wp_nonce_field( '180c_newsletter_public', '_wpnonce', false, true );
}

/**
 * HTML de l'image hero (ACF `newsletter_hero_image`, candidat LCP).
 *
 * Renvoie une chaîne vide si aucune image n'est définie : le CSS applique alors
 * un fond de repli. L'image est chargée en priorité (eager + fetchpriority high).
 *
 * @return string Balise <img> échappée par WordPress, ou chaîne vide.
 */
function _180c_newsletter_hero_image_html(): string {
	$image_id = (int) _180c_acf( 'newsletter_hero_image', get_queried_object_id(), 0 );

	if ( ! $image_id ) {
		return '';
	}

	return (string) wp_get_attachment_image(
		$image_id,
		'full',
		false,
		array(
			'class'         => 'newsletter-hero__img',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
			'alt'           => '',
		)
	);
}

/**
 * Requête des dernières recettes publiées (rail « Un avant-goût »).
 *
 * Toujours bornée (`posts_per_page` limité, `no_found_rows`) — jamais -1.
 *
 * @param int $limit Nombre de recettes (défaut 8).
 * @return WP_Query
 */
function _180c_newsletter_recent_recipes_query( int $limit = 8 ): WP_Query {
	return new WP_Query(
		array(
			'post_type'           => 'recipe',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, $limit ),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
}

/*
 * ---------------------------------------------------------------------------
 * SEO — intégration au module natif (inc/seo/*). Aucune balise dupliquée :
 * on se branche uniquement sur les points d'extension. Robots (index, follow)
 * et og:type=website sont déjà le comportement natif pour une page.
 * ---------------------------------------------------------------------------
 */

/*
 * Le <title> de la page newsletter n'est plus posé ici. Il est résolu par la
 * table centrale (inc/seo/title-map.php, clé « newsletter ») et assemblé par
 * _180c_build_title() : « Les Cahiers de Delphine — Newsletter recettes · 180°C ».
 */

add_filter( '180c/seo_description', '_180c_newsletter_seo_description' );

/**
 * Meta description de la page newsletter (propagée à <meta> + og:description).
 *
 * @param string $description Description courante.
 * @return string
 */
function _180c_newsletter_seo_description( $description ) {
	if ( _180c_is_newsletter_page() ) {
		return 'Recevez chaque semaine les meilleures recettes de Delphine par e-mail : inspirations de saison, pas-à-pas gourmands et coups de cœur de la rédaction 180°C.';
	}
	return $description;
}

add_filter( '180c/og_image_id', '_180c_newsletter_og_image_id' );

/**
 * Utilise l'image hero (ACF) comme image Open Graph de la page newsletter.
 *
 * @param int $attachment_id ID résolu par le module (0 si aucun).
 * @return int
 */
function _180c_newsletter_og_image_id( $attachment_id ) {
	if ( _180c_is_newsletter_page() ) {
		$hero = (int) _180c_acf( 'newsletter_hero_image', get_queried_object_id(), 0 );
		if ( $hero > 0 ) {
			return $hero;
		}
	}
	return (int) $attachment_id;
}

add_filter( '180c/schema_graph', '_180c_newsletter_schema_graph' );

/**
 * Ajoute un nœud WebPage au @graph Schema.org de la page newsletter.
 *
 * Conserve l'invariant « un seul bloc JSON-LD par page » du module schema.php.
 *
 * @param array $graph Structure { '@context', '@graph' }.
 * @return array
 */
function _180c_newsletter_schema_graph( $graph ) {
	if ( ! _180c_is_newsletter_page() || empty( $graph['@graph'] ) || ! is_array( $graph['@graph'] ) ) {
		return $graph;
	}

	$url = (string) get_permalink();

	$graph['@graph'][] = array(
		'@type'       => 'WebPage',
		'@id'         => $url . '#webpage',
		'name'        => wp_get_document_title(),
		'url'         => $url,
		'description' => 'Inscrivez-vous à la newsletter de Delphine pour recevoir chaque semaine les recettes de 180°C.',
	);

	return $graph;
}
