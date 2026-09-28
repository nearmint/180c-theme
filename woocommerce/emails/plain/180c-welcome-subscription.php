<?php
/**
 * Email texte brut — Bienvenue après activation d'abonnement (180°C).
 *
 * Variante plain de l'email custom `180c_welcome_subscription`.
 * Rendu par _180C_Email_Welcome_Subscription::get_content_plain().
 *
 * @package 180c
 *
 * @var string $email_heading       Titre de l'email.
 * @var string $customer_first_name Prénom du client.
 * @var string $additional_content  Contenu additionnel (réglages).
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

if ( '' !== trim( (string) $customer_first_name ) ) {
	/* translators: %s: prénom du client. */
	printf( esc_html__( 'Bonjour %s,', '180c' ), esc_html( $customer_first_name ) );
} else {
	esc_html_e( 'Bonjour,', '180c' );
}
echo "\n\n";

echo esc_html__( 'Bienvenue ! Votre abonnement 180°C est maintenant actif.', '180c' ) . "\n\n";

echo esc_html__( 'Vous avez accès à :', '180c' ) . "\n";
echo '- ' . esc_html__( 'Plus de 1 500 recettes testées et approuvées', '180c' ) . "\n";
echo '- ' . esc_html__( 'Les Cahiers de Delphine : notre newsletter exclusive chaque vendredi', '180c' ) . "\n";
// APP-RELEASE : décommenter à la sortie des apps mobiles.
// echo '- ' . esc_html__( 'L’accès complet aux recettes avec l’app iOS et Android', '180c' ) . "\n";
echo '- ' . esc_html__( 'Tous les portraits et reportages publiés dans 180°C', '180c' ) . "\n";
echo '- ' . esc_html__( 'Votre carnet personnel pour sauvegarder vos recettes préférées', '180c' ) . "\n\n";

echo esc_html__( 'Découvrir les recettes :', '180c' ) . ' ' . esc_url( home_url( '/recettes/' ) ) . "\n\n";

echo esc_html__( 'Des questions ou besoin d’aide ? Nous contacter :', '180c' ) . ' ' . esc_url( home_url( '/contact/' ) ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo esc_html__( 'À bientôt,', '180c' ) . "\n";
echo esc_html__( 'L’équipe 180°C', '180c' ) . "\n";

echo "\n----------------------------------------\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
