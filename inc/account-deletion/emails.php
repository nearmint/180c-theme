<?php
/**
 * Suppression de compte — enregistrement et envoi des e-mails.
 *
 * Deux e-mails, tous deux passés par le mailer WooCommerce :
 *  - confirmation de la demande (action `180c/account_deletion_email_confirm`) ;
 *  - confirmation de la suppression (action `180c/account_deletion_email_done`).
 *
 * IMPORTANT — tirer l'action ne suffit pas à envoyer : les classes ne s'y
 * abonnent que dans leur constructeur, exécuté par le filtre
 * `woocommerce_email_classes`, lui-même appliqué au seul appel de
 * `WC()->mailer()`. Tout envoi passe donc par les dispatchers ci-dessous, qui
 * forcent l'init du mailer avant de tirer l'action (même convention que
 * inc/gift/emails.php et inc/woo/emails.php).
 *
 * Les données sont passées en tableau, jamais par un ID d'utilisateur :
 * le second e-mail part APRÈS `wp_delete_user()`, quand le compte n'existe plus.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre les classes d'e-mail auprès de WooCommerce.
 *
 * @param array $emails Classes d'e-mail existantes.
 * @return array Classes enrichies.
 */
function _180c_account_deletion_register_emails( $emails ) {
	require_once _180C_THEME_DIR . '/inc/account-deletion/class-180c-email-account-deletion-confirm.php';
	require_once _180C_THEME_DIR . '/inc/account-deletion/class-180c-email-account-deletion-done.php';

	if ( class_exists( '_180C_Email_Account_Deletion_Confirm' ) ) {
		$emails['_180C_Email_Account_Deletion_Confirm'] = new _180C_Email_Account_Deletion_Confirm();
	}

	if ( class_exists( '_180C_Email_Account_Deletion_Done' ) ) {
		$emails['_180C_Email_Account_Deletion_Done'] = new _180C_Email_Account_Deletion_Done();
	}

	return $emails;
}
add_filter( 'woocommerce_email_classes', '_180c_account_deletion_register_emails' );

/**
 * Récupère une instance d'e-mail en garantissant l'init du mailer.
 *
 * @param string $class_name Nom de la classe d'e-mail.
 * @return WC_Email|null Instance, ou null si WooCommerce est indisponible.
 */
function _180c_account_deletion_mailer_email( string $class_name ) {
	if ( ! function_exists( 'WC' ) || ! is_callable( array( WC(), 'mailer' ) ) ) {
		return null;
	}

	$mailer = WC()->mailer();

	if ( ! $mailer || empty( $mailer->emails[ $class_name ] ) ) {
		return null;
	}

	return $mailer->emails[ $class_name ];
}

/**
 * Envoie l'e-mail de confirmation de demande.
 *
 * @param int    $user_id Identifiant WP.
 * @param string $token   Jeton en clair.
 * @return bool Vrai si le message a été accepté par wp_mail().
 */
function _180c_account_deletion_send_confirmation( int $user_id, string $token ): bool {
	$user = get_userdata( $user_id );

	if ( ! $user || '' === $token ) {
		return false;
	}

	$email = _180c_account_deletion_mailer_email( '_180C_Email_Account_Deletion_Confirm' );

	if ( ! $email ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'échec non bloquant, sans donnée personnelle.
		error_log( '[180c account-deletion] classe e-mail de confirmation indisponible (user ' . $user_id . ')' );

		return false;
	}

	$email->last_send_ok = false;

	do_action( // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).
		'180c/account_deletion_email_confirm',
		array(
			'email'       => (string) $user->user_email,
			'first_name'  => (string) $user->first_name,
			'confirm_url' => _180c_account_deletion_confirm_url( $token ),
		)
	);

	$sent = ! empty( $email->last_send_ok );

	if ( $sent ) {
		_180c_account_deletion_mark_sent( $user_id );
	} else {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'échec non bloquant, sans donnée personnelle.
		error_log( '[180c account-deletion] envoi de l\'e-mail de confirmation refusé (user ' . $user_id . ')' );
	}

	return $sent;
}

/**
 * Envoie l'e-mail de confirmation de suppression.
 *
 * Appelé APRÈS wp_delete_user() : toutes les données viennent de l'instantané
 * pris avant la suppression, aucun accès au compte n'est possible ici.
 *
 * @param array $snapshot {
 *     Instantané pris avant la suppression.
 *
 *     @type string $email              Destinataire.
 *     @type string $first_name         Prénom, éventuellement vide.
 *     @type bool   $newsletter_removed Le contact Mailchimp a-t-il été supprimé.
 * }
 * @return bool Vrai si le message a été accepté par wp_mail().
 */
function _180c_account_deletion_send_farewell( array $snapshot ): bool {
	if ( empty( $snapshot['email'] ) ) {
		return false;
	}

	$email = _180c_account_deletion_mailer_email( '_180C_Email_Account_Deletion_Done' );

	if ( ! $email ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'échec non bloquant, sans donnée personnelle.
		error_log( '[180c account-deletion] classe e-mail de fin indisponible' );

		return false;
	}

	$email->last_send_ok = false;

	do_action( // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).
		'180c/account_deletion_email_done',
		array(
			'email'              => (string) $snapshot['email'],
			'first_name'         => (string) ( $snapshot['first_name'] ?? '' ),
			'newsletter_removed' => ! empty( $snapshot['newsletter_removed'] ),
		)
	);

	$sent = ! empty( $email->last_send_ok );

	if ( ! $sent ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'échec non bloquant, sans donnée personnelle.
		error_log( '[180c account-deletion] envoi de l\'e-mail de fin refusé' );
	}

	return $sent;
}
