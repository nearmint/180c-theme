<?php
/**
 * Override thème — « Réinitialisation de mot de passe » client (#12), FR brandé.
 *
 * Surcharge `woocommerce/templates/emails/customer-reset-password.php`. La
 * logique du lien de réinitialisation est reprise VERBATIM du template WC (ne
 * pas modifier : sécurité). Châssis 180c + surtitre COMPTE conservés.
 *
 * @var string   $user_login         Identifiant de connexion.
 * @var int      $user_id            ID utilisateur.
 * @var string   $reset_key          Clé de réinitialisation.
 * @var string   $blogname           Nom du site.
 * @var string   $email_heading      Titre.
 * @var string   $additional_content Contenu additionnel (réglages).
 * @var WC_Email $email              Instance de l'email.
 *
 * @package 180c
 * @version 10.9.0
 */

defined( 'ABSPATH' ) || exit;

$_180c_user  = get_user_by( 'login', $user_login );
$_180c_first = $_180c_user && '' !== $_180c_user->first_name ? $_180c_user->first_name : $user_login;

// Lien de réinitialisation — base VERBATIM du template WooCommerce (clé + login
// conservés : sécurité). Rendu filtrable pour pouvoir viser la page brandée
// custom /reinitialiser-mot-de-passe/ au lieu de l'endpoint natif Mon Compte
// (voir _180c_reset_password_email / filtre `180c/reset_password_email_url`).
$_180c_reset_url = apply_filters(
	'180c/reset_password_email_url',
	add_query_arg(
		array(
			'key'   => $reset_key,
			'id'    => $user_id,
			'login' => rawurlencode( $user_login ),
		),
		wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
	),
	$reset_key,
	$user_id,
	$user_login
);

$_180c_btn_style = "display:inline-block; background-color:#FFAE3A; color:#0E0E0E; font-family:'Oswald','Arial Narrow',Arial,Helvetica,sans-serif; font-size:15px; font-weight:600; text-decoration:none; padding:14px 30px; border-radius:8px;";

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p style="margin:0 0 18px 0;">
	<?php
	/* translators: %s : prénom (ou identifiant) du client. */
	printf( esc_html__( 'Bonjour %s,', '180c' ), esc_html( $_180c_first ) );
	?>
</p>

<p style="margin:0 0 18px 0;"><?php esc_html_e( 'Quelqu’un a demandé une réinitialisation de mot de passe pour votre compte 180°C. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.', '180c' ); ?></p>

<p style="margin:0 0 8px 0;"><?php esc_html_e( 'Sinon, créez un nouveau mot de passe :', '180c' ); ?></p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:8px auto 0 auto;">
	<tr><td align="center" bgcolor="#FFAE3A" style="border-radius:8px;">
		<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo esc_url( $_180c_reset_url ); ?>" style="height:48px;v-text-anchor:middle;width:280px;" arcsize="17%" strokecolor="#FFAE3A" fillcolor="#FFAE3A"><w:anchorlock/><center style="color:#0E0E0E;font-family:'Oswald',Arial,sans-serif;font-size:15px;font-weight:600;"><?php echo esc_html__( 'Réinitialiser mon mot de passe', '180c' ); ?></center></v:roundrect><![endif]-->
		<!--[if !mso]><!--><a class="btn-a" href="<?php echo esc_url( $_180c_reset_url ); ?>" style="<?php echo esc_attr( $_180c_btn_style ); ?>"><?php echo esc_html__( 'Réinitialiser mon mot de passe', '180c' ); ?></a><!--<![endif]-->
	</td></tr>
</table>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
