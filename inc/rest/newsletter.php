<?php
/**
 * Endpoints REST newsletter — proxy Mailchimp API v3.
 *
 * Routes :
 *   POST /180c/v1/newsletter/subscribe/
 *   POST /180c/v1/newsletter/unsubscribe/
 *   GET  /180c/v1/newsletter/status/
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', '_180c_rest_register_newsletter' );

/**
 * Enregistre les routes REST newsletter.
 *
 * @return void
 */
function _180c_rest_register_newsletter() {
	// POST /newsletter/subscribe/.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/newsletter/subscribe',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_rest_newsletter_subscribe',
			'permission_callback' => '__return_true',
			'args'                => array(
				'email'      => array(
					'required'          => true,
					'type'              => 'string',

					/*
					 * `validate_callback` explicite, OBLIGATOIRE ici.
					 *
					 * Le cœur n'assigne `rest_parse_request_arg` — qui valide le
					 * schéma avant d'assainir — qu'aux arguments SANS
					 * `sanitize_callback` (class-wp-rest-request.php:858-861).
					 * En déclarer un désactive donc la vérification de `type`, et
					 * `sanitize_email()` recevait la valeur brute.
					 *
					 * Or `sanitize_email()` appelle `strlen()` sans cast : un
					 * `{"email": []}` sur cette route PUBLIQUE et sans nonce
					 * provoquait un `TypeError` non rattrapé — 500 en local, 503
					 * en production. Constaté le 2026-09-10.
					 *
					 * `has_valid_params()` s'exécutant avant `sanitize_params()`
					 * (class-wp-rest-server.php:1115/1119), la valeur non scalaire
					 * est désormais refusée en 400 avant d'atteindre le sanitizer.
					 */
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_email',
					'description'       => __( 'Adresse e-mail à inscrire.', '180c' ),
				),
				'list'       => array(
					'required'    => false,
					'type'        => 'string',
					'enum'        => array( 'free', 'premium' ),
					'default'     => 'free',
					'description' => __( 'Identifiant de la liste (free ou premium) — contrat site web.', '180c' ),
				),
				// Contrat apps mobiles : ID de liste Mailchimp brut.
				'list_id'    => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => __( 'ID de liste Mailchimp (apps mobiles, JWT requis).', '180c' ),
				),
				// Contrat apps mobiles : action sur la liste.
				'action'     => array(
					'required'    => false,
					'type'        => 'string',
					'enum'        => array( 'subscribe', 'unsubscribe', 'status' ),
					'description' => __( 'Action newsletter (subscribe|unsubscribe|status) — apps mobiles.', '180c' ),
				),
				'first_name' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'last_name'  => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				// Traçabilité de la source (tags src-plt/src-loc, cf.
				// inc/mailchimp/source-tags.php). Valeur libre acceptée mais
				// résolue par allow-list : un slug inconnu ne crée aucun tag.
				'source'     => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => __( 'Slug de la source d’inscription (allow-list serveur).', '180c' ),
				),
			),
		)
	);

	// POST /newsletter/unsubscribe/.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/newsletter/unsubscribe',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_rest_newsletter_unsubscribe',
			'permission_callback' => '_180c_rest_jwt_or_cookie',
			'args'                => array(
				'email' => array(
					'required'          => true,
					'type'              => 'string',
					// Même fatal que sur /newsletter/subscribe : voir le
					// commentaire détaillé à l'enregistrement de cette route.
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_email',
				),
				'list'  => array(
					'required' => false,
					'type'     => 'string',
					'enum'     => array( 'free', 'premium' ),
					'default'  => 'free',
				),
			),
		)
	);

	// GET /newsletter/status/.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/newsletter/status',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_newsletter_status',
			'permission_callback' => '_180c_rest_jwt_or_cookie',
		)
	);

	// GET|POST /newsletter/account-optin/ — toggle opt-in « Cahiers de Delphine »
	// depuis Mon Compte (utilisateur connecté). Branché sur la MÊME liste que la
	// page Newsletter (liste « free »), single opt-in idempotent.
	// GET → { optin } (état réel de l'abonnement à l'audience), pour que le toggle
	// reflète Mailchimp et non la seule meta locale.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/newsletter/account-optin',
		array(
			'methods'             => 'GET, POST',
			'callback'            => '_180c_rest_newsletter_account_optin',
			'permission_callback' => '_180c_rest_jwt_or_cookie',
			'args'                => array(
				'optin'  => array(
					'required' => false,
					'type'     => 'boolean',
				),
				// Traçabilité de la source (cf. inc/mailchimp/source-tags.php).
				'source' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);

	// GET|POST /newsletter/premium/ — opt-in premium (tag Mailchimp « Abonnés Premium »).
	// GET  → { optin: bool } (présence du tag chez l'utilisateur connecté).
	// POST → add/remove du tag selon { optin }. Réservé aux abonnés premium.
	// Auth : cookie+nonce (site) OU JWT (apps), via _180c_rest_jwt_or_cookie.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/newsletter/premium',
		array(
			'methods'             => 'GET, POST',
			'callback'            => '_180c_rest_newsletter_premium',
			'permission_callback' => '_180c_rest_jwt_or_cookie',
			'args'                => array(
				'optin'  => array(
					'required' => false,
					'type'     => 'boolean',
				),
				// Traçabilité de la source (cf. inc/mailchimp/source-tags.php).
				'source' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);

	// POST /newsletter/public-subscribe/ — formulaire public anonyme (page newsletter).
	// Aucune authentification : la protection repose sur nonce + honeypot + rate-limit IP.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/newsletter/public-subscribe',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_rest_newsletter_public_subscribe',
			'permission_callback' => '__return_true',
			'args'                => array(
				// Traçabilité de la source (cf. inc/mailchimp/source-tags.php).
				// Les autres paramètres (email, nl_nonce, _gotcha, list_id) restent
				// lus et validés dans le handler, pipeline à court-circuit.
				'source' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);

	// GET /newsletter/nonce/ — nonce frais pour le formulaire public (robustesse
	// cache : une page HTML servie depuis un cache peut porter un nonce périmé).
	register_rest_route(
		_180C_API_NAMESPACE,
		'/newsletter/nonce',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_newsletter_nonce',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Résout l'audience Mailchimp unique du site.
 *
 * Priorité : `_180C_MC_AUDIENCE_ID` (constante réelle wp-config) → `MAILCHIMP_LIST_FREE`
 * (legacy, dépréciée) → chaîne vide. Le modèle 180°C n'utilise
 * qu'une seule audience ; la distinction gratuit / premium se fait par tag
 * (`_180C_MC_TAG_PREMIUM`), jamais par une liste séparée.
 *
 * @return string ID d'audience Mailchimp (vide si aucune constante n'est définie).
 */
function _180c_nl_audience_id(): string {
	if ( defined( '_180C_MC_AUDIENCE_ID' ) && '' !== (string) _180C_MC_AUDIENCE_ID ) {
		return (string) _180C_MC_AUDIENCE_ID;
	}
	if ( defined( 'MAILCHIMP_LIST_FREE' ) && '' !== (string) MAILCHIMP_LIST_FREE ) {
		return (string) MAILCHIMP_LIST_FREE; // Legacy back-compat (constante dépréciée).
	}
	return '';
}

/**
 * Résout l'ID de liste Mailchimp à partir du slug ('free' ou 'premium').
 *
 * Modèle « une seule audience » (audit 2026-06, D4) : `free` comme `premium`
 * résolvent vers l'audience unique. Le slug est conservé pour la compatibilité
 * d'appel mais n'altère plus l'audience résolue — la distinction premium passe
 * par le tag (cf. inc/mailchimp/premium-tag.php), plus aucun 500 sur constante
 * `MAILCHIMP_LIST_PREMIUM` absente.
 *
 * @param string $list Slug de la liste ('free' | 'premium'), informatif.
 * @return string ID d'audience Mailchimp.
 */
function _180c_mailchimp_list_id( $list ) {
	unset( $list );
	return _180c_nl_audience_id();
}

/**
 * Effectue une requête vers l'API Mailchimp v3.
 *
 * @param string     $method   Méthode HTTP (GET, POST, PUT, PATCH).
 * @param string     $endpoint Chemin relatif (ex: '/lists/{id}/members/{hash}').
 * @param array|null $body     Corps de la requête (sera encodé en JSON).
 * @return array|WP_Error Réponse décodée ou WP_Error.
 */
function _180c_mailchimp_request( $method, $endpoint, $body = null ) {
	if ( ! defined( 'MAILCHIMP_API_KEY' ) ) {
		_180c_log( 'MAILCHIMP_API_KEY non définie', array(), 'error' );
		return new WP_Error(
			'mailchimp_not_configured',
			__( 'Service newsletter temporairement indisponible', '180c' ),
			array( 'status' => 503 )
		);
	}

	// Extrait le datacenter depuis la clé (ex: 'abc123-us9' → 'us9').
	$api_key = MAILCHIMP_API_KEY;
	$parts   = explode( '-', $api_key );
	$dc      = end( $parts );

	if ( empty( $dc ) || $dc === $api_key ) {
		_180c_log( 'Format de clé Mailchimp invalide', array(), 'error' );
		return new WP_Error(
			'mailchimp_config_error',
			__( 'Service newsletter temporairement indisponible', '180c' ),
			array( 'status' => 503 )
		);
	}

	$url  = 'https://' . $dc . '.api.mailchimp.com/3.0' . $endpoint;
	$args = array(
		'method'  => strtoupper( $method ),
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		),
		'timeout' => 8,
	);

	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		_180c_log(
			'Erreur HTTP Mailchimp',
			array(
				'error'    => $response->get_error_message(),
				'endpoint' => $endpoint,
			),
			'error'
		);
		return new WP_Error(
			'mailchimp_http_error',
			__( 'Service newsletter temporairement indisponible', '180c' ),
			array( 'status' => 502 )
		);
	}

	$status_code  = wp_remote_retrieve_response_code( $response );
	$raw_body     = wp_remote_retrieve_body( $response );
	$decoded_body = json_decode( $raw_body, true );

	// Codes 2xx = succès.
	if ( $status_code >= 200 && $status_code < 300 ) {
		return is_array( $decoded_body ) ? $decoded_body : array();
	}

	_180c_log(
		'Réponse Mailchimp non-2xx',
		array(
			'status'   => $status_code,
			'endpoint' => $endpoint,
			'title'    => $decoded_body['title'] ?? 'unknown',
		),
		'warning'
	);

	return new WP_Error(
		'mailchimp_api_error',
		__( 'Service newsletter temporairement indisponible', '180c' ),
		array( 'status' => 502 )
	);
}

/**
 * Inscrit (ou met à jour) une adresse e-mail sur une liste Mailchimp.
 *
 * Helper réutilisable côté serveur (REST + inscription compte). Single opt-in
 * (`status_if_new = subscribed`), aligné sur les formulaires publics
 * et le toggle Mon compte (D2, audit 2026-06) ; Mailchimp ignore `status_if_new`
 * pour un membre déjà existant, donc un membre `unsubscribed`/`cleaned` n'est
 * jamais réactivé silencieusement par ce helper.
 *
 * @param string $email      Adresse e-mail.
 * @param string $list_slug  'free' ou 'premium'.
 * @param string $first_name Prénom (optionnel).
 * @param string $last_name  Nom (optionnel).
 * @return array|WP_Error Réponse Mailchimp décodée ou WP_Error.
 */
function _180c_mailchimp_subscribe( $email, $list_slug = 'free', $first_name = '', $last_name = '' ) {
	$email = sanitize_email( $email );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', __( 'Adresse e-mail invalide', '180c' ), array( 'status' => 400 ) );
	}

	$list_id = _180c_mailchimp_list_id( $list_slug );
	if ( is_wp_error( $list_id ) ) {
		return $list_id;
	}

	$email_hash   = md5( strtolower( $email ) );
	$endpoint     = '/lists/' . $list_id . '/members/' . $email_hash;
	$merge_fields = array();

	$first_name = wp_strip_all_tags( (string) $first_name );
	$last_name  = wp_strip_all_tags( (string) $last_name );

	if ( '' !== $first_name ) {
		$merge_fields['FNAME'] = $first_name;
	}
	if ( '' !== $last_name ) {
		$merge_fields['LNAME'] = $last_name;
	}

	$body = array(
		'email_address' => $email,
		'status_if_new' => 'subscribed', // Single opt-in (aligné formulaires publics, D2).
	);

	if ( ! empty( $merge_fields ) ) {
		$body['merge_fields'] = $merge_fields;
	}

	return _180c_mailchimp_request( 'PUT', $endpoint, $body );
}

// ============================================================
// Contrat apps mobiles — proxy Mailchimp sécurisé JWT
//
// Un seul endpoint POST /newsletter/subscribe reçoit { list_id, action, email }
// avec un Bearer JWT. La clé Mailchimp ne quitte jamais le serveur (wp-config).
// Le contrat site web historique (anonyme) reste servi par la même
// route quand aucun marqueur « app » n'est présent.
// ============================================================

/**
 * Détecte le contrat « apps mobiles » sur la requête /newsletter/subscribe.
 *
 * Marqueurs : paramètre `action`, paramètre `list_id`, ou en-tête
 * `Authorization: Bearer`. Le formulaire site web n'envoie aucun de ces signaux.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return bool True si la requête suit le contrat apps (JWT requis).
 */
function _180c_nl_is_app_request( WP_REST_Request $request ): bool {
	if ( '' !== (string) $request->get_param( 'action' ) ) {
		return true;
	}
	if ( '' !== (string) $request->get_param( 'list_id' ) ) {
		return true;
	}
	$auth = (string) $request->get_header( 'Authorization' );
	return 0 === stripos( $auth, 'Bearer ' );
}

/**
 * Rate limiting newsletter (fenêtre fixe, par IP).
 *
 * Transient `180c_nl_{bucket}_{md5(ip)}` (TTL 60 s) conservant le compteur et la
 * fin de fenêtre (le TTL n'est pas réinitialisé à chaque incrément).
 *
 * **Rétro-compatibilité stricte** : appelé avec ses défauts
 * (`$bucket='rate'`, `$max=10`), la clé générée est **byte-identique** à
 * l'historique (`180c_nl_rate_{md5(ip)}`) et le plafond reste 10 — le pipeline du
 * contrat apps (`_180c_rest_newsletter_app`) est **inchangé**. Un `$bucket`
 * distinct (ex. `'web'`) isole les compteurs d'une autre surface.
 *
 * @param string $ip     Adresse IP du client.
 * @param string $bucket Compartiment logique du compteur (défaut `'rate'`).
 * @param int    $max    Plafond de requêtes par fenêtre (défaut 10).
 * @return bool True si la limite est dépassée (la requête doit être rejetée).
 */
function _180c_nl_rate_limited( string $ip, string $bucket = 'rate', int $max = 10 ): bool {
	$key   = '180c_nl_' . $bucket . '_' . md5( $ip );
	$now   = time();
	$state = get_transient( $key );

	if ( ! is_array( $state ) || empty( $state['reset'] ) || $now >= (int) $state['reset'] ) {
		set_transient(
			$key,
			array(
				'count' => 1,
				'reset' => $now + MINUTE_IN_SECONDS,
			),
			MINUTE_IN_SECONDS
		);
		return false;
	}

	if ( (int) $state['count'] >= $max ) {
		return true;
	}

	$state['count'] = (int) $state['count'] + 1;
	$ttl            = max( 1, (int) $state['reset'] - $now );
	set_transient( $key, $state, $ttl );
	return false;
}

/**
 * Liste blanche des IDs de liste Mailchimp acceptés par le contrat apps.
 *
 * Modèle « une seule audience » (audit 2026-06, D4) : le contrat apps s'inscrit
 * toujours sur l'audience unique (`_180c_nl_audience_id()`). On ne renvoie plus
 * un tableau vide quand `MAILCHIMP_LIST_FREE`/`PREMIUM` (dépréciées) sont absentes
 * — c'était la cause du `invalid_list` (400) qui bloquait toutes les apps. Pas de
 * clé 'premium' : l'enrôlement premium n'est pas une liste séparée mais le tag
 * Mailchimp (endpoint /newsletter/premium + synchro abonnement).
 *
 * @return array<string,string> Map slug => ID Mailchimp (clé 'free' uniquement).
 */
function _180c_nl_allowed_list_ids(): array {
	return array( 'free' => _180c_nl_audience_id() );
}

/**
 * Normalise un statut Mailchimp vers l'énum du contrat apps.
 *
 * @param string $mc_status Statut brut Mailchimp.
 * @return string 'subscribed' | 'unsubscribed' | 'pending'.
 */
function _180c_nl_normalize_status( string $mc_status ): string {
	if ( 'subscribed' === $mc_status ) {
		return 'subscribed';
	}
	if ( 'pending' === $mc_status ) {
		return 'pending';
	}
	// 'unsubscribed', 'cleaned', 'transactional', inconnu → désinscrit.
	return 'unsubscribed';
}

/**
 * Détecte la présence du tag premium dans le tableau `tags` d'un membre Mailchimp.
 *
 * Opère sur le payload membre DÉJÀ récupéré (`tags[] = { id, name }`) : aucun
 * appel Mailchimp supplémentaire. Comparaison par ID si le transient
 * `_180c_mc_premium_tag_id` est déjà chaud (lecture directe, sans déclencher le
 * résolveur `/segments`), sinon par nom insensible à la casse
 * (`_180c_mc_premium_tag_name()` = constante `_180C_MC_TAG_PREMIUM`).
 *
 * @param array $tags Tableau `tags` du membre (chaque entrée : { id, name }).
 * @return bool True si le tag premium est présent.
 */
function _180c_nl_member_has_premium_tag( array $tags ): bool {
	// Lecture DIRECTE du transient (jamais le résolveur, qui ferait un appel
	// Mailchimp `/segments` et briserait la garantie « un seul appel membre »).
	$cached_id   = (int) get_transient( '_180c_mc_premium_tag_id' );
	$name_needle = function_exists( '_180c_mc_premium_tag_name' )
		? strtolower( trim( _180c_mc_premium_tag_name() ) )
		: '';

	foreach ( $tags as $tag ) {
		if ( ! is_array( $tag ) ) {
			continue;
		}
		if ( $cached_id > 0 && isset( $tag['id'] ) && (int) $tag['id'] === $cached_id ) {
			return true;
		}
		if ( '' !== $name_needle && isset( $tag['name'] ) && strtolower( trim( (string) $tag['name'] ) ) === $name_needle ) {
			return true;
		}
	}

	return false;
}

/**
 * Appel bas-niveau à l'API Mailchimp v3 avec retry 3× backoff.
 *
 * Réessaie sur erreur réseau et sur réponse 5xx (backoff 200 ms, 400 ms). Ne
 * journalise jamais la clé API ni l'e-mail en clair.
 *
 * @param string     $method   Méthode HTTP (GET, PUT, PATCH…).
 * @param string     $endpoint Chemin relatif (ex: '/lists/{id}/members/{hash}').
 * @param array|null $body     Corps à encoder en JSON.
 * @return array{code:int,data:array}|WP_Error Réponse structurée ou WP_Error (503).
 */
function _180c_nl_mailchimp_call( string $method, string $endpoint, ?array $body = null ) {
	// Audience non configurée (`_180C_MC_AUDIENCE_ID` absente) : un chemin
	// `/lists//…` répondrait 404, qu'un appelant pourrait lire comme « membre
	// déjà absent ». On refuse l'appel plutôt que de laisser croire à un succès.
	if ( str_contains( $endpoint, '/lists//' ) ) {
		_180c_log( 'Audience Mailchimp non configurée (_180C_MC_AUDIENCE_ID)', array(), 'error' );
		return new WP_Error(
			'mailchimp_not_configured',
			__( 'Service newsletter temporairement indisponible', '180c' ),
			array( 'status' => 503 )
		);
	}

	if ( ! defined( 'MAILCHIMP_API_KEY' ) || '' === (string) MAILCHIMP_API_KEY ) {
		_180c_log( 'MAILCHIMP_API_KEY non définie', array(), 'error' );
		return new WP_Error(
			'mailchimp_not_configured',
			__( 'Service newsletter temporairement indisponible', '180c' ),
			array( 'status' => 503 )
		);
	}

	$api_key = (string) MAILCHIMP_API_KEY;
	$parts   = explode( '-', $api_key );
	$dc      = end( $parts );

	if ( empty( $dc ) || $dc === $api_key ) {
		_180c_log( 'Format de clé Mailchimp invalide', array(), 'error' );
		return new WP_Error(
			'mailchimp_config_error',
			__( 'Service newsletter temporairement indisponible', '180c' ),
			array( 'status' => 503 )
		);
	}

	$url  = 'https://' . $dc . '.api.mailchimp.com/3.0' . $endpoint;
	$args = array(
		'method'  => strtoupper( $method ),
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		),
		'timeout' => 8,
	);

	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}

	$max_attempts = 3;
	$response     = null;
	$status_code  = 0;

	for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
		$response = wp_remote_request( $url, $args );

		if ( ! is_wp_error( $response ) ) {
			$status_code = (int) wp_remote_retrieve_response_code( $response );
			if ( $status_code < 500 ) {
				break; // 2xx/4xx : réponse exploitable, on arrête.
			}
		}

		if ( $attempt < $max_attempts ) {
			usleep( 200000 * $attempt ); // Backoff : 200 ms puis 400 ms.
		}
	}

	if ( is_wp_error( $response ) ) {
		_180c_log( 'Erreur réseau Mailchimp (apps)', array(), 'error' );
		return new WP_Error(
			'mailchimp_unavailable',
			__( 'Service newsletter temporairement indisponible', '180c' ),
			array( 'status' => 503 )
		);
	}

	if ( $status_code >= 500 ) {
		_180c_log( 'Mailchimp 5xx (apps)', array( 'status' => $status_code ), 'error' );
		return new WP_Error(
			'mailchimp_upstream',
			__( 'Service newsletter temporairement indisponible', '180c' ),
			array( 'status' => 503 )
		);
	}

	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

	return array(
		'code' => $status_code,
		'data' => is_array( $decoded ) ? $decoded : array(),
	);
}

/**
 * Construit la réponse REST normalisée du contrat apps.
 *
 * @param string $action  Action demandée.
 * @param string $list_id ID de liste Mailchimp.
 * @param string $status  Statut normalisé.
 * @param int    $http    Code HTTP.
 * @return WP_REST_Response
 */
function _180c_nl_app_response( string $action, string $list_id, string $status, int $http ): WP_REST_Response {
	$response = rest_ensure_response(
		array(
			'list_id' => $list_id,
			'action'  => $action,
			'status'  => $status,
		)
	);
	$response->set_status( $http );
	return $response;
}

/**
 * Détermine le slug de source d'une requête app (dérivation TRANSITOIRE).
 *
 * L'app Android transmet déjà `source: "android-app"`. L'app iOS n'envoie
 * aucun `source` (audit Phase 0, écart E2) : on la reconnaît à la signature de
 * son payload, seul contrat app qui fournit `action` et/ou `list_id` explicites.
 *
 * TRANSITOIRE — au build 2, les deux apps enverront un `source` explicite
 * (`ios-app` / `android-app`) : SUPPRIMER alors l'heuristique de repli et se
 * contenter du paramètre reçu.
 *
 * @param string $raw_source  Paramètre `source` brut reçu (peut être vide).
 * @param string $raw_action  Paramètre `action` brut reçu (avant défaut).
 * @param string $raw_list_id Paramètre `list_id` brut reçu (avant défaut).
 * @return string Slug de source résolu (`ios-app`, `android-app`, ou la valeur
 *                brute reçue — l'allow-list de `_180c_mc_source_tags()` tranche).
 */
function _180c_nl_app_source_slug( string $raw_source, string $raw_action, string $raw_list_id ): string {
	$source = strtolower( trim( $raw_source ) );

	if ( '' !== $source ) {
		return $source; // Contrat explicite (Android aujourd'hui, iOS au build 2).
	}

	if ( '' !== $raw_action || '' !== $raw_list_id ) {
		return 'ios-app'; // Heuristique transitoire : contrat iOS actuel.
	}

	return ''; // Journalisé en `src-loc:unknown` par _180c_mc_source_tags().
}

/**
 * Handler du contrat apps mobiles pour POST /newsletter/subscribe.
 *
 * Pipeline : rate limit → JWT → action → e-mail (= compte JWT) → list_id
 * whitelisté → appel Mailchimp → statut normalisé.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_newsletter_app( WP_REST_Request $request ) {
	// 1. Rate limit 10 req/min/IP (avant tout traitement coûteux).
	$ip = function_exists( '_180c_get_client_ip' )
		? _180c_get_client_ip()
		: (string) ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	if ( _180c_nl_rate_limited( $ip ) ) {
		return new WP_Error(
			'rate_limited',
			__( 'Trop de requêtes. Réessayez dans une minute.', '180c' ),
			array( 'status' => 429 )
		);
	}

	// 2. Authentification obligatoire (401 si manquant/invalide/expiré).
	// Validateur UNIQUE du thème (LOT G) : session web/plugin d'abord, JWT app
	// en repli. On ne force PLUS `_180c_rest_jwt_user` (revérif HS256 contre la
	// constante), qui divergeait de `/me` et échouait en prod (`jwt_signature`)
	// quand la constante `SIMPLE_JWT_LOGIN_SECRET` ne correspondait pas à la clé
	// du plugin. Renvoie directement l'objet WP_User exploité à l'étape 4.
	$user = _180c_rest_jwt_or_cookie_user( $request );
	if ( is_wp_error( $user ) ) {
		return $user;
	}

	// Paramètres BRUTS, capturés avant application des défauts : ils portent la
	// signature du contrat appelant et servent à dériver la plateforme (T5b).
	$raw_action  = (string) $request->get_param( 'action' );
	$raw_list_id = (string) $request->get_param( 'list_id' );
	$raw_source  = (string) $request->get_param( 'source' );

	// 3. Action valide (l'énum REST filtre déjà les valeurs hors-liste).
	// `action` absente → `subscribe` par défaut : l'app Android envoie
	// `{ email, consent, source }` sans `action` ni `list_id`, mais son client
	// HTTP joint un Bearer JWT à toutes les requêtes — ce qui la faisait basculer
	// sur ce contrat et échouer en `invalid_action` (400). iOS envoie `action`
	// explicitement : son comportement est inchangé.
	$action = '' !== $raw_action ? $raw_action : 'subscribe';
	if ( ! in_array( $action, array( 'subscribe', 'unsubscribe', 'status' ), true ) ) {
		return new WP_Error(
			'invalid_action',
			__( 'Action invalide.', '180c' ),
			array( 'status' => 400 )
		);
	}

	// Consentement explicitement refusé → aucune écriture. On dégrade en lecture
	// seule plutôt que d'inscrire de force un utilisateur qui a dit non.
	$consent = $request->get_param( 'consent' );
	if ( null !== $consent && ! rest_sanitize_boolean( $consent ) ) {
		$action = 'status';
	}

	// 4. E-mail valide ET correspondant au compte authentifié (403 sinon).
	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'invalid_email',
			__( 'Adresse e-mail invalide.', '180c' ),
			array( 'status' => 400 )
		);
	}

	// $user est déjà résolu à l'étape 2 (validateur unique du thème).
	if ( ! $user || ! $user->ID || strtolower( $user->user_email ) !== strtolower( $email ) ) {
		return new WP_Error(
			'email_mismatch',
			__( 'L’e-mail ne correspond pas au compte authentifié.', '180c' ),
			array( 'status' => 403 )
		);
	}

	// 5. list_id whitelisté (IDs Mailchimp issus de wp-config uniquement).
	// `list_id` absent → audience unique par défaut (même motif Android que
	// pour `action` ci-dessus). Une valeur explicite hors whitelist reste un 400.
	$allowed     = _180c_nl_allowed_list_ids();
	$allowed_ids = array_values( $allowed );
	$list_id     = sanitize_text_field( $raw_list_id );

	if ( '' === $list_id ) {
		$list_id = _180c_nl_audience_id();
	}

	if ( '' === $list_id || ! in_array( $list_id, $allowed_ids, true ) ) {
		return new WP_Error(
			'invalid_list',
			__( 'Liste non autorisée.', '180c' ),
			array( 'status' => 400 )
		);
	}

	$is_premium = isset( $allowed['premium'] ) && $list_id === $allowed['premium'];

	// Gating premium : s'inscrire à la liste premium exige un abonnement recettes
	// actif. Le statut et la désinscription restent toujours autorisés.
	if ( $is_premium && 'subscribe' === $action && ! _180c_is_recipe_subscriber() ) {
		return new WP_Error(
			'premium_required',
			__( 'Abonnement recettes requis pour cette liste.', '180c' ),
			array( 'status' => 403 )
		);
	}

	$email_hash = md5( strtolower( trim( $email ) ) );
	$endpoint   = '/lists/' . $list_id . '/members/' . $email_hash;

	// 6. action = status : lecture seule.
	if ( 'status' === $action ) {
		$result = _180c_nl_mailchimp_call( 'GET', $endpoint );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// 404 = non membre → considéré désinscrit.
		$status = ( 200 === $result['code'] )
			? _180c_nl_normalize_status( (string) ( $result['data']['status'] ?? '' ) )
			: 'unsubscribed';

		return _180c_nl_app_response( $action, $list_id, $status, 200 );
	}

	// 7. subscribe / unsubscribe : upsert PUT (cf. cahier des charges).
	if ( 'unsubscribe' === $action ) {
		$body = array(
			'email_address' => $email,
			'status'        => 'unsubscribed',
		);
	} else {
		// Single opt-in : status_if_new = subscribed, aligné sur les formulaires
		// publics et le toggle Mon compte (D2) — le consentement est
		// déjà recueilli dans l'app, une confirmation e-mail supplémentaire faisait
		// retomber le toggle à « off » au rechargement (iOS teste `subscribed`).
		// Mailchimp ignore status_if_new pour un membre existant : aucun membre
		// désinscrit n'est réactivé silencieusement, aucun inscrit n'est dégradé.
		$body = array(
			'email_address' => $email,
			'status_if_new' => 'subscribed',
		);
	}

	$result = _180c_nl_mailchimp_call( 'PUT', $endpoint, $body );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	// 4xx Mailchimp = requête refusée en amont (sans fuite de détail).
	if ( $result['code'] >= 400 ) {
		_180c_log(
			'Mailchimp a refusé la requête (apps)',
			array(
				'status' => $result['code'],
				'action' => $action,
			),
			'warning'
		);
		return new WP_Error(
			'mailchimp_rejected',
			__( 'Demande non aboutie, réessayez plus tard.', '180c' ),
			array( 'status' => 502 )
		);
	}

	$status = _180c_nl_normalize_status( (string) ( $result['data']['status'] ?? '' ) );
	$slug   = $is_premium ? 'premium' : 'free';

	// Traçabilité de la source : uniquement à l'inscription (une désinscription
	// ne pose aucun tag). Best-effort.
	if ( 'subscribe' === $action ) {
		_180c_mc_tag_source( $email, _180c_nl_app_source_slug( $raw_source, $raw_action, $raw_list_id ) );
	}

	/**
	 * Déclenché après une synchronisation newsletter via le contrat apps (JWT).
	 *
	 * Hook dédié aux apps : il ne réutilise PAS `180c/newsletter_subscribed`
	 * (couplé au flag GA4 du site web, orienté page-view) afin d'éviter des
	 * événements analytics web parasites depuis un contexte mobile.
	 *
	 * @param int    $user_id ID de l'utilisateur authentifié.
	 * @param string $action  Action effectuée ('subscribe' | 'unsubscribe').
	 * @param string $list_id ID de liste Mailchimp.
	 * @param string $status  Statut résultant ('subscribed' | 'unsubscribed' | 'pending').
	 * @param string $slug    Slug de liste ('free' | 'premium').
	 */
	do_action( '180c/newsletter_app_synced', (int) $user->ID, $action, $list_id, $status, $slug ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	return _180c_nl_app_response( $action, $list_id, $status, 200 );
}

/**
 * POST /newsletter/subscribe/ — Inscription à une liste Mailchimp.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_newsletter_subscribe( WP_REST_Request $request ) {
	// Contrat apps mobiles : JWT requis + action/list_id. Le contrat
	// site web (anonyme) reste géré par le reste de cette fonction.
	if ( _180c_nl_is_app_request( $request ) ) {
		return _180c_rest_newsletter_app( $request );
	}

	// --- Contrat site web anonyme (form « Cahiers de Delphine ») : durcissement
	// anti-abus (aligné) : honeypot → rate-limit → validation stricte.
	// Aucune authentification (permission `__return_true`), d'où ces garde-fous.

	// 1. Honeypot : un champ leurre `_gotcha` rempli signe un bot. Réponse 200
	// « ok » silencieuse (on ne révèle jamais le rejet), AUCUN appel Mailchimp.
	if ( '' !== trim( (string) $request->get_param( '_gotcha' ) ) ) {
		return rest_ensure_response(
			array(
				'subscribed' => true,
				'list'       => 'free',
				'status'     => 'subscribed',
			)
		);
	}

	// 2. Nonce frais — vérification SOUPLE (tolérante au skew de cache). Le JS
	// récupère un nonce frais via GET /newsletter/nonce (action
	// `180c_newsletter_public`, même mécanique que `public-subscribe`) et le
	// transmet dans le body `nl_nonce`. Cas gérés :
	// - ABSENT / vide → laissé passer : une page HTML+JS ancienne servie depuis un
	// cache n'envoie pas encore `nl_nonce` ; honeypot + rate-limit + validation
	// stricte restent actifs (aucun form cassé pendant le skew de cache).
	// - PRÉSENT et INVALIDE → 403 explicite, AUCUN appel Mailchimp.
	// - PRÉSENT et VALIDE → on continue.
	// PASSAGE EN MODE STRICT (refuser aussi l'absence), une fois le cache de pages
	// purgé/expiré : retirer la condition « '' !== $nl_nonce && » de la ligne ci-dessous.
	$nl_nonce = (string) $request->get_param( 'nl_nonce' );
	if ( '' !== $nl_nonce && ! wp_verify_nonce( $nl_nonce, '180c_newsletter_public' ) ) {
		return new WP_Error(
			'invalid_nonce',
			__( 'Session expirée, rechargez la page et réessayez.', '180c' ),
			array( 'status' => 403 )
		);
	}

	// 3. Rate-limit IP : 10 req/60 s, compartiment `web` distinct du bucket app
	// (réutilise le helper commun sans altérer le comptage du contrat apps).
	$ip = _180c_get_client_ip();
	if ( _180c_nl_rate_limited( $ip, 'web' ) ) {
		return new WP_Error(
			'rate_limited',
			__( 'Trop de requêtes. Réessayez dans une minute.', '180c' ),
			array( 'status' => 429 )
		);
	}

	// 4. Validation stricte : e-mail valide + normalisation lowercase (cohérent
	// avec le hash Mailchimp, calculé sur l'e-mail en minuscules).
	$email      = strtolower( sanitize_email( (string) $request->get_param( 'email' ) ) );
	$list_slug  = (string) $request->get_param( 'list' );
	$first_name = wp_strip_all_tags( (string) $request->get_param( 'first_name' ) );
	$last_name  = wp_strip_all_tags( (string) $request->get_param( 'last_name' ) );

	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'invalid_email',
			__( 'Adresse e-mail invalide', '180c' ),
			array( 'status' => 400 )
		);
	}

	// Slug de liste borné à l'énum du contrat (vide ou toute autre valeur → 'free').
	if ( ! in_array( $list_slug, array( 'free', 'premium' ), true ) ) {
		$list_slug = 'free';
	}

	// La liste premium nécessite un abonnement actif.
	if ( 'premium' === $list_slug ) {
		if ( ! is_user_logged_in() || ! _180c_is_recipe_subscriber() ) {
			return new WP_Error(
				'premium_required',
				__( 'Abonnement recettes requis pour cette liste', '180c' ),
				array( 'status' => 403 )
			);
		}
	}

	$result = _180c_mailchimp_subscribe( $email, $list_slug, $first_name, $last_name );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$member_status = $result['status'] ?? 'pending';
	$user_id       = get_current_user_id();

	// Traçabilité de la source : tags additifs posés après l'upsert réussi.
	// Best-effort — un échec de tag ne doit jamais invalider l'inscription.
	_180c_mc_tag_source( $email, (string) $request->get_param( 'source' ) );

	// Mise à jour meta user si connecté.
	if ( $user_id ) {
		$meta_key = ( 'premium' === $list_slug ) ? '_180c_newsletter_premium' : '_180c_newsletter_free';
		update_user_meta( $user_id, $meta_key, 1 );
	}

	/**
	 * Déclenché après une inscription newsletter.
	 *
	 * @param int    $user_id   ID de l'utilisateur (0 si anonyme).
	 * @param string $email     Adresse e-mail.
	 * @param string $list_slug Slug de la liste ('free' ou 'premium').
	 */
	do_action( '180c/newsletter_subscribed', $user_id, $email, $list_slug );

	// HTTP 201 si nouveau membre, 200 si déjà existant.
	$is_new_member = ! isset( $result['_links'] ) || 'PUT' === ( $result['_links'][0]['method'] ?? '' );
	$http_status   = ( 'subscribed' !== $member_status && 'pending' === $member_status ) ? 201 : 200;

	$response = rest_ensure_response(
		array(
			'subscribed' => true,
			'list'       => $list_slug,
			'status'     => $member_status,
		)
	);
	$response->set_status( $http_status );

	return $response;
}

/**
 * POST /newsletter/unsubscribe/ — Désinscription d'une liste Mailchimp.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_newsletter_unsubscribe( WP_REST_Request $request ) {
	$email     = sanitize_email( $request->get_param( 'email' ) );
	$list_slug = $request->get_param( 'list' ) ?: 'free';

	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'invalid_email',
			__( 'Adresse e-mail invalide', '180c' ),
			array( 'status' => 400 )
		);
	}

	$list_id = _180c_mailchimp_list_id( $list_slug );
	if ( is_wp_error( $list_id ) ) {
		return $list_id;
	}

	$email_hash = md5( strtolower( $email ) );
	$endpoint   = '/lists/' . $list_id . '/members/' . $email_hash;

	$result = _180c_mailchimp_request( 'PATCH', $endpoint, array( 'status' => 'unsubscribed' ) );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$user_id = get_current_user_id();

	// Mise à jour meta user si connecté.
	if ( $user_id ) {
		$meta_key = ( 'premium' === $list_slug ) ? '_180c_newsletter_premium' : '_180c_newsletter_free';
		update_user_meta( $user_id, $meta_key, 0 );
	}

	/**
	 * Déclenché après une désinscription newsletter.
	 *
	 * @param int    $user_id   ID de l'utilisateur (0 si anonyme).
	 * @param string $email     Adresse e-mail.
	 * @param string $list_slug Slug de la liste ('free' ou 'premium').
	 */
	do_action( '180c/newsletter_unsubscribed', $user_id, $email, $list_slug );

	return rest_ensure_response( array( 'unsubscribed' => true ) );
}

/**
 * GET /newsletter/status/ — Statut NL du user courant pour chaque liste.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_newsletter_status( WP_REST_Request $request ) {
	unset( $request );

	$user = wp_get_current_user();

	if ( ! $user || ! $user->ID ) {
		return new WP_Error(
			'not_authenticated',
			__( 'Authentification requise', '180c' ),
			array( 'status' => 401 )
		);
	}

	// Un SEUL appel membre, via l'appelant commun (`_180c_nl_mailchimp_call`, le
	// même que le contrat apps) : réponse { code, data } exploitable, ou WP_Error.
	// Le modèle 180°C n'a qu'une audience ; free/premium se lisent sur ce membre.
	$audience   = _180c_nl_audience_id();
	$email_hash = md5( strtolower( (string) $user->user_email ) );
	$endpoint   = '/lists/' . $audience . '/members/' . $email_hash;

	$result = _180c_nl_mailchimp_call( 'GET', $endpoint );

	// Erreur réseau / Mailchimp indisponible : on remonte une erreur EXPLICITE
	// (le WP_Error porte code + message + status 503), plutôt qu'un « unknown »
	// muet qui empêchait de distinguer indisponibilité et désinscription.
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$code = (int) $result['code'];

	// 404 = membre absent de l'audience → réellement désinscrit (jamais confondu
	// avec une erreur de lecture).
	if ( 404 === $code ) {
		return rest_ensure_response(
			array(
				'free'    => 'unsubscribed',
				'premium' => 'unsubscribed',
			)
		);
	}

	// Autre 4xx = refus Mailchimp explicite (sans fuite de détail).
	if ( $code >= 400 ) {
		_180c_log( 'Mailchimp a refusé la lecture de statut newsletter', array( 'status' => $code ), 'warning' );
		return new WP_Error(
			'mailchimp_rejected',
			__( 'Demande non aboutie, réessayez plus tard.', '180c' ),
			array( 'status' => 502 )
		);
	}

	// 200 : statut d'audience normalisé (subscribed | pending | unsubscribed).
	$free = _180c_nl_normalize_status( (string) ( $result['data']['status'] ?? '' ) );

	// `premium` = présence du TAG premium sur CE membre (jamais déduit de
	// l'audience), lue depuis le MÊME payload — aucun appel Mailchimp supplémentaire.
	$tags    = ( isset( $result['data']['tags'] ) && is_array( $result['data']['tags'] ) )
		? $result['data']['tags']
		: array();
	$premium = _180c_nl_member_has_premium_tag( $tags ) ? 'subscribed' : 'unsubscribed';

	return rest_ensure_response(
		array(
			'free'    => $free,
			'premium' => $premium,
		)
	);
}

/**
 * POST /newsletter/account-optin/ — bascule l'opt-in « Cahiers de Delphine ».
 *
 * Réservé à l'utilisateur connecté (Mon Compte). Inscrit ou désinscrit sa propre
 * adresse e-mail sur la liste « free » Mailchimp — exactement la même liste que
 * la page Newsletter (cf. `_180c_nl_public_allowed_list_ids()`). Single opt-in
 * idempotent : l'utilisateur étant déjà authentifié, aucun double opt-in n'est
 * requis (cohérent avec le formulaire public). L'état est miroité dans
 * la meta `_180c_newsletter_free` qui pilote l'affichage du toggle.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_newsletter_account_optin( WP_REST_Request $request ) {
	$user = wp_get_current_user();

	if ( ! $user || ! $user->ID ) {
		return new WP_Error(
			'not_authenticated',
			__( 'Authentification requise', '180c' ),
			array( 'status' => 401 )
		);
	}

	$email = sanitize_email( (string) $user->user_email );

	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'invalid_email',
			__( 'Adresse e-mail invalide', '180c' ),
			array( 'status' => 400 )
		);
	}

	// Même liste que la page Newsletter (liste « free » / Cahiers de Delphine).
	$allowed_list_id = _180c_nl_public_allowed_list_ids()[0];
	$member_endpoint = '/lists/' . $allowed_list_id . '/members/' . md5( strtolower( $email ) );

	// Lecture seule : renvoie l'état réel de l'abonnement à l'audience (et aligne
	// la meta locale), pour que le toggle reflète Mailchimp au chargement.
	if ( WP_REST_Server::READABLE === $request->get_method() ) {
		$status = _180c_nl_mailchimp_call( 'GET', $member_endpoint );
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		$is_subscribed = ( 200 === (int) $status['code'] && 'subscribed' === (string) ( $status['data']['status'] ?? '' ) );
		update_user_meta( (int) $user->ID, '_180c_newsletter_free', $is_subscribed ? 1 : 0 );
		return rest_ensure_response( array( 'optin' => $is_subscribed ) );
	}

	$optin = rest_sanitize_boolean( $request->get_param( 'optin' ) );

	if ( $optin ) {
		// Single opt-in idempotent — cohérent avec la page Newsletter.
		$body = array(
			'email_address' => $email,
			'status'        => 'subscribed',
			'status_if_new' => 'subscribed',
		);
	} else {
		$body = array(
			'email_address' => $email,
			'status'        => 'unsubscribed',
		);
	}

	$result = _180c_nl_mailchimp_call( 'PUT', $member_endpoint, $body );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	if ( (int) $result['code'] >= 400 ) {
		_180c_log(
			'Mailchimp a refusé l\'opt-in compte',
			array( 'status' => (int) $result['code'] ),
			'warning'
		);
		return new WP_Error(
			'mailchimp_rejected',
			__( 'Demande non aboutie, réessayez plus tard.', '180c' ),
			array( 'status' => 502 )
		);
	}

	// Miroir local : source de vérité pour l'affichage initial du toggle.
	update_user_meta( (int) $user->ID, '_180c_newsletter_free', $optin ? 1 : 0 );

	// Traçabilité de la source : uniquement à l'opt-in. Une désinscription ne
	// pose aucun tag (les tags de source ne sont jamais retirés). Best-effort.
	if ( $optin ) {
		_180c_mc_tag_source( $email, (string) $request->get_param( 'source' ) );
	}

	return rest_ensure_response( array( 'optin' => $optin ) );
}

/**
 * GET|POST /newsletter/premium/ — opt-in premium (tag Mailchimp « Abonnés Premium »).
 *
 * GET  : renvoie `{ optin: bool }` (présence du tag chez l'utilisateur courant).
 * POST : add/remove du tag selon `{ optin }`, puis miroite la meta
 *        `_180c_newsletter_premium` (état initial du toggle « Mon Compte »).
 *
 * Gating : utilisateur authentifié ET abonné recettes actif (403 sinon). Le tag
 * premium ne doit jamais être posé hors abonnement. Auth cookie (site) ou JWT
 * (apps) résolue par la permission `_180c_rest_jwt_or_cookie`.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_newsletter_premium( WP_REST_Request $request ) {
	$user = wp_get_current_user();

	if ( ! $user || ! $user->ID ) {
		return new WP_Error(
			'not_authenticated',
			__( 'Authentification requise', '180c' ),
			array( 'status' => 401 )
		);
	}

	// Gating premium : add/remove ET lecture réservés aux abonnés recettes actifs.
	if ( ! _180c_is_recipe_subscriber() ) {
		return new WP_Error(
			'premium_required',
			__( 'Abonnement recettes requis.', '180c' ),
			array( 'status' => 403 )
		);
	}

	$email = sanitize_email( (string) $user->user_email );
	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'invalid_email',
			__( 'Adresse e-mail invalide', '180c' ),
			array( 'status' => 400 )
		);
	}

	// Lecture seule.
	if ( WP_REST_Server::READABLE === $request->get_method() ) {
		$has = _180c_mc_premium_has( $email );
		if ( is_wp_error( $has ) ) {
			return $has;
		}
		// Miroir local opportuniste : aligne la meta sur l'état réel Mailchimp.
		update_user_meta( (int) $user->ID, '_180c_newsletter_premium', $has ? 1 : 0 );
		return rest_ensure_response( array( 'optin' => (bool) $has ) );
	}

	// Écriture : add/remove du tag.
	$optin  = rest_sanitize_boolean( $request->get_param( 'optin' ) );
	$result = _180c_mc_premium_set( $email, $optin );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	update_user_meta( (int) $user->ID, '_180c_newsletter_premium', $optin ? 1 : 0 );

	// Traçabilité de la source : uniquement à l'opt-in. Défaut `web-account-prefs`
	// (surface « Mon compte ») si l'appelant n'a pas transmis de slug. Best-effort.
	if ( $optin ) {
		$source = (string) $request->get_param( 'source' );
		_180c_mc_tag_source( $email, '' !== $source ? $source : 'web-account-prefs' );
	}

	/**
	 * Déclenché après une bascule de l'opt-in premium depuis un compte.
	 *
	 * @param int  $user_id ID de l'utilisateur.
	 * @param bool $optin   Nouvel état (true = tag posé, false = tag retiré).
	 */
	do_action( '180c/newsletter_premium_toggled', (int) $user->ID, $optin ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	return rest_ensure_response( array( 'optin' => $optin ) );
}

/**
 * Opt-in newsletter gratuite à l'inscription (Mailchimp).
 *
 * Déclenché par le hook `180c/user_registered` (inc/auth/register.php) lorsque
 * la case « newsletter gratuite » a été cochée (meta `_180c_newsletter_free`).
 * Best-effort : un échec Mailchimp n'interrompt pas la création du compte.
 *
 * @param int $user_id ID du nouvel utilisateur.
 * @return void
 */
function _180c_newsletter_optin_on_register( $user_id ) {
	$user_id = (int) $user_id;

	if ( ! get_user_meta( $user_id, '_180c_newsletter_free', true ) ) {
		return;
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}

	$result = _180c_mailchimp_subscribe(
		$user->user_email,
		'free',
		(string) get_user_meta( $user_id, 'first_name', true ),
		(string) get_user_meta( $user_id, 'last_name', true )
	);

	if ( is_wp_error( $result ) ) {
		_180c_log(
			'Mailchimp opt-in à l\'inscription échoué.',
			array(
				'user_id' => $user_id,
				'error'   => $result->get_error_message(),
			),
			'warning'
		);
		return;
	}

	// Traçabilité de la source : case newsletter cochée au formulaire de création
	// de compte. Best-effort, après inscription Mailchimp réussie.
	_180c_mc_tag_source( (string) $user->user_email, 'web-account-signup' );

	/** Voir _180c_rest_newsletter_subscribe() pour la documentation du hook. */
	do_action( '180c/newsletter_subscribed', $user_id, $user->user_email, 'free' );
}
add_action( '180c/user_registered', '_180c_newsletter_optin_on_register' );

// ============================================================
// Contrat site web public — page /newsletter/ anonyme
//
// Endpoint POST /newsletter/public-subscribe ouvert (sans JWT). Reçoit
// { email, list_id?, nl_nonce, _gotcha }. Protection : honeypot + nonce +
// rate-limit IP (5/60 s) + whitelist de liste. Single opt-in (status=subscribed,
// idempotent). La clé Mailchimp ne quitte jamais le serveur et n'est jamais
// journalisée ; l'IP n'est jamais loggée en clair (uniquement hachée en clé de
// transient). Distinct du contrat apps JWT et du double opt-in.
// ============================================================

/**
 * Liste blanche des IDs de liste Mailchimp acceptés par le formulaire public.
 *
 * Défaut = l'audience unique (`_180c_nl_audience_id()` : `_180C_MC_AUDIENCE_ID`,
 * sinon legacy, sinon vide). Filtrable pour étendre la whitelist
 * sans toucher au code.
 *
 * @return string[] Liste d'IDs Mailchimp autorisés (au moins un).
 */
function _180c_nl_public_allowed_list_ids(): array {
	$default = _180c_nl_audience_id();

	$ids = (array) apply_filters( '_180c_newsletter_public_list_ids', array( $default ) );

	// Garantit au moins une entrée non vide.
	$ids = array_values( array_filter( array_map( 'strval', $ids ) ) );
	return empty( $ids ) ? array( $default ) : $ids;
}

/**
 * Rate limiting du formulaire public : 5 requêtes / 60 s / IP (fenêtre fixe).
 *
 * Clé de transient `180c_nl_pub_{md5(ip)}` : l'IP n'est jamais stockée ni loggée
 * en clair. Le TTL n'est pas réinitialisé à chaque incrément (fin de fenêtre
 * conservée dans l'état).
 *
 * @param string $ip Adresse IP du client.
 * @return bool True si la limite est dépassée (requête à rejeter en 429).
 */
function _180c_nl_public_rate_limited( string $ip ): bool {
	$key   = '180c_nl_pub_' . md5( $ip );
	$now   = time();
	$state = get_transient( $key );

	if ( ! is_array( $state ) || empty( $state['reset'] ) || $now >= (int) $state['reset'] ) {
		set_transient(
			$key,
			array(
				'count' => 1,
				'reset' => $now + MINUTE_IN_SECONDS,
			),
			MINUTE_IN_SECONDS
		);
		return false;
	}

	if ( (int) $state['count'] >= 5 ) {
		return true;
	}

	$state['count'] = (int) $state['count'] + 1;
	$ttl            = max( 1, (int) $state['reset'] - $now );
	set_transient( $key, $state, $ttl );
	return false;
}

/**
 * Incrémente le compteur d'échecs IP (transient `180c_nl_fail_{md5(ip)}`).
 *
 * Sert de base au déclenchement futur de Turnstile (cf.
 * `_180c_newsletter_require_turnstile`). Stub : aucune vérification active.
 *
 * @param string $ip Adresse IP du client.
 * @return int Nombre d'échecs cumulés après incrément.
 */
function _180c_nl_public_bump_failures( string $ip ): int {
	$key   = '180c_nl_fail_' . md5( $ip );
	$count = (int) get_transient( $key ) + 1;
	set_transient( $key, $count, 10 * MINUTE_IN_SECONDS );
	return $count;
}

/**
 * Handler GET /newsletter/nonce — fournit un nonce frais au formulaire public.
 *
 * Permet à une page HTML servie depuis un cache (hébergeur / WP Super Cache) de
 * récupérer un nonce valide juste avant la soumission, sans dépendre du champ
 * caché potentiellement périmé. Donnée non sensible : le nonce est lié à la
 * seule action publique `180c_newsletter_public`.
 *
 * @return WP_REST_Response
 */
function _180c_rest_newsletter_nonce(): WP_REST_Response {
	return new WP_REST_Response(
		array( 'nonce' => wp_create_nonce( '180c_newsletter_public' ) ),
		200
	);
}

/**
 * Handler du formulaire public anonyme — POST /newsletter/public-subscribe.
 *
 * Pipeline à court-circuit : honeypot → nonce → (gate Turnstile, stub) →
 * rate-limit → e-mail → list_id whitelisté → upsert Mailchimp single opt-in.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_newsletter_public_subscribe( WP_REST_Request $request ) {
	// 1. Honeypot : un champ `_gotcha` rempli signe un bot. Réponse 200 « ok »
	// silencieuse — on ne révèle jamais le rejet et on n'appelle pas Mailchimp.
	if ( '' !== trim( (string) $request->get_param( '_gotcha' ) ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	// 2. Nonce de session (action `180c_newsletter_public`). Paramètre nommé
	// `nl_nonce` et NON `_wpnonce` : ce dernier est réservé par l'API REST WP
	// (validé comme nonce `wp_rest`), ce qui provoquerait un 403 cœur avant
	// même ce handler. L'action doit être identique à la création du nonce.
	$nonce = (string) $request->get_param( 'nl_nonce' );
	if ( ! wp_verify_nonce( $nonce, '180c_newsletter_public' ) ) {
		return new WP_Error(
			'invalid_nonce',
			__( 'Session expirée, rechargez la page et réessayez.', '180c' ),
			array( 'status' => 403 )
		);
	}

	$ip = _180c_get_client_ip();

	// 3. Garde Turnstile (stub, inactif par défaut). Si un intégrateur active le
	// filtre après N échecs, exiger un token valide (vérification à implémenter).
	$fail_count        = (int) get_transient( '180c_nl_fail_' . md5( $ip ) );
	$require_turnstile = (bool) apply_filters( '_180c_newsletter_require_turnstile', false, $fail_count );
	if ( $require_turnstile && '' === trim( (string) $request->get_param( 'cf_turnstile_response' ) ) ) {
		return new WP_Error(
			'turnstile_required',
			__( 'Vérification anti-robot requise.', '180c' ),
			array( 'status' => 403 )
		);
	}

	// 4. Rate-limit IP : 5 requêtes / 60 s.
	if ( _180c_nl_public_rate_limited( $ip ) ) {
		return new WP_Error(
			'rate_limited',
			__( 'Trop de tentatives, réessayez dans une minute.', '180c' ),
			array( 'status' => 429 )
		);
	}

	// 5. E-mail valide.
	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( ! is_email( $email ) ) {
		_180c_nl_public_bump_failures( $ip );
		return new WP_Error(
			'invalid_email',
			__( 'Adresse e-mail invalide.', '180c' ),
			array( 'status' => 400 )
		);
	}

	// 6. list_id whitelisté : toute valeur hors-liste retombe sur le défaut.
	$allowed = _180c_nl_public_allowed_list_ids();
	$list_id = sanitize_text_field( (string) $request->get_param( 'list_id' ) );
	if ( '' === $list_id || ! in_array( $list_id, $allowed, true ) ) {
		$list_id = $allowed[0];
	}

	// 7. Upsert Mailchimp single opt-in (idempotent : déjà inscrit = succès).
	$endpoint = '/lists/' . $list_id . '/members/' . md5( strtolower( $email ) );
	$result   = _180c_nl_mailchimp_call(
		'PUT',
		$endpoint,
		array(
			'email_address' => $email,
			'status'        => 'subscribed',
			'status_if_new' => 'subscribed',
		)
	);

	if ( is_wp_error( $result ) ) {
		// _180c_nl_mailchimp_call() renvoie déjà un WP_Error 503 (réseau/5xx),
		// sans jamais exposer la clé ni l'e-mail.
		return new WP_Error(
			'newsletter_unavailable',
			__( 'Une erreur est survenue, merci de réessayer.', '180c' ),
			array( 'status' => 503 )
		);
	}

	// 5xx Mailchimp (au-delà du retry interne) → erreur serveur générique.
	if ( (int) $result['code'] >= 500 ) {
		_180c_log( 'Mailchimp 5xx (public-subscribe)', array( 'status' => (int) $result['code'] ), 'error' );
		return new WP_Error(
			'newsletter_unavailable',
			__( 'Une erreur est survenue. Merci de réessayer plus tard.', '180c' ),
			array( 'status' => 503 )
		);
	}

	// 4xx Mailchimp = adresse non réinscriptible (Forgotten Email, désinscrite,
	// en état de conformité, ressource invalide). On remonte une vraie erreur au
	// lieu d'un faux `ok:true` (cf. audit 2026-06, risque #2). Pas d'énumération
	// possible : un membre déjà `subscribed` est réinscrit par le PUT et renvoie
	// un 2xx (succès idempotent), jamais ce 4xx.
	if ( (int) $result['code'] >= 400 ) {
		_180c_log(
			'Mailchimp a refusé l\'inscription publique',
			array(
				'status' => (int) $result['code'],
				'title'  => (string) ( $result['data']['title'] ?? 'unknown' ),
			),
			'warning'
		);
		return new WP_Error(
			'newsletter_rejected',
			__( 'Cette adresse ne peut pas être réinscrite. Veuillez nous contacter.', '180c' ),
			array( 'status' => 422 )
		);
	}

	// 2xx : inscription effective (ou réinscription idempotente) → succès.
	// Traçabilité de la source : tags additifs posés après l'upsert réussi.
	// Best-effort — un échec de tag ne doit jamais invalider l'inscription.
	_180c_mc_tag_source( $email, (string) $request->get_param( 'source' ) );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Catalogue des libellés newsletter exposés au JS (i18n, domaine `180c`).
 *
 * Source unique des messages de feedback des formulaires d'inscription et des
 * toggles Mon compte. Injecté dans `window._180c.nlMessages` via
 * `wp_localize_script` (inc/enqueue.php) ; les modules JS s'en servent au lieu de
 * chaînes en dur (audit 2026-06, D6). Les clés correspondent au catalogue de
 * messages du correctif opt-ins. Doit rester aligné avec les fallbacks de
 * `src/js/modules/newsletter-messages.js`.
 *
 * @return array<string,string> Map clé => libellé traduit.
 */
function _180c_newsletter_js_i18n(): array {
	return array(
		'success'        => __( 'Merci, votre inscription à la newsletter est confirmée.', '180c' ),
		'emptyEmail'     => __( 'Veuillez saisir votre adresse email.', '180c' ),
		'invalidEmail'   => __( 'Cette adresse email n’est pas valide.', '180c' ),
		'rateLimited'    => __( 'Trop de tentatives. Merci de réessayer dans une minute.', '180c' ),
		'sessionExpired' => __( 'Votre session a expiré, merci de recharger la page.', '180c' ),
		'rejected'       => __( 'Cette adresse ne peut pas être réinscrite. Veuillez nous contacter.', '180c' ),
		'network'        => __( 'Connexion impossible. Vérifiez votre réseau et réessayez.', '180c' ),
		'serverError'    => __( 'Une erreur est survenue. Merci de réessayer plus tard.', '180c' ),
		'subscriberOnly' => __( 'Cette option est réservée aux abonnés.', '180c' ),
		'toggleOn'       => __( 'Vous êtes inscrit à la newsletter.', '180c' ),
		'toggleOff'      => __( 'Vous êtes désinscrit de la newsletter.', '180c' ),
	);
}
