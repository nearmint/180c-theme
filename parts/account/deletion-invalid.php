<?php
/**
 * Mon compte — écran S3' : lien de confirmation inutilisable.
 *
 * Jeton inconnu, expiré, déjà consommé, ou appartenant à un autre compte —
 * tous ces cas mènent ici, sans jamais distinguer lequel.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;
?>

<section class="c-account-deletion">

	<p class="c-account-deletion__intro">
		<?php esc_html_e( 'Ce lien ne fonctionne plus. Il a expiré ou a déjà été utilisé.', '180c' ); ?>
	</p>

	<p class="c-account-deletion__actions">
		<a
			class="btn btn--secondary"
			href="<?php echo esc_url( wc_get_account_endpoint_url( _180C_ACCOUNT_DELETION_ENDPOINT ) ); ?>"
		>
			<?php esc_html_e( 'Refaire une demande', '180c' ); ?>
		</a>
	</p>

</section>
