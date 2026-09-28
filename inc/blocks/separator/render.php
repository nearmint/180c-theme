<?php
/**
 * Bloc 180c/separator — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$style = isset( $attributes['style'] ) && in_array( $attributes['style'], array( 'line', 'image' ), true ) ? $attributes['style'] : 'line';

// URL brute conservée : `attachment_url_to_postid()` interroge `_wp_attached_file`
// et échouerait sur une URL percent-encodée par esc_url() (les visuels 180°C
// contiennent des « © » et « ° » dans leur nom de fichier).
$image_url_raw = isset( $attributes['image_url'] ) ? (string) $attributes['image_url'] : '';
$image_url     = '' !== $image_url_raw ? esc_url( $image_url_raw ) : '';

// ITEM 9 / CWV — dimensions explicites (opportunité `unsized-images`). L'URL
// vient d'un attribut de bloc, sans ID de média : on le résout à la volée. On
// rend la balise sans dimensions plutôt que d'en inventer si le visuel est hors
// médiathèque, ou si `attachment_url_to_postid()` échoue parce que
// `_wp_attached_file` a été réécrit en `.webp` (cf. `inc/images.php`).
$image_w = 0;
$image_h = 0;
if ( 'image' === $style && '' !== $image_url_raw ) {
	$image_id = attachment_url_to_postid( $image_url_raw );
	if ( $image_id ) {
		$image_dim = _180c_media_dimensions_for_src( $image_url_raw, $image_id );
		if ( $image_dim ) {
			list( $image_w, $image_h ) = $image_dim;
		}
	}
}

$wrapper_attrs = get_block_wrapper_attributes(
	array( 'class' => 'block-180c-separator block-180c-separator--' . esc_attr( $style ) )
);
?>
<div <?php echo $wrapper_attrs; ?> role="separator" aria-hidden="true">
	<?php if ( 'image' === $style && $image_url ) : ?>
		<img
			src="<?php echo $image_url; ?>"
			alt=""
			role="presentation"
			class="block-180c-separator__image"
			<?php if ( $image_w && $image_h ) : ?>
			width="<?php echo (int) $image_w; ?>"
			height="<?php echo (int) $image_h; ?>"
			<?php endif; ?>
			loading="lazy"
			decoding="async"
		>
	<?php else : ?>
		<hr class="block-180c-separator__line">
	<?php endif; ?>
</div>
