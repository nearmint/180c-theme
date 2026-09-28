<?php
/**
 * Whitelist des blocs Gutenberg autorisés par type de contenu.
 *
 * Pour les `post` (articles) : palette éditoriale resserrée + blocs maison `acf/*`.
 * Pour les `recipe` : blocs natifs de base + blocs 180c spécifiques recette.
 * Pour les autres types : pas de restriction (liste complète).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filtre la liste des blocs autorisés selon le type de contenu en cours d'édition.
 *
 * @param bool|string[] $allowed_block_types Tableau de blocs autorisés, ou true pour tout autoriser.
 * @param \WP_Block_Editor_Context $block_editor_context Contexte de l'éditeur de blocs.
 * @return bool|string[] Tableau filtré ou true.
 */
add_filter(
	'allowed_block_types_all',
	function ( $allowed_block_types, $block_editor_context ) {
		if ( empty( $block_editor_context->post ) ) {
			return $allowed_block_types;
		}

		$post_type = $block_editor_context->post->post_type;

		// Blocs natifs communs aux articles et aux recettes.
		$core_common = array(
			'core/paragraph',
			'core/heading',
			'core/image',
			'core/list',
			'core/list-item',
			'core/quote',
			'core/embed',
			'core/gallery',
			'core/separator',
			'core/table',
			'core/html',
			'core/code',
			'core/file',
			'core/audio',
			'core/video',
			'core/columns',
			'core/column',
			'core/group',
			'core/cover',
			// Bloc « Classic » : laisse passer les articles legacy créés en
			// éditeur classique (ils s'ouvrent sous forme d'un core/freeform).
			// Sans lui, ces contenus seraient signalés « bloc non autorisé ».
			'core/freeform',
		);

		// Blocs 180c utilisables dans les recettes.
		$blocks_180c_recipe = array(
			'180c/recipe-card',
			'180c/quote',
			'180c/paywall',
		);

		// Branche ARTICLES : palette éditoriale resserrée. On retire les blocs
		// de mise en page (table, columns, group, cover, code, media…) pour
		// garder un flux d'article propre, et on préserve dynamiquement les
		// blocs maison `acf/*` (dont acf/coordonnees-lieu).
		if ( 'post' === $post_type ) {
			$core_post = array(
				'core/paragraph',
				'core/heading',
				'core/list',
				'core/list-item',
				'core/quote',
				'core/image',
				'core/gallery',
				'core/embed',
				'core/separator',
				// Bloc « Classic » : laisse passer les articles legacy créés en
				// éditeur classique (ils s'ouvrent sous forme d'un core/freeform).
				'core/freeform',
			);

			$own = array();
			foreach ( array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
				if ( str_starts_with( $name, 'acf/' ) ) {
					$own[] = $name;
				}
			}

			return array_merge( $core_post, $own );
		}

		// Recettes et autres types : inchangés.
		if ( 'recipe' === $post_type ) {
			return array_merge( $core_common, $blocks_180c_recipe );
		}

		return $allowed_block_types;
	},
	10,
	2
);
