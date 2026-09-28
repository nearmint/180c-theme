<?php
/**
 * Désactivation du script emoji de WordPress (gain de perf).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', '_180c_remove_tinymce_emoji' );
		add_filter( 'wp_resource_hints', '_180c_remove_emoji_dns_prefetch', 10, 2 );
	}
);

/**
 * Retire wpemoji du TinyMCE.
 *
 * @param array $plugins Plugins TinyMCE.
 * @return array
 */
function _180c_remove_tinymce_emoji( $plugins ) {
	if ( is_array( $plugins ) ) {
		return array_diff( $plugins, array( 'wpemoji' ) );
	}
	return array();
}

/**
 * Retire le DNS prefetch des emoji s.w.org.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Type.
 * @return array
 */
function _180c_remove_emoji_dns_prefetch( $urls, $relation_type ) {
	if ( 'dns-prefetch' === $relation_type ) {
		$urls = array_filter(
			$urls,
			function ( $url ) {
				return false === strpos( $url, 's.w.org' );
			}
		);
	}
	return $urls;
}
