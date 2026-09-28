<?php
/**
 * Bloc 180c/app-promo — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 * Caché si cookie dismissed_app_banner=1.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// APP-RELEASE : le bloc de promotion des applications ne rend rien tant que les
// apps mobiles ne sont pas publiées. Retirer ce return pour réactiver le rendu.
return;

// Masquage server-side si l'utilisateur a dismissé la bannière.
$dismissed = isset( $_COOKIE['dismissed_app_banner'] ) && '1' === $_COOKIE['dismissed_app_banner'];
if ( $dismissed ) {
	return;
}

$title       = isset( $attributes['title'] ) && $attributes['title'] ? sanitize_text_field( $attributes['title'] ) : __( 'Cuisinez avec l\'app 180°C', '180c' );
$description = isset( $attributes['description'] ) ? sanitize_text_field( $attributes['description'] ) : '';
$features    = isset( $attributes['features'] ) && is_array( $attributes['features'] ) ? $attributes['features'] : array();
$ios_url     = isset( $attributes['ios_url'] ) ? esc_url( $attributes['ios_url'] ) : '';
$android_url = isset( $attributes['android_url'] ) ? esc_url( $attributes['android_url'] ) : '';
$image_url   = isset( $attributes['image_url'] ) ? esc_url( $attributes['image_url'] ) : '';

// Limiter à 3 features max.
$features = array_slice( array_filter( $features ), 0, 3 );

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-app-promo' ) );
?>
<section <?php echo $wrapper_attrs; ?>>
	<?php if ( $image_url ) : ?>
		<div class="block-180c-app-promo__media">
			<img
				src="<?php echo $image_url; ?>"
				alt="<?php esc_attr_e( 'Application 180°C', '180c' ); ?>"
				class="block-180c-app-promo__image"
				loading="lazy"
				decoding="async"
				width="400"
				height="600"
			>
		</div>
	<?php endif; ?>
	<div class="block-180c-app-promo__content">
		<h2 class="block-180c-app-promo__title"><?php echo esc_html( $title ); ?></h2>
		<?php if ( $description ) : ?>
			<p class="block-180c-app-promo__description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $features ) ) : ?>
			<ul class="block-180c-app-promo__features" role="list">
				<?php foreach ( $features as $feature ) : ?>
					<li class="block-180c-app-promo__feature">
						<svg class="block-180c-app-promo__feature-icon" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="20 6 9 17 4 12"/>
						</svg>
						<span><?php echo esc_html( $feature ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<div class="block-180c-app-promo__ctas">
			<?php if ( $ios_url ) : ?>
				<a
					class="block-180c-app-promo__store-btn block-180c-app-promo__store-btn--ios"
					href="<?php echo $ios_url; ?>"
					target="_blank"
					rel="noopener noreferrer"
					data-source="home-module"
					data-store="ios"
					aria-label="<?php esc_attr_e( 'Télécharger sur l\'App Store', '180c' ); ?>"
				>
					<svg class="block-180c-app-promo__store-icon" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
					<span class="block-180c-app-promo__store-label">
						<small><?php esc_html_e( 'Disponible sur', '180c' ); ?></small>
						<strong><?php esc_html_e( 'App Store', '180c' ); ?></strong>
					</span>
				</a>
			<?php endif; ?>
			<?php if ( $android_url ) : ?>
				<a
					class="block-180c-app-promo__store-btn block-180c-app-promo__store-btn--android"
					href="<?php echo $android_url; ?>"
					target="_blank"
					rel="noopener noreferrer"
					data-source="home-module"
					data-store="android"
					aria-label="<?php esc_attr_e( 'Télécharger sur Google Play', '180c' ); ?>"
				>
					<svg class="block-180c-app-promo__store-icon" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="m3 20.5v-17c0-.83 1-.83 1.5-.5l17 8.5-17 8.5c-.5.33-1.5.33-1.5-.5z" opacity=".3"/><path d="M3 20.5V3.5c0-.83 1-.83 1.5-.5L21.5 12 4.5 21c-.5.33-1.5.33-1.5-.5zm2-1.86V5.36L18.04 12 5 18.64z"/></svg>
					<span class="block-180c-app-promo__store-label">
						<small><?php esc_html_e( 'Disponible sur', '180c' ); ?></small>
						<strong><?php esc_html_e( 'Google Play', '180c' ); ?></strong>
					</span>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>
