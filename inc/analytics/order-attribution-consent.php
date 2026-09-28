<?php
/**
 * Attribution des commandes WooCommerce — conditionnée au consentement.
 *
 * Problème
 * --------
 * WooCommerce charge `sourcebuster-js` et `wc-order-attribution` sur TOUTES les
 * pages du front, `allowTracking` codé à `true`, sans le moindre égard pour un
 * quelconque consentement (`OrderAttributionController::enqueue_scripts_and_styles()`,
 * appelé sur `wp_enqueue_scripts` depuis `on_init()`). Sourcebuster dépose alors
 * huit cookies `sbjs_*` (`sbjs_current`, `sbjs_first`, `sbjs_session`,
 * `sbjs_udata`, `sbjs_migrations`, `sbjs_promo` et leurs variantes `_add`) dès la
 * première page vue — constaté en production sur l'accueil, visiteur anonyme.
 *
 * Ces cookies servent l'attribution marketing des commandes : ils tracent la
 * source d'acquisition d'un visiteur dans le temps. C'est du traçage à finalité
 * marketing, soumis au consentement, et GA4 est déjà gaté ici depuis la mise en
 * place de la CMP — cette surface était le trou restant.
 *
 * Approche
 * --------
 * Le dequeue est INCONDITIONNEL côté serveur. Il n'est pas question de tester
 * le cookie `_180c_consent` en PHP : le HTML de la home est servi par WP Super
 * Cache, une variante « avec consentement » serait mise en cache puis servie à
 * des visiteurs qui n'ont rien accepté. Même contrainte, même solution que pour
 * GA4 : le serveur ne charge rien, et le JS injecte les scripts à l'acceptation.
 *
 * Ceinture et bretelles, le filtre officiel `wc_order_attribution_allow_tracking`
 * (`OrderAttributionController.php:327`) est forcé à `false`. Si un chemin
 * d'exécution ré-enqueue les scripts en contournant ce dequeue, ils démarreront
 * malgré tout en mode « tracking désactivé » — `order-attribution.js` appelle
 * alors `removeTrackingCookies()` et purge les `sbjs_*` au lieu de les poser.
 *
 * Le tunnel d'achat reste fonctionnel : `getAttributionData()` rend un objet
 * intégralement à `null` quand le tracking est désactivé ou que sourcebuster est
 * absent (`assets/js/frontend/order-attribution.js`, ligne 22-27). La commande
 * part sans données d'attribution — « origine inconnue » — et rien d'autre ne
 * change. Ces scripts ne participent ni au panier, ni au paiement.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles WooCommerce à ne pas charger avant consentement.
 *
 * `wc-order-attribution` et NON `wc-order-attribution-js` : le suffixe `-js`
 * que l'on lit dans le HTML est ajouté par WordPress à l'identifiant de la
 * balise, il ne fait pas partie du handle (cf. `OrderAttributionController.php`,
 * lignes 272 et 281).
 */
const _180C_ATTRIBUTION_HANDLES = array( 'sourcebuster-js', 'wc-order-attribution' );

/**
 * Force `allowTracking` à false dans le namespace JS de WooCommerce.
 *
 * @return bool Toujours false.
 */
function _180c_attribution_deny_tracking(): bool {
	return false;
}
add_filter( 'wc_order_attribution_allow_tracking', '_180c_attribution_deny_tracking' );

/**
 * Retire les scripts d'attribution de la file d'attente.
 *
 * Priorité 100 : `OrderAttributionController` s'enregistre sur
 * `wp_enqueue_scripts` à la priorité par défaut (10), il faut passer après lui
 * pour avoir quelque chose à désinscrire.
 *
 * @return void
 */
function _180c_attribution_dequeue_scripts() {
	if ( is_admin() ) {
		return;
	}

	foreach ( _180C_ATTRIBUTION_HANDLES as $handle ) {
		wp_dequeue_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', '_180c_attribution_dequeue_scripts', 100 );

/**
 * Expose au JS ce qu'il faut pour injecter les scripts après consentement.
 *
 * Les URLs sont résolues côté PHP : elles portent la version de WooCommerce et
 * le suffixe `.min` selon `SCRIPT_DEBUG`, deux valeurs que le JS ne peut pas
 * deviner. Le namespace `wc_order_attribution` est reconstruit ici parce que le
 * `wp_localize_script()` de WooCommerce disparaît avec le dequeue : sans lui,
 * `order-attribution.js` planterait à la première ligne (`params` indéfini).
 *
 * `allowTracking` vaut `true` dans CE payload : il n'est injecté qu'après
 * acceptation explicite, moment où le traçage est précisément autorisé. Le
 * filtre ci-dessus, lui, protège le cas où les scripts seraient chargés par une
 * autre voie que celle-ci.
 *
 * Rendu sur `wp_head` à la priorité 3 : après le bootstrap du consentement
 * (ga4.php, priorité 2) et avant le bundle principal qui contient le module de
 * consentement.
 *
 * @return void
 */
function _180c_attribution_expose_loader() {
	if ( is_admin() || ! function_exists( 'WC' ) || ! defined( 'WC_PLUGIN_FILE' ) ) {
		return;
	}

	$suffix  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	$version = defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';

	/*
	 * Mêmes valeurs par défaut que WooCommerce, filtres officiels inclus, pour
	 * que l'attribution se comporte à l'identique une fois le consentement
	 * accordé. Les recopier ici est le prix du dequeue : le payload d'origine
	 * n'est jamais construit puisque le script n'est jamais enfilé.
	 *
	 * Les `apply_filters()` qui suivent rejouent des filtres appartenant à
	 * WooCommerce. Leurs noms sont imposés par le plugin — les préfixer en
	 * `180c/` les rendrait inertes et ferait diverger notre payload de celui
	 * qu'attendent les extensions tierces. D'où les `phpcs:ignore` ciblés.
	 */

	// Préfixe des champs : reconstruit exactement comme
	// `OrderAttributionMeta::set_field_prefix()` (trait, lignes 105-123), filtre
	// officiel inclus, y compris la normalisation des underscores.
	$prefix = trim( (string) apply_filters( 'wc_order_attribution_tracking_field_prefix', 'wc_order_attribution_' ), '_' ) . '_'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtre WooCommerce, nom imposé par le plugin.

	$params = array(
		'lifetime'      => (float) apply_filters( 'wc_order_attribution_cookie_lifetime_months', 0.00001 ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtre WooCommerce, nom imposé par le plugin.
		'session'       => (int) apply_filters( 'wc_order_attribution_session_length_minutes', 30 ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtre WooCommerce, nom imposé par le plugin.
		'base64'        => (bool) apply_filters( 'wc_order_attribution_use_base64_cookies', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtre WooCommerce, nom imposé par le plugin.
		'ajaxurl'       => admin_url( 'admin-ajax.php' ),
		'prefix'        => $prefix,
		'allowTracking' => true,
	);

	/*
	 * Carte champ → accesseur sourcebuster. `OrderAttributionController::$fields`
	 * est privée et sans accesseur public pour la carte (seul `get_field_names()`
	 * expose les clés) : on la reconstruit depuis les mêmes valeurs par défaut
	 * (`OrderAttributionMeta::$default_fields`, trait lignes 27-50) en passant par
	 * le même filtre officiel. Un plugin qui personnalise les champs est donc
	 * respecté à l'identique. Sans cette carte, `getAttributionData()` rendrait un
	 * objet vide et l'attribution serait silencieusement perdue même après accord.
	 */
	$fields = (array) apply_filters(
		'wc_order_attribution_tracking_fields', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtre WooCommerce, nom imposé par le plugin.
		array(
			'source_type'          => 'current.typ',
			'referrer'             => 'current_add.rf',
			'utm_campaign'         => 'current.cmp',
			'utm_source'           => 'current.src',
			'utm_medium'           => 'current.mdm',
			'utm_content'          => 'current.cnt',
			'utm_id'               => 'current.id',
			'utm_term'             => 'current.trm',
			'utm_source_platform'  => 'current.plt',
			'utm_creative_format'  => 'current.fmt',
			'utm_marketing_tactic' => 'current.tct',
			'session_entry'        => 'current_add.ep',
			'session_start_time'   => 'current_add.fd',
			'session_pages'        => 'session.pgs',
			'session_count'        => 'udata.vst',
			'user_agent'           => 'udata.uag',
		)
	);

	$payload = array(
		'scripts' => array(
			// L'ordre compte : order-attribution.js déclare sourcebuster-js en
			// dépendance et lit `sbjs` au chargement.
			add_query_arg( 'ver', $version, plugins_url( "assets/js/sourcebuster/sourcebuster{$suffix}.js", WC_PLUGIN_FILE ) ),
			add_query_arg( 'ver', $version, plugins_url( "assets/js/frontend/order-attribution{$suffix}.js", WC_PLUGIN_FILE ) ),
		),
		'data'    => array(
			'params' => $params,
			'fields' => $fields,
		),
	);

	printf(
		'<script>window._180cOrderAttribution=%s;</script>' . "\n",
		wp_json_encode( $payload )
	);
}
add_action( 'wp_head', '_180c_attribution_expose_loader', 3 );
