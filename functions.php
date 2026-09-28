<?php
/**
 * Point d'entrée du thème 180°C.
 *
 * Charge le bootstrap qui initialise tous les modules.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/bootstrap.php';

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once get_template_directory() . '/inc/cli/class-help-importer.php';
}
