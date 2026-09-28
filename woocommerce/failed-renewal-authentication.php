<?php
/**
 * Override thème — WooPayments « failed_renewal_authentication » (#46).
 *
 * Surcharge le template du plugin (`woocommerce-payments/.../emails/
 * failed-renewal-authentication.php`) pour livrer le corps en français et un
 * bouton d'action brandé. Le template d'origine appelle déjà
 * `woocommerce_email_header` / `woocommerce_email_footer` → le châssis 180c est
 * conservé. Le sujet et le titre sont gérés en option (apply-email-translations).
 *
 * Variables fournies par WC_Payments_Email_Failed_Renewal_Authentication::get_content_html() :
 *
 * @var WC_Order $order             Commande de renouvellement.
 * @var string   $email_heading     Titre (bande accent).
 * @var string   $authorization_url URL d'autorisation 3-D Secure.
 * @var WC_Email $email             Objet e-mail.
 *
 * @package 180c
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php $_180c_first_name = ( $order instanceof WC_Order ) ? $order->get_billing_first_name() : ''; ?>
<p style="margin:0 0 18px 0;">
	<?php
	if ( '' !== trim( $_180c_first_name ) ) {
		/* translators: %s : prénom du client. */
		printf( esc_html__( 'Bonjour %s,', '180c' ), esc_html( $_180c_first_name ) );
	} else {
		esc_html_e( 'Bonjour,', '180c' );
	}
	?>
</p>

<p style="margin:0 0 18px 0;">
	<?php
	printf(
		/* translators: %s : nom du site. */
		esc_html__( 'Le renouvellement de votre abonnement à %s n’a pas pu être finalisé : votre banque demande une validation de votre part (sécurité 3-D Secure). Pour éviter toute interruption de votre accès, validez le paiement en un clic. Sans validation, votre abonnement pourrait être suspendu.', '180c' ),
		esc_html( get_bloginfo( 'name' ) )
	);
	?>
</p>

<?php if ( ! empty( $authorization_url ) ) : ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:24px auto 0 auto;">
	<tr>
		<td align="center" bgcolor="#FFAE3A" style="border-radius:8px;">
			<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo esc_url( $authorization_url ); ?>" style="height:48px;v-text-anchor:middle;width:260px;" arcsize="17%" strokecolor="#FFAE3A" fillcolor="#FFAE3A"><w:anchorlock/><center style="color:#0E0E0E;font-family:'Oswald',Arial,sans-serif;font-size:15px;font-weight:600;"><?php echo esc_html__( 'Valider mon paiement', '180c' ); ?></center></v:roundrect><![endif]-->
			<!--[if !mso]><!-->
			<a class="btn-a" href="<?php echo esc_url( $authorization_url ); ?>" style="display:inline-block; background-color:#FFAE3A; color:#0E0E0E; font-family:'Oswald','Arial Narrow',Arial,Helvetica,sans-serif; font-size:15px; font-weight:600; text-decoration:none; padding:14px 30px; border-radius:8px;"><?php echo esc_html__( 'Valider mon paiement', '180c' ); ?></a>
			<!--<![endif]-->
		</td>
	</tr>
</table>
<?php endif; ?>

<?php $_180c_from = function_exists( '_180c_mail_from_address_value' ) ? _180c_mail_from_address_value() : ''; ?>
<p style="margin:24px 0 0 0;"><?php echo esc_html( '' !== $_180c_from ? sprintf( /* translators: %s: adresse e-mail de l'expéditeur. */ __( 'l’équipe 180°C — %s', '180c' ), $_180c_from ) : __( 'l’équipe 180°C', '180c' ) ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
