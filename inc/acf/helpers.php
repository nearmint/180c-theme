<?php
/**
 * Helpers ACF.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Récupère un champ ACF avec valeur par défaut.
 *
 * @param string   $field   Nom du champ.
 * @param int|null $post_id Post ID (par défaut courant).
 * @param mixed    $default Valeur par défaut.
 * @return mixed
 */
function _180c_acf( $field, $post_id = null, $default = null ) {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}
	$value = get_field( $field, $post_id );
	return ( null !== $value && false !== $value && '' !== $value ) ? $value : $default;
}
