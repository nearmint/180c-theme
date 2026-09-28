<?php
/**
 * Email HTML — Confirmation de réactivation d'abonnement (180°C).
 *
 * Override thème de l'email custom `180c_subscription_resumed`.
 * Rendu par _180C_Email_Subscription_Resumed::get_content_html().
 *
 * @package 180c
 *
 * @var string      $email_heading       Titre de l'email.
 * @var string      $customer_first_name Prénom du client.
 * @var string|null $next_payment_date   Date de prochaine facturation (FR) ou null.
 * @var string      $recipes_url         URL de découverte des recettes (CTA).
 * @var string      $additional_content  Contenu additionnel (réglages).
 * @var WC_Email    $email               Instance de l'email.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	if ( '' !== trim( (string) $customer_first_name ) ) {
		/* translators: %s: prénom du client. */
		printf( esc_html__( 'Bonjour %s,', '180c' ), esc_html( $customer_first_name ) );
	} else {
		esc_html_e( 'Bonjour,', '180c' );
	}
	?>
</p>

<p><?php esc_html_e( 'Bonne nouvelle : votre abonnement 180°C est de nouveau actif. Vous retrouvez dès maintenant l\'ensemble des recettes et des contenus réservés aux abonnés.', '180c' ); ?></p>

<?php if ( $next_payment_date ) : ?>
	<p>
		<?php
		printf(
			/* translators: %s: date de prochaine facturation. */
			esc_html__( 'Votre prochaine échéance est prévue le %s.', '180c' ),
			'<strong>' . esc_html( $next_payment_date ) . '</strong>'
		);
		?>
	</p>
<?php endif; ?>

<p style="margin:24px 0;">
	<a href="<?php echo esc_url( $recipes_url ); ?>" style="display:inline-block; background-color:#FFAE3A; color:#0E0E0E; text-decoration:none; padding:13px 26px; font-family:'Oswald','Arial Narrow',Arial,Helvetica,sans-serif; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; border-radius:4px;"><?php esc_html_e( 'Retrouver les recettes', '180c' ); ?></a>
</p>

<p style="margin-top:20px;"><?php esc_html_e( 'L’équipe 180°C', '180c' ); ?></p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
