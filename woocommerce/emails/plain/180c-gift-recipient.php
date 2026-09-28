<?php
/**
 * Email texte brut — cadeau reçu (bénéficiaire).
 *
 * Override thème de l'email custom `180c_gift_recipient` (variante plain).
 *
 * @package 180c
 *
 * @var string $first_name         Prénom du bénéficiaire.
 * @var string $message            Message personnel du donateur.
 * @var string $set_password_url   Lien de définition de mot de passe ou vide.
 * @var string $login_url          URL de connexion.
 * @var string $start_date         Date de début d'accès (FR).
 * @var string $end_date           Date de fin d'accès (FR).
 * @var string $email_heading      Titre de l'email.
 * @var string $additional_content Contenu additionnel.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

/* translators: %s: prénom du bénéficiaire. */
echo esc_html( sprintf( __( 'Bonjour %s,', '180c' ), $first_name ) ) . "\n\n";

echo esc_html__( 'Bonne nouvelle : on vous offre un abonnement de 12 mois aux recettes de 180°C — un accès illimité à toutes nos recettes en ligne.', '180c' ) . "\n\n";

if ( '' !== trim( $message ) ) {
	echo esc_html__( 'Message :', '180c' ) . "\n";
	echo esc_html( $message ) . "\n\n";
}

if ( '' !== $set_password_url ) {
	echo esc_html__( 'Un compte a été créé pour vous. Définissez votre mot de passe pour accéder à vos recettes :', '180c' ) . "\n";
	echo esc_url_raw( $set_password_url ) . "\n\n";
} else {
	echo esc_html__( 'Votre accès est déjà rattaché à votre compte. Connectez-vous pour en profiter :', '180c' ) . "\n";
	echo esc_url_raw( $login_url ) . "\n\n";
}

if ( '' !== $end_date ) {
	/* translators: 1: date de début, 2: date de fin. */
	echo esc_html( sprintf( __( 'Votre accès est ouvert du %1$s au %2$s.', '180c' ), $start_date, $end_date ) ) . "\n\n";
}

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n" . esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
