<?php
/**
 * Configuration et garde-fous du module Notifications push (OneSignal).
 *
 * WordPress est la source de vérité et la console d'envoi unique des notifications
 * push de l'app mobile 180°C. Ce fichier centralise :
 *   - la lecture des constantes OneSignal depuis wp-config.php ;
 *   - le test d'état de configuration ;
 *   - le mapping des segments 180°C vers les segments OneSignal réels ;
 *   - le garde-fou d'affichage (admin notice) et de désactivation d'envoi.
 *
 * La clé REST OneSignal ne doit JAMAIS être écrite dans un log, affichée en
 * admin, exposée dans une réponse REST, ni committée. Elle vit uniquement dans
 * le wp-config.php du serveur.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clé du custom post type des notifications.
 *
 * Centralisée ici (config chargée en premier) pour éviter toute chaîne en dur
 * dupliquée dans les autres modules (post-type, admin, client, rest).
 */
define( '_180C_NOTIF_POST_TYPE', '180c_notification' );

// Segment par défaut de l'envoi manuel. Volontairement « testers » pour éviter
// tout envoi accidentel aux vrais utilisateurs : c'est le seul segment qui
// s'envoie sans case de confirmation. Surchargeable via wp-config.php ou le
// filtre '_180c_onesignal_default_segment'. Les anciennes valeurs (`testflight`,
// `appstore`, `all`) restent tolérées et sont ramenées vers une clé courante par
// _180c_onesignal_default_segment().
if ( ! defined( '_180C_ONESIGNAL_DEFAULT_SEGMENT' ) ) {
	define( '_180C_ONESIGNAL_DEFAULT_SEGMENT', 'testers' );
}

// Mode simulation : si vrai, aucun appel réseau n'est fait à OneSignal ; le
// payload complet est logué et un identifiant factice « dryrun_… » est retourné.
if ( ! defined( '_180C_ONESIGNAL_DRY_RUN' ) ) {
	define( '_180C_ONESIGNAL_DRY_RUN', false );
}

/**
 * Retourne l'App ID OneSignal, ou une chaîne vide si non défini.
 *
 * L'App ID n'est pas un secret mais reste en constante wp-config, jamais en dur.
 *
 * @return string
 */
function _180c_onesignal_app_id() {
	return defined( '_180C_ONESIGNAL_APP_ID' ) ? (string) _180C_ONESIGNAL_APP_ID : '';
}

/**
 * Retourne la clé REST OneSignal, ou une chaîne vide si non définie.
 *
 * Valeur sensible : ne jamais la loguer, l'afficher ni l'exposer côté REST.
 *
 * @return string
 */
function _180c_onesignal_rest_key() {
	return defined( '_180C_ONESIGNAL_REST_KEY' ) ? (string) _180C_ONESIGNAL_REST_KEY : '';
}

/**
 * Indique si le module est pleinement configuré (App ID + clé REST présents).
 *
 * @return bool
 */
function _180c_onesignal_is_configured() {
	return '' !== _180c_onesignal_app_id() && '' !== _180c_onesignal_rest_key();
}

/**
 * Indique si le mode simulation (dry-run) est actif.
 *
 * @return bool
 */
function _180c_onesignal_is_dry_run() {
	return defined( '_180C_ONESIGNAL_DRY_RUN' ) && _180C_ONESIGNAL_DRY_RUN;
}

/**
 * Mapping des segments 180°C. L'IDENTIFIANT OneSignal est la référence
 * canonique ; le nom n'est qu'un « dernier connu », résolu à l'envoi via
 * l'API de listing (_180c_onesignal_fetch_segments_index()).
 *
 * Les UUID de segments sont propres au compte OneSignal : ils ne sont pas
 * versionnés et se lisent dans la constante `_180C_ONESIGNAL_SEGMENT_IDS`
 * (wp-config.php), tableau `clé interne => UUID`. Sans UUID, l'envoi cible le
 * nom stocké, sans contrôle de renommage (cf. _180c_onesignal_resolve_segment_name()).
 *
 * @return array<string,array{id:string,name:string}> Clé interne => { id, name }.
 */
function _180c_onesignal_segments() {
	$ids = defined( '_180C_ONESIGNAL_SEGMENT_IDS' ) && is_array( _180C_ONESIGNAL_SEGMENT_IDS ) ? _180C_ONESIGNAL_SEGMENT_IDS : array();

	/**
	 * Filtre le mapping des segments 180°C vers OneSignal.
	 *
	 * @param array<string,array{id:string,name:string}> $segments Clé interne => { id, name }.
	 */
	return (array) apply_filters(
		'_180c_onesignal_segments',
		array(
			'all_users'   => array(
				'id'   => (string) ( $ids['all_users'] ?? '' ),
				'name' => 'Tous les utilisateurs',
			),
			'subscribers' => array(
				'id'   => (string) ( $ids['subscribers'] ?? '' ),
				'name' => 'Abonnés',
			),
			'testers'     => array(
				'id'   => (string) ( $ids['testers'] ?? '' ),
				'name' => 'Testeurs',
			),
		)
	);
}

/**
 * Correspondance de secours des anciennes clés de segment vers les clés
 * courantes. Ne réécrit rien en base : sert uniquement à afficher et à router
 * proprement les notifications déjà envoyées avant le LOT D.
 *
 * @return array<string,string> Ancienne clé => clé courante.
 */
function _180c_onesignal_segment_legacy_aliases() {
	return array(
		'testflight' => 'testers',
		'appstore'   => 'all_users',
		'all'        => 'all_users',
	);
}

/**
 * Normalise une clé de segment : ramène une ancienne clé vers sa clé courante.
 *
 * @param string $key Clé interne (courante ou héritée).
 * @return string Clé courante.
 */
function _180c_onesignal_normalize_segment_key( $key ) {
	$aliases = _180c_onesignal_segment_legacy_aliases();

	return $aliases[ $key ] ?? $key;
}

/**
 * Résout une clé de segment interne en nom de segment (dernier connu).
 *
 * Tolère les anciennes clés (`testflight` | `appstore` | `all`). Retombe sur le
 * segment par défaut si la clé est inconnue.
 *
 * @param string $key Clé interne.
 * @return string Nom du segment OneSignal (dernier connu).
 */
function _180c_onesignal_segment_name( $key ) {
	$segments = _180c_onesignal_segments();
	$key      = _180c_onesignal_normalize_segment_key( $key );

	if ( isset( $segments[ $key ]['name'] ) ) {
		return (string) $segments[ $key ]['name'];
	}

	$default = _180c_onesignal_default_segment();

	return isset( $segments[ $default ]['name'] ) ? (string) $segments[ $default ]['name'] : 'Testeurs';
}

/**
 * Retourne l'identifiant OneSignal canonique d'une clé de segment.
 *
 * @param string $key Clé interne (courante ou héritée).
 * @return string UUID du segment, ou chaîne vide si inconnu.
 */
function _180c_onesignal_segment_id( $key ) {
	$segments = _180c_onesignal_segments();
	$key      = _180c_onesignal_normalize_segment_key( $key );

	return isset( $segments[ $key ]['id'] ) ? (string) $segments[ $key ]['id'] : '';
}

/**
 * Retourne la liste des clés de segment internes valides (courantes).
 *
 * @return string[]
 */
function _180c_onesignal_segment_keys() {
	return array_keys( _180c_onesignal_segments() );
}

/**
 * Retourne la clé de segment par défaut de l'envoi manuel.
 *
 * Ramène toute ancienne valeur (ex. `testflight`) vers sa clé courante, de sorte
 * qu'une constante restée sur une valeur héritée ne provoque jamais d'erreur.
 *
 * @return string Clé courante.
 */
function _180c_onesignal_default_segment() {
	$default = defined( '_180C_ONESIGNAL_DEFAULT_SEGMENT' ) ? (string) _180C_ONESIGNAL_DEFAULT_SEGMENT : 'testers';

	/**
	 * Filtre la clé de segment par défaut de l'envoi manuel.
	 *
	 * @param string $default Clé interne du segment par défaut.
	 */
	$default = (string) apply_filters( '_180c_onesignal_default_segment', $default );

	return _180c_onesignal_normalize_segment_key( $default );
}

/**
 * Interroge l'API OneSignal pour lister les segments de l'app et les indexe par
 * identifiant. Résultat mis en cache (transient) 1 heure.
 *
 * Contrat API vérifié sur https://documentation.onesignal.com/reference/view-segments :
 *   GET https://api.onesignal.com/apps/{app_id}/segments
 *   Header : Authorization: Key {REST_API_KEY}
 *   Réponse : { total_count, segments: [ { id, name, is_active, ... }, ... ] }
 * L'endpoint ne renvoie PAS le nombre d'abonnés (metadata uniquement).
 *
 * @param bool $force Ignorer le cache et rafraîchir.
 * @return array<string,array{name:string,is_active:bool}>|WP_Error Index id => { name, is_active }.
 */
function _180c_onesignal_fetch_segments_index( $force = false ) {
	$cache_key = '_180c_onesignal_segments_index';

	if ( ! $force ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$app_id = _180c_onesignal_app_id();
	$key    = _180c_onesignal_rest_key();

	if ( '' === $app_id || '' === $key ) {
		return new WP_Error( 'onesignal_not_configured', __( 'OneSignal non configuré : impossible de lister les segments.', '180c' ) );
	}

	$endpoint = sprintf( 'https://api.onesignal.com/apps/%s/segments?limit=300', rawurlencode( $app_id ) );

	$response = wp_remote_get(
		$endpoint,
		array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Key ' . $key,
				'Accept'        => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 || ! is_array( $body ) || ! isset( $body['segments'] ) || ! is_array( $body['segments'] ) ) {
		/* translators: %d: code HTTP renvoyé par OneSignal. */
		return new WP_Error( 'onesignal_segments_http', sprintf( __( 'OneSignal (listing segments) a répondu HTTP %d.', '180c' ), $code ) );
	}

	$index = array();
	foreach ( $body['segments'] as $seg ) {
		if ( ! is_array( $seg ) || empty( $seg['id'] ) ) {
			continue;
		}
		$index[ (string) $seg['id'] ] = array(
			'name'      => isset( $seg['name'] ) ? (string) $seg['name'] : '',
			'is_active' => ! isset( $seg['is_active'] ) || (bool) $seg['is_active'],
		);
	}

	set_transient( $cache_key, $index, HOUR_IN_SECONDS );

	return $index;
}

/**
 * Résout, au moment de l'envoi, le nom courant d'un segment à partir de son
 * identifiant canonique.
 *
 * Règles (arbitrage LOT D) :
 *   - API injoignable        -> repli sur le nom stocké, journalise, N'ARRÊTE PAS.
 *   - identifiant absent      -> segment supprimé  -> WP_Error (envoi REFUSÉ).
 *   - is_active à false       -> segment en pause  -> WP_Error (envoi REFUSÉ).
 *   - nom résolu ≠ nom stocké -> journalise un avertissement, utilise le nom résolu.
 *
 * @param string $key Clé interne du segment (courante ou héritée).
 * @return string|WP_Error Nom de segment à envoyer, ou WP_Error si l'envoi doit être refusé.
 */
function _180c_onesignal_resolve_segment_name( $key ) {
	$key    = _180c_onesignal_normalize_segment_key( $key );
	$stored = _180c_onesignal_segment_name( $key );
	$id     = _180c_onesignal_segment_id( $key );

	// Clé inconnue (ex. issue d'un filtre custom sans id) : impossible de vérifier
	// par identifiant, on retombe sur le nom stocké sans bloquer.
	if ( '' === $id ) {
		return $stored;
	}

	$index = _180c_onesignal_fetch_segments_index();

	if ( is_wp_error( $index ) ) {
		_180c_log(
			'Segments OneSignal : listing indisponible, repli sur le nom stocké',
			array(
				'segment' => $key,
				'stored'  => $stored,
				'error'   => $index->get_error_message(),
			),
			'warning'
		);
		return $stored;
	}

	if ( ! isset( $index[ $id ] ) ) {
		return new WP_Error(
			'segment_deleted',
			sprintf(
				/* translators: 1: nom du segment, 2: identifiant OneSignal. */
				__( 'Segment « %1$s » (%2$s) introuvable côté OneSignal : il a probablement été supprimé. Envoi refusé.', '180c' ),
				$stored,
				$id
			)
		);
	}

	if ( empty( $index[ $id ]['is_active'] ) ) {
		return new WP_Error(
			'segment_inactive',
			sprintf(
				/* translators: %s: nom du segment. */
				__( 'Segment « %s » en pause (inactif) côté OneSignal. Envoi refusé.', '180c' ),
				'' !== $index[ $id ]['name'] ? $index[ $id ]['name'] : $stored
			)
		);
	}

	$resolved = '' !== $index[ $id ]['name'] ? $index[ $id ]['name'] : $stored;

	if ( $resolved !== $stored ) {
		_180c_log(
			'Segments OneSignal : nom divergent, usage du nom résolu',
			array(
				'segment'  => $key,
				'id'       => $id,
				'stored'   => $stored,
				'resolved' => $resolved,
			),
			'warning'
		);
	}

	return $resolved;
}

/**
 * Indique si un segment exige une case de confirmation avant envoi.
 *
 * Les segments à large audience (`all_users`, `subscribers`) l'exigent ;
 * `testers` s'envoie sans friction.
 *
 * @param string $key Clé interne (courante ou héritée).
 * @return bool
 */
function _180c_onesignal_segment_requires_confirmation( $key ) {
	$key = _180c_onesignal_normalize_segment_key( $key );

	return in_array( $key, array( 'all_users', 'subscribers' ), true );
}

/**
 * Affiche un avertissement admin sur les écrans du CPT quand le module n'est
 * pas configuré. Jamais de fatal, jamais de warning PHP.
 *
 * @return void
 */
function _180c_onesignal_config_notice() {
	if ( _180c_onesignal_is_configured() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || _180C_NOTIF_POST_TYPE !== $screen->post_type ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__(
			'Notifications push désactivées : les constantes _180C_ONESIGNAL_APP_ID et _180C_ONESIGNAL_REST_KEY doivent être définies dans wp-config.php avant tout envoi.',
			'180c'
		)
	);
}
add_action( 'admin_notices', '_180c_onesignal_config_notice' );
