<?php
/**
 * Service worker OneSignal servi à l'URL racine `/OneSignalSDKWorker.js`.
 *
 * POURQUOI UN HANDLER PHP
 * -----------------------
 * Un service worker ne contrôle que les pages situées sous son propre chemin.
 * Servi depuis `/wp-content/themes/180c-theme/assets/onesignal/`, il ne verrait
 * jamais `/recettes/` ni `/mon-compte/`. Le scope utile est donc l'origine, et
 * la seule façon de l'obtenir sans écrire à la racine du serveur est de servir
 * le fichier depuis une URL racine — d'où cette règle de réécriture.
 *
 * LE FICHIER SOURCE NE DOIT JAMAIS ÊTRE ÉDITÉ
 * -------------------------------------------
 * `assets/onesignal/OneSignalSDKWorker.js` est la copie EXACTE, octet pour
 * octet, du fichier téléchargé depuis la console OneSignal : une seule ligne
 * `importScripts`, sans commentaire ni ajout. Toute explication sur ce fichier
 * vit ici, dans le code qui le sert, précisément pour qu'il reste conforme et
 * comparable à l'original. Le SDK remplace de toute façon le worker par sa
 * propre implémentation : y écrire quoi que ce soit serait sans effet.
 *
 * La version du SDK (v16) doit rester alignée sur celle chargée côté page par
 * `src/js/modules/push-sdk.js`. Un worker v16 avec une page v15 ne s'apparient
 * pas, et la subscription échoue silencieusement.
 *
 * Cette voie évite trois écueils :
 *   - aucun fichier n'est ajouté à la racine du thème, ce qui ferait échouer la
 *     garde racine du pipeline de déploiement (allow-list bidirectionnelle,
 *     .github/workflows/deploy.yml) ;
 *   - aucun `.htaccess` n'est requis, ce qui serait fragile en hébergement mutualisé ;
 *   - la source reste versionnée dans le dépôt, sous `assets/`, dossier déjà
 *     déployé.
 *
 * CACHE
 * -----
 * Le CDN de l'hébergeur sert les assets du thème en `max-age=900`. Un worker figé quinze
 * minutes rendrait tout correctif impraticable : la réponse est donc émise en
 * `no-store`, et la page est exclue du cache WP Super Cache via `DONOTCACHEPAGE`.
 *
 * Le pattern de réécriture reprend celui, déjà éprouvé dans ce thème, du fichier
 * d'association de domaine Apple Pay (`inc/woo/apple-google-pay.php`), auquel
 * s'ajoute un flush versionné : `add_rewrite_rule()` ne suffit pas, les règles
 * étant persistées en base.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Version du jeu de règles de réécriture du module push.
 *
 * À incrémenter à CHAQUE modification de `_180c_push_register_rewrite()`. C'est
 * le seul déclencheur du flush : sans incrément, une règle modifiée ne serait
 * jamais réécrite en base, et l'URL retomberait en 404.
 */
define( '_180C_PUSH_REWRITE_VERSION', 1 );

/**
 * Nom de l'option portant la version des règles réellement écrites en base.
 */
define( '_180C_PUSH_REWRITE_OPTION', '_180c_push_rewrite_version' );

/**
 * Nom de la query var interne du handler.
 */
define( '_180C_PUSH_WORKER_QUERY_VAR', '_180c_push_worker' );

/**
 * Chemin disque du fichier source du service worker.
 *
 * @return string Chemin absolu.
 */
function _180c_push_worker_path() {
	return _180C_THEME_DIR . '/assets/onesignal/OneSignalSDKWorker.js';
}

/**
 * URL publique du service worker, telle qu'elle doit être passée au SDK.
 *
 * Le SDK OneSignal attend un chemin RELATIF à l'origine (`serviceWorkerPath`),
 * pas une URL absolue : on renvoie donc le nom de fichier nu.
 *
 * @return string
 */
function _180c_push_worker_filename() {
	return 'OneSignalSDKWorker.js';
}

/**
 * Enregistre la règle de réécriture exposant le worker à la racine.
 *
 * @return void
 */
function _180c_push_register_rewrite() {
	add_rewrite_rule(
		'^' . preg_quote( _180c_push_worker_filename(), '/' ) . '$',
		'index.php?' . _180C_PUSH_WORKER_QUERY_VAR . '=1',
		'top'
	);
}
add_action( 'init', '_180c_push_register_rewrite', 10 );

/**
 * Déclare la query var interne du handler.
 *
 * @param string[] $vars Query vars publiques.
 * @return string[]
 */
function _180c_push_worker_query_vars( $vars ) {
	$vars[] = _180C_PUSH_WORKER_QUERY_VAR;

	return $vars;
}
add_filter( 'query_vars', '_180c_push_worker_query_vars' );

/**
 * Réécrit les règles une seule fois, quand la version du module a changé.
 *
 * `flush_rewrite_rules()` reconstruit l'intégralité des règles et écrit une
 * option de plusieurs kilo-octets : l'appeler à chaque chargement coûterait une
 * écriture par requête. Le garde par option le réduit à un appel après chaque
 * déploiement modifiant les règles.
 *
 * Branché en priorité 20 : les règles doivent être déclarées (priorité 10)
 * avant d'être écrites.
 *
 * @return void
 */
function _180c_push_maybe_flush_rewrite() {
	$stored = (int) get_option( _180C_PUSH_REWRITE_OPTION, 0 );

	if ( _180C_PUSH_REWRITE_VERSION === $stored ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( _180C_PUSH_REWRITE_OPTION, _180C_PUSH_REWRITE_VERSION, false );
}
add_action( 'init', '_180c_push_maybe_flush_rewrite', 20 );

/**
 * Sert le service worker et termine la requête.
 *
 * Aucun rendu WordPress : ni `<head>`, ni template, ni filtre de contenu. Le
 * `exit` est indispensable — un service worker dont le corps contiendrait du
 * HTML serait rejeté par le navigateur.
 *
 * Branché en priorité 0, et c'est nécessaire : `redirect_canonical()` occupe
 * `template_redirect` en priorité 10 et, ne reconnaissant pas cette URL comme
 * un fichier, la renvoyait en 301 vers `/OneSignalSDKWorker.js/`. Mesuré en
 * local avant correction. Le navigateur refuse d'enregistrer un worker servi
 * derrière une redirection : le module entier tombait.
 *
 * @return void
 */
function _180c_push_serve_worker() {
	if ( ! get_query_var( _180C_PUSH_WORKER_QUERY_VAR ) ) {
		return;
	}

	$file = _180c_push_worker_path();

	if ( ! file_exists( $file ) ) {
		status_header( 404 );
		exit;
	}

	// WP Super Cache met en tampon dès la phase 1, bien avant le thème. Sans ce
	// drapeau, la réponse pourrait être figée en fichier statique et servie
	// ensuite sans repasser par ce handler — donc sans ses en-têtes de cache.
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constante de l'écosystème des plugins de cache (WP Super Cache, W3TC) ; la préfixer la rendrait inopérante.
		define( 'DONOTCACHEPAGE', true );
	}

	status_header( 200 );
	header( 'Content-Type: application/javascript; charset=UTF-8' );

	// `no-store` et non `no-cache` : le CDN de l'hébergeur sert les assets du thème en
	// max-age=900, et un worker périmé quinze minutes rendrait tout correctif
	// impraticable. Le navigateur, lui, revalide de toute façon le worker.
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	// Le scope est déjà obtenu par l'URL racine ; cet en-tête est une ceinture
	// de sécurité, l'audit du 2026-09-04 n'ayant pas pu vérifier le
	// comportement de l'hébergeur sur les en-têtes personnalisés.
	header( 'Service-Worker-Allowed: /' );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Sortie en flux d'un fichier local du thème ; WP_Filesystem lit en mémoire et n'apporte rien ici.
	readfile( $file );
	exit;
}
add_action( 'template_redirect', '_180c_push_serve_worker', 0 );
