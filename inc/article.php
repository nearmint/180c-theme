<?php
/**
 * Article single — template-tags & helpers métier.
 *
 * Article = post natif. Ce module expose les helpers utilisés par
 * `single.php` et les patterns `patterns/article-*.php` pour :
 *  - normaliser le chapô (excerpt manuel) ;
 *  - choisir la catégorie principale (1ʳᵉ assignée, ou feuille la plus
 *    profonde si plusieurs termes forment une hiérarchie) ;
 *  - récupérer le numéro source en réutilisant le champ ACF
 *    `related_product` déjà existant (group_article_relations.json) ;
 *  - construire la requête « À lire aussi » (3 articles même catégorie,
 *    courant exclu, jamais `posts_per_page = -1`).
 *
 * Le rendu effectif vit dans les patterns. Aucune logique SEO/Schema ici :
 * ces deux pipelines sont déjà branchés depuis `inc/seo/*`
 * et étendus par `inc/article-schema.php`.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retourne le chapô d'un article (excerpt manuel) déjà échappé pour esc_html.
 *
 * Source unique : `post_excerpt`. Par décision produit (cf. cahier des charges, H1)
 * il n'y a PAS de champ ACF `article_chapo` — l'excerpt est la seule source.
 *
 * Le helper retourne la chaîne brute (texte propre, sans HTML), prête à être
 * passée à `esc_html()` côté pattern. On ne tronque pas : si l'éditeur veut
 * un chapô court, il le saisit court.
 *
 * @param int $post_id ID de l'article (défaut : courant).
 * @return string Chapô texte brut, ou chaîne vide si pas d'excerpt.
 */
function _180c_article_chapo( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$excerpt = (string) $post->post_excerpt;
	if ( '' === trim( $excerpt ) ) {
		return '';
	}

	return wp_strip_all_tags( $excerpt );
}

/**
 * Retourne la catégorie principale d'un article.
 *
 * Règles (cf. cahier des charges, H3 — pas de plugin SEO « primary category ») :
 *  1. Termes assignés à l'article, hors catégorie par défaut (`uncategorized`).
 *  2. Si plusieurs termes forment une hiérarchie parent → enfant parmi ceux
 *     assignés, on prend le terme **enfant le plus profond** (le plus
 *     spécifique). Cela évite qu'un article rangé dans
 *     « Recettes › Desserts » remonte « Recettes » dans le hero.
 *  3. Sinon, on prend simplement le premier terme assigné.
 *
 * @param int $post_id ID de l'article (défaut : courant).
 * @return WP_Term|null Le terme principal, ou null si aucune catégorie utile.
 */
function _180c_article_primary_category( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id ) {
		return null;
	}

	$terms = get_the_terms( $post_id, 'category' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}

	$default_id = (int) get_option( 'default_category' );

	// Filtre : exclut la catégorie par défaut (Uncategorized / Non classé).
	$filtered = array();
	foreach ( $terms as $term ) {
		if ( $term instanceof WP_Term && (int) $term->term_id !== $default_id ) {
			$filtered[ (int) $term->term_id ] = $term;
		}
	}

	if ( empty( $filtered ) ) {
		// Si l'article n'est rangé que dans la catégorie par défaut, on
		// l'utilise quand même plutôt que de retourner null — c'est mieux
		// que pas de catégorie dans le hero.
		return $terms[0] instanceof WP_Term ? $terms[0] : null;
	}

	// Recherche du terme enfant le plus profond parmi les termes assignés
	// formant une hiérarchie. Pour chaque candidat, on regarde si un AUTRE
	// candidat l'a pour ancêtre — auquel cas on garde plutôt l'enfant.
	$assigned_ids = array_keys( $filtered );
	$deepest      = null;
	$deepest_lvl  = -1;

	foreach ( $filtered as $term ) {
		$ancestors = get_ancestors( (int) $term->term_id, 'category' );
		$ancestors = array_intersect( $ancestors, $assigned_ids );

		$level = count( $ancestors );
		if ( $level > $deepest_lvl ) {
			$deepest_lvl = $level;
			$deepest     = $term;
		}
	}

	if ( $deepest instanceof WP_Term ) {
		return $deepest;
	}

	return reset( $filtered );
}

/**
 * Retourne le produit Woo « source » d'un article (provenance).
 *
 * Réutilise le champ ACF existant `related_product` (cf.
 * `acf-json/group_article_relations.json`, déjà présent à l'initialisation
 * du thème). Ce champ pointe vers le produit dont l'article est issu :
 * depuis la mutualisation du bloc source (feat/article-product-relation),
 * il accepte TOUT produit publié (numéro, livre, cahier…), non plus
 * uniquement la taxonomie `product_cat:numeros`.
 *
 * Décision d'archi : ne PAS créer un champ parallèle
 * `article_source_issue`, mais consommer l'existant. Respecte la règle
 * « Mutualisation MAX » du brief et évite de fragmenter le modèle de
 * contenu (un seul champ = une seule source de vérité côté éditeur).
 *
 * @param int $post_id ID de l'article (défaut : courant).
 * @return WC_Product|null Le produit Woo, ou null si non renseigné /
 *                          Woo absent / produit supprimé.
 */
function _180c_article_source_issue( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id ) {
		return null;
	}

	if ( ! function_exists( 'get_field' ) || ! function_exists( 'wc_get_product' ) ) {
		return null;
	}

	$product_id = (int) get_field( 'related_product', $post_id );
	if ( $product_id < 1 ) {
		return null;
	}

	$product = wc_get_product( $product_id );
	if ( ! $product instanceof WC_Product ) {
		return null;
	}

	return $product;
}

/**
 * Retourne la requête « À lire aussi » (contenus recommandés) pour un article.
 *
 * Pertinence + découvrabilité :
 *  - Pool = articles partageant la catégorie principale OU des tags avec
 *    l'article courant (tax_query relation OR) ;
 *  - Exclut l'article courant ;
 *  - Tri aléatoire (`orderby rand`) : résultats différents à chaque affichage ;
 *  - `no_found_rows` (pas de pagination) ;
 *  - `ignore_sticky_posts` (les stickies n'ont pas à dominer le bloc related) ;
 *  - **Jamais `posts_per_page = -1`** : on plafonne strictement à $count.
 *
 * Repli : si l'article n'a aucun signal de parenté exploitable (ni catégorie
 * principale ni tags) ou si le pool pertinent est vide, on renvoie les articles
 * les plus récents (toujours bornés à $count).
 *
 * @param int $post_id ID de l'article (défaut : courant).
 * @param int $count   Nombre d'articles voulus (1..12, défaut 4).
 * @return WP_Query Requête prête à boucler.
 */
function _180c_article_related( $post_id = 0, $count = 4 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$count   = max( 1, min( 12, (int) $count ) );

	if ( ! $post_id ) {
		// Requête volontairement impossible : WP_Query vide sans hit BDD.
		return new WP_Query( array( 'post__in' => array( 0 ) ) );
	}

	$primary = _180c_article_primary_category( $post_id );
	$tag_ids = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
	$tag_ids = is_wp_error( $tag_ids ) ? array() : $tag_ids;

	$base_args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'post__not_in'        => array( $post_id ),
		'posts_per_page'      => $count,
		'orderby'             => 'rand',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	$tax_query = array( 'relation' => 'OR' );
	if ( $primary instanceof WP_Term ) {
		$tax_query[] = array(
			'taxonomy' => 'category',
			'field'    => 'term_id',
			'terms'    => array( (int) $primary->term_id ),
		);
	}
	if ( ! empty( $tag_ids ) ) {
		$tax_query[] = array(
			'taxonomy' => 'post_tag',
			'field'    => 'term_id',
			'terms'    => array_map( 'intval', $tag_ids ),
		);
	}

	// Pool pertinent (même catégorie principale OU tags partagés).
	if ( count( $tax_query ) > 1 ) {
		$args              = $base_args;
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$query             = new WP_Query( $args );
		if ( $query->have_posts() ) {
			return $query;
		}
	}

	// Repli : articles récents (aucun signal de parenté / pool vide).
	$fallback            = $base_args;
	$fallback['orderby'] = 'date';
	$fallback['order']   = 'DESC';
	return new WP_Query( $fallback );
}

/**
 * Désactive l'auto-insertion du bouton « Favoris » du plugin Favorites
 * (Kyle Phillips, slug `favorites/`) sur les articles natifs uniquement.
 *
 * Le plugin lit `get_option('simplefavorites_display')` dans
 * `SettingsRepository::displayInPostType()` (cf.
 * wp-content/plugins/favorites/app/Config/SettingsRepository.php:165) pour
 * décider s'il append/prepend son markup `<a class="simplefavorite-button">`
 * dans `the_content`. Aucun filter exposé côté plugin.
 *
 * On filtre dynamiquement la valeur de l'option **uniquement** quand
 * `is_singular('post')` afin de :
 *  - retirer le bouton parasite « Ajouter à mon carnet de recettes » qui
 *    apparaissait à tort sur les articles ;
 *  - conserver intact le comportement du plugin sur les recettes (le
 *    « carnet de recettes » légitime côté `single-recipe.php` doit rester
 *    fonctionnel).
 *
 * On hook tardivement (`wp` action) pour garantir que les conditional tags
 * sont résolus, puis on attache le filtre `option_simplefavorites_display`.
 * Aucune modification persistante : la valeur en BDD reste inchangée.
 */
add_action(
	'wp',
	function () {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		add_filter( 'option_simplefavorites_display', '_180c_disable_favorites_on_post', 999 );
	}
);

/**
 * Force le flag `display=false` pour le post type `post` dans l'option
 * `simplefavorites_display` retournée par get_option().
 *
 * Idempotent — si l'option est vide / non-tableau, on la retourne telle quelle.
 *
 * @param mixed $value Valeur brute de l'option `simplefavorites_display`.
 * @return mixed Valeur potentiellement filtrée (cas tableau avec posttypes.post).
 */
function _180c_disable_favorites_on_post( $value ) {
	if ( ! is_array( $value ) || empty( $value['posttypes'] ) || ! is_array( $value['posttypes'] ) ) {
		return $value;
	}
	if ( isset( $value['posttypes']['post'] ) && is_array( $value['posttypes']['post'] ) ) {
		$value['posttypes']['post']['display'] = 'false';
	}
	return $value;
}

/**
 * Indique si un auteur a au moins une recette publiée.
 *
 * Utilisé par la bio article pour décider d'afficher ou non le
 * lien « Voir toutes ses recettes ». Évite une WP_Query : `count_user_posts`
 * lit un cache compté côté core et reste correct si seules les recettes
 * sont concernées.
 *
 * @param int $author_id ID de l'auteur.
 * @return bool True si l'auteur a >= 1 recette publiée (statut publish).
 */
function _180c_author_has_recipes( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return false;
	}

	return (int) count_user_posts( $author_id, 'recipe', true ) > 0;
}
