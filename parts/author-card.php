<?php
/**
 * Template part : carte auteur (composant partagé).
 *
 * Pendant de parts/recipe-card.php et parts/article-card.php pour les comptes
 * auteur. S'appuie sur _180c_render_author_card() (inc/search.php) : photo
 * éditoriale ACF prioritaire, fallback monogramme.
 *
 * Usage :
 *   get_template_part( 'parts/author-card', null, array(
 *       'author_id' => 123,
 *   ) );
 *
 * @package 180c
 *
 * @var array $args {
 *     @type int $author_id ID de l'auteur.
 * }
 */

defined( 'ABSPATH' ) || exit;

$author_id = isset( $args['author_id'] ) ? (int) $args['author_id'] : 0;

if ( ! $author_id ) {
	return;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML déjà échappé dans le helper.
echo _180c_render_author_card( $author_id );
