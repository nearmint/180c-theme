<?php
/**
 * Endpoint app-version (équivalent app-version.json hébergé).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			_180C_API_NAMESPACE,
			'/app-version',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => '_180c_rest_app_version',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * GET /app-version
 *
 * @return WP_REST_Response
 */
function _180c_rest_app_version() {
	return rest_ensure_response(
		array(
			'ios'     => array(
				'min'     => '1.0.0',
				'current' => '1.0.0',
			),
			'android' => array(
				'min'     => '1.0.0',
				'current' => '1.0.0',
			),
		)
	);
}
