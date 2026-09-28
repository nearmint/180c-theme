<?php
/**
 * Email HTML — confirmation d'une demande de suppression de compte.
 *
 * Override thème de l'email custom `180c_account_deletion_confirm`.
 * Rendu par _180C_Email_Account_Deletion_Confirm::get_content_html().
 *
 * @package 180c
 *
 * @var string   $first_name         Prénom du titulaire, éventuellement vide.
 * @var string   $confirm_url        URL de confirmation portant le jeton.
 * @var string   $email_heading      Titre de l'email.
 * @var string   $additional_content Contenu additionnel (réglages).
 * @var WC_Email $email              Instance de l'email.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook du cœur de WooCommerce, émis par tout gabarit d'e-mail HTML.
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	if ( '' !== trim( $first_name ) ) {
		/* translators: %s: prénom du titulaire du compte. */
		printf( esc_html__( 'Bonjour %s,', '180c' ), esc_html( $first_name ) );
	} else {
		esc_html_e( 'Bonjour,', '180c' );
	}
	?>
</p>

<p><?php esc_html_e( 'Vous avez demandé à supprimer votre compte 180°C.', '180c' ); ?></p>

<p><?php esc_html_e( 'Pour confirmer, cliquez sur le bouton ci-dessous.', '180c' ); ?></p>

<p>
	<a href="<?php echo esc_url( $confirm_url ); ?>" style="display:inline-block;padding:12px 22px;background:#FFAE3A;color:#0E0E0E;text-decoration:none;border-radius:6px;font-weight:bold;">
		<?php esc_html_e( 'Confirmer la suppression', '180c' ); ?>
	</a>
</p>

<p><?php esc_html_e( 'Ce lien fonctionne pendant 24 heures. Passé ce délai, il faudra refaire une demande.', '180c' ); ?></p>

<p><?php esc_html_e( 'Une fois confirmée, la suppression est définitive.', '180c' ); ?></p>

<p><?php esc_html_e( 'Vous n’êtes pas à l’origine de cette demande ? Ignorez cet e-mail, votre compte restera intact. Par sécurité, changez votre mot de passe.', '180c' ); ?></p>

<p>
	<?php esc_html_e( 'À bientôt,', '180c' ); ?><br>
	<?php esc_html_e( 'L’équipe 180°C', '180c' ); ?>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook du cœur de WooCommerce, émis par tout gabarit d'e-mail HTML.
do_action( 'woocommerce_email_footer', $email );
