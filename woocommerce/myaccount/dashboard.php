<?php
/**
 * My Account Dashboard — surcharge 180°C.
 *
 * Redirige vers notre template parts/account/dashboard.php custom.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook natif Woo — préservé pour la compatibilité plugins.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_account_dashboard' );

// Charge notre dashboard custom.
get_template_part( 'parts/account/dashboard' );

/* phpcs:ignore Squiz.PHP.EmbeddedPhp.NoSemicolon */
