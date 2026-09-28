<?php
/**
 * Email texte brut — Confirmation de réactivation d'abonnement (180°C).
 *
 * Variante plain de l'email custom `180c_subscription_resumed`.
 * Rendu par _180C_Email_Subscription_Resumed::get_content_plain().
 *
 * @package 180c
 *
 * @var string      $email_heading     Titre de l'email.
 * @var string|null $next_payment_date Date de prochaine facturation (FR) ou null.
 * @var string      $recipes_url       URL de découverte des recettes (CTA).
 * @var string      $additional_content Contenu additionnel (réglages).
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

echo esc_html__( 'Bonne nouvelle : votre abonnement 180°C est de nouveau actif. Vous retrouvez dès maintenant l\'ensemble des recettes et des contenus réservés aux abonnés.', '180c' ) . "\n\n";

if ( $next_payment_date ) {
	printf(
		/* translators: %s: date de prochaine facturation. */
		esc_html__( 'Votre prochaine échéance est prévue le %s.', '180c' ),
		esc_html( $next_payment_date )
	);
	echo "\n\n";
}

echo esc_html__( 'Retrouver les recettes :', '180c' ) . "\n";
echo esc_url( $recipes_url ) . "\n\n";

echo esc_html__( 'L’équipe 180°C', '180c' ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n----------------------------------------\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
