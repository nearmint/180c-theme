<?php
/**
 * Part — section suppression de compte RGPD.
 *
 * A inclure dans le template /mon-compte/profil/.
 * N'affiche rien si l'utilisateur n'est pas connecté.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	return;
}

$delete_requested = isset( $_GET['delete_requested'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['delete_requested'] ) );
$delete_error     = isset( $_GET['delete_error'] ) ? sanitize_key( wp_unslash( $_GET['delete_error'] ) ) : '';
?>

<section class="account-danger-zone" aria-labelledby="danger-zone-title">
	<h2 class="account-danger-zone__title" id="danger-zone-title">
		<?php esc_html_e( 'Zone de danger', '180c' ); ?>
	</h2>

	<?php if ( $delete_requested ) : ?>
		<div class="auth-message auth-message--info" role="status" aria-live="polite">
			<?php esc_html_e( 'Un e-mail de confirmation vous a été envoyé. Cliquez sur le lien pour confirmer la suppression de votre compte.', '180c' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( 'active_subscription' === $delete_error ) : ?>
		<div class="auth-message auth-message--error" role="alert">
			<?php esc_html_e( 'Vous avez un abonnement actif. Veuillez le résilier avant de supprimer votre compte.', '180c' ); ?>
			<a href="<?php echo esc_url( home_url( '/mon-compte/abonnement/' ) ); ?>" class="btn btn--secondary btn--sm">
				<?php esc_html_e( 'Gérer mon abonnement', '180c' ); ?>
			</a>
		</div>
	<?php elseif ( 'mail_failed' === $delete_error ) : ?>
		<div class="auth-message auth-message--error" role="alert">
			<?php esc_html_e( 'L\'envoi de l\'e-mail de confirmation a échoué. Veuillez réessayer.', '180c' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $delete_requested ) : ?>
		<p class="account-danger-zone__desc">
			<?php esc_html_e( 'La suppression de votre compte est définitive et irréversible. Toutes vos données personnelles, vos favoris et votre historique seront supprimés.', '180c' ); ?>
		</p>

		<form
			method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			class="account-danger-zone__form"
			data-confirm="<?php echo esc_attr__( 'Êtes-vous sûr de vouloir supprimer définitivement votre compte ? Cette action est irréversible.', '180c' ); ?>"
		>
			<input type="hidden" name="action" value="_180c_request_delete">
			<?php wp_nonce_field( '_180c_delete_account', '_180c_delete_nonce' ); ?>
			<button type="submit" class="btn btn--danger">
				<?php esc_html_e( 'Supprimer mon compte', '180c' ); ?>
			</button>
		</form>
	<?php endif; ?>
</section>
