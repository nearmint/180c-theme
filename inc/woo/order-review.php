<?php
/**
 * Récapitulatif de commande — 180°C.
 *
 * Refonte du bloc « review-order » du checkout pour un récap lisible sur les
 * trois cas (revues seules / abonnement seul / mixte), compatible WooCommerce
 * + Subscriptions + Memberships.
 *
 * Ce fichier :
 *  - neutralise le rendu « totaux récurrents » natif de WooCommerce
 *    Subscriptions (table déléguée à `checkout/recurring-totals.php`) afin que
 *    le template thème pilote intégralement le bloc « Puis chaque {période} » ;
 *  - expose des helpers `_180c_` en lecture seule sur le panier (aucun calcul,
 *    aucune sortie HTML directe) consommés par
 *    `woocommerce/checkout/review-order.php`.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Détache le rendu des totaux récurrents natif de Subscriptions.
 *
 * Subscriptions injecte sa table « Recurring totals » via
 * `WC_Subscriptions_Cart::display_recurring_totals()` accrochée sur
 * `woocommerce_review_order_after_order_total` (priorité 10, cf.
 * includes/core/class-wc-subscriptions-cart.php). Le récap 180°C rend son
 * propre bloc « Puis chaque {période} » directement dans le template : on
 * retire donc le callback natif pour éviter un double affichage.
 *
 * Exécuté sur `wp_loaded` (après l'enregistrement du hook par le plugin).
 *
 * @return void
 */
function _180c_detach_native_recurring_totals() {
	if ( ! class_exists( 'WC_Subscriptions_Cart' ) ) {
		return;
	}

	remove_action(
		'woocommerce_review_order_after_order_total',
		array( 'WC_Subscriptions_Cart', 'display_recurring_totals' )
	);
}
add_action( 'wp_loaded', '_180c_detach_native_recurring_totals' );

/**
 * Montant réellement débité aujourd'hui.
 *
 * Total panier natif (TTC), qui inclut déjà le 1er versement de l'abonnement
 * lorsqu'un produit d'abonnement est au panier. Aucun calcul manuel : on lit le
 * total fourni par WooCommerce.
 *
 * @return string Montant formaté (HTML de prix WooCommerce) ou chaîne vide.
 */
function _180c_get_today_total() {
	if ( ! WC()->cart ) {
		return '';
	}

	return WC()->cart->get_total( 'view' );
}

/**
 * Lignes de TVA du panier, une par taux.
 *
 * Le libellé s'adapte au mode d'affichage des prix :
 *  - prix TTC → « dont {label} » (TVA déjà comprise dans « À payer aujourd'hui ») ;
 *  - prix HT  → « + {label} »   (TVA ajoutée au montant).
 *
 * @return array<int,array{label:string,amount:string}> Liste vide si pas de TVA.
 */
function _180c_get_cart_tax_lines() {
	if ( ! WC()->cart || ! wc_tax_enabled() ) {
		return array();
	}

	$includes_tax = WC()->cart->display_prices_including_tax();
	$prefix       = $includes_tax ? __( 'dont', '180c' ) : '+';
	$lines        = array();

	foreach ( WC()->cart->get_tax_totals() as $tax ) {
		$lines[] = array(
			'label'  => trim( $prefix . ' ' . $tax->label ),
			'amount' => $tax->formatted_amount,
		);
	}

	return $lines;
}

/**
 * Récapitulatif de l'abonnement récurrent (le cas échéant).
 *
 * Un seul abonnement par panier est garanti (contrainte métier) : on lit donc
 * le premier `recurring_cart` non vide, sans boucle d'agrégation. Aucun calcul
 * manuel : montant lu via `WC_Cart::get_total()`.
 *
 * @return array{amount:string,period:string,label:string,next_date:?string}|null
 *         null si aucun abonnement au panier.
 */
function _180c_get_recurring_summary() {
	if ( ! WC()->cart || empty( WC()->cart->recurring_carts ) ) {
		return null;
	}

	$recurring_cart = null;
	foreach ( WC()->cart->recurring_carts as $cart ) {
		if ( ! empty( $cart ) ) {
			$recurring_cart = $cart;
			break;
		}
	}

	if ( null === $recurring_cart || ! function_exists( 'wcs_cart_pluck' ) ) {
		return null;
	}

	$period = wcs_cart_pluck( $recurring_cart, 'subscription_period', '' );

	$labels = array(
		'month' => __( 'Puis chaque mois', '180c' ),
		'year'  => __( 'Puis chaque année', '180c' ),
	);
	$label  = isset( $labels[ $period ] ) ? $labels[ $period ] : __( 'Puis chaque période', '180c' );

	$next_date = null;
	if ( ! empty( $recurring_cart->next_payment_date ) && function_exists( 'wcs_date_to_time' ) ) {
		$next_date = date_i18n( wc_date_format(), wcs_date_to_time( $recurring_cart->next_payment_date ) );
	}

	return array(
		'amount'    => $recurring_cart->get_total( 'view' ),
		'period'    => $period,
		'label'     => $label,
		'next_date' => $next_date,
	);
}

/**
 * Le panier contient-il un produit nécessitant une livraison ?
 *
 * Sert uniquement au conditionnel d'affichage de la ligne « Livraison » ; le
 * rendu reste natif (`wc_cart_totals_shipping_html()`).
 *
 * @return bool
 */
function _180c_cart_has_shipping() {
	if ( ! WC()->cart ) {
		return false;
	}

	return WC()->cart->needs_shipping() && WC()->cart->show_shipping();
}
