<?php
/**
 * Email HTML — Confirmation de mise en pause d'abonnement (180°C).
 *
 * Override thème de l'email custom `180c_subscription_paused`.
 * Rendu par _180C_Email_Subscription_Paused::get_content_html().
 *
 * @package 180c
 *
 * @var string   $email_heading       Titre de l'email.
 * @var string   $customer_first_name Prénom du client.
 * @var string   $reactivate_url      URL de réactivation (Mon compte).
 * @var string   $additional_content  Contenu additionnel (réglages).
 * @var WC_Email $email               Instance de l'email.
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

<p><?php esc_html_e( 'Votre abonnement 180°C est bien en pause. La facturation est suspendue : aucun prélèvement ne sera effectué tant que vous n\'aurez pas repris votre abonnement.', '180c' ); ?></p>

<p><?php esc_html_e( 'Votre accès aux recettes et aux contenus réservés aux abonnés est suspendu à compter d\'aujourd\'hui. Le temps restant sur votre abonnement est conservé : vous le retrouverez intégralement à la reprise.', '180c' ); ?></p>

<p><?php esc_html_e( 'Vous pouvez réactiver votre abonnement à tout moment, en un clic, depuis votre espace personnel.', '180c' ); ?></p>

<p style="margin:24px 0;">
	<a href="<?php echo esc_url( $reactivate_url ); ?>" style="display:inline-block; background-color:#FFAE3A; color:#0E0E0E; text-decoration:none; padding:13px 26px; font-family:'Oswald','Arial Narrow',Arial,Helvetica,sans-serif; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; border-radius:4px;"><?php esc_html_e( 'Réactiver mon abonnement', '180c' ); ?></a>
</p>

<p style="margin-top:20px;"><?php esc_html_e( 'L’équipe 180°C', '180c' ); ?></p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
