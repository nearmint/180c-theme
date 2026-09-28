<?php
/**
 * Plan du site HTML (/plan-du-site/) — data layer dynamique.
 *
 * Construit l'arbre du plan du site (pages indexables, hubs d'archives, termes
 * de taxonomies non vides, contributeurs publics) consommé par le template
 * `page-plan-du-site.php`. 100 % dynamique : aucune liste en dur.
 *
 * Complément HTML du sitemap XML natif (inc/seo/sitemap.php) : maillage interne
 * + découverte humaine. On ne liste donc PAS les recettes/produits un par un —
 * ce sont les hubs taxonomiques qui portent le maillage profond.
 *
 * Toute la construction lourde passe par un transient (12 h) busté sur les
 * modifications de contenu / termes / profils.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clé du transient de cache de l'arbre du plan du site.
 */
const _180C_SITEMAP_CACHE_KEY = '_180c_sitemap_cache';

/*
 * 1. Point d'entrée caché
 */

/**
 * Retourne l'arbre du plan du site, depuis le cache si disponible.
 *
 * @return array{
 *   pages: array<int,array{title:string,url:string}>,
 *   sections: array<int,array{label:string, hub:?array{title:string,url:string}, groups:array<int,array{label:string, items:array<int,array{title:string,url:string}>}>}>,
 *   authors: array<int,array{title:string,url:string}>
 * }
 */
function _180c_get_sitemap_data(): array {
	$cached = get_transient( _180C_SITEMAP_CACHE_KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$data = _180c_build_sitemap_data();
	set_transient( _180C_SITEMAP_CACHE_KEY, $data, 12 * HOUR_IN_SECONDS );

	return $data;
}

/*
 * 2. Construction de l'arbre
 */

/**
 * Construit l'arbre complet (non caché). Réservé à _180c_get_sitemap_data().
 *
 * @return array Arbre structuré (cf. _180c_get_sitemap_data()).
 */
function _180c_build_sitemap_data(): array {
	$front_id   = (int) get_option( 'page_on_front' );
	$posts_page = (int) get_option( 'page_for_posts' );
	// _180c_shop_page_id() ignore l'option WooCommerce tant qu'elle pointe sur
	// un brouillon, et retombe sur la page « boutique » publiée.
	$shop_id = _180c_shop_page_id();

	/*
	 * Hubs — pôles d'IA. Chaque hub possède une URL résolue dynamiquement.
	 * Les pages WP servant de hub (page des articles, boutique) sont collectées
	 * dans $hub_ids pour ne pas réapparaître dans la liste « Pages principales ».
	 */
	$hub_ids = array_values(
		array_filter(
			array( $front_id, $posts_page, $shop_id ),
			static function ( $id ) {
				return $id > 0;
			}
		)
	);

	// Hub La Gazette : page des articles si définie, sinon front page.
	$gazette_hub = null;
	if ( $posts_page > 0 ) {
		$gazette_hub = array(
			'title' => get_the_title( $posts_page ),
			'url'   => get_permalink( $posts_page ),
		);
	} elseif ( $front_id > 0 ) {
		$gazette_hub = array(
			'title' => get_the_title( $front_id ),
			'url'   => get_permalink( $front_id ),
		);
	}

	// Hub Recettes : archive du CPT `recipe` (has_archive => 'toutes-les-recettes').
	$recipe_archive = get_post_type_archive_link( 'recipe' );
	$recipe_hub     = $recipe_archive
		? array(
			'title' => __( 'Toutes les recettes', '180c' ),
			'url'   => $recipe_archive,
		)
		: null;

	// Hub Boutique : page boutique WooCommerce.
	$shop_hub = null;
	if ( $shop_id > 0 ) {
		$shop_hub = array(
			'title' => get_the_title( $shop_id ),
			'url'   => get_permalink( $shop_id ),
		);
	}

	/*
	 * Sections — un pôle d'IA par entrée. Une section vide (sans hub ni groupe)
	 * est écartée pour ne jamais rendre de bloc orphelin.
	 */
	$sections = array();

	$sections[] = array(
		'label'  => __( 'La Gazette', '180c' ),
		'hub'    => $gazette_hub,
		'groups' => array_values(
			array_filter( array( _180c_sitemap_term_group( 'category' ) ) )
		),
	);

	$sections[] = array(
		'label'  => __( 'Recettes', '180c' ),
		'hub'    => $recipe_hub,
		'groups' => array_values(
			array_filter(
				array(
					_180c_sitemap_term_group( 'recipe_category' ),
					_180c_sitemap_term_group( 'recipe_season' ),
					_180c_sitemap_term_group( 'recipe_publication' ),
				)
			)
		),
	);

	// Boutique : uniquement si WooCommerce est actif.
	if ( function_exists( 'wc_get_page_id' ) ) {
		$sections[] = array(
			'label'  => __( 'Boutique', '180c' ),
			'hub'    => $shop_hub,
			'groups' => array_values(
				array_filter(
					array(
						_180c_sitemap_term_group( 'product_cat' ),
						_180c_sitemap_term_group( 'product_brand' ),
					)
				)
			),
		);
	}

	// On ne garde que les sections porteuses d'au moins un lien.
	$sections = array_values(
		array_filter(
			$sections,
			static function ( $section ) {
				return ! empty( $section['hub'] ) || ! empty( $section['groups'] );
			}
		)
	);

	return array(
		'pages'    => _180c_sitemap_pages( $hub_ids ),
		'sections' => $sections,
		'authors'  => _180c_sitemap_authors(),
	);
}

/*
 * 3. Pages principales
 */

/**
 * Retourne les pages WP publiées indexables, hors hubs déjà rendus.
 *
 * Tri : menu_order croissant puis titre. Dédoublonnage des hubs via $hub_ids.
 *
 * @param int[] $hub_ids IDs de pages servant de hub (à exclure de la liste).
 * @return array<int,array{title:string,url:string}>
 */
function _180c_sitemap_pages( array $hub_ids = array() ): array {
	$pages = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'suppress_filters' => false,
		)
	);

	$out = array();
	foreach ( $pages as $page ) {
		if ( in_array( (int) $page->ID, $hub_ids, true ) ) {
			continue;
		}
		if ( ! _180c_sitemap_is_page_indexable( (int) $page->ID ) ) {
			continue;
		}
		$out[] = array(
			'title' => get_the_title( $page ),
			'url'   => get_permalink( $page ),
		);
	}

	return $out;
}

/**
 * Détermine si une page doit figurer dans le plan du site (indexable + public).
 *
 * Exclut : statut non publié, page protégée par mot de passe, pages
 * fonctionnelles WooCommerce (panier/commande/compte, via la liste partagée
 * du sitemap XML), la page plan-du-site elle-même, et toute page que le thème
 * sert en `noindex` (cf. `_180c_seo_is_noindex_post()`, inc/seo/meta-tags.php).
 *
 * @param int $page_id ID de la page.
 * @return bool
 */
function _180c_sitemap_is_page_indexable( int $page_id ): bool {
	$page_id = absint( $page_id );
	if ( ! $page_id ) {
		return false;
	}

	$post = get_post( $page_id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return false;
	}

	// Page protégée par mot de passe : contenu non public.
	if ( '' !== (string) $post->post_password ) {
		return false;
	}

	// Filet supplémentaire : visibilité publique effective (WP 5.7+).
	if ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $post ) ) {
		return false;
	}

	// Pages fonctionnelles WooCommerce + pages système (liste partagée).
	if ( function_exists( '_180c_seo_excluded_page_ids' ) ) {
		if ( in_array( $page_id, _180c_seo_excluded_page_ids(), true ) ) {
			return false;
		}
	}

	// La page plan-du-site ne se liste pas elle-même.
	if ( 'plan-du-site' === $post->post_name ) {
		return false;
	}

	// noindex décidé par le thème. Une seule source : la fonction qui pose la
	// meta robots décide aussi de la présence au plan du site. Le champ ACF
	// `no_index` qui pilotait cette règle a été supprimé avec le groupe ACF.
	if ( function_exists( '_180c_seo_is_noindex_post' ) && _180c_seo_is_noindex_post( $page_id ) ) {
		return false;
	}

	return true;
}

/*
 * 4. Groupes de termes
 */

/**
 * Construit un groupe de liens à partir des termes non vides d'une taxonomie.
 *
 * Le libellé du groupe est le label de la taxonomie. Les termes dont
 * `get_term_link()` échoue sont ignorés. Retourne null si la taxonomie
 * n'existe pas ou ne contient aucun terme non vide exploitable.
 *
 * @param string $taxonomy Slug de la taxonomie.
 * @return array{label:string, items:array<int,array{title:string,url:string}>}|null
 */
function _180c_sitemap_term_group( string $taxonomy ): ?array {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return null;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return null;
	}

	$items = array();
	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$items[] = array(
			'title' => $term->name,
			'url'   => $link,
		);
	}

	if ( empty( $items ) ) {
		return null;
	}

	$tax   = get_taxonomy( $taxonomy );
	$label = ( $tax && isset( $tax->labels->name ) ) ? (string) $tax->labels->name : $taxonomy;

	return array(
		'label' => $label,
		'items' => $items,
	);
}

/*
 * 5. Contributeurs publics
 */

/**
 * Retourne les contributeurs publics ayant publié du contenu.
 *
 * Critères : avoir publié au moins un `post` ou une `recipe` ET être marqué
 * public (toggle ACF `author_public`, opt-out par défaut — cf.
 * _180c_author_is_public()). Tri alphabétique sur le nom affiché.
 *
 * @return array<int,array{title:string,url:string}>
 */
function _180c_sitemap_authors(): array {
	$users = get_users(
		array(
			'has_published_posts' => array( 'post', 'recipe' ),
			'fields'              => array( 'ID', 'display_name' ),
		)
	);

	$out = array();
	foreach ( $users as $user ) {
		$user_id = (int) $user->ID;

		if ( function_exists( '_180c_author_is_public' ) && ! _180c_author_is_public( $user_id ) ) {
			continue;
		}

		$name = function_exists( '_180c_author_display_name' )
			? _180c_author_display_name( $user_id )
			: '';
		if ( '' === $name ) {
			$name = (string) $user->display_name;
		}

		$out[] = array(
			'title' => $name,
			'url'   => get_author_posts_url( $user_id ),
		);
	}

	usort(
		$out,
		static function ( $a, $b ) {
			return strnatcasecmp( $a['title'], $b['title'] );
		}
	);

	return $out;
}

/*
 * 6. Invalidation du cache
 */

/**
 * Supprime le transient de l'arbre du plan du site.
 *
 * @return void
 */
function _180c_flush_sitemap_cache() {
	delete_transient( _180C_SITEMAP_CACHE_KEY );
}

add_action( 'save_post', '_180c_flush_sitemap_cache' );
add_action( 'deleted_post', '_180c_flush_sitemap_cache' );
add_action( 'edited_term', '_180c_flush_sitemap_cache' );
add_action( 'created_term', '_180c_flush_sitemap_cache' );
add_action( 'deleted_term', '_180c_flush_sitemap_cache' );
add_action( 'profile_update', '_180c_flush_sitemap_cache' );
add_action( 'updated_user_meta', '_180c_flush_sitemap_cache' );
