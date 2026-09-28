<?php
/**
 * Performance — déchargement conditionnel des assets plugin hors commerce (CH-04).
 *
 * Deux volets :
 *  - feuilles de style : ~7 <link> render-blocking WooCommerce / Memberships /
 *    Stripe / PayPal inutiles sur les pages éditoriales ;
 *  - scripts : la pile WooCommerce du <head> et, par ricochet, jQuery +
 *    jquery-migrate (34 Ko bloquants) une fois qu'elle n'a plus de dépendant.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indique si la page courante est un contexte « commerce » où les assets
 * WooCommerce / Memberships / Stripe doivent rester chargés.
 *
 * Garde-fou strict : shop & archives produit & fiche produit (is_woocommerce),
 * panier, commande, Mon Compte, fiches produit, et fiches recette (favoris +
 * paywall Memberships). Filtrable pour couvrir une page éditoriale qui
 * embarquerait un shortcode WooCommerce.
 *
 * @return bool
 */
function _180c_is_commerce_context() {
	$is_commerce = (
		( function_exists( 'is_woocommerce' ) && is_woocommerce() )
		|| ( function_exists( 'is_cart' ) && is_cart() )
		|| ( function_exists( 'is_checkout' ) && is_checkout() )
		|| ( function_exists( 'is_account_page' ) && is_account_page() )
		|| is_singular( 'product' )
		|| is_singular( 'recipe' )
	);

	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (bool) apply_filters( '180c/is_commerce_context', $is_commerce );
}

/**
 * Liste des handles de feuilles de style déchargées hors contexte commerce.
 *
 * WooCommerce (core + smallscreen + layout), WooCommerce Blocks, Memberships,
 * Stripe-blocks et un widget de login legacy : ~7 feuilles render-blocking
 * inutiles sur les pages éditoriales.
 *
 * Conservés par précaution (hors liste, jamais déchargés) :
 *   - shortcodes-indep (`sc-frontend-style`) : shortcodes utilisables dans le
 *     corps éditorial des articles/pages ;
 *   - favorites (`simple-favorites`) : le thème pilote son affichage sur les
 *     articles (inc/article.php, option `simplefavorites_display`).
 *
 * @return string[]
 */
function _180c_offcommerce_style_handles() {
	$handles = array(
		'woocommerce-general',
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'wc-blocks-style',
		'wc-memberships-frontend',
		'wc-stripe-blocks-checkout-style',
		'style_login_widget',
		// PayPal Payments — `ppcp-pwc-payment-method` est enqueue depuis
		// get_payment_method_script_handles() (registre des moyens de paiement
		// WC Blocks), donc sans aucune condition de page : sa feuille
		// gateway.css se retrouvait render-blocking sur la home. Le handle
		// jumeau `ppcp-local-apms-gateway` pointe le même fichier mais est,
		// lui, déjà gaté sur cart/checkout/order-pay par le plugin ; on le
		// liste par sécurité au cas où cette condition évoluerait.
		'ppcp-pwc-payment-method',
		'ppcp-local-apms-gateway',
	);

	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (array) apply_filters( '180c/offcommerce_dequeue_styles', $handles );
}

/**
 * Décharge les feuilles plugin superflues hors contexte commerce.
 *
 * Couvre les feuilles enqueue classiques (WooCommerce core, Memberships, widget
 * login) sur wp_enqueue_scripts. Garde-fou strict via _180c_is_commerce_context.
 *
 * @return void
 */
function _180c_dequeue_offcommerce_styles() {
	if ( _180c_is_commerce_context() ) {
		return;
	}
	foreach ( _180c_offcommerce_style_handles() as $handle ) {
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', '_180c_dequeue_offcommerce_styles', 100 );

/**
 * Liste des handles de scripts déchargés hors contexte commerce.
 *
 * WooCommerce enqueue inconditionnellement, dans le <head>, une pile de scripts
 * qui n'a aucun rôle sur une page éditoriale : blockUI (overlay des formulaires
 * panier), js-cookie, woocommerce.min.js (selects pays/état, retrait de coupon)
 * et — via le plugin Table Rate Shipping — un `frontend-checkout.min.js` chargé
 * jusque sur la home.
 *
 * L'enjeu n'est pas leur poids propre (ils sont déjà en `defer`) mais leur
 * dépendance à jQuery : celle-ci force `jquery.min.js` + `jquery-migrate.min.js`
 * en <script> BLOQUANT dans le <head>, soit 34 Ko et ~1 350 ms sur le chemin
 * critique mobile mesurés par PageSpeed. Retirer ces quatre handles rend jQuery
 * orphelin, et `_180c_maybe_dequeue_jquery()` peut alors le sortir aussi.
 *
 * Conservés volontairement (hors liste) :
 *   - `sourcebuster-js` / `wc-order-attribution` : attribution marketing de
 *     première visite, qui doit être captée quelle que soit la page d'entrée —
 *     et qui ne dépend pas de jQuery ;
 *   - `wc-add-to-cart` : les rails produits de la home l'utilisent (voir
 *     l'enqueue conditionnel dans inc/enqueue.php). Il dépend de jQuery, que la
 *     garde ci-dessous conservera donc automatiquement sur ces pages.
 *
 * @return string[]
 */
function _180c_offcommerce_script_handles() {
	$handles = array(
		'wc-jquery-blockui',
		'wc-js-cookie',
		'woocommerce',
		'woocommerce_shipping_table_rate_checkout',
	);

	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (array) apply_filters( '180c/offcommerce_dequeue_scripts', $handles );
}

/**
 * Indique si un script dépend, directement ou transitivement, d'un des handles
 * donnés.
 *
 * @param string   $handle  Handle à inspecter.
 * @param string[] $needles Handles recherchés dans l'arbre de dépendances.
 * @param array    $seen    Handles déjà visités (garde anti-cycle, usage interne).
 * @return bool
 */
function _180c_script_depends_on( $handle, array $needles, array &$seen = array() ) {
	if ( isset( $seen[ $handle ] ) ) {
		return false;
	}
	$seen[ $handle ] = true;

	$scripts = wp_scripts();
	if ( ! isset( $scripts->registered[ $handle ] ) ) {
		return false;
	}

	foreach ( (array) $scripts->registered[ $handle ]->deps as $dep ) {
		if ( in_array( $dep, $needles, true ) ) {
			return true;
		}
		if ( _180c_script_depends_on( $dep, $needles, $seen ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Décharge jQuery — mais seulement s'il ne reste plus personne pour s'en servir.
 *
 * Garde auto-suffisante, et non liste en dur : après le déchargement des scripts
 * WooCommerce ci-dessus, on inspecte ce qui reste réellement en file. jQuery
 * n'est retiré que si AUCUN script encore en file n'en dépend (directement ou
 * transitivement) et qu'aucun code inline ne lui est rattaché. Un plugin qui
 * enqueue un script jQuery sur une page éditoriale conserve donc jQuery sans
 * qu'il y ait quoi que ce soit à ajouter ici.
 *
 * Filet supplémentaire côté WordPress : `WP_Dependencies::all_deps()` résout les
 * dépendances à l'impression. Un script jQuery enqueue APRÈS ce passage (dans le
 * pied de page, par exemple) fera donc réapparaître jQuery de lui-même, en pied
 * de page et non plus dans le <head>.
 *
 * Reste hors de portée de la garde : un plugin qui imprimerait du `jQuery(...)`
 * en dur via `wp_footer` sans déclarer la moindre dépendance. D'où le filtre
 * `180c/dequeue_jquery` pour rétablir jQuery au cas par cas.
 *
 * @return void
 */
function _180c_maybe_dequeue_jquery() {
	$jquery  = array( 'jquery', 'jquery-core', 'jquery-migrate' );
	$scripts = wp_scripts();

	// Du code inline rattaché à jQuery suppose jQuery présent : on ne touche à rien.
	foreach ( $jquery as $handle ) {
		if ( $scripts->get_data( $handle, 'before' ) || $scripts->get_data( $handle, 'after' ) ) {
			return;
		}
	}

	foreach ( $scripts->queue as $handle ) {
		if ( in_array( $handle, $jquery, true ) ) {
			continue;
		}
		$seen = array();
		if ( _180c_script_depends_on( $handle, $jquery, $seen ) ) {
			return;
		}
	}

	/**
	 * Permet de conserver jQuery malgré l'absence de dépendance déclarée.
	 *
	 * @param bool $dequeue Décharger jQuery.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	if ( ! apply_filters( '180c/dequeue_jquery', true ) ) {
		return;
	}

	foreach ( $jquery as $handle ) {
		wp_dequeue_script( $handle );
	}
}

/**
 * Décharge les scripts plugin superflus hors contexte commerce, puis jQuery
 * s'il devient orphelin.
 *
 * Priorité 100, comme le pendant CSS : après tous les enqueue des plugins.
 *
 * @return void
 */
function _180c_dequeue_offcommerce_scripts() {
	if ( is_admin() || _180c_is_commerce_context() ) {
		return;
	}

	foreach ( _180c_offcommerce_script_handles() as $handle ) {
		wp_dequeue_script( $handle );
	}

	_180c_maybe_dequeue_jquery();
}
add_action( 'wp_enqueue_scripts', '_180c_dequeue_offcommerce_scripts', 100 );

/*
 * ------------------------------------------------------------------
 * Styles de l'éditeur de blocs sur les pages qui n'en contiennent pas
 * ------------------------------------------------------------------
 */

/**
 * Indique si la page courante rend du contenu Gutenberg.
 *
 * Décide par la présence réelle de blocs (`has_blocks()`) et non par une liste
 * blanche de templates : les articles de La Gazette SONT en blocs (vérifié sur
 * 12/12 d'un échantillon de production), une liste figée les aurait cassés au
 * premier oubli. En cas de doute, la fonction rend `true` — charger une feuille
 * inutile coûte 3,5 Ko gzip, ne pas la charger là où elle sert casse le rendu.
 *
 * @return bool
 */
function _180c_page_has_blocks() {
	// Admin, éditeur, REST : on ne décide jamais du chargement ici. Gutenberg
	// doit rester intact pour l'équipe éditoriale.
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return true;
	}

	$post_id = 0;

	if ( is_singular() ) {
		$post_id = (int) get_queried_object_id();
	} elseif ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
		// Sur la boutique, l'objet interrogé est le type de contenu `product`
		// et non la page : on va chercher la page boutique elle-même.
		$post_id = (int) wc_get_page_id( 'shop' );
	} elseif ( is_home() && ! is_front_page() ) {
		$post_id = (int) get_option( 'page_for_posts' );
	}

	$has_blocks = $post_id > 0 ? has_blocks( $post_id ) : false;

	/**
	 * Filtre la détection de contenu Gutenberg sur la page courante.
	 *
	 * Échappatoire pour un contexte non couvert (widget de blocs, shortcode
	 * rendant du contenu bloc d'un autre post, extension tierce).
	 *
	 * @param bool $has_blocks Résultat de la détection.
	 * @param int  $post_id    Contenu inspecté, 0 si aucun.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (bool) apply_filters( '180c/page_has_blocks', $has_blocks, $post_id );
}

/**
 * Décharge les styles de blocs sur les pages dépourvues de contenu Gutenberg.
 *
 * Le thème est full custom (Tailwind v4 CSS-first + BEM) : sur l'accueil, la
 * boutique, le panier et les fiches recette, `wp-block-library` (3 648 o) et
 * `global-styles` (14 095 o) sont émis en <style> inline sans qu'aucune règle
 * ne s'applique — mesuré : les seules occurrences de `wp-block-*` dans le HTML
 * de la home sont DANS ces feuilles, jamais dans le markup.
 *
 * Volontairement CONSERVÉ : `wp-img-auto-sizes-contain` (135 o). Malgré son
 * appartenance au même lot, cette feuille est fonctionnelle — elle corrige la
 * hauteur des images en `sizes="auto"`, que la mosaïque de couvertures utilise.
 * La retirer provoquerait un décalage de mise en page.
 *
 * @return void
 */
function _180c_dequeue_block_styles() {
	if ( _180c_page_has_blocks() ) {
		return;
	}

	/**
	 * Filtre les handles de styles de blocs déchargés hors contenu Gutenberg.
	 *
	 * @param string[] $handles Handles à décharger.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	$handles = (array) apply_filters( '180c/dequeue_block_styles', array( 'wp-block-library' ) );

	foreach ( $handles as $handle ) {
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', '_180c_dequeue_block_styles', 100 );

/**
 * Empêche la génération des styles globaux issus de theme.json.
 *
 * `global-styles` (14 095 o, la plus lourde du lot) ne peut pas être retirée
 * par `wp_dequeue_style()` : depuis WordPress 6.9, un thème classique charge
 * les assets de blocs à la demande et le cœur enfile cette feuille depuis
 * `wp_footer` priorité 1 avant de la remonter dans le <head>
 * (`wp_enqueue_global_styles()`, wp-includes/script-loader.php). Tout dequeue
 * posé sur `wp_enqueue_scripts` passe donc AVANT l'enqueue et ne capte rien —
 * constaté en local sous WP 7.0.2.
 *
 * On désenregistre donc les deux actions du cœur plutôt que de courir après le
 * handle. `template_redirect` est le premier hook où la requête principale est
 * résolue (donc où `has_blocks()` a un sens) et où les deux actions sont encore
 * en place.
 *
 * Le thème n'utilise aucune variable `--wp--preset--*` (vérifié sur l'ensemble
 * de src/css/ : une seule occurrence, dans un commentaire). Sur une page sans
 * blocs, cette feuille n'a donc strictement aucun consommateur.
 *
 * @return void
 */
function _180c_disable_global_styles() {
	if ( is_admin() || _180c_page_has_blocks() ) {
		return;
	}

	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
}
add_action( 'template_redirect', '_180c_disable_global_styles' );

/**
 * Supprime la balise <link> des feuilles ciblées au moment de l'impression.
 *
 * Filet de sécurité pour les feuilles ajoutées via enqueue_block_assets après
 * wp_enqueue_scripts (WooCommerce Blocks `wc-blocks-style`, Stripe
 * `wc-stripe-blocks-checkout-style`), que wp_dequeue_style ne capte pas. Agit au
 * rendu du tag, donc indépendamment du moment d'enqueue.
 *
 * @param string $tag    Balise <link> complète.
 * @param string $handle Handle de la feuille.
 * @return string Tag d'origine, ou chaîne vide pour supprimer la feuille.
 */
function _180c_filter_offcommerce_style_tag( $tag, $handle ) {
	if ( _180c_is_commerce_context() ) {
		return $tag;
	}
	if ( in_array( $handle, _180c_offcommerce_style_handles(), true ) ) {
		return '';
	}
	return $tag;
}
add_filter( 'style_loader_tag', '_180c_filter_offcommerce_style_tag', 10, 2 );
