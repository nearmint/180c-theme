<?php
/**
 * Part — formulaire d'inscription.
 *
 * Inclus dans inc/auth/views/register.php.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$ip             = _180c_get_client_ip();
$show_turnstile = _180c_should_show_turnstile( $ip );
$error          = _180c_get_auth_error();
?>

<div class="auth-form" role="main" aria-labelledby="register-title">

	<div class="auth-form__header">
		<h1 class="auth-form__title" id="register-title">
			<?php esc_html_e( 'Créer un compte', '180c' ); ?>
		</h1>
		<p class="auth-form__header-link">
			<?php esc_html_e( 'Déjà inscrit ?', '180c' ); ?>
			<a href="<?php echo esc_url( home_url( '/connexion/' ) ); ?>" class="auth-form__alt-link">
				<?php esc_html_e( 'Se connecter', '180c' ); ?>
			</a>
		</p>
	</div>

	<?php if ( $error ) : ?>
		<div class="auth-message auth-message--error" role="alert" aria-live="polite">
			<?php echo esc_html( $error['message'] ); ?>
		</div>
	<?php endif; ?>

	<form
		method="post"
		action="<?php echo esc_url( home_url( '/inscription/' ) ); ?>"
		class="auth-form__body"
		novalidate
		data-auth-form="register"
	>
		<?php wp_nonce_field( '_180c_register', '_180c_register_nonce' ); ?>

		<div class="auth-form__row">
			<div class="auth-form__field field <?php echo ( $error && 'name' === $error['code'] ) ? 'field--error' : ''; ?>">
				<label class="label is-required" for="firstname">
					<?php esc_html_e( 'Prénom', '180c' ); ?>
				</label>
				<input
					type="text"
					id="firstname"
					name="firstname"
					class="input"
					autocomplete="given-name"
					required
					aria-required="true"
					<?php echo ( $error && 'name' === $error['code'] ) ? 'aria-invalid="true"' : ''; ?>
					value="<?php echo isset( $_POST['firstname'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['firstname'] ) ) ) : ''; ?>"
				>
			</div>

			<div class="auth-form__field field <?php echo ( $error && 'name' === $error['code'] ) ? 'field--error' : ''; ?>">
				<label class="label is-required" for="lastname">
					<?php esc_html_e( 'Nom', '180c' ); ?>
				</label>
				<input
					type="text"
					id="lastname"
					name="lastname"
					class="input"
					autocomplete="family-name"
					required
					aria-required="true"
					<?php echo ( $error && 'name' === $error['code'] ) ? 'aria-invalid="true"' : ''; ?>
					value="<?php echo isset( $_POST['lastname'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['lastname'] ) ) ) : ''; ?>"
				>
				<?php if ( $error && 'name' === $error['code'] ) : ?>
					<span class="field__error">
						<?php echo esc_html( $error['message'] ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<div class="auth-form__field field <?php echo ( $error && in_array( $error['code'], array( 'email_invalid', 'email_exists' ), true ) ) ? 'field--error' : ''; ?>">
			<label class="label is-required" for="email">
				<?php esc_html_e( 'Adresse e-mail', '180c' ); ?>
			</label>
			<input
				type="email"
				id="email"
				name="email"
				class="input"
				autocomplete="email"
				inputmode="email"
				required
				aria-required="true"
				<?php echo ( $error && in_array( $error['code'], array( 'email_invalid', 'email_exists' ), true ) ) ? 'aria-invalid="true"' : ''; ?>
				value="<?php echo isset( $_POST['email'] ) ? esc_attr( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : ''; ?>"
			>
			<?php if ( $error && in_array( $error['code'], array( 'email_invalid', 'email_exists' ), true ) ) : ?>
				<span class="field__error">
					<?php echo esc_html( $error['message'] ); ?>
				</span>
			<?php endif; ?>
		</div>

		<div class="auth-form__field field <?php echo ( $error && 'password_weak' === $error['code'] ) ? 'field--error' : ''; ?>">
			<label class="label is-required" for="password">
				<?php esc_html_e( 'Mot de passe', '180c' ); ?>
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
				<?php echo ( $error && 'password_weak' === $error['code'] ) ? 'aria-invalid="true"' : ''; ?>
			>
			<span class="field__helper" id="password-hint">
				<?php esc_html_e( 'Minimum 10 caractères.', '180c' ); ?>
			</span>
			<?php if ( $error && 'password_weak' === $error['code'] ) : ?>
				<span class="field__error">
					<?php echo esc_html( $error['message'] ); ?>
				</span>
			<?php endif; ?>
		</div>

		<div class="auth-form__field field">
			<label class="checkbox">
				<input
					type="checkbox"
					id="newsletter"
					name="newsletter"
					class="checkbox__input"
					value="1"
				>
				<span class="checkbox__label">
					<?php esc_html_e( 'Je m\'inscris à la newsletter gratuite de 180°C', '180c' ); ?>
				</span>
			</label>
		</div>

		<div class="auth-form__cgu field <?php echo ( $error && 'cgu' === $error['code'] ) ? 'field--error' : ''; ?>">
			<label class="checkbox">
				<input
					type="checkbox"
					id="cgu"
					name="cgu"
					class="checkbox__input"
					required
					aria-required="true"
					value="1"
					<?php echo ( $error && 'cgu' === $error['code'] ) ? 'aria-invalid="true"' : ''; ?>
				>
				<span class="checkbox__label">
					<?php
					printf(
						/* translators: %s: lien vers les CGU */
						esc_html__( 'J\'accepte les %s', '180c' ),
						'<a href="' . esc_url( home_url( '/cgv/' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Conditions Générales d\'Utilisation', '180c' ) . '</a>'
					);
					?>
				</span>
			</label>
			<?php if ( $error && 'cgu' === $error['code'] ) : ?>
				<span class="field__error">
					<?php echo esc_html( $error['message'] ); ?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( $show_turnstile ) : ?>
			<div class="turnstile-container" data-sitekey="<?php echo esc_attr( _180c_turnstile_site_key() ); ?>">
				<!-- Le widget Turnstile est injecté par auth.js -->
			</div>
		<?php endif; ?>

		<div class="auth-form__submit">
			<button type="submit" class="btn btn--primary auth-form__submit-btn">
				<?php esc_html_e( 'Créer mon compte', '180c' ); ?>
			</button>
		</div>
	</form>
</div>
