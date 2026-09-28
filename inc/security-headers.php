<?php
/**
 * En-têtes de sécurité HTTP.
 *
 * Pose le socle d'en-têtes défensifs sur les réponses HTML générées par PHP,
 * et supprime les deux fuites de version les plus évidentes (`X-Powered-By`,
 * `<meta name="generator">`).
 *
 * PORTÉE RÉELLE — à lire avant de conclure quoi que ce soit d'un `curl` :
 *
 *  1. `send_headers` ne se déclenche PAS sur un hit de WP Super Cache.
 *     `advanced-cache.php` sert le fichier et fait `exit` avant que le cycle
 *     WordPress ne démarre. WPSC rejoue une liste blanche d'en-têtes mémorisés
 *     à la génération, dont on sait qu'elle est partielle (elle ne contient pas
 *     `Link`, absent des réponses en cache alors que le core le pose) et
 *     antérieure à `Referrer-Policy` comme à `Content-Security-Policy`.
 *     Autrement dit : les pages anonymes en cache — l'essentiel du trafic —
 *     ne sont couvertes ici que si WPSC veut bien rejouer ces en-têtes.
 *     Le vecteur qui les couvre à coup sûr est `mod_headers` dans le
 *     `.htaccess` racine (runbook dans Notion — Architecture technique), appliqué
 *     manuellement en SFTP puisqu'il est hors du périmètre de la CI.
 *
 *  2. Les fichiers statiques (CSS, JS, images, PDF) sont servis directement par
 *     Apache, sans PHP. Même conclusion : `.htaccess`.
 *
 *  3. Les réponses REST ne passent pas ici non plus : `rest_api_loaded()`
 *     court-circuite le cycle sur `parse_request`, avant `WP::send_headers()`.
 *     Sans impact pour `180c/v1`, consommé par les apps natives iOS et Android
 *     qui n'appliquent aucune de ces politiques navigateur.
 *
 * Ce module reste malgré tout le seul vecteur qui passe par le pipeline de
 * déploiement habituel, et il couvre les pages jamais mises en cache — panier,
 * commande, mon-compte, sessions connectées —, c'est-à-dire les plus sensibles.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Durée de vie du HSTS, en secondes.
 *
 * Surchargeable dans `wp-config.php`, qui est chargé bien avant le thème. C'est
 * volontairement le seul levier de progression : monter de palier ne demande
 * aucun déploiement, et redescendre non plus.
 *
 *   Palier 1  300         5 minutes. Valeur de départ, jetable.
 *   Palier 2  15768000    6 mois. Après 48 h de palier 1 sans incident.
 *   Palier 3  31536000    1 an, à combiner avec includeSubDomains.
 *
 * Une valeur nulle ou négative désactive entièrement l'en-tête : c'est le
 * rollback le plus rapide, mais il ne rappelle PAS les navigateurs qui ont déjà
 * mémorisé la politique. Ils resteront en HTTPS forcé jusqu'à expiration du
 * `max-age` qu'ils ont reçu — d'où les paliers.
 */
if ( ! defined( '_180C_HSTS_MAX_AGE' ) ) {
	define( '_180C_HSTS_MAX_AGE', 300 );
}

/**
 * Étend le HSTS aux sous-domaines.
 *
 * À ne passer à `true` qu'une fois vérifié que TOUS les sous-domaines de l'hôte
 * servent un HTTPS valide : la directive les rend inaccessibles en HTTP, sans
 * recours côté visiteur.
 *
 * Attention à ce que couvre réellement la directive. Émise depuis
 * `www.180c.fr`, elle ne concerne que `*.www.180c.fr`, c'est-à-dire rien, et
 * elle ne protège pas l'apex. La redirection `180c.fr` → `www` est servie par
 * Apache sans passer par PHP : couvrir l'apex impose le `.htaccess`, hors de
 * portée de ce module (runbook dans Notion — Architecture technique).
 */
if ( ! defined( '_180C_HSTS_INCLUDE_SUBDOMAINS' ) ) {
	define( '_180C_HSTS_INCLUDE_SUBDOMAINS', false );
}

/**
 * Construit la valeur de l'en-tête `Strict-Transport-Security`.
 *
 * `preload` est délibérément absent : la soumission à la liste des navigateurs
 * est externe au site et de fait irréversible à l'échelle du parc installé.
 *
 * @return string Valeur de l'en-tête, ou chaîne vide si le HSTS est désactivé.
 */
function _180c_hsts_header_value() {
	$max_age = (int) _180C_HSTS_MAX_AGE;

	if ( $max_age <= 0 ) {
		return '';
	}

	$value = 'max-age=' . $max_age;

	if ( _180C_HSTS_INCLUDE_SUBDOMAINS ) {
		$value .= '; includeSubDomains';
	}

	return $value;
}

/**
 * Active la CSP en observation (`Content-Security-Policy-Report-Only`).
 *
 * Passer la constante à `false` dans `wp-config.php` coupe l'émission sans
 * déploiement. L'en-tête ne bloque rien par construction : il ne fait que
 * reporter dans la console du navigateur ce qu'une CSP bloquerait.
 */
if ( ! defined( '_180C_CSP_REPORT_ONLY' ) ) {
	define( '_180C_CSP_REPORT_ONLY', true );
}

/**
 * Politique CSP d'observation, directive par directive.
 *
 * Construite à partir de l'inventaire des tiers réellement chargés en front,
 * relevé sur le HTML servi par la production et sur un tunnel de commande joué
 * avec un panier réel — pas sur une lecture du code, qui référence des hôtes
 * jamais atteints par le navigateur (Formspree, appelé côté serveur ;
 * fonts.googleapis.com, réservé aux e-mails Mailchimp).
 *
 * `'unsafe-inline'` figure d'emblée sur `script-src` et `style-src`. Ce n'est
 * pas un renoncement : le site émet 9 à 11 blocs `<script>` inline et 5 blocs
 * `<style>` par page, dont le bootstrap Consent Mode qui doit s'exécuter avant
 * tout le reste. Les inclure évite de noyer le rapport sous des centaines de
 * violations déjà connues, pour laisser apparaître le seul inconnu utile : les
 * hôtes tiers manquants. La sortie par nonce est documentée comme dette dans
 * le runbook Notion (Architecture technique).
 *
 * `report-uri` est volontairement absent : collecter les rapports supposerait
 * un endpoint public sur un hébergement mutualisé, exposé à un flood trivial.
 * La collecte se fait à la main, console ouverte, sur la checklist de parcours.
 *
 * @return array<string, string> Directives indexées par nom.
 */
function _180c_csp_report_only_directives() {
	$directives = array(
		'default-src'     => "'self'",
		'script-src'      => "'self' 'unsafe-inline' https://cloud.umami.is https://www.googletagmanager.com https://js.stripe.com https://m.stripe.net https://b.stripecdn.com https://www.paypal.com https://www.paypalobjects.com https://challenges.cloudflare.com https://cdn.onesignal.com",
		'style-src'       => "'self' 'unsafe-inline'",
		'img-src'         => "'self' data: blob: https://img.youtube.com https://i.ytimg.com https://www.google-analytics.com https://www.googletagmanager.com https://q.stripe.com https://www.paypal.com https://www.paypalobjects.com https://secure.gravatar.com",
		'connect-src'     => "'self' https://cloud.umami.is https://gateway.umami.is https://www.google-analytics.com https://region1.google-analytics.com https://analytics.google.com https://www.googletagmanager.com https://api.stripe.com https://m.stripe.net https://r.stripe.com https://www.paypal.com https://challenges.cloudflare.com https://api.onesignal.com https://*.os.tc",
		'frame-src'       => "'self' https://js.stripe.com https://hooks.stripe.com https://m.stripe.network https://www.youtube-nocookie.com https://www.youtube.com https://www.paypal.com https://challenges.cloudflare.com",
		'font-src'        => "'self' data:",
		// Le service worker OneSignal est servi depuis l'origine
		// (`/OneSignalSDKWorker.js`, cf. inc/push/worker.php) ; il n'importe
		// le SDK distant qu'ensuite, par `importScripts`, ce que gouverne
		// `script-src`. `'self'` suffit donc ici.
		'worker-src'      => "'self'",
		'object-src'      => "'none'",
		'base-uri'        => "'self'",
		'frame-ancestors' => "'self'",
	);

	/**
	 * Filtre les directives de la CSP d'observation.
	 *
	 * Permet d'ajouter un hôte découvert en cours de campagne sans redéployer,
	 * depuis un mu-plugin ou `wp-config.php`.
	 *
	 * @param array<string, string> $directives Directives indexées par nom.
	 */
	return (array) apply_filters( '180c/csp_report_only', $directives );
}

/**
 * Sérialise les directives en valeur d'en-tête CSP.
 *
 * @param array<string, string> $directives Directives indexées par nom.
 * @return string Valeur prête à émettre, vide si aucune directive.
 */
function _180c_csp_serialize( array $directives ) {
	$parts = array();

	foreach ( $directives as $name => $value ) {
		$name  = trim( (string) $name );
		$value = trim( (string) $value );

		if ( '' === $name ) {
			continue;
		}

		$parts[] = '' === $value ? $name : $name . ' ' . $value;
	}

	return implode( '; ', $parts );
}

/**
 * Émet les en-têtes de sécurité sur la réponse courante.
 *
 * Volontairement limité aux directives sans risque de casse :
 *
 *  - `X-Frame-Options` et `frame-ancestors` verrouillent l'inclusion du site
 *    dans une iframe tierce (clickjacking). Les iframes sortantes — Stripe,
 *    PayPal, la façade YouTube — relèvent de `frame-src`, non restreint ici.
 *  - `object-src 'none'` neutralise `<object>`/`<embed>`, jamais utilisés.
 *  - `base-uri 'self'` empêche une injection de `<base>` de détourner les URL
 *    relatives de la page.
 *
 * La CSP n'expose délibérément ni `default-src` ni `script-src` : sans ces
 * directives aucun script n'est bloqué, donc aucune régression possible. Le
 * durcissement scripts/styles fait l'objet d'un lot séparé, en report-only
 * d'abord.
 *
 * `header()` est appelé avec son `$replace` par défaut (true) : si WordPress a
 * déjà posé `X-Frame-Options` — ce qu'il fait sur l'admin et sur l'écran de
 * connexion via `send_frame_options_header()` — la valeur est remplacée, pas
 * dupliquée.
 *
 * Ce même `$replace` joue contre nous sur `/commande/` et `/mon-compte/` :
 * `wc_send_frame_options_header()` (WooCommerce, `template_redirect` prio 10)
 * y appelle `send_frame_options_header()`, qui réécrit la CSP entière avec le
 * seul `frame-ancestors 'self';` du core — et efface donc `object-src` et
 * `base-uri`. `template_redirect` s'exécutant après `send_headers`, la fonction
 * est ré-branchée en fin de `template_redirect` pour reprendre la main. Mesuré :
 * sans ce second accrochage, `/commande/` ne renvoie que la CSP du core.
 *
 * @return void
 */
function _180c_security_headers() {
	if ( headers_sent() ) {
		return;
	}

	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( "Content-Security-Policy: frame-ancestors 'self'; object-src 'none'; base-uri 'self'" );

	// CSP en observation. Les deux hooks qui appellent cette fonction sont des
	// hooks de front-office — `WP::send_headers()` et `template_redirect` ne
	// s'exécutent ni sur l'admin ni sur `wp-login.php` —, la politique ne peut
	// donc pas atteindre l'éditeur. L'aperçu du personnalisateur est exclu
	// explicitement : il injecte ses propres scripts et noierait le rapport.
	if ( _180C_CSP_REPORT_ONLY && ! is_customize_preview() ) {
		$csp = _180c_csp_serialize( _180c_csp_report_only_directives() );
		if ( '' !== $csp ) {
			header( 'Content-Security-Policy-Report-Only: ' . $csp );
		}
	}

	// HSTS. La garde `is_ssl()` n'est pas cosmétique : envoyé sur une réponse
	// HTTP, l'en-tête est ignoré par la spécification, mais un environnement de
	// développement servi en clair n'a aucune raison de l'émettre.
	if ( is_ssl() ) {
		$hsts = _180c_hsts_header_value();
		if ( '' !== $hsts ) {
			header( 'Strict-Transport-Security: ' . $hsts );
		}
	}

	// Fuite de version PHP. Émis par le moteur lui-même (`expose_php`), donc
	// retiré ici plutôt que jamais produit : sur une réponse servie depuis le
	// cache, seul `mod_headers` peut encore l'enlever.
	header_remove( 'X-Powered-By' );
}
add_action( 'send_headers', '_180c_security_headers' );
add_action( 'template_redirect', '_180c_security_headers', PHP_INT_MAX );

/*
 * Retire `<meta name="generator" content="WordPress x.y.z" />` du `<head>`.
 *
 * Retire aussi celle de WooCommerce, sans avoir à la viser : le plugin ajoute
 * la sienne par le filtre `get_the_generator_html`, qui n'est appelé que depuis
 * `wp_generator()`. Couper l'action à la racine coupe toute la chaîne. Vérifié
 * en production : avant, deux balises `generator` (WordPress et
 * WooCommerce) ; après, aucune, y compris sur les pages WooCommerce.
 */
remove_action( 'wp_head', 'wp_generator' );
