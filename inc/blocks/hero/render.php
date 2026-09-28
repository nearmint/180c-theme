<?php
/**
 * Bloc 180c/hero — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$post_id       = isset( $attributes['selected_post_id'] ) ? (int) $attributes['selected_post_id'] : 0;
$display_style = isset( $attributes['display_style'] ) ? $attributes['display_style'] : 'full';
$cta_label     = isset( $attributes['cta_label'] ) && $attributes['cta_label'] ? $attributes['cta_label'] : '';
$cta_url       = isset( $attributes['cta_url'] ) && $attributes['cta_url'] ? $attributes['cta_url'] : '';

if ( ! $post_id ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-hero block-180c-hero--placeholder"><p>' . esc_html__( 'Hero : sélectionnez un article, une recette ou un produit dans les réglages du bloc.', '180c' ) . '</p></div>';
	}
	return;
}

$post = get_post( $post_id );

if ( ! $post || 'publish' !== get_post_status( $post ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<div class="block-180c-hero block-180c-hero--placeholder"><p>' . esc_html__( 'Hero : le contenu sélectionné est introuvable ou non publié.', '180c' ) . '</p></div>';
	}
	return;
}

$permalink = esc_url( get_permalink( $post ) );
$title     = esc_html( $post->post_title );
$excerpt   = wp_kses_post( wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 30, '…' ) );
$thumb_id  = get_post_thumbnail_id( $post );

// Surtitre : catégorie principale ou type de contenu.
$supertitle = '';
if ( 'post' === $post->post_type ) {
	$cats = get_the_category( $post->ID );
	if ( ! empty( $cats ) ) {
		$supertitle = esc_html( $cats[0]->name );
	}
} elseif ( 'recipe' === $post->post_type ) {
	$supertitle = esc_html__( 'Recette', '180c' );
} elseif ( 'product' === $post->post_type ) {
	$supertitle = esc_html__( 'À la boutique', '180c' );
}

// CTA : priorité attribut, sinon permalink.
if ( ! $cta_label ) {
	if ( 'product' === $post->post_type ) {
		$cta_label = __( 'Découvrir', '180c' );
	} elseif ( 'recipe' === $post->post_type ) {
		$cta_label = __( 'Voir la recette', '180c' );
	} else {
		$cta_label = __( 'Lire l\'article', '180c' );
	}
}
if ( ! $cta_url ) {
	$cta_url = $permalink;
}

// Image hero : thumbnail ou image ACF hero_image pour les recettes.
$img_html = '';
if ( $thumb_id ) {
	$img_html = wp_get_attachment_image(
		$thumb_id,
		'full',
		false,
		array(
			'class'         => 'block-180c-hero__image',
			'loading'       => 'eager',
			'decoding'      => 'async',
			'fetchpriority' => 'high',
		)
	);
} elseif ( 'recipe' === $post->post_type && function_exists( 'get_field' ) ) {
	$hero_image = get_field( 'hero_image', $post->ID );
	if ( $hero_image ) {
		$img_html = wp_get_attachment_image(
			is_array( $hero_image ) ? $hero_image['ID'] : (int) $hero_image,
			'full',
			false,
			array(
				'class'         => 'block-180c-hero__image',
				'loading'       => 'eager',
				'decoding'      => 'async',
				'fetchpriority' => 'high',
			)
		);
	}
}

$style_class   = 'split' === $display_style ? 'block-180c-hero--split' : 'block-180c-hero--full';
$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-hero ' . $style_class ) );
?>
<div <?php echo $wrapper_attrs; ?>>
	<?php if ( $img_html ) : ?>
		<div class="block-180c-hero__media">
			<?php echo $img_html; ?>
		</div>
	<?php endif; ?>
	<div class="block-180c-hero__content">
		<?php if ( $supertitle ) : ?>
			<span class="block-180c-hero__supertitle"><?php echo $supertitle; ?></span>
		<?php endif; ?>
		<h1 class="block-180c-hero__title"><?php echo $title; ?></h1>
		<?php if ( $excerpt ) : ?>
			<p class="block-180c-hero__excerpt"><?php echo $excerpt; ?></p>
		<?php endif; ?>
		<a class="btn btn--primary block-180c-hero__cta" href="<?php echo esc_url( $cta_url ); ?>">
			<?php echo esc_html( $cta_label ); ?>
		</a>
	</div>
</div>
