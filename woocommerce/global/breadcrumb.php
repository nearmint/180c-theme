<?php
/**
 * Breadcrumb — surcharge 180°C.
 *
 * Convertit le fil généré par WooCommerce vers le composant `.breadcrumb` du
 * thème (séparateurs chevron, dernier item non cliquable avec
 * aria-current="page") via _180c_breadcrumb_markup(). Plus aucun slash.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 2.3.0
 *
 * @var array $breadcrumb Fil généré par WC_Breadcrumb (couples [label, url]).
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $breadcrumb ) || ! function_exists( '_180c_breadcrumb_markup' ) ) {
	return;
}

$items = array();
foreach ( $breadcrumb as $crumb ) {
	$items[] = array(
		'label' => isset( $crumb[0] ) ? $crumb[0] : '',
		'url'   => ( isset( $crumb[1] ) && $crumb[1] ) ? $crumb[1] : null,
	);
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le helper.
echo _180c_breadcrumb_markup( $items );
