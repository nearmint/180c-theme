<?php
/**
 * Schema.org VideoObject (JSON-LD) — construction du nœud (ITEM 15).
 *
 * Audit GSC 2026-07-30 : les contenus de `/la-gazette/videos/…` n'émettaient
 * aucun VideoObject. `/la-gazette/videos/cocotte-joues-de-boeuf/` cumule
 * des milliers d'impressions (tout comme « recette joue de bœuf en cocotte »)
 * sans le moindre rich result vidéo.
 *
 * Le nœud est injecté dans le @graph unique de la page via le filtre
 * `180c/schema_graph` (cf. `inc/seo/schema.php`) — donc jamais dans un second
 * bloc <script>, l'invariant « un seul bloc JSON-LD par page » est conservé.
 *
 * SOURCE DE LA DONNÉE — Phase 0 (relevé du 2026-07-31, base locale)
 * -----------------------------------------------------------------
 * Il n'existe AUCUN champ ACF vidéo dans le modèle de contenu. La vidéo vit
 * exclusivement dans `post_content`, sous deux formes :
 *
 *  1. bloc `core/embed` YouTube (29 des 33 contenus vidéo) — rendu en front
 *     par la façade `inc/blocks/youtube-facade.php` ;
 *  2. URL YouTube nue laissée à l'autoembed de WordPress (4 contenus).
 *
 * Le cas 2 impose de recopier le critère exact de `WP_Embed::autoembed()` :
 * WordPress ne transforme en lecteur qu'une URL SEULE sur sa ligne ou SEULE
 * dans son paragraphe. Deux contenus du corpus (`olivier-nasti-traqueur-de-
 * nature`, `herve-bourdon-petit-hotel-du-grand-large`) portent une URL YouTube
 * au fil d'un paragraphe de texte : elle n'est PAS transformée en lecteur, la
 * page n'affiche aucune vidéo, et une regex naïve leur aurait fabriqué un
 * VideoObject pour une vidéo absente de la page.
 *
 * Aucun embed Vimeo, Dailymotion ou Facebook dans le corpus (l'unique lien
 * Dailymotion rencontré, sur `marseille-se-fait-mousser`, est un lien de texte,
 * pas un embed). Le générateur ne couvre donc que YouTube : ajouter un
 * fournisseur sans contenu à baliser reviendrait à écrire du code mort.
 *
 * PAS D'APPEL RÉSEAU AU RENDU
 * ---------------------------
 * `thumbnailUrl` sort de l'image à la une (les 41 contenus de la catégorie
 * « Vidéos » en ont une, sans exception), à défaut de la vignette YouTube
 * `hqdefault` — déductible de l'identifiant, donc sans requête HTTP. La
 * vignette `maxresdefault` n'est volontairement PAS utilisée : elle n'existe
 * pas pour toutes les vidéos et le vérifier imposerait un appel HTTP bloquant
 * dans `wp_head`. Il n'y a donc aucun fetch, et par conséquent aucun transient
 * à prévoir.
 *
 * PROPRIÉTÉS VOLONTAIREMENT ABSENTES
 * ----------------------------------
 *  - `duration` : la durée n'existe nulle part côté serveur (ni ACF, ni meta) ;
 *    l'obtenir demanderait l'API YouTube Data. Omise plutôt qu'inventée.
 *  - `contentUrl` : les vidéos sont hébergées par YouTube, le site ne dispose
 *    d'aucun fichier média. Google demande de n'exposer que `embedUrl` dans ce
 *    cas de figure.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Motif d'extraction d'un identifiant de vidéo YouTube (11 caractères).
 *
 * Identique à celui de `_180c_youtube_extract_id()` (façade YouTube) : les deux
 * modules doivent voir exactement la même vidéo, sinon le JSON-LD décrirait un
 * lecteur que la page n'affiche pas.
 *
 * @var string
 */
const _180C_VIDEO_YOUTUBE_ID_RX = '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

/**
 * Slugs des catégories qui désignent un contenu éditorial vidéo.
 *
 * `videos` (terme 80, enfant de « La Gazette ») et `videos-lcd` (terme 120,
 * « Vidéos LCD ») sont les deux seules rubriques vidéo du site ; leurs contenus
 * sont servis sous `/la-gazette/videos/…`. Les descendants de `videos` sont
 * couverts automatiquement (cf. `_180c_post_is_video_content()`).
 *
 * @return string[]
 */
function _180c_video_category_slugs() {
	/**
	 * Catégories considérées comme éditorialement vidéo.
	 *
	 * @param string[] $slugs Slugs de catégories.
	 */
	return (array) apply_filters( '180c/schema/video_category_slugs', array( 'videos', 'videos-lcd' ) );
}

/**
 * Le contenu est-il un contenu vidéo éligible au balisage VideoObject ?
 *
 * Règle volontairement conservatrice : la vidéo doit être le SUJET de la page,
 * pas une illustration au fil du texte. 14 articles du corpus (rubriques
 * Opinion, Portraits, Reportages…) portent un embed YouTube accessoire ; leur
 * baliser un VideoObject reviendrait à déclarer à Google une page vidéo qui
 * n'en est pas une. Aucun d'eux, mesuré, n'a l'embed en tête de contenu.
 *
 * NE PAS ÉLARGIR LA RÈGLE AUX RECETTES « CAHIERS DE DELPHINE » — mesuré le
 * 2026-07-31 : les 4 contenus non éligibles qui affichent pourtant un vrai
 * lecteur sont `au-nom-de-la-terre` (bande-annonce dans une tribune) et trois
 * recettes des Cahiers. Or ces trois-là portent EXACTEMENT le même
 * identifiant YouTube qu'une page `/la-gazette/videos/…` déjà éligible
 * (`les-calamars-farcis` ↔ `des-calamars-farcis`, etc.). Les baliser
 * déclarerait une même vidéo sous deux URL — à Google d'arbitrer, et la page
 * recette est la moins bonne candidate puisqu'elle est premium. Le corpus
 * éligible, lui, ne contient aucun doublon d'identifiant.
 *
 * Le CPT `recipe` est éligible par nature : le nœud n'y est jamais autonome,
 * il alimente la propriété `video` du Recipe (cf. `_180c_schema_graph_add_video()`).
 *
 * @param int $post_id ID du contenu.
 * @return bool
 */
function _180c_post_is_video_content( $post_id ) {
	$post_id   = (int) $post_id;
	$is_video  = false;
	$post_type = get_post_type( $post_id );

	if ( 'recipe' === $post_type ) {
		$is_video = true;
	} elseif ( 'post' === $post_type ) {
		$slugs = _180c_video_category_slugs();
		$terms = get_the_terms( $post_id, 'category' );

		if ( $terms && ! is_wp_error( $terms ) ) {
			// Racine « Vidéos » : ses éventuelles sous-rubriques héritent de
			// l'éligibilité sans qu'il faille les déclarer une à une.
			$root    = get_term_by( 'slug', 'videos', 'category' );
			$root_id = $root instanceof WP_Term ? (int) $root->term_id : 0;

			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				if ( in_array( $term->slug, $slugs, true ) ) {
					$is_video = true;
					break;
				}
				if ( $root_id && in_array( $root_id, get_ancestors( (int) $term->term_id, 'category' ), true ) ) {
					$is_video = true;
					break;
				}
			}
		}
	}

	/**
	 * Éligibilité d'un contenu au balisage VideoObject.
	 *
	 * @param bool $is_video Résultat de la règle par défaut.
	 * @param int  $post_id  ID du contenu.
	 */
	return (bool) apply_filters( '180c/schema/is_video_content', $is_video, $post_id );
}

/**
 * Extrait l'identifiant de la vidéo YouTube réellement lue sur la page.
 *
 * Deux passes, dans l'ordre de fiabilité :
 *  1. premier bloc `core/embed` YouTube (récursif : un embed peut vivre dans
 *     un `core/group` ou un `core/columns`) ;
 *  2. repli sur les formes que `WP_Embed::autoembed()` transforme réellement en
 *     lecteur — URL seule sur sa ligne, URL seule dans son paragraphe, ou
 *     iframe YouTube déjà présente dans le contenu.
 *
 * Une seule vidéo est retournée (la première) : Google ne veut qu'un objet
 * vidéo par page, et le corpus ne compte aucun contenu multi-vidéos.
 *
 * @param int $post_id ID du contenu.
 * @return string Identifiant YouTube, ou chaîne vide.
 */
function _180c_video_extract_youtube_id( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || '' === trim( (string) $post->post_content ) ) {
		return '';
	}

	$content = (string) $post->post_content;

	// Garde bon marché avant `parse_blocks()` : le CPT `recipe` est éligible en
	// bloc (1 553 recettes publiées) alors qu'aucune ne contient de vidéo. Sans
	// ce test, chaque rendu de recette paierait un parse_blocks() pour rien.
	if ( false === strpos( $content, 'youtu' ) ) {
		return '';
	}

	// Passe 1 — blocs core/embed.
	$found = _180c_video_scan_blocks( parse_blocks( $content ) );
	if ( '' !== $found ) {
		return $found;
	}

	// Passe 2 — autoembed WordPress + iframe déjà écrite dans le contenu.
	$patterns = array(
		// URL seule sur sa ligne (1er motif de WP_Embed::autoembed()).
		'|^\s*(https?://[^\s<>"]+)\s*$|im',
		// URL seule dans son paragraphe (2e motif de WP_Embed::autoembed()).
		'|<p(?: [^>]*)?>\s*(https?://[^\s<>"]+)\s*</p>|i',
		// Iframe YouTube écrite en dur dans le contenu.
		'|<iframe[^>]+src=["\']([^"\']+)["\']|i',
	);

	foreach ( $patterns as $pattern ) {
		if ( ! preg_match_all( $pattern, $content, $matches ) ) {
			continue;
		}
		foreach ( $matches[1] as $url ) {
			if ( preg_match( _180C_VIDEO_YOUTUBE_ID_RX, $url, $id_match ) ) {
				return $id_match[1];
			}
		}
	}

	return '';
}

/**
 * Parcourt récursivement un arbre de blocs à la recherche d'un embed YouTube.
 *
 * @param array $blocks Blocs issus de parse_blocks().
 * @return string Identifiant YouTube du premier embed trouvé, ou chaîne vide.
 */
function _180c_video_scan_blocks( $blocks ) {
	if ( ! is_array( $blocks ) ) {
		return '';
	}

	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}

		if ( ! empty( $block['blockName'] ) && 'core/embed' === $block['blockName'] ) {
			$url = isset( $block['attrs']['url'] ) ? (string) $block['attrs']['url'] : '';
			if ( '' !== $url && preg_match( _180C_VIDEO_YOUTUBE_ID_RX, $url, $matches ) ) {
				return $matches[1];
			}
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			$nested = _180c_video_scan_blocks( $block['innerBlocks'] );
			if ( '' !== $nested ) {
				return $nested;
			}
		}
	}

	return '';
}

/**
 * Construit le nœud Schema.org VideoObject d'un contenu.
 *
 * @param int $post_id ID du contenu.
 * @return array|null Tableau prêt pour wp_json_encode, ou null si aucune vidéo.
 */
function _180c_build_video_schema_node( $post_id ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	$video_id = _180c_video_extract_youtube_id( $post_id );
	if ( '' === $video_id ) {
		return null;
	}

	$permalink = get_permalink( $post_id );

	// `name` : même normalisation que le headline Article (ITEM 6) — le JSON-LD
	// est une donnée, pas du HTML, et 20 % du corpus porte des `&rsquo;`.
	$name = function_exists( '_180c_title_normalize' )
		? _180c_title_normalize( (string) get_the_title( $post_id ) )
		: wp_strip_all_tags( (string) get_the_title( $post_id ) );

	$node = array(
		'@type'      => 'VideoObject',
		'@id'        => $permalink . '#video',
		'name'       => $name,
		// La date de première publication de l'article est la seule date de
		// mise en ligne dont dispose le site. C'est aussi celle qu'expose déjà
		// le nœud Article (`datePublished`) : les deux ne peuvent pas diverger.
		'uploadDate' => get_the_date( 'c', $post_id ),
		// Lecteur réellement ouvert par la façade et par le repli <noscript>
		// (`inc/blocks/youtube-facade.php`) : domaine sans cookie.
		'embedUrl'   => 'https://www.youtube-nocookie.com/embed/' . $video_id,
		'publisher'  => array( '@id' => home_url( '/' ) . '#organization' ),
	);

	// Description — même pipeline que le nœud Article, avec la même précaution :
	// `_180c_get_seo_description()` lit l'objet interrogé et mémoïse, elle n'est
	// donc juste que si $post_id EST la page courante.
	$description = '';
	if ( function_exists( '_180c_get_seo_description' )
		&& is_singular()
		&& (int) get_queried_object_id() === $post_id
	) {
		$description = (string) _180c_get_seo_description();
	} elseif ( function_exists( '_180c_description_clean' ) ) {
		$description = _180c_description_clean( (string) $post->post_excerpt );
		if ( function_exists( '_180c_description_trim' ) ) {
			$description = _180c_description_trim( $description );
		}
	}

	if ( '' !== $description ) {
		$node['description'] = $description;
	}

	$thumbnails = _180c_video_thumbnail_urls( $post_id, $video_id );
	if ( $thumbnails ) {
		$node['thumbnailUrl'] = $thumbnails;
	}

	/**
	 * Filtre le nœud VideoObject avant injection dans le @graph.
	 *
	 * @param array $node    Nœud VideoObject.
	 * @param int   $post_id ID du contenu.
	 */
	return (array) apply_filters( '180c/schema/video_node', $node, $post_id );
}

/**
 * Vignettes du VideoObject, sans aucun appel réseau.
 *
 * Ordre : image à la une (`full` puis `large`, chemins encodés RFC 3986 comme
 * partout ailleurs dans le @graph — 13 % des fichiers portent un `©`, un `°` ou
 * une apostrophe typographique), puis repli sur la vignette YouTube.
 *
 * Le repli utilise `hqdefault` (480×360) et non `maxresdefault` : cette
 * dernière n'est générée que pour une partie des vidéos et renvoie un 404 pour
 * les autres, ce qu'on ne peut constater qu'en émettant une requête HTTP —
 * exclu au rendu. La façade front peut se le permettre (elle a un `onerror`
 * côté navigateur), le JSON-LD non.
 *
 * @param int    $post_id  ID du contenu.
 * @param string $video_id Identifiant YouTube.
 * @return string[] Liste d'URL, éventuellement vide.
 */
function _180c_video_thumbnail_urls( $post_id, $video_id ) {
	$urls     = array();
	$thumb_id = (int) get_post_thumbnail_id( $post_id );

	if ( $thumb_id ) {
		foreach ( array( 'full', 'large' ) as $size ) {
			$url = wp_get_attachment_image_url( $thumb_id, $size );
			if ( ! $url ) {
				continue;
			}
			$urls[] = function_exists( '_180c_encode_url_path' ) ? _180c_encode_url_path( $url ) : $url;
		}
		$urls = array_values( array_unique( array_filter( $urls ) ) );
	}

	if ( ! $urls && '' !== $video_id ) {
		$urls[] = 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg';
	}

	return $urls;
}

/**
 * Injecte le VideoObject dans le @graph de la page.
 *
 * DÉCISION « prérequis souple » (ITEM 15) — un seul objet vidéo par page :
 *
 *  - si le @graph contient un nœud `Recipe`, la vidéo alimente sa propriété
 *    `video` ; aucun nœud autonome n'est ajouté ;
 *  - si ce `Recipe` porte déjà une vidéo (champ ACF `recipe_video_url`, cf.
 *    `inc/recipe-schema.php`), on ne touche à rien ;
 *  - sinon (cas de tout le corpus actuel) le VideoObject est un nœud autonome
 *    du @graph, aux côtés du nœud Article.
 *
 * État constaté le 2026-07-31 : la branche `Recipe` ne se déclenche sur aucun
 * contenu réel. Les 33 contenus vidéo du site sont des `post` (nœud Article),
 * y compris les recettes filmées de la rubrique « Vidéos LCD » et
 * `/la-gazette/videos/cocotte-joues-de-boeuf/`. Et aucune des 1 553 recettes du
 * CPT `recipe` ne contient d'embed vidéo. La branche est écrite pour rester
 * juste le jour où une recette est filmée, pas pour un besoin actuel.
 *
 * @param array $result Structure { '@context', '@graph' }.
 * @return array
 */
function _180c_schema_graph_add_video( $result ) {
	if ( ! is_singular() || empty( $result['@graph'] ) || ! is_array( $result['@graph'] ) ) {
		return $result;
	}

	$post_id = (int) get_queried_object_id();
	if ( ! $post_id || ! _180c_post_is_video_content( $post_id ) ) {
		return $result;
	}

	$node = _180c_build_video_schema_node( $post_id );
	if ( empty( $node ) ) {
		return $result;
	}

	foreach ( $result['@graph'] as $index => $graph_node ) {
		if ( ! is_array( $graph_node ) || ! isset( $graph_node['@type'] ) || 'Recipe' !== $graph_node['@type'] ) {
			continue;
		}
		if ( empty( $graph_node['video'] ) ) {
			$result['@graph'][ $index ]['video'] = $node;
		}
		// Un Recipe est présent : jamais de nœud vidéo concurrent.
		return $result;
	}

	$result['@graph'][] = $node;

	return $result;
}
add_filter( '180c/schema_graph', '_180c_schema_graph_add_video', 10 );
