<?php
/**
 * Part — formulaire de connexion.
 *
 * Inclus dans inc/auth/views/login.php.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$ip             = _180c_get_client_ip();
$show_turnstile = _180c_should_show_turnstile( $ip );
$error          = _180c_get_auth_error();

// Message de succès de réinitialisation de mot de passe.
$password_reset_success = isset( $_GET['password_reset'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['password_reset'] ) );

// Redirect de destination (sécurisé).
$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/mon-compte/' );
$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/mon-compte/' ) );
?>

<div class="auth-form" role="main" aria-labelledby="login-title">

	<div class="auth-form__header">
		<h1 class="auth-form__title" id="login-title">
			<?php esc_html_e( 'Connexion', '180c' ); ?>
		</h1>
		<p class="auth-form__header-link">
			<?php esc_html_e( 'Pas encore abonné ?', '180c' ); ?>
			<a href="<?php echo esc_url( apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) ) ); ?>" class="auth-form__alt-link">
				<?php esc_html_e( 'Abonnez-vous', '180c' ); ?>
			</a>
		</p>
	</div>

	<?php if ( $password_reset_success ) : ?>
		<div class="auth-message auth-message--success" role="status" aria-live="polite">
			<?php esc_html_e( 'Mot de passe mis à jour. Vous pouvez vous connecter.', '180c' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<?php
		// Mesure Umami (login_error) : on expose le CODE générique de l'erreur
		// (credentials, empty, blocked, nonce, turnstile) et jamais l'identifiant
		// saisi. Lu au chargement par src/js/modules/umami-events.js.
		?>
		<div class="auth-message auth-message--error" role="alert" aria-live="polite"
			data-umami-login-error="<?php echo esc_attr( $error['code'] ); ?>">
			<?php echo esc_html( $error['message'] ); ?>
		</div>
	<?php endif; ?>

	<form
		method="post"
		action="<?php echo esc_url( home_url( '/connexion/' ) ); ?>"
		class="auth-form__body"
		novalidate
		data-auth-form="login"
	>
		<?php wp_nonce_field( '_180c_login', '_180c_login_nonce' ); ?>
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

		<div class="auth-form__field field <?php echo ( $error && in_array( $error['code'], array( 'credentials', 'empty' ), true ) ) ? 'field--error' : ''; ?>">
			<label class="label is-required" for="log">
				<?php esc_html_e( 'Adresse e-mail', '180c' ); ?>
			</label>
			<input
				type="text"
				id="log"
				name="log"
				class="input"
				autocomplete="username"
				autocapitalize="none"
				spellcheck="false"
				required
				aria-required="true"
				<?php echo ( $error && 'credentials' === $error['code'] ) ? 'aria-invalid="true"' : ''; ?>
			>
		</div>

		<div class="auth-form__field field <?php echo ( $error && in_array( $error['code'], array( 'credentials', 'empty' ), true ) ) ? 'field--error' : ''; ?>">
			<label class="label is-required" for="pwd">
				<?php esc_html_e( 'Mot de passe', '180c' ); ?>
			</label>
			<input
				type="password"
				id="pwd"
				name="pwd"
				class="input"
				autocomplete="current-password"
				required
				aria-required="true"
				<?php echo ( $error && 'credentials' === $error['code'] ) ? 'aria-invalid="true"' : ''; ?>
			>
			<?php if ( $error && in_array( $error['code'], array( 'credentials', 'empty', 'blocked' ), true ) ) : ?>
				<span class="field__error" role="alert">
					<?php echo esc_html( $error['message'] ); ?>
				</span>
			<?php endif; ?>
		</div>

		<div class="auth-form__field field">
			<label class="checkbox">
				<input
					type="checkbox"
					id="rememberme"
					name="rememberme"
					class="checkbox__input"
					value="forever"
				>
				<span class="checkbox__label">
					<?php esc_html_e( 'Se souvenir de moi', '180c' ); ?>
				</span>
			</label>
		</div>

		<?php if ( $show_turnstile ) : ?>
			<div class="turnstile-container" data-sitekey="<?php echo esc_attr( _180c_turnstile_site_key() ); ?>">
				<!-- Le widget Turnstile est injecté par auth.js -->
			</div>
		<?php endif; ?>

		<div class="auth-form__submit">
			<button type="submit" class="btn btn--primary auth-form__submit-btn">
				<?php esc_html_e( 'Se connecter', '180c' ); ?>
			</button>
		</div>

		<div class="auth-form__footer">
			<a
				href="<?php echo esc_url( home_url( '/mot-de-passe-oublie/' ) ); ?>"
				class="auth-form__alt-link"
			>
				<?php esc_html_e( 'Mot de passe oublié ?', '180c' ); ?>
			</a>
		</div>
	</form>
</div>
