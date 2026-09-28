<?php
/**
 * Bloc 180c/recipes-rail — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/_helpers.php';

$title             = isset( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : '';
$mode              = isset( $attributes['mode'] ) ? $attributes['mode'] : 'recent';
$season_term_id    = isset( $attributes['season_term_id'] ) ? (int) $attributes['season_term_id'] : 0;
$type_term_id      = isset( $attributes['type_term_id'] ) ? (int) $attributes['type_term_id'] : 0;
$count             = isset( $attributes['count'] ) ? max( 1, min( 20, (int) $attributes['count'] ) ) : 8;
$manual_recipe_ids = isset( $attributes['manual_recipe_ids'] ) && is_array( $attributes['manual_recipe_ids'] ) ? array_map( 'intval', $attributes['manual_recipe_ids'] ) : array();

$query_args = array(
	'post_type'      => 'recipe',
	'post_status'    => 'publish',
	'posts_per_page' => $count,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
);

switch ( $mode ) {
	case 'season':
		if ( $season_term_id ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'recipe_season',
					'field'    => 'term_id',
					'terms'    => $season_term_id,
				),
			);
		}
		break;

	case 'type':
		if ( $type_term_id ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'recipe_category',
					'field'    => 'term_id',
					'terms'    => $type_term_id,
				),
			);
		}
		break;

	case 'manual':
		if ( ! empty( $manual_recipe_ids ) ) {
			$query_args['post__in']       = $manual_recipe_ids;
			$query_args['orderby']        = 'post__in';
			$query_args['posts_per_page'] = count( $manual_recipe_ids );
		}
		break;

	case 'recent':
	default:
		break;
}

$query = new WP_Query( $query_args );

if ( ! $query->have_posts() ) {
	if ( current_user_can( 'edit_posts' ) ) {
		$label = $title ?: __( 'Rail de recettes', '180c' );
		echo '<div class="block-180c-recipes-rail block-180c-recipes-rail--placeholder"><p>'
			. esc_html( sprintf( __( '%s : aucune recette trouvée.', '180c' ), $label ) )
			. '</p></div>';
	}
	wp_reset_postdata();
	return;
}

$items_html = array();
while ( $query->have_posts() ) {
	$query->the_post();
	$items_html[] = _180c_block_render_recipe_card( get_post() );
}
wp_reset_postdata();

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-recipes-rail' ) );
?>
<section <?php echo $wrapper_attrs; ?>>
	<?php if ( $title ) : ?>
		<h2 class="block-180c-recipes-rail__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<div class="block-180c-recipes-rail__track" role="list">
		<?php foreach ( $items_html as $item ) : ?>
			<div class="block-180c-recipes-rail__item" role="listitem">
				<?php echo $item; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
