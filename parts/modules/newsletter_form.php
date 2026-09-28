<?php
/**
 * Template part — Module home : Formulaire newsletter.
 *
 * Sous-champs ACF :
 *   - title (text)
 *   - description (textarea)
 *   - image (image) — utilisée en fond plein module (overlay sombre) si fournie
 *   - submit_label (text)
 *   - signup_legal_text (wysiwyg) — mention rich-text éditable sous le bouton
 *
 * Soumet en AJAX vers POST /wp-json/180c/v1/newsletter/public-subscribe (REST
 * 180c) via le module unifié src/js/modules/newsletter.js (sélecteur
 * [data-newsletter-form]). Single opt-in idempotent, protégé par nonce + honeypot.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/*
 * Masqué pour les abonnés aux recettes : ce module promeut la newsletter gratuite,
 * sans intérêt pour un abonné déjà engagé. Filtrable via `180c/hide_newsletter_module`.
 */
if ( apply_filters( '180c/hide_newsletter_module', _180c_is_recipe_subscriber() ) ) {
	return;
}

$title        = get_sub_field( 'title' );
$description  = get_sub_field( 'description' );
$image        = get_sub_field( 'image' );
$submit_label = get_sub_field( 'submit_label' ) ?: __( "S'inscrire", '180c' );
$legal_text   = get_sub_field( 'signup_legal_text' );
if ( ! $legal_text ) {
	$legal_text = __( 'En vous inscrivant, vous acceptez de recevoir la newsletter de 180°C. Désinscription à tout moment via le lien présent dans chaque e-mail ; vos données ne sont jamais cédées à des tiers.', '180c' );
}

$has_image   = ! empty( $image );
$section_mod = $has_image ? ' home-module--newsletter-with-image' : '';

$email_id    = 'nl-home-email-' . wp_unique_id();
$feedback_id = 'nl-home-feedback-' . wp_unique_id();
?>

<section class="home-module home-module--newsletter_form<?php echo esc_attr( $section_mod ); ?>">
	<div class="container-180c">
		<div class="newsletter-180c<?php echo $has_image ? ' newsletter-180c--with-image' : ''; ?>">

			<?php if ( $has_image ) : ?>
				<div class="newsletter-180c__media" aria-hidden="true">
					<?php
					// Image décorative de fond, plein module (overlay géré en CSS).
					echo wp_get_attachment_image(
						(int) $image['ID'],
						'large',
						false,
						array(
							'class'    => 'newsletter-180c__image',
							'loading'  => 'lazy',
							'decoding' => 'async',
							'sizes'    => '100vw',
							'alt'      => '',
						)
					);
					?>
				</div>
			<?php endif; ?>

			<div class="newsletter-180c__content">
				<?php if ( $title ) : ?>
					<h2 class="newsletter-180c__title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( $description ) : ?>
					<p class="newsletter-180c__description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>

				<form
					class="newsletter-form newsletter-180c__form"
					data-newsletter-form
					data-newsletter-list="free"
					data-ga-location="home"
					aria-describedby="<?php echo esc_attr( $feedback_id ); ?>"
					novalidate
				>
					<div class="newsletter-form__group">
						<label for="<?php echo esc_attr( $email_id ); ?>" class="screen-reader-text">
							<?php esc_html_e( 'Votre adresse e-mail', '180c' ); ?>
						</label>
						<input
							type="email"
							id="<?php echo esc_attr( $email_id ); ?>"
							name="email"
							class="newsletter-form__input"
							autocomplete="email"
							inputmode="email"
							required
							aria-required="true"
							aria-describedby="<?php echo esc_attr( $feedback_id ); ?>"
							placeholder="<?php esc_attr_e( 'nom@exemple.fr', '180c' ); ?>"
						>
						<button type="submit" class="newsletter-form__submit" data-newsletter-submit aria-busy="false">
							<?php echo esc_html( $submit_label ); ?>
						</button>
					</div>

					<div class="newsletter-form__secondary">
						<?php echo wp_kses_post( $legal_text ); ?>
					</div>

					<?php // Honeypot : hors flux, jamais visible ni focalisable. ?>
					<div class="newsletter-form__gotcha" aria-hidden="true">
						<label for="<?php echo esc_attr( $email_id ); ?>-gotcha"><?php esc_html_e( 'Laissez ce champ vide', '180c' ); ?></label>
						<input id="<?php echo esc_attr( $email_id ); ?>-gotcha" name="_gotcha" type="text" tabindex="-1" autocomplete="off">
					</div>

					<?php
					// Champ `nl_nonce` volontairement VIDE : cette page est servie depuis le cache
					// statique de WP Super Cache, qui survit à l'expiration du nonce. Le JS le
					// remplit avec une valeur fraîche avant le POST (`fetchActionNonce()`,
					// src/js/modules/session.js). Le champ reste dans le DOM car c'est lui que
					// le JS cible ; sans JS il part vide, cas déjà prévu côté serveur
					// (inc/rest/newsletter.php : honeypot + rate-limit + validation).
					?>
					<input type="hidden" name="nl_nonce" value="">

					<p
						id="<?php echo esc_attr( $feedback_id ); ?>"
						class="newsletter-form__feedback"
						role="status"
						aria-live="polite"
						aria-atomic="true"
						hidden
					></p>
				</form>
			</div>
		</div>
	</div>
</section>
