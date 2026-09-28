<?php
/**
 * Mon compte — écran S2 : demande envoyée, en attente de confirmation.
 *
 * @package 180c
 *
 * @var array $args {
 *     @type string $email  Adresse à laquelle l'e-mail a été envoyé.
 *     @type string $notice Notice à afficher (renvoi, délai), ou chaîne vide.
 * }
 */

defined( 'ABSPATH' ) || exit;

$sent_email  = isset( $args['email'] ) ? (string) $args['email'] : '';
$sent_notice = isset( $args['notice'] ) ? (string) $args['notice'] : '';
?>

<section class="c-account-deletion">

	<?php if ( '' !== $sent_notice ) : ?>
		<p class="c-account-deletion__notice" role="status" aria-live="polite">
			<?php echo esc_html( $sent_notice ); ?>
		</p>
	<?php endif; ?>

	<h2 class="c-account-deletion__title">
		<?php
		printf(
			/* translators: %s: adresse e-mail du compte. */
			esc_html__( 'Nous vous avons envoyé un e-mail à %s.', '180c' ),
			esc_html( $sent_email )
		);
		?>
	</h2>

	<ul class="c-account-deletion__list">
		<li class="c-account-deletion__item">
			<?php esc_html_e( 'Ouvrez-le et cliquez sur le bouton pour confirmer. Ce lien est valable 24 heures.', '180c' ); ?>
		</li>
		<li class="c-account-deletion__item">
			<?php esc_html_e( 'Rien reçu ? Regardez dans vos courriers indésirables.', '180c' ); ?>
		</li>
	</ul>

	<div class="c-account-deletion__actions">

		<form method="post" action="">
			<input type="hidden" name="_180c_acctdel_action" value="resend">
			<?php wp_nonce_field( '_180c_acctdel_resend', '_180c_acctdel_nonce' ); ?>
			<button type="submit" class="btn btn--secondary">
				<?php esc_html_e( 'Renvoyer l\'e-mail', '180c' ); ?>
			</button>
		</form>

	</div>

</section>
