<?php
/**
 * Meta tags SEO natifs.
 *
 * Gère : title (+ pattern filtrable), meta description, robots, canonical,
 * Open Graph, Twitter Cards, Smart Banner iOS. Aucun plugin (ni Yoast,
 * ni Rank Math).
 *
 * Sortie injectée sur wp_head priorité 1 (avant tout autre) via les helpers
 * _180c_render_meta(), _180c_render_canonical(), _180c_render_og(),
 * _180c_render_twitter().
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/*
 * 1. Title tag
 */

/**
 * Séparateur du titre : "·" (interpunct).
 *
 * @return string
 */
add_filter(
	'document_title_separator',
	function () {
		return '·';
	}
);

/**
 * Ajuste les parties du titre selon le contexte (mécanique WP native).
 *
 * @param array $parts Parties du titre (title, tagline, site).
 * @return array
 */
add_filter( 'document_title_parts', '_180c_seo_document_title_parts' );

/**
 * Construit les parties du titre selon le contexte courant.
 *
 * @param array $parts Parties du titre (title, tagline, site).
 * @return array
 */
function _180c_seo_document_title_parts( $parts ) {
	// Ce filtre n'est plus qu'un filet : l'override ACF (overrides.php,
	// priorité 4) puis le gabarit (title-resolver.php, priorité 6) tranchent
	// avant lui. Il ne sert que les contextes qu'aucun des deux ne couvre —
	// recherche, 404, archives de date.
	if ( is_post_type_archive( 'recipe' ) ) {
		$parts['title'] = __( 'Recettes', '180c' );
	} elseif ( is_author() && function_exists( '_180c_author_display_name' ) ) {
		$author_name = _180c_author_display_name( (int) get_queried_object_id() );
		if ( '' !== $author_name ) {
			$parts['title'] = $author_name;
		}
	} elseif ( is_search() ) {
		/* translators: %s: terme de recherche */
		$parts['title'] = sprintf( __( 'Recherche : %s', '180c' ), get_search_query() );
	} elseif ( is_404() ) {
		$parts['title'] = __( 'Page introuvable', '180c' );
	}

	// Normalise le site name indépendamment du blog name en BDD.
	$parts['site'] = '180°C';

	// Supprime la tagline sur les singuliers (évite "titre · tagline · 180°C").
	if ( is_singular() && isset( $parts['tagline'] ) ) {
		unset( $parts['tagline'] );
	}

	// Home : WP natif n'ajoute jamais le nom du site (le titre de la home EST
	// déjà le nom du site, ou un seo_title auto-porteur qui le contient).
	// Sans ça on obtient le doublon « 180°C … · 180°C ».
	if ( is_front_page() ) {
		unset( $parts['site'] );
	}

	return $parts;
}

/**
 * Point d'extension du pattern de titre.
 *
 * Par défaut le titre est assemblé par WP : « [Titre] · 180°C ». Pour le
 * personnaliser, filtrez `180c/seo_title_pattern` (ou son alias spec
 * `_180c_seo_title_pattern`) en renvoyant un pattern sprintf à 2 arguments :
 * %1$s = titre de la page, %2$s = nom du site. Renvoyer une chaîne vide
 * (défaut) laisse WP construire le titre nativement.
 *
 * @param string $title Titre court-circuité (vide = laisser WP construire).
 * @return string
 */
add_filter( 'pre_get_document_title', '_180c_seo_title_pattern_override', 5 );

/**
 * Applique un pattern de titre custom si un filtre en fournit un.
 *
 * @param string $title Titre déjà résolu (vide par défaut à ce stade).
 * @return string Titre final, ou chaîne vide pour laisser WP construire.
 */
function _180c_seo_title_pattern_override( $title ) {
	if ( '' !== $title ) {
		return $title;
	}

	/**
	 * Filtre le pattern d'assemblage du titre (sprintf à 2 args).
	 *
	 * @param string $pattern Pattern sprintf, ex. '%1$s · %2$s'. Vide = natif.
	 */
	$pattern = (string) apply_filters( '180c/seo_title_pattern', '' );
	// Alias littéral conforme au cahier des charges.
	$pattern = (string) apply_filters( '_180c_seo_title_pattern', $pattern );

	if ( '' === $pattern ) {
		return $title;
	}

	return sprintf( $pattern, _180c_seo_bare_title(), '180°C' );
}

/**
 * Retourne le titre « nu » (sans site) de la page courante.
 *
 * Utilisé uniquement par le chemin pattern custom (cf.
 * _180c_seo_title_pattern_override()). Couvre les contextes principaux.
 *
 * @return string
 */
function _180c_seo_bare_title() {
	if ( is_singular() ) {
		// Pas d'override ACF à consulter ici : s'il y en avait un, la priorité 4
		// aurait déjà rendu le title et ce chemin ne serait pas atteint.
		return (string) get_the_title();
	}
	if ( is_post_type_archive( 'recipe' ) ) {
		return __( 'Recettes', '180c' );
	}
	if ( is_author() && function_exists( '_180c_author_display_name' ) ) {
		$author_name = _180c_author_display_name( (int) get_queried_object_id() );
		if ( '' !== $author_name ) {
			return $author_name;
		}
	}
	if ( is_search() ) {
		/* translators: %s: terme de recherche */
		return sprintf( __( 'Recherche : %s', '180c' ), get_search_query() );
	}
	if ( is_404() ) {
		return __( 'Page introuvable', '180c' );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			return $term->name;
		}
	}
	return get_bloginfo( 'name' );
}

/*
 * 2. Helpers de contexte (description, image OG, canonical)
 */

/**
 * Retourne la meta description selon le contexte courant.
 *
 * L'override ACF `seo_description` (`inc/seo/overrides.php`) prime sur toute
 * la chaîne quand il est saisi, et il est servi **sans troncature** : une
 * description écrite à la main n'a pas à être coupée à 155 caractères par le
 * code, sous peine de trahir silencieusement l'intention rédactionnelle. À
 * défaut de saisie, la description est dérivée du contenu réel, ou lue dans
 * `_180c_description_map_pages()` pour les pages statiques.
 *
 * **Jamais vide.** Chaque branche se termine par un repli, et le dernier repli
 * est `_180c_description_default()`. L'ancienne chaîne retombait sur
 * `get_bloginfo( 'description' )`, **vide en base** : recettes, articles et
 * taxonomies sortaient donc sans balise description du tout (constaté en local
 * sur l'intégralité des recettes — leur `post_content` est vide, tout le
 * contenu vit dans les champs ACF, si bien qu'aucun repli ne produisait rien).
 *
 * Mémoïsé pour la requête.
 *
 * @return string Description nettoyée, prête pour esc_attr().
 */
function _180c_get_seo_description() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	// Override rédactionnel : court-circuite toute la chaîne de replis.
	$description = _180c_seo_override( 'seo_description' );
	$is_override = ( '' !== $description );

	if ( $is_override ) {
		// Une saisie de la rédaction fait foi : aucun repli n'est consulté.
		$description = _180c_description_clean( $description );
	} elseif ( is_front_page() ) {
		$map         = _180c_description_map_pages();
		$description = isset( $map['_front'] ) ? (string) $map['_front'] : '';
	} elseif ( is_singular() ) {
		$post_id = (int) get_queried_object_id();

		// 1. Page statique mappée — la table prime, comme pour le title.
		if ( is_page() ) {
			$slug = (string) get_post_field( 'post_name', $post_id );
			$map  = _180c_description_map_pages();
			if ( isset( $map[ $slug ] ) ) {
				$description = (string) $map[ $slug ];
			}
		}

		// 2. Produit : description courte WooCommerce, puis description longue.
		if ( '' === $description && is_singular( 'product' ) ) {
			$product = get_post( $post_id );
			if ( $product instanceof WP_Post ) {
				$description = _180c_description_clean( (string) $product->post_excerpt );
				if ( '' === $description ) {
					$description = _180c_description_clean( (string) $product->post_content );
				}
			}
		}

		// 3. Extrait manuel — 92 % des recettes et des articles en ont un.
		if ( '' === $description ) {
			$raw = (string) get_post_field( 'post_excerpt', $post_id );
			if ( '' !== trim( $raw ) ) {
				$description = _180c_description_clean( $raw );
			}
		}

		// 4. Intro de recette (champ ACF `recipe_intro`).
		if ( '' === $description && is_singular( 'recipe' ) ) {
			$description = _180c_description_clean( (string) _180c_acf( 'recipe_intro' ) );
		}

		// 5. Corps du contenu.
		// Anti-fuite : sur un article premium non accessible, on n'expose JAMAIS
		// le corps (l'excerpt gaté a déjà été tenté plus haut).
		if ( '' === $description
			&& ! ( function_exists( '_180c_article_is_gated' ) && _180c_article_is_gated() ) ) {
			$description = _180c_description_clean( (string) get_post_field( 'post_content', $post_id ) );
		}

		// 6. Repli composé. Indispensable sur les recettes : leur `post_content`
		// est systématiquement vide, et 39 % seulement ont une `recipe_intro`.
		if ( '' === $description ) {
			$title = _180c_description_clean( (string) get_the_title( $post_id ) );
			if ( '' !== $title ) {
				$description = is_singular( 'recipe' )
					/* translators: %s: titre de la recette. */
					? sprintf( __( '%s : la recette de 180°C, avec ses ingrédients, ses étapes et les conseils de la rédaction.', '180c' ), $title )
					/* translators: %s: titre du contenu. */
					: sprintf( __( '%s — à lire sur 180°C, la revue culture food.', '180c' ), $title );
			}
		}
	} elseif ( is_post_type_archive( 'recipe' ) ) {
		$map         = _180c_description_map_pages();
		$description = isset( $map['_recipe_archive'] ) ? (string) $map['_recipe_archive'] : '';
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$map         = _180c_description_map_pages();
		$description = isset( $map['boutique'] ) ? (string) $map['boutique'] : '';
	} elseif ( is_author() ) {
		$author_id   = (int) get_queried_object_id();
		$bio         = _180c_description_clean( (string) get_the_author_meta( 'description', $author_id ) );
		$author_name = function_exists( '_180c_author_display_name' )
			? _180c_author_display_name( $author_id )
			: (string) get_the_author_meta( 'display_name', $author_id );

		if ( '' !== $bio ) {
			$description = $bio;
		}
		if ( '' === $description && '' !== $author_name ) {
			// Réutilise le comptage du résolveur de title (articles / recettes /
			// les deux / aucun) plutôt que de relancer une requête : une seule
			// source décide de ce que l'auteur a publié.
			$context   = function_exists( '_180c_title_author_context' )
				? _180c_title_author_context( $author_id )
				: null;
			$qualifier = ( is_array( $context ) && isset( $context['qualifier'] ) )
				? (string) $context['qualifier']
				: '';

			$description = ( '' !== $qualifier )
				? sprintf(
					/* translators: 1: nom de l'auteur, 2: nature du contenu publié (ex. « Articles et recettes »). */
					__( '%1$s : %2$s publiés sur 180°C, la revue culture food.', '180c' ),
					$author_name,
					mb_strtolower( $qualifier )
				)
				: sprintf(
					/* translators: %s: nom de l'auteur */
					__( 'Les publications de %s sur 180°C, la revue culture food.', '180c' ),
					$author_name
				);
		}
	} elseif ( is_search() ) {
		/* translators: %s: terme de recherche */
		$description = sprintf( __( 'Résultats de recherche pour « %s » sur 180°C, la revue culture food.', '180c' ), get_search_query() );
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();

		$term_desc = ( $term instanceof WP_Term )
			? _180c_description_clean( (string) $term->description )
			: '';

		$description = ( '' !== $term_desc )
			? $term_desc
			: ( ( $term instanceof WP_Term ) ? _180c_description_term_fallback( $term ) : '' );
	}

	if ( '' === $description ) {
		$description = _180c_description_default();
	}

	// La troncature ne s'applique qu'aux descriptions calculées. Couper une
	// saisie manuelle reviendrait à corriger la rédaction sans le lui dire ;
	// c'est le rôle du compteur de caractères de l'admin, pas du front.
	$description = $is_override
		? _180c_description_clean( $description )
		: _180c_description_trim( _180c_description_clean( $description ) );

	// Pagination : la mention est ajoutée après la coupe, pour qu'elle ne soit
	// jamais elle-même tronquée.
	$paged = function_exists( '_180c_title_current_page' ) ? _180c_title_current_page() : 0;
	if ( $paged >= 2 ) {
		/* translators: %d: numéro de page. */
		$description .= ' ' . sprintf( __( '(page %d)', '180c' ), $paged );
	}

	/**
	 * Filtre la meta description finale de la page courante.
	 *
	 * Propagée à <meta name="description">, og:description et au JSON-LD.
	 * Permet à des templates spécifiques (ex. page contact) de
	 * fournir une description dédiée sans dupliquer de balise.
	 *
	 * @param string $description Description résolue par le module.
	 */
	$description = (string) apply_filters( '180c/seo_description', $description );

	$cache = $description;
	return $cache;
}

/**
 * Slugs de pages toujours en `noindex`.
 *
 * Surfaces personnelles ou transactionnelles : elles n'ont aucune valeur
 * d'indexation, et `/mon-carnet/` exposerait en plus une liste propre à un
 * compte. `/panier/`, `/commande/` et `/mon-compte/` sont déjà couverts par
 * `_180c_seo_noindex_transactional()` via les conditions WooCommerce ; ils
 * figurent ici pour que la règle tienne même si WooCommerce est désactivé.
 *
 * `app` est la cible du QR code de téléchargement de l'app
 * (page-templates/page-app-download.php) : une redirection selon l'appareil, sans
 * contenu propre. Le `noindex` couvre les cas où elle rend malgré tout du HTML
 * (aperçu admin) ; sur la réponse redirigée, c'est l'en-tête `X-Robots-Tag` du
 * template qui porte le signal, puisque aucun `<head>` n'est émis.
 *
 * @return string[]
 */
function _180c_seo_noindex_page_slugs(): array {
	$slugs = array(
		'mon-carnet',
		'connexion',
		'mon-compte',
		'panier',
		'commande',
		'app',
	);

	/**
	 * Filtre les slugs de pages forcées en noindex.
	 *
	 * @param string[] $slugs Slugs de pages.
	 */
	return (array) apply_filters( '180c/seo_noindex_page_slugs', $slugs ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * IDs de contenus forcés en `noindex` — exceptions éditoriales.
 *
 * **Point d'extension délibérément vide.**
 *
 * Le champ ACF `no_index` permettait à la rédaction de désindexer un contenu au
 * cas par cas. En le supprimant, on perd ces décisions : c'est le risque
 * principal de cette bascule, puisqu'une page volontairement désindexée
 * redeviendrait indexable sans que rien ne le signale.
 *
 * La liste doit être alimentée **après** lecture de l'écran d'audit en
 * production (Outils → Audit SEO (ACF)), qui relève les contenus portant
 * `no_index` à vrai. Elle n'est pas alimentée ici : la base locale ne reflète
 * pas la production, et inventer des IDs serait pire que de n'en avoir aucun.
 *
 * Deux façons de l'alimenter, au choix :
 *  - ajouter les IDs au tableau ci-dessous, dans un commit dédié ;
 *  - ou passer par le filtre, sans toucher au thème.
 *
 * @return int[]
 */
function _180c_seo_noindex_post_ids(): array {
	$ids = array();

	/**
	 * Filtre les IDs de contenus forcés en noindex.
	 *
	 * @param int[] $ids IDs de contenus.
	 */
	return array_map( 'intval', (array) apply_filters( '180c/seo_noindex_post_ids', $ids ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Indique si un contenu doit être servi en `noindex`.
 *
 * @param int $post_id ID du contenu.
 * @return bool
 */
function _180c_seo_is_noindex_post( int $post_id ): bool {
	if ( $post_id <= 0 ) {
		return false;
	}

	if ( in_array( $post_id, _180c_seo_noindex_post_ids(), true ) ) {
		return true;
	}

	if ( 'page' !== get_post_type( $post_id ) ) {
		return false;
	}

	$slug = (string) get_post_field( 'post_name', $post_id );

	return in_array( $slug, _180c_seo_noindex_page_slugs(), true );
}

/**
 * Retourne les données de l'image Open Graph selon le contexte courant.
 *
 * Chaîne de résolution : override ACF → image mise en avant → première image
 * du contenu → visuel par défaut en médiathèque → visuel de partage par défaut
 * (`_180C_DEFAULT_SHARE_IMAGE`, 1200×630).
 *
 * **Aucun recadrage.** La taille `large` est un redimensionnement
 * proportionnel de WordPress, jamais un crop. Les dimensions déclarées sont
 * celles du fichier réellement servi — voir `_180c_og_image_payload()`.
 *
 * `twitter:image` dérive de cette même fonction (_180c_render_twitter()) : un
 * seul point de résolution pour les deux réseaux.
 *
 * @return array|null { url: string, width: int, height: int } ou null.
 */
function _180c_get_og_image() {
	// 0. Override rédactionnel (`inc/seo/overrides.php`), contenus et termes.
	// Un identifiant invalide, un SVG ou une pièce jointe supprimée rendent un
	// payload null : on retombe alors sur la chaîne normale plutôt que de
	// publier une balise cassée.
	$override_id = _180c_seo_override_id( 'og_image_override' );
	if ( $override_id > 0 ) {
		$payload = _180c_og_image_payload( $override_id );
		if ( null !== $payload ) {
			return $payload;
		}
	}

	$attachment_id = 0;

	if ( is_singular() ) {
		$post_id = (int) get_queried_object_id();

		// 1. Image mise en avant. Couverture mesurée : 1552/1553 recettes,
		// 582/585 articles, 91/92 produits.
		$attachment_id = (int) get_post_thumbnail_id( $post_id );

		// 2. Première image du contenu. Gutenberg comme l'éditeur classique
		// posent la classe `wp-image-{ID}` : on récupère l'ID de la pièce
		// jointe plutôt que son URL, seul moyen d'en connaître les dimensions.
		if ( ! $attachment_id ) {
			$attachment_id = _180c_og_first_content_image_id( $post_id );
		}
	}

	/**
	 * Filtre l'ID de la pièce jointe utilisée comme image Open Graph.
	 *
	 * Permet aux templates dépourvus d'image à la une (ex. page newsletter
	 * dont le visuel est porté par un champ ACF) de fournir leur propre image.
	 *
	 * @param int $attachment_id ID résolu (featured → première image), ou 0.
	 */
	$attachment_id = (int) apply_filters( '180c/og_image_id', (int) $attachment_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	$payload = _180c_og_image_payload( $attachment_id );
	if ( null !== $payload ) {
		return $payload;
	}

	// 3. Contexte auteur : avatar ACF strict. Si l'auteur a téléversé sa photo,
	// on l'utilise. Sinon on ne pousse JAMAIS une URL Gravatar/silhouette.
	if ( is_author() && function_exists( '_180c_author_has_real_avatar' ) ) {
		$author_id = (int) get_queried_object_id();
		if ( _180c_author_has_real_avatar( $author_id ) && function_exists( '_180c_author_photo_attachment_id' ) ) {
			$payload = _180c_og_image_payload( (int) _180c_author_photo_attachment_id( $author_id ) );
			if ( null !== $payload ) {
				return $payload;
			}
		}
	}

	// 4. Visuel par défaut du thème, en médiathèque (filtrable).
	$payload = _180c_og_image_payload( _180c_og_default_image_id() );
	if ( null !== $payload ) {
		return $payload;
	}

	// 5. Visuel de partage par défaut, servi par URL (`_180C_DEFAULT_SHARE_IMAGE`).
	return _180c_og_default_share_payload();
}

/**
 * Construit le tuple Open Graph du visuel de partage par défaut.
 *
 * Contrairement aux étapes précédentes, la source n'est pas une pièce jointe
 * mais un fichier du thème (`assets/social/`) désigné par URL : il n'est pas en
 * médiathèque, donc aucune résolution par ID ne fonctionnerait. Le servir depuis
 * le thème le rend identique en local et en production, et le fait partir avec
 * le déploiement. Les dimensions et le type MIME sont ceux du fichier réellement
 * servi (cf. `inc/bootstrap.php`), pas une supposition.
 *
 * @return array|null { url: string, width: int, height: int, type: string } ou null.
 */
function _180c_og_default_share_payload() {
	/**
	 * Filtre l'URL du visuel de partage par défaut.
	 *
	 * @param string $url URL absolue du visuel 1200×630.
	 */
	$url = (string) apply_filters( '180c/og_default_share_image', defined( '_180C_DEFAULT_SHARE_IMAGE' ) ? (string) _180C_DEFAULT_SHARE_IMAGE : '' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	if ( '' === $url ) {
		return null;
	}

	// Dimensions et type MIME déclarés seulement si l'URL n'a pas été substituée
	// par un filtre : sur un autre fichier, ils seraient faux, et une balise sans
	// dimensions vaut mieux qu'une balise qui ment (même règle que le logo
	// Organization, cf. inc/seo/schema.php).
	$is_default = defined( '_180C_DEFAULT_SHARE_IMAGE' ) && (string) _180C_DEFAULT_SHARE_IMAGE === $url;

	return array(
		'url'    => $url,
		'width'  => $is_default && defined( '_180C_DEFAULT_SHARE_IMAGE_WIDTH' ) ? (int) _180C_DEFAULT_SHARE_IMAGE_WIDTH : 0,
		'height' => $is_default && defined( '_180C_DEFAULT_SHARE_IMAGE_HEIGHT' ) ? (int) _180C_DEFAULT_SHARE_IMAGE_HEIGHT : 0,
		// Seul le visuel constant porte un type : son format est connu de façon
		// certaine. Les pièces jointes de la cascade n'en déclarent pas — leur
		// format varie (WebP, PNG, JPEG) et `og:image:type` est facultatif.
		'type'   => $is_default && defined( '_180C_DEFAULT_SHARE_IMAGE_TYPE' ) ? (string) _180C_DEFAULT_SHARE_IMAGE_TYPE : '',
	);
}

/**
 * Retourne le texte alternatif de l'image de partage courante.
 *
 * Priorité : texte alternatif de la pièce jointe (si l'image en provient) →
 * titre du document. On n'invente jamais de description : à défaut d'alt en
 * médiathèque, le titre de la page décrit correctement ce que montre l'aperçu.
 *
 * @param array $image Tuple retourné par _180c_get_og_image().
 * @return string Texte alternatif, éventuellement vide.
 */
function _180c_og_image_alt( array $image ): string {
	if ( ! empty( $image['alt'] ) ) {
		return (string) $image['alt'];
	}

	return (string) wp_get_document_title();
}

/**
 * Construit le tuple Open Graph d'une pièce jointe.
 *
 * Utilise `wp_get_attachment_image_src()`, et non le couple
 * `wp_get_attachment_image_url()` + `wp_get_attachment_metadata()` :
 * `metadata` porte les dimensions du fichier **d'origine**, alors que l'URL
 * servie est celle de la taille `large`. Sur toute image plus grande que le
 * seuil `large`, l'ancien code annonçait donc des dimensions fausses — mesuré
 * en local : `og:image:width` à 2560×1696 pour un fichier réellement servi en
 * 1024×678. Facebook et X redimensionnent ou rejettent l'aperçu sur cette base.
 *
 * `wp_get_attachment_image_src()` renvoie les dimensions **de la taille
 * demandée** : URL et dimensions ne peuvent plus diverger.
 *
 * Les SVG sont écartés : les crawlers sociaux ne les acceptent pas, et ils
 * n'ont pas de dimensions en pixels exploitables.
 *
 * @param int $attachment_id ID de la pièce jointe. 0 = aucune.
 * @return array|null { url: string, width: int, height: int } ou null.
 */
function _180c_og_image_payload( int $attachment_id ) {
	if ( $attachment_id <= 0 ) {
		return null;
	}

	if ( 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
		return null;
	}

	$src = wp_get_attachment_image_src( $attachment_id, 'large' );
	if ( ! is_array( $src ) || empty( $src[0] ) ) {
		return null;
	}

	return array(
		'url'    => (string) $src[0],
		'width'  => isset( $src[1] ) ? (int) $src[1] : 0,
		'height' => isset( $src[2] ) ? (int) $src[2] : 0,
		// Texte alternatif porté par le payload : le résoudre plus tard depuis
		// l'URL imposerait un `attachment_url_to_postid()` — une requête par
		// page, qui échouerait de surcroît sur une URL de taille dérivée.
		'alt'    => trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
	);
}

/**
 * Retourne l'ID de la première image du contenu d'un post.
 *
 * @param int $post_id ID du contenu.
 * @return int 0 si aucune image identifiable.
 */
function _180c_og_first_content_image_id( int $post_id ): int {
	$content = (string) get_post_field( 'post_content', $post_id );
	if ( '' === $content ) {
		return 0;
	}

	if ( preg_match( '/wp-image-(\d+)/', $content, $matches ) ) {
		return (int) $matches[1];
	}

	return 0;
}

/**
 * Retourne l'ID du visuel Open Graph par défaut du thème.
 *
 * Étape médiathèque de la cascade : elle ne rend un ID que si `custom_logo` est
 * renseigné avec un raster, ou si un filtre en fournit un. Le visuel de partage
 * livré par la direction artistique (1200×630) n'est PAS résolu ici mais par
 * `_180c_og_default_share_payload()` : il vit dans les fichiers du thème et non
 * en médiathèque, aucun ID de pièce jointe ne le désigne.
 *
 * Cette étape reste utile pour qu'un administrateur puisse substituer un visuel
 * de médiathèque au visuel constant, sans toucher au code.
 *
 * @return int ID de la pièce jointe, ou 0.
 */
function _180c_og_default_image_id(): int {
	$default = 0;

	// `custom_logo` n'est retenu que s'il s'agit d'un raster : un SVG serait
	// écarté plus loin par _180c_og_image_payload(), autant être explicite.
	$logo = (int) get_theme_mod( 'custom_logo' );
	if ( $logo && 'image/svg+xml' !== get_post_mime_type( $logo ) ) {
		$default = $logo;
	}

	/**
	 * Filtre l'ID du visuel Open Graph par défaut.
	 *
	 * @param int $default ID de la pièce jointe, ou 0 si aucun visuel.
	 */
	return (int) apply_filters( '180c/og_default_image_id', $default ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Retourne l'URL canonique selon le contexte courant.
 *
 * @return string URL absolue.
 */
function _180c_get_canonical_url() {
	if ( is_singular() ) {
		return (string) get_permalink();
	}
	if ( is_post_type_archive( 'recipe' ) ) {
		return (string) get_post_type_archive_link( 'recipe' );
	}
	if ( is_author() ) {
		return (string) get_author_posts_url( (int) get_queried_object_id() );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		return (string) get_term_link( get_queried_object() );
	}
	if ( is_home() && ! is_front_page() ) {
		return (string) get_permalink( get_option( 'page_for_posts' ) );
	}
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_search() ) {
		return home_url( '/?s=' . rawurlencode( get_search_query( false ) ) );
	}
	// Fallback global : URL courante reconstruite par WP.
	global $wp;
	return home_url( add_query_arg( array(), $wp->request ) );
}

/**
 * Applique le filtre de substitution de canonical.
 *
 * Wrapper de _180c_get_canonical_url() : permet aux modules (notamment le
 * résolveur de titles, qui connaît les archives redondantes) de rerouter la
 * canonical vers la page de référence sans dupliquer la balise.
 *
 * @return string URL absolue.
 */
function _180c_get_canonical_url_filtered() {
	/**
	 * Filtre l'URL canonique de la page courante.
	 *
	 * @param string $canonical URL résolue par le module.
	 */
	return (string) apply_filters( '180c/seo_canonical', _180c_get_canonical_url() ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/*
 * 3. Helpers de rendu (echo wp_head)
 */

/**
 * Teste la présence d'une directive dans une chaîne robots.
 *
 * Compare token par token plutôt qu'en sous-chaîne : `strpos()` seul
 * confondrait `index` avec `noindex`, et `follow` avec `nofollow`. Les
 * directives à valeur (`max-image-preview:large`) sont reconnues sur leur seul
 * nom, quelle que soit la valeur portée.
 *
 * @param string $robots    Chaîne robots (ex. « noindex, follow »).
 * @param string $directive Nom de directive, sans sa valeur (ex. « noindex »).
 * @return bool
 */
function _180c_seo_robots_has( $robots, $directive ) {
	foreach ( explode( ',', (string) $robots ) as $token ) {
		$token = trim( $token );
		if ( $token === $directive || 0 === strpos( $token, $directive . ':' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Absorbe les directives que le core émettait via son propre `wp_robots`.
 *
 * Le thème est désormais seul à rendre la balise `<meta name="robots">` : la
 * sortie du core est désenregistrée en fin de fichier (section 4). Cette
 * fonction reprend, une par une, les directives que cette sortie apportait et
 * que le calcul du thème n'a jamais produites — sans quoi le désenregistrement
 * serait une perte sèche.
 *
 * Appliquée APRÈS `180c/seo_robots`, à dessein : les filtres du module
 * retournent une chaîne complète et écrasent ce qui précède. Poser
 * `max-image-preview:large` avant eux reviendrait à la laisser effacer par le
 * premier filtre qui force un `noindex`.
 *
 * Trois reprises, dans l'ordre de priorité :
 *
 *  1. Réglage « Demander aux moteurs de recherche de ne pas indexer ce site »
 *     (Réglages > Lecture, option `blog_public`). Le core le traduisait en
 *     `noindex, nofollow` via `wp_robots_noindex()`. C'est un interrupteur
 *     global : il écrase tout, et il coupe aussi `max-image-preview:large`
 *     — `wp_robots_max_image_preview_large()` est elle-même conditionnée à
 *     `blog_public`. Sans cette reprise, la case deviendrait purement
 *     décorative.
 *  2. URL de réponse à un commentaire (`?replytocom=`) ou de prévisualisation
 *     d'un commentaire non approuvé : `noindex, follow`. Les commentaires sont
 *     désactivés et `robots.txt` bloque déjà ces motifs, mais la reprise est
 *     gratuite et couvre l'URL fabriquée à la main.
 *  3. `max-image-preview:large`, que le core posait sur TOUTES les pages alors
 *     que le thème ne la produisait que sur sa branche indexable. C'est la
 *     seule directive réellement perdue sur les gabarits `noindex`.
 *
 * @param string $robots Directive robots issue du filtre `180c/seo_robots`.
 * @return string Directive robots complétée.
 */
function _180c_seo_robots_absorb_core( $robots ) {
	$robots = trim( (string) $robots );

	// 1. Site marqué non public : noindex, nofollow, et rien d'autre.
	if ( ! get_option( 'blog_public' ) ) {
		return 'noindex, nofollow';
	}

	// 2. URL de commentaire.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Simple lecture de marqueurs d'URL publics, aucune action déclenchée.
	$is_comment_url = isset( $_GET['replytocom'] )
		|| ( isset( $_GET['unapproved'] ) && isset( $_GET['moderation-hash'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( $is_comment_url && ! _180c_seo_robots_has( $robots, 'noindex' ) ) {
		$robots = 'noindex, follow';
	}

	// 3. Aperçu d'image.
	if ( ! _180c_seo_robots_has( $robots, 'max-image-preview' ) ) {
		$robots = ( '' === $robots ) ? 'max-image-preview:large' : $robots . ', max-image-preview:large';
	}

	return $robots;
}

/**
 * Rend les meta de base : robots, description, Smart Banner iOS.
 *
 * @return void
 */
function _180c_render_meta() {
	// Robots.
	$no_index = false;

	if ( is_singular() ) {
		$post_id = (int) get_queried_object_id();

		// Règles en dur, en remplacement du champ ACF `no_index` supprimé.
		if ( _180c_seo_is_noindex_post( $post_id ) ) {
			$no_index = true;
		}

		// Drafts / trashed (sécurité, en théorie WP ne les sert pas).
		$post = get_post();
		if ( $post && in_array( $post->post_status, array( 'draft', 'trash', 'private' ), true ) ) {
			$no_index = true;
		}
	}

	if ( is_404() ) {
		$no_index = true;
	}

	if ( $no_index ) {
		$robots = 'noindex, nofollow';
	} elseif ( is_search() ) {
		// Page de résultats de recherche : noindex mais follow — on
		// ne veut pas indexer les SERP internes, mais les liens vers les
		// contenus (recettes, articles, auteurs) doivent continuer à
		// transmettre le crawl.
		$robots = 'noindex, follow';
	} elseif ( is_date() ) {
		// Archives de date : faible valeur SEO, fort risque de
		// contenu dupliqué/mince → noindex mais follow (le crawl des liens
		// vers les contenus est préservé).
		$robots = 'noindex, follow';
	} else {
		$robots = 'index, follow, max-image-preview:large, max-snippet:-1';
	}

	/**
	 * Filtre la directive robots calculée par le thème.
	 *
	 * Permet aux templates personnels (ex. /mon-carnet/) de forcer
	 * un noindex sans champ ACF, en s'enregistrant avant `wp_head`.
	 *
	 * @param string $robots Directive robots (ex. « index, follow »).
	 */
	$robots = (string) apply_filters( '180c/seo_robots', $robots );

	// Reprise des directives du core, dont la sortie est désenregistrée en
	// section 4. À faire après le filtre : voir _180c_seo_robots_absorb_core().
	$robots = _180c_seo_robots_absorb_core( $robots );

	printf( '<meta name="robots" content="%s">' . "\n", esc_attr( $robots ) );

	// Description.
	$description = _180c_get_seo_description();
	if ( $description ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
	}

	// Smart Banner iOS.
	if ( defined( '_180C_IOS_APP_ID' ) && _180C_IOS_APP_ID ) {
		/**
		 * Filtre l'App ID iOS pour le Smart Banner.
		 *
		 * @param string $app_id Identifiant numérique de l'app sur l'App Store.
		 */
		$app_id = apply_filters( '180c/ios_app_id', _180C_IOS_APP_ID );
		printf( '<meta name="apple-itunes-app" content="app-id=%s">' . "\n", esc_attr( $app_id ) );
	}
}

/**
 * Force `noindex` sur les pages transactionnelles WooCommerce (S3).
 *
 * Panier, commande et Mon Compte n'ont aucune valeur SEO et sont déjà en
 * `Disallow` dans le robots.txt dynamique (inc/seo/sitemap.php). On aligne la
 * meta robots : `noindex, follow` (on n'indexe pas, mais le crawl des liens
 * sortants — produits, sections — reste autorisé). Branché sur le filtre maison
 * `180c/seo_robots` exposé par _180c_render_meta().
 *
 * @param string $robots Directive robots calculée par le thème.
 * @return string
 */
function _180c_seo_noindex_transactional( $robots ) {
	if ( ! function_exists( 'is_cart' ) ) {
		return $robots;
	}
	if ( is_cart() || is_checkout() || is_account_page() ) {
		return 'noindex, follow';
	}
	return $robots;
}
add_filter( '180c/seo_robots', '_180c_seo_noindex_transactional' );

/**
 * Rend la balise canonical.
 *
 * Sur les singuliers, WP core imprime déjà rel_canonical (wp_head prio 10) :
 * on ne le double pas. Pour les archives et pages spéciales, on l'imprime ici.
 *
 * @return void
 */
function _180c_render_canonical() {
	if ( is_singular() ) {
		return;
	}
	if ( is_404() ) {
		return;
	}
	$canonical = _180c_get_canonical_url_filtered();
	if ( $canonical ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
	}
}

/**
 * Rend les balises Open Graph.
 *
 * @return void
 */
function _180c_render_og() {
	$og_title = wp_get_document_title();
	$og_desc  = _180c_get_seo_description();
	$og_url   = is_singular() ? (string) get_permalink() : _180c_get_canonical_url_filtered();
	$og_image = _180c_get_og_image();

	$og_type = 'website';
	if ( is_singular( array( 'post', 'recipe' ) ) ) {
		$og_type = 'article';
	} elseif ( is_singular( 'product' ) ) {
		$og_type = 'product';
	} elseif ( is_author() ) {
		$og_type = 'profile';
	}

	echo '<meta property="og:site_name" content="180°C">' . "\n";
	echo '<meta property="og:locale" content="fr_FR">' . "\n";
	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $og_type ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $og_title ) );
	if ( $og_desc ) {
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $og_desc ) );
	}
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $og_url ) );

	if ( $og_image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $og_image['url'] ) );
		if ( ! empty( $og_image['type'] ) ) {
			printf( '<meta property="og:image:type" content="%s">' . "\n", esc_attr( $og_image['type'] ) );
		}
		if ( $og_image['width'] > 0 ) {
			printf( '<meta property="og:image:width" content="%d">' . "\n", (int) $og_image['width'] );
		}
		if ( $og_image['height'] > 0 ) {
			printf( '<meta property="og:image:height" content="%d">' . "\n", (int) $og_image['height'] );
		}
		$og_image_alt = _180c_og_image_alt( $og_image );
		if ( '' !== $og_image_alt ) {
			printf( '<meta property="og:image:alt" content="%s">' . "\n", esc_attr( $og_image_alt ) );
		}
	}

	// Profile (page auteur) : balises OGP spécifiques au type "profile".
	if ( 'profile' === $og_type ) {
		$author_id = (int) get_queried_object_id();
		$first     = (string) get_the_author_meta( 'first_name', $author_id );
		$last      = (string) get_the_author_meta( 'last_name', $author_id );
		$nickname  = (string) get_the_author_meta( 'user_nicename', $author_id );
		if ( '' !== $first ) {
			printf( '<meta property="profile:first_name" content="%s">' . "\n", esc_attr( $first ) );
		}
		if ( '' !== $last ) {
			printf( '<meta property="profile:last_name" content="%s">' . "\n", esc_attr( $last ) );
		}
		if ( '' !== $nickname ) {
			printf( '<meta property="profile:username" content="%s">' . "\n", esc_attr( $nickname ) );
		}
	}

	// Article (posts natifs + recettes) : balises OGP spécifiques au type
	// "article" — published_time, modified_time, author, section. Ajoutées
	// par pour aligner Facebook/LinkedIn sur les Rich Results
	// Schema déjà émis pour le @graph (mêmes données, surface différente).
	if ( 'article' === $og_type ) {
		$post_id = (int) get_the_ID();
		if ( $post_id ) {
			printf(
				'<meta property="article:published_time" content="%s">' . "\n",
				esc_attr( get_the_date( 'c', $post_id ) )
			);
			printf(
				'<meta property="article:modified_time" content="%s">' . "\n",
				esc_attr( get_the_modified_date( 'c', $post_id ) )
			);

			// article:author — URL de la page auteur publique seulement.
			// On évite d'exposer une URL qui partirait en 301 vers l'accueil
			// pour les comptes masqués (toggle ACF author_public off).
			$author_id = (int) get_post_field( 'post_author', $post_id );
			if ( $author_id ) {
				$is_public = function_exists( '_180c_author_is_public' )
					? _180c_author_is_public( $author_id )
					: true;
				if ( $is_public ) {
					printf(
						'<meta property="article:author" content="%s">' . "\n",
						esc_url( get_author_posts_url( $author_id ) )
					);
				}
			}

			// article:section — uniquement pour les posts natifs (les
			// recettes utilisent recipe_category dans le schema, qui est
			// différent sémantiquement de "rubrique éditoriale").
			if ( is_singular( 'post' ) && function_exists( '_180c_article_primary_category' ) ) {
				$primary = _180c_article_primary_category( $post_id );
				if ( $primary instanceof WP_Term ) {
					printf(
						'<meta property="article:section" content="%s">' . "\n",
						esc_attr( $primary->name )
					);
				}
			}
		}
	}

	// Product (WooCommerce) : balises OGP spécifiques au type "product" —
	// prix, devise (EUR), disponibilité. Mêmes données que le nœud Product
	// du @graph (inc/seo/schema.php), surface différente (Facebook/LinkedIn).
	if ( 'product' === $og_type && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( get_the_ID() );
		if ( $product instanceof WC_Product ) {
			$price = $product->get_price();
			if ( '' !== $price ) {
				printf(
					'<meta property="product:price:amount" content="%s">' . "\n",
					esc_attr( $price )
				);
				printf(
					'<meta property="product:price:currency" content="%s">' . "\n",
					esc_attr( get_woocommerce_currency() )
				);
			}
			printf(
				'<meta property="og:availability" content="%s">' . "\n",
				esc_attr( $product->is_in_stock() ? 'instock' : 'outofstock' )
			);
		}
	}
}

/**
 * Rend les balises Twitter Cards (summary_large_image).
 *
 * @return void
 */
function _180c_render_twitter() {
	$title = wp_get_document_title();
	$desc  = _180c_get_seo_description();
	$image = _180c_get_og_image();

	/**
	 * Filtre le handle Twitter/X du site.
	 *
	 * @param string $handle Handle du compte X/Twitter, avec ou sans arobase.
	 */
	$twitter_handle = apply_filters( '180c/seo_twitter_handle', '@180C_LaRevue' );

	// `twitter:site` attend un @handle. La valeur était servie sans arobase,
	// forme que la documentation X ne reconnaît pas ; on la normalise ici plutôt
	// que dans la constante, pour qu'un filtre mal renseigné soit rattrapé aussi.
	$twitter_handle = ltrim( trim( (string) $twitter_handle ), '@' );
	if ( '' !== $twitter_handle ) {
		$twitter_handle = '@' . $twitter_handle;
	}

	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	if ( '' !== $twitter_handle ) {
		printf( '<meta name="twitter:site" content="%s">' . "\n", esc_attr( $twitter_handle ) );
	}
	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
	if ( $desc ) {
		printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	if ( $image ) {
		printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image['url'] ) );
		$image_alt = _180c_og_image_alt( $image );
		if ( '' !== $image_alt ) {
			printf( '<meta name="twitter:image:alt" content="%s">' . "\n", esc_attr( $image_alt ) );
		}
	}
}

/*
 * 4. Câblage wp_head (priorité 1 — avant tout autre)
 */

/*
 * Une seule balise robots par page.
 *
 * Jusqu'ici, chaque page en sortait DEUX : celle du core via `wp_robots`
 * (`max-image-preview:large`, complétée par WooCommerce sur les pages
 * transactionnelles) et celle que `_180c_render_meta()` calcule. Elles ne se
 * contredisaient pas, mais rien ne le garantissait, et Google n'a aucune règle
 * stable pour arbitrer deux balises robots divergentes — il applique en
 * général la plus restrictive, donc un `noindex` accidentel l'emporterait.
 *
 * Ce qui a été absorbé côté thème avant le débranchement, voir
 * `_180c_seo_robots_absorb_core()` : `max-image-preview:large` sur les
 * branches `noindex`, le réglage `blog_public`, et les URL de commentaire.
 * Le `noindex` WooCommerce des pages panier / commande / compte était déjà
 * couvert par `_180c_seo_noindex_transactional()`.
 *
 * ⛔ Retirer l'ACTION, jamais les callbacks du FILTRE `wp_robots` : celui-ci
 * alimente aussi `login_head` (wp-login.php) et `embed_head` (documents
 * oEmbed). Un `remove_all_filters( 'wp_robots' )` réindexerait la page de
 * connexion et les embeds.
 */
remove_action( 'wp_head', 'wp_robots', 1 );

add_action(
	'wp_head',
	function () {
		_180c_render_meta();
		_180c_render_canonical();
		_180c_render_og();
		_180c_render_twitter();
	},
	1
);
