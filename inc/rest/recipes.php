<?php
/**
 * Endpoint REST — liste filtrable des recettes (archive « Toutes les recettes »).
 *
 * Alimente le scroll infini + les filtres dynamiques de l'archive recette
 * (`archive-recipe.php`, post type archive `/toutes-les-recettes/`).
 *
 * Route : GET /wp-json/180c/v1/recipes
 *   ?page=…            (int, 1+)
 *   &search=…          (mot-clé, recherche WP native sur titre + contenu)
 *   &category=1,2,3    (IDs de termes recipe_publication — publications)
 *   &season=4,5        (IDs de termes recipe_season)
 *   &type=6            (IDs de termes recipe_category — types de plat)
 *
 * Retourne : { items: "<HTML des cartes>", has_more: bool, page: int, total: int }
 *
 * La taxonomie `recipe_tag` (étiquettes) est volontairement exclue des filtres.
 * Les helpers de requête/rendu sont mutualisés avec le rendu serveur de la
 * première page (cf. archive-recipe.php) afin que SSR et scroll infini restent
 * strictement alignés.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nombre de recettes par page (SSR initial + chaque lot de scroll infini).
 */
if ( ! defined( '_180C_RECIPES_ARCHIVE_PER_PAGE' ) ) {
	define( '_180C_RECIPES_ARCHIVE_PER_PAGE', 24 );
}

/**
 * Taxonomies filtrables de l'archive recette (étiquettes exclues).
 *
 * Clé = nom du paramètre REST / data-attr JS ; valeur = slug de taxonomie.
 *
 * @return array<string,string> Map paramètre → taxonomie.
 */
function _180c_recipes_archive_filter_taxonomies() {
	return array(
		'category' => 'recipe_publication',
		'season'   => 'recipe_season',
		'type'     => 'recipe_category',
	);
}

/**
 * Convertit une liste d'IDs de termes (chaîne CSV ou tableau) en entiers positifs.
 *
 * @param mixed $raw Valeur brute (« 1,2,3 » ou array).
 * @return int[] IDs assainis, sans doublon.
 */
function _180c_recipes_archive_parse_term_ids( $raw ) {
	if ( is_string( $raw ) ) {
		$raw = explode( ',', $raw );
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$ids = array_filter( array_map( 'absint', $raw ) );

	return array_values( array_unique( $ids ) );
}

/**
 * Construit les arguments WP_Query de l'archive recette pour un jeu de filtres.
 *
 * Mutualisé entre le rendu serveur de la première page et l'endpoint REST.
 * Ordre fixe : date de publication décroissante. La relation entre groupes de
 * taxonomies est AND ; à l'intérieur d'un groupe, l'opérateur IN agit en OR.
 *
 * @param array $filters {
 *     Filtres normalisés.
 *
 *     @type string $search   Mot-clé de recherche.
 *     @type int[]  $category IDs de termes recipe_publication.
 *     @type int[]  $season   IDs de termes recipe_season.
 *     @type int[]  $type     IDs de termes recipe_category.
 * }
 * @param int   $page Page courante (1+).
 * @return array Arguments WP_Query.
 */
function _180c_recipes_archive_query_args( array $filters, $page = 1 ) {
	$args = array(
		'post_type'           => 'recipe',
		'post_status'         => 'publish',
		'posts_per_page'      => _180C_RECIPES_ARCHIVE_PER_PAGE,
		'paged'               => max( 1, (int) $page ),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
	);

	if ( ! empty( $filters['search'] ) ) {
		$args['s'] = (string) $filters['search'];
	}

	$tax_query = array();
	foreach ( _180c_recipes_archive_filter_taxonomies() as $key => $taxonomy ) {
		if ( empty( $filters[ $key ] ) ) {
			continue;
		}
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'term_id',
			'terms'    => array_map( 'intval', (array) $filters[ $key ] ),
			'operator' => 'IN',
		);
	}

	if ( ! empty( $tax_query ) ) {
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	return $args;
}

/**
 * Rend le HTML des cartes recette d'une WP_Query (sans le <li> conteneur).
 *
 * Chaque carte est un <article> ; le JS de scroll infini les enveloppe ensuite
 * dans des <li class="recipes-grid__item"> pour préserver la sémantique de la
 * grille (<ul role="list">), à l'identique du rendu serveur initial.
 *
 * @param WP_Query $query Requête déjà exécutée.
 * @return string HTML concaténé des cartes.
 */
function _180c_recipes_archive_render_cards( WP_Query $query ) {
	if ( ! $query->have_posts() ) {
		return '';
	}

	ob_start();
	while ( $query->have_posts() ) {
		$query->the_post();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le renderer.
		echo _180c_render_recipe_card( get_the_ID(), 'md' );
	}
	wp_reset_postdata();

	return (string) ob_get_clean();
}

/**
 * Enregistre la route REST « recipes ».
 *
 * @return void
 */
function _180c_rest_register_recipes() {
	$term_ids_sanitizer = '_180c_recipes_archive_parse_term_ids';

	register_rest_route(
		_180C_API_NAMESPACE,
		'/recipes',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_get_recipes',
			'permission_callback' => '__return_true',
			'args'                => array(
				'page'     => array(
					'required'          => false,
					'type'              => 'integer',

					/*
					 * `validate_callback` explicite : déclarer un
					 * `sanitize_callback` prive l'argument du
					 * `rest_parse_request_arg` que le cœur assigne par défaut
					 * (class-wp-rest-request.php:858-861), et c'est lui qui
					 * applique le schéma. Sans cette ligne, `page=0` repartait en
					 * 200, rattrapé par le `max( 1, … )` du callback.
					 *
					 * Le seul appelant, recipes-archive.js:99, envoie toujours un
					 * numéro de page ≥ 1.
					 */
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'absint',
					'minimum'           => 1,
					'default'           => 1,
				),
				'search'   => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				),
				'category' => array(
					'required'          => false,
					'sanitize_callback' => $term_ids_sanitizer,
					'default'           => array(),
				),
				'season'   => array(
					'required'          => false,
					'sanitize_callback' => $term_ids_sanitizer,
					'default'           => array(),
				),
				'type'     => array(
					'required'          => false,
					'sanitize_callback' => $term_ids_sanitizer,
					'default'           => array(),
				),
			),
		)
	);
}
add_action( 'rest_api_init', '_180c_rest_register_recipes' );

/**
 * Callback REST : renvoie le HTML des cartes pour une page + jeu de filtres.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response
 */
function _180c_rest_get_recipes( WP_REST_Request $request ) {
	// Filet : la validation REST est en amont (validate_callback).
	$page = max( 1, absint( $request->get_param( 'page' ) ) );

	$filters = array(
		'search'   => (string) $request->get_param( 'search' ),
		'category' => (array) $request->get_param( 'category' ),
		'season'   => (array) $request->get_param( 'season' ),
		'type'     => (array) $request->get_param( 'type' ),
	);

	$query = new WP_Query( _180c_recipes_archive_query_args( $filters, $page ) );

	$html     = _180c_recipes_archive_render_cards( $query );
	$has_more = $page < (int) $query->max_num_pages;

	return rest_ensure_response(
		array(
			'items'    => $html,
			'has_more' => $has_more,
			'page'     => $page,
			'total'    => (int) $query->found_posts,
		)
	);
}
