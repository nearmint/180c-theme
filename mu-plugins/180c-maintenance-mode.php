<?php
/**
 * Plugin Name:  180°C — Maintenance Mode
 * Description:  Page de maintenance 503 autonome (sans dépendance au thème ni au build Vite) pour la fenêtre de bascule v1. Bascule via `wp 180c maintenance on|off|status` ou la constante `_180C_MAINTENANCE`. Laisse passer l'admin, l'aperçu (capability ou cookie de bypass) et les IP autorisées. Le rollback réarme automatiquement la maintenance car l'état vit dans la BDD restaurée.
 * Version:      1.0.0
 * Author:       180°C
 * License:      GPL-2.0-or-later
 * Update URI:   false
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nom de l'option (autoloadée) portant l'état de la maintenance.
 *
 * Valeur `'1'` = maintenance armée, toute autre valeur = levée. L'option vit
 * dans la BDD : une restauration (restauration de sauvegarde) qui réimporte un dump
 * pris « site gelé » réarme donc la maintenance d'elle-même.
 */
const _180C_MAINTENANCE_OPTION = '180c_maintenance_active';

/**
 * Nom du cookie d'aperçu armé par `?180c-preview=<token>`.
 *
 * Il ne contient jamais le token en clair : on y stocke son `sha256`, comparé
 * en temps constant à l'arrivée.
 */
const _180C_MAINTENANCE_PREVIEW_COOKIE = '180c_preview';

/**
 * Indique si la maintenance est armée pour la requête courante.
 *
 * Ordre de priorité :
 * 1. `_180C_MAINTENANCE_DISABLED` (true) → échappatoire d'urgence : le front
 *    redevient public à la requête suivante même si l'option/constante disent
 *    « on ». Sert de rollback < 30 s si la maintenance reste coincée et que
 *    WP-CLI est hors d'atteinte (édition de `wp-config.php`).
 * 2. `_180C_MAINTENANCE` (true) → verrou « dur » : prime sur l'option et
 *    survit à un import de base (utile pendant un `wp db import`).
 * 3. Option `_180C_MAINTENANCE_OPTION` en base (basculée par WP-CLI).
 *
 * @return bool True si la page de maintenance doit potentiellement être servie.
 */
function _180c_maintenance_is_active() {
	if ( defined( '_180C_MAINTENANCE_DISABLED' ) && _180C_MAINTENANCE_DISABLED ) {
		return false;
	}

	if ( defined( '_180C_MAINTENANCE' ) && _180C_MAINTENANCE ) {
		return true;
	}

	return '1' === (string) get_option( _180C_MAINTENANCE_OPTION, '' );
}

/**
 * Indique si la requête courante doit traverser la maintenance sans être bloquée.
 *
 * Contextes non bloqués :
 * - administration, `wp-login.php`, AJAX, REST, XML-RPC, cron, WP-CLI (ces
 *   contextes ne déclenchent de toute façon pas `template_redirect`, mais on les
 *   garde par défense) ;
 * - utilisateur connecté disposant de `manage_options` (aperçu admin) ;
 * - requête portant le cookie d'aperçu valide ;
 * - adresse IP listée dans `_180C_MAINTENANCE_ALLOW_IPS`.
 *
 * @return bool True si la requête doit voir le site normalement.
 */
function _180c_maintenance_should_bypass() {
	if ( is_admin() ) {
		return true;
	}

	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		return true;
	}

	if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
		return true;
	}

	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		|| ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return true;
	}

	if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
		return true;
	}

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	if ( _180c_maintenance_ip_allowed() ) {
		return true;
	}

	$bypass = _180c_maintenance_has_preview_cookie();

	/**
	 * Filtre la décision de laisser passer la requête malgré la maintenance.
	 *
	 * Permet d'élargir l'allowlist (ex. un en-tête de monitoring) sans modifier
	 * ce mu-plugin.
	 *
	 * @param bool $bypass True si la requête doit voir le site normalement.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks projet `180c/` imposé par CLAUDE.md.
	return (bool) apply_filters( '180c/maintenance_should_bypass', $bypass );
}

/**
 * Teste si l'IP du client figure dans `_180C_MAINTENANCE_ALLOW_IPS`.
 *
 * La constante est une liste d'IP séparées par des virgules définie dans
 * `wp-config.php`. Comparaison best-effort sur `REMOTE_ADDR` : derrière un CDN
 * ou un proxy (hébergeur), cette valeur peut être celle du proxy — préférer alors le
 * cookie d'aperçu.
 *
 * @return bool True si l'IP courante est explicitement autorisée.
 */
function _180c_maintenance_ip_allowed() {
	if ( ! defined( '_180C_MAINTENANCE_ALLOW_IPS' ) || '' === (string) _180C_MAINTENANCE_ALLOW_IPS ) {
		return false;
	}

	if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
		return false;
	}

	$remote = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

	$allowed = array_filter( array_map( 'trim', explode( ',', (string) _180C_MAINTENANCE_ALLOW_IPS ) ) );

	return in_array( $remote, $allowed, true );
}

/**
 * Vérifie la présence d'un cookie d'aperçu valide.
 *
 * Le cookie stocke le `sha256` du token ; on le compare en temps constant au
 * `sha256` de `_180C_MAINTENANCE_BYPASS_TOKEN`. Sans token configuré, aucun
 * cookie n'est jamais considéré valide.
 *
 * @return bool True si le cookie d'aperçu correspond au token configuré.
 */
function _180c_maintenance_has_preview_cookie() {
	if ( ! defined( '_180C_MAINTENANCE_BYPASS_TOKEN' ) || '' === (string) _180C_MAINTENANCE_BYPASS_TOKEN ) {
		return false;
	}

	if ( empty( $_COOKIE[ _180C_MAINTENANCE_PREVIEW_COOKIE ] ) ) {
		return false;
	}

	$presented = sanitize_text_field( wp_unslash( $_COOKIE[ _180C_MAINTENANCE_PREVIEW_COOKIE ] ) );
	$expected  = hash( 'sha256', (string) _180C_MAINTENANCE_BYPASS_TOKEN );

	return hash_equals( $expected, $presented );
}

/**
 * Arme ou retire le cookie d'aperçu selon le paramètre `?180c-preview=`.
 *
 * - `?180c-preview=<token>` (token = `_180C_MAINTENANCE_BYPASS_TOKEN`) → pose le
 *   cookie d'aperçu (durée 8 h, HttpOnly, SameSite=Lax).
 * - `?180c-preview=off` → retire le cookie.
 *
 * Branché sur `init` (front uniquement) afin d'émettre le `Set-Cookie` avant
 * tout rendu. Aucune vérification de nonce : le token *est* le secret d'accès.
 *
 * @return void
 */
function _180c_maintenance_handle_preview_arg() {
	if ( is_admin() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Le token d'aperçu est lui-même le secret ; un nonce n'est pas applicable à un lien pré-auth.
	if ( ! isset( $_GET['180c-preview'] ) ) {
		return;
	}

	if ( headers_sent() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Idem : valeur comparée en temps constant au token configuré.
	$value  = sanitize_text_field( wp_unslash( $_GET['180c-preview'] ) );
	$path   = defined( 'COOKIEPATH' ) && '' !== COOKIEPATH ? COOKIEPATH : '/';
	$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

	if ( 'off' === $value ) {
		setcookie(
			_180C_MAINTENANCE_PREVIEW_COOKIE,
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		return;
	}

	if ( ! defined( '_180C_MAINTENANCE_BYPASS_TOKEN' ) || '' === (string) _180C_MAINTENANCE_BYPASS_TOKEN ) {
		return;
	}

	if ( ! hash_equals( (string) _180C_MAINTENANCE_BYPASS_TOKEN, $value ) ) {
		return;
	}

	setcookie(
		_180C_MAINTENANCE_PREVIEW_COOKIE,
		hash( 'sha256', (string) _180C_MAINTENANCE_BYPASS_TOKEN ),
		array(
			'expires'  => time() + ( 8 * HOUR_IN_SECONDS ),
			'path'     => $path,
			'domain'   => $domain,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}
add_action( 'init', '_180c_maintenance_handle_preview_arg' );

/**
 * Sert la page de maintenance 503 sur le rendu front, puis stoppe l'exécution.
 *
 * Branché sur `template_redirect` : ne s'active donc que sur le rendu d'une page
 * front-end (jamais sur l'admin, la connexion, l'AJAX, la REST API ou le cron).
 * Émet `503 Service Unavailable`, `Retry-After` et `Cache-Control: no-store`
 * pour qu'aucun CDN ni navigateur ne mette la page de maintenance en cache.
 *
 * @return void
 */
function _180c_maintenance_render() {
	if ( ! _180c_maintenance_is_active() || _180c_maintenance_should_bypass() ) {
		return;
	}

	/**
	 * Filtre le délai (secondes) annoncé dans l'en-tête `Retry-After`.
	 *
	 * @param int $retry_after Délai en secondes (défaut : 1 heure).
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks projet `180c/` imposé par CLAUDE.md.
	$retry_after = (int) apply_filters( '180c/maintenance_retry_after', HOUR_IN_SECONDS );

	if ( ! headers_sent() ) {
		status_header( 503 );
		nocache_headers();
		header( 'Retry-After: ' . max( 0, $retry_after ) );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Content-Type: text/html; charset=utf-8' );
	}

	_180c_maintenance_print_page();
	exit;
}
add_action( 'template_redirect', '_180c_maintenance_render' );

/**
 * Imprime la page HTML 503 autonome (CSS inline, aucune dépendance externe).
 *
 * Inline CSS volontaire : la page doit rester opérante même pendant un dépôt
 * FTP (le build Vite `dist/` peut être en cours de copie) et même si la BDD est
 * en cours d'import — d'où l'absence de requête (`get_bloginfo`, etc.). C'est le
 * pattern standard d'un drop-in de maintenance WordPress. Tokens DS respectés :
 * accent `#FFAE3A`, fond sombre, contrastes WCAG AA, mobile-first.
 *
 * @return void
 */
function _180c_maintenance_print_page() {
	?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>Maintenance en cours — 180°C</title>
	<style>
		:root { color-scheme: dark; }
		* { box-sizing: border-box; }
		html, body { height: 100%; margin: 0; }
		body {
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			padding: 1.5rem;
			background: #161514;
			color: #f4f1ea;
			font-family: "Helvetica Neue", Arial, sans-serif;
			line-height: 1.6;
			-webkit-font-smoothing: antialiased;
		}
		.box { max-width: 33rem; width: 100%; text-align: center; }
		.brand {
			font-size: 0.8rem;
			letter-spacing: 0.22em;
			text-transform: uppercase;
			color: #ffae3a;
			margin: 0 0 1.75rem;
		}
		h1 {
			font-family: Georgia, "Times New Roman", serif;
			font-size: clamp(1.75rem, 5vw, 2.6rem);
			font-weight: 700;
			line-height: 1.15;
			margin: 0 0 1rem;
		}
		p { font-size: 1.05rem; color: #d6d1c7; margin: 0 auto 1rem; max-width: 28rem; }
		.rule { width: 3rem; height: 3px; background: #ffae3a; border: 0; margin: 1.75rem auto; }
		.note { font-size: 0.85rem; color: #908a7e; }
		.note a { color: #ffae3a; }
	</style>
</head>
<body>
	<main class="box" role="main">
		<p class="brand">180°C</p>
		<h1>Nous revenons très vite</h1>
		<hr class="rule" aria-hidden="true">
		<p>Le site est momentanément en maintenance le temps d'une mise à jour. Merci de votre patience&nbsp;: tout sera de retour dans quelques instants.</p>
		<p class="note">Une question&nbsp;? Écrivez-nous à <a href="mailto:contact@180c.fr">contact@180c.fr</a>.</p>
	</main>
</body>
</html>
	<?php
}

/*
 * --------------------------------------------------------------------------
 * Outillage WP-CLI : `wp 180c maintenance status|on|off`.
 * Aucun effet de bord hors contexte CLI (les commandes ne sont enregistrées
 * que lorsque WP_CLI est défini).
 * --------------------------------------------------------------------------
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Affiche l'état courant de la maintenance.
	 *
	 * ## EXAMPLES
	 *
	 *     wp 180c maintenance status
	 *
	 * @return void
	 */
	function _180c_maintenance_cli_status() {
		$option = '1' === (string) get_option( _180C_MAINTENANCE_OPTION, '' );
		$hard   = defined( '_180C_MAINTENANCE' ) && _180C_MAINTENANCE;
		$kill   = defined( '_180C_MAINTENANCE_DISABLED' ) && _180C_MAINTENANCE_DISABLED;
		$active = _180c_maintenance_is_active();

		WP_CLI::log( 'Option BDD (' . _180C_MAINTENANCE_OPTION . ') : ' . ( $option ? 'on' : 'off' ) );
		WP_CLI::log( 'Verrou dur (_180C_MAINTENANCE) : ' . ( $hard ? 'on' : 'off' ) );
		WP_CLI::log( 'Échappatoire (_180C_MAINTENANCE_DISABLED) : ' . ( $kill ? 'on' : 'off' ) );

		if ( $active ) {
			WP_CLI::success( 'Maintenance ARMÉE — le front anonyme reçoit une 503.' );
		} else {
			WP_CLI::success( 'Maintenance LEVÉE — le front est public.' );
		}
	}
	WP_CLI::add_command( '180c maintenance status', '_180c_maintenance_cli_status' );

	/**
	 * Arme la maintenance (option BDD).
	 *
	 * ## EXAMPLES
	 *
	 *     wp 180c maintenance on
	 *
	 * @return void
	 */
	function _180c_maintenance_cli_on() {
		update_option( _180C_MAINTENANCE_OPTION, '1', true );

		if ( defined( '_180C_MAINTENANCE_DISABLED' ) && _180C_MAINTENANCE_DISABLED ) {
			WP_CLI::warning( '_180C_MAINTENANCE_DISABLED est défini à true : le front reste public malgré l\'option. Retirez la constante de wp-config.php.' );
		}

		WP_CLI::success( 'Maintenance armée. Vérifiez avec `wp 180c maintenance status`.' );
	}
	WP_CLI::add_command( '180c maintenance on', '_180c_maintenance_cli_on' );

	/**
	 * Lève la maintenance (option BDD).
	 *
	 * Rappelle, le cas échéant, qu'un verrou dur `_180C_MAINTENANCE` reste actif
	 * et doit être retiré de `wp-config.php` pour rouvrir le front.
	 *
	 * ## EXAMPLES
	 *
	 *     wp 180c maintenance off
	 *
	 * @return void
	 */
	function _180c_maintenance_cli_off() {
		update_option( _180C_MAINTENANCE_OPTION, '', true );

		if ( defined( '_180C_MAINTENANCE' ) && _180C_MAINTENANCE ) {
			WP_CLI::warning( 'Verrou dur _180C_MAINTENANCE toujours actif : retirez la constante de wp-config.php pour rouvrir le front.' );
		}

		WP_CLI::success( 'Maintenance levée. Vérifiez avec `wp 180c maintenance status`.' );
	}
	WP_CLI::add_command( '180c maintenance off', '_180c_maintenance_cli_off' );
}
