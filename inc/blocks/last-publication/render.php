<?php
/**
 * Bloc 180c/last-publication — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$source            = isset( $attributes['source'] ) ? $attributes['source'] : 'auto';
$manual_product_id = isset( $attributes['manual_product_id'] ) ? (int) $attributes['manual_product_id'] : 0;
$headline_override = isset( $attributes['headline_override'] ) ? sanitize_text_field( $attributes['headline_override'] ) : '';
$cta_label         = isset( $attributes['cta_label'] ) && $attributes['cta_label'] ? sanitize_text_field( $attributes['cta_label'] ) : __( 'Acheter', '180c' );

$product = null;

if ( 'manual' === $source && $manual_product_id ) {
	if ( function_exists( 'wc_get_product' ) ) {
		$wc_product = wc_get_product( $manual_product_id );
		if ( $wc_product ) {
			$product = $wc_product;
		}
	}
} else {
	// Auto : dernier produit de la catégorie "livres".
	$query = new WP_Query(
		array(
			'post_type'      => 'product',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => 'livres',
				),
			),
			'no_found_rows'  => true,
		)
	);

	if ( $query->have_posts() && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $query->posts[0]->ID );
	}
	wp_reset_postdata();
}

if ( ! $product ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-last-publication block-180c-last-publication--placeholder"><p>' . esc_html__( 'Dernière publication : aucun produit trouvé dans la catégorie « livres ».', '180c' ) . '</p></div>';
	}
	return;
}

$title       = esc_html( $product->get_name() );
$headline    = $headline_override ? esc_html( $headline_override ) : $title;
$description = wp_kses_post( $product->get_description() ?: $product->get_short_description() );
$permalink   = esc_url( $product->get_permalink() );
$cart_url    = esc_url( $product->add_to_cart_url() );
$thumb_id    = $product->get_image_id();
$img_html    = '';

if ( $thumb_id ) {
	$img_html = wp_get_attachment_image(
		$thumb_id,
		'large',
		false,
		array(
			'class'    => 'block-180c-last-publication__cover',
			'loading'  => 'lazy',
			'decoding' => 'async',
		)
	);
}

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-last-publication' ) );
?>
<section <?php echo $wrapper_attrs; ?>>
	<?php if ( $img_html ) : ?>
		<div class="block-180c-last-publication__media">
			<a href="<?php echo $permalink; ?>" aria-hidden="true" tabindex="-1">
				<?php echo $img_html; ?>
			</a>
		</div>
	<?php endif; ?>
	<div class="block-180c-last-publication__content">
		<span class="block-180c-last-publication__eyebrow"><?php esc_html_e( 'Dernière publication', '180c' ); ?></span>
		<h2 class="block-180c-last-publication__title">
			<a href="<?php echo $permalink; ?>"><?php echo $headline; ?></a>
		</h2>
		<?php if ( $description ) : ?>
			<div class="block-180c-last-publication__description"><?php echo $description; ?></div>
		<?php endif; ?>
		<a class="btn btn--primary block-180c-last-publication__cta" href="<?php echo $cart_url; ?>">
			<?php echo esc_html( $cta_label ); ?>
		</a>
	</div>
</section>
