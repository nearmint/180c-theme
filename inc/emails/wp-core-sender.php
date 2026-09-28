<?php
/**
 * Expéditeur des e-mails WordPress core — alignement sur l'expéditeur WooCommerce.
 *
 * WooCommerce envoie ses e-mails depuis « 180°C » et l'adresse de son option
 * `woocommerce_email_from_address` (filtres dans inc/woo/emails.php). Les e-mails
 * WP natifs (réinitialisation de mot de passe, suppression de compte, RGPD,
 * sécurité, mises à jour auto) partent eux du défaut WordPress
 * `wordpress@{domaine}` / « WordPress », d'où un double expéditeur incohérent
 * (marque + alignement SPF/DKIM partiel).
 *
 * Ce fichier pose `wp_mail_from` / `wp_mail_from_name` pour aligner TOUS les
 * e-mails sur le même expéditeur, SANS écraser le From propre de WooCommerce :
 * `WC_Email::send()` ajoute ses propres filtres au moment de l'envoi (donc après
 * l'ajout réalisé ici au chargement) et reste prioritaire pour ses e-mails ; par
 * sécurité supplémentaire, on ne remplace de toute façon que la valeur **par
 * défaut** de WordPress (jamais une adresse déjà personnalisée).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adresse expéditrice cible, partagée avec WooCommerce.
 *
 * Non versionnée : constante `_180C_MAIL_FROM_ADDRESS` (wp-config.php) si elle
 * est définie, sinon l'adresse expéditrice réglée dans WooCommerce. Chaîne vide
 * si aucune des deux n'est exploitable : le défaut WordPress est alors conservé.
 *
 * @return string
 */
function _180c_mail_from_address_value(): string {
	$address = defined( '_180C_MAIL_FROM_ADDRESS' ) ? (string) _180C_MAIL_FROM_ADDRESS : (string) get_option( 'woocommerce_email_from_address', '' );

	return is_email( $address ) ? $address : '';
}

/** Nom expéditeur cible, partagé avec WooCommerce. */
const _180C_MAIL_FROM_NAME = '180°C';

/**
 * Calcule l'adresse expéditrice par défaut de WordPress (`wordpress@{domaine}`).
 *
 * Reproduit la logique de la fonction pluggable `wp_mail()` afin de ne remplacer
 * QUE ce défaut (et donc de respecter toute adresse déjà personnalisée par un
 * autre filtre, dont celui de WooCommerce posé à l'envoi).
 *
 * @return string Adresse par défaut, ou chaîne vide si le host est introuvable.
 */
function _180c_wp_default_from_address(): string {
	$sitename = wp_parse_url( network_home_url(), PHP_URL_HOST );

	if ( ! is_string( $sitename ) || '' === $sitename ) {
		return '';
	}

	$sitename = strtolower( $sitename );
	if ( str_starts_with( $sitename, 'www.' ) ) {
		$sitename = substr( $sitename, 4 );
	}

	return 'wordpress@' . $sitename;
}

/**
 * Aligne l'adresse expéditrice des e-mails WP-core sur celle de WooCommerce.
 *
 * @param string $from Adresse courante fournie par le filtre.
 * @return string
 */
function _180c_mail_from_address( string $from ): string {
	// On ne touche pas une adresse déjà personnalisée (ex. WooCommerce à l'envoi).
	if ( '' !== $from && _180c_wp_default_from_address() !== $from ) {
		return $from;
	}

	$target = _180c_mail_from_address_value();

	return '' !== $target ? $target : $from;
}
add_filter( 'wp_mail_from', '_180c_mail_from_address' );

/**
 * Aligne le nom expéditeur des e-mails WP-core sur celui de WooCommerce.
 *
 * @param string $name Nom courant fourni par le filtre.
 * @return string
 */
function _180c_mail_from_name( string $name ): string {
	// Défaut WordPress = « WordPress ». On ne remplace que ce défaut.
	if ( '' !== $name && 'WordPress' !== $name ) {
		return $name;
	}

	return _180C_MAIL_FROM_NAME;
}
add_filter( 'wp_mail_from_name', '_180c_mail_from_name' );

/**
 * Journalise la raison réelle d'un échec d'envoi.
 *
 * `wp_mail()` ne renvoie qu'un booléen : sans ce hook, un échec PHPMailer est
 * totalement muet (aucun plugin de log d'e-mails n'est installé). On ne
 * journalise QUE le message d'erreur : `$error->get_error_data()` porte le
 * destinataire, le sujet et le corps du message, qui n'ont rien à faire dans un
 * log. Les adresses e-mail que PHPMailer glisse parfois dans son propre message
 * (« Invalid address: … ») sont masquées avant écriture.
 *
 * @param WP_Error $error Erreur remontée par `wp_mail()`.
 * @return void
 */
function _180c_log_mail_failure( $error ): void {
	if ( ! is_wp_error( $error ) || ! function_exists( '_180c_log' ) ) {
		return;
	}

	$reason = preg_replace(
		'/[^\s<>()\[\],;:"]+@[^\s<>()\[\],;:"]+/',
		'[email masqué]',
		$error->get_error_message()
	);

	_180c_log( 'wp_mail_failed: ' . $reason, array(), 'error' );
}
add_action( 'wp_mail_failed', '_180c_log_mail_failure' );
