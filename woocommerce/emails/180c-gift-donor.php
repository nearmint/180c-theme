<?php
/**
 * Email HTML — confirmation de cadeau (donateur).
 *
 * Override thème de l'email custom `180c_gift_donor`.
 * Rendu par _180C_Email_Gift_Donor::get_content_html().
 *
 * @package 180c
 *
 * @var string   $donor_first_name     Prénom du donateur.
 * @var string   $recipient_first_name Prénom du bénéficiaire.
 * @var string   $recipient_email      E-mail du bénéficiaire.
 * @var string   $send_label           Date d'envoi (FR) ou « dès maintenant ».
 * @var string   $amount               Montant formaté de la commande.
 * @var string   $email_heading        Titre de l'email.
 * @var string   $additional_content   Contenu additionnel (réglages).
 * @var WC_Email $email                Instance de l'email.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	if ( '' !== trim( $donor_first_name ) ) {
		/* translators: %s: prénom du donateur. */
		printf( esc_html__( 'Bonjour %s,', '180c' ), esc_html( $donor_first_name ) );
	} else {
		esc_html_e( 'Bonjour,', '180c' );
	}
	?>
</p>

<p><?php esc_html_e( 'Votre abonnement cadeau est confirmé.', '180c' ); ?></p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
	<tr>
		<td style="padding:8px 0;border-bottom:1px solid #EEE;"><?php esc_html_e( 'Bénéficiaire', '180c' ); ?></td>
		<td style="padding:8px 0;border-bottom:1px solid #EEE;text-align:right;">
			<?php
			echo esc_html( '' !== trim( $recipient_first_name ) ? $recipient_first_name : '—' );
			if ( '' !== trim( $recipient_email ) ) {
				echo ' (' . esc_html( $recipient_email ) . ')';
			}
			?>
		</td>
	</tr>
	<tr>
		<td style="padding:8px 0;border-bottom:1px solid #EEE;"><?php esc_html_e( 'Envoi prévu', '180c' ); ?></td>
		<td style="padding:8px 0;border-bottom:1px solid #EEE;text-align:right;"><?php echo esc_html( $send_label ); ?></td>
	</tr>
	<?php if ( '' !== $amount ) : ?>
		<tr>
			<td style="padding:8px 0;"><?php esc_html_e( 'Montant', '180c' ); ?></td>
			<td style="padding:8px 0;text-align:right;"><?php echo wp_kses_post( $amount ); ?></td>
		</tr>
	<?php endif; ?>
</table>

<p>
	<?php
	printf(
		/* translators: %s: prénom du bénéficiaire. */
		esc_html__( 'Le jour J, %s recevra un e-mail l’invitant à activer son accès. Vous n’avez plus rien à faire.', '180c' ),
		esc_html( $recipient_first_name )
	);
	?>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
