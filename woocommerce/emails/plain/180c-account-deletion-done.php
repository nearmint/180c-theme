<?php
/**
 * Email texte brut — compte supprimé.
 *
 * Override thème de l'email custom `180c_account_deletion_done` (plain).
 *
 * @package 180c
 *
 * @var string $first_name         Prénom, éventuellement vide.
 * @var string $deleted_on         Date de suppression (format FR).
 * @var bool   $newsletter_removed Le contact Mailchimp a-t-il été supprimé.
 * @var string $contact_email      Adresse de contact.
 * @var string $email_heading      Titre de l'email.
 * @var string $additional_content Contenu additionnel.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

if ( '' !== trim( $first_name ) ) {
	/* translators: %s: prénom du titulaire du compte. */
	echo esc_html( sprintf( __( 'Bonjour %s,', '180c' ), $first_name ) ) . "\n\n";
} else {
	echo esc_html__( 'Bonjour,', '180c' ) . "\n\n";
}

/* translators: %s: date de suppression (ex. « 4 septembre 2026 »). */
echo esc_html( sprintf( __( 'Nous avons supprimé votre compte 180°C le %s.', '180c' ), $deleted_on ) ) . "\n\n";

echo esc_html__( 'Nous avons effacé vos informations personnelles et vos favoris.', '180c' ) . "\n";

if ( $newsletter_removed ) {
	echo esc_html__( 'Vous ne recevrez plus nos newsletters.', '180c' ) . "\n";
}

echo "\n";

echo esc_html__( 'Nous gardons vos anciennes commandes et vos factures. La loi nous y oblige. Elles ne sont plus rattachées à un compte.', '180c' ) . "\n\n";

echo esc_html__( 'Vous pouvez créer un nouveau compte à tout moment, avec la même adresse e-mail.', '180c' ) . "\n\n";

if ( '' !== $contact_email ) {
	/* translators: %s: adresse e-mail de contact. */
	echo esc_html( sprintf( __( 'Une question ? Écrivez-nous à %s.', '180c' ), $contact_email ) ) . "\n\n";
}

echo esc_html__( 'Merci pour votre confiance,', '180c' ) . "\n";
echo esc_html__( 'L’équipe 180°C', '180c' ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtre du cœur de WooCommerce, appliqué par tout gabarit d'e-mail texte.
echo "\n" . esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
