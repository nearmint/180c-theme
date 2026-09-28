<?php
/**
 * Email texte brut — Confirmation de mise en pause d'abonnement (180°C).
 *
 * Variante plain de l'email custom `180c_subscription_paused`.
 * Rendu par _180C_Email_Subscription_Paused::get_content_plain().
 *
 * @package 180c
 *
 * @var string $email_heading      Titre de l'email.
 * @var string $reactivate_url     URL de réactivation (Mon compte).
 * @var string $additional_content Contenu additionnel (réglages).
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

echo esc_html__( 'Votre abonnement 180°C est bien en pause. La facturation est suspendue : aucun prélèvement ne sera effectué tant que vous n\'aurez pas repris votre abonnement.', '180c' ) . "\n\n";

echo esc_html__( 'Votre accès aux recettes et aux contenus réservés aux abonnés est suspendu à compter d\'aujourd\'hui. Le temps restant sur votre abonnement est conservé : vous le retrouverez intégralement à la reprise.', '180c' ) . "\n\n";

echo esc_html__( 'Réactivez votre abonnement à tout moment, en un clic, depuis votre espace personnel :', '180c' ) . "\n";
echo esc_url( $reactivate_url ) . "\n\n";

echo esc_html__( 'L’équipe 180°C', '180c' ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n----------------------------------------\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
