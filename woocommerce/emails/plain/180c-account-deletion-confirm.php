<?php
/**
 * Email texte brut — confirmation d'une demande de suppression de compte.
 *
 * Override thème de l'email custom `180c_account_deletion_confirm` (plain).
 * L'URL de confirmation est seule sur sa ligne : les clients texte la
 * transforment en lien cliquable sans la tronquer.
 *
 * @package 180c
 *
 * @var string $first_name         Prénom du titulaire, éventuellement vide.
 * @var string $confirm_url        URL de confirmation portant le jeton.
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

echo esc_html__( 'Vous avez demandé à supprimer votre compte 180°C.', '180c' ) . "\n\n";

echo esc_html__( 'Pour confirmer, ouvrez le lien ci-dessous.', '180c' ) . "\n\n";

echo esc_url_raw( $confirm_url ) . "\n\n";

echo esc_html__( 'Ce lien fonctionne pendant 24 heures. Passé ce délai, il faudra refaire une demande.', '180c' ) . "\n\n";

echo esc_html__( 'Une fois confirmée, la suppression est définitive.', '180c' ) . "\n\n";

echo esc_html__( 'Vous n’êtes pas à l’origine de cette demande ? Ignorez cet e-mail, votre compte restera intact. Par sécurité, changez votre mot de passe.', '180c' ) . "\n\n";

echo esc_html__( 'À bientôt,', '180c' ) . "\n";
echo esc_html__( 'L’équipe 180°C', '180c' ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Filtre du cœur de WooCommerce, appliqué par tout gabarit d'e-mail texte.
echo "\n" . esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
