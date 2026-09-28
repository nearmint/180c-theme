<?php
/**
 * Override thème — « Nouveau compte » client (#13), version FR brandée.
 *
 * Surcharge `woocommerce/templates/emails/customer-new-account.php`. Le châssis
 * 180c (header/footer/styles + surtitre COMPTE) est conservé.
 *
 * Audience réelle : cet e-mail n'est envoyé qu'aux comptes créés SANS que la
 * cliente choisisse son mot de passe (voir le filtre
 * `woocommerce_email_enabled_customer_new_account` dans inc/woo/emails.php).
 * En pratique : checkout Apple Pay / Google Pay (Stripe Express) et autres flux
 * « passwordless ». La copie est donc centrée sur la définition du mot de passe
 * et reste neutre sur la nature de l'achat (abonnement OU produit physique),
 * l'Express Checkout étant disponible sur tout le catalogue.
 *
 * @var string   $user_login         Identifiant de connexion.
 * @var string   $blogname           Nom du site.
 * @var bool     $password_generated Mot de passe généré (lien à envoyer) ?
 * @var string   $set_password_url   Lien de définition du mot de passe.
 * @var string   $email_heading      Titre.
 * @var string   $additional_content Contenu additionnel (réglages).
 * @var WC_Email $email              Instance de l'email.
 *
 * @package 180c
 * @version 10.9.0
 */

defined( 'ABSPATH' ) || exit;

$_180c_user      = get_user_by( 'login', $user_login );
$_180c_first     = $_180c_user && '' !== $_180c_user->first_name ? $_180c_user->first_name : $user_login;
$_180c_my_acc    = wc_get_page_permalink( 'myaccount' );
$_180c_contact   = home_url( '/contact/' );
$_180c_btn_style = "display:inline-block; background-color:#FFAE3A; color:#0E0E0E; font-family:'Oswald','Arial Narrow',Arial,Helvetica,sans-serif; font-size:15px; font-weight:600; text-decoration:none; padding:14px 30px; border-radius:8px;";

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p style="margin:0 0 18px 0;">
	<?php
	/* translators: %s : prénom (ou identifiant) du client. */
	printf( esc_html__( 'Bonjour %s,', '180c' ), esc_html( $_180c_first ) );
	?>
</p>

<p style="margin:0 0 18px 0;">
	<?php esc_html_e( 'Bienvenue chez 180°C ! Un compte a été créé pour vous lors de votre commande : vous y retrouverez vos achats, vos éventuels abonnements et vos recettes, réunis au même endroit.', '180c' ); ?>
</p>

<?php if ( ! empty( $password_generated ) && ! empty( $set_password_url ) ) : ?>
	<p style="margin:0 0 18px 0;"><?php esc_html_e( 'Il ne reste qu’une étape : définissez votre mot de passe pour activer votre accès.', '180c' ); ?></p>
	<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:8px auto 20px auto;">
		<tr><td align="center" bgcolor="#FFAE3A" style="border-radius:8px;">
			<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo esc_url( $set_password_url ); ?>" style="height:48px;v-text-anchor:middle;width:260px;" arcsize="17%" strokecolor="#FFAE3A" fillcolor="#FFAE3A"><w:anchorlock/><center style="color:#0E0E0E;font-family:'Oswald',Arial,sans-serif;font-size:15px;font-weight:600;"><?php echo esc_html__( 'Définir mon mot de passe', '180c' ); ?></center></v:roundrect><![endif]-->
			<!--[if !mso]><!--><a class="btn-a" href="<?php echo esc_url( $set_password_url ); ?>" style="<?php echo esc_attr( $_180c_btn_style ); ?>"><?php echo esc_html__( 'Définir mon mot de passe', '180c' ); ?></a><!--<![endif]-->
		</td></tr>
	</table>
	<p style="margin:0 0 18px 0; font-size:13px; color:#6E6E6E;"><?php esc_html_e( 'Ce lien est valable 24 heures. Vous vous connecterez ensuite avec votre adresse e-mail.', '180c' ); ?></p>
<?php else : ?>
	<p style="margin:0 0 18px 0;">
		<?php
		printf(
			/* translators: %s : lien vers l'espace client. */
			wp_kses( __( 'Connectez-vous dès maintenant à votre <a href="%s">espace client</a> avec votre adresse e-mail.', '180c' ), array( 'a' => array( 'href' => array() ) ) ),
			esc_url( $_180c_my_acc )
		);
		?>
	</p>
<?php endif; ?>

<p style="margin:18px 0 0 0;">
	<?php
	printf(
		/* translators: %s : lien vers l'espace client. */
		wp_kses( __( 'Une fois connecté·e, retrouvez vos commandes, vos abonnements et vos recettes dans votre <a href="%s">espace client</a>.', '180c' ), array( 'a' => array( 'href' => array() ) ) ),
		esc_url( $_180c_my_acc )
	);
	?>
</p>

<p style="margin:18px 0 0 0;">
	<?php
	printf(
		/* translators: %s : lien vers la page de contact. */
		wp_kses( __( 'Une question ? Notre équipe vous répond : <a href="%s">Nous contacter</a>.', '180c' ), array( 'a' => array( 'href' => array() ) ) ),
		esc_url( $_180c_contact )
	);
	?>
</p>

<p style="margin:18px 0 0 0;">
	<?php esc_html_e( 'À très vite,', '180c' ); ?><br>
	<?php esc_html_e( 'L’équipe 180°C', '180c' ); ?>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
