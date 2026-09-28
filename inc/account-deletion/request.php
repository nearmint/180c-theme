<?php
/**
 * Suppression de compte — cycle de vie de la demande.
 *
 * Une demande vit dans une seule usermeta (`_180c_account_deletion`). Elle
 * porte le hash du jeton de confirmation, sa date d'expiration et les choix
 * faits au formulaire.
 *
 * Le jeton en clair n'est JAMAIS stocké : seul son sha256 l'est, et la
 * vérification compare en temps constant le hash du jeton reçu à celui de
 * l'utilisateur courant. Il n'existe donc aucune recherche globale de jeton —
 * un jeton volé sans la session correspondante ne mène à rien.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clé de l'usermeta portant la demande en cours.
 */
const _180C_ACCOUNT_DELETION_META = '_180c_account_deletion';

/**
 * Délai minimal entre deux envois de l'e-mail de confirmation, en secondes.
 */
const _180C_ACCOUNT_DELETION_RESEND_DELAY = 600;

/**
 * Clé du transient anti-renvoi.
 *
 * @param int $user_id Identifiant WP.
 * @return string Clé de transient.
 */
function _180c_account_deletion_resend_key( int $user_id ): string {
	return '_180c_acctdel_resend_' . $user_id;
}

/**
 * Clé du verrou d'exécution du pipeline.
 *
 * @param int $user_id Identifiant WP.
 * @return string Clé de transient.
 */
function _180c_account_deletion_lock_key( int $user_id ): string {
	return '_180c_acctdel_lock_' . $user_id;
}

/**
 * Retourne la demande en cours, ou un tableau vide.
 *
 * Une demande expirée est considérée comme absente (et purgée au passage).
 *
 * @param int $user_id Identifiant WP.
 * @return array Demande, ou tableau vide.
 */
function _180c_account_deletion_get_request( int $user_id ): array {
	$request = get_user_meta( $user_id, _180C_ACCOUNT_DELETION_META, true );

	if ( ! is_array( $request ) || empty( $request['token_hash'] ) ) {
		return array();
	}

	if ( (int) ( $request['expires'] ?? 0 ) <= time() ) {
		_180c_account_deletion_clear_request( $user_id );

		return array();
	}

	return $request;
}

/**
 * Crée (ou remplace) la demande de suppression et retourne le jeton en clair.
 *
 * La demande ne porte plus aucun choix : le formulaire ne pose plus de
 * question et le retrait des newsletters est systématique.
 *
 * @param int $user_id Identifiant WP.
 * @return string Jeton en clair, à n'envoyer que par e-mail.
 */
function _180c_account_deletion_create_request( int $user_id ): string {
	$token = bin2hex( random_bytes( 32 ) );

	$request = array(
		'token_hash'   => hash( 'sha256', $token ),
		'expires'      => time() + DAY_IN_SECONDS,
		'requested_at' => time(),
		'last_sent'    => 0,
	);

	update_user_meta( $user_id, _180C_ACCOUNT_DELETION_META, $request );
	delete_transient( _180c_account_deletion_resend_key( $user_id ) );

	return $token;
}

/**
 * Régénère le jeton d'une demande existante sans toucher aux autres choix.
 *
 * Utilisé au renvoi de l'e-mail : l'ancien lien cesse aussitôt de fonctionner.
 *
 * @param int $user_id Identifiant WP.
 * @return string Nouveau jeton en clair, vide si aucune demande en cours.
 */
function _180c_account_deletion_refresh_token( int $user_id ): string {
	$request = _180c_account_deletion_get_request( $user_id );

	if ( empty( $request ) ) {
		return '';
	}

	$token = bin2hex( random_bytes( 32 ) );

	$request['token_hash'] = hash( 'sha256', $token );
	$request['expires']    = time() + DAY_IN_SECONDS;

	update_user_meta( $user_id, _180C_ACCOUNT_DELETION_META, $request );

	return $token;
}

/**
 * Supprime la demande en cours.
 *
 * @param int $user_id Identifiant WP.
 * @return void
 */
function _180c_account_deletion_clear_request( int $user_id ): void {
	delete_user_meta( $user_id, _180C_ACCOUNT_DELETION_META );
	delete_transient( _180c_account_deletion_resend_key( $user_id ) );
}

/**
 * Vérifie un jeton de confirmation contre la demande de l'utilisateur courant.
 *
 * @param int    $user_id Identifiant WP.
 * @param string $token   Jeton reçu (en clair).
 * @return bool Vrai si le jeton correspond à une demande non expirée.
 */
function _180c_account_deletion_verify_token( int $user_id, string $token ): bool {
	if ( '' === $token ) {
		return false;
	}

	$request = _180c_account_deletion_get_request( $user_id );

	if ( empty( $request ) ) {
		return false;
	}

	return hash_equals( (string) $request['token_hash'], hash( 'sha256', $token ) );
}

/**
 * Enregistre l'horodatage du dernier envoi et arme le délai anti-renvoi.
 *
 * @param int $user_id Identifiant WP.
 * @return void
 */
function _180c_account_deletion_mark_sent( int $user_id ): void {
	$request = _180c_account_deletion_get_request( $user_id );

	if ( ! empty( $request ) ) {
		$request['last_sent'] = time();
		update_user_meta( $user_id, _180C_ACCOUNT_DELETION_META, $request );
	}

	set_transient(
		_180c_account_deletion_resend_key( $user_id ),
		1,
		_180C_ACCOUNT_DELETION_RESEND_DELAY
	);
}

/**
 * Indique si un nouvel envoi de l'e-mail est autorisé.
 *
 * @param int $user_id Identifiant WP.
 * @return bool Vrai si le délai anti-renvoi est écoulé.
 */
function _180c_account_deletion_can_resend( int $user_id ): bool {
	return false === get_transient( _180c_account_deletion_resend_key( $user_id ) );
}

/**
 * Construit l'URL de confirmation portant le jeton.
 *
 * @param string $token Jeton en clair.
 * @return string URL absolue.
 */
function _180c_account_deletion_confirm_url( string $token ): string {
	return add_query_arg(
		'confirm',
		rawurlencode( $token ),
		wc_get_account_endpoint_url( _180C_ACCOUNT_DELETION_ENDPOINT )
	);
}
