<?php
/**
 * Schema.org JSON-LD.
 *
 * Génère un @graph unique par page contenant : Organization, WebSite,
 * Article | Recipe | Product (selon contexte), BreadcrumbList.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/*
 * Output principal — wp_head priorité 5
 */

add_action( 'wp_head', '_180c_render_jsonld', 5 );

/*
 * Anti-duplication WooCommerce
 */

add_action( 'wp_head', '_180c_dequeue_wc_structured_data', 1 );

/**
 * Retire la sortie JSON-LD native de WooCommerce.
 *
 * WooCommerce émet ses propres nœuds Product / WebSite / BreadcrumbList via
 * WC_Structured_Data::output_structured_data() (wp_footer, priorité 10). Ces
 * entités sont déjà couvertes par le @graph unique de ce module
 * (_180c_render_jsonld, wp_head priorité 5). On retire la sortie WooCommerce
 * pour préserver l'invariant « un seul bloc JSON-LD par page » et éviter un
 * nœud Product dupliqué sur la fiche produit.
 *
 * @return void
 */
function _180c_dequeue_wc_structured_data() {
	if ( ! function_exists( 'WC' ) ) {
		return;
	}

	$wc = WC();
	if ( $wc && isset( $wc->structured_data ) && $wc->structured_data instanceof WC_Structured_Data ) {
		remove_action( 'wp_footer', array( $wc->structured_data, 'output_structured_data' ), 10 );
	}
}

/**
 * Rend le bloc <script type="application/ld+json"> du @graph de la page.
 *
 * Source unique de JSON-LD pour la page : un seul @graph contenant
 * Organization, WebSite, le nœud principal (Article/Recipe/Product) et le
 * BreadcrumbList. Évite toute duplication de blocs JSON-LD.
 *
 * @return void
 */
function _180c_render_jsonld() {
	$graph = _180c_build_schema_graph();
	if ( empty( $graph['@graph'] ) ) {
		return;
	}

	// JSON_HEX_TAG et JSON_HEX_AMP : le JSON est écrit tel quel à l'intérieur
	// d'un élément <script>, où un `</script>` présent dans une donnée
	// éditoriale fermerait le bloc et rendrait tout le @graph illisible pour
	// les analyseurs. Les échapper en < / & reste du JSON valide.
	$json = wp_json_encode(
		$graph,
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP
	);

	if ( false === $json ) {
		return;
	}

	echo '<script type="application/ld+json">' . "\n";
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $json;
	echo "\n" . '</script>' . "\n";
}

/*
 * Helper ISO 8601 durée
 */

/**
 * Convertit un nombre de minutes en durée ISO 8601 (PTxHxM).
 *
 * @param int|string $minutes Durée en minutes.
 * @return string Chaîne ISO 8601 (ex: 'PT1H30M'), vide si 0.
 */
function _180c_minutes_to_iso8601_duration( $minutes ) {
	$minutes = (int) $minutes;
	if ( $minutes <= 0 ) {
		return '';
	}
	$hours = intdiv( $minutes, 60 );
	$mins  = $minutes % 60;
	return 'PT' . ( $hours ? $hours . 'H' : '' ) . ( $mins ? $mins . 'M' : '' );
}

/*
 * Helper URL RFC 3986
 */

/**
 * Encode le chemin d'une URL segment par segment (RFC 3986).
 *
 * WordPress sert les médias sous leur nom de fichier d'origine. Les visuels
 * 180°C portent massivement des caractères non-ASCII (`©`, `°`, accents) :
 * `wp_get_attachment_image_url()` renvoie alors une URL brute du type
 * `…/uploads/2026/07/©180°C-Flan-1.jpg`, qui n'est pas un URI valide. Injectée
 * telle quelle dans un JSON-LD, elle est refusée par les analyseurs stricts.
 *
 * Chaque segment est décodé puis ré-encodé, ce qui rend la fonction idempotente
 * (une URL déjà encodée n'est pas ré-encodée en `%25xx`). Le schéma, l'hôte, la
 * query et le fragment sont laissés intacts : seul le chemin est concerné.
 *
 * @param string $url URL absolue ou relative.
 * @return string URL dont le chemin est conforme RFC 3986.
 */
function _180c_encode_url_path( $url ) {
	$url = (string) $url;
	if ( '' === $url ) {
		return '';
	}

	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
		return $url;
	}

	$segments = explode( '/', $parts['path'] );
	foreach ( $segments as $i => $segment ) {
		if ( '' === $segment ) {
			continue;
		}
		$segments[ $i ] = rawurlencode( rawurldecode( $segment ) );
	}

	$rebuilt = '';
	if ( ! empty( $parts['scheme'] ) ) {
		$rebuilt .= $parts['scheme'] . '://';
	}
	if ( ! empty( $parts['user'] ) ) {
		$rebuilt .= $parts['user'];
		if ( ! empty( $parts['pass'] ) ) {
			$rebuilt .= ':' . $parts['pass'];
		}
		$rebuilt .= '@';
	}
	if ( ! empty( $parts['host'] ) ) {
		$rebuilt .= $parts['host'];
	}
	if ( ! empty( $parts['port'] ) ) {
		$rebuilt .= ':' . $parts['port'];
	}

	$rebuilt .= implode( '/', $segments );

	if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
		$rebuilt .= '?' . $parts['query'];
	}
	if ( isset( $parts['fragment'] ) && '' !== $parts['fragment'] ) {
		$rebuilt .= '#' . $parts['fragment'];
	}

	return $rebuilt;
}

/*
 * Graph principal
 */

/**
 * Construit le graphe Schema.org complet pour la page courante.
 *
 * @return array Structure prête pour wp_json_encode.
 */
function _180c_build_schema_graph() {
	$graph = array(
		_180c_schema_organization(),
		_180c_schema_website(),
	);

	// Accueil : WebPage + ItemList du slider de recettes. Traité AVANT
	// le bloc is_singular() : la branche `template-home.php` ci-dessous exclut
	// explicitement la front-page, qui ne tombait donc dans aucun cas et
	// n'émettait que Organization + WebSite.
	if ( is_front_page() ) {
		foreach ( _180c_schema_front_page() as $node ) {
			$graph[] = $node;
		}
	}

	if ( is_singular() ) {
		$post_id = get_the_ID();
		$type    = get_post_type();

		if ( is_page_template( 'template-home.php' ) && ! is_front_page() ) {
			// Même builder, trois pages :
			// - home Boutique → identifiée par l'IDENTITÉ de la page (slug
			// `boutique`, repli ID via filtre `180c/boutique_home_id`) depuis
			// le retrait du layout `hero_subscribe` (itération 2026-06) ;
			// - home Recettes → présence du layout `carnet` ;
			// - sinon « La Gazette » (CollectionPage + Blog).
			$has_layout = function_exists( '_180c_home_modules_has_layout' );

			$boutique_page = get_page_by_path( 'boutique' );
			/**
			 * ID de la page « home Boutique » (template-home.php).
			 *
			 * Permet de surcharger la résolution par slug si la page Boutique
			 * porte un autre slug.
			 *
			 * @param int $boutique_id ID résolu depuis le slug `boutique` (0 si absent).
			 */
			$boutique_id = (int) apply_filters( '180c/boutique_home_id', $boutique_page instanceof WP_Post ? (int) $boutique_page->ID : 0 );

			if ( $boutique_id && (int) $post_id === $boutique_id ) {
				$home_nodes = _180c_schema_shop_home( $post_id );
			} elseif ( $has_layout && _180c_home_modules_has_layout( $post_id, 'carnet' ) ) {
				$home_nodes = _180c_schema_recipes_home( $post_id );
			} else {
				$home_nodes = _180c_schema_gazette( $post_id );
			}
			foreach ( $home_nodes as $node ) {
				$graph[] = $node;
			}
		} elseif ( 'recipe' === $type ) {
			$current = _180c_schema_recipe( $post_id );
		} elseif ( 'post' === $type ) {
			$current = _180c_schema_article( $post_id );
		} elseif ( 'product' === $type ) {
			$current = _180c_schema_product( $post_id );
		} else {
			$current = null;
		}

		if ( ! empty( $current ) ) {
			$graph[] = $current;
		}

		$breadcrumbs = _180c_schema_breadcrumbs();
		if ( $breadcrumbs ) {
			$graph[] = $breadcrumbs;
		}
	} elseif ( is_author() ) {
		// Page auteur — ProfilePage + Person + breadcrumb.
		if ( function_exists( '_180c_build_author_schema_node' ) ) {
			$profile = _180c_build_author_schema_node( (int) get_queried_object_id() );
			if ( $profile ) {
				$graph[] = $profile;
			}
		}
		$breadcrumbs = _180c_schema_breadcrumbs();
		if ( $breadcrumbs ) {
			$graph[] = $breadcrumbs;
		}
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		// Archive boutique WooCommerce (/tous-les-produits/) : CollectionPage +
		// ItemList produits + BreadcrumbList, aligné sur les archives de terme
		// product_cat (CH-11 / S5). WC n'émet plus son propre @graph (dequeue).
		foreach ( _180c_schema_shop_archive() as $node ) {
			$graph[] = $node;
		}
		$breadcrumbs = _180c_schema_breadcrumbs();
		if ( $breadcrumbs ) {
			$graph[] = $breadcrumbs;
		}
	} elseif ( is_post_type_archive( 'recipe' ) || is_tax() || is_category() || is_tag() ) {
		// Archives de terme (servies par archive.php) : CollectionPage + ItemList.
		// L'archive du CPT recipe garde son propre traitement (Chantier B).
		if ( is_tax() || is_category() || is_tag() ) {
			foreach ( _180c_schema_term_archive() as $node ) {
				$graph[] = $node;
			}
		}
		$breadcrumbs = _180c_schema_breadcrumbs();
		if ( $breadcrumbs ) {
			$graph[] = $breadcrumbs;
		}
	}

	$result = array(
		'@context' => 'https://schema.org',
		'@graph'   => array_values( array_filter( $graph ) ),
	);

	/**
	 * Filtre le @graph Schema.org complet de la page courante.
	 *
	 * Permet d'ajouter un nœud contextuel (ex. ContactPage) tout en
	 * conservant l'invariant « un seul bloc JSON-LD par page ».
	 *
	 * @param array $result Structure { '@context', '@graph' }.
	 */
	return (array) apply_filters( '180c/schema_graph', $result );
}

/*
 * Organization
 */

/**
 * Noeud Organization pour 180°C.
 *
 * Typé `NewsMediaOrganization` : 180°C est un éditeur de presse et non une
 * entreprise quelconque. C'est une sous-classe stricte d'`Organization`, donc
 * aucun consommateur qui attend une `Organization` ne perd quoi que ce soit —
 * mais Google dispose d'un signal d'entité éditoriale qu'il n'avait pas.
 *
 * Ne sont émises que les propriétés dont la valeur est vérifiable dans le site
 * lui-même. `foundingDate` et `issn` restent volontairement absentes : aucune
 * source dans le contenu ne permet de les renseigner, et une donnée d'entité
 * inventée est plus coûteuse à corriger qu'à ne pas émettre.
 *
 * @return array
 */
function _180c_schema_organization() {
	$home = home_url( '/' );

	/**
	 * Filtre les URLs sameAs pour l'Organization.
	 *
	 * @param string[] $urls URLs des profils sociaux.
	 */
	$same_as = apply_filters(
		'180c/seo_organization_same_as',
		array(
			'https://www.facebook.com/180C.LaRevue',
			'https://www.instagram.com/180c_larevue/',
			'https://x.com/180C_LaRevue',
			'https://www.youtube.com/@180clarevueculturefood8',
		)
	);

	$schema = array(
		'@type'         => 'NewsMediaOrganization',
		'@id'           => $home . '#organization',
		'name'          => '180°C',
		'alternateName' => '180°C Culture Food',
		'url'           => $home,
	);

	// Description d'entité — stable sur toutes les pages, contrairement à la
	// meta description qui suit la page courante. On reprend donc l'entrée de
	// l'accueil, la seule qui décrit la maison d'édition et non un contenu.
	if ( function_exists( '_180c_description_map_pages' ) ) {
		$descriptions = _180c_description_map_pages();
		$description  = isset( $descriptions['_front'] ) ? trim( (string) $descriptions['_front'] ) : '';
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}
	}

	$logo = _180c_schema_organization_logo();
	if ( $logo ) {
		$schema['logo'] = $logo;
	}

	// `masthead` : la page qui présente l'équipe éditoriale. Émise seulement si
	// elle existe et est publiée — un lien mort dans le @graph vaut moins que
	// pas de lien du tout.
	$about = get_page_by_path( 'a-propos' );
	if ( $about instanceof WP_Post && 'publish' === $about->post_status ) {
		$schema['masthead'] = get_permalink( $about );
	}

	if ( ! empty( $same_as ) ) {
		$schema['sameAs'] = array_values( $same_as );
	}

	return $schema;
}

/**
 * Construit le nœud ImageObject du logo de l'Organization.
 *
 * Sources, par ordre de priorité :
 * 1. `_180C_ORGANIZATION_LOGO` — logo de marque désigné par la DA ;
 * 2. `custom_logo` (Customizer) — choix explicite de l'administrateur ;
 * 3. `site_icon` — repli sur le logo de marque déjà téléversé.
 *
 * L'étape 1 prime parce que `site_icon` servait jusqu'ici de repli et publiait
 * `cropped-Logo180C-CultureFood-Rond.webp` : un visuel **recadré** pour la
 * favicon, déclaré 512×512. Le logo d'entité déclaré à Google doit être le
 * fichier de marque, pas son dérivé carré de barre d'onglet.
 *
 * Les repli 2 et 3 restent en place : sans eux, le nœud Organization serait émis
 * **sans `logo`** si la constante venait à être vidée (constaté en production le
 * 2026-07-31), privant Google du visuel d'entité qui alimente le knowledge panel.
 * WordPress impose au `site_icon` un carré d'au moins 512 px, au-delà du minimum
 * de 112 px exigé par Google.
 *
 * Le chemin est ré-encodé (RFC 3986) car les médias 180°C portent massivement
 * des caractères non-ASCII (`©`, `°`, accents).
 *
 * @return array|null Nœud ImageObject, ou null si aucun visuel n'est disponible.
 */
function _180c_schema_organization_logo() {
	// 1. Logo de marque désigné. Déclaré par URL et non par ID : le fichier vit
	// en production et n'est pas présent dans les uploads locaux, un ID rendrait
	// donc 0 en développement. Dimensions relevées sur le fichier réellement
	// servi (cf. inc/bootstrap.php), jamais déduites d'un autre visuel.
	if ( defined( '_180C_ORGANIZATION_LOGO' ) && '' !== (string) _180C_ORGANIZATION_LOGO ) {
		$schema = array(
			'@type' => 'ImageObject',
			'url'   => _180c_encode_url_path( (string) _180C_ORGANIZATION_LOGO ),
		);
		if ( defined( '_180C_ORGANIZATION_LOGO_WIDTH' ) ) {
			$schema['width'] = (int) _180C_ORGANIZATION_LOGO_WIDTH;
		}
		if ( defined( '_180C_ORGANIZATION_LOGO_HEIGHT' ) ) {
			$schema['height'] = (int) _180C_ORGANIZATION_LOGO_HEIGHT;
		}

		/** This filter is documented below in _180c_schema_organization_logo(). */
		$filtered = (string) apply_filters( '180c/schema_organization_logo', $schema['url'] ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
		if ( $filtered === $schema['url'] ) {
			return $schema;
		}
		if ( '' === $filtered ) {
			return null;
		}

		// URL substituée par un filtre : les dimensions constantes ne la
		// décrivent plus, on n'en déclare aucune plutôt que d'en mentir.
		return array(
			'@type' => 'ImageObject',
			'url'   => _180c_encode_url_path( $filtered ),
		);
	}

	// 2-3. Replis médiathèque.
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( ! $logo_id ) {
		$logo_id = (int) get_option( 'site_icon' );
	}

	$resolved = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';

	/**
	 * Filtre l'URL du logo de l'Organization (Schema.org).
	 *
	 * @param string $resolved URL résolue depuis custom_logo puis site_icon (vide si aucun).
	 */
	$logo_url = (string) apply_filters( '180c/schema_organization_logo', $resolved ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	if ( '' === $logo_url ) {
		return null;
	}

	$schema = array(
		'@type' => 'ImageObject',
		'url'   => _180c_encode_url_path( $logo_url ),
	);

	// Dimensions émises seulement si l'URL retenue est bien celle de la pièce
	// jointe résolue : un filtre qui substitue un autre visuel rendrait les
	// dimensions de la médiathèque fausses, et mieux vaut n'en déclarer aucune
	// qu'en déclarer de mensongères.
	if ( $logo_id && $logo_url === $resolved ) {
		$meta = wp_get_attachment_metadata( $logo_id );
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['width'] ) ) {
				$schema['width'] = (int) $meta['width'];
			}
			if ( ! empty( $meta['height'] ) ) {
				$schema['height'] = (int) $meta['height'];
			}
		}
	}

	return $schema;
}

/*
 * WebSite
 */

/**
 * Noeud WebSite, porteur du SearchAction sur la page d'accueil.
 *
 * Le nœud WebSite lui-même est émis sur **toutes** les pages : les CollectionPage
 * des archives, des homes de section et de la boutique s'y rattachent via
 * `isPartOf` (`#website`), et le retirer ailleurs laisserait ces références
 * pointer dans le vide.
 *
 * Le `potentialAction` (SearchAction), lui, est restreint à la page d'accueil,
 * conformément à la recommandation de Google de ne déclarer la zone de recherche
 * du site qu'une fois, sur la home. Il était jusqu'ici répété sur chaque page.
 *
 * Cible du SearchAction : `/?s=`, la recherche native du thème — vérifiée en
 * production (HTTP 200, sans redirection).
 *
 * @return array
 */
function _180c_schema_website() {
	$home = home_url( '/' );

	$schema = array(
		'@type'     => 'WebSite',
		'@id'       => $home . '#website',
		'url'       => $home,
		'name'      => '180°C',
		'publisher' => array( '@id' => $home . '#organization' ),
	);

	if ( is_front_page() ) {
		$schema['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => $home . '?s={search_term_string}',
			),
			'query-input' => 'required name=search_term_string',
		);
	}

	return $schema;
}

/*
 * WebPage + ItemList — accueil
 */

/**
 * Retourne les IDs de recettes que le slider de l'accueil va afficher.
 *
 * **Ne passe PAS par `_180c_resolve_recipe_rail_ids()`**, et c'est délibéré :
 * ce résolveur alimente l'accumulateur de dédoublonnage inter-modules
 * (`_180c_displayed_recipe_ids()`). Or le @graph est construit sur `wp_head`,
 * donc AVANT le rendu des modules : l'appeler ici marquerait les recettes comme
 * « déjà affichées » et les modules suivants les écarteraient. Le schema
 * casserait la page qu'il décrit.
 *
 * On rejoue donc la lecture en pur read-only : mêmes paramètres, aucun effet de
 * bord. Le transient du résolveur est lu s'il existe, ce qui garantit une sortie
 * strictement identique à celle du slider ; sinon la même requête est rejouée
 * (6 IDs, `no_found_rows`), sans réécrire le transient.
 *
 * Le `count` vient de la composition ACF réelle et non d'une constante : une
 * liste codée en dur divergerait du contenu affiché à la première modification
 * en back-office.
 *
 * @param int $post_id ID de la page d'accueil.
 * @return int[] IDs de recettes, dans l'ordre d'affichage. Vide si pas de slider.
 */
function _180c_schema_front_page_recipe_ids( $post_id ) {
	$post_id = (int) $post_id;

	if ( ! $post_id || ! function_exists( 'have_rows' ) || ! have_rows( 'home_modules', $post_id ) ) {
		return array();
	}

	// Premier layout `recipes_slider` de la composition. En production comme en
	// local il ouvre la page : aucun module consommateur de recettes ne le
	// précède, donc son `exclude_displayed` éventuel n'a rien à exclure et la
	// liste ci-dessous correspond exactement à ce qui est rendu.
	$count = 0;
	$found = false;
	while ( have_rows( 'home_modules', $post_id ) ) {
		the_row();
		if ( 'recipes_slider' === get_row_layout() ) {
			$count = (int) get_sub_field( 'count' );
			$found = true;
			break;
		}
	}
	reset_rows();

	if ( ! $found ) {
		return array();
	}

	// Mêmes valeurs par défaut et même plafond que parts/modules/recipes_slider.php.
	if ( $count < 1 ) {
		$count = 6;
	}
	if ( defined( '_180C_RECIPES_SLIDER_MAX' ) ) {
		$count = min( $count, (int) _180C_RECIPES_SLIDER_MAX );
	}

	// Clé du cache court posé par _180c_resolve_recipe_rail_ids() en mode
	// « recent » sans dédoublonnage — le cas du slider.
	$cached = get_transient( '_180c_rail_recipe_ids_recent_' . $count );
	if ( is_array( $cached ) && ! empty( $cached ) ) {
		return array_map( 'intval', $cached );
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'recipe',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
		)
	);

	return array_map( 'intval', $query->posts );
}

/**
 * Noeud Schema.org de la page d'accueil.
 *
 * L'accueil n'émettait jusqu'ici que `NewsMediaOrganization` + `WebSite` :
 * aucun nœud ne décrivait la page elle-même ni son contenu. La branche
 * `template-home.php` du graphe est explicitement conditionnée à
 * `! is_front_page()`, et la front-page ne tombait donc dans aucun cas.
 *
 * Émet une `WebPage` rattachée au `WebSite`, dont l'entité principale est un
 * `ItemList` de `ListItem` (position + url + name) pointant vers les recettes
 * du slider.
 *
 * `WebPage` et non `CollectionPage` : ce dernier type décrit une page dont
 * l'objet EST une collection — une archive, un index, une liste de résultats.
 * L'accueil de 180°C est une page éditoriale composite (hero, rails, mosaïque,
 * newsletter, boutique) dont le slider de recettes n'est qu'un module parmi
 * d'autres. La typer en `CollectionPage` annonçait à Google un inventaire qu'elle
 * n'est pas. Les archives, elles, restent bien en `CollectionPage` (cf. plus
 * haut dans ce fichier).
 *
 * Le passage est sans effet sur le reste du nœud : `mainEntity` (l'`ItemList`),
 * `isPartOf` et `description` sont inchangés, et `CollectionPage` étant une
 * sous-classe de `WebPage`, toutes les propriétés portées restent valides.
 *
 * **Aucun objet `Recipe` n'est inliné** : ils existent déjà, complets, sur les
 * pages recette — et ce schema-là est performant (position moyenne 1,26 sur
 * « Galerie de recettes »). Le dupliquer partiellement ici ne pourrait que le
 * dégrader. L'ItemList se contente de pointer.
 *
 * @return array Liste de noeuds, ou tableau vide.
 */
function _180c_schema_front_page() {
	$home    = home_url( '/' );
	$post_id = (int) get_queried_object_id();

	$node = array(
		'@type'    => 'WebPage',
		'@id'      => $home . '#webpage',
		'url'      => $home,
		'name'     => wp_get_document_title(),
		'isPartOf' => array( '@id' => $home . '#website' ),
	);

	$description = function_exists( '_180c_get_seo_description' ) ? (string) _180c_get_seo_description() : '';
	if ( '' !== $description ) {
		$node['description'] = $description;
	}

	$elements = array();
	$position = 1;
	foreach ( _180c_schema_front_page_recipe_ids( $post_id ) as $recipe_id ) {
		$url = get_permalink( $recipe_id );
		if ( ! $url ) {
			continue;
		}
		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'url'      => _180c_encode_url_path( $url ),
			'name'     => get_the_title( $recipe_id ),
		);
	}

	if ( ! empty( $elements ) ) {
		$node['mainEntity'] = array(
			'@type'           => 'ItemList',
			'@id'             => $home . '#recipes',
			'name'            => __( 'Les dernières recettes de Delphine', '180c' ),
			'itemListOrder'   => 'https://schema.org/ItemListOrderDescending',
			'numberOfItems'   => count( $elements ),
			'itemListElement' => $elements,
		);
	}

	return array( $node );
}

/*
 * CollectionPage + Blog — page « La Gazette »
 */

/**
 * Noeuds Schema.org pour la page éditoriale « La Gazette » (template-home.php).
 *
 * Émet une CollectionPage (la page elle-même, rattachée au WebSite) et un
 * noeud Blog décrivant le flux éditorial. Le WebSite + SearchAction restent
 * portés par la front_page.
 *
 * @param int $post_id ID de la page Gazette.
 * @return array Liste de noeuds (CollectionPage, Blog), ou tableau vide.
 */
function _180c_schema_gazette( $post_id ) {
	$home = home_url( '/' );
	$url  = get_permalink( $post_id );

	if ( ! $url ) {
		return array();
	}

	$name = get_the_title( $post_id );

	$collection = array(
		'@type'    => 'CollectionPage',
		'@id'      => $url . '#webpage',
		'url'      => $url,
		'name'     => $name,
		'isPartOf' => array( '@id' => $home . '#website' ),
		'about'    => array( '@id' => $url . '#blog' ),
	);

	$blog = array(
		'@type' => 'Blog',
		'@id'   => $url . '#blog',
		'url'   => $url,
		'name'  => $name,
	);

	return array( $collection, $blog );
}

/**
 * Noeuds Schema.org pour la home « Recettes » (template-home.php + module carnet).
 *
 * Émet une CollectionPage rattachée au WebSite, dont l'entité principale est un
 * ItemList des dernières recettes publiées (titres + URLs publics uniquement,
 * aucune donnée personnalisée ni premium). Le @graph hérite par ailleurs du
 * BreadcrumbList et des nœuds Organization/WebSite.
 *
 * @param int $post_id ID de la page Recettes.
 * @return array Liste de noeuds (CollectionPage), ou tableau vide.
 */
function _180c_schema_recipes_home( $post_id ) {
	$home = home_url( '/' );
	$url  = get_permalink( $post_id );

	if ( ! $url ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'recipe',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);

	$elements = array();
	$position = 1;
	foreach ( $query->posts as $recipe ) {
		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'url'      => get_permalink( $recipe ),
			'name'     => get_the_title( $recipe ),
		);
	}
	wp_reset_postdata();

	$collection = array(
		'@type'    => 'CollectionPage',
		'@id'      => $url . '#webpage',
		'url'      => $url,
		'name'     => get_the_title( $post_id ),
		'isPartOf' => array( '@id' => $home . '#website' ),
	);

	if ( ! empty( $elements ) ) {
		$collection['mainEntity'] = array(
			'@type'           => 'ItemList',
			'itemListElement' => $elements,
		);
	}

	return array( $collection );
}

/**
 * Noeuds Schema.org pour la home « Boutique » (page slug `boutique`, template-home.php).
 *
 * Émet une CollectionPage rattachée au WebSite, dont l'entité principale est un
 * ItemList des derniers produits publiés (titres + URLs publics uniquement,
 * aucun prix/stock/note — 180°C n'affiche ni avis ni AggregateRating). Le @graph
 * hérite par ailleurs du BreadcrumbList et des nœuds Organization/WebSite.
 *
 * @param int $post_id ID de la page Boutique.
 * @return array Liste de noeuds (CollectionPage), ou tableau vide.
 */
function _180c_schema_shop_home( $post_id ) {
	$home = home_url( '/' );
	$url  = get_permalink( $post_id );

	if ( ! $url ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);

	$elements = array();
	$position = 1;
	foreach ( $query->posts as $product ) {
		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'url'      => get_permalink( $product ),
			'name'     => get_the_title( $product ),
		);
	}
	wp_reset_postdata();

	$collection = array(
		'@type'    => 'CollectionPage',
		'@id'      => $url . '#webpage',
		'url'      => $url,
		'name'     => get_the_title( $post_id ),
		'isPartOf' => array( '@id' => $home . '#website' ),
	);

	if ( ! empty( $elements ) ) {
		$collection['mainEntity'] = array(
			'@type'           => 'ItemList',
			'itemListElement' => $elements,
		);
	}

	return array( $collection );
}

/**
 * CollectionPage + ItemList pour une archive de terme.
 *
 * Couvre les surfaces servies par archive.php : category, post_tag,
 * recipe_category/season/type/tag, product_brand (et toute taxonomie publique).
 * L'ItemList reprend les éléments réellement listés sur la page courante (max
 * 10), sans requête supplémentaire (réutilise la requête principale déjà
 * exécutée). Les archives de date sont volontairement exclues (faible valeur).
 *
 * @return array Liste de noeuds (CollectionPage), ou tableau vide.
 */
function _180c_schema_term_archive() {
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return array();
	}

	$url = get_term_link( $term );
	if ( is_wp_error( $url ) || ! $url ) {
		return array();
	}

	$home = home_url( '/' );

	$elements = array();
	$position = 1;
	foreach ( array_slice( (array) $GLOBALS['wp_query']->posts, 0, 10 ) as $listed ) {
		$permalink = get_permalink( $listed );
		if ( ! $permalink ) {
			continue;
		}
		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'url'      => $permalink,
			'name'     => get_the_title( $listed ),
		);
	}

	$collection = array(
		'@type'    => 'CollectionPage',
		'@id'      => $url . '#webpage',
		'url'      => $url,
		'name'     => single_term_title( '', false ),
		'isPartOf' => array( '@id' => $home . '#website' ),
	);

	$description = wp_strip_all_tags( term_description( $term ) );
	if ( '' !== trim( $description ) ) {
		$collection['description'] = $description;
	}

	if ( ! empty( $elements ) ) {
		$collection['mainEntity'] = array(
			'@type'           => 'ItemList',
			'itemListElement' => $elements,
		);
	}

	return array( $collection );
}

/**
 * Construit le nœud CollectionPage de l'archive boutique WooCommerce.
 *
 * Pendant de _180c_schema_term_archive() pour la page boutique
 * (/tous-les-produits/), qui n'est pas une archive de terme mais une
 * post_type_archive : CollectionPage + ItemList des produits listés (10 max),
 * rattaché au WebSite. Aligne le shop sur les catégories produit (S5).
 *
 * @return array Liste de nœuds (CollectionPage) ou tableau vide.
 */
function _180c_schema_shop_archive() {
	// _180c_shop_url() ignore l'option WooCommerce tant qu'elle pointe sur un
	// brouillon : on n'émet jamais une URL ?page_id=… dans le @graph.
	$url = _180c_shop_url();
	if ( '' === $url ) {
		$url = (string) get_post_type_archive_link( 'product' );
	}
	if ( '' === $url ) {
		return array();
	}

	$home = home_url( '/' );

	$elements = array();
	$position = 1;
	foreach ( array_slice( (array) $GLOBALS['wp_query']->posts, 0, 10 ) as $listed ) {
		$permalink = get_permalink( $listed );
		if ( ! $permalink ) {
			continue;
		}
		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'url'      => $permalink,
			'name'     => get_the_title( $listed ),
		);
	}

	$name = function_exists( 'woocommerce_page_title' )
		? wp_strip_all_tags( woocommerce_page_title( false ) )
		: __( 'Boutique', '180c' );

	$collection = array(
		'@type'    => 'CollectionPage',
		'@id'      => $url . '#webpage',
		'url'      => $url,
		'name'     => $name,
		'isPartOf' => array( '@id' => $home . '#website' ),
	);

	$description = (string) _180c_get_seo_description();
	if ( '' !== trim( $description ) ) {
		$collection['description'] = $description;
	}

	if ( ! empty( $elements ) ) {
		$collection['mainEntity'] = array(
			'@type'           => 'ItemList',
			'itemListElement' => $elements,
		);
	}

	return array( $collection );
}

/*
 * BreadcrumbList
 */

/**
 * Génère dynamiquement un BreadcrumbList pour la page courante.
 *
 * Dérivé de la source unique du fil d'Ariane (_180c_get_breadcrumb_items,
 * inc/helpers.php) : le JSON-LD reflète exactement le fil visible rendu
 * au-dessus du footer, garantissant l'absence de divergence entre
 * affichage et données structurées. Respecte le même gate de visibilité que le
 * rendu visuel (_180c_should_show_breadcrumb) — pas de breadcrumb structuré là
 * où aucun n'est affiché (front page, homes de section).
 *
 * Le dernier item (page courante, `url` vide côté source) est émis sans `item`,
 * usage recommandé pour le nœud terminal d'un BreadcrumbList.
 *
 * @return array|null Noeud BreadcrumbList ou null si non pertinent.
 */
function _180c_schema_breadcrumbs() {
	if ( ! function_exists( '_180c_get_breadcrumb_items' )
		|| ! function_exists( '_180c_should_show_breadcrumb' )
		|| ! _180c_should_show_breadcrumb()
	) {
		return null;
	}

	$items = _180c_get_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return null;
	}

	$elements = array();
	$position = 1;
	foreach ( $items as $item ) {
		if ( empty( $item['label'] ) ) {
			continue;
		}

		$entry = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => wp_strip_all_tags( $item['label'] ),
		);

		if ( ! empty( $item['url'] ) ) {
			$entry['item'] = esc_url_raw( $item['url'] );
		}

		$elements[] = $entry;
	}

	if ( count( $elements ) < 2 ) {
		return null;
	}

	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $elements,
	);
}

/*
 * Article
 */

/**
 * Noeud Article pour un post standard.
 *
 * Délègue à _180c_build_article_schema_node() (inc/article-schema.php),
 * source de vérité du nœud Article (en parité avec
 * `_180c_build_recipe_schema_node`). Conservé ici comme point d'entrée
 * du @graph global (cf. _180c_build_schema_graph()).
 *
 * @param int $post_id ID du post.
 * @return array|null
 */
function _180c_schema_article( $post_id ) {
	if ( ! function_exists( '_180c_build_article_schema_node' ) ) {
		return null;
	}
	return _180c_build_article_schema_node( $post_id );
}

/*
 * Recipe
 */

/**
 * Noeud Recipe pour une recette (CPT recipe).
 *
 * Délègue à _180c_build_recipe_schema_node() (inc/recipe-schema.php), source de
 * vérité du nœud Recipe alignée sur le modèle ACF. Conservé ici comme
 * point d'entrée du @graph global (cf. _180c_build_schema_graph()).
 *
 * @param int $post_id ID de la recette.
 * @return array|null
 */
function _180c_schema_recipe( $post_id ) {
	if ( ! function_exists( '_180c_build_recipe_schema_node' ) ) {
		return null;
	}
	return _180c_build_recipe_schema_node( $post_id );
}

/*
 * Product
 */

/**
 * Noeud Product pour un produit WooCommerce.
 *
 * @param int $post_id ID du produit.
 * @return array|null
 */
function _180c_schema_product( $post_id ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return null;
	}

	$product = wc_get_product( $post_id );
	if ( ! $product ) {
		return null;
	}

	$home      = home_url( '/' );
	$permalink = get_permalink( $post_id );

	$schema = array(
		'@type' => 'Product',
		'@id'   => $permalink . '#product',
		'name'  => $product->get_name(),
		'brand' => array( '@id' => $home . '#organization' ),
		'url'   => $permalink,
	);

	// Description : short_description → description. L'override ACF
	// `seo_description` a été retiré avec le groupe ACF « SEO » ; c'est le même
	// ordre que celui de `_180c_get_seo_description()` pour les produits, si
	// bien que le JSON-LD et la meta description ne peuvent pas diverger.
	$description = _180c_description_clean( (string) $product->get_short_description() );
	if ( '' === $description ) {
		$description = _180c_description_clean( (string) $product->get_description() );
	}
	$description = _180c_description_trim( $description );
	if ( $description ) {
		$schema['description'] = $description;
	}

	// SKU.
	$sku = $product->get_sku();
	if ( $sku ) {
		$schema['sku'] = $sku;
	}

	// Images.
	$images = _180c_schema_image_objects( $post_id );
	if ( $images ) {
		$schema['image'] = $images;
	}

	// GTIN-13 — ISBN-13 depuis le champ natif WooCommerce `_global_unique_id`
	// (source unique, WC 9.2+). Tous les ISBN du catalogue sont des EAN/ISBN
	// à 13 chiffres → `gtin13`. Omis si vide (ex. coffret, abonnement).
	$gtin_digits = preg_replace( '/\D/', '', (string) $product->get_global_unique_id() );
	if ( 13 === strlen( (string) $gtin_digits ) ) {
		$schema['gtin13'] = $gtin_digits;
	} elseif ( '' !== (string) $gtin_digits ) {
		$schema['gtin'] = $gtin_digits; // Repli générique si longueur non standard.
	}

	// Catégorie produit principale (premier terme `product_cat` assigné).
	$cat_ids = $product->get_category_ids();
	if ( ! empty( $cat_ids ) ) {
		$term = get_term( (int) $cat_ids[0], 'product_cat' );
		if ( $term instanceof WP_Term ) {
			$schema['category'] = $term->name;
		}
	}

	// Poids natif WC → QuantitativeValue (unité boutique mappée UN/CEFACT).
	$weight = $product->get_weight();
	if ( '' !== $weight && (float) $weight > 0 ) {
		$weight_codes     = array(
			'g'   => 'GRM',
			'kg'  => 'KGM',
			'lbs' => 'LBR',
			'oz'  => 'ONZ',
		);
		$weight_unit      = (string) get_option( 'woocommerce_weight_unit' );
		$schema['weight'] = array(
			'@type'    => 'QuantitativeValue',
			'value'    => (float) $weight,
			'unitCode' => $weight_codes[ $weight_unit ] ?? 'GRM',
		);
	}

	// Dimensions natives WC → QuantitativeValue en cm (CMT). La `length` WC
	// correspond à la profondeur (`depth`) Schema.org.
	$dimension_unit = (string) get_option( 'woocommerce_dimension_unit' );
	$dimension_map  = array(
		'height' => 'get_height',
		'width'  => 'get_width',
		'depth'  => 'get_length',
	);
	foreach ( $dimension_map as $prop => $getter ) {
		$raw = $product->$getter();
		if ( '' !== $raw && null !== $raw && (float) $raw > 0 ) {
			$schema[ $prop ] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => (float) wc_get_dimension( (float) $raw, 'cm', $dimension_unit ),
				'unitCode' => 'CMT',
			);
		}
	}

	// Nombre de pages → additionalProperty (attribut local `pagination`, texte
	// libre ex. « 80 pages sans publicité » → entier extrait si présent).
	$pagination = trim( (string) $product->get_attribute( 'pagination' ) );
	if ( '' !== $pagination ) {
		$pages_value                  = preg_match( '/\d+/', $pagination, $m ) ? (int) $m[0] : $pagination;
		$schema['additionalProperty'] = array(
			array(
				'@type' => 'PropertyValue',
				'name'  => __( 'Nombre de pages', '180c' ),
				'value' => $pages_value,
			),
		);
	}

	// Langue du contenu.
	$schema['inLanguage'] = 'fr';

	// Offer — émis uniquement si un prix réel existe. Les produits « externes »
	// sans prix (ex. « disponible uniquement en librairie ») n'ont pas d'offre
	// en ligne : ne rien fabriquer.
	//
	// `url` reste TOUJOURS le permalien, y compris pour un produit externe. Le
	// nœud décrit une offre dont le `seller` est 180°C : y mettre l'URL du
	// marchand (`_product_url`, qui pointe sur Place des Libraires) associerait
	// une page tierce à un vendeur qui n'est pas le sien, et enverrait les
	// moteurs hors du site depuis nos propres données structurées.
	$price = $product->get_price();
	if ( '' !== $price ) {
		$offer_url = $permalink;

		$schema['offers'] = array(
			'@type'           => 'Offer',
			'price'           => $price,
			'priceCurrency'   => get_woocommerce_currency(),
			'availability'    => $product->is_in_stock()
				? 'https://schema.org/InStock'
				: 'https://schema.org/OutOfStock',
			'itemCondition'   => 'https://schema.org/NewCondition',
			'url'             => $offer_url,
			'priceValidUntil' => gmdate( 'Y' ) . '-12-31',
			'seller'          => array( '@id' => $home . '#organization' ),
		);
	}

	return $schema;
}

/*
 * Helpers internes
 */

/**
 * Retourne un tableau d'URLs d'images pour un post (featured image).
 *
 * @param int $post_id ID du post.
 * @return string[] Tableau d'URLs (peut être vide).
 */
function _180c_schema_image_objects( $post_id ) {
	$images = array();

	$thumb_id = get_post_thumbnail_id( $post_id );
	if ( $thumb_id ) {
		$url = wp_get_attachment_image_url( (int) $thumb_id, 'large' );
		if ( $url ) {
			$images[] = $url;
		}
	}

	return $images;
}
