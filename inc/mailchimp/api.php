<?php
/**
 * Client API Mailchimp v3 partagé.
 *
 * Utilisé par la synchro des tags (premium-tag.php, source-tags.php,
 * unpaid-tag.php) et par la suppression de compte (account-deletion/externals.php).
 * Clé lue dans la constante `MAILCHIMP_API_KEY` (wp-config.php), datacenter
 * dérivé du suffixe de la clé, header `Authorization: Bearer`. La clé ne quitte
 * jamais le serveur et n'est jamais journalisée.
 *
 * Le thème ne crée ni n'envoie aucune campagne : les newsletters sont composées
 * et envoyées directement dans Mailchimp.
 *
 * Constante de configuration optionnelle (wp-config.php) :
 *   - _180C_MC_AUDIENCE_ID : ID d'audience (requis, aucune valeur par défaut).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * ID de l'audience (liste) Mailchimp unique.
 *
 * Préfère `_180C_MC_AUDIENCE_ID`, puis `MAILCHIMP_LIST_FREE`, sinon chaîne
 * vide (constante absente de `wp-config.php`).
 *
 * @return string ID d'audience Mailchimp.
 */
function _180c_mc_audience_id(): string {
	if ( defined( '_180C_MC_AUDIENCE_ID' ) && '' !== (string) _180C_MC_AUDIENCE_ID ) {
		return (string) _180C_MC_AUDIENCE_ID;
	}
	if ( defined( 'MAILCHIMP_LIST_FREE' ) && '' !== (string) MAILCHIMP_LIST_FREE ) {
		return (string) MAILCHIMP_LIST_FREE;
	}
	return '';
}

/**
 * Effectue une requête vers l'API Mailchimp v3.
 *
 * Wrapper `wp_remote_request` avec retry 3× et backoff sur erreur réseau / 5xx.
 * Ne journalise jamais la clé API. Le datacenter est dérivé du suffixe de la
 * clé (ex. `…-us9`).
 *
 * @param string     $method   Méthode HTTP (GET, POST, PUT, PATCH).
 * @param string     $endpoint Chemin relatif (ex. '/campaigns').
 * @param array|null $body     Corps de la requête (encodé en JSON).
 * @return array{code:int,data:array}|WP_Error Réponse structurée ou WP_Error.
 */
function _180c_mc_request( string $method, string $endpoint, ?array $body = null ) {
	// Audience non configurée (`_180C_MC_AUDIENCE_ID` absente) : un chemin
	// `/lists//…` répondrait 404, qu'un appelant pourrait lire comme « membre
	// déjà absent ». On refuse l'appel plutôt que de laisser croire à un succès.
	if ( str_contains( $endpoint, '/lists//' ) ) {
		_180c_log( 'Audience Mailchimp non configurée (_180C_MC_AUDIENCE_ID)', array(), 'error' );
		return new WP_Error(
			'mailchimp_not_configured',
			__( 'Audience Mailchimp non configurée.', '180c' ),
			array( 'status' => 503 )
		);
	}

	if ( ! defined( 'MAILCHIMP_API_KEY' ) || '' === (string) MAILCHIMP_API_KEY ) {
		_180c_log( 'MAILCHIMP_API_KEY non définie', array(), 'error' );
		return new WP_Error(
			'mailchimp_not_configured',
			__( 'Clé API Mailchimp non configurée.', '180c' ),
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
			__( 'Clé API Mailchimp invalide.', '180c' ),
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
		'timeout' => 15,
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
				break; // 2xx / 4xx : réponse exploitable.
			}
		}

		if ( $attempt < $max_attempts ) {
			usleep( 200000 * $attempt ); // Backoff : 200 ms puis 400 ms.
		}
	}

	if ( is_wp_error( $response ) ) {
		_180c_log( 'Erreur réseau Mailchimp', array( 'endpoint' => $endpoint ), 'error' );
		return new WP_Error(
			'mailchimp_unavailable',
			__( 'Service Mailchimp temporairement indisponible.', '180c' ),
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
 * Construit un WP_Error lisible à partir d'une réponse Mailchimp 4xx/5xx.
 *
 * Le message inclut le titre/détail Mailchimp (contexte admin uniquement), sans
 * jamais exposer la clé API.
 *
 * @param array $result Réponse `{code, data}` de _180c_mc_request().
 * @return WP_Error
 */
function _180c_mc_error_from_result( array $result ): WP_Error {
	$code   = (int) ( $result['code'] ?? 0 );
	$title  = (string) ( $result['data']['title'] ?? '' );
	$detail = (string) ( $result['data']['detail'] ?? '' );

	$message = trim( $title . ( '' !== $detail ? ' — ' . $detail : '' ) );
	if ( '' === $message ) {
		$message = __( 'Réponse Mailchimp inattendue.', '180c' );
	}

	_180c_log( 'Mailchimp a refusé une requête', array( 'status' => $code ), 'warning' );

	return new WP_Error( 'mailchimp_api_error', $message, array( 'status' => $code ) );
}
