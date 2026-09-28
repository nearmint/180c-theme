<?php
/**
 * Pattern PHP — hero immersif d'un article (polish).
 *
 * Image immersive pleine largeur (LCP : eager + fetchpriority). Crédit
 * photo dans un bloc `.media-credit` repliable (composant partagé
 * `.media-credit` + `.overlay-icon-btn` + `.media-caption`).
 *
 * Le titre, l'eyebrow, le chapô et la byline sont rendus séparément
 * sous le hero via `_180c_render_entry_header()` (cf. single.php).
 *
 * Inclus depuis single.php via get_template_part( 'patterns/article-hero' ).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$post_id = (int) get_the_ID();
if ( ! $post_id ) {
	return;
}

$thumb_id = (int) get_post_thumbnail_id( $post_id );

if ( ! $thumb_id ) {
	return;
}

// Légende et crédit ne font qu'UN seul champ : le « Caption » de la modale
// média, stocké dans le post_excerpt de l'attachment et renvoyé par
// wp_get_attachment_caption(). Le pattern le lisait deux fois (légende puis
// « crédit ») et l'affichait en double, séparé par « · ».
$thumb_caption = trim( (string) wp_get_attachment_caption( $thumb_id ) );
$has_credit    = '' !== $thumb_caption;

// Légende réutilisée par la lightbox.
$thumb_lightbox_caption = $thumb_caption;

$lightbox_src = (string) wp_get_attachment_image_url( $thumb_id, 'large' );
?>

<figure
	class="article-hero__media media-credit"
	data-lightbox-src="<?php echo esc_url( $lightbox_src ); ?>"
	data-caption="<?php echo esc_attr( $thumb_lightbox_caption ); ?>"
>

	<?php
	echo wp_get_attachment_image(
		$thumb_id,
		'full',
		false,
		array(
			'class'         => 'article-hero__image',
			'alt'           => esc_attr( get_the_title( $post_id ) ),
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
			// L'image n'est plus pleine page : largeur de la carte entry-header
			// (≈80% du container ≥1024px, sinon pleine largeur du container).
			'sizes'         => '(min-width: 1024px) 60rem, 100vw',
			'itemprop'      => 'image',
		)
	);
	?>

	<button
		type="button"
		class="article-hero__zoom overlay-icon-btn"
		aria-label="<?php esc_attr_e( "Afficher l'image en grand", '180c' ); ?>"
		aria-haspopup="dialog"
		data-lightbox-trigger
	>
		<span class="article-hero__zoom-icon" aria-hidden="true"><?php echo _180c_render_svg_icon( 'expand' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</button>

	<?php if ( $has_credit ) : ?>
		<button
			type="button"
			class="media-credit__toggle overlay-icon-btn"
			aria-label="<?php esc_attr_e( "Afficher le crédit de l'image", '180c' ); ?>"
		>
			<?php echo _180c_render_svg_icon( 'copyright' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>
		<figcaption class="media-credit__caption media-caption">
			<?php echo esc_html( $thumb_caption ); ?>
		</figcaption>
	<?php endif; ?>

</figure>
