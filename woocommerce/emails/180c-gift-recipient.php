<?php
/**
 * Email HTML — cadeau reçu (bénéficiaire).
 *
 * Override thème de l'email custom `180c_gift_recipient`.
 * Rendu par _180C_Email_Gift_Recipient::get_content_html().
 *
 * @package 180c
 *
 * @var string   $first_name         Prénom du bénéficiaire.
 * @var string   $message            Message personnel du donateur.
 * @var string   $set_password_url   Lien de définition de mot de passe (nouveau compte) ou vide.
 * @var string   $login_url          URL de connexion (compte existant).
 * @var string   $start_date         Date de début d'accès (FR).
 * @var string   $end_date           Date de fin d'accès (FR).
 * @var string   $email_heading      Titre de l'email.
 * @var string   $additional_content Contenu additionnel (réglages).
 * @var WC_Email $email              Instance de l'email.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	printf(
		/* translators: %s: prénom du bénéficiaire. */
		esc_html__( 'Bonjour %s,', '180c' ),
		esc_html( $first_name )
	);
	?>
</p>

<p><?php esc_html_e( 'Bonne nouvelle : on vous offre un abonnement de 12 mois aux recettes de 180°C — un accès illimité à toutes nos recettes en ligne.', '180c' ); ?></p>

<?php if ( '' !== trim( $message ) ) : ?>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;">
		<tr>
			<td style="padding:16px 20px;background:#FAF6EF;border-left:4px solid #FFAE3A;font-style:italic;">
				<?php echo wp_kses_post( nl2br( esc_html( $message ) ) ); ?>
			</td>
		</tr>
	</table>
<?php endif; ?>

<?php if ( '' !== $set_password_url ) : ?>
	<p><?php esc_html_e( 'Un compte a été créé pour vous. Définissez votre mot de passe pour accéder à vos recettes :', '180c' ); ?></p>
	<p>
		<a href="<?php echo esc_url( $set_password_url ); ?>" style="display:inline-block;padding:12px 22px;background:#FFAE3A;color:#0E0E0E;text-decoration:none;border-radius:6px;font-weight:bold;">
			<?php esc_html_e( 'Activer mon accès', '180c' ); ?>
		</a>
	</p>
<?php else : ?>
	<p><?php esc_html_e( 'Votre accès est déjà rattaché à votre compte. Connectez-vous pour en profiter :', '180c' ); ?></p>
	<p>
		<a href="<?php echo esc_url( $login_url ); ?>" style="display:inline-block;padding:12px 22px;background:#FFAE3A;color:#0E0E0E;text-decoration:none;border-radius:6px;font-weight:bold;">
			<?php esc_html_e( 'Me connecter', '180c' ); ?>
		</a>
	</p>
<?php endif; ?>

<?php if ( '' !== $end_date ) : ?>
	<p>
		<?php
		printf(
			/* translators: 1: date de début, 2: date de fin. */
			esc_html__( 'Votre accès est ouvert du %1$s au %2$s.', '180c' ),
			esc_html( $start_date ),
			esc_html( $end_date )
		);
		?>
	</p>
<?php endif; ?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
