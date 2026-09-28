<?php
/**
 * Mon compte — écran S3 : confirmation finale.
 *
 * Dernier écran avant l'exécution du pipeline. Le jeton est réémis en champ
 * caché : le POST « execute » le revérifie, comme le lien de l'e-mail.
 *
 * @package 180c
 *
 * @var array $args {
 *     @type string $token Jeton de confirmation validé.
 * }
 */

defined( 'ABSPATH' ) || exit;

$confirm_token = isset( $args['token'] ) ? (string) $args['token'] : '';
?>

<section class="c-account-deletion">

	<h2 class="c-account-deletion__title">
		<?php esc_html_e( 'Dernière étape', '180c' ); ?>
	</h2>

	<ul class="c-account-deletion__list">
		<li class="c-account-deletion__item c-account-deletion__item--strong">
			<?php esc_html_e( 'Confirmez la suppression de votre compte 180°C. Cette action est définitive.', '180c' ); ?>
		</li>
		<li class="c-account-deletion__item">
			<?php esc_html_e( 'Nous effaçons vos informations personnelles et vos favoris. Nous gardons vos anciennes commandes et vos factures.', '180c' ); ?>
		</li>
	</ul>

	<div class="c-account-deletion__actions">

		<form method="post" action="">
			<input type="hidden" name="_180c_acctdel_action" value="execute">
			<input type="hidden" name="acctdel_token" value="<?php echo esc_attr( $confirm_token ); ?>">
			<?php wp_nonce_field( '_180c_acctdel_execute', '_180c_acctdel_nonce' ); ?>
			<button type="submit" class="btn btn--danger">
				<?php esc_html_e( 'Supprimer définitivement mon compte', '180c' ); ?>
			</button>
		</form>

		<form method="post" action="">
			<input type="hidden" name="_180c_acctdel_action" value="cancel">
			<?php wp_nonce_field( '_180c_acctdel_cancel', '_180c_acctdel_nonce' ); ?>
			<button type="submit" class="btn btn--link">
				<?php esc_html_e( 'Garder mon compte', '180c' ); ?>
			</button>
		</form>

	</div>

</section>
