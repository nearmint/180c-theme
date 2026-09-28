<?php
/**
 * Template part : carte article.
 *
 * Adaptation classique du « pattern article-card ». S'appuie sur
 * _180c_render_article_card() — pendant naturel de _180c_render_recipe_card()
 * pour le CPT `post`. Carte visuellement alignée sur `card-180c--recipe`.
 *
 * Usage :
 *   get_template_part( 'parts/article-card', null, array(
 *       'post_id' => 123,
 *       'size'    => 'md', // 'sm' | 'md' | 'lg'
 *   ) );
 *
 * @package 180c
 *
 * @var array $args {
 *     @type int    $post_id ID de l'article.
 *     @type string $size    Variante de taille.
 * }
 */

defined( 'ABSPATH' ) || exit;

$post_id   = isset( $args['post_id'] ) ? (int) $args['post_id'] : (int) get_the_ID();
$card_size = isset( $args['size'] ) ? (string) $args['size'] : 'md';

if ( ! $post_id ) {
	return;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML déjà échappé dans le helper.
echo _180c_render_article_card( $post_id, $card_size );
