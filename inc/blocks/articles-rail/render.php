<?php
/**
 * Bloc 180c/articles-rail — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/_helpers.php';

$title           = isset( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : '';
$mode            = isset( $attributes['mode'] ) ? $attributes['mode'] : 'recent';
$category_id     = isset( $attributes['category_id'] ) ? (int) $attributes['category_id'] : 0;
$tag_id          = isset( $attributes['tag_id'] ) ? (int) $attributes['tag_id'] : 0;
$count           = isset( $attributes['count'] ) ? max( 1, min( 20, (int) $attributes['count'] ) ) : 8;
$manual_post_ids = isset( $attributes['manual_post_ids'] ) && is_array( $attributes['manual_post_ids'] ) ? array_map( 'intval', $attributes['manual_post_ids'] ) : array();

$query_args = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => $count,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
);

switch ( $mode ) {
	case 'category':
		if ( $category_id ) {
			$query_args['cat'] = $category_id;
		}
		break;

	case 'tag':
		if ( $tag_id ) {
			$query_args['tag__in'] = array( $tag_id );
		}
		break;

	case 'manual':
		if ( ! empty( $manual_post_ids ) ) {
			$query_args['post__in']       = $manual_post_ids;
			$query_args['orderby']        = 'post__in';
			$query_args['posts_per_page'] = count( $manual_post_ids );
		}
		break;

	case 'recent':
	default:
		break;
}

$query = new WP_Query( $query_args );

if ( ! $query->have_posts() ) {
	if ( current_user_can( 'edit_posts' ) ) {
		$label = $title ?: __( 'Rail d\'articles', '180c' );
		echo '<div class="block-180c-articles-rail block-180c-articles-rail--placeholder"><p>'
			. esc_html( sprintf( __( '%s : aucun article trouvé.', '180c' ), $label ) )
			. '</p></div>';
	}
	wp_reset_postdata();
	return;
}

$items_html = array();
while ( $query->have_posts() ) {
	$query->the_post();
	$items_html[] = _180c_block_render_article_card( get_post() );
}
wp_reset_postdata();

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-articles-rail' ) );
?>
<section <?php echo $wrapper_attrs; ?>>
	<?php if ( $title ) : ?>
		<h2 class="block-180c-articles-rail__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<div class="block-180c-articles-rail__track" role="list">
		<?php foreach ( $items_html as $item ) : ?>
			<div class="block-180c-articles-rail__item" role="listitem">
				<?php echo $item; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
