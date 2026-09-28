<?php
/**
 * Single Product Image — surcharge 180°C.
 *
 * Reprend le gabarit du cœur WooCommerce 11.1.0, y compris la galerie média
 * (`ProductMediaGallery`) qui autorise une vidéo en premier élément.
 *
 * DEUX adaptations 180°C, et rien d'autre :
 *  1. classe `product-page__gallery` sur le conteneur (design system) ;
 *  2. l'image principale est une candidate LCP : `loading="eager"`,
 *     `fetchpriority="high"` et un `sizes` accordé à notre gabarit deux
 *     colonnes lui sont injectés. L'injection ne vise QUE la branche image —
 *     une vidéo de galerie n'a pas de `<img>` à prioriser et le HTML produit
 *     par `get_gallery_video_html()` doit rester intact.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.1.0
 */

use Automattic\WooCommerce\Enums\ProductType;
use Automattic\WooCommerce\Internal\ProductGallery\ProductMediaGallery;

defined( 'ABSPATH' ) || exit;

// Note: `wc_get_gallery_image_html` was added in WC 3.3.2 and did not exist prior. This check protects against theme overrides being used on older versions of WC.
if ( ! function_exists( 'wc_get_gallery_image_html' ) ) {
	return;
}

global $product;

/*
 * Les noms de variables qui suivent sont ceux du gabarit du cœur, à la lettre :
 * toute renomination rendrait la prochaine resynchronisation illisible. Le sniff
 * PrefixAllGlobals est de toute façon un faux positif ici — un gabarit chargé
 * par `wc_get_template()` s'exécute dans la portée de cette fonction, ces
 * variables ne sont pas globales. Désactivation limitée à ce bloc et à ce sniff.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$columns           = apply_filters( 'woocommerce_product_thumbnails_columns', 4 );
$post_thumbnail_id = $product->get_image_id();
$media_items       = ProductMediaGallery::get_product_media_gallery_items_for_display( $product );
// The helper returns the full gallery order; product-thumbnails.php renders the remaining items.
$first_media_item = $media_items[0] ?? array();
$first_media_id   = isset( $first_media_item['id'] ) ? absint( $first_media_item['id'] ) : $post_thumbnail_id;
$has_media        = ! empty( $first_media_item ) && 'placeholder' !== ( $first_media_item['source_type'] ?? '' );
$is_video         = $has_media && 'video' === ( $first_media_item['media_type'] ?? '' );
$wrapper_classes  = apply_filters(
	'woocommerce_single_product_image_gallery_classes',
	array(
		'woocommerce-product-gallery',
		'woocommerce-product-gallery--' . ( $has_media ? 'with-images' : 'without-images' ),
		'woocommerce-product-gallery--columns-' . absint( $columns ),
		'images',
		'product-page__gallery',
	)
);
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>"
	data-columns="<?php echo esc_attr( $columns ); ?>"
	style="opacity: 0; transition: opacity .25s ease-in-out;">
	<div class="woocommerce-product-gallery__wrapper">
		<?php
		if ( $is_video ) {
			$html = ProductMediaGallery::get_gallery_video_html( $first_media_item, true );
		} elseif ( $has_media && $first_media_id ) {
			$html = wc_get_gallery_image_html( $first_media_id, true );

			/*
			 * Adaptation 180°C — l'image principale est la candidate LCP de la
			 * fiche produit : chargement immédiat, priorité haute, et `sizes`
			 * accordé au gabarit (pleine largeur sous 768px, moitié au-delà).
			 */
			$html = preg_replace(
				'/<img\s/i',
				'<img loading="eager" fetchpriority="high" sizes="(max-width: 768px) 100vw, 50vw" ',
				$html,
				1
			);
		} else {
			// Check for visible children with prices to determine if variation image swapping is possible.
			// Using get_visible_children() + get_price() is more efficient than get_available_variations()
			// as it uses cached IDs and synced price data rather than loading all variation objects.
			$wrapper_classname = $product->is_type( ProductType::VARIABLE ) && ! empty( $product->get_visible_children() ) && '' !== $product->get_price() ?
				'woocommerce-product-gallery__image woocommerce-product-gallery__image--placeholder' :
				'woocommerce-product-gallery__image--placeholder';
			$html              = sprintf( '<div class="%s">', esc_attr( $wrapper_classname ) );
			// Adaptation 180°C : le visuel de repli n'est jamais la LCP, il reste en `lazy`.
			$html .= sprintf( '<img src="%s" alt="%s" class="wp-post-image" loading="lazy" />', esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ), esc_html__( 'Awaiting product image', 'woocommerce' ) );
			$html .= '</div>';
		}

		if ( $is_video ) {
			/**
			 * Filter product video thumbnail HTML string.
			 *
			 * @since 11.0.0
			 * @internal For exclusive usage of WooCommerce core, backwards compatibility not guaranteed.
			 *
			 * @param string $html          Product video thumbnail HTML string.
			 * @param int    $attachment_id Video attachment ID.
			 * @param array  $media_item    Product media gallery item.
			 */
			echo apply_filters( 'woocommerce_single_product_video_thumbnail_html', $html, $first_media_id, $first_media_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			/**
			 * Filter product image thumbnail HTML string.
			 *
			 * @since 1.6.4
			 *
			 * @param string $html          Product image thumbnail HTML string.
			 * @param int    $attachment_id Attachment ID.
			 */
			echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', $html, $first_media_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		do_action( 'woocommerce_product_thumbnails' );
		?>
	</div>
</div>
