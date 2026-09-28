<?php
/**
 * Template part : carte recette.
 *
 * Adaptation classique du « pattern recipe-card » du cahier des charges (le thème
 * n'est pas FSE). S'appuie sur l'unité de rendu réutilisable
 * _180c_block_render_recipe_card() partagée avec les blocs Gutenberg.
 *
 * Usage :
 *   get_template_part( 'parts/recipe-card', null, array(
 *       'recipe_id' => 123,
 *       'size'      => 'md', // 'sm' | 'md' | 'lg'
 *   ) );
 *
 * @package 180c
 *
 * @var array $args {
 *     @type int    $recipe_id ID de la recette.
 *     @type string $size      Variante de taille.
 * }
 */

defined( 'ABSPATH' ) || exit;

$recipe_id = isset( $args['recipe_id'] ) ? (int) $args['recipe_id'] : (int) get_the_ID();
$card_size = isset( $args['size'] ) ? (string) $args['size'] : 'md';

if ( ! $recipe_id ) {
	return;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML déjà échappé dans le helper.
echo _180c_render_recipe_card( $recipe_id, $card_size );
