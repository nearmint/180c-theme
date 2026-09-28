<?php
/**
 * « Offrir un abonnement » — enregistrement des e-mails transactionnels.
 *
 * Deux e-mails custom :
 *  - bénéficiaire : envoyé le jour de la livraison (action `180c/gift_email_recipient`) ;
 *  - donateur : confirmation à l'achat (action `180c/gift_email_donor`).
 *
 * Les classes sont chargées paresseusement dans le filtre `woocommerce_email_classes`
 * (WooCommerce est alors garanti chargé). Gabarits (override thème) dans
 * woocommerce/emails/ — jamais dans templates/woocommerce/.
 *
 * IMPORTANT — `do_action( '180c/gift_email_*' )` ne suffit pas à envoyer l'e-mail :
 * les classes ne s'abonnent à ces actions que dans leur constructeur, lui-même
 * exécuté par le filtre `woocommerce_email_classes`, qui n'est appliqué que
 * lorsque `WC()->mailer()` est appelé. Or WooCommerce n'initialise le mailer que
 * pour ses propres hooks transactionnels (`WC_Emails::init_transactional_emails()`)
 * — jamais dans une requête Action Scheduler, ni avant nos propres callbacks
 * branchés sur `woocommerce_order_status_*`. Tout envoi doit donc passer par les
 * dispatchers ci-dessous, qui forcent `WC()->mailer()` avant de tirer l'action
 * (même convention que inc/woo/emails.php).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Charge une ligne cadeau depuis la table.
 *
 * @param int $gift_id ID de la ligne.
 * @return object|null Ligne (stdClass) ou null.
 */
function _180c_gift_get_row( $gift_id ) {
	global $wpdb;
	$table = _180c_gift_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $gift_id ) );
}

add_filter( 'woocommerce_email_classes', '_180c_gift_register_emails' );

/**
 * Enregistre les classes d'e-mail cadeau auprès de WooCommerce.
 *
 * @param array $emails Classes d'e-mail existantes.
 * @return array
 */
function _180c_gift_register_emails( $emails ) {
	require_once _180C_THEME_DIR . '/inc/gift/class-180c-email-gift-recipient.php';
	require_once _180C_THEME_DIR . '/inc/gift/class-180c-email-gift-donor.php';

	if ( class_exists( '_180C_Email_Gift_Recipient' ) ) {
		$emails['_180C_Email_Gift_Recipient'] = new _180C_Email_Gift_Recipient();
	}
	if ( class_exists( '_180C_Email_Gift_Donor' ) ) {
		$emails['_180C_Email_Gift_Donor'] = new _180C_Email_Gift_Donor();
	}

	return $emails;
}

/**
 * Récupère une instance d'e-mail cadeau en garantissant l'init du mailer.
 *
 * `WC()->mailer()` applique `woocommerce_email_classes` : c'est le seul moment
 * où les classes cadeau sont instanciées et où elles s'abonnent aux actions
 * `180c/gift_email_*`. Sans cet appel, l'action part dans le vide.
 *
 * @param string $class_name Nom de la classe d'e-mail cadeau.
 * @return WC_Email|null Instance, ou null si WooCommerce est indisponible.
 */
function _180c_gift_mailer_email( $class_name ) {
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
 * Envoie l'e-mail de confirmation au donateur.
 *
 * @param int $gift_id ID de la ligne cadeau.
 * @return bool Vrai si le message a été accepté par wp_mail().
 */
function _180c_gift_send_donor_email( $gift_id ) {
	$email = _180c_gift_mailer_email( '_180C_Email_Gift_Donor' );

	if ( ! $email ) {
		_180c_log( 'Gift : classe e-mail donateur indisponible', array( 'gift_id' => (int) $gift_id ), 'error' );
		return false;
	}

	$email->last_send_ok = false;

	// Le hook reste le point d'extension public ; le mailer étant désormais
	// initialisé, la classe y est bien branchée.
	do_action( '180c/gift_email_donor', (int) $gift_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).

	return ! empty( $email->last_send_ok );
}

/**
 * Envoie l'e-mail cadeau au bénéficiaire.
 *
 * @param int   $gift_id ID de la ligne cadeau.
 * @param array $context Contexte de livraison (set_password_url, start_ts, end_ts…).
 * @return bool Vrai si le message a été accepté par wp_mail().
 */
function _180c_gift_send_recipient_email( $gift_id, $context = array() ) {
	$email = _180c_gift_mailer_email( '_180C_Email_Gift_Recipient' );

	if ( ! $email ) {
		_180c_log( 'Gift : classe e-mail bénéficiaire indisponible', array( 'gift_id' => (int) $gift_id ), 'error' );
		return false;
	}

	$email->last_send_ok = false;

	do_action( '180c/gift_email_recipient', (int) $gift_id, is_array( $context ) ? $context : array() ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).

	return ! empty( $email->last_send_ok );
}
