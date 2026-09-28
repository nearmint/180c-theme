<?php
/**
 * Endpoint de session — nonces frais pour une page servie depuis le cache.
 *
 * Problème résolu
 * ---------------
 * WP Super Cache sert le HTML anonyme depuis un fichier statique. Les nonces
 * (`_180c.nonce` pour l'API REST, `_180cCart.nonce` pour le panier) sont
 * inlinés dans ce HTML par `inc/enqueue.php` et `inc/woo/cart.php`. Or un nonce
 * WordPress vit 24 h au plus (deux ticks de 12 h) : passé ce délai, la page en
 * cache porte un nonce périmé et TOUT appel qui s'en sert échoue — en 403
 * `rest_cookie_invalid_nonce` côté REST, en 0/`-1` côté `wc-ajax`. L'échec est
 * silencieux pour le visiteur : le bouton ne fait simplement rien.
 *
 * Cette route rend les mêmes nonces, à la demande et jamais en cache, de sorte
 * que l'âge du HTML n'entre plus en ligne de compte.
 *
 * Périmètre volontairement minimal
 * --------------------------------
 * La réponse ne contient que les deux nonces et l'état de connexion. Aucune
 * donnée personnelle : la route est publique en lecture, elle doit rester
 * inintéressante pour qui la scrute. `is_logged_in` n'apprend rien à l'appelant
 * qu'il ne sache déjà — il ne renseigne QUI n'est connecté que si l'on est déjà
 * en possession du cookie de session.
 *
 * Un nonce n'est pas un secret : il est déjà présent en clair dans le HTML de
 * chaque page. Il est lié à la session (cookie) et à l'action, donc le servir
 * ici n'ouvre aucun accès qu'un simple `GET /` n'ouvrait pas déjà. C'est aussi
 * exactement le modèle des routes `newsletter/nonce` et `contact/nonce` déjà en
 * place.
 *
 * Un appelant anonyme reçoit les nonces de la session anonyme — ce sont ceux
 * dont le panier a besoin, l'ajout au panier ne demandant pas de compte.
 *
 * Pourquoi la requête n'envoie PAS de nonce, et ce que ça impose ici
 * -----------------------------------------------------------------
 * `refreshSession()` (src/js/modules/session.js) appelle cette route SANS
 * en-tête `X-WP-Nonce`, et ce n'est pas un oubli : c'est structurel. Le cas
 * qu'elle traite est justement celui d'un nonce périmé — l'envoyer ferait
 * répondre 403 à WordPress avant même que la route s'exécute, et la seule voie
 * de récupération disparaîtrait.
 *
 * Mais une requête REST sans nonce est dégradée en anonyme par le cœur :
 * `rest_cookie_check_errors()` (wp-includes/rest-api.php) exécute
 * `wp_set_current_user( 0 )` quand aucun nonce n'est présent. Sans la
 * réhydratation ci-dessous, la route rendrait donc des nonces de la session
 * ANONYME à un visiteur connecté — nonces que `wp_verify_nonce()` rejette
 * ensuite, puisqu'il les recalcule à partir de l'utilisateur courant. Le client
 * écrasait alors le nonce valide de sa page par un nonce invalide, et tous les
 * appels REST/`wc-ajax` des comptes connectés échouaient en 403 (ajout au
 * carnet, ajout au panier), sans que le rejeu de `restFetch()` puisse aider :
 * il relisait la même valeur anonyme.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre GET /180c/v1/session.
 *
 * @return void
 */
function _180c_rest_register_session() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/session',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_session',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', '_180c_rest_register_session' );

/**
 * Indique si la requête courante provient à coup sûr d'une page du site.
 *
 * Garde indispensable à la réhydratation ci-dessous : WordPress répond aux
 * requêtes REST inter-origines avec `Access-Control-Allow-Credentials: true`
 * (`rest_send_cors_headers()`), si bien qu'un site tiers peut appeler cette
 * route avec les cookies de la victime et LIRE la réponse. C'est précisément
 * pourquoi le cœur dégrade en anonyme toute requête REST sans nonce : servir un
 * nonce `wp_rest` valide à un appelant inter-origines ouvrirait le CSRF sur
 * toute l'API. On ne réhydrate donc la session que si l'origine est prouvée.
 *
 *  - `Sec-Fetch-Site` est posé par le navigateur et figure parmi les en-têtes
 *    interdits à `fetch()` : une page tierce ne peut pas le falsifier.
 *  - Repli `Origin` / `Referer` pour les navigateurs sans `Sec-Fetch-*`
 *    (Safari < 16.4) — tout aussi infalsifiables depuis du JS de page.
 *  - Aucun des deux (client hors navigateur, navigation directe) : pas de
 *    réhydratation. La réponse reste celle, anonyme, d'aujourd'hui.
 *
 * @return bool
 */
function _180c_rest_session_is_same_origin(): bool {
	if ( isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) {
		$site = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) );

		return in_array( $site, array( 'same-origin', 'same-site' ), true );
	}

	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

	foreach ( array( 'HTTP_ORIGIN', 'HTTP_REFERER' ) as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}

		$candidate = strtolower(
			(string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER[ $header ] ) ), PHP_URL_HOST )
		);

		// Le premier en-tête présent tranche : un Origin tiers ne doit pas
		// pouvoir être rattrapé par un Referer complaisant.
		return '' !== $candidate && $candidate === $host;
	}

	return false;
}

/**
 * Rétablit l'utilisateur courant à partir du cookie de session.
 *
 * Contre-mesure au `wp_set_current_user( 0 )` que le cœur applique aux requêtes
 * REST dépourvues de nonce (cf. l'en-tête de fichier). La validation employée
 * est celle de WordPress lui-même — `wp_validate_auth_cookie()` vérifie
 * signature, expiration et jeton de session : aucune confiance n'est accordée
 * au cookie au-delà de ce que le cœur accorderait sur une page normale.
 *
 * Les nonces générés ensuite le sont donc pour le bon utilisateur ET le bon
 * jeton de session, seule façon qu'ils passent `wp_verify_nonce()` côté appel
 * réel.
 *
 * @return void
 */
function _180c_rest_session_restore_user(): void {
	if ( is_user_logged_in() || ! _180c_rest_session_is_same_origin() ) {
		return;
	}

	$user_id = wp_validate_auth_cookie( '', 'logged_in' );

	if ( $user_id ) {
		wp_set_current_user( $user_id );
	}
}

/**
 * Handler GET /session — nonces frais + état de connexion.
 *
 * Les en-têtes anti-cache sont posés explicitement en plus de
 * `nocache_headers()` : le cœur WP envoie déjà ces en-têtes sur `/wp-json/`
 * (vérifié en production : `Cache-Control: no-store, no-cache, must-revalidate`),
 * mais cette route ne doit pas dépendre d'un comportement qu'une extension de
 * cache ou un CDN pourrait modifier sans prévenir. Une réponse mise en cache
 * ici recréerait exactement le bug qu'elle corrige, en pire : le nonce périmé
 * serait alors servi à la place du frais.
 *
 * @return WP_REST_Response
 */
function _180c_rest_session(): WP_REST_Response {
	// AVANT toute création de nonce : sans cela ils seraient calculés pour
	// l'utilisateur 0 et rejetés à l'usage par un compte connecté.
	_180c_rest_session_restore_user();

	$response = new WP_REST_Response(
		array(
			// Nonce de l'API REST WP (en-tête `X-WP-Nonce`) : favoris, gift,
			// auteur, archive recettes, désinscription, opt-in Mon compte.
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			// Nonce des endpoints `wc-ajax` du panier (`inc/woo/cart.php`).
			'cart_nonce'   => wp_create_nonce( '180c_cart' ),
			// Nonce des formulaires newsletter publics. Les formulaires vont
			// déjà chercher le leur juste avant l'envoi (`newsletter/nonce`) ;
			// le servir ici sert le REPLI : si le réseau lâche au
			// moment de la soumission, le champ caché porte alors une valeur
			// rafraîchie au chargement plutôt que celle, périmée, du cache page.
			'nl_nonce'     => wp_create_nonce( '180c_newsletter_public' ),
			// Nonce du formulaire de contact. Même rôle de REPLI que `nl_nonce`
			// ci-dessus : contact.js va chercher le sien sur `contact/nonce` juste
			// avant l'envoi, mais si le réseau lâche à ce moment-là, le champ caché
			// porte alors une valeur rafraîchie au premier signe d'intention plutôt
			// que celle, vide, du HTML en cache.
			'cf_nonce'     => wp_create_nonce( _180C_CONTACT_NONCE_ACTION ),
			'is_logged_in' => is_user_logged_in(),
		),
		200
	);

	nocache_headers();
	$response->header( 'Cache-Control', 'no-store, max-age=0' );

	return $response;
}
