<?php
/**
 * Écriture du tag `subscription_status` sur l'utilisateur OneSignal.
 *
 * L'utilisateur OneSignal est adressé par son `external_id`, qui vaut l'ID
 * utilisateur WordPress — exactement ce que pose l'app iOS
 * (`OneSignal.login(String(id))`, PushNotificationService.swift:126) et ce que
 * cible déjà la purge de compte (`inc/account-deletion/externals.php:94`). Un
 * même compte porte donc un seul utilisateur OneSignal, quel que soit le canal.
 *
 * DUPLICATION ASSUMÉE DE LA COUCHE HTTP
 * -------------------------------------
 * Il n'existe pas, dans `inc/notifications/`, de fonction réutilisable d'appel
 * authentifié : `_180c_onesignal_send()` construit ses arguments en ligne
 * (onesignal-client.php:88-96) et `_180c_onesignal_fetch_segments_index()` les
 * reconstruit pour son compte (config.php:241-250). L'en-tête d'authentification
 * y est déjà écrit deux fois.
 *
 * Extraire un client commun supposerait de modifier le module d'envoi BO, hors
 * périmètre de ce lot. On duplique donc a minima — en réutilisant les
 * accesseurs de configuration existants (`_180c_onesignal_app_id()`,
 * `_180c_onesignal_rest_key()`), de sorte qu'aucune constante ni aucune clé ne
 * soit redéfinie ici.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta miroir de l'opt-in web push.
 *
 * Miroir d'affichage, JAMAIS faisant autorité : la vérité est la permission du
 * navigateur et l'état de la subscription côté OneSignal. Cette meta sert à
 * rendre l'état initial du toggle sans appel réseau, et à savoir s'il vaut la
 * peine d'appeler l'API pour ce compte.
 */
const _180C_PUSH_OPTIN_META = '_180c_push_web_optin';

/**
 * Indique si un compte a activé le web push depuis ce site.
 *
 * @param int $user_id ID du compte.
 * @return bool
 */
function _180c_push_user_has_optin( int $user_id ): bool {
	return (bool) get_user_meta( $user_id, _180C_PUSH_OPTIN_META, true );
}

/**
 * Écrit le miroir local de l'opt-in web push.
 *
 * @param int  $user_id ID du compte.
 * @param bool $optin   État à mémoriser.
 * @return void
 */
function _180c_push_set_user_optin( int $user_id, bool $optin ): void {
	update_user_meta( $user_id, _180C_PUSH_OPTIN_META, $optin ? 1 : 0 );
}

/**
 * Aligne le tag `subscription_status` de l'utilisateur OneSignal sur son statut
 * d'abonnement réel.
 *
 * Contrat, calqué sur la synchro Mailchimp (fail-open, idempotent) :
 *   - compte sans opt-in web  -> no-op silencieux, aucun appel réseau ;
 *   - OneSignal non configuré -> no-op, journalisé en debug ;
 *   - dry-run actif           -> aucun appel réseau, payload journalisé ;
 *   - utilisateur inconnu (404) -> succès : il n'a simplement pas de
 *     subscription web, ce n'est pas une erreur ;
 *   - toute autre erreur      -> WP_Error rendu à l'appelant ET journalisé,
 *     sans jamais interrompre le flux WooCommerce qui a déclenché la synchro.
 *
 * @param int $user_id ID du compte.
 * @return true|WP_Error True si l'état est aligné (ou n'avait pas à l'être).
 */
function _180c_push_sync_user_tag( int $user_id ) {
	if ( $user_id <= 0 ) {
		return new WP_Error( 'push_invalid_user', __( 'Compte invalide.', '180c' ) );
	}

	// Un compte qui n'a jamais activé le web push n'a pas de subscription web :
	// l'appeler coûterait une requête HTTP pour rien, à chaque transition.
	if ( ! _180c_push_user_has_optin( $user_id ) ) {
		return true;
	}

	if ( ! function_exists( '_180c_onesignal_is_configured' ) || ! _180c_onesignal_is_configured() ) {
		_180c_log(
			'Sync tag push ignorée : OneSignal non configuré',
			array( 'user_id' => $user_id )
		);
		return true;
	}

	$status = _180c_push_user_subscription_status( $user_id );

	$payload = array(
		'properties' => array(
			'tags' => array(
				_180C_PUSH_STATUS_TAG => $status,
			),
		),
	);

	if ( function_exists( '_180c_onesignal_is_dry_run' ) && _180c_onesignal_is_dry_run() ) {
		_180c_log(
			'Sync tag push dry-run',
			array(
				'user_id' => $user_id,
				'payload' => $payload,
			)
		);
		return true;
	}

	$endpoint = sprintf(
		'https://api.onesignal.com/apps/%s/users/by/external_id/%d',
		rawurlencode( _180c_onesignal_app_id() ),
		$user_id
	);

	// Mêmes en-têtes, même schéma d'authentification (`Key`) et même timeout que
	// le module d'envoi. `PATCH` et non `PUT` : on ne fusionne qu'un tag, sans
	// toucher aux autres propriétés de l'utilisateur — dont les tags `env` et
	// `app_version` posés par l'app iOS, qu'un remplacement effacerait.
	$response = wp_remote_request(
		$endpoint,
		array(
			'method'  => 'PATCH',
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Key ' . _180c_onesignal_rest_key(),
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		)
	);

	if ( is_wp_error( $response ) ) {
		_180c_log(
			'Sync tag push : OneSignal injoignable',
			array(
				'user_id' => $user_id,
				'error'   => $response->get_error_code(),
			),
			'warning'
		);
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	// 404 : le compte n'a pas (ou plus) d'utilisateur OneSignal. Cas normal —
	// opt-in révoqué au niveau du navigateur, ou données effacées côté client.
	if ( 404 === $code ) {
		_180c_log(
			'Sync tag push : utilisateur OneSignal inconnu',
			array( 'user_id' => $user_id )
		);
		return true;
	}

	if ( $code < 200 || $code >= 300 ) {
		_180c_log(
			'Sync tag push refusée par OneSignal',
			array(
				'user_id' => $user_id,
				'status'  => $code,
			),
			'warning'
		);

		/* translators: %d: code HTTP renvoyé par OneSignal. */
		return new WP_Error( 'push_http_error', sprintf( __( 'OneSignal a répondu HTTP %d.', '180c' ), $code ) );
	}

	/**
	 * Déclenché après un alignement réussi du tag de statut push.
	 *
	 * @param int    $user_id ID du compte.
	 * @param string $status  Valeur appliquée (`active` ou `none`).
	 */
	do_action( '180c/push/tag_synced', $user_id, $status ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	return true;
}
