<?php
/**
 * Page Abonnement 180°C — helpers & hooks WooCommerce.
 *
 * Logique `_180c_subscribe_*` de la page d'abonnement numérique :
 *  - lecture des offres ACF + résolution prix/URL en direct depuis WooCommerce ;
 *  - garantie d'un seul abonnement simultané dans le panier ;
 *  - redirection vers le checkout à l'ajout d'un abonnement ;
 *  - détection du nom de l'abonnement actif (cas « déjà abonné ») ;
 *  - défauts SEO (title, description, image OG) câblés sur le module natif.
 *
 * Template associé : page-abonnement.php + groupe ACF group_180c_page_abonnement.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/*
 * 1. Helpers de contexte
 */

/**
 * La requête courante rend-elle la page Abonnement ?
 *
 * @return bool
 */
function _180c_is_subscribe_page() {
	return is_page_template( 'page-abonnement.php' );
}

/**
 * URL de la page Centre d'aide (slug conventionnel du thème).
 *
 * @return string
 */
function _180c_subscribe_help_url() {
	return home_url( '/centre-daide/' );
}

/**
 * Un produit est-il un abonnement WooCommerce Subscriptions ?
 *
 * Reste robuste si WC Subscriptions est absent (fallback sur le type produit).
 *
 * @param WC_Product|mixed $product Produit à tester.
 * @return bool
 */
function _180c_subscribe_is_subscription_product( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	if ( class_exists( 'WC_Subscriptions_Product' ) ) {
		return (bool) WC_Subscriptions_Product::is_subscription( $product );
	}

	return in_array( $product->get_type(), array( 'subscription', 'variable-subscription' ), true );
}

/*
 * 2. Offres & ajout au panier
 */

/**
 * URL d'ajout au panier d'un produit, base checkout.
 *
 * @param int $product_id ID du produit WooCommerce.
 * @return string URL prête à échapper, ou chaîne vide si WooCommerce absent.
 */
function _180c_subscribe_add_to_cart_url( $product_id ) {
	if ( ! function_exists( 'wc_get_checkout_url' ) ) {
		return '';
	}

	return add_query_arg( 'add-to-cart', (int) $product_id, wc_get_checkout_url() );
}

/**
 * ID du produit d'abonnement mensuel (« 100% bien manger », 2,99 €/mois).
 *
 * @return int
 */
function _180c_subscribe_monthly_product_id() {
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (int) apply_filters( '180c/monthly_subscription_id', 13121518 );
}

/**
 * URL d'ajout au panier de l'abonnement mensuel, avec atterrissage sur /panier/.
 *
 * Pré-ajoute l'abonnement mensuel (2,99 €/mois) puis renvoie vers le panier. Le
 * marqueur `_180c_add_dest=cart` neutralise, pour ce seul parcours, la
 * redirection checkout des abonnements (cf. _180c_subscribe_force_cart_redirect),
 * propre au tunnel paywall. URL construite à l'exécution via `wc_get_cart_url()` :
 * agnostique à l'environnement (local/prod).
 *
 * @return string URL prête à échapper, ou chaîne vide si WooCommerce absent.
 */
function _180c_subscribe_monthly_cart_url() {
	if ( ! function_exists( 'wc_get_cart_url' ) ) {
		return '';
	}

	return add_query_arg(
		array(
			'add-to-cart'    => _180c_subscribe_monthly_product_id(),
			'_180c_add_dest' => 'cart',
		),
		wc_get_cart_url()
	);
}

/**
 * Pointe les CTA « Je m'abonne » des paywalls vers le panier (abo mensuel pré-ajouté).
 *
 * Branché sur le filtre `180c/paywall_subscribe_url` (consommé par les paywalls
 * single recette & single article) : le CTA pré-ajoute l'abonnement mensuel
 * 2,99 €/mois et renvoie vers /panier/, court-circuit de la landing /abonnement/.
 *
 * Limité au rendu front-end : les contextes REST (apps mobiles) et flux RSS
 * conservent l'URL lisible de la landing /abonnement/ (sans add-to-cart).
 *
 * @param string $url URL d'abonnement par défaut du paywall.
 * @return string
 */
function _180c_paywall_subscribe_url_to_cart( $url ) {
	if ( ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) || is_feed() ) {
		return $url;
	}

	$cart = _180c_subscribe_monthly_cart_url();
	return $cart ? $cart : $url;
}
// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespace de hooks 180c/ imposé par CLAUDE.md.
add_filter( '180c/paywall_subscribe_url', '_180c_paywall_subscribe_url_to_cart' );

/**
 * Force l'atterrissage panier (silencieux) pour les ajouts marqués `_180c_add_dest=cart`.
 *
 * Priorité 30 : passe APRÈS _180c_subscribe_redirect_to_checkout (priorité 20)
 * pour ramener vers /panier/ les ajouts d'abonnement issus du parcours paywall,
 * sans toucher au parcours « page Abonnement » (qui mène au checkout).
 *
 * Supprime aussi le message natif « … a été ajouté à votre panier » : il est
 * généré juste avant ce filtre (WC_Form_Handler::add_to_cart_handler_simple →
 * wc_add_to_cart_message), donc on vide ici les notices « success » de la
 * requête. C'est le seul point fiable : après la redirection, le marqueur a
 * disparu et la page panier ne peut plus distinguer ce parcours.
 *
 * @param string $url URL de redirection courante.
 * @return string
 */
function _180c_subscribe_force_cart_redirect( $url ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'un marqueur GET du parcours add-to-cart natif WooCommerce, aucune écriture.
	$dest = isset( $_GET['_180c_add_dest'] ) ? sanitize_key( wp_unslash( $_GET['_180c_add_dest'] ) ) : '';
	if ( 'cart' !== $dest || ! function_exists( 'wc_get_cart_url' ) ) {
		return $url;
	}

	// Atterrissage panier sans le bandeau « ajouté au panier ».
	if ( function_exists( 'wc_get_notices' ) && function_exists( 'wc_set_notices' ) ) {
		$notices = wc_get_notices();
		unset( $notices['success'] );
		wc_set_notices( $notices );
	}

	return wc_get_cart_url();
}
add_filter( 'woocommerce_add_to_cart_redirect', '_180c_subscribe_force_cart_redirect', 30 );

/**
 * Redirige (301) les fiches produit des abonnements numériques vers /abonnement/.
 *
 * Les produits d'abonnement (mensuel #13121518, annuel #13121519) ne doivent pas
 * exposer leur fiche produit WooCommerce : la page éditoriale /abonnement/ est le
 * point d'entrée unique. L'ajout au panier (`?add-to-cart=ID`) n'est pas affecté
 * car il ne charge pas la fiche produit.
 *
 * @return void
 */
function _180c_subscribe_redirect_product_pages() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	/**
	 * IDs des produits d'abonnement à rediriger vers la page /abonnement/.
	 *
	 * @param int[] $ids Liste d'IDs de produits WooCommerce.
	 */
	$ids = (array) apply_filters(
		'180c/subscription_products_redirect_to_abonnement', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
		array( _180c_subscribe_monthly_product_id(), 13121519 )
	);

	if ( ! in_array( (int) get_queried_object_id(), array_map( 'intval', $ids ), true ) ) {
		return;
	}

	wp_safe_redirect( apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) ), 301 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	exit;
}
add_action( 'template_redirect', '_180c_subscribe_redirect_product_pages' );

/**
 * Liste des offres d'abonnement prêtes à itérer dans le template.
 *
 * Lit le repeater ACF `_180c_abo_offres` et résout pour chaque offre le produit
 * WooCommerce, son prix (HTML live), l'URL d'ajout au panier et son statut
 * « mise en avant ». Les offres dont le produit est introuvable sont ignorées.
 *
 * @return array<int, array{
 *     product: WC_Product,
 *     product_id: int,
 *     title: string,
 *     subtitle: string,
 *     slug: string,
 *     description: string,
 *     cta_label: string,
 *     price_html: string,
 *     add_to_cart_url: string,
 *     is_featured: bool
 * }>
 */
function _180c_subscribe_get_offers() {
	if ( ! function_exists( 'have_rows' ) || ! function_exists( 'wc_get_product' ) ) {
		return array();
	}

	if ( ! have_rows( '_180c_abo_offres' ) ) {
		return array();
	}

	$offers = array();

	while ( have_rows( '_180c_abo_offres' ) ) {
		the_row();

		$product_field = get_sub_field( 'offre_produit' );
		$product_id    = 0;

		if ( $product_field instanceof WP_Post ) {
			$product_id = (int) $product_field->ID;
		} elseif ( is_object( $product_field ) && isset( $product_field->ID ) ) {
			$product_id = (int) $product_field->ID;
		} elseif ( is_numeric( $product_field ) ) {
			$product_id = (int) $product_field;
		}

		if ( ! $product_id ) {
			continue;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		$cta_label = (string) get_sub_field( 'offre_cta_label' );

		$offers[] = array(
			'product'         => $product,
			'product_id'      => $product_id,
			// Slug produit : identifiant d'offre stable et non nominatif, utilisé
			// comme dimension `offer` par la mesure Umami (cf inc/analytics/umami.php).
			'slug'            => (string) $product->get_slug(),
			'title'           => (string) get_sub_field( 'offre_titre' ),
			'subtitle'        => (string) get_sub_field( 'offre_sous_titre' ),
			'description'     => (string) get_sub_field( 'offre_description' ),
			'cta_label'       => '' !== $cta_label ? $cta_label : __( "S'abonner", '180c' ),
			'price_html'      => (string) $product->get_price_html(),
			'add_to_cart_url' => _180c_subscribe_add_to_cart_url( $product_id ),
			'is_featured'     => (bool) get_sub_field( 'offre_mise_en_avant' ),
		);
	}

	return $offers;
}

/**
 * Premier visuel du carousel (utilisé comme image OG / candidat LCP).
 *
 * @return int ID de la pièce jointe, ou 0.
 */
function _180c_subscribe_first_carousel_image_id() {
	if ( ! function_exists( 'get_field' ) ) {
		return 0;
	}

	$carousel = get_field( '_180c_abo_carousel' );
	if ( empty( $carousel ) || ! is_array( $carousel ) ) {
		return 0;
	}

	$first = $carousel[0];
	$image = isset( $first['slide_image'] ) ? $first['slide_image'] : null;

	if ( is_array( $image ) && ! empty( $image['ID'] ) ) {
		return (int) $image['ID'];
	}
	if ( is_numeric( $image ) ) {
		return (int) $image;
	}

	return 0;
}

/*
 * 3. Règles panier WooCommerce (un seul abonnement, redirection checkout)
 */

/**
 * Un flux natif WooCommerce Subscriptions est-il en cours ?
 *
 * Couvre le paiement d'un renouvellement (`order-pay` d'une commande de
 * renouvellement échouée), le réabonnement (`resubscribe`), le changement
 * d'offre (`switch`) et le paiement initial. Ces parcours ne sont PAS des
 * achats neufs : ils régularisent ou font évoluer un abonnement existant, et
 * doivent traverser les garde-fous « un seul abonnement » sans être bloqués ni
 * tronqués.
 *
 * Trois signaux, du plus fiable au plus permissif :
 *
 * 1. `$cart_item_data` — signal d'AUTORITÉ. `WCS_Cart_Renewal::setup_cart()`
 *    vide le panier PUIS appelle `woocommerce_add_to_cart_validation` en lui
 *    passant `array( $this->cart_item_key => … )` en 6e argument. À cet instant
 *    le panier est vide : les helpers `wcs_cart_contains_*()` renvoient tous
 *    `false` et ne peuvent donc PAS servir de détection sur la 1re ligne. Les
 *    clés sont celles déclarées par les classes WCS (`$cart_item_key`).
 * 2. Helpers `wcs_cart_contains_*()` — fiables une fois qu'au moins une ligne du
 *    flux est au panier (2e ligne d'une commande multi-articles, ou hook
 *    `woocommerce_add_to_cart` qui s'exécute après l'ajout).
 * 3. Paramètres de requête — dernier recours, seul signal disponible pour le
 *    switch, qui emprunte l'`add_to_cart` natif depuis `?switch-subscription=`
 *    sans passer par `setup_cart()`.
 *
 * @param array $cart_item_data Données d'item transmises par WCS (6e argument
 *                              de `woocommerce_add_to_cart_validation`).
 * @return bool True si l'ajout relève d'un flux natif WCS.
 */
function _180c_subscribe_is_wcs_native_flow( $cart_item_data = array() ) {
	// 1. Clé de flux posée par WCS dans les données d'item.
	if ( is_array( $cart_item_data ) ) {
		$flow_keys = array(
			'subscription_renewal',
			'subscription_resubscribe',
			'subscription_switch',
			'subscription_initial_payment',
		);
		foreach ( $flow_keys as $flow_key ) {
			if ( isset( $cart_item_data[ $flow_key ] ) ) {
				return true;
			}
		}
	}

	// 2. Le panier porte déjà le flux.
	if ( function_exists( 'wcs_cart_contains_renewal' ) && wcs_cart_contains_renewal() ) {
		return true;
	}
	if ( function_exists( 'wcs_cart_contains_resubscribe' ) && wcs_cart_contains_resubscribe() ) {
		return true;
	}
	if ( function_exists( 'wcs_cart_contains_switches' ) && wcs_cart_contains_switches() ) {
		return true;
	}

	// 3. Fallback requête (switch depuis la fiche produit, order-pay). Lecture
	// seule de paramètres natifs WooCommerce Subscriptions, aucune écriture.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['switch-subscription'] ) || isset( $_GET['subscription_renewal'] ) || isset( $_GET['resubscribe'] ) ) {
		return true;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	return false;
}

/**
 * Garantit un seul abonnement dans le panier.
 *
 * À l'ajout d'un produit d'abonnement, retire tous les AUTRES produits
 * d'abonnement déjà présents (un seul abonnement simultané possible).
 * Les produits physiques/numériques non-abonnement ne sont pas touchés.
 *
 * Les flux natifs WCS sont exclus : une commande de renouvellement ou un switch
 * peut légitimement porter plusieurs lignes d'abonnement, les élaguer
 * amputerait le montant à régler.
 *
 * @param string $cart_item_key Clé de l'item ajouté.
 * @param int    $product_id    ID du produit ajouté.
 * @return void
 */
function _180c_subscribe_enforce_single_subscription( $cart_item_key, $product_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	// Renouvellement / resubscribe / switch : le panier est reconstitué par WCS,
	// on n'y touche pas. Ici l'article est déjà au panier → les helpers
	// `wcs_cart_contains_*()` sont fiables.
	if ( _180c_subscribe_is_wcs_native_flow() ) {
		return;
	}

	$added = wc_get_product( (int) $product_id );
	if ( ! _180c_subscribe_is_subscription_product( $added ) ) {
		return;
	}

	foreach ( WC()->cart->get_cart() as $key => $item ) {
		if ( $key === $cart_item_key || empty( $item['data'] ) ) {
			continue;
		}
		if ( _180c_subscribe_is_subscription_product( $item['data'] ) ) {
			WC()->cart->remove_cart_item( $key );
		}
	}
}
add_action( 'woocommerce_add_to_cart', '_180c_subscribe_enforce_single_subscription', 10, 2 );

/**
 * Garde-fou serveur : empêche un abonné en cours d'ajouter un abonnement.
 *
 * Pendant du masquage UI (cross-sell panier, CTA « Je m'abonne ») : même si
 * l'ajout est déclenché par un lien direct `?add-to-cart=ID` ou une requête
 * forgée qui contourne l'interface, un abonné dont l'abonnement est en cours ne
 * peut pas en re-souscrire un second. Bloque à la validation d'ajout au panier
 * et affiche un message renvoyant vers Mon compte.
 *
 * Le parcours CADEAU (produit `ABO-GIFT-ANN`, endpoint `180c/v1/gift/start`) est
 * explicitement exclu : un abonné DOIT pouvoir OFFRIR un abonnement. On teste le
 * produit cadeau AVANT le test « produit d'abonnement » car le cadeau peut être
 * lui-même de type abonnement.
 *
 * Les flux natifs WCS (paiement d'un renouvellement échoué, resubscribe, switch)
 * sont eux aussi exclus, EN PREMIER : ce ne sont pas des achats neufs mais des
 * régularisations d'un abonnement existant. Sans cette exclusion, un abonné dont
 * le renouvellement Stripe a échoué (abonnement passé `on-hold`, donc « en
 * cours » au sens du prédicat) voyait son article refusé par ce garde, le panier
 * reconstitué par `WCS_Cart_Renewal::setup_cart()` restait vide, et le checkout
 * vide le renvoyait sur `/panier/` avec la notice « déjà abonné » — sans aucun
 * moyen de régler.
 *
 * @param bool  $passed         Résultat de validation courant.
 * @param int   $product_id     ID du produit en cours d'ajout.
 * @param int   $quantity       Quantité ajoutée (non utilisé).
 * @param int   $variation_id   ID de variation (non utilisé).
 * @param array $variations     Attributs de variation (non utilisé).
 * @param array $cart_item_data Données d'item ; porte la clé de flux WCS.
 * @return bool False pour bloquer l'ajout, sinon la valeur reçue.
 */
function _180c_subscribe_block_add_for_subscriber( $passed, $product_id, $quantity = 1, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
	if ( ! $passed ) {
		return $passed;
	}

	// Flux natif WCS : régularisation d'un abonnement existant, jamais bloquée.
	if ( _180c_subscribe_is_wcs_native_flow( $cart_item_data ) ) {
		return $passed;
	}

	// Exclusion du produit cadeau : offrir un abonnement reste permis à un abonné.
	if ( function_exists( '_180c_gift_is_gift_product' ) && _180c_gift_is_gift_product( $product_id ) ) {
		return $passed;
	}

	if ( ! _180c_subscribe_is_subscription_product( wc_get_product( (int) $product_id ) ) ) {
		return $passed;
	}

	if ( function_exists( '_180c_user_has_ongoing_subscription' ) && _180c_user_has_ongoing_subscription() ) {
		wc_add_notice(
			sprintf(
				/* translators: %s: URL de la page Mon compte. */
				__( 'Vous êtes déjà abonné·e. Retrouvez votre abonnement dans <a href="%s">votre compte</a>.', '180c' ),
				esc_url( wc_get_page_permalink( 'myaccount' ) )
			),
			'error'
		);
		return false;
	}

	return $passed;
}
// 6 arguments : le 6e (`$cart_item_data`) porte la clé de flux WCS, seul signal
// disponible quand le panier vient d'être vidé par `setup_cart()`.
add_filter( 'woocommerce_add_to_cart_validation', '_180c_subscribe_block_add_for_subscriber', 10, 6 );

/**
 * Redirige vers le checkout à l'ajout d'un abonnement.
 *
 * Priorité 20 : passe APRÈS la surcharge cart-first (inc/woo/overrides.php,
 * priorité 10 par défaut) afin que les produits d'abonnement aillent
 * directement au tunnel de paiement, conformément au parcours d'abonnement.
 * Les produits non-abonnement conservent le comportement
 * cart-first standard.
 *
 * @param string           $url            URL de redirection courante.
 * @param WC_Product|false $adding_to_cart Produit ajouté (WC ≥ 3.0), ou false.
 * @return string
 */
function _180c_subscribe_redirect_to_checkout( $url, $adding_to_cart = null ) {
	$product = $adding_to_cart instanceof WC_Product ? $adding_to_cart : null;

	if ( null === $product && isset( $_GET['add-to-cart'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture du paramètre natif WooCommerce d'ajout au panier (GET), aucune écriture.
		$pid = absint( wp_unslash( $_GET['add-to-cart'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $pid ) {
			$product = wc_get_product( $pid );
		}
	}

	if ( _180c_subscribe_is_subscription_product( $product ) && function_exists( 'wc_get_checkout_url' ) ) {
		return wc_get_checkout_url();
	}

	return $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', '_180c_subscribe_redirect_to_checkout', 20, 2 );

/*
 * 4. Détection « déjà abonné »
 */

/**
 * Nom du produit de l'abonnement actif de l'utilisateur courant.
 *
 * Ne renvoie un nom que si l'utilisateur a effectivement accès aux recettes
 * (cas « déjà abonné »). Parcourt les abonnements WC Subscriptions, retient le
 * premier au statut actif / résiliation programmée, et renvoie le nom de son
 * premier article. Chaîne vide si introuvable ou plugin absent.
 *
 * @return string
 */
function _180c_user_active_subscription_name() {
	if ( function_exists( '_180c_user_has_recipe_access' ) && ! _180c_user_has_recipe_access() ) {
		return '';
	}

	$user_id = get_current_user_id();
	if ( $user_id <= 0 || ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return '';
	}

	$subscriptions = wcs_get_users_subscriptions( $user_id );
	if ( empty( $subscriptions ) ) {
		return '';
	}

	foreach ( $subscriptions as $subscription ) {
		if ( ! $subscription instanceof WC_Subscription ) {
			continue;
		}
		if ( ! $subscription->has_status( array( 'active', 'pending-cancel' ) ) ) {
			continue;
		}
		foreach ( $subscription->get_items() as $item ) {
			$name = trim( (string) $item->get_name() );
			if ( '' !== $name ) {
				return $name;
			}
		}
	}

	return '';
}

/**
 * Retire du nom d'un produit d'abonnement le mot « abonnement » initial.
 *
 * Les produits sont nommés « Abonnement digital 100% bien manger (mensuel) ».
 * Injecté tel quel dans une phrase qui contient déjà le mot (« Vous avez déjà
 * l'abonnement %s. »), il produit un doublon. Cette fonction ne sert donc qu'aux
 * libellés insérés dans une telle phrase : elle rogne l'article et le mot
 * « abonnement(s) » de tête, en conservant le reste intact.
 *
 * Renvoie une chaîne vide si le retrait ne laisse rien (produit nommé
 * « Abonnement » tout court) : l'appelant retombe alors sur sa formulation
 * générique, plutôt que d'afficher « l'abonnement Abonnement ».
 *
 * @param string $name Nom du produit d'abonnement.
 * @return string Nom sans le mot « abonnement » de tête, ou chaîne vide.
 */
function _180c_subscription_name_without_prefix( $name ) {
	$name = trim( (string) $name );

	// « Abonnement », « L'abonnement », « Un abonnement », au singulier comme au
	// pluriel. Apostrophe droite ou typographique. Le mot doit être isolé : un
	// nom du type « Abonnement-cadeau » reste intact.
	$stripped = preg_replace( '/^(?:(?:l|d|un|une|le|la|les|des)[\'’\s]+)?abonnements?(?:\s+|$)/iu', '', $name );

	if ( ! is_string( $stripped ) ) {
		return $name;
	}

	return trim( $stripped );
}

/*
 * 5. SEO — défauts câblés sur le module natif (inc/seo/meta-tags.php)
 *
 * Le groupe ACF « SEO » (seo_title, seo_description, og_image_override,
 * no_index) a été supprimé : il n'y a plus de saisie admin à respecter, et
 * donc plus de test « seulement si le champ est vide ». Ce que fournit ce
 * bloc est la valeur de la page, pas un défaut.
 */

/*
 * Le <title> de la page abonnement n'est plus posé ici. Il est résolu par la
 * table centrale (inc/seo/title-map.php, clé « abonnement ») et assemblé par
 * _180c_build_title() : « Abonnement — 1 500 recettes, sans engagement · 180°C ».
 *
 * L'ancien défaut « S'abonner » passait par la mécanique $parts de WordPress,
 * qui rajoute elle-même le nom du site : incompatible avec la règle « une seule
 * occurrence de la marque ».
 */

/**
 * Meta description de la page abonnement.
 */
add_filter(
	'180c/seo_description', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	function ( $description ) {
		if ( ! _180c_is_subscribe_page() ) {
			return $description;
		}
		return __( 'Abonnez-vous à 180°C et accédez en illimité à toutes les recettes du magazine sur le web et les applications. Sans engagement, résiliable à tout moment.', '180c' );
	}
);

/**
 * Image OG par défaut : premier visuel du carousel, si aucun override/featured.
 */
add_filter(
	'180c/og_image_id', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	function ( $attachment_id ) {
		if ( $attachment_id || ! _180c_is_subscribe_page() ) {
			return $attachment_id;
		}
		$first = _180c_subscribe_first_carousel_image_id();
		return $first ? $first : $attachment_id;
	}
);

/*
 * 6. Schema.org — Product + Offer par offre (sans avis)
 */

/**
 * Rend le JSON-LD des offres d'abonnement (Product + Offer).
 *
 * Un nœud Product par offre, avec son Offer (prix live WooCommerce, devise
 * EUR). 180°C n'affiche aucun avis : ni `aggregateRating` ni `review`. Appelé
 * depuis page-abonnement.php. Ne rend rien en l'absence d'offre.
 *
 * @return void
 */
function _180c_subscribe_render_jsonld() {
	$offers = _180c_subscribe_get_offers();
	if ( empty( $offers ) ) {
		return;
	}

	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
	$page_url = (string) get_permalink();
	$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
	$nodes    = array();

	foreach ( $offers as $offer ) {
		$product = $offer['product'];
		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		$offer_node = array(
			'@type'         => 'Offer',
			'priceCurrency' => $currency,
			'availability'  => 'https://schema.org/InStock',
			'url'           => $page_url,
		);

		$price = $product->get_price();
		if ( '' !== $price && null !== $price && function_exists( 'wc_format_decimal' ) ) {
			$offer_node['price'] = (string) wc_format_decimal( $price, $decimals );
		}

		$description = trim( wp_strip_all_tags( (string) $offer['description'] ) );
		if ( '' === $description ) {
			$description = trim( wp_strip_all_tags( (string) $product->get_short_description() ) );
		}

		$node = array(
			'@type'  => 'Product',
			'name'   => $product->get_name(),
			'offers' => $offer_node,
		);
		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$nodes[] = $node;
	}

	if ( empty( $nodes ) ) {
		return;
	}

	$graph = array(
		'@context' => 'https://schema.org',
		'@graph'   => $nodes,
	);

	$json = wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	if ( false === $json ) {
		return;
	}

	echo '<script type="application/ld+json">' . "\n";
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD encodé par wp_json_encode().
	echo $json;
	echo "\n" . '</script>' . "\n";
}
