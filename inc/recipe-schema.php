<?php
/**
 * Schema.org Recipe (JSON-LD) — construction du nœud.
 *
 * Source de vérité du nœud Recipe, aligné sur le modèle ACF
 * (group_recipe_fields.json) : recipe_intro, servings/servings_unit,
 * ingredients_groups → items → line, steps → step_title/step_content.
 *
 * Le nœud est injecté dans le @graph unique de la page par inc/seo/schema.php
 * (wp_head, priorité 5). _180c_render_recipe_jsonld() est fourni pour un rendu
 * autonome éventuel, mais N'EST PAS appelé par le template afin d'éviter un
 * second bloc JSON-LD Recipe (duplication = pénalité Rich Results).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Construit le nœud Schema.org Recipe pour une recette donnée.
 *
 * Conforme Google Rich Results : name, image, author, datePublished,
 * description, prepTime/cookTime/totalTime (si renseignés), recipeYield,
 * recipeCategory, recipeCuisine, recipeIngredient, recipeInstructions,
 * keywords, isAccessibleForFree.
 *
 * @param int $post_id ID de la recette.
 * @return array|null Tableau prêt pour wp_json_encode, ou null si introuvable.
 */
function _180c_build_recipe_schema_node( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'recipe' !== get_post_type( $post ) ) {
		return null;
	}

	$home      = home_url( '/' );
	$permalink = get_permalink( $post_id );

	$schema = array(
		'@type'            => 'Recipe',
		'@id'              => $permalink . '#recipe',
		'name'             => get_the_title( $post_id ),
		'mainEntityOfPage' => $permalink,
		'datePublished'    => get_the_date( 'c', $post_id ),
		'publisher'        => array( '@id' => $home . '#organization' ),
	);

	// Auteur.
	$author = get_the_author_meta( 'display_name', (int) $post->post_author );
	if ( $author ) {
		$schema['author'] = array(
			'@type' => 'Person',
			'name'  => $author,
		);
	}

	// Description : recipe_intro → meta SEO.
	$intro = (string) _180c_acf( 'recipe_intro', $post_id );
	if ( $intro ) {
		$schema['description'] = wp_strip_all_tags( $intro );
	} elseif ( function_exists( '_180c_get_seo_description' ) ) {
		$description = _180c_get_seo_description();
		if ( $description ) {
			$schema['description'] = $description;
		}
	}

	// Image principale (featured image).
	//
	// `image` est l'une des deux propriétés REQUISES par Google pour Recipe.
	// Deux précautions. D'abord le chemin encodé RFC 3986 : un tiers du
	// catalogue porte des `©`, `°` ou accents dans le nom de fichier, qui
	// produisaient une URL non conforme. Ensuite `full` en tête (Google demande
	// des visuels larges), `large` ensuite lorsqu'il pointe un fichier distinct.
	$thumb_id = get_post_thumbnail_id( $post_id );
	if ( $thumb_id ) {
		$images = array();
		foreach ( array( 'full', 'large' ) as $size ) {
			$image_url = wp_get_attachment_image_url( (int) $thumb_id, $size );
			if ( ! $image_url ) {
				continue;
			}
			$images[] = _180c_encode_url_path( $image_url );
		}
		$images = array_values( array_unique( array_filter( $images ) ) );
		if ( $images ) {
			$schema['image'] = $images;
		}
	}

	// Date de dernière modification, quand elle diffère réellement de la
	// publication (sinon la propriété n'apporte rien et brouille la fraîcheur).
	$modified = get_the_modified_date( 'c', $post_id );
	if ( $modified && $modified !== $schema['datePublished'] ) {
		$schema['dateModified'] = $modified;
	}

	// Temps de préparation, cuisson et repos (champs ACF en minutes).
	//
	// Une propriété n'est émise que si l'éditeur a renseigné le champ : aucune
	// durée n'est déduite ni arrondie. `totalTime` est la somme des trois, y
	// compris le repos, que Google ne modélise pas séparément.
	$prep_time  = (int) _180c_acf( 'prep_time_min', $post_id, 0 );
	$cook_time  = (int) _180c_acf( 'cook_time_min', $post_id, 0 );
	$rest_time  = (int) _180c_acf( 'rest_time_min', $post_id, 0 );
	$total_time = $prep_time + $cook_time + $rest_time;

	if ( $prep_time > 0 ) {
		$schema['prepTime'] = _180c_minutes_to_iso8601_duration( $prep_time );
	}
	if ( $cook_time > 0 ) {
		$schema['cookTime'] = _180c_minutes_to_iso8601_duration( $cook_time );
	}
	if ( $total_time > 0 ) {
		$schema['totalTime'] = _180c_minutes_to_iso8601_duration( $total_time );
	}

	// Portions (servings + servings_unit). Google type `recipeYield` en Text :
	// on émet une chaîne simple plutôt qu'un tableau à un élément.
	$servings = (int) _180c_acf( 'servings', $post_id, 0 );
	if ( $servings > 0 ) {
		$unit                  = (string) _180c_acf( 'servings_unit', $post_id, '' );
		$schema['recipeYield'] = trim( $servings . ' ' . $unit );
	}

	// Catégorie culinaire (recipe_category).
	$recipe_cats = get_the_terms( $post_id, 'recipe_category' );
	if ( $recipe_cats && ! is_wp_error( $recipe_cats ) ) {
		$schema['recipeCategory'] = reset( $recipe_cats )->name;
	}

	// Cuisine : rien à émettre par défaut.
	//
	// La constante « Française » posée sur les 1553 recettes était fausse pour
	// une part visible du catalogue (horiatiki, riz cantonais, gigot à la
	// plancha…). Le modèle de contenu ne porte aucune taxonomie de cuisine :
	// tant qu'elle n'existe pas, on n'émet rien plutôt qu'une valeur inventée.
	// Le filtre permet de brancher une source réelle sans toucher au nœud.
	/**
	 * Valeur de `recipeCuisine` pour une recette.
	 *
	 * @param string $cuisine Vide par défaut — aucune donnée réelle disponible.
	 * @param int    $post_id ID de la recette.
	 */
	$cuisine = (string) apply_filters( '180c/schema/recipe_cuisine', '', $post_id );
	if ( '' !== trim( $cuisine ) ) {
		$schema['recipeCuisine'] = trim( $cuisine );
	}

	// Ingrédients : aplatit les groupes ACF en liste de lignes.
	$ingredients_groups = _180c_acf( 'ingredients_groups', $post_id );
	if ( is_array( $ingredients_groups ) ) {
		$flat = array();
		foreach ( $ingredients_groups as $group ) {
			if ( empty( $group['items'] ) || ! is_array( $group['items'] ) ) {
				continue;
			}
			foreach ( $group['items'] as $item ) {
				$line = isset( $item['line'] ) ? trim( wp_strip_all_tags( (string) $item['line'] ) ) : '';
				if ( $line ) {
					$flat[] = $line;
				}
			}
		}
		if ( $flat ) {
			$schema['recipeIngredient'] = $flat;
		}
	}

	// Instructions : steps ACF → HowToStep.
	//
	// Données structurées complètes (recipeInstructions inclus) même pour une
	// recette premium : on suit le modèle « paywalled content » de Google
	// (balisage complet + isAccessibleForFree:false + hasPart/cssSelector,
	// ajoutés plus bas) plutôt que d'omettre le déroulé.
	$steps = _180c_acf( 'steps', $post_id );
	if ( is_array( $steps ) ) {
		$instructions = array();
		foreach ( $steps as $step ) {
			$text = isset( $step['step_content'] ) ? wp_strip_all_tags( (string) $step['step_content'] ) : '';
			if ( ! $text ) {
				continue;
			}
			$how_to_step = array(
				'@type' => 'HowToStep',
				'text'  => $text,
			);
			// `step_title` traverse le même filtre que le contenu : 82 recettes
			// portent un `</strong>` ou un `<br>` orphelin dans leur titre
			// d'étape, qui partait tel quel dans la propriété `name`.
			$title = isset( $step['step_title'] ) ? trim( wp_strip_all_tags( (string) $step['step_title'] ) ) : '';
			if ( $title ) {
				$how_to_step['name'] = $title;
			}
			$instructions[] = $how_to_step;
		}
		if ( $instructions ) {
			$schema['recipeInstructions'] = $instructions;
		}
	}

	// Vidéo : aucun champ vidéo n'existe aujourd'hui dans le modèle recette.
	// Le bloc est conservé tel quel — il ne produira rien tant que la donnée
	// n'est pas réelle, et se branchera seul le jour où le champ est créé.
	$video_url = (string) _180c_acf( 'recipe_video_url', $post_id, '' );
	if ( $video_url ) {
		$schema['video'] = array(
			'@type'       => 'VideoObject',
			'contentUrl'  => esc_url_raw( $video_url ),
			'name'        => get_the_title( $post_id ),
			'description' => isset( $schema['description'] ) ? $schema['description'] : get_the_title( $post_id ),
		);
	}

	// Keywords : étiquettes de recette (taxonomie recipe_tag).
	$tags = get_the_terms( $post_id, 'recipe_tag' );
	if ( $tags && ! is_wp_error( $tags ) ) {
		$schema['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
	}

	// Accessibilité (paywall) : modèle « paywalled content » de Google.
	// Recette premium → données complètes exposées, mais isAccessibleForFree
	// false + hasPart pointant le sélecteur CSS de la zone réservée (la zone
	// paywall rendue côté serveur en lieu et place du déroulé). Cela permet
	// l'éligibilité Rich Results tout en déclarant honnêtement le contenu gaté.
	//
	// SUSPECT PRINCIPAL du verdict Rich Results FAIL constaté en juillet 2026 :
	// les 1553 recettes sont premium, donc 100 % du CPT porte ce couple, et
	// 100 % des recettes recrawlées échouent — y compris celles dont l'image
	// est déjà en ASCII pur. La documentation « paywalled content » de Google
	// ne liste pas Recipe parmi les types couverts. Le filtre ci-dessous permet
	// de neutraliser la déclaration le temps d'un test Rich Results, sans
	// toucher au code, avant de trancher éditorialement.
	/**
	 * Émettre ou non la déclaration de paywall sur le nœud Recipe.
	 *
	 * @param bool $emit    true par défaut : déclaration honnête du contenu gaté.
	 * @param int  $post_id ID de la recette.
	 */
	$emit_paywall = (bool) apply_filters( '180c/schema/recipe_paywall_markup', true, $post_id );

	if ( ! $emit_paywall ) {
		return $schema;
	}

	if ( _180c_recipe_is_premium( $post_id ) ) {
		$schema['isAccessibleForFree'] = false;
		$schema['hasPart']             = array(
			array(
				'@type'               => 'WebPageElement',
				'isAccessibleForFree' => false,
				'cssSelector'         => '.recipe-paywall',
			),
		);
	} else {
		$schema['isAccessibleForFree'] = true;
	}

	return $schema;
}

/**
 * Rend un bloc <script type="application/ld+json"> autonome pour la recette.
 *
 * Fourni par parité avec le brief. NON appelé par single-recipe.php :
 * le nœud Recipe est déjà injecté dans le @graph global via inc/seo/schema.php.
 * À n'utiliser que dans un contexte sans graphe global.
 *
 * @param int|null $post_id ID de la recette (par défaut : courante).
 * @return string HTML du script JSON-LD, ou chaîne vide.
 */
function _180c_render_recipe_jsonld( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$node    = _180c_build_recipe_schema_node( $post_id );
	if ( ! $node ) {
		return '';
	}

	$payload = array_merge( array( '@context' => 'https://schema.org' ), $node );
	$json    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	if ( false === $json ) {
		return '';
	}

	return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n";
}
