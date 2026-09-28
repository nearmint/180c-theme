<?php
/**
 * Hub d'initialisation des endpoints REST custom 180°C.
 *
 * Charge les modules REST individuels et enregistre les hooks transverses
 * (CORS, headers communs).
 *
 * Namespace : /wp-json/180c/v1/*
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once _180C_THEME_DIR . '/inc/rest/session.php';
require_once _180C_THEME_DIR . '/inc/rest/auth.php';
require_once _180C_THEME_DIR . '/inc/rest/me.php';
require_once _180C_THEME_DIR . '/inc/rest/app-login.php';
require_once _180C_THEME_DIR . '/inc/rest/home-recettes.php';
require_once _180C_THEME_DIR . '/inc/rest/favorites.php';
require_once _180C_THEME_DIR . '/inc/rest/newsletter.php';
require_once _180C_THEME_DIR . '/inc/rest/recipes.php';
require_once _180C_THEME_DIR . '/inc/rest/app-version.php';
require_once _180C_THEME_DIR . '/inc/rest/unsubscribe.php';
require_once _180C_THEME_DIR . '/inc/rest/subscription-reactivate.php';
require_once _180C_THEME_DIR . '/inc/rest/subscribers-export.php';
require_once _180C_THEME_DIR . '/inc/rest/unpaid-tag-status.php';
require_once _180C_THEME_DIR . '/inc/rest/contact.php';
require_once _180C_THEME_DIR . '/inc/rest/push.php';
require_once _180C_THEME_DIR . '/inc/notifications/rest.php';

/**
 * Gestion CORS pour les requêtes REST provenant des apps mobiles.
 *
 * Les origines autorisées sont filtrables via '180c/rest_allowed_origins'.
 */
add_filter(
	'rest_pre_serve_request',
	function ( $served, $result, $request ) {
		$origin = $_SERVER['HTTP_ORIGIN'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		/**
		 * Liste des origines autorisées pour les requêtes REST cross-origin.
		 *
		 * @param string[] $origins Tableau d'URLs origine.
		 */
		$allowed_origins = (array) apply_filters(
			'180c/rest_allowed_origins',
			array(
				'https://180c.fr',
				'https://www.180c.fr',
				'capacitor://localhost',  // Capacitor iOS/Android.
				'http://localhost',       // Dev local Capacitor.
			)
		);

		if ( in_array( $origin, $allowed_origins, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
			header( 'Vary: Origin' );
		}

		return $served;
	},
	10,
	3
);

/**
 * Répond aux preflight OPTIONS sans auth pour les routes 180c/v1/*.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			_180C_API_NAMESPACE,
			'/(?P<path>.*)',
			array(
				'methods'             => 'OPTIONS',
				'callback'            => static function () {
					return rest_ensure_response( array() );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);
