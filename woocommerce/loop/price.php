<?php
/**
 * Loop Price — surcharge 180°C.
 *
 * Formatage prix avec font-display et mise en avant visuelle.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     1.6.4
 */

defined( 'ABSPATH' ) || exit;

global $product;
?>

<?php
$price_html = $product->get_price_html();
if ( $price_html ) :
	?>
	<span class="price product-price">
		<?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</span>
<?php endif; ?>
