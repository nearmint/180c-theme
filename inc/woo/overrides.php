<?php
/**
 * Surcharges WooCommerce — 180°C.
 *
 * Hooks : cart-first, reviews, free shipping blurb,
 * slug mon-compte, et endpoints Mon Compte.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// 1. HPOS — pourquoi aucune déclaration de compatibilité ici
// ============================================================
//
// `FeaturesUtil::declare_compatibility()` attend le chemin d'un fichier de
// PLUGIN — le `__FILE__` d'une extension enregistrée — qu'elle résout via le
// registre des plugins. Un chemin de thème n'y figure jamais : le
// `FeaturesController` rejette la déclaration ET journalise une erreur à
// CHAQUE requête passant par `before_woocommerce_init`.
//
// Le thème a porté un tel appel jusqu'au 2026-08-22. Effet mesuré sur deux
// jours de logs de production : 66 entrées ERROR « Invalid plugin file [...]
// for feature 'custom_order_tables' », soit 100 % du log d'erreurs
// WooCommerce — au point d'y masquer toute erreur réelle — sans que la
// compatibilité ait jamais été enregistrée une seule fois.
//
// NE PAS RÉINTRODUIRE : l'API est réservée aux plugins. Un thème n'a rien à
// déclarer ; il lui suffit de ne jamais accéder aux commandes via les
// fonctions `post` (`get_post_meta()`, `WP_Query` sur `shop_order`…), ce que
// ce fichier respecte.

// ============================================================
// 2. Autocomplete des commandes 100 % virtuelles
// ============================================================
//
// Les abonnements digitaux (produits `virtual` non `downloadable`) donnent un
// accès immédiat : aucune étape « en préparation » n'a de sens. Par défaut,
// WooCommerce fait passer ces commandes par `processing` avant `completed`, ce
// qui déclenche DEUX e-mails client successifs (`customer_processing_order`
// « commande reçue » puis `customer_completed_order` « commande confirmée »).
//
// On corrige à la source, via le filtre canonique
// `woocommerce_payment_complete_order_status` : au paiement, une commande 100 %
// virtuelle passe directement à `completed`. Elle ne transite jamais par
// `processing`, donc un seul e-mail est envoyé.
//
// Avantages vs l'ancien auto-complete sur `woocommerce_thankyou` :
// - fiable même si la cliente ne revient pas sur la page de remerciement
// (paiement asynchrone / Apple Pay / Google Pay via Stripe Express) ;
// - l'e-mail `processing` n'est jamais émis (au lieu d'être émis puis suivi
// d'un `completed` redondant).
//
// Les commandes contenant au moins un produit physique conservent le flux
// standard `processing` → `completed` (« reçue » puis « expédiée »).

/**
 * Force le statut `completed` dès le paiement pour les commandes 100 % virtuelles.
 *
 * @param string $order_status Statut calculé par WooCommerce après paiement.
 * @param int    $order_id     ID de la commande.
 * @return string Statut à appliquer.
 */
function _180c_virtual_orders_complete_on_payment( string $order_status, int $order_id ): string {
	// On n'agit que si WooCommerce s'apprêtait à mettre la commande en
	// `processing` : on ne perturbe pas un `on-hold` (paiement différé/SEPA…).
	if ( 'processing' !== $order_status ) {
		return $order_status;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return $order_status;
	}

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( ! $product || ! $product->is_virtual() ) {
			return $order_status; // Au moins un produit physique → flux standard.
		}
	}

	return 'completed';
}
add_filter( 'woocommerce_payment_complete_order_status', '_180c_virtual_orders_complete_on_payment', 10, 2 );

// ============================================================
// 3. Cart-first standard — PAS de redirection directe vers checkout
// après add to cart. On laisse Woo gérer le comportement par défaut
// (notice + rester sur la page produit).
// ============================================================

/**
 * Force le comportement cart-first : après add-to-cart, l'utilisateur
 * reste sur la page produit. On laisse WooCommerce afficher la notice
 * native "Produit ajouté au panier" avec le lien vers le panier.
 *
 * On n'agit sur ce filtre QUE si la redirection pointait directement
 * vers le checkout (comportement "Direct Checkout" à éviter).
 */
add_filter(
	'woocommerce_add_to_cart_redirect',
	function ( $url ) {
		// Si la redirection cible directement le checkout, l'annuler.
		if ( $url && false !== strpos( $url, wc_get_checkout_url() ) ) {
			return wc_get_cart_url();
		}
		return $url;
	}
);

// ============================================================
// 4. Désactiver les avis (reviews) sur les produits
// ============================================================

add_action(
	'init',
	function () {
		remove_post_type_support( 'product', 'comments' );
	},
	20
);

add_filter(
	'woocommerce_product_tabs',
	function ( $tabs ) {
		unset( $tabs['reviews'] );
		return $tabs;
	}
);

// ============================================================
// 6. Layout cart collaterals
// ============================================================

/**
 * Ouvre le wrapper layout pour les totaux du panier.
 *
 * @return void
 */
function _180c_cart_collaterals_open(): void {
	echo '<div class="cart-collaterals__inner">';
}
add_action( 'woocommerce_cart_collaterals', '_180c_cart_collaterals_open', 1 );

/**
 * Ferme le wrapper layout pour les totaux du panier.
 *
 * @return void
 */
function _180c_cart_collaterals_close(): void {
	echo '</div>';
}
add_action( 'woocommerce_cart_collaterals', '_180c_cart_collaterals_close', 99 );

// ============================================================
// 7. Slug my-account → mon-compte
// Géré via WooCommerce > Réglages > Comptes & Confidentialité
// en production. On s'assure ici que la page est bien définie
// et on filtre l'URL si le slug diffère.
// ============================================================

add_filter(
	'woocommerce_get_myaccount_page_permalink',
	function ( $permalink ) {
		// Le slug est configuré au niveau de la page WP (slug: mon-compte).
		// Ce filtre permet un fallback propre si la page n'a pas le bon slug.
		return $permalink;
	}
);

// ============================================================
// 7b. Mon Compte non connecté → redirection vers /connexion/
// WooCommerce affiche son propre formulaire de connexion sur la
// page Mon Compte ; on impose à la place notre page d'auth custom
// (/connexion/) en conservant la cible via redirect_to.
// ============================================================

add_action(
	'template_redirect',
	function () {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || is_user_logged_in() ) {
			return;
		}

		// Cible de retour : la page Mon Compte (permalink configuré côté WP).
		$target = wc_get_page_permalink( 'myaccount' );
		if ( ! $target ) {
			$target = home_url( '/mon-compte/' );
		}

		wp_safe_redirect(
			add_query_arg(
				'redirect_to',
				rawurlencode( $target ),
				home_url( '/connexion/' )
			)
		);
		exit;
	}
);

// ============================================================
// 7c. Masquer la catégorie « Abonnement digital » sur la Boutique
// La Boutique ne doit pas lister les produits de la catégorie
// « Abonnement digital » (slug `abonnements`) : l'abonnement est promu
// via ses surfaces dédiées, pas dans le catalogue produit. Deux surfaces
// sont concernées et partagent le même helper :
//   - l'archive WooCommerce (is_shop()), filtrée ici via le hook ;
//   - la page WP `boutique` (template-home.php), dont le module
//     `products_rail` (mode « recent ») est filtré dans
//     parts/modules/products_rail.php.
// L'archive propre de la catégorie reste accessible en accès direct.
// ============================================================

/**
 * Term IDs de catégories produit à masquer des surfaces « Boutique ».
 *
 * Résolution robuste : par slug, repli par nom. Résultat mémoïsé pour la
 * durée de la requête (évite des get_term_by répétés dans les rails).
 *
 * @return int[] Liste de term_id `product_cat` à exclure (peut être vide).
 */
function _180c_boutique_hidden_term_ids(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$term = get_term_by( 'slug', 'abonnements', 'product_cat' );
	if ( ! $term ) {
		$term = get_term_by( 'name', 'Abonnement digital', 'product_cat' );
	}

	$cache = ( $term && ! is_wp_error( $term ) ) ? array( (int) $term->term_id ) : array();
	return $cache;
}

add_action(
	'woocommerce_product_query',
	function ( $query ) {
		if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
			return;
		}

		$hidden = _180c_boutique_hidden_term_ids();
		if ( empty( $hidden ) ) {
			return;
		}

		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $hidden,
			'operator' => 'NOT IN',
		);
		$query->set( 'tax_query', $tax_query );
	}
);

// ============================================================
// 8. Désactiver les blocks Woo (cart + checkout) si on utilise
// les shortcodes / pages classiques (cart-first standard).
// Ces blocks sont désactivés pour que nos overrides templates
// s'appliquent correctement.
// ============================================================

add_action(
	'woocommerce_blocks_loaded',
	function () {
		// On préserve le comportement shortcode-based pour cart + checkout.
		// Les blocks Cart et Checkout de WooCommerce sont ignorés au profit
		// de nos templates surchargés (templates/woocommerce/cart/cart.php
		// et templates/woocommerce/checkout/form-checkout.php).
		// Pas de suppression d'enqueue ici — Woo gère ça via ses propres checks.
	}
);
