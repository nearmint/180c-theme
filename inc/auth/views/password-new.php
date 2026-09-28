<?php
/**
 * Vue — page saisie du nouveau mot de passe.
 *
 * Chargée via template_include lorsque _180c_auth=reset-pwd.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main-content" class="auth-page">
	<div class="auth-page__inner">
		<?php
		// Validation du token en amont du rendu.
		$_180c_reset_key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$_180c_reset_login = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : '';

		if ( '' === $_180c_reset_key || '' === $_180c_reset_login ) {
			?>
			<div class="auth-form" role="main">
				<div class="auth-message auth-message--error" role="alert">
					<?php esc_html_e( 'Lien de réinitialisation invalide ou expiré.', '180c' ); ?>
					<a href="<?php echo esc_url( home_url( '/mot-de-passe-oublie/' ) ); ?>" class="auth-form__alt-link">
						<?php esc_html_e( 'Demander un nouveau lien', '180c' ); ?>
					</a>
				</div>
			</div>
			<?php
		} else {
			$_180c_reset_user = check_password_reset_key( $_180c_reset_key, $_180c_reset_login );
			if ( is_wp_error( $_180c_reset_user ) ) {
				?>
				<div class="auth-form" role="main">
					<div class="auth-message auth-message--error" role="alert">
						<?php esc_html_e( 'Ce lien est invalide ou a expiré. Veuillez en demander un nouveau.', '180c' ); ?>
						<a href="<?php echo esc_url( home_url( '/mot-de-passe-oublie/' ) ); ?>" class="auth-form__alt-link">
							<?php esc_html_e( 'Demander un nouveau lien', '180c' ); ?>
						</a>
					</div>
				</div>
				<?php
			} else {
				// Token valide : affichage du formulaire.
				$_180c_auth_error = _180c_get_auth_error();
				?>
				<div class="auth-form" role="main">
					<div class="auth-form__header">
						<h1 class="auth-form__title">
							<?php esc_html_e( 'Nouveau mot de passe', '180c' ); ?>
						</h1>
					</div>

					<?php if ( $_180c_auth_error ) : ?>
						<div class="auth-message auth-message--error" role="alert" aria-live="polite">
							<?php echo esc_html( $_180c_auth_error['message'] ); ?>
						</div>
					<?php endif; ?>

					<form
						method="post"
						action="<?php echo esc_url( home_url( '/reinitialiser-mot-de-passe/' ) ); ?>"
						class="auth-form__body"
						novalidate
						data-auth-form="reset"
					>
						<?php wp_nonce_field( '_180c_reset', '_180c_reset_nonce' ); ?>
						<input type="hidden" name="key" value="<?php echo esc_attr( $_180c_reset_key ); ?>">
						<input type="hidden" name="login" value="<?php echo esc_attr( $_180c_reset_login ); ?>">

						<div class="auth-form__field field <?php echo ( $_180c_auth_error && 'password_weak' === $_180c_auth_error['code'] ) ? 'field--error' : ''; ?>">
							<label class="label is-required" for="password">
								<?php esc_html_e( 'Nouveau mot de passe', '180c' ); ?>
							</label>
							<input
								type="password"
								id="password"
								name="password"
								class="input"
								autocomplete="new-password"
								minlength="10"
								required
								aria-required="true"
								aria-describedby="password-hint"
								<?php echo ( $_180c_auth_error && 'password_weak' === $_180c_auth_error['code'] ) ? 'aria-invalid="true"' : ''; ?>
							>
							<span class="field__helper" id="password-hint">
								<?php esc_html_e( 'Minimum 10 caractères.', '180c' ); ?>
							</span>
							<?php if ( $_180c_auth_error && 'password_weak' === $_180c_auth_error['code'] ) : ?>
								<span class="field__error">
									<?php echo esc_html( $_180c_auth_error['message'] ); ?>
								</span>
							<?php endif; ?>
						</div>

						<div class="auth-form__field field <?php echo ( $_180c_auth_error && 'password_mismatch' === $_180c_auth_error['code'] ) ? 'field--error' : ''; ?>">
							<label class="label is-required" for="password_confirm">
								<?php esc_html_e( 'Confirmer le mot de passe', '180c' ); ?>
							</label>
							<input
								type="password"
								id="password_confirm"
								name="password_confirm"
								class="input"
								autocomplete="new-password"
								required
								aria-required="true"
								<?php echo ( $_180c_auth_error && 'password_mismatch' === $_180c_auth_error['code'] ) ? 'aria-invalid="true"' : ''; ?>
							>
							<?php if ( $_180c_auth_error && 'password_mismatch' === $_180c_auth_error['code'] ) : ?>
								<span class="field__error">
									<?php echo esc_html( $_180c_auth_error['message'] ); ?>
								</span>
							<?php endif; ?>
						</div>

						<div class="auth-form__submit">
							<button type="submit" class="btn btn--primary auth-form__submit-btn">
								<?php esc_html_e( 'Enregistrer le nouveau mot de passe', '180c' ); ?>
							</button>
						</div>
					</form>
				</div>
				<?php
			}
		}
		?>
	</div>
</main>

<?php
get_footer();
