<?php
/**
 * Bloc 180c/latest-issues — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$title = isset( $attributes['title'] ) && $attributes['title'] ? sanitize_text_field( $attributes['title'] ) : __( 'Les derniers numéros', '180c' );
$count = isset( $attributes['count'] ) ? max( 2, min( 12, (int) $attributes['count'] ) ) : 6;

$query = new WP_Query(
	array(
		'post_type'      => 'product',
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => 'numeros',
			),
		),
		'no_found_rows'  => true,
	)
);

if ( ! $query->have_posts() ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-latest-issues block-180c-latest-issues--placeholder"><p>' . esc_html__( 'Derniers numéros : aucun produit trouvé dans la catégorie « numéros ».', '180c' ) . '</p></div>';
	}
	wp_reset_postdata();
	return;
}

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-latest-issues' ) );
?>
<section <?php echo $wrapper_attrs; ?>>
	<h2 class="block-180c-latest-issues__title"><?php echo esc_html( $title ); ?></h2>
	<div class="block-180c-latest-issues__track" role="list" aria-label="<?php echo esc_attr( $title ); ?>">
		<?php
		while ( $query->have_posts() ) :
			$query->the_post();
			?>
			<?php
			$product_id = get_the_ID();
			$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			if ( ! $product ) {
				continue;
			}
			$item_url   = esc_url( $product->get_permalink() );
			$item_title = esc_html( $product->get_name() );
			$img_id     = $product->get_image_id();
			$img_html   = $img_id ? wp_get_attachment_image(
				$img_id,
				'medium',
				false,
				array(
					'class'    => 'block-180c-latest-issues__cover',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			) : '';

			// Numéro et date de publication via ACF.
			$pub_date = function_exists( 'get_field' ) ? get_field( 'publication_date', $product_id ) : '';
			?>
			<div class="block-180c-latest-issues__item" role="listitem">
				<a class="block-180c-latest-issues__item-link" href="<?php echo $item_url; ?>" aria-label="<?php echo $item_title; ?>">
					<?php if ( $img_html ) : ?>
						<div class="block-180c-latest-issues__item-media">
							<?php echo $img_html; ?>
						</div>
					<?php endif; ?>
					<div class="block-180c-latest-issues__item-body">
						<span class="block-180c-latest-issues__item-title"><?php echo $item_title; ?></span>
						<?php if ( $pub_date ) : ?>
							<span class="block-180c-latest-issues__item-date"><?php echo esc_html( $pub_date ); ?></span>
						<?php endif; ?>
					</div>
				</a>
			</div>
		<?php endwhile; ?>
	</div>
</section>
<?php
wp_reset_postdata();
