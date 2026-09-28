<?php
/**
 * Opt-in premium Mailchimp — gestion du tag « Abonnés Premium ».
 *
 * Le « premium » n'est PAS une liste séparée : c'est un TAG posé sur l'unique
 * audience du site (`_180C_MC_AUDIENCE_ID`). On n'écrit jamais dans un segment sauvegardé (ceux-ci
 * servent uniquement au ciblage des campagnes, composées dans Mailchimp). La
 * cible « gratuit » est l'ensemble des contacts `subscribed` ne portant pas ce
 * tag.
 *
 * Écritures membre (par adresse e-mail, jamais par segment) :
 *   - ensure subscribed : PUT /lists/{aud}/members/{hash} (status_if_new=subscribed)
 *   - add/remove tag    : POST /lists/{aud}/members/{hash}/tags {tags:[{name,status}]}
 *   - lecture           : GET  /lists/{aud}/members/{hash}/tags
 *
 * Tous les appels passent par `_180c_mc_request()` (api.php) : la clé
 * API ne quitte jamais le serveur et n'est jamais journalisée.
 *
 * Constantes (wp-config.php) :
 *   - _180C_MC_AUDIENCE_ID : ID d'audience (requis, aucune valeur par défaut).
 *   - _180C_MC_TAG_PREMIUM : nom EXACT du tag premium. REQUISE : sans elle,
 *     toute écriture premium est refusée (fail-closed). Il n'existe volontairement
 *     aucune valeur par défaut : l'ancien défaut pointait sur le tag legacy
 *     « Abonnés aux recettes en ligne », supprimé de l'audience, et un
 *     POST /tags sur un nom inconnu aurait recréé ce tag mort en y déversant les
 *     abonnés (cf. RG7 : aucune écriture legacy).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * ID de l'audience Mailchimp cible des écritures membre (opt-in premium).
 *
 * Réutilise `_180C_MC_AUDIENCE_ID` si défini, sinon le résolveur partagé
 * `_180c_mc_audience_id()` (api.php), sinon chaîne vide.
 *
 * @return string ID d'audience Mailchimp.
 */
function _180c_mc_premium_audience_id(): string {
	if ( defined( '_180C_MC_AUDIENCE_ID' ) && '' !== (string) _180C_MC_AUDIENCE_ID ) {
		return (string) _180C_MC_AUDIENCE_ID;
	}
	if ( function_exists( '_180c_mc_audience_id' ) ) {
		return _180c_mc_audience_id();
	}
	return '';
}

/**
 * Nom du tag premium utilisé pour les écritures membre.
 *
 * Lu exclusivement depuis `_180C_MC_TAG_PREMIUM` (wp-config, hors dépôt). Aucun
 * repli : une chaîne vide signale une configuration absente et fait échouer les
 * écritures de façon explicite plutôt que d'atteindre un tag arbitraire.
 *
 * @return string Nom du tag, ou chaîne vide si la constante n'est pas définie.
 */
function _180c_mc_premium_tag_name(): string {
	if ( defined( '_180C_MC_TAG_PREMIUM' ) && '' !== trim( (string) _180C_MC_TAG_PREMIUM ) ) {
		return trim( (string) _180C_MC_TAG_PREMIUM );
	}
	return '';
}

/**
 * WP_Error « tag premium non configuré » (constante `_180C_MC_TAG_PREMIUM` absente).
 *
 * @return WP_Error
 */
function _180c_mc_premium_not_configured(): WP_Error {
	_180c_log( 'Constante _180C_MC_TAG_PREMIUM absente : écriture premium refusée', array(), 'error' );
	return new WP_Error(
		'mc_tag_not_configured',
		__( 'Tag premium Mailchimp non configuré (constante _180C_MC_TAG_PREMIUM).', '180c' ),
		array( 'status' => 503 )
	);
}

/**
 * Résout l'ID numérique du segment statique sous-jacent au tag premium.
 *
 * Dans Mailchimp, un tag EST un segment statique. Pour cibler une campagne sur
 * les contacts portant (ou non) le tag premium, il faut l'ID numérique de ce
 * segment — jamais codé en dur : on le retrouve par son nom via
 * `GET /lists/{aud}/segments?type=static`. Résultat mis en cache (transient,
 * 1 h) ; WP_Error explicite si le tag est introuvable.
 *
 * @return int|WP_Error ID du segment statique, ou WP_Error.
 */
function _180c_mc_premium_tag_id() {
	$cache_key = '_180c_mc_premium_tag_id';
	$cached    = get_transient( $cache_key );
	if ( false !== $cached && (int) $cached > 0 ) {
		return (int) $cached;
	}

	$aud  = _180c_mc_premium_audience_id();
	$name = _180c_mc_premium_tag_name();

	if ( '' === $name ) {
		return _180c_mc_premium_not_configured();
	}

	$result = _180c_mc_request( 'GET', '/lists/' . $aud . '/segments?type=static&count=1000' );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( (int) $result['code'] >= 400 ) {
		return _180c_mc_error_from_result( $result );
	}

	$segments = ( isset( $result['data']['segments'] ) && is_array( $result['data']['segments'] ) )
		? $result['data']['segments']
		: array();

	$needle = mb_strtolower( trim( $name ) );
	foreach ( $segments as $seg ) {
		if ( ! isset( $seg['name'] ) ) {
			continue;
		}
		if ( mb_strtolower( trim( (string) $seg['name'] ) ) === $needle ) {
			$id = (int) ( $seg['id'] ?? 0 );
			if ( $id > 0 ) {
				set_transient( $cache_key, $id, HOUR_IN_SECONDS );
				return $id;
			}
		}
	}

	return new WP_Error(
		'mc_tag_not_found',
		sprintf(
			/* translators: %s: premium tag name. */
			__( 'Tag premium « %s » introuvable dans l’audience Mailchimp. Vérifiez son nom exact (constante _180C_MC_TAG_PREMIUM).', '180c' ),
			$name
		)
	);
}

/**
 * Construit le WP_Error « refus Mailchimp » sans exposer de détail sensible.
 *
 * @param int $code Code HTTP renvoyé par Mailchimp.
 * @return WP_Error
 */
function _180c_mc_premium_rejected( int $code ): WP_Error {
	_180c_log( 'Mailchimp a refusé une écriture tag premium', array( 'status' => $code ), 'warning' );
	return new WP_Error(
		'mailchimp_rejected',
		__( 'Demande non aboutie, réessayez plus tard.', '180c' ),
		array( 'status' => 502 )
	);
}

/**
 * Pose ou retire le tag premium pour une adresse e-mail.
 *
 * Garantit d'abord que le contact est `subscribed` (un tag ne s'applique qu'à un
 * membre existant ; `status_if_new=subscribed` ne dégrade jamais un membre déjà
 * présent), puis active/désactive le tag premium. Idempotent.
 *
 * @param string $email  Adresse e-mail du membre.
 * @param bool   $active True pour poser le tag (opt-in), false pour le retirer.
 * @return true|WP_Error True si l'écriture a abouti, WP_Error sinon.
 */
function _180c_mc_premium_set( string $email, bool $active ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', __( 'Adresse e-mail invalide.', '180c' ), array( 'status' => 400 ) );
	}

	$tag_name = _180c_mc_premium_tag_name();
	if ( '' === $tag_name ) {
		return _180c_mc_premium_not_configured();
	}

	$aud  = _180c_mc_premium_audience_id();
	$hash = md5( strtolower( trim( $email ) ) );

	// 1. S'assurer que le contact existe et est subscribed (prérequis au tag).
	$ensure = _180c_mc_request(
		'PUT',
		'/lists/' . $aud . '/members/' . $hash,
		array(
			'email_address' => $email,
			'status_if_new' => 'subscribed',
		)
	);
	if ( is_wp_error( $ensure ) ) {
		return $ensure;
	}
	if ( (int) $ensure['code'] >= 400 ) {
		return _180c_mc_premium_rejected( (int) $ensure['code'] );
	}

	// 2. Activer/désactiver le tag premium (succès Mailchimp = 204 sans corps).
	$res = _180c_mc_request(
		'POST',
		'/lists/' . $aud . '/members/' . $hash . '/tags',
		array(
			'tags' => array(
				array(
					'name'   => $tag_name,
					'status' => $active ? 'active' : 'inactive',
				),
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( (int) $res['code'] >= 400 ) {
		return _180c_mc_premium_rejected( (int) $res['code'] );
	}

	return true;
}

/**
 * Indique si une adresse e-mail porte le tag premium.
 *
 * @param string $email Adresse e-mail du membre.
 * @return bool|WP_Error True si le tag est présent, false sinon ; WP_Error sur
 *                       erreur réseau/Mailchimp (un non-membre 404 = false).
 */
function _180c_mc_premium_has( string $email ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', __( 'Adresse e-mail invalide.', '180c' ), array( 'status' => 400 ) );
	}

	if ( '' === _180c_mc_premium_tag_name() ) {
		return _180c_mc_premium_not_configured();
	}

	$aud  = _180c_mc_premium_audience_id();
	$hash = md5( strtolower( trim( $email ) ) );

	$res = _180c_mc_request( 'GET', '/lists/' . $aud . '/members/' . $hash . '/tags' );
	if ( is_wp_error( $res ) ) {
		return $res;
	}

	// 404 = non membre → pas de tag.
	if ( 404 === (int) $res['code'] ) {
		return false;
	}
	if ( (int) $res['code'] >= 400 ) {
		return _180c_mc_premium_rejected( (int) $res['code'] );
	}

	$tags = ( isset( $res['data']['tags'] ) && is_array( $res['data']['tags'] ) ) ? $res['data']['tags'] : array();
	$name = strtolower( _180c_mc_premium_tag_name() );

	foreach ( $tags as $tag ) {
		if ( isset( $tag['name'] ) && strtolower( (string) $tag['name'] ) === $name ) {
			return true;
		}
	}

	return false;
}
