<?php
/**
 * Bloc 180c/subscription-banner — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$hide_for_subscribers = isset( $attributes['hide_for_subscribers'] ) ? (bool) $attributes['hide_for_subscribers'] : true;

// Masquer pour les abonnés actifs.
if ( $hide_for_subscribers && function_exists( '_180c_is_recipe_subscriber' ) && _180c_is_recipe_subscriber() ) {
	return;
}

$title     = isset( $attributes['title'] ) && $attributes['title'] ? sanitize_text_field( $attributes['title'] ) : __( 'Accédez à toutes les recettes', '180c' );
$desc      = isset( $attributes['description'] ) ? sanitize_text_field( $attributes['description'] ) : '';
$cta_label = isset( $attributes['cta_label'] ) && $attributes['cta_label'] ? sanitize_text_field( $attributes['cta_label'] ) : __( 'Je m\'abonne', '180c' );
$image_url = isset( $attributes['image_url'] ) ? esc_url( $attributes['image_url'] ) : '';
$has_image = ! empty( $image_url );

// URL de l'abonnement : page produit unique ou page dédiée.
$sub_url = esc_url( apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) ) );

$block_class   = $has_image ? 'block-180c-subscription-banner block-180c-subscription-banner--with-image' : 'block-180c-subscription-banner';
$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => $block_class ) );
?>
<section <?php echo $wrapper_attrs; ?> aria-label="<?php esc_attr_e( 'Offre d\'abonnement', '180c' ); ?>">
	<?php if ( $has_image ) : ?>
		<div class="block-180c-subscription-banner__media">
			<img
				src="<?php echo $image_url; ?>"
				alt=""
				role="presentation"
				class="block-180c-subscription-banner__image"
				loading="lazy"
				decoding="async"
				width="800"
				height="600"
			>
		</div>
	<?php endif; ?>
	<div class="block-180c-subscription-banner__content">
		<h2 class="block-180c-subscription-banner__title"><?php echo esc_html( $title ); ?></h2>
		<?php if ( $desc ) : ?>
			<p class="block-180c-subscription-banner__description"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>
		<a class="btn btn--primary block-180c-subscription-banner__cta" href="<?php echo $sub_url; ?>">
			<?php echo esc_html( $cta_label ); ?>
		</a>
	</div>
</section>
