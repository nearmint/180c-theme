<?php
/**
 * Endpoint REST — composition de la home Recettes pour les apps.
 *
 * Route :
 *   GET /180c/v1/home-recettes  (public, lecture seule)
 *
 * Sérialise, DANS L'ORDRE, la composition du Home Builder (`home_modules`) de la
 * page `/recettes/` : un descripteur JSON léger par module (aucun HTML). Les
 * layouts connus sont mappés vers les types du contrat app ; les autres passent
 * en « passthrough » (type = nom du layout, l'app ignore ce qu'elle ne gère pas).
 *
 * Les rails recette exposent des `recipe_ids` résolus côté serveur (logique
 * mutualisée `_180c_resolve_recipe_rail_ids`) ; l'app hydrate ensuite via
 * `wp/v2/recipe?include={ids}&_embed&orderby=include`.
 *
 * Visibilité : chaque bloc porte sa valeur BRUTE de sous-champ `display`
 * (`web_only` / `app_only` / `web_app`) dans la clé `visibility`. Par défaut la
 * route écarte les modules `web_only`, comme elle écartait naguère les modules
 * `hidden` : les apps déjà en production ne filtrent rien elles-mêmes et
 * afficheraient sinon des modules destinés au seul web. Le paramètre `?all=1`
 * désactive ce filtre et renvoie la composition intégrale — c'est la vue à
 * consommer quand les apps sauront appliquer leur propre règle.
 *
 * Cache : transients `_180c_home_recettes_payload_v3` et
 * `_180c_home_recettes_payload_v3_all` (10 min chacun) — DEUX entrées distinctes,
 * sans quoi le premier appelant figerait sa vue pour l'autre pendant 10 min.
 * Purge des deux au `save_post` de la page `/recettes/`.
 *
 * Le suffixe de version est **volontaire** : il change à chaque modification du
 * comportement de cette route. Un déploiement force ainsi un cache miss immédiat
 * plutôt que de laisser servir jusqu'à 10 min le payload calculé par la version
 * précédente — et rend le déploiement observable de l'extérieur, le `page_id`
 * renvoyé basculant dès la première requête.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

const _180C_HOME_RECETTES_CACHE_KEY = '_180c_home_recettes_payload_v3';

add_action( 'rest_api_init', '_180c_rest_register_home_recettes' );

/**
 * Enregistre la route /home-recettes.
 *
 * @return void
 */
function _180c_rest_register_home_recettes() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/home-recettes',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_get_home_recettes',
			'permission_callback' => '__return_true',
			'args'                => array(
				'all' => array(
					'description'       => __( 'Renvoyer aussi les modules destinés au seul web (visibility = web_only).', '180c' ),
					'type'              => 'boolean',
					'default'           => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
			),
		)
	);
}

/**
 * GET /home-recettes — composition ordonnée de la home Recettes.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response
 */
function _180c_rest_get_home_recettes( WP_REST_Request $request ) {
	$all       = (bool) $request->get_param( 'all' );
	$cache_key = $all ? _180C_HOME_RECETTES_CACHE_KEY . '_all' : _180C_HOME_RECETTES_CACHE_KEY;

	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return rest_ensure_response( $cached );
	}

	$page    = get_page_by_path( 'recettes' );
	$page_id = $page instanceof WP_Post ? (int) $page->ID : 0;

	$payload = array(
		'page_id' => $page_id,
		'blocks'  => array(),
	);

	if ( $page_id && function_exists( 'get_field' ) ) {
		$modules = get_field( 'home_modules', $page_id );
		if ( is_array( $modules ) ) {
			foreach ( $modules as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$block = _180c_home_recettes_block( $row, $all );
				if ( null !== $block ) {
					$payload['blocks'][] = $block;
				}
			}
		}
	}

	set_transient( $cache_key, $payload, 10 * MINUTE_IN_SECONDS );

	return rest_ensure_response( $payload );
}

/**
 * Sérialise un module `home_modules` en descripteur de bloc.
 *
 * @param array $row               Ligne ACF du Flexible Content (sous-champs formatés).
 * @param bool  $include_web_only  Conserver les modules `web_only`. Défaut : false.
 * @return array|null Descripteur, ou null si module écarté / sans layout.
 */
function _180c_home_recettes_block( array $row, $include_web_only = false ) {
	$layout = isset( $row['acf_fc_layout'] ) ? (string) $row['acf_fc_layout'] : '';
	if ( '' === $layout ) {
		return null;
	}

	$visibility = _180c_home_module_visibility( $row['display'] ?? '' );

	// `none` : legacy `hidden`, masqué partout — jamais sérialisé, même en ?all=1.
	if ( 'none' === $visibility ) {
		return null;
	}

	// Masqué : Web uniquement. Levé par ?all=1, chaque plateforme filtrant alors elle-même.
	if ( 'web_only' === $visibility && ! $include_web_only ) {
		return null;
	}

	// Champs communs. `anchor` est null : aucun sous-champ d'ancre dans le modèle.
	$base = array(
		'type'       => $layout,
		'anchor'     => null,
		'title'      => isset( $row['title'] ) ? (string) $row['title'] : '',
		'visibility' => $visibility,
	);

	switch ( $layout ) {
		case 'recipes_rail':
			$target = _180c_recipe_rail_term( $row );
			return array_merge(
				$base,
				array(
					'type'         => 'rail',
					'source'       => ! empty( $row['mode'] ) ? (string) $row['mode'] : 'recent',
					'taxonomy'     => '' !== $target['taxonomy'] ? $target['taxonomy'] : null,
					'term_slug'    => $target['term'] instanceof WP_Term ? $target['term']->slug : null,
					'count'        => (int) ( $row['count'] ?? 0 ) > 0 ? (int) $row['count'] : 8,
					'view_all_url' => _180c_recipe_rail_view_all_url( $row ),
					'recipe_ids'   => _180c_resolve_recipe_rail_ids( $row ),
				)
			);

		case 'recipes_slider':
			/*
			 * Même donnée qu'un rail « récents » — seule la présentation web
			 * diffère (une recette en vedette à la fois). On l'expose donc
			 * comme un `rail`, que les apps savent déjà rendre, plutôt que de
			 * le laisser filer au passthrough avec son seul titre : elles
			 * afficheraient un bloc vide. `variant` reste additif, libre aux
			 * apps de l'ignorer ou d'en faire un carrousel plein écran.
			 *
			 * Aucun rendu conditionnel ici (contrairement à gift_banner) : le
			 * contenu est identique pour tous, il est donc légitime dans le
			 * transient partagé de cette route publique.
			 */
			$slider_count = (int) ( $row['count'] ?? 0 );
			$slider_count = $slider_count > 0 ? $slider_count : 6;
			$slider       = array(
				'mode'  => 'recent',
				'count' => min( _180C_RECIPES_SLIDER_MAX, $slider_count ),
			);
			return array_merge(
				$base,
				array(
					'title'        => '' !== $base['title'] ? $base['title'] : __( 'Les dernières recettes publiées', '180c' ),
					'type'         => 'rail',
					'variant'      => 'slider',
					'source'       => 'recent',
					'taxonomy'     => null,
					'term_slug'    => null,
					'count'        => $slider['count'],
					'view_all_url' => _180c_recipe_rail_view_all_url( $slider ),
					'recipe_ids'   => _180c_resolve_recipe_rail_ids(
						$slider + array( 'exclude_displayed' => ! empty( $row['exclude_displayed'] ) )
					),
				)
			);

		case 'hero_editorial':
			return array_merge(
				$base,
				array(
					'type'       => 'featured',
					'recipe_ids' => _180c_home_recettes_hero_ids( $row ),
				)
			);

		case 'subscription_banner':
			$cta_url = ! empty( $row['cta_url'] ) ? (string) $row['cta_url'] : home_url( '/abonnement/' );
			return array_merge(
				$base,
				array(
					'type'      => 'cta_subscribe',
					'body'      => isset( $row['description'] ) ? (string) $row['description'] : '',
					'cta_text'  => isset( $row['cta_label'] ) ? (string) $row['cta_label'] : '',
					'cta_url'   => $cta_url,
					'login_url' => home_url( '/mon-compte/?redirect_to=' . rawurlencode( home_url( '/recettes/' ) ) ),
				)
			);

		case 'gift_banner':
			/*
			 * Module réservé aux abonnés connectés (parts/modules/gift_banner.php).
			 * La route est publique ET le payload est mis en cache dans un
			 * transient PARTAGÉ : un rendu conditionnel serait figé par le premier
			 * appelant et servi à tout le monde. On l'omet donc purement et
			 * simplement, plutôt que de le laisser filer par le passthrough.
			 * Si les apps doivent un jour proposer le cadeau, cela passera par un
			 * bloc dédié, résolu hors de ce cache.
			 */
			return null;

		case 'category_tiles':
			$taxonomy = isset( $row['taxonomy'] ) ? (string) $row['taxonomy'] : '';
			$count    = (int) ( $row['count'] ?? 0 ) > 0 ? (int) $row['count'] : 8;
			return array_merge(
				$base,
				array(
					'type'     => 'category_tiles',
					'taxonomy' => '' !== $taxonomy ? $taxonomy : null,
					'terms'    => _180c_home_recettes_tiles_terms( $taxonomy, $count ),
				)
			);

		case 'recipe_search':
			return array_merge(
				$base,
				array(
					'type'        => 'search',
					'placeholder' => isset( $row['placeholder'] ) ? (string) $row['placeholder'] : '',
				)
			);

		default:
			// Passthrough : type = nom du layout (l'app ignore ce qu'elle ne gère pas).
			return $base;
	}
}

/**
 * Résout l'ID de recette mis en avant par un module `hero_editorial`.
 *
 * @param array $row Sous-champs du module hero.
 * @return int[] Liste (0 ou 1 ID de recette).
 */
function _180c_home_recettes_hero_ids( array $row ) {
	if ( ! empty( $row['hero_auto_latest_recipe'] ) ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'recipe',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);
		return array_map( 'intval', $query->posts );
	}

	$selected = $row['selected_post'] ?? null;
	$post_id  = is_object( $selected ) ? (int) $selected->ID : (int) $selected;
	if ( $post_id > 0 && 'recipe' === get_post_type( $post_id ) ) {
		return array( $post_id );
	}

	return array();
}

/**
 * Termes d'une taxonomie pour un module `category_tiles`.
 *
 * @param string $taxonomy Clé de taxonomie (valeur stockée du sélecteur).
 * @param int    $count    Nombre de termes (les plus fournis en priorité).
 * @return array<int,array{slug:string,name:string,url:?string,count:int}>
 */
function _180c_home_recettes_tiles_terms( $taxonomy, $count ) {
	if ( '' === (string) $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => max( 1, (int) $count ),
			'hide_empty' => true,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$out = array();
	foreach ( $terms as $term ) {
		$link  = get_term_link( $term, $taxonomy );
		$out[] = array(
			'slug'  => $term->slug,
			'name'  => $term->name,
			'url'   => is_wp_error( $link ) ? null : $link,
			'count' => (int) $term->count,
		);
	}

	return $out;
}

/**
 * Purge le cache du payload home-recettes à l'enregistrement de la page Recettes.
 *
 * Les deux vues (filtrée et `?all=1`) ont leur propre transient : les deux sont
 * purgées, sinon une composition modifiée resterait servie 10 min sur l'une.
 *
 * @param int $post_id ID du post enregistré.
 * @return void
 */
function _180c_home_recettes_flush_cache( $post_id ) {
	$page = get_page_by_path( 'recettes' );
	if ( $page instanceof WP_Post && (int) $page->ID === (int) $post_id ) {
		delete_transient( _180C_HOME_RECETTES_CACHE_KEY );
		delete_transient( _180C_HOME_RECETTES_CACHE_KEY . '_all' );
	}
}
add_action( 'save_post', '_180c_home_recettes_flush_cache' );
