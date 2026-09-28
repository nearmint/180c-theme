<?php
/**
 * Bloc 180c/newsletter-form — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$title        = isset( $attributes['title'] ) && $attributes['title'] ? sanitize_text_field( $attributes['title'] ) : __( 'Restez aux fourneaux', '180c' );
$description  = isset( $attributes['description'] ) ? sanitize_text_field( $attributes['description'] ) : '';
$image_url    = isset( $attributes['image_url'] ) ? esc_url( $attributes['image_url'] ) : '';
$submit_label = isset( $attributes['submit_label'] ) && $attributes['submit_label'] ? sanitize_text_field( $attributes['submit_label'] ) : __( 'S\'inscrire', '180c' );

$has_image   = ! empty( $image_url );
$block_class = $has_image ? 'block-180c-newsletter-form block-180c-newsletter-form--with-image' : 'block-180c-newsletter-form';

$form_id   = 'nl-form-' . wp_unique_id();
$region_id = 'nl-feedback-' . wp_unique_id();
$email_id  = 'nl-email-' . wp_unique_id();

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => $block_class ) );
?>
<section <?php echo $wrapper_attrs; ?>>
	<?php if ( $has_image ) : ?>
		<div class="block-180c-newsletter-form__media">
			<img
				src="<?php echo $image_url; ?>"
				alt=""
				role="presentation"
				class="block-180c-newsletter-form__image"
				loading="lazy"
				decoding="async"
				width="600"
				height="400"
			>
		</div>
	<?php endif; ?>
	<div class="block-180c-newsletter-form__content">
		<h2 class="block-180c-newsletter-form__title"><?php echo esc_html( $title ); ?></h2>
		<?php if ( $description ) : ?>
			<p class="block-180c-newsletter-form__description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<form
			id="<?php echo esc_attr( $form_id ); ?>"
			class="block-180c-newsletter-form__form"
			novalidate
			data-newsletter-form
			data-newsletter-list="free"
			data-ga-location="block"
			aria-describedby="<?php echo esc_attr( $region_id ); ?>"
		>
			<div class="block-180c-newsletter-form__field">
				<label class="block-180c-newsletter-form__label" for="<?php echo esc_attr( $email_id ); ?>">
					<?php esc_html_e( 'Adresse e-mail', '180c' ); ?>
				</label>
				<div class="block-180c-newsletter-form__input-group">
					<input
						id="<?php echo esc_attr( $email_id ); ?>"
						class="block-180c-newsletter-form__input"
						type="email"
						name="email"
						autocomplete="email"
						required
						aria-required="true"
						placeholder="<?php esc_attr_e( 'vous@example.com', '180c' ); ?>"
					>
					<button class="btn btn--primary block-180c-newsletter-form__submit" type="submit" data-newsletter-submit aria-busy="false">
						<?php echo esc_html( $submit_label ); ?>
					</button>
				</div>
			</div>
			<p class="block-180c-newsletter-form__consent">
				<?php esc_html_e( 'En vous inscrivant, vous acceptez de recevoir la newsletter de 180°C. Désinscription à tout moment ; vos données ne sont jamais cédées à des tiers.', '180c' ); ?>
			</p>

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
				id="<?php echo esc_attr( $region_id ); ?>"
				class="newsletter-form__feedback"
				role="status"
				aria-live="polite"
				aria-atomic="true"
				hidden
			></p>
		</form>
	</div>
</section>
