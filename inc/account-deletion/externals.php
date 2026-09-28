<?php
/**
 * Suppression de compte — effacement chez les prestataires.
 *
 * Les deux appels de ce fichier sont NON BLOQUANTS : un échec est journalisé
 * mais n'empêche jamais la suppression du compte. Un utilisateur ne doit pas
 * rester prisonnier d'une panne Mailchimp.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retire le contact de l'audience Mailchimp, en mode ARCHIVE.
 *
 * `DELETE /lists/{id}/members/{hash}` archive le contact : il cesse de recevoir
 * les campagnes, et l'adresse reste réinscriptible plus tard. C'est le
 * comportement voulu — quelqu'un qui supprime son compte doit pouvoir se
 * réabonner à la newsletter, ou recréer un compte, sans se heurter à un mur.
 *
 * `delete-permanent` n'est délibérément PAS utilisé : il rend l'adresse
 * définitivement non réinscriptible (« Forgotten Email Not Subscribed », vérifié
 * le 2026-09-01), ce qui punirait l'utilisateur bien au-delà de sa demande.
 *
 * Un 204 (archivé) comme un 404 (déjà absent de l'audience) valent succès :
 * dans les deux cas, le contact ne reçoit plus rien. Le client générique du
 * thème (`_180c_mc_request()`, inc/mailchimp/api.php) est réutilisé tel
 * quel — aucune modification n'y est apportée.
 *
 * @param string $email   Adresse du compte supprimé.
 * @param bool   $dry_run Vrai pour simuler sans appeler l'API.
 * @return bool Vrai si le contact ne reçoit plus les campagnes.
 */
function _180c_account_deletion_mailchimp_forget( string $email, bool $dry_run ): bool {
	if ( '' === $email || ! function_exists( '_180c_mc_request' ) ) {
		return false;
	}

	if ( $dry_run ) {
		return true;
	}

	$hash     = md5( strtolower( trim( $email ) ) );
	$audience = _180c_mc_audience_id();

	$result = _180c_mc_request( 'DELETE', '/lists/' . $audience . '/members/' . $hash );

	if ( is_wp_error( $result ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'échec non bloquant, sans adresse e-mail.
		error_log( '[180c account-deletion] Mailchimp injoignable : ' . $result->get_error_code() );

		return false;
	}

	$code = (int) ( $result['code'] ?? 0 );

	if ( 204 === $code || 200 === $code || 404 === $code ) {
		return true;
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'échec non bloquant, sans adresse e-mail.
	error_log( '[180c account-deletion] Mailchimp a refusé l\'archivage du contact (HTTP ' . $code . ')' );

	return false;
}

/**
 * Supprime l'utilisateur OneSignal identifié par son external_id.
 *
 * Les deux applications posent l'ID WordPress en external_id
 * (`OneSignal.login(String(id))` — PushNotificationService.swift:126 et
 * PushNotificationService.kt:97), c'est donc la seule clé nécessaire.
 *
 * Aucune constante nouvelle : le module Notifications du thème porte déjà
 * `_180C_ONESIGNAL_APP_ID` et `_180C_ONESIGNAL_REST_KEY`
 * (inc/notifications/config.php). Sans clé REST, l'étape est simplement
 * abandonnée.
 *
 * @param int  $user_id Identifiant WP, utilisé comme external_id.
 * @param bool $dry_run Vrai pour simuler sans appeler l'API.
 * @return string État de l'étape, à fin de journalisation.
 */
function _180c_account_deletion_onesignal_forget( int $user_id, bool $dry_run ): string {
	if ( ! function_exists( '_180c_onesignal_is_configured' ) || ! _180c_onesignal_is_configured() ) {
		return 'skipped: OneSignal non configuré';
	}

	if ( $dry_run || ( function_exists( '_180c_onesignal_is_dry_run' ) && _180c_onesignal_is_dry_run() ) ) {
		return 'dry-run';
	}

	$url = sprintf(
		'https://api.onesignal.com/apps/%s/users/by/external_id/%d',
		rawurlencode( _180c_onesignal_app_id() ),
		$user_id
	);

	$response = wp_remote_request(
		$url,
		array(
			'method'  => 'DELETE',
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Key ' . _180c_onesignal_rest_key(),
				'Accept'        => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'échec non bloquant.
		error_log( '[180c account-deletion] OneSignal injoignable pour user ' . $user_id );

		return 'error: réseau';
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( $code >= 200 && $code < 300 ) {
		return 'deleted';
	}

	if ( 404 === $code ) {
		return 'absent';
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'échec non bloquant.
	error_log( '[180c account-deletion] OneSignal a refusé la suppression (HTTP ' . $code . ') pour user ' . $user_id );

	return 'error: HTTP ' . $code;
}
