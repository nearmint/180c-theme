<?php
/**
 * Apple Pay / Google Pay via Stripe Express Checkout — 180°C.
 *
 * Assure que les boutons Express Checkout s'affichent aux bons endroits
 * si le plugin WooCommerce Stripe Gateway est actif.
 * Ne réimplémente PAS le SDK Stripe.
 *
 * Emplacement des boutons :
 * - Page produit (au-dessus du bouton ATC)
 * - Page panier (avant "Passer la commande")
 * - Page checkout (en haut)
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// 1. Domain association Apple Pay
// .well-known/apple-developer-merchantid-domain-association
// ============================================================

add_action(
	'init',
	function () {
		add_rewrite_rule(
			'^\.well-known/apple-developer-merchantid-domain-association$',
			'index.php?_180c_apple_assoc=1',
			'top'
		);
	}
);

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = '_180c_apple_assoc';
		return $vars;
	}
);

add_action(
	'template_redirect',
	function () {
		if ( ! get_query_var( '_180c_apple_assoc' ) ) {
			return;
		}

		$file = _180C_THEME_DIR . '/well-known/apple-developer-merchantid-domain-association';
		if ( ! file_exists( $file ) ) {
			status_header( 404 );
			exit;
		}

		header( 'Content-Type: text/plain' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.readfile_readfile
		exit;
	}
);

// ============================================================
// 2. Helper : détecte si Stripe Express Checkout est disponible
// ============================================================

/**
 * Vérifie si le plugin Stripe WooCommerce est actif et supporte
 * l'Express Checkout (Apple Pay / Google Pay).
 *
 * @return bool
 */
function _180c_stripe_express_available(): bool {
	return class_exists( 'WC_Stripe_Payment_Request' )
		|| class_exists( 'WC_Stripe_Express_Checkout_Element' )
		|| ( function_exists( 'wc_stripe' ) && method_exists( wc_stripe(), 'get_main_stripe_gateway' ) );
}

// ============================================================
// 3. Rendu bouton Express Checkout
// Utilise le hook natif Stripe si disponible, sinon silencieux.
// ============================================================

/**
 * Affiche le bouton Stripe Express Checkout (Apple Pay / Google Pay).
 * Wrapper avec classe design system pour le positionnement.
 *
 * @param string $context Contexte d'affichage (product, cart, checkout).
 * @return void
 */
function _180c_render_stripe_express_button( string $context = 'product' ): void {
	if ( ! _180c_stripe_express_available() ) {
		return;
	}

	echo '<div class="stripe-express stripe-express--' . esc_attr( $context ) . '">';

	/**
	 * Hook Stripe Express Checkout Element (WooCommerce Stripe Gateway >= 7.x).
	 * Tente d'abord le hook moderne, puis le legacy.
	 */
	if ( has_action( 'wc_stripe_express_checkout_button_html' ) ) {
		do_action( 'wc_stripe_express_checkout_button_html' );
	} elseif ( has_action( 'woocommerce_stripe_payment_request_button_html' ) ) {
		do_action( 'woocommerce_stripe_payment_request_button_html' );
	}

	echo '</div>';
}

// ============================================================
// 4. Hooks d'affichage — Page produit
// ============================================================

/**
 * Affiche le bouton Express Checkout sur la page produit,
 * au-dessus du bouton "Ajouter au panier".
 *
 * @return void
 */
function _180c_stripe_express_after_atc(): void {
	_180c_render_stripe_express_button( 'product' );
}
add_action( 'woocommerce_after_add_to_cart_button', '_180c_stripe_express_after_atc', 5 );

// ============================================================
// 5. Hooks d'affichage — Page panier
// ============================================================

/**
 * Affiche le bouton Express Checkout dans le panier,
 * avant le bouton "Passer la commande".
 *
 * @return void
 */
function _180c_stripe_express_in_cart(): void {
	_180c_render_stripe_express_button( 'cart' );
}
add_action( 'woocommerce_proceed_to_checkout', '_180c_stripe_express_in_cart', 5 );

// ============================================================
// 6. Hooks d'affichage — Page checkout
// ============================================================

/**
 * Affiche le bouton Express Checkout en haut de la page checkout.
 *
 * @return void
 */
function _180c_stripe_express_on_checkout(): void {
	_180c_render_stripe_express_button( 'checkout' );
}
add_action( 'woocommerce_checkout_before_customer_details', '_180c_stripe_express_on_checkout', 5 );

// ============================================================
// 7. Style du bouton Stripe Express Checkout
// ============================================================

/**
 * Personnalise l'apparence du bouton Stripe Express Checkout.
 * Compatible avec l'API WooCommerce Stripe Gateway.
 *
 * @param array $style Style courant.
 * @return array Style modifié.
 */
function _180c_stripe_button_style( array $style ): array {
	return array_merge(
		$style,
		array(
			'theme'  => 'dark',
			'type'   => 'buy',
			'height' => '44px',
		)
	);
}
add_filter( 'wc_stripe_payment_request_button_style', '_180c_stripe_button_style' );
add_filter( 'wc_stripe_express_checkout_button_options', '_180c_stripe_button_style' );
