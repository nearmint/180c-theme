<?php
/**
 * Synchronisation Local JSON ACF.
 *
 * Les groupes de champs ACF sont versionnés dans /acf-json.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'acf/settings/save_json',
	function () {
		return _180C_THEME_DIR . '/acf-json';
	}
);

add_filter(
	'acf/settings/load_json',
	function ( $paths ) {
		unset( $paths[0] );
		$paths[] = _180C_THEME_DIR . '/acf-json';
		return $paths;
	}
);
