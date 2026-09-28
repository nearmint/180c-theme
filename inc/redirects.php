<?php
/**
 * Redirections 301 — résolveur runtime.
 *
 * Sert au front les redirections produites lors de la migration et stockées dans
 * la table `{prefix}180c_redirects` (schéma défini par dans
 * le script SQL de création des tables, alimentée lors de la migration des recettes ; scripts non versionnés).
 *
 * Stratégie :
 *  - Le résolveur ne touche JAMAIS la BDD sur une page qui se résout normalement :
 *    la table n'est interrogée que lorsque WordPress s'apprête à servir un 404
 *    (`is_404()` au moment de `template_redirect`). Coût sur le trafic courant : 0
 *    requête supplémentaire.
 *  - Une « static map » filtrable (`180c/redirects/static_map`) gère en plus les
 *    redirections structurelles décidées (déplacements de préfixes) qui doivent
 *    s'appliquer même si l'ancienne URL résout encore.
 *  - Les chemins sont RELATIFS (ex. `/ma-recette/` → `/recettes/ma-recette/`),
 *    conformément au stockage de la table. La query string est préservée.
 *  - Protection anti-boucle : une cible qui normalise vers la source est ignorée.
 *  - En complément des 301, une liste de motifs sert un **410 Gone** pour les
 *    chemins définitivement supprimés et sans équivalent (voir la section
 *    « 410 Gone » en fin de fichier).
 *
 * La résolution effective des URLs (table de mapping détaillée, audit des 50 URLs
 * représentatives) est documentée hors dépôt.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nom complet (préfixé) de la table des redirections.
 *
 * @return string
 */
function _180c_redirects_table() {
	global $wpdb;
	return $wpdb->prefix . '180c_redirects';
}

/**
 * Normalise un chemin pour comparaison / stockage.
 *
 * Conserve uniquement le chemin (sans schéma, hôte ni query string), force un
 * slash de tête, retire le slash de fin (sauf racine) et décode les %xx.
 *
 * @param string $path Chemin ou URL.
 * @return string Chemin normalisé sans slash final (ex. `/numeros`), ou `/`.
 */
function _180c_redirect_normalize_path( $path ) {
	$path = (string) wp_parse_url( (string) $path, PHP_URL_PATH );
	$path = rawurldecode( $path );
	$path = '/' . ltrim( $path, '/' );
	$path = rtrim( $path, '/' );
	return '' === $path ? '/' : $path;
}

/**
 * Carte des redirections structurelles décidées (déplacements de préfixes).
 *
 * Les **clés** sont des chemins normalisés au sens de
 * `_180c_redirect_normalize_path()`, donc **sans slash final** : c'est sous cette
 * forme que la requête entrante est comparée.
 *
 * Les **valeurs** conservent au contraire leur **slash final**, afin que
 * `wp_safe_redirect()` pointe directement sur l'URL canonique du site et que le
 * navigateur n'enchaîne pas un second saut via le `redirect_canonical` de
 * WordPress (exigence : une redirection = un seul saut).
 *
 * S'appliquent même quand l'ancienne URL résout encore (contrairement à la table
 * BDD, réservée aux 404). Extensible via le filtre `180c/redirects/static_map`
 * sans toucher au code, et via la constante `_180C_EXTRA_REDIRECTS`
 * (wp-config.php, tableau `source => cible`) pour les entrées qui ne doivent
 * pas être versionnées.
 *
 * Entrées issues de l'audit Google Search Console du 2026-07-30 : cinq hubs
 * supprimés sans redirection, toujours servis en SERP mais en 404 au fetch
 * (trafic organique résiduel). Cibles arbitrées après vérification du
 * statut réel de chaque destination en prod :
 *
 *  - `/cahiers-de-delphine/` et
 *    `/les-cahiers-de-delphine/` : les deux portent la même requête de
 *    marque et sont en 404. Toutes les cibles « Cahiers » envisagées le sont
 *    aussi — `/recettes/les-cahiers-de-delphine/` et
 *    `/category/recettes/les-cahiers-de-delphine/` inclus, la branche de
 *    catégorie `recettes` ayant disparu avec la migration vers le CPT `recipe`.
 *    Les pages supprimées étant des optins newsletter, le trafic est renvoyé
 *    vers `/newsletter/`.
 *  - `/recettes-en-ligne/` et `/toutes-nos-selections-de-recettes/`
 *    : galerie de recettes vivante.
 *  - `/abonnement-en-ligne/` : la landing `/abonnement/` est
 *    publiée et indexable. `/categorie-produit/abonnements/` est écartée : elle
 *    est déjà forcée en `noindex` et sa canonical pointe vers `/abonnement/`
 *    (voir `inc/seo/title-map.php`).
 *
 * Trois entrées supplémentaires viennent de l'ITEM 7 (assainissement de
 * l'indexation), et **dépendent de la migration `020-index-cleanup`** :
 *
 *  - `/la-gazette/reportages/lgep-test` : slug fautif d'un article bien réel
 *    (« La Sélection Engagée de La Grande Épicerie de Paris », 2016), renommé
 *    par l'opération A de la migration.
 *  - deux archives d'auteur renommées ou vidées par l'opération B et C
 *    (identifiant de connexion exposé dans une URL, doublon de compte). Elles
 *    dérivent de noms de personnes : elles vivent dans `_180C_EXTRA_REDIRECTS`
 *    (wp-config.php), pas dans le dépôt.
 *
 * ⚠️ Ces entrées ne doivent partir en production qu'**après** l'opération de
 * renommage correspondante en base : déployées avant, elles redirigeraient vers
 * des URLs qui n'existent pas encore, donc vers des 404.
 *
 * @return array<string,string> source normalisée (sans slash final) => cible.
 */
function _180c_redirect_static_map() {
	$map = array(
		'/cahiers-de-delphine'               => '/newsletter/',
		'/les-cahiers-de-delphine'           => '/newsletter/',
		'/recettes-en-ligne'                 => '/recettes/',
		'/toutes-nos-selections-de-recettes' => '/recettes/',
		'/abonnement-en-ligne'               => '/abonnement/',

		// ITEM 7 — voir l'avertissement d'ordonnancement ci-dessus.
		'/la-gazette/reportages/lgep-test'   => '/la-gazette/reportages/la-selection-engagee-la-grande-epicerie-de-paris/',
	);

	if ( defined( '_180C_EXTRA_REDIRECTS' ) && is_array( _180C_EXTRA_REDIRECTS ) ) {
		foreach ( _180C_EXTRA_REDIRECTS as $source => $target ) {
			if ( is_string( $source ) && is_string( $target ) && '' !== $source && '' !== $target ) {
				$map[ $source ] = $target;
			}
		}
	}

	/**
	 * Filtre la carte des redirections structurelles.
	 *
	 * @param array<string,string> $map source => cible (chemins normalisés).
	 */
	return (array) apply_filters( '180c/redirects/static_map', $map ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Recherche une redirection pour un chemin normalisé.
 *
 * Ordre de résolution : (1) static map décidée, (2) table BDD (uniquement si la
 * requête courante est un 404). Le résultat est mis en cache mémoire le temps de
 * la requête.
 *
 * @param string $path        Chemin normalisé (sans slash final).
 * @param bool   $consult_db  Autoriser la consultation de la table BDD.
 * @return array{target:string,status:int,id:int}|null
 */
function _180c_redirect_lookup( $path, $consult_db ) {
	static $cache = array();

	$key = ( $consult_db ? 'db:' : 'static:' ) . $path;
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}

	$result = null;

	$static = _180c_redirect_static_map();
	if ( isset( $static[ $path ] ) ) {
		$result = array(
			'target' => (string) $static[ $path ],
			'status' => 301,
			'id'     => 0,
		);
	}

	if ( null === $result && $consult_db ) {
		global $wpdb;
		$table = _180c_redirects_table();
		// La table stocke les sources avec slash final (ex. `/ma-recette/`).
		// On teste les deux variantes pour être tolérant.
		$candidates = array( $path . '/', $path );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, target_url, status_code FROM {$table} WHERE source_url IN (%s, %s) LIMIT 1",
				$candidates[0],
				$candidates[1]
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $row && '' !== (string) $row['target_url'] ) {
			$status = (int) $row['status_code'];
			$result = array(
				'target' => (string) $row['target_url'],
				'status' => ( $status >= 300 && $status < 400 ) ? $status : 301,
				'id'     => (int) $row['id'],
			);
		}
	}

	$cache[ $key ] = $result;
	return $result;
}

/**
 * Incrémente le compteur de hits d'une redirection BDD (best-effort).
 *
 * @param int $id Identifiant de ligne dans la table.
 * @return void
 */
function _180c_redirect_bump_hits( $id ) {
	if ( $id <= 0 ) {
		return;
	}
	global $wpdb;
	$table = _180c_redirects_table();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = hits + 1 WHERE id = %d", $id ) );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Construit l'URL absolue de destination à partir d'une cible relative.
 *
 * Préserve la query string de la requête entrante si la cible n'en porte pas
 * déjà une. Une cible absolue (http/https) est utilisée telle quelle.
 *
 * @param string $target     Cible (chemin relatif ou URL absolue).
 * @param string $query_string Query string entrante (sans le `?`).
 * @return string URL prête pour `wp_safe_redirect`.
 */
function _180c_redirect_build_target( $target, $query_string ) {
	$target = trim( (string) $target );

	$is_absolute = (bool) wp_parse_url( $target, PHP_URL_HOST );
	$has_query   = false !== strpos( $target, '?' );

	if ( ! $is_absolute ) {
		$target = home_url( '/' . ltrim( $target, '/' ) );
	}

	if ( '' !== $query_string && ! $has_query ) {
		$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query_string;
	}

	return $target;
}

/**
 * Intercepte la requête front et applique une éventuelle redirection.
 *
 * Branché sur `template_redirect` en priorité haute (5), avant le
 * `redirect_canonical` natif (priorité 10) et avant le rendu du template 404.
 *
 * @return void
 */
function _180c_redirects_maybe_redirect() {
	// Jamais en admin, REST, cron, ou flux : ces contextes n'utilisent pas
	// template_redirect comme une page front, mais on reste défensif.
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() ) {
		return;
	}
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- décomposé/normalisé ci-dessous.
	$path        = _180c_redirect_normalize_path( $request_uri );

	if ( '/' === $path ) {
		return;
	}

	// La table BDD n'est consultée que sur un 404 (zéro requête sur le trafic
	// normal). La static map, elle, s'applique toujours.
	$consult_db = is_404();
	$match      = _180c_redirect_lookup( $path, $consult_db );

	if ( null === $match ) {
		return;
	}

	// Anti-boucle : la cible normalisée ne doit pas pointer vers la source.
	if ( _180c_redirect_normalize_path( $match['target'] ) === $path ) {
		return;
	}

	$query_string = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ré-encodée par wp_safe_redirect.
	$destination  = _180c_redirect_build_target( $match['target'], $query_string );

	_180c_redirect_bump_hits( $match['id'] );

	wp_safe_redirect( $destination, $match['status'] );
	exit;
}
add_action( 'template_redirect', '_180c_redirects_maybe_redirect', 5 );

/*
 * ---------------------------------------------------------------------------
 * URL unique par article — 301 des variantes de catégorie (ITEM 14)
 * ---------------------------------------------------------------------------
 *
 * Cause racine. La structure de permaliens est `/%category%/%postname%/` avec
 * un `category_base` vide. Un article rangé à la fois dans une catégorie parente
 * et dans sa fille est donc servi en 200 sous PLUSIEURS chemins :
 *
 *   /la-gazette/culture-food/caprier-gros-nuls/   (get_permalink — la canonique)
 *   /la-gazette/caprier-gros-nuls/                (variante, 200 elle aussi)
 *
 * Pourquoi le canonical natif ne la corrige pas. `redirect_canonical()` traite
 * bien le cas général — `/foo/caprier-gros-nuls/` et
 * `/la-gazette/reportages/caprier-gros-nuls/` partent en 301 — mais son test est
 * (wp-includes/canonical.php, branche `is_single() && %category%`) :
 *
 *   ! $category || is_wp_error( $category ) || ! has_term( $category->term_id, ... )
 *
 * Autrement dit il ne redirige que si le segment de catégorie est introuvable
 * OU n'est pas assigné au post. Quand le post porte réellement la catégorie
 * parente, `has_term()` est vrai : le chemin est jugé valide et servi tel quel.
 * C'est exactement le trou que cette section bouche.
 *
 * Mesure locale du 2026-07-31 : 585 articles publiés, 569 exposent au moins une
 * variante, 746 URLs dupliquées au total.
 *
 * Ce que la règle NE touche pas, par construction : les 68 articles dont la
 * canonique EST `/la-gazette/{slug}/` (catégorie unique, sans sous-section).
 * Comme la comparaison se fait contre `get_permalink()`, leur chemin demandé est
 * égal à leur chemin canonique et aucune redirection n'est émise. Aucune liste
 * d'exclusion à maintenir.
 *
 * Hors périmètre volontaire : les archives de catégories (`/opinion/` vs
 * `/category/opinion/`), sujet parké au backlog. La règle ne s'applique qu'aux
 * requêtes `is_singular( 'post' )`.
 */

/**
 * Redirige une requête d'article servie sous un chemin non canonique.
 *
 * Branché en priorité 6 : après le résolveur de redirections décidées (5), qui
 * reste prioritaire, et avant le `redirect_canonical` natif (10) — de sorte
 * qu'une variante parte en UN seul saut vers `get_permalink()` plutôt que
 * d'enchaîner deux 301.
 *
 * Le périmètre est restreint au type `post` et à la présence de `%category%`
 * dans la structure de permaliens : c'est la seule combinaison qui produit le
 * défaut. Les pages, produits WooCommerce et CPT `recipe` gardent le
 * comportement natif, leurs permaliens ne portant pas de segment de catégorie.
 *
 * @return void
 */
function _180c_redirects_canonical_single() {
	global $wp_rewrite;

	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() || is_embed() ) {
		return;
	}
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}
	if ( ! is_main_query() || ! is_singular( 'post' ) || is_preview() || is_attachment() ) {
		return;
	}

	// Le défaut n'existe que sous une structure à segment de catégorie.
	if ( ! $wp_rewrite instanceof WP_Rewrite || false === strpos( (string) $wp_rewrite->permalink_structure, '%category%' ) ) {
		return;
	}

	// Prévisualisations et brouillons : l'URL de travail n'a pas à être corrigée.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- lecture seule, aucun effet de bord.
	if ( isset( $_GET['preview'] ) || isset( $_GET['preview_id'] ) || isset( $_GET['p'] ) || isset( $_GET['page_id'] ) ) {
		return;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// Pagination d'article et de commentaires : laissées au canonical natif, qui
	// sait reconstruire le suffixe `/2/` ou `/comment-page-2/`.
	if ( get_query_var( 'page' ) || get_query_var( 'cpage' ) ) {
		return;
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return;
	}

	$permalink = get_permalink( $post );
	if ( ! $permalink || is_wp_error( $permalink ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- décomposé/normalisé ci-dessous.
	$current     = _180c_redirect_normalize_path( $request_uri );
	$target      = _180c_redirect_normalize_path( $permalink );

	// Anti-boucle : chemins normalisés identiques => rien à faire. Couvre aussi
	// les 68 articles légitimement canoniques sous `/la-gazette/{slug}/`.
	if ( '' === $target || '/' === $target || $current === $target ) {
		return;
	}

	$query_string = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ré-encodée par wp_safe_redirect.
	$destination  = _180c_redirect_build_target( $permalink, $query_string );

	wp_safe_redirect( $destination, 301 );
	exit;
}
add_action( 'template_redirect', '_180c_redirects_canonical_single', 6 );

/*
 * ---------------------------------------------------------------------------
 * 410 Gone — chemins définitivement supprimés, sans équivalent
 * ---------------------------------------------------------------------------
 *
 * Un 301 suppose une destination pertinente. Pour un contenu supprimé qui n'en
 * a aucune, le 410 est la réponse correcte : Google le traite comme définitif
 * et désindexe nettement plus vite qu'un 404 (qu'il re-crawle longtemps « au
 * cas où »). On réserve donc ce mécanisme aux motifs dont on est certain qu'ils
 * ne reviendront pas.
 */

/**
 * Motifs de chemins servis en 410 Gone.
 *
 * Entrées issues de l'audit Google Search Console du 2026-07-30 : seize
 * sitemaps legacy restaient déclarés dans la console, tous en erreur. Ils
 * étaient générés par un plugin SEO (Yoast / RankMath) désinstallé depuis ;
 * plus rien ne les produit et le sitemap natif `/wp-sitemap.xml` les remplace
 * intégralement. Vérifié en production le 2026-07-31 : les huit chemins testés
 * répondaient déjà 404, ils ne servent donc plus aucun contenu.
 *
 * Le motif couvre `post-sitemap.xml` (sans indice) comme `post-sitemap1.xml` à
 * `post-sitemap10.xml` via `\d*`. Il est ancré sur le chemin complet et ne peut
 * pas atteindre les sitemaps natifs, tous préfixés `wp-sitemap-`.
 *
 * Absent de cette liste, à dessein : `/la-gazette/reportages/lgep-test/`. Le
 * brief de l'ITEM 7 le rangeait parmi les pages de test et demandait un 410. Le
 * relevé du 2026-07-31 dit l'inverse — l'URL sert un article éditorial réel et
 * publié (« La Sélection Engagée de La Grande Épicerie de Paris », 2016-05-28,
 * corps de texte intégral). Seul son slug est fautif : `lgep` abrège le titre et
 * `test` est un nom de travail resté en place. Il est traité par un renommage
 * de slug et une 301 (static map ci-dessus, migration `020-index-cleanup`), et
 * non par une suppression.
 *
 * @return string[] Expressions régulières testées sur le chemin normalisé.
 */
function _180c_redirects_gone_patterns() {
	$patterns = array(
		'#^/(sitemap_index|post-sitemap\d*|category-sitemap|product-sitemap|page-sitemap|post_tag-sitemap)\.xml$#',
	);

	/**
	 * Filtre les motifs de chemins servis en 410 Gone.
	 *
	 * @param string[] $patterns Expressions régulières (chemin normalisé).
	 */
	return (array) apply_filters( '180c/redirects/gone_patterns', $patterns ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Sert un 410 Gone sur les chemins définitivement supprimés.
 *
 * Branché en priorité 4, donc avant le résolveur de redirections (5) : un motif
 * 410 n'a pas à être confronté à la table des 301. La réponse est un corps
 * `text/plain` minimal plutôt que le gabarit 404 du thème (≈ 100 Ko), inutile
 * pour un crawler et coûteux à servir.
 *
 * @return void
 */
function _180c_redirects_maybe_gone() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots() ) {
		return;
	}
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- décomposé/normalisé ci-dessous.
	$path        = _180c_redirect_normalize_path( $request_uri );

	if ( '/' === $path ) {
		return;
	}

	$matched = false;
	foreach ( _180c_redirects_gone_patterns() as $pattern ) {
		if ( preg_match( $pattern, $path ) ) {
			$matched = true;
			break;
		}
	}

	if ( ! $matched ) {
		return;
	}

	status_header( 410 );
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow', true );

	echo "410 Gone\n";
	echo "Ce sitemap a ete supprime avec le plugin qui le generait.\n";
	echo 'Sitemap courant : ' . esc_url_raw( home_url( '/wp-sitemap.xml' ) ) . "\n";
	exit;
}
add_action( 'template_redirect', '_180c_redirects_maybe_gone', 4 );
