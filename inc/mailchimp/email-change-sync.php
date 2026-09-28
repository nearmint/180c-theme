<?php
/**
 * Répercute un changement d'e-mail de compte sur le contact Mailchimp.
 *
 * Avant ce fichier, rien ne suivait le changement : l'ancien contact gardait
 * son tag premium (newsletter envoyée à une adresse que le client a quittée)
 * et la nouvelle adresse n'était taguée qu'à la transition d'abonnement ou
 * d'adhésion suivante — parfois un an plus tard.
 *
 * Sur `profile_update` (Mon compte, admin, API REST WooCommerce), quand l'e-mail
 * du compte change :
 *
 * 1. l'ancien contact existe et la nouvelle adresse est libre dans l'audience
 *    → `PATCH email_address` : même contact, tags et historique conservés ;
 * 2. la nouvelle adresse existe déjà (cas typique : correction d'une faute de
 *    frappe vers l'adresse réelle, déjà inscrite à la gratuite)
 *    → tag premium posé sur le contact existant si le compte est abonné,
 *      retiré de l'ancien contact, ancien contact archivé ;
 * 3. l'ancien contact n'existe pas → tag premium posé sur la nouvelle adresse
 *    si le compte est abonné.
 *
 * Exception : une nouvelle adresse qui n'est qu'un alias `+…` de l'ancienne
 * (renommage d'un compte abandonné en `x+ancien-compte@domaine`) ne touche à
 * rien. La boîte est la même, et le contact appartient à la personne réelle.
 *
 * Aucun échec n'est silencieux : chacun est écrit dans le journal PHP (même
 * sans WP_DEBUG_LOG), mémorisé dans la meta utilisateur
 * `_180c_mc_email_sync_error` et signalé par `180c/mc/email_change_sync_failed`.
 * Aucune adresse e-mail n'est journalisée : l'ID du compte suffit.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta utilisateur : dernier échec de synchro (affichage admin, diagnostic).
 */
const _180C_MC_EMAIL_SYNC_ERROR_META = '_180c_mc_email_sync_error';

/**
 * Retire la partie `+…` de la partie locale d'une adresse.
 *
 * @param string $email Adresse, déjà en minuscules.
 * @return string Adresse sans sous-adressage.
 */
function _180c_mc_email_strip_plus( string $email ): string {
	$at = strrpos( $email, '@' );
	if ( false === $at ) {
		return $email;
	}
	$local = substr( $email, 0, $at );
	$plus  = strpos( $local, '+' );

	return false === $plus ? $email : substr( $local, 0, $plus ) . substr( $email, $at );
}

/**
 * Consigne un échec de synchro, de façon durable et visible.
 *
 * @param int    $user_id ID du compte.
 * @param string $step    Étape en échec.
 * @param mixed  $detail  Code HTTP, code d'erreur ou WP_Error.
 * @return void
 */
function _180c_mc_email_sync_fail( int $user_id, string $step, $detail ): void {
	if ( is_wp_error( $detail ) ) {
		$detail = $detail->get_error_code();
	} elseif ( is_array( $detail ) ) {
		$detail = 'HTTP ' . (int) ( $detail['code'] ?? 0 ) . ' ' . (string) ( $detail['data']['title'] ?? '' );
	}
	$context = array(
		'user_id' => $user_id,
		'step'    => $step,
		'detail'  => (string) $detail,
	);

	_180c_log( 'Synchro Mailchimp du changement d’e-mail échouée', $context, 'error' );
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Échec à ne jamais taire, même sans WP_DEBUG_LOG.
	error_log( '[180c] Synchro Mailchimp du changement d’e-mail échouée ' . wp_json_encode( $context ) );
	update_user_meta( $user_id, _180C_MC_EMAIL_SYNC_ERROR_META, $context + array( 'time' => time() ) );

	/**
	 * Déclenché à chaque échec de synchro Mailchimp d'un changement d'e-mail.
	 *
	 * @param int    $user_id ID du compte.
	 * @param string $step    Étape en échec.
	 * @param string $detail  Détail technique (sans adresse e-mail).
	 */
	do_action( '180c/mc/email_change_sync_failed', $user_id, $step, (string) $detail ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Statut Mailchimp d'une adresse dans l'audience.
 *
 * @param string $aud   ID d'audience.
 * @param string $email Adresse, en minuscules.
 * @return string|WP_Error Statut Mailchimp, `absent` (404), ou WP_Error.
 */
function _180c_mc_email_sync_member_status( string $aud, string $email ) {
	$res = _180c_mc_request( 'GET', '/lists/' . $aud . '/members/' . md5( $email ) . '?fields=status' );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( 404 === (int) $res['code'] ) {
		return 'absent';
	}
	if ( (int) $res['code'] >= 400 ) {
		return _180c_mc_error_from_result( $res );
	}

	return (string) ( $res['data']['status'] ?? 'absent' );
}

/**
 * Pose le tag premium sur une adresse, et consigne un échec éventuel.
 *
 * @param int    $user_id ID du compte.
 * @param string $email   Adresse cible.
 * @param string $step    Libellé d'étape pour le journal.
 * @return bool True si le tag est posé.
 */
function _180c_mc_email_sync_grant( int $user_id, string $email, string $step ): bool {
	$res = _180c_mc_premium_set( $email, true );
	if ( is_wp_error( $res ) ) {
		_180c_mc_email_sync_fail( $user_id, $step, $res );
		return false;
	}
	return true;
}

/**
 * Répercute le changement d'e-mail d'un compte sur l'audience Mailchimp.
 *
 * @param int     $user_id       ID du compte.
 * @param WP_User $old_user_data Données du compte AVANT la mise à jour.
 * @return void
 */
function _180c_mc_email_change_sync( $user_id, $old_user_data ): void {
	$user_id = (int) $user_id;
	$user    = get_userdata( $user_id );
	if ( ! $user || ! is_object( $old_user_data ) || ! isset( $old_user_data->user_email ) ) {
		return;
	}

	$old = strtolower( trim( (string) $old_user_data->user_email ) );
	$new = strtolower( trim( (string) $user->user_email ) );

	// Casse seule : même hash Mailchimp, même contact. Rien à faire.
	if ( $old === $new || ! is_email( $old ) || ! is_email( $new ) ) {
		return;
	}

	// Renommage d'un compte abandonné en alias de sa propre boîte.
	if ( _180c_mc_email_strip_plus( $new ) === _180c_mc_email_strip_plus( $old ) ) {
		return;
	}

	/**
	 * Faut-il répercuter ce changement d'e-mail sur Mailchimp ?
	 *
	 * @param bool $sync    True par défaut.
	 * @param int  $user_id ID du compte.
	 */
	if ( ! (bool) apply_filters( '180c/mc/sync_email_change', true, $user_id ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
		return;
	}

	$aud = function_exists( '_180c_mc_premium_audience_id' ) ? _180c_mc_premium_audience_id() : '';
	if ( '' === $aud ) {
		_180c_mc_email_sync_fail( $user_id, 'config', 'audience absente' );
		return;
	}

	// Garde sans contrôle d'utilisateur courant : la mise à jour peut venir de
	// l'admin ou de l'API REST (cf. inc/push/subscriber-status.php).
	$is_subscriber = function_exists( '_180c_push_user_is_subscriber' ) && _180c_push_user_is_subscriber( $user_id );

	$old_status = _180c_mc_email_sync_member_status( $aud, $old );
	$new_status = _180c_mc_email_sync_member_status( $aud, $new );
	if ( is_wp_error( $old_status ) || is_wp_error( $new_status ) ) {
		_180c_mc_email_sync_fail( $user_id, 'lecture', is_wp_error( $old_status ) ? $old_status : $new_status );
		return;
	}

	$ok = true;

	if ( in_array( $old_status, array( 'absent', 'archived' ), true ) ) {
		// 3. Pas d'ancien contact à déplacer.
		if ( $is_subscriber ) {
			$ok = _180c_mc_email_sync_grant( $user_id, $new, 'tag nouvelle adresse (sans ancien contact)' );
		}
	} elseif ( 'absent' === $new_status ) {
		// 1. Adresse libre : le contact change d'adresse, tags compris.
		$res = _180c_mc_request( 'PATCH', '/lists/' . $aud . '/members/' . md5( $old ), array( 'email_address' => $new ) );
		if ( is_wp_error( $res ) || (int) $res['code'] >= 400 ) {
			_180c_mc_email_sync_fail( $user_id, 'patch email_address', $res );
			$ok = false;
			// L'abonné ne doit pas perdre sa newsletter premium pour autant.
			if ( $is_subscriber ) {
				_180c_mc_email_sync_grant( $user_id, $new, 'tag nouvelle adresse (après échec du patch)' );
			}
		} elseif ( $is_subscriber ) {
			// Idempotent : couvre un ancien contact qui n'avait pas le tag.
			$ok = _180c_mc_email_sync_grant( $user_id, $new, 'tag après patch' );
		}
	} else {
		// 2. La nouvelle adresse est déjà un contact : on la tague, on retire
		// l'ancienne de la cible premium puis on l'archive (jamais supprimée).
		if ( $is_subscriber ) {
			$ok = _180c_mc_email_sync_grant( $user_id, $new, 'tag contact existant' );
		}

		if ( $ok ) {
			$tag = _180c_mc_premium_tag_name();
			$res = '' === $tag
				? new WP_Error( 'mc_tag_not_configured' )
				: _180c_mc_request(
					'POST',
					'/lists/' . $aud . '/members/' . md5( $old ) . '/tags',
					array(
						'tags' => array(
							array(
								'name'   => $tag,
								'status' => 'inactive',
							),
						),
					)
				);
			if ( is_wp_error( $res ) || (int) $res['code'] >= 400 ) {
				_180c_mc_email_sync_fail( $user_id, 'retrait tag ancien contact', $res );
				$ok = false;
			}
		}

		if ( $ok ) {
			// DELETE sur un membre = archivage Mailchimp (réversible).
			$res = _180c_mc_request( 'DELETE', '/lists/' . $aud . '/members/' . md5( $old ) );
			if ( is_wp_error( $res ) || (int) $res['code'] >= 400 ) {
				_180c_mc_email_sync_fail( $user_id, 'archivage ancien contact', $res );
				$ok = false;
			}
		}
	}

	if ( $ok ) {
		delete_user_meta( $user_id, _180C_MC_EMAIL_SYNC_ERROR_META );
	}
}
add_action( 'profile_update', '_180c_mc_email_change_sync', 20, 2 );
