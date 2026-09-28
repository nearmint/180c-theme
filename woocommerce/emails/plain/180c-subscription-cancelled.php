<?php
/**
 * Email texte brut — Confirmation de résiliation d'abonnement (180°C).
 *
 * Variante plain de l'email custom `180c_subscription_cancelled`.
 * Rendu par _180C_Email_Subscription_Cancelled::get_content_plain().
 *
 * @package 180c
 *
 * @var string      $email_heading      Titre de l'email.
 * @var string|null $end_date           Date de fin d'accès (FR) ou null.
 * @var string      $additional_content Contenu additionnel (réglages).
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

echo esc_html__( 'Nous confirmons la résiliation de votre abonnement 180°C.', '180c' ) . "\n\n";

if ( $end_date ) {
	printf(
		/* translators: %s: date de fin d'accès. */
		esc_html__( 'Vous conservez l\'accès à l\'ensemble de nos recettes jusqu\'au %s. Passé cette date, votre abonnement ne sera pas renouvelé.', '180c' ),
		esc_html( $end_date )
	);
} else {
	echo esc_html__( 'Votre abonnement a pris fin et ne sera pas renouvelé.', '180c' );
}
echo "\n\n";

$sub_url = apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) );
echo esc_html__( 'Vous pouvez vous réabonner à tout moment via la page abonnement :', '180c' ) . "\n";
echo esc_url( $sub_url ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n----------------------------------------\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
