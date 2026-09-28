<?php
/**
 * Plugin Name:  180°C — HTML Cache Headers
 * Description:  Normalise l'en-tête Cache-Control des réponses HTML front-end. Retire le `no-store` qui désactivait tout cache CDN/navigateur (cause n°1 du LCP mobile à 8,9 s) : les pages publiques anonymes deviennent `public, max-age=300, s-maxage=600`, les visiteurs connectés `private, no-cache` (jamais no-store), tandis que l'admin, le panier, le checkout et `/mon-compte/` conservent un cache désactivé.
 * Version:      1.0.0
 * Author:       180°C
 * License:      GPL-2.0-or-later
 * Update URI:   false
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Constante d'échappement (rollback alternatif).
 *
 * Définir `_180C_HTML_CACHE_HEADERS_DISABLED` à `true` dans `wp-config.php`
 * neutralise entièrement ce mu-plugin sans avoir à supprimer le fichier — les
 * en-têtes redeviennent ceux émis par le reste de la stack à la requête
 * suivante. Sert de rollback < 30 s lorsqu'on n'a pas d'accès SFTP immédiat.
 */
if ( defined( '_180C_HTML_CACHE_HEADERS_DISABLED' ) && _180C_HTML_CACHE_HEADERS_DISABLED ) {
	return;
}

/**
 * Détermine si la requête courante est une page HTML front-end « gérable ».
 *
 * On laisse délibérément intacts les contextes qui doivent rester dynamiques
 * (admin, AJAX, cron, REST, XML-RPC, WP-CLI, page de connexion) ainsi que les
 * méthodes HTTP non idempotentes (POST, etc.). Pour tous ces cas le mu-plugin
 * ne touche à aucun en-tête : le `no-store` éventuel est conservé.
 *
 * @return bool True si l'on peut normaliser le Cache-Control de cette requête.
 */
function _180c_html_cache_is_managed_request() {
	if ( is_admin() ) {
		return false;
	}

	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		return false;
	}

	if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
		return false;
	}

	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		|| ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return false;
	}

	if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Lecture de la méthode HTTP uniquement, assainie ci-dessous.
	$method = isset( $_SERVER['REQUEST_METHOD'] )
		? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
		: 'GET';

	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return false;
	}

	return true;
}

/**
 * Indique si la page rendue est intrinsèquement dynamique (cache à proscrire).
 *
 * Couvre les aperçus (preview / customizer) et les pages WooCommerce liées à
 * une session utilisateur : panier, commande (checkout) et compte (`/mon-compte/`).
 * Les fonctions Woo sont testées via `function_exists()` car l'extension peut
 * être absente ou neutralisée selon l'environnement.
 *
 * @return bool True si la page doit conserver un cache désactivé (no-store).
 */
function _180c_html_cache_is_dynamic_page() {
	if ( is_preview() || is_customize_preview() ) {
		return true;
	}

	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return true;
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return true;
	}

	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		return true;
	}

	return false;
}

/**
 * Détecte la présence d'un cookie de personnalisation dans la requête.
 *
 * Sert de garde-fou anti-fuite : si un visiteur « anonyme » porte un cookie de
 * session (connexion, mot de passe d'article, panier WooCommerce), sa réponse
 * ne doit pas être mise en cache partagé (CDN). On bascule alors en `private`.
 * Les cookies inoffensifs (analytics, consentement de la CMP maison) sont ignorés afin de
 * ne pas fragmenter le cache CDN des visiteurs réellement anonymes.
 *
 * Seuls les **noms** de cookies sont lus, jamais leurs valeurs.
 *
 * @return bool True si un cookie de session/personnalisation est présent.
 */
function _180c_html_cache_has_private_cookie() {
	if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
		return false;
	}

	$prefixes = array(
		'wordpress_logged_in_',
		'wp-postpass_',
		'comment_author_',
		'woocommerce_items_in_cart',
		'woocommerce_cart_hash',
		'wp_woocommerce_session_',
	);

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Seuls les noms (clés) de cookies sont inspectés, jamais les valeurs.
	foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
		$cookie_name = (string) $cookie_name;

		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $cookie_name, $prefix ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Calcule la directive Cache-Control adaptée au contexte de la requête.
 *
 * Stratégie cible :
 * - Pages dynamiques (panier, checkout, compte, preview) → cache désactivé.
 * - Visiteur connecté ou porteur d'un cookie de session → `private, no-cache`
 *   (jamais `no-store`, ce qui autorise le cache navigateur privé).
 * - Page 404 → `public, max-age=60` (cache court, le contenu peut apparaître).
 * - Visiteur anonyme (≈ 90 % du trafic) → `public, max-age=300, s-maxage=600`.
 *
 * @return string|null Directive Cache-Control, ou null si la requête n'est pas gérable.
 */
function _180c_html_cache_directive() {
	if ( ! _180c_html_cache_is_managed_request() ) {
		return null;
	}

	if ( _180c_html_cache_is_dynamic_page() ) {
		$directive = 'no-store, no-cache, must-revalidate, max-age=0';
		$context   = 'dynamic';
	} elseif ( is_user_logged_in() || _180c_html_cache_has_private_cookie() ) {
		$directive = 'private, no-cache, max-age=0, must-revalidate';
		$context   = 'private';
	} elseif ( is_404() ) {
		$directive = 'public, max-age=60';
		$context   = 'notfound';
	} else {
		$directive = 'public, max-age=300, s-maxage=600';
		$context   = 'public';
	}

	/**
	 * Filtre la directive Cache-Control émise pour une réponse HTML front-end.
	 *
	 * Permet d'ajuster les durées de cache sans modifier ce mu-plugin.
	 *
	 * @param string $directive Valeur de l'en-tête Cache-Control.
	 * @param string $context   Contexte calculé : `dynamic`, `private`, `notfound` ou `public`.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks projet `180c/` imposé par CLAUDE.md.
	return (string) apply_filters( '180c/html_cache_control', $directive, $context );
}

/**
 * Émet l'en-tête Cache-Control normalisé (mécanisme principal, autoritaire).
 *
 * Branché en toute fin de `send_headers` puis ré-affirmé sur `template_redirect`
 * (priorité maximale) afin de remplacer (`replace = true`) tout Cache-Control
 * posé en amont par WordPress, une extension de cache ou une règle héritée. Pour
 * les réponses cacheables, les en-têtes anti-cache résiduels (Pragma, Expires
 * dans le passé) sont retirés afin d'éviter toute contradiction.
 *
 * Limite connue : un `Header set Cache-Control` posé au niveau serveur (Apache
 * `.htaccess` avec `always`, ou CDN de l'hébergeur) s'applique après PHP et ne peut pas
 * être surchargé ici — cela relève d'une action opérateur côté hébergeur.
 *
 * @return void
 */
function _180c_html_cache_send_headers() {
	if ( headers_sent() ) {
		return;
	}

	$directive = _180c_html_cache_directive();

	if ( null === $directive || '' === $directive ) {
		return;
	}

	header( 'Cache-Control: ' . $directive, true );

	if ( false !== strpos( $directive, 'public' ) ) {
		header_remove( 'Pragma' );
		header_remove( 'Expires' );
	}
}
add_action( 'send_headers', '_180c_html_cache_send_headers', PHP_INT_MAX );
add_action( 'template_redirect', '_180c_html_cache_send_headers', PHP_INT_MAX );

/**
 * Retire le token `no-store` des en-têtes nocache front-end (filet de sécurité).
 *
 * Toute fonction appelant `nocache_headers()` (cœur WP ou extension) passe par
 * le filtre `nocache_headers`. On y supprime le seul token `no-store` lorsque la
 * requête est une page HTML front-end non dynamique : le reste de la directive
 * (`no-cache, must-revalidate, private`…) est préservé, ce qui empêche la
 * réintroduction tardive du `no-store` sans pour autant rendre cacheable une
 * page qui demande explicitement à ne pas l'être. L'admin, AJAX, le panier, le
 * checkout et le compte conservent leur `no-store` complet.
 *
 * @param array $headers En-têtes nocache calculés par WordPress.
 * @return array En-têtes éventuellement nettoyés du token `no-store`.
 */
function _180c_html_cache_filter_nocache( $headers ) {
	if ( ! is_array( $headers ) || empty( $headers['Cache-Control'] ) ) {
		return $headers;
	}

	if ( ! _180c_html_cache_is_managed_request() || _180c_html_cache_is_dynamic_page() ) {
		return $headers;
	}

	$tokens = array_filter(
		array_map( 'trim', explode( ',', (string) $headers['Cache-Control'] ) ),
		static function ( $token ) {
			return '' !== $token && 0 !== strcasecmp( $token, 'no-store' );
		}
	);

	$headers['Cache-Control'] = implode( ', ', $tokens );

	return $headers;
}
add_filter( 'nocache_headers', '_180c_html_cache_filter_nocache' );
