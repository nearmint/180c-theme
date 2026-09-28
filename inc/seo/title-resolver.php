<?php
/**
 * Résolution contextuelle du `<title>`.
 *
 * Unique point d'entrée du site : `pre_get_document_title` (priorité 6, juste
 * après le point d'extension `180c/seo_title_pattern` de meta-tags.php, qu'il
 * ne clobbers jamais).
 *
 * Prendre la main ici plutôt que sur `document_title_parts` est délibéré : la
 * mécanique `$parts` de WordPress rajoute elle-même le nom du site avec son
 * propre séparateur, ce qui rend impossible la règle « une seule occurrence de
 * la marque » et le suffixe unique ` · 180°C`. `document_title_parts` reste
 * branché (meta-tags.php) et sert de filet pour les contextes non couverts
 * ici — recherche, 404, archives de date.
 *
 * `og:title` et `twitter:title` dérivent tous deux de `wp_get_document_title()`
 * (inc/seo/meta-tags.php) : ils suivent automatiquement, sans duplication.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Court-circuite la construction native du title par le helper central.
 *
 * @param string $title Title déjà résolu en amont ('' par défaut).
 * @return string Title final, ou `$title` inchangé si le contexte n'est pas couvert.
 */
function _180c_seo_resolve_document_title( $title ) {
	// Un filtre amont a déjà tranché (ex. pattern custom) : on ne touche pas.
	if ( '' !== (string) $title ) {
		return $title;
	}

	$resolved = _180c_title_context();
	if ( null === $resolved ) {
		return $title;
	}

	$args          = isset( $resolved['args'] ) ? (array) $resolved['args'] : array();
	$args['paged'] = isset( $args['paged'] ) ? (int) $args['paged'] : _180c_title_current_page();

	return _180c_build_title(
		(string) $resolved['subject'],
		isset( $resolved['qualifier'] ) ? (string) $resolved['qualifier'] : '',
		$args
	);
}
add_filter( 'pre_get_document_title', '_180c_seo_resolve_document_title', 6 );

/**
 * Résout le couple sujet/qualificatif du contexte courant.
 *
 * Mémoïsé : `wp_get_document_title()` est appelé au moins trois fois par page
 * (title, og:title, twitter:title).
 *
 * @return array{subject:string, qualifier:string, args:array}|null Null si contexte non couvert.
 */
function _180c_title_context() {
	static $cache = false;
	if ( false !== $cache ) {
		return $cache;
	}

	$cache = _180c_title_resolve_context();

	/**
	 * Filtre le contexte de title résolu, avant assemblage.
	 *
	 * @param array|null $context Tuple {subject, qualifier, args} ou null.
	 */
	$cache = apply_filters( '180c/title_context', $cache ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	return $cache;
}

/**
 * Calcule le contexte de title. Voir _180c_title_context() pour le cache.
 *
 * @return array{subject:string, qualifier:string, args:array}|null
 */
function _180c_title_resolve_context() {
	// 1. Routes virtuelles d'authentification (rewrite rules, aucun objet de
	// requête : sans ce cas, WordPress rend un title vide → « 180°C » seul).
	$auth = _180c_title_auth_context();
	if ( null !== $auth ) {
		return $auth;
	}

	// 2. Accueil.
	if ( is_front_page() ) {
		return _180c_title_page_entry( '_front' );
	}

	// 3. Archive du CPT recipe (/toutes-les-recettes/).
	if ( is_post_type_archive( 'recipe' ) ) {
		return _180c_title_page_entry( '_recipe_archive' );
	}

	// 4. Taxonomies.
	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$entry = _180c_title_term_entry( $term );

			return array(
				'subject'   => $entry['subject'],
				'qualifier' => $entry['qualifier'],
				'args'      => array(),
			);
		}
	}

	// 5. Page auteur.
	if ( is_author() ) {
		return _180c_title_author_context( (int) get_queried_object_id() );
	}

	// 6. Contenus singuliers.
	if ( is_singular() ) {
		return _180c_title_singular_context();
	}

	// Contexte non couvert (recherche, 404, archives de date) : on laisse la
	// mécanique $parts de meta-tags.php faire son travail.
	return null;
}

/*
 * ---------------------------------------------------------------------------
 * Contextes
 * ---------------------------------------------------------------------------
 */

/**
 * Contexte des routes virtuelles d'authentification.
 *
 * Ces routes (`inc/auth/*.php`) sont servies par des rewrite rules sans objet
 * de requête : WordPress n'a aucun titre à proposer.
 *
 * @return array{subject:string, qualifier:string, args:array}|null
 */
function _180c_title_auth_context() {
	$auth = (string) get_query_var( '_180c_auth' );
	if ( '' === $auth ) {
		return null;
	}

	$subjects = array(
		'login'     => __( 'Connexion', '180c' ),
		'register'  => __( 'Inscription', '180c' ),
		'forgot'    => __( 'Mot de passe oublié', '180c' ),
		'reset-pwd' => __( 'Réinitialiser le mot de passe', '180c' ),
	);

	if ( ! isset( $subjects[ $auth ] ) ) {
		return null;
	}

	return array(
		'subject'   => $subjects[ $auth ],
		'qualifier' => '',
		'args'      => array(),
	);
}

/**
 * Contexte d'une page statique mappée.
 *
 * Une page listée dans `_180c_title_map_pages()` a un title décidé
 * éditorialement et versionné. Depuis la suppression du groupe ACF « SEO »,
 * c'est la seule source possible : plus rien en base ne peut la contredire.
 *
 * @param string $key Clé de la table (slug de page, ou clé virtuelle).
 * @return array{subject:string, qualifier:string, args:array}|null
 */
function _180c_title_page_entry( string $key ) {
	$map = _180c_title_map_pages();
	if ( ! isset( $map[ $key ] ) ) {
		return null;
	}

	$entry = $map[ $key ];

	return array(
		'subject'   => (string) $entry['subject'],
		'qualifier' => (string) $entry['qualifier'],
		'args'      => isset( $entry['args'] ) ? (array) $entry['args'] : array(),
	);
}

/**
 * Contexte d'une page auteur.
 *
 * Le qualificatif décrit ce que l'auteur a réellement publié. Le comptage
 * réutilise les requêtes du template auteur (`inc/author.php`), qui alimentent
 * déjà les ancres `#articles` et `#recettes` — aucune requête supplémentaire
 * n'est écrite ici.
 *
 * @param int $author_id ID de l'auteur.
 * @return array{subject:string, qualifier:string, args:array}|null
 */
function _180c_title_author_context( int $author_id ) {
	if ( ! $author_id || ! function_exists( '_180c_author_display_name' ) ) {
		return null;
	}

	$name = _180c_author_display_name( $author_id );
	if ( '' === $name ) {
		return null;
	}

	$counts = _180c_title_author_counts( $author_id );

	if ( $counts['articles'] > 0 && $counts['recipes'] > 0 ) {
		$qualifier = __( 'Articles et recettes', '180c' );
	} elseif ( $counts['articles'] > 0 ) {
		$qualifier = __( 'Articles', '180c' );
	} elseif ( $counts['recipes'] > 0 ) {
		$qualifier = __( 'Recettes', '180c' );
	} else {
		// Aucun contenu publié : title neutre + noindex (cf. robots ci-dessous).
		$qualifier = '';
	}

	return array(
		'subject'   => $name,
		'qualifier' => $qualifier,
		'args'      => array(),
	);
}

/**
 * Nombre d'articles et de recettes publiés par un auteur.
 *
 * Mémoïsé par auteur : le résolveur de title et le filtre robots consomment
 * tous deux ce résultat.
 *
 * @param int $author_id ID de l'auteur.
 * @return array{articles:int, recipes:int}
 */
function _180c_title_author_counts( int $author_id ): array {
	static $cache = array();

	if ( isset( $cache[ $author_id ] ) ) {
		return $cache[ $author_id ];
	}

	$articles = 0;
	$recipes  = 0;

	if ( function_exists( '_180c_author_articles_query' ) ) {
		$articles = (int) _180c_author_articles_query( $author_id, 1 )->found_posts;
	}
	if ( function_exists( '_180c_author_recipes_query' ) ) {
		$recipes = (int) _180c_author_recipes_query( $author_id, 1 )->found_posts;
	}

	$cache[ $author_id ] = array(
		'articles' => $articles,
		'recipes'  => $recipes,
	);

	return $cache[ $author_id ];
}

/**
 * Contexte d'un contenu singulier (page, article, recette, produit).
 *
 * @return array{subject:string, qualifier:string, args:array}|null
 */
function _180c_title_singular_context() {
	$post_id = (int) get_queried_object_id();
	if ( ! $post_id ) {
		return null;
	}

	// Page mappée : la table prime (source de vérité unique).
	if ( is_page() ) {
		$slug  = (string) get_post_field( 'post_name', $post_id );
		$entry = _180c_title_page_entry( $slug );
		if ( null !== $entry ) {
			return $entry;
		}
	}

	// Contenu unitaire : le sujet est le titre du contenu. L'override ACF
	// `seo_title` ne passe plus par ici — il court-circuite le gabarit en amont
	// (`inc/seo/overrides.php`, priorité 4) et sert sa valeur telle quelle.
	// Ce chemin ne voit donc que les contenus sans override.
	$subject = _180c_title_normalize( (string) get_the_title( $post_id ) );

	if ( '' === $subject ) {
		return null;
	}

	// Le mot « Recette » passe à droite du cadratin, en qualificatif — il n'est
	// jamais préfixé au titre.
	$qualifier = is_singular( 'recipe' ) ? __( 'Recette', '180c' ) : '';

	return array(
		'subject'   => $subject,
		'qualifier' => $qualifier,
		'args'      => array(),
	);
}

/*
 * ---------------------------------------------------------------------------
 * Robots & canonicals pilotés par la table (étape 7)
 * ---------------------------------------------------------------------------
 */

/**
 * Identifiant `taxonomie:slug` du terme couramment interrogé.
 *
 * @return string Chaîne vide hors contexte de taxonomie.
 */
function _180c_title_current_term_key(): string {
	if ( ! is_category() && ! is_tag() && ! is_tax() ) {
		return '';
	}

	$term = get_queried_object();

	return ( $term instanceof WP_Term ) ? $term->taxonomy . ':' . $term->slug : '';
}

/**
 * Force `noindex, follow` sur les surfaces sans valeur d'indexation.
 *
 * Couvre : les termes redondants de `_180c_title_noindex_terms()`, les pages
 * auteur sans aucun contenu publié, les routes d'authentification, et — si le
 * seuil est activé — les archives de taxonomie trop minces.
 *
 * @param string $robots Directive robots calculée en amont.
 * @return string
 */
function _180c_title_seo_robots( $robots ) {
	// Routes d'authentification.
	if ( '' !== (string) get_query_var( '_180c_auth' ) ) {
		return 'noindex, follow';
	}

	// Page auteur sans contenu publié.
	if ( is_author() ) {
		$counts = _180c_title_author_counts( (int) get_queried_object_id() );
		if ( 0 === $counts['articles'] && 0 === $counts['recipes'] ) {
			return 'noindex, follow';
		}
	}

	$key = _180c_title_current_term_key();
	if ( '' === $key ) {
		return $robots;
	}

	if ( in_array( $key, _180c_title_noindex_terms(), true ) ) {
		return 'noindex, follow';
	}

	// Archives minces — inactif tant que le seuil vaut 0 (décision éditoriale).
	$threshold = _180c_title_thin_archive_threshold();
	if ( $threshold > 0 ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term && (int) $term->count < $threshold ) {
			return 'noindex, follow';
		}
	}

	return $robots;
}
add_filter( '180c/seo_robots', '_180c_title_seo_robots' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespace de hooks 180c/ imposé par CLAUDE.md.

/**
 * Remplace la canonical des termes redondants par leur page de référence.
 *
 * `category/la-gazette` → `/la-gazette/` ; `product_cat/abonnements` →
 * `/abonnement/`. Ces deux archives dupliquent une page éditoriale existante.
 *
 * @param string $canonical URL canonique calculée par le module.
 * @return string
 */
function _180c_title_canonical_override( $canonical ) {
	$key = _180c_title_current_term_key();
	if ( '' === $key ) {
		return $canonical;
	}

	$map = _180c_title_term_canonicals();

	return isset( $map[ $key ] ) ? home_url( $map[ $key ] ) : $canonical;
}
add_filter( '180c/seo_canonical', '_180c_title_canonical_override' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespace de hooks 180c/ imposé par CLAUDE.md.
