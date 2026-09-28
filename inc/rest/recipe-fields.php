<?php
/**
 * Exposition REST des champs ACF de la recette.
 *
 * Les champs du groupe ACF « recipe_fields » sont exposés en lecture sur le
 * endpoint natif /wp-json/wp/v2/recipe/{id} via register_rest_field(). Couplé à
 * ?_embed, les apps iOS/Android récupèrent post + media + taxonomies + champs
 * structurés en une seule requête.
 *
 * Rappel WP : _embed et _fields sont incompatibles ; côté apps, toujours _embed.
 *
 * Endpoint custom 180c/v1/recipes : hors scope (init dédiée à venir).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre les champs ACF de la recette dans la réponse REST native.
 *
 * Chaque champ est en lecture seule (pas d'update_callback) : l'édition passe
 * par l'interface ACF en wp-admin. Les valeurs sont normalisées pour offrir
 * une structure stable et typée aux consommateurs (apps, front).
 *
 * @return void
 */
function _180c_register_recipe_rest_fields() {
	if ( ! function_exists( 'get_field' ) ) {
		return;
	}

	$view_edit = array( 'view', 'edit' );

	// recipe_intro — texte d'introduction.
	register_rest_field(
		'recipe',
		'recipe_intro',
		array(
			'get_callback' => '_180c_rest_recipe_intro',
			'schema'       => array(
				'description' => __( 'Introduction éditoriale de la recette.', '180c' ),
				'type'        => 'string',
				'context'     => $view_edit,
			),
		)
	);

	// servings — nombre de portions.
	register_rest_field(
		'recipe',
		'servings',
		array(
			'get_callback' => '_180c_rest_recipe_servings',
			'schema'       => array(
				'description' => __( 'Nombre de portions.', '180c' ),
				'type'        => array( 'integer', 'null' ),
				'context'     => $view_edit,
			),
		)
	);

	// servings_unit — unité de portion.
	register_rest_field(
		'recipe',
		'servings_unit',
		array(
			'get_callback' => '_180c_rest_recipe_servings_unit',
			'schema'       => array(
				'description' => __( 'Unité associée au nombre de portions (ex. « personnes »).', '180c' ),
				'type'        => 'string',
				'context'     => $view_edit,
			),
		)
	);

	// recipe_is_premium — flag éditorial.
	register_rest_field(
		'recipe',
		'recipe_is_premium',
		array(
			'get_callback' => '_180c_rest_recipe_is_premium',
			'schema'       => array(
				'description' => __( 'Drapeau éditorial « recette premium ». Le paywall front reste piloté par class_list.', '180c' ),
				'type'        => 'boolean',
				'context'     => $view_edit,
			),
		)
	);

	// recipe_locked — contenu premium verrouillé pour l'utilisateur courant.
	register_rest_field(
		'recipe',
		'recipe_locked',
		array(
			'get_callback' => '_180c_rest_recipe_locked',
			'schema'       => array(
				'description' => __( 'Vrai si le contenu premium est masqué pour l\'utilisateur courant (paywall serveur).', '180c' ),
				'type'        => 'boolean',
				'context'     => $view_edit,
			),
		)
	);

	// ingredients_groups — repeater de groupes d'ingrédients.
	register_rest_field(
		'recipe',
		'ingredients_groups',
		array(
			'get_callback' => '_180c_rest_recipe_ingredients_groups',
			'schema'       => array(
				'description' => __( 'Groupes d\'ingrédients, chacun avec un libellé optionnel et des lignes brutes.', '180c' ),
				'type'        => 'array',
				'context'     => $view_edit,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'group_label' => array( 'type' => 'string' ),
						'items'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'line' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
			),
		)
	);

	// steps — repeater d'étapes.
	register_rest_field(
		'recipe',
		'steps',
		array(
			'get_callback' => '_180c_rest_recipe_steps',
			'schema'       => array(
				'description' => __( 'Étapes de préparation (titre optionnel + contenu HTML).', '180c' ),
				'type'        => 'array',
				'context'     => $view_edit,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'step_title'   => array( 'type' => 'string' ),
						'step_content' => array( 'type' => 'string' ),
					),
				),
			),
		)
	);

	// source_issue — produit WooCommerce (numéro papier d'origine).
	register_rest_field(
		'recipe',
		'source_issue',
		array(
			'get_callback' => '_180c_rest_recipe_source_issue',
			'schema'       => array(
				'description' => __( 'ID du produit WooCommerce (numéro papier) dont la recette est issue.', '180c' ),
				'type'        => array( 'integer', 'null' ),
				'context'     => $view_edit,
			),
		)
	);
}
add_action( 'rest_api_init', '_180c_register_recipe_rest_fields', 10 );

/**
 * Callback REST — recipe_intro.
 *
 * @param array $post_data Données du post REST (clé « id »).
 * @return string
 */
function _180c_rest_recipe_intro( $post_data ) {
	return (string) get_field( 'recipe_intro', $post_data['id'] );
}

/**
 * Callback REST — servings.
 *
 * @param array $post_data Données du post REST.
 * @return int|null
 */
function _180c_rest_recipe_servings( $post_data ) {
	$value = get_field( 'servings', $post_data['id'] );
	return ( '' === $value || null === $value ) ? null : (int) $value;
}

/**
 * Callback REST — servings_unit.
 *
 * @param array $post_data Données du post REST.
 * @return string
 */
function _180c_rest_recipe_servings_unit( $post_data ) {
	return (string) get_field( 'servings_unit', $post_data['id'] );
}

/**
 * Callback REST — recipe_is_premium.
 *
 * @param array $post_data Données du post REST.
 * @return bool
 */
function _180c_rest_recipe_is_premium( $post_data ) {
	return (bool) get_field( 'recipe_is_premium', $post_data['id'] );
}

/**
 * Callback REST — ingredients_groups (structure normalisée).
 *
 * @param array $post_data Données du post REST.
 * @return array
 */
function _180c_rest_recipe_ingredients_groups( $post_data ) {
	// Gating serveur : aucun ingrédient transmis si le contenu est verrouillé.
	if ( _180c_recipe_rest_is_locked( $post_data['id'] ) ) {
		return array();
	}

	$groups = get_field( 'ingredients_groups', $post_data['id'] );
	$out    = array();

	if ( ! is_array( $groups ) ) {
		return $out;
	}

	foreach ( $groups as $group ) {
		$lines = array();

		if ( ! empty( $group['items'] ) && is_array( $group['items'] ) ) {
			foreach ( $group['items'] as $item ) {
				$line = isset( $item['line'] ) ? trim( (string) $item['line'] ) : '';
				if ( '' !== $line ) {
					$lines[] = array( 'line' => $line );
				}
			}
		}

		$out[] = array(
			'group_label' => isset( $group['group_label'] ) ? (string) $group['group_label'] : '',
			'items'       => $lines,
		);
	}

	return $out;
}

/**
 * Callback REST — steps (structure normalisée).
 *
 * @param array $post_data Données du post REST.
 * @return array
 */
function _180c_rest_recipe_steps( $post_data ) {
	// Gating serveur : aucune étape transmise si le contenu est verrouillé.
	if ( _180c_recipe_rest_is_locked( $post_data['id'] ) ) {
		return array();
	}

	$steps = get_field( 'steps', $post_data['id'] );
	$out   = array();

	if ( ! is_array( $steps ) ) {
		return $out;
	}

	foreach ( $steps as $step ) {
		$out[] = array(
			'step_title'   => isset( $step['step_title'] ) ? (string) $step['step_title'] : '',
			'step_content' => isset( $step['step_content'] ) ? (string) $step['step_content'] : '',
		);
	}

	return $out;
}

/**
 * Callback REST — source_issue (ID produit WooCommerce).
 *
 * @param array $post_data Données du post REST.
 * @return int|null
 */
function _180c_rest_recipe_source_issue( $post_data ) {
	$value = get_field( 'source_issue', $post_data['id'] );

	if ( is_array( $value ) ) {
		$value = reset( $value );
	}

	$value = (int) $value;

	return $value > 0 ? $value : null;
}

/**
 * Callback REST — recipe_locked.
 *
 * @param array $post_data Données du post REST.
 * @return bool
 */
function _180c_rest_recipe_locked( $post_data ) {
	return _180c_recipe_rest_is_locked( $post_data['id'] );
}

/**
 * Filtre rest_prepare_recipe : tronque content.rendered des recettes verrouillées.
 *
 * Calque le gating article (inc/article-access.php) pour les recettes : un
 * non-abonné ne reçoit jamais le corps Gutenberg complet d'une recette premium,
 * seulement l'intro (teaser) + un lien d'abonnement. Couplé au gating des champs
 * ACF (ingrédients / étapes), le contenu premium n'est jamais transmis en REST.
 *
 * @param WP_REST_Response $response Réponse REST.
 * @param WP_Post          $post     Recette.
 * @return WP_REST_Response
 */
function _180c_gate_recipe_rest( $response, $post ) {
	if ( ! $post instanceof WP_Post || 'recipe' !== $post->post_type ) {
		return $response;
	}

	if ( ! _180c_recipe_rest_is_locked( $post->ID ) ) {
		return $response;
	}

	$data = $response->get_data();

	if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
		$intro  = function_exists( 'get_field' ) ? (string) get_field( 'recipe_intro', $post->ID ) : '';
		$teaser = '' !== $intro ? wpautop( wp_kses_post( $intro ) ) : '';
		$url    = apply_filters( '180c/paywall_subscribe_url', home_url( '/abonnement/' ) );

		$data['content']['rendered']  = $teaser
			. "\n<p><a href=\"" . esc_url( $url ) . '">'
			. esc_html__( 'Recette réservée aux abonnés.', '180c' ) . '</a></p>';
		$data['content']['protected'] = true;
		unset( $data['content']['raw'] );
	}

	$response->set_data( $data );
	return $response;
}
add_filter( 'rest_prepare_recipe', '_180c_gate_recipe_rest', 10, 2 );
