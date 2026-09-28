<?php
/**
 * Schema.org Article (JSON-LD) — construction du nœud.
 *
 * Source de vérité du nœud Article, en parité stricte avec
 * `inc/recipe-schema.php`. Le nœud est injecté dans le @graph unique de
 * la page par `inc/seo/schema.php::_180c_schema_article()` (qui se réduit
 * désormais à un wrapper sur la fonction ci-dessous).
 *
 * `_180c_render_article_jsonld()` est fournie pour un rendu autonome
 * éventuel (apps headless, contextes hors graphe global), mais N'EST PAS
 * appelée par single.php afin d'éviter un second bloc JSON-LD Article
 * (duplication = pénalité Rich Results).
 *
 * Différences avec la version initiale :
 *  - author.url = page auteur publique (si toggle ACF author_public on) ;
 *  - articleSection = catégorie principale (helper
 *    _180c_article_primary_category) — meilleur signal sémantique que la
 *    1re catégorie WP.
 *
 * Durcissement ITEM 6 (audit GSC 2026-07-30) :
 *  - headline / author.name / articleSection normalisés (entités HTML
 *    décodées) — le JSON-LD est une donnée, pas du HTML ;
 *  - headline tronqué à 110 caractères sur limite de mot ;
 *  - image encodée RFC 3986 et `full` en tête, comme le nœud Recipe ;
 *  - description repliée sur l'extrait hors de la page courante.
 *
 * Type retenu : `Article`, et non `NewsArticle`. La gazette publie du
 * magazine culinaire intemporel — les articles les plus performants ont
 * 7 à 9 ans (câprier 2017, pâte à pizza 2018) — sans rubrique d'actualité
 * datée. `NewsArticle` signalerait à Google une fraîcheur que le contenu
 * n'a pas, et exposerait la gazette aux critères de Google News.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Longueur maximale du `headline` Article, ellipse comprise.
 *
 * Google ignore purement et simplement les `headline` de plus de 110
 * caractères : la propriété requise est alors considérée comme absente.
 * Un seul article du corpus dépasse aujourd'hui (127 c), mais la coupe doit
 * être posée dans le générateur, pas laissée à la vigilance de la rédaction.
 *
 * @var int
 */
const _180C_ARTICLE_HEADLINE_MAX = 110;

/**
 * Construit le nœud Schema.org Article pour un post natif.
 *
 * Conforme Google Rich Results : @type, @id, headline, mainEntityOfPage,
 * datePublished, dateModified, publisher, author (Person + url),
 * description, image, articleSection.
 *
 * @param int $post_id ID de l'article.
 * @return array|null Tableau prêt pour wp_json_encode, ou null si introuvable.
 */
function _180c_build_article_schema_node( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== get_post_type( $post ) ) {
		return null;
	}

	$home      = home_url( '/' );
	$permalink = get_permalink( $post_id );

	// `headline` : le JSON-LD est une donnée, pas du HTML. `get_the_title()`
	// renvoie du texte entité-encodé (`c&rsquo;est`, `Portraits &amp; …`) que
	// wp_json_encode() sérialise tel quel — 20 % du corpus (117/585 posts)
	// exposait ainsi des `&rsquo;`, `&#8211;` et `&#8230;` bruts à Google.
	// On normalise puis on tronque sur limite de mot.
	$headline = _180c_title_normalize( (string) get_the_title( $post_id ) );
	$headline = _180c_title_truncate( $headline, _180C_ARTICLE_HEADLINE_MAX );

	$schema = array(
		'@type'            => 'Article',
		'@id'              => $permalink . '#article',
		'headline'         => $headline,
		'mainEntityOfPage' => $permalink,
		'datePublished'    => get_the_date( 'c', $post_id ),
		'dateModified'     => get_the_modified_date( 'c', $post_id ),
		'publisher'        => array( '@id' => $home . '#organization' ),
	);

	// Auteur — enrichi de l'URL de la page auteur publique.
	$author_id = (int) $post->post_author;
	if ( $author_id ) {
		$author_name = function_exists( '_180c_author_display_name' )
			? _180c_author_display_name( $author_id )
			: (string) get_the_author_meta( 'display_name', $author_id );

		// Même précaution que pour le headline : les patronymes à apostrophe
		// ou à esperluette remontent entité-encodés.
		$author_name = _180c_title_normalize( (string) $author_name );

		if ( '' !== $author_name ) {
			$author_node = array(
				'@type' => 'Person',
				'name'  => $author_name,
			);

			// L'URL n'est exposée dans le schema que si la page auteur est
			// publique (toggle ACF author_public). Sinon on n'expose pas un
			// lien que la 301 va de toute façon rediriger vers l'accueil.
			$is_public = function_exists( '_180c_author_is_public' )
				? _180c_author_is_public( $author_id )
				: true;

			if ( $is_public ) {
				$author_node['url'] = get_author_posts_url( $author_id );
			}

			$schema['author'] = $author_node;
		}
	}

	// Description : mutualise avec le pipeline meta description (priorité
	// ACF seo_description → excerpt → 1er paragraphe).
	//
	// `_180c_get_seo_description()` ne prend pas d'argument : elle lit l'objet
	// interrogé et mémoïse son résultat pour toute la requête. Elle n'est donc
	// juste que si $post_id EST la page courante — ce qui est toujours le cas
	// dans le @graph, mais pas dans le rendu autonome ci-dessous, où elle
	// renverrait la description d'un autre post. On replie alors sur l'extrait.
	$description = '';
	if ( function_exists( '_180c_get_seo_description' )
		&& is_singular()
		&& (int) get_queried_object_id() === (int) $post_id
	) {
		$description = (string) _180c_get_seo_description();
	} elseif ( function_exists( '_180c_description_clean' ) ) {
		$description = _180c_description_clean( (string) $post->post_excerpt );
	}

	if ( '' !== $description ) {
		$schema['description'] = $description;
	}

	// Image principale (featured image) — même traitement que Recipe (ITEM 3).
	//
	// Deux précautions. D'abord le chemin encodé RFC 3986 : 75 des 578 images
	// à la une de la gazette (13 %) portent un `©`, un `°` ou une apostrophe
	// typographique dans leur nom de fichier, ce qui produit une URL qui n'est
	// pas un URI valide et que les analyseurs stricts refusent. Ensuite `full`
	// en tête — Google demande des visuels d'au moins 1200 px de large, ce que
	// `large` (1024 px) ne garantit pas — puis `large` lorsqu'il pointe un
	// fichier distinct.
	$thumb_id = (int) get_post_thumbnail_id( $post_id );
	if ( $thumb_id ) {
		$images = array();
		foreach ( array( 'full', 'large' ) as $size ) {
			$image_url = wp_get_attachment_image_url( $thumb_id, $size );
			if ( ! $image_url ) {
				continue;
			}
			$images[] = _180c_encode_url_path( $image_url );
		}
		$images = array_values( array_unique( array_filter( $images ) ) );
		if ( $images ) {
			$schema['image'] = $images;
		}
	}

	// articleSection — catégorie principale via le helper.
	if ( function_exists( '_180c_article_primary_category' ) ) {
		$primary = _180c_article_primary_category( $post_id );
		if ( $primary instanceof WP_Term ) {
			// « Portraits &amp; reportages » — la plus grosse catégorie de la
			// gazette (138 articles) — partait telle quelle dans le JSON.
			$section = _180c_title_normalize( (string) $primary->name );
			if ( '' !== $section ) {
				$schema['articleSection'] = $section;
			}
		}
	}

	return $schema;
}

/**
 * Rend un bloc <script type="application/ld+json"> autonome pour l'article.
 *
 * Fournie par parité avec le cahier des charges. NON appelée par single.php :
 * le nœud Article est déjà injecté dans le @graph global via
 * `inc/seo/schema.php`. À n'utiliser que dans un contexte sans graphe
 * global (apps headless, exports statiques, etc.).
 *
 * @param int|null $post_id ID de l'article (par défaut : courant).
 * @return string HTML du script JSON-LD, ou chaîne vide.
 */
function _180c_render_article_jsonld( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$node    = _180c_build_article_schema_node( $post_id );
	if ( ! $node ) {
		return '';
	}

	$payload = array_merge( array( '@context' => 'https://schema.org' ), $node );
	$json    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	if ( false === $json ) {
		return '';
	}

	return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n";
}
