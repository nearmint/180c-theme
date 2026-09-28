<?php
/**
 * Bloc 180c/quote — rendu serveur.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$text        = isset( $attributes['text'] ) ? $attributes['text'] : '';
$attribution = isset( $attributes['attribution'] ) ? sanitize_text_field( $attributes['attribution'] ) : '';

if ( ! $text ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<blockquote class="block-180c-quote block-180c-quote--placeholder"><p>' . esc_html__( 'Citation : saisissez le texte dans les réglages du bloc.', '180c' ) . '</p></blockquote>';
	}
	return;
}

// Autoriser balises inline sûres uniquement.
$allowed_tags = array(
	'em'     => array(),
	'strong' => array(),
	'br'     => array(),
	'span'   => array( 'class' => array() ),
);
$safe_text    = wp_kses( $text, $allowed_tags );

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'block-180c-quote' ) );
?>
<blockquote <?php echo $wrapper_attrs; ?>>
	<p class="block-180c-quote__text"><?php echo $safe_text; ?></p>
	<?php if ( $attribution ) : ?>
		<cite class="block-180c-quote__attribution"><?php echo esc_html( $attribution ); ?></cite>
	<?php endif; ?>
</blockquote>
