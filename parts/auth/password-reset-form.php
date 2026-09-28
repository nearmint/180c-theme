<?php
/**
 * Part — formulaire mot de passe oublié.
 *
 * Inclus dans inc/auth/views/password-reset.php.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$error  = _180c_get_auth_error();
$notice = _180c_get_password_notice();

// La demande vient d'être traitée : le handler redirige avec `envoye=1`
// (POST/Redirect/GET). L'URL fait foi même si le transient de notice a expiré,
// afin de ne jamais réafficher le formulaire pré-rempli d'un POST resoumis.
if ( ! $notice && isset( $_GET['envoye'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['envoye'] ) ) ) {
	$notice = array(
		'code'    => 'sent',
		'message' => __( 'Si cette adresse est associée à un compte, vous recevrez un e-mail avec un lien de réinitialisation.', '180c' ),
	);
}
?>

<div class="auth-form" role="main" aria-labelledby="forgot-title">

	<div class="auth-form__header">
		<h1 class="auth-form__title" id="forgot-title">
			<?php esc_html_e( 'Mot de passe oublié', '180c' ); ?>
		</h1>
		<p class="auth-form__header-desc">
			<?php esc_html_e( 'Saisissez votre adresse e-mail, nous vous enverrons un lien pour réinitialiser votre mot de passe.', '180c' ); ?>
		</p>
	</div>

	<?php if ( $notice ) : ?>
		<div class="auth-message auth-message--info" role="status" aria-live="polite">
			<?php echo esc_html( $notice['message'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="auth-message auth-message--error" role="alert" aria-live="polite">
			<?php echo esc_html( $error['message'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $notice ) : ?>
		<form
			method="post"
			action="<?php echo esc_url( home_url( '/mot-de-passe-oublie/' ) ); ?>"
			class="auth-form__body"
			novalidate
			data-auth-form="forgot"
		>
			<?php wp_nonce_field( '_180c_forgot', '_180c_forgot_nonce' ); ?>

			<div class="auth-form__field field <?php echo ( $error && 'email_invalid' === $error['code'] ) ? 'field--error' : ''; ?>">
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
					<?php echo ( $error && 'email_invalid' === $error['code'] ) ? 'aria-invalid="true"' : ''; ?>
				>
			</div>

			<div class="auth-form__submit">
				<button type="submit" class="btn btn--primary auth-form__submit-btn">
					<?php esc_html_e( 'Envoyer le lien de réinitialisation', '180c' ); ?>
				</button>
			</div>
		</form>
	<?php endif; ?>

	<div class="auth-form__footer">
		<a href="<?php echo esc_url( home_url( '/connexion/' ) ); ?>" class="auth-form__alt-link">
			<?php esc_html_e( 'Retour à la connexion', '180c' ); ?>
		</a>
	</div>
</div>
