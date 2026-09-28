<?php
/**
 * Product Loop Start — surcharge 180°C.
 *
 * Markup grille produits cohérent avec .card-product du design system.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     3.3.0
 */

defined( 'ABSPATH' ) || exit;
?>
<ul class="products products-grid columns-<?php echo esc_attr( wc_get_loop_prop( 'columns' ) ); ?>">
