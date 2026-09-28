<?php
/**
 * Email HTML — compte supprimé.
 *
 * Override thème de l'email custom `180c_account_deletion_done`.
 * Rendu par _180C_Email_Account_Deletion_Done::get_content_html().
 *
 * @package 180c
 *
 * @var string   $first_name         Prénom, éventuellement vide.
 * @var string   $deleted_on         Date de suppression (format FR).
 * @var bool     $newsletter_removed Le contact Mailchimp a-t-il été supprimé.
 * @var string   $contact_email      Adresse de contact.
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

<p>
	<?php
	printf(
		/* translators: %s: date de suppression (ex. « 4 septembre 2026 »). */
		esc_html__( 'Nous avons supprimé votre compte 180°C le %s.', '180c' ),
		esc_html( $deleted_on )
	);
	?>
</p>

<p>
	<?php esc_html_e( 'Nous avons effacé vos informations personnelles et vos favoris.', '180c' ); ?>
	<?php if ( $newsletter_removed ) : ?>
		<br><?php esc_html_e( 'Vous ne recevrez plus nos newsletters.', '180c' ); ?>
	<?php endif; ?>
</p>

<p><?php esc_html_e( 'Nous gardons vos anciennes commandes et vos factures. La loi nous y oblige. Elles ne sont plus rattachées à un compte.', '180c' ); ?></p>

<p><?php esc_html_e( 'Vous pouvez créer un nouveau compte à tout moment, avec la même adresse e-mail.', '180c' ); ?></p>

<?php if ( '' !== $contact_email ) : ?>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: lien mailto vers l'adresse de contact. */
				__( 'Une question ? Écrivez-nous à %s.', '180c' ),
				'<a href="mailto:' . esc_attr( $contact_email ) . '">' . esc_html( $contact_email ) . '</a>'
			),
			array( 'a' => array( 'href' => array() ) )
		);
		?>
	</p>
<?php endif; ?>

<p>
	<?php esc_html_e( 'Merci pour votre confiance,', '180c' ); ?><br>
	<?php esc_html_e( 'L’équipe 180°C', '180c' ); ?>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook du cœur de WooCommerce, émis par tout gabarit d'e-mail HTML.
do_action( 'woocommerce_email_footer', $email );
