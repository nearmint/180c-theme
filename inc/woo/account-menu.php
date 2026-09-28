<?php
/**
 * Mon Compte WooCommerce — 180°C.
 *
 * Enregistre les endpoints custom, filtre le menu, et branche
 * les templates parts/account/*.php sur chaque endpoint.
 *
 * Items custom retirés du menu Woo dans le cadre de la refonte single-page
 * (cf parts/account/dashboard.php). Endpoints (rewrite rules) et templates
 * legacy parts/account/*.php conservés temporairement, cleanup prévu dans
 * ticket séparé.
 *
 * Endpoints (dormants côté menu, toujours accessibles via URL) :
 *  - profil
 *  - abonnement
 *  - factures
 *  - newsletter
 *  - commandes (remplace orders Woo)
 *  - favoris
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// 1. Enregistrement des endpoints custom via add_rewrite_endpoint()
// ============================================================

add_action(
	'init',
	function () {
		add_rewrite_endpoint( 'profil', EP_PAGES );
		add_rewrite_endpoint( 'abonnement', EP_PAGES );
		add_rewrite_endpoint( 'factures', EP_PAGES );
		add_rewrite_endpoint( 'newsletter', EP_PAGES );
		add_rewrite_endpoint( 'commandes', EP_PAGES );
		add_rewrite_endpoint( 'favoris', EP_PAGES );
	}
);

// ============================================================
// 2. Ajout des endpoints aux query_vars de WordPress
// ============================================================

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'profil';
		$vars[] = 'abonnement';
		$vars[] = 'factures';
		$vars[] = 'newsletter';
		$vars[] = 'commandes';
		$vars[] = 'favoris';
		return $vars;
	}
);

// ============================================================
// 3. Menu Mon Compte custom
// ============================================================

/**
 * Remplace le menu Woo par le menu 180°C — version single-page.
 *
 * Les items custom (profil, abonnement, commandes, factures, favoris,
 * newsletter) ont été retirés au profit du dashboard unique avec ancres.
 * Les endpoints WC natifs restent listés pour rester accessibles depuis
 * la sidebar du dashboard (boutons « Modifier ») et le fil d'Ariane Woo.
 *
 * @param array $items Items du menu Woo natifs (ignorés, on retourne le nôtre).
 * @return array Menu custom.
 */
function _180c_account_menu_items( array $items ): array {
	unset( $items );

	return array(
		'dashboard'       => __( 'Tableau de bord', '180c' ),
		'edit-account'    => __( 'Mon profil', '180c' ),
		'edit-address'    => __( 'Adresses', '180c' ),
		'payment-methods' => __( 'Moyens de paiement', '180c' ),
		'customer-logout' => __( 'Déconnexion', '180c' ),
	);
}
add_filter( 'woocommerce_account_menu_items', '_180c_account_menu_items' );

// ============================================================
// 4. URLs des endpoints custom
// ============================================================

/**
 * Retourne l'URL correcte pour les endpoints custom Mon Compte.
 *
 * @param string $url      URL courante.
 * @param string $endpoint Slug de l'endpoint.
 * @param string $value    Valeur de l'endpoint.
 * @param string $permalink Permalink de la page Mon Compte.
 * @return string URL modifiée.
 */
function _180c_account_menu_endpoint_url( string $url, string $endpoint, string $value, string $permalink ): string {
	$custom_endpoints = array( 'profil', 'abonnement', 'factures', 'newsletter', 'commandes', 'favoris' );

	if ( in_array( $endpoint, $custom_endpoints, true ) ) {
		return trailingslashit( $permalink ) . $endpoint . '/';
	}

	return $url;
}
add_filter( 'woocommerce_get_endpoint_url', '_180c_account_menu_endpoint_url', 10, 4 );

// ============================================================
// 5. Contenu de chaque endpoint — branche vers parts/account/
// ============================================================

/**
 * Endpoint : profil.
 *
 * @return void
 */
add_action(
	'woocommerce_account_profil_endpoint',
	function () {
		get_template_part( 'parts/account/profil' );
	}
);

/**
 * Endpoint : abonnement.
 *
 * @return void
 */
add_action(
	'woocommerce_account_abonnement_endpoint',
	function () {
		get_template_part( 'parts/account/abonnement' );
	}
);

/**
 * Endpoint : factures.
 *
 * @return void
 */
add_action(
	'woocommerce_account_factures_endpoint',
	function () {
		get_template_part( 'parts/account/factures' );
	}
);

/*
 * Endpoint : newsletter — onglet legacy SUPPRIMÉ (audit 2026-06, Lot D).
 *
 * Le toggle « gratuite » de cet onglet n'écrivait qu'en meta locale (jamais
 * Mailchimp) et le toggle premium faisait doublon avec l'enrôlement auto par
 * abonnement. La surface d'opt-in gratuit vit désormais sur les formulaires
 * publics ; l'opt-in abonné sur le dashboard (parts/account/dashboard.php).
 * Le slug `newsletter` reste enregistré (section 1) et 301 vers /mon-compte/
 * (section 7), comme ses 5 endpoints frères consolidés — mais sans template ni
 * action de rendu : plus aucune fiche `parts/account/newsletter.php`.
 */

/**
 * Endpoint : commandes (remplace l'endpoint orders natif Woo).
 *
 * @return void
 */
add_action(
	'woocommerce_account_commandes_endpoint',
	function () {
		get_template_part( 'parts/account/commandes' );
	}
);

/**
 * Endpoint : favoris.
 *
 * @return void
 */
add_action(
	'woocommerce_account_favoris_endpoint',
	function () {
		get_template_part( 'parts/account/favoris' );
	}
);

// ============================================================
// 6. Suppression de la nav WC native sur toutes les pages /mon-compte/*
// La refonte single-page rend sa propre sidebar (cf parts/account/dashboard.php).
// Le hook tourne en priorité 20 sur 'wp' pour s'assurer que WC a fini
// d'enregistrer ses propres actions avant qu'on en retire une.
// ============================================================

add_action(
	'wp',
	static function (): void {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation' );
		}
	},
	20
);

// ============================================================
// 7. Legacy endpoints 301 → /mon-compte/. Endpoints natifs WC préservés.
//
// Les 6 endpoints custom (profil, abonnement, factures, newsletter,
// commandes, favoris) ont ete consolides dans la single-page
// /mon-compte/ via ancres. Les rewrite rules restent enregistrees
// (cf section 1) pour ne pas casser le routage WP, mais toute requete
// vers ces sous-URLs est 301 vers la page principale.
//
// Les endpoints natifs WC (edit-account, edit-address, payment-methods,
// view-order, orders, etc.) ne sont JAMAIS rediriges.
// ============================================================

add_action(
	'template_redirect',
	static function (): void {
		if ( is_admin() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		if ( ! isset( WC()->query ) || ! method_exists( WC()->query, 'get_current_endpoint' ) ) {
			return;
		}

		$current = (string) WC()->query->get_current_endpoint();

		if ( '' === $current ) {
			return;
		}

		$legacy = array( 'profil', 'abonnement', 'factures', 'newsletter', 'commandes', 'favoris' );

		if ( in_array( $current, $legacy, true ) ) {
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ), 301 );
			exit;
		}
	}
);
