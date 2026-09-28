<?php
/**
 * Bloc 180c/complete-collection — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$title                    = isset( $attributes['title'] ) && $attributes['title'] ? sanitize_text_field( $attributes['title'] ) : __( 'La collection complète', '180c' );
$description              = isset( $attributes['description'] ) ? sanitize_text_field( $attributes['description'] ) : '';
$show_purchased_indicator = isset( $attributes['show_purchased_indicator'] ) ? (bool) $attributes['show_purchased_indicator'] : true;

// Récupération de tous les numéros.
$query = new WP_Query(
	array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
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
		echo '<div class="block-180c-complete-collection block-180c-complete-collection--placeholder"><p>' . esc_html__( 'Collection complète : aucun numéro trouvé.', '180c' ) . '</p></div>';
	}
	wp_reset_postdata();
	return;
}

// Cross-reference des commandes si l'utilisateur est connecté et l'indicateur activé.
$purchased_ids = array();
if ( $show_purchased_indicator && is_user_logged_in() && function_exists( 'wc_get_orders' ) ) {
	$user_id   = get_current_user_id();
	$order_ids = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'status'      => array( 'completed', 'processing' ),
			'limit'       => -1,
			'return'      => 'ids',
		)
	);

	foreach ( $order_ids as $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			continue;
		}
		foreach ( $order->get_items() as $item ) {
			$purchased_ids[] = (int) $item->get_product_id();
		}
	}
	$purchased_ids = array_unique( $purchased_ids );
}

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-complete-collection' ) );
?>
<section <?php echo $wrapper_attrs; ?>>
	<div class="block-180c-complete-collection__header">
		<h2 class="block-180c-complete-collection__title"><?php echo esc_html( $title ); ?></h2>
		<?php if ( $description ) : ?>
			<p class="block-180c-complete-collection__description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
	</div>
	<div class="block-180c-complete-collection__grid">
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
					'class'    => 'block-180c-complete-collection__cover',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			) : '';

			$is_purchased = in_array( $product_id, $purchased_ids, true );
			$badge_label  = $is_purchased ? __( 'Déjà acheté', '180c' ) : __( 'Acheter', '180c' );
			$badge_class  = $is_purchased ? 'block-180c-complete-collection__badge--owned' : 'block-180c-complete-collection__badge--buy';
			$pub_date     = function_exists( 'get_field' ) ? get_field( 'publication_date', $product_id ) : '';
			?>
			<div class="block-180c-complete-collection__item">
				<a class="block-180c-complete-collection__item-link" href="<?php echo $item_url; ?>" aria-label="<?php echo $item_title; ?>">
					<?php if ( $img_html ) : ?>
						<div class="block-180c-complete-collection__item-media">
							<?php echo $img_html; ?>
							<?php if ( $show_purchased_indicator ) : ?>
								<span class="block-180c-complete-collection__badge <?php echo esc_attr( $badge_class ); ?>">
									<?php echo esc_html( $badge_label ); ?>
								</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<div class="block-180c-complete-collection__item-body">
						<span class="block-180c-complete-collection__item-title"><?php echo $item_title; ?></span>
						<?php if ( $pub_date ) : ?>
							<span class="block-180c-complete-collection__item-date"><?php echo esc_html( $pub_date ); ?></span>
						<?php endif; ?>
					</div>
				</a>
			</div>
		<?php endwhile; ?>
	</div>
</section>
<?php
wp_reset_postdata();
