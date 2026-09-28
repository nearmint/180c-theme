<?php
/**
 * Suppression de compte RGPD — FLUX HISTORIQUE, DÉBRANCHÉ.
 *
 * Ce module est remplacé par inc/account-deletion/, qui porte l'onglet
 * « Supprimer mon compte » de Mon compte. Ses deux points d'entrée ont été
 * débranchés (enregistrements retirés en fin de sections 2 et 3), pour deux raisons
 * vérifiées le 2026-09-01 :
 *
 *  1. Il ne détache pas les commandes ni les abonnements avant d'appeler
 *     `wp_delete_user()`. Or `WC_Subscriptions_Manager::trash_users_subscriptions()`
 *     (hook `delete_user`) appelle `$subscription->delete( true )` : une
 *     suppression DÉFINITIVE malgré le nom de la méthode. Ce flux détruisait
 *     donc les abonnements du compte, ce que la décision produit interdit.
 *
 *  2. Son formulaire (`parts/auth/delete-account.php`) n'est inclus par AUCUN
 *     gabarit — la seule référence du dépôt est la fiche du Design System.
 *     L'écran était donc inatteignable, mais `_180c_handle_delete_confirmation()`
 *     restait branchée sur `init` et se déclenchait sur un simple
 *     `?confirm_delete=…&uid=…`.
 *
 * Le code est conservé intact pour que le retour en arrière tienne en une
 * révocation de commit. Le hook public `180c/account_deleted` est désormais
 * émis par le nouveau pipeline (inc/account-deletion/eraser.php), si bien que
 * `_180c_favorites_purge_user()` garde son contrat.
 *
 * Flux (historique) :
 *  1. L'utilisateur connecté clique "Supprimer mon compte" dans /mon-compte/profil/.
 *  2. Un e-mail de confirmation avec un lien tokenisé (TTL 24h) lui est envoyé.
 *  3. Le clic sur le lien confirme la suppression après vérification du token.
 *
 * Sécurité :
 *  - Token signé via hash_hmac + wp_salt() avec TTL de 24h (transient).
 *  - Si abonnement WC Subscriptions actif → refus + redirection vers résiliation.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// Envoi de l'e-mail de confirmation
// ============================================================

/**
 * Génère un token sécurisé de suppression de compte.
 *
 * Le token est stocké en transient avec un TTL de 24h.
 *
 * @param int $user_id Identifiant WP de l'utilisateur.
 * @return string Token signé.
 */
function _180c_generate_delete_token( int $user_id ): string {
	$token = hash_hmac( 'sha256', $user_id . '|delete|' . time(), wp_salt( 'auth' ) );
	set_transient( '_180c_delete_account_' . $user_id, $token, DAY_IN_SECONDS );
	return $token;
}

/**
 * Valide un token de suppression de compte.
 *
 * @param int    $user_id Identifiant WP.
 * @param string $token   Token soumis.
 * @return bool
 */
function _180c_verify_delete_token( int $user_id, string $token ): bool {
	$stored = get_transient( '_180c_delete_account_' . $user_id );
	if ( false === $stored || ! is_string( $stored ) ) {
		return false;
	}
	return hash_equals( $stored, $token );
}

/**
 * Envoie l'e-mail de confirmation de suppression de compte.
 *
 * @param int $user_id Identifiant WP.
 * @return bool Vrai si l'e-mail a été envoyé.
 */
function _180c_send_delete_confirmation_email( int $user_id ): bool {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}

	$token       = _180c_generate_delete_token( $user_id );
	$confirm_url = add_query_arg(
		array(
			'confirm_delete' => $token,
			'uid'            => $user_id,
		),
		home_url( '/mon-compte/' )
	);

	$subject = sprintf(
		/* translators: %s: site name */
		__( '[%s] Confirmation de suppression de votre compte', '180c' ),
		get_bloginfo( 'name' )
	);

	$message = sprintf(
		/* translators: %s: first name */
		__( 'Bonjour %s,', '180c' ) . "\n\n",
		esc_html( $user->first_name ?: $user->display_name )
	);
	$message .= __( 'Vous avez demandé la suppression définitive de votre compte 180°C.', '180c' ) . "\n\n";
	$message .= __( 'Cliquez sur le lien ci-dessous pour confirmer. Ce lien est valable 24 heures.', '180c' ) . "\n\n";
	$message .= esc_url( $confirm_url ) . "\n\n";
	$message .= __( 'Si vous n\'avez pas fait cette demande, ignorez cet e-mail.', '180c' ) . "\n\n";
	$message .= sprintf(
		/* translators: %s: site name */
		__( 'L\'équipe %s', '180c' ),
		get_bloginfo( 'name' )
	);

	return wp_mail( $user->user_email, $subject, $message );
}

// ============================================================
// Handler — demande de suppression (formulaire Mon Compte)
// ============================================================

/**
 * Traite la demande de suppression de compte depuis le profil Mon Compte.
 *
 * Hookée sur `admin_post__180c_request_delete` (formulaire vers admin-post.php).
 *
 * @return void
 */
function _180c_handle_delete_request(): void {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/connexion/' ) );
		exit;
	}

	$nonce = isset( $_POST['_180c_delete_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_180c_delete_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, '_180c_delete_account' ) ) {
		wp_die( esc_html__( 'Requête invalide.', '180c' ), 403 );
	}

	$user_id = get_current_user_id();

	// Vérification abonnement actif (WC Subscriptions).
	if ( _180c_user_has_active_subscription( $user_id ) ) {
		wp_safe_redirect(
			add_query_arg( 'delete_error', 'active_subscription', home_url( '/mon-compte/abonnement/' ) )
		);
		exit;
	}

	$sent = _180c_send_delete_confirmation_email( $user_id );

	if ( $sent ) {
		wp_safe_redirect(
			add_query_arg( 'delete_requested', '1', home_url( '/mon-compte/profil/' ) )
		);
	} else {
		wp_safe_redirect(
			add_query_arg( 'delete_error', 'mail_failed', home_url( '/mon-compte/profil/' ) )
		);
	}
	exit;
}
// Enregistrement retiré : ce flux est remplacé par inc/account-deletion/, et
// l'action `admin_post__180c_request_delete` n'est plus branchée.

// ============================================================
// Handler — confirmation via lien tokenisé
// ============================================================

/**
 * Confirme et exécute la suppression de compte.
 *
 * Déclenché par ?confirm_delete=TOKEN&uid=USER_ID sur /mon-compte/.
 *
 * @return void
 */
function _180c_handle_delete_confirmation(): void {
	if ( ! isset( $_GET['confirm_delete'], $_GET['uid'] ) ) {
		return;
	}

	$token   = sanitize_text_field( wp_unslash( $_GET['confirm_delete'] ) );
	$user_id = absint( $_GET['uid'] );

	if ( 0 === $user_id || '' === $token ) {
		wp_safe_redirect( home_url( '/mon-compte/' ) );
		exit;
	}

	// Vérification du token.
	if ( ! _180c_verify_delete_token( $user_id, $token ) ) {
		wp_safe_redirect(
			add_query_arg( 'delete_error', 'invalid_token', home_url( '/mon-compte/' ) )
		);
		exit;
	}

	// Vérification abonnement actif (dernier rempart).
	if ( _180c_user_has_active_subscription( $user_id ) ) {
		wp_safe_redirect(
			add_query_arg( 'delete_error', 'active_subscription', home_url( '/mon-compte/abonnement/' ) )
		);
		exit;
	}

	// Suppression des favoris custom.
	_180c_delete_user_favorites( $user_id );

	// Hook custom avant suppression.
	do_action( '180c/account_deleted', $user_id );

	// Révocation du cookie de connexion si connecté.
	if ( is_user_logged_in() && get_current_user_id() === $user_id ) {
		wp_logout();
	}

	// Suppression du compte WordPress.
	require_once ABSPATH . 'wp-admin/includes/user.php';
	$deleted = wp_delete_user( $user_id );

	if ( $deleted ) {
		_180c_log( 'Account deleted (RGPD).', array( 'user_id' => $user_id ), 'info' );
		// Nettoyage du transient (devenu orphelin).
		delete_transient( '_180c_delete_account_' . $user_id );
		wp_safe_redirect( add_query_arg( 'account_deleted', '1', home_url( '/' ) ) );
	} else {
		_180c_log( 'Account deletion failed.', array( 'user_id' => $user_id ), 'error' );
		wp_safe_redirect(
			add_query_arg( 'delete_error', 'deletion_failed', home_url( '/mon-compte/' ) )
		);
	}
	exit;
}
// Enregistrement retiré : ce flux est remplacé par inc/account-deletion/, et
// cette fonction n'est plus branchée sur `init`.

// ============================================================
// Helpers
// ============================================================

/**
 * Retourne vrai si l'utilisateur a un abonnement WC Subscriptions actif.
 *
 * Dégradation silencieuse si le plugin n'est pas actif.
 *
 * @param int $user_id Identifiant WP.
 * @return bool
 */
function _180c_user_has_active_subscription( int $user_id ): bool {
	if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
		return false;
	}
	return wcs_user_has_subscription( $user_id, '', 'active' );
}

/**
 * Supprime les favoris de l'utilisateur dans la table custom.
 *
 * @param int $user_id Identifiant WP.
 * @return void
 */
function _180c_delete_user_favorites( int $user_id ): void {
	global $wpdb;
	$table = $wpdb->prefix . 'user_favorites';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( ! $exists ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->delete(
		$table,
		array( 'user_id' => $user_id ),
		array( '%d' )
	);
}
