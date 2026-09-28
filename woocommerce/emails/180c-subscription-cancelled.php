<?php
/**
 * Email HTML — Confirmation de résiliation d'abonnement (180°C).
 *
 * Override thème de l'email custom `180c_subscription_cancelled`.
 * Rendu par _180C_Email_Subscription_Cancelled::get_content_html().
 *
 * @package 180c
 *
 * @var string      $email_heading       Titre de l'email.
 * @var string      $customer_first_name Prénom du client.
 * @var string|null $end_date            Date de fin d'accès (FR) ou null.
 * @var string      $additional_content Contenu additionnel (réglages).
 * @var WC_Email    $email              Instance de l'email.
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

<p><?php esc_html_e( 'Nous confirmons la résiliation de votre abonnement 180°C.', '180c' ); ?></p>

<?php if ( $end_date ) : ?>
	<p>
		<?php
		printf(
			/* translators: %s: date de fin d'accès. */
			esc_html__( 'Vous conservez l\'accès à l\'ensemble de nos recettes jusqu\'au %s. Passé cette date, votre abonnement ne sera pas renouvelé.', '180c' ),
			esc_html( $end_date )
		);
		?>
	</p>
<?php else : ?>
	<p><?php esc_html_e( 'Votre abonnement a pris fin et ne sera pas renouvelé.', '180c' ); ?></p>
<?php endif; ?>

<p>
	<?php
	$sub_url = apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) );
	esc_html_e( 'Vous pouvez vous réabonner à tout moment via la', '180c' );
	?>
	<a href="<?php echo esc_url( $sub_url ); ?>" style="color:#FFAE3A; font-weight:bold; text-decoration:none;"><?php esc_html_e( 'page abonnement', '180c' ); ?></a>.
</p>

<p><?php esc_html_e( 'L’équipe 180°C', '180c' ); ?></p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
