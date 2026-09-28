<?php
/**
 * Email HTML — Bienvenue après activation d'abonnement (180°C).
 *
 * Override thème de l'email custom `180c_welcome_subscription`.
 * Rendu par _180C_Email_Welcome_Subscription::get_content_html().
 *
 * @package 180c
 *
 * @var string   $email_heading       Titre de l'email.
 * @var string   $customer_first_name Prénom du client.
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

<p><?php esc_html_e( 'Bienvenue ! Votre abonnement 180°C est maintenant actif. 🎉', '180c' ); ?></p>

<p><strong><?php esc_html_e( 'Vous avez accès à :', '180c' ); ?></strong></p>

<ul>
	<li><?php esc_html_e( 'Plus de 1 500 recettes testées et approuvées', '180c' ); ?></li>
	<li><?php esc_html_e( 'Les Cahiers de Delphine : notre newsletter exclusive chaque vendredi', '180c' ); ?></li>
	<?php /* APP-RELEASE : décommenter à la sortie des apps mobiles. ?><li><?php esc_html_e( 'L’accès complet aux recettes avec l’app iOS et Android', '180c' ); ?></li><?php */ ?>
	<li><?php esc_html_e( 'Tous les portraits et reportages publiés dans 180°C', '180c' ); ?></li>
	<li><?php esc_html_e( 'Votre carnet personnel pour sauvegarder vos recettes préférées', '180c' ); ?></li>
</ul>

<p style="margin:24px 0;">
	<a href="<?php echo esc_url( home_url( '/recettes/' ) ); ?>" style="display:inline-block; background-color:#FFAE3A; color:#0E0E0E; text-decoration:none; padding:13px 26px; font-family:'Oswald','Arial Narrow',Arial,Helvetica,sans-serif; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; border-radius:4px;"><?php esc_html_e( 'Découvrir les recettes', '180c' ); ?></a>
</p>

<p style="margin-top:20px;">
	<?php esc_html_e( 'Des questions ou besoin d’aide ?', '180c' ); ?>
	<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" style="color:#FFAE3A; font-weight:bold; text-decoration:none;"><?php esc_html_e( 'Nous contacter', '180c' ); ?></a>.
</p>

<p style="margin-top:20px;">
	<?php esc_html_e( 'À bientôt,', '180c' ); ?><br>
	<?php esc_html_e( 'L’équipe 180°C', '180c' ); ?>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
