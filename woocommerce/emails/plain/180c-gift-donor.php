<?php
/**
 * Email texte brut — confirmation de cadeau (donateur).
 *
 * Override thème de l'email custom `180c_gift_donor` (variante plain).
 *
 * @package 180c
 *
 * @var string $recipient_first_name Prénom du bénéficiaire.
 * @var string $recipient_email      E-mail du bénéficiaire.
 * @var string $send_label           Date d'envoi (FR) ou « dès maintenant ».
 * @var string $amount               Montant formaté de la commande.
 * @var string $email_heading        Titre de l'email.
 * @var string $additional_content   Contenu additionnel.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

echo esc_html__( 'Merci ! Votre abonnement cadeau est confirmé.', '180c' ) . "\n\n";

echo esc_html__( 'Bénéficiaire :', '180c' ) . ' ' . esc_html( $recipient_first_name ) . ' (' . esc_html( $recipient_email ) . ")\n";
echo esc_html__( 'Envoi prévu :', '180c' ) . ' ' . esc_html( $send_label ) . "\n";

if ( '' !== $amount ) {
	echo esc_html__( 'Montant :', '180c' ) . ' ' . esc_html( wp_strip_all_tags( $amount ) ) . "\n";
}

echo "\n";

/* translators: %s: prénom du bénéficiaire. */
echo esc_html( sprintf( __( 'Le jour J, %s recevra un e-mail l’invitant à activer son accès. Vous n’avez plus rien à faire.', '180c' ), $recipient_first_name ) ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n" . esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
