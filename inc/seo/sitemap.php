<?php
/**
 * Configuration du sitemap WP natif (WP 5.5+).
 *
 * Le sitemap XML est accessible sur /wp-sitemap.xml.
 * On filtre les types de contenu, exclut les pages système
 * et génère un robots.txt dynamique.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/*
 * 1. Post types autorisés dans le sitemap
 */

/**
 * Restreint le sitemap aux CPT pertinents pour le SEO.
 *
 * @param WP_Post_Type[] $post_types Tableau indexé par slug.
 * @return WP_Post_Type[]
 */
add_filter(
	'wp_sitemaps_post_types',
	function ( $post_types ) {
		$allowed = array( 'post', 'page', 'recipe', 'product' );
		foreach ( array_keys( $post_types ) as $type ) {
			if ( ! in_array( $type, $allowed, true ) ) {
				unset( $post_types[ $type ] );
			}
		}
		return $post_types;
	}
);

/*
 * 2. Taxonomies autorisées dans le sitemap
 */

/**
 * Restreint le sitemap aux taxonomies publiques pertinentes.
 *
 * `post_tag` est volontairement absent depuis l'audit GSC du 2026-07-30 : la
 * taxonomie ne compte que 6 termes en production, hérités de l'ancien site et
 * jamais réinvestis éditorialement. Leurs archives n'apportent aucun contenu
 * propre (simple liste d'articles déjà couverts par `category`) et diluaient le
 * budget de crawl. Les articles concernés restent servis par
 * `wp-sitemap-posts-post-*.xml` : seules les pages d'archive `/tag/{slug}/`
 * sortent du sitemap. `recipe_tag` est conservé — cette taxonomie-là est
 * alimentée et sert de navigation réelle sur le CPT `recipe`.
 *
 * @param WP_Taxonomy[] $taxonomies Tableau indexé par slug.
 * @return WP_Taxonomy[]
 */
add_filter(
	'wp_sitemaps_taxonomies',
	function ( $taxonomies ) {
		$allowed = array( 'category', 'recipe_category', 'recipe_season', 'recipe_publication', 'recipe_tag' );
		foreach ( array_keys( $taxonomies ) as $tax ) {
			if ( ! in_array( $tax, $allowed, true ) ) {
				unset( $taxonomies[ $tax ] );
			}
		}
		return $taxonomies;
	}
);

/*
 * 3. Exclusion des pages système (Mon Compte, panier, etc.)
 */

/**
 * Retourne les IDs des pages à exclure du sitemap.
 *
 * Liste des slugs système : Mon Compte, Panier, Commande,
 * Connexion, Inscription, Mot de passe oublié, Cookies, Téléchargement app.
 *
 * `app` doit figurer ici explicitement : la seule autre exclusion du sitemap XML
 * (section 4, filtre `wp_sitemaps_posts_entry`) teste le champ ACF `no_index`,
 * supprimé avec son groupe — elle ne lit pas `_180c_seo_is_noindex_post()`. Le
 * plan du site HTML, lui, dérive déjà du `noindex` du thème
 * (`_180c_sitemap_is_page_indexable()`, inc/sitemap-page.php).
 *
 * @return int[] Tableau d'IDs de pages.
 */
function _180c_seo_excluded_page_ids() {
	static $ids = null;

	if ( null !== $ids ) {
		return $ids;
	}

	/**
	 * Filtre la liste des slugs de pages à exclure du sitemap.
	 *
	 * @param string[] $slugs Slugs des pages système.
	 */
	$slugs = apply_filters(
		'180c/seo_excluded_page_slugs',
		array(
			'mon-compte',
			'panier',
			'commande',
			'connexion',
			'inscription',
			'mot-de-passe-oublie',
			'cookies',
			'app',
		)
	);

	$ids = array();
	foreach ( $slugs as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post ) {
			$ids[] = $page->ID;
		}
	}

	// WooCommerce : pages configurées en BDD (panier, commande, Mon Compte).
	if ( function_exists( 'wc_get_page_id' ) ) {
		$woo_pages = array( 'cart', 'checkout', 'myaccount' );
		foreach ( $woo_pages as $woo_page ) {
			$woo_id = (int) wc_get_page_id( $woo_page );
			if ( $woo_id > 0 ) {
				$ids[] = $woo_id;
			}
		}
	}

	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

	return $ids;
}

/**
 * Exclut les pages système des requêtes sitemap.
 *
 * @param array  $args      Arguments WP_Query du sitemap.
 * @param string $post_type Slug du post type.
 * @return array
 */
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$excluded = _180c_seo_excluded_page_ids();
			if ( ! empty( $excluded ) ) {
				$existing             = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
				$args['post__not_in'] = array_values( array_unique( array_merge( $existing, $excluded ) ) );
			}
		}
		return $args;
	},
	10,
	2
);

/*
 * 4. Exclure les posts marqués no_index du sitemap
 */

/**
 * Filtre les entrées du sitemap pour exclure celles marquées no_index via ACF.
 *
 * Utilise wp_sitemaps_posts_entry afin de ne pas alourdir
 * les queries — le filtre s'applique entrée par entrée.
 *
 * @param array   $entry    Données de l'entrée.
 * @param WP_Post $post     Objet post.
 * @return array|false Retourne false pour exclure l'entrée.
 */
add_filter(
	'wp_sitemaps_posts_entry',
	function ( $entry, $post ) {
		if ( function_exists( 'get_field' ) ) {
			$no_index = (bool) get_field( 'no_index', $post->ID );
			if ( $no_index ) {
				return false;
			}
		}
		return $entry;
	},
	10,
	2
);

/*
 * 5. Suppression du provider users du sitemap (CH-09 / SEC1)
 *
 * Le sitemap users de WordPress (wp-sitemap-users-1.xml) liste tous les
 * utilisateurs ayant publié au moins un post : c'est un vecteur d'énumération
 * des comptes. On retire entièrement le provider `users` plutôt que de filtrer
 * les comptes au cas par cas (l'ancien filtre wp_sitemaps_users_query_args, qui
 * n'excluait que les comptes author_public=0, est remplacé par cette
 * suppression complète). Les pages auteur publiques restent servies
 * normalement — elles ne dépendent pas du sitemap des users.
 */

add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		if ( 'users' === $name ) {
			return false;
		}
		return $provider;
	},
	10,
	2
);

/*
 * 6. robots.txt dynamique (durci — WooCommerce + blocage crawlers IA)
 */

/**
 * Retourne le ruleset robots.txt complet de production.
 *
 * Couvre le durcissement WooCommerce (URLs panier / compte / commande,
 * paramètres de filtrage et d'ajout au panier), le blocage des crawlers
 * d'entraînement de modèles IA et des agents de réponse / recherche, puis
 * la ligne Sitemap. Cette dernière est omise si un plugin SEO (Yoast ou
 * RankMath) est actif, ces plugins injectant déjà leur propre directive
 * Sitemap dans robots.txt (on évite ainsi la duplication).
 *
 * @return string Contenu complet du robots.txt de production.
 */
function _180c_robots_rules() {
	$rules = <<<'ROBOTS'
# robots.txt — 180c.fr — généré par 180c-theme
User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php
Disallow: /wp-login.php
Disallow: /xmlrpc.php
Disallow: /mon-compte/
Disallow: /panier/
Disallow: /commande/
Disallow: /connexion/
Disallow: /inscription/
Disallow: /mot-de-passe-oublie/
Disallow: /*add-to-cart=
Disallow: /*?wc-ajax=
Disallow: /*?add_to_wishlist=
Disallow: /*?removed_item=
Disallow: /?s=
Disallow: /*?s=
Disallow: /*?orderby=
Disallow: /*?filter_
Disallow: /*?min_price=
Disallow: /*?max_price=
Disallow: /*?replytocom=
Disallow: /trackback/
Disallow: /*/trackback/
Allow: /wp-content/uploads/
Allow: /*.css$
Allow: /*.js$

# ==================================================================
# POLITIQUE IA — entraînement refusé, réponse à la demande autorisée
#
# 180°C est un éditeur payant. On distingue deux usages :
#   - ENTRAÎNEMENT de modèles sur le corpus : refusé (groupes ci-dessous) ;
#   - CITATION dans une réponse déclenchée par un lecteur : autorisée, c'est
#     du trafic et de la notoriété.
#
# Les moteurs de réponse (OAI-SearchBot, ChatGPT-User, Claude-User,
# Claude-SearchBot, PerplexityBot, Perplexity-User, Applebot, DuckAssist)
# ne sont VOLONTAIREMENT pas listés, et il ne faut pas les ajouter.
#
# RFC 9309 § 2.2.1 : un robot n'applique QU'UN SEUL groupe, le plus
# spécifique qui corresponde à son jeton produit ; tous les autres, y
# compris « User-agent: * », sont ignorés. Écrire « User-agent: PerplexityBot
# / Allow: / » ferait donc SORTIR PerplexityBot du groupe « * » et cesser
# de respecter les vingt Disallow de durcissement WooCommerce ci-dessus.
# Ce serait une régression, pas une autorisation : sans groupe dédié, ces
# agents parcourent déjà tout ce qui n'est pas verrouillé.
#
# Rappel : robots.txt est un signal, pas une barrière. Son respect dépend
# entièrement du crawler. Ne pas le présenter comme une protection.
#
# Politique validée le 16/07/2026, révisée le 03/08/2026. À revoir chaque
# trimestre.
# ==================================================================

# Jetons d'exclusion d'entraînement (sans effet sur le référencement)
#
# Google-Extended — NON BLOQUÉ, arbitrage du 03/08/2026.
# Ce jeton gouverne l'usage du contenu pour l'entraînement de Gemini et pour
# le grounding des applications Gemini. D'après la documentation Google, il
# n'a AUCUN effet sur l'inclusion dans la recherche ni sur les AI Overviews,
# qui s'appuient sur l'index de recherche classique — le coût d'un blocage
# est donc plus faible qu'on ne le lit souvent, mais le bénéfice d'un
# déblocage l'est aussi. Décision retenue : rester visible des surfaces
# Gemini, quitte à alimenter leur entraînement.
# Pour refuser l'entraînement Gemini, décommenter les deux lignes suivantes :
# User-agent: Google-Extended
# Disallow: /

User-agent: Applebot-Extended
Disallow: /

# Robots d'entraînement
User-agent: GPTBot
Disallow: /

User-agent: ClaudeBot
Disallow: /

User-agent: anthropic-ai
Disallow: /

User-agent: CCBot
Disallow: /

User-agent: Meta-ExternalAgent
Disallow: /

User-agent: FacebookBot
Disallow: /

User-agent: Amazonbot
Disallow: /

User-agent: cohere-ai
Disallow: /

User-agent: Bytespider
Disallow: /

User-agent: Diffbot
Disallow: /

User-agent: omgilibot
Disallow: /

User-agent: ImagesiftBot
Disallow: /

User-agent: Timpibot
Disallow: /

User-agent: YouBot
Disallow: /
ROBOTS;

	// Sitemap : déléguée à un éventuel plugin SEO actif pour éviter le doublon.
	$sitemap = home_url( '/wp-sitemap.xml' );
	if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) {
		$sitemap = null;
	}

	if ( null !== $sitemap ) {
		$rules .= "\n\nSitemap: " . esc_url( $sitemap ) . "\n";
	} else {
		$rules .= "\n";
	}

	return $rules;
}

/**
 * Filtre le contenu de robots.txt servi par WordPress.
 *
 * Hors production (dev / staging / local), renvoie un verrou complet
 * « Disallow: / » pour empêcher toute indexation des environnements de
 * travail. En production, renvoie le ruleset durci complet.
 *
 * @param string $output Contenu robots.txt courant (remplacé intégralement).
 * @param bool   $public Le site est-il public (option blog_public).
 * @return string
 */
function _180c_robots_txt( $output, $public ) {
	if ( ! $public || 'production' !== wp_get_environment_type() ) {
		return "User-agent: *\nDisallow: /\n";
	}

	return _180c_robots_rules();
}
add_filter( 'robots_txt', '_180c_robots_txt', 10, 2 );
