<?php
/**
 * Template part — fil d'Ariane générique réutilisable.
 *
 * Rend _180c_breadcrumb_markup() à partir d'items fournis. Réutilisable
 * site-wide (non câblé ailleurs pour l'instant). Sur les pages WooCommerce,
 * préférer _180c_render_breadcrumb() qui s'appuie sur woocommerce_breadcrumb().
 *
 * Usage :
 *   get_template_part( 'parts/breadcrumb', null, array(
 *       'items' => array(
 *           array( 'label' => 'Accueil', 'url' => home_url( '/' ) ),
 *           array( 'label' => 'Page courante' ), // sans 'url' = aria-current
 *       ),
 *   ) );
 *
 * @package 180c
 *
 * @var array $args {
 *     @type array $items Items ordonnés { label:string, url?:string }.
 * }
 */

defined( 'ABSPATH' ) || exit;

$_180c_items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();

if ( empty( $_180c_items ) || ! function_exists( '_180c_breadcrumb_markup' ) ) {
	return;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le helper.
echo _180c_breadcrumb_markup( $_180c_items );
