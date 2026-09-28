<?php
/**
 * Surcharge du tunnel de commande WooCommerce — 180°C.
 *
 * Logique `_180c_*` du checkout : détection de la nature du panier,
 * noindex des pages transactionnelles, et (étapes suivantes) simplification
 * des champs, récap différencié, microcopy, politique de compte.
 *
 * On surcharge WooCommerce sans toucher au moteur de paiement, aux passerelles
 * ni à l'update AJAX natif du récapitulatif.
 *
 * Assets : pas d'enqueue dédié. Le CSS (`src/css/components/checkout.css`) est
 * importé dans `src/css/main.css` ; le JS (`src/js/modules/checkout.js`) est
 * chargé en import() dynamique depuis `src/js/main.js`, gardé par la présence de
 * `form.checkout`. Le noindex ci-dessous reste la seule logique PHP côté assets.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Détermine la nature du panier courant.
 *
 * Un article est « numérique » s'il s'agit d'un abonnement
 * (WC_Subscriptions_Product::is_subscription) ou d'un produit qui ne nécessite
 * pas de livraison (virtuel / téléchargeable). Sinon il est « physique ».
 *
 * Utilisé pour le récap différencié, la mention « résiliable à tout moment »
 * et la politique de compte (compte requis si abonnement).
 *
 * @return string 'digital' | 'physical' | 'mixed' | 'empty'.
 */
function _180c_checkout_cart_type() {
	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		return 'empty';
	}

	$has_physical = false;
	$has_digital  = false;

	foreach ( WC()->cart->get_cart() as $item ) {
		if ( empty( $item['data'] ) || ! is_a( $item['data'], 'WC_Product' ) ) {
			continue;
		}

		$product = $item['data'];
		$is_sub  = class_exists( 'WC_Subscriptions_Product' )
			&& WC_Subscriptions_Product::is_subscription( $product );

		if ( $is_sub || ! $product->needs_shipping() ) {
			$has_digital = true;
		} else {
			$has_physical = true;
		}
	}

	if ( $has_physical && $has_digital ) {
		return 'mixed';
	}

	return $has_physical ? 'physical' : 'digital';
}

/**
 * Réorganise les hooks natifs du checkout pour le layout empilé en 3 cards.
 *
 * 1. Détache le bloc paiement (`woocommerce_checkout_payment`, prio 20) de
 *    l'action `woocommerce_checkout_order_review` : `#order_review` ne rend
 *    plus que le tableau récap (Card 1 « Votre commande »). Le paiement est
 *    re-rendu explicitement dans la Card 3 par `form-checkout.php` via
 *    `woocommerce_checkout_payment()`. L'update AJAX reste intact (fragments
 *    CSS `.woocommerce-checkout-review-order-table` / `.woocommerce-checkout-payment`,
 *    indépendants de la position DOM).
 *
 * 2. Masque l'accordéon coupon (« Vous avez un code ? ») en retirant le
 *    formulaire coupon natif : pas de promo au checkout dans le tunnel 180°C.
 *
 * Hooké sur `wp_loaded` (après l'enregistrement des hooks de template par
 * WooCommerce, à `plugins_loaded`) pour garantir que les `remove_action`
 * ciblent des callbacks déjà accrochés.
 *
 * @return void
 */
add_action(
	'wp_loaded',
	function () {
		remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

		// Détache le formulaire de connexion natif du haut de page : il est
		// re-rendu explicitement par form-checkout.php juste au-dessus de la
		// section « Vos informations » (les notices, elles, restent en haut via
		// woocommerce_output_all_notices, toujours accroché à ce hook).
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );

		// Masque la mention « Vos données personnelles seront utilisées… »
		// (texte de politique de confidentialité natif WC) sous le bouton de
		// paiement. La case Conditions générales (si activée) reste intacte.
		remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
	}
);

/**
 * Supprime le message « … a été ajouté à votre panier » sur la page commande.
 *
 * Le récapitulatif de commande affiche déjà l'intégralité du panier ; la notice
 * de confirmation d'ajout (type `success`), empilée en haut du tunnel, fait
 * doublon. On retire uniquement les notices `success` présentes au chargement
 * initial de la page commande — les erreurs de validation restent intactes, et
 * les retours de coupon (générés en AJAX pendant le checkout) ne passent pas par
 * ce hook. `is_wc_endpoint_url()` exclut l'endpoint order-received (confirmation).
 *
 * @return void
 */
add_action(
	'template_redirect',
	function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
			return;
		}
		if ( ! function_exists( 'wc_get_notices' ) || ! function_exists( 'wc_set_notices' ) ) {
			return;
		}

		$notices = wc_get_notices();
		if ( empty( $notices['success'] ) ) {
			return;
		}

		unset( $notices['success'] );
		wc_set_notices( $notices );
	}
);

/*
 * Désindexation de la page commande : plus rien ici.
 *
 * Ce fichier portait un filtre `wp_robots` qui posait `noindex` + `nofollow`
 * sur `is_checkout()`. Il alimentait la balise robots du CORE, laquelle n'est
 * plus rendue depuis l'unification (voir `inc/seo/meta-tags.php`, section 4) :
 * le filtre était donc devenu du code mort.
 *
 * La couverture est intégralement reprise par
 * `_180c_seo_noindex_transactional()` (`inc/seo/meta-tags.php`), branchée sur
 * `180c/seo_robots`, qui traite `is_cart() || is_checkout() || is_account_page()`
 * — `is_checkout()` couvrant la page commande ET l'endpoint `order-received`.
 * Aucun trou de couverture entre les deux.
 *
 * Le `nofollow` est abandonné au profit de `follow`, décision produit du
 * 2026-07-31 : c'est la convention du module SEO, et sur une page déjà
 * `noindex` un `nofollow` n'ajoute rien tout en coupant la circulation du lien
 * vers le reste du site.
 */

/**
 * Simplifie les champs du checkout : retrait société + complément d'adresse,
 * autocomplete corrects sur les champs conservés (téléphone conservé).
 *
 * Priorité 9999 : passe APRÈS WooCommerce Checkout Field Editor (prio 1000) le
 * cas échéant, pour garantir la disparition de `company`/`address_2` quelle que
 * soit sa configuration.
 *
 * @param array $fields Champs du checkout (groupes billing/shipping/...).
 * @return array Champs filtrés.
 */
add_filter(
	'woocommerce_checkout_fields',
	function ( $fields ) {
		// Retrait société + complément d'adresse (billing ET shipping).
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			unset(
				$fields[ $group ][ $group . '_company' ],
				$fields[ $group ][ $group . '_address_2' ]
			);
		}

		// Autocomplete corrects sur les champs de facturation conservés.
		$autocomplete = array(
			'billing_email'      => 'email',
			'billing_first_name' => 'given-name',
			'billing_last_name'  => 'family-name',
			'billing_phone'      => 'tel',
			'billing_address_1'  => 'address-line1',
			'billing_postcode'   => 'postal-code',
			'billing_country'    => 'country',
		);

		foreach ( $autocomplete as $key => $value ) {
			if ( isset( $fields['billing'][ $key ] ) ) {
				$fields['billing'][ $key ]['autocomplete'] = $value;
			}
		}

		// Microcopy ton 180°C : libellés sur les champs conservés. Les hints
		// (descriptions sous les champs) sont retirés pour alléger le formulaire.
		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['label']       = __( 'Adresse e-mail', '180c' );
			$fields['billing']['billing_email']['description'] = '';
		}
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['label']       = __( 'Téléphone', '180c' );
			$fields['billing']['billing_phone']['description'] = '';
		}

		/*
		 * Commande 100 % numérique (abonnement / produit virtuel) : aucune
		 * livraison physique → on retire toute l'adresse postale et le
		 * téléphone. Ne restent que nom, prénom et e-mail (strict nécessaire à
		 * la facturation + la confirmation). La section livraison entière est
		 * vidée ; côté template, elle n'est de toute façon pas rendue
		 * (`needs_shipping_address()` faux) → pas de case « Expédier à une
		 * adresse différente ? » ni de flash de contenu. Filtre PHP (pas de JS)
		 * pour un rendu serveur stable.
		 */
		if ( WC()->cart && ! WC()->cart->needs_shipping() ) {
			unset(
				$fields['billing']['billing_country'],
				$fields['billing']['billing_address_1'],
				$fields['billing']['billing_postcode'],
				$fields['billing']['billing_city'],
				$fields['billing']['billing_state'],
				$fields['billing']['billing_phone']
			);

			$fields['shipping'] = array();
		}

		return $fields;
	},
	9999
);

/**
 * Politique de compte : invité autorisé pour un panier 100 % physique,
 * compte requis dès qu'un abonnement est présent (nécessaire à l'accès au
 * contenu — WC Subscriptions force déjà le compte ; ce filtre rend la règle
 * explicite et homogène). La création de compte côté physique reste gérée
 * nativement (optionnelle, non forcée).
 *
 * @return bool True si un compte est requis (panier non 100 % physique).
 */
add_filter(
	'woocommerce_checkout_registration_required',
	function () {
		return 'physical' !== _180c_checkout_cart_type();
	}
);

/**
 * Libellé du bouton de paiement. Le montant (« Payer 39,00 € ») est ajouté
 * côté JS (étape 5) en lisant le total du récap — pas en PHP, car il évolue
 * avec l'update AJAX.
 *
 * @return string
 */
add_filter(
	'woocommerce_order_button_text',
	function () {
		return __( 'Payer', '180c' );
	}
);

/**
 * Indique si une commande contient au moins un abonnement.
 *
 * Utilisé par la page de confirmation (thankyou) pour différencier le message
 * selon le contenu de la commande (le panier étant vidé à ce stade).
 *
 * @param WC_Order $order Commande.
 * @return bool
 */
function _180c_order_has_subscription( WC_Order $order ): bool {
	if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
		return false;
	}

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( $product && WC_Subscriptions_Product::is_subscription( $product ) ) {
			return true;
		}
	}

	return false;
}

// ============================================================
// Opt-in newsletter au checkout (D7, audit 2026-06)
//
// Case DÉCOCHÉE proposant la newsletter « Les Cahiers de Delphine » sur les
// commandes SANS abonnement (un abonnement enrôle déjà à la newsletter premium
// via la synchro Subscriptions). Consentement horodaté en meta de commande,
// inscription Mailchimp best-effort au paiement confirmé. Invités inclus.
// ============================================================

/**
 * Affiche la case d'opt-in newsletter au checkout (décochée par défaut, RGPD).
 *
 * Rendu via `woocommerce_checkout_after_customer_details` : ce point d'ancrage
 * est HORS du bloc `#order_review` rafraîchi en AJAX (`update_order_review`),
 * l'état coché de la case n'est donc jamais réinitialisé lors d'un changement
 * d'adresse ou de mode de livraison.
 *
 * N'affiche RIEN dans deux cas (le champ est alors absent du DOM et de l'arbre
 * d'accessibilité, et ne peut pas être soumis) :
 *  - le panier contient un abonnement (l'enrôlement premium est automatique) ;
 *  - l'utilisateur est déjà abonné en cours (`_180c_user_has_ongoing_subscription`)
 *    quel que soit le contenu du panier : il reçoit déjà la newsletter via le
 *    tag Mailchimp premium « Abonnés Premium », lui reproposer l'opt-in serait
 *    redondant.
 *
 * Le champ est enveloppé dans une `.checkout__section` titrée « Newsletter
 * gratuite » pour s'aligner sur les autres cards du tunnel (même balise/niveau
 * de titre `<h2 class="checkout__section-title">`). Il est rendu en chaîne
 * (`return => true`) afin de retirer le suffixe « (facultatif) » que
 * WooCommerce ajoute automatiquement à tout champ non requis
 * (`<span class="optional">`, cf. wc-template-functions.php) : l'opt-in est
 * volontaire, la mention parasiterait le libellé sans rien apporter. Le libellé
 * reste à l'intérieur du `<label>` → la case demeure cliquable.
 *
 * @return void
 */
function _180c_checkout_newsletter_field(): void {
	if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
		return;
	}

	// Abonné en cours : il reçoit déjà la newsletter (tag premium) → on ne
	// rend pas la section (absente du DOM, non soumissible).
	if ( function_exists( '_180c_user_has_ongoing_subscription' ) && _180c_user_has_ongoing_subscription() ) {
		return;
	}

	$field = woocommerce_form_field(
		'_180c_newsletter_optin',
		array(
			'type'    => 'checkbox',
			'class'   => array( 'checkout__newsletter-optin' ),
			'label'   => __( 'Je souhaite recevoir la newsletter Les Cahiers de Delphine.', '180c' ),
			'default' => 0,
			'return'  => true,
		),
		'' // Valeur courante vide → case décochée.
	);

	// Neutralise le suffixe auto « (facultatif) » injecté dans le <label>.
	$field = preg_replace( '#&nbsp;<span class="optional">.*?</span>#', '', (string) $field );

	echo '<section class="checkout__section checkout__section--newsletter" aria-labelledby="checkout-newsletter-heading">';
	printf(
		'<h2 id="checkout-newsletter-heading" class="checkout__section-title">%s</h2>',
		esc_html__( 'Newsletter gratuite', '180c' )
	);
	echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML généré et échappé par woocommerce_form_field().
	echo '</section>';
}
add_action( 'woocommerce_checkout_after_customer_details', '_180c_checkout_newsletter_field' );

/**
 * Persiste le consentement newsletter (valeur + horodatage) en meta de commande.
 *
 * Lit la case postée au checkout. Le nonce de checkout WooCommerce
 * (`woocommerce-process-checkout-nonce`) est validé par le cœur AVANT ce hook,
 * d'où l'absence de vérification de nonce supplémentaire ici. L'horodatage n'est
 * écrit que lorsque le consentement est donné.
 *
 * @param WC_Order $order Commande en cours de création.
 * @return void
 */
function _180c_checkout_save_newsletter_consent( $order ): void {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce de checkout WC validé en amont de ce hook.
	$optin = isset( $_POST['_180c_newsletter_optin'] ) && '' !== (string) wp_unslash( $_POST['_180c_newsletter_optin'] );

	$order->update_meta_data( '_180c_newsletter_optin', $optin ? 'yes' : 'no' );

	if ( $optin ) {
		$order->update_meta_data( '_180c_newsletter_optin_at', gmdate( 'c' ) );
	}
}
add_action( 'woocommerce_checkout_create_order', '_180c_checkout_save_newsletter_consent', 10, 1 );

/**
 * Inscrit le client à la newsletter au paiement confirmé (D7).
 *
 * Déclenché sur `woocommerce_payment_complete` (et non à la création de commande)
 * pour ne consommer le consentement qu'une fois le paiement abouti. Best-effort :
 * n'interrompt jamais la commande et journalise les échecs. Réutilise le chemin
 * d'inscription existant `_180c_mailchimp_subscribe()` (audience unique, single
 * opt-in idempotent) — aucune logique d'appel Mailchimp dupliquée (6.4). Invités
 * inclus : l'adresse lue est l'e-mail de facturation. Une meta de garde évite la
 * double inscription si le hook est rejoué.
 *
 * @param int $order_id ID de la commande payée.
 * @return void
 */
function _180c_checkout_newsletter_on_payment( $order_id ): void {
	$order = wc_get_order( $order_id );
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( 'yes' !== $order->get_meta( '_180c_newsletter_optin' ) ) {
		return;
	}

	// Garde anti-doublon : une seule inscription par commande.
	if ( '' !== (string) $order->get_meta( '_180c_newsletter_synced' ) ) {
		return;
	}

	$email = sanitize_email( (string) $order->get_billing_email() );
	if ( ! is_email( $email ) || ! function_exists( '_180c_mailchimp_subscribe' ) ) {
		return;
	}

	$result = _180c_mailchimp_subscribe(
		$email,
		'free',
		(string) $order->get_billing_first_name(),
		(string) $order->get_billing_last_name()
	);

	if ( is_wp_error( $result ) ) {
		if ( function_exists( '_180c_log' ) ) {
			_180c_log(
				'Opt-in newsletter checkout échoué.',
				array(
					'order_id' => (int) $order_id,
					'error'    => $result->get_error_message(),
				),
				'warning'
			);
		}
		return;
	}

	// Traçabilité de la source : opt-in coché au checkout. Best-effort, après
	// inscription Mailchimp réussie.
	if ( function_exists( '_180c_mc_tag_source' ) ) {
		_180c_mc_tag_source( $email, 'web-checkout' );
	}

	$order->update_meta_data( '_180c_newsletter_synced', gmdate( 'c' ) );
	$order->save();
}
add_action( 'woocommerce_payment_complete', '_180c_checkout_newsletter_on_payment', 10, 1 );

// ============================================================
// Accordéon paiement — intitulés, helpers et logos (Phase 4)
//
// Renomme les passerelles en intitulés sobres, pose une microcopy de
// réassurance dans le panneau de chaque méthode, et substitue aux icônes des
// passerelles un SVG monochrome (carte générique maison + PayPal officiel),
// recoloré theme-aware en CSS (.checkout-payment__logo svg { fill: currentColor }).
//
// Tout passe par les filtres natifs WooCommerce (woocommerce_gateway_title /
// _description / _icon) : aucun template ni moteur de paiement touché.
// ============================================================

/**
 * Renomme l'intitulé des passerelles dans l'accordéon de paiement.
 *
 * Stripe (« Options de paiement » par défaut, hérité du réglage admin) →
 * « Carte bancaire ». PayPal → « PayPal » (libellé sobre, explicite).
 *
 * @param string $title Intitulé courant de la passerelle.
 * @param string $id    Identifiant de la passerelle.
 * @return string Intitulé filtré.
 */
function _180c_checkout_gateway_title( $title, $id ) {
	switch ( $id ) {
		case 'stripe':
			return __( 'Carte bancaire', '180c' );
		case 'paypal':
			return __( 'PayPal', '180c' );
		default:
			return $title;
	}
}
add_filter( 'woocommerce_gateway_title', '_180c_checkout_gateway_title', 20, 2 );

/**
 * Renomme le titre poussé au Payment Element Stripe (UPE).
 *
 * Le libellé « Options de paiement » visible DANS le bloc carte ne provient pas
 * de `get_title()` (donc pas du filtre `woocommerce_gateway_title` ci-dessus) :
 * c'est le titre du moyen de paiement « carte » (réglage admin de la méthode CC)
 * que le plugin pousse au JS via `wc_stripe_upe_params` — d'une part en
 * `title` (titre de la passerelle), d'autre part dans `paymentMethodsConfig`
 * (clé `card`, rendue comme libellé de la méthode dans l'accordéon interne de
 * l'Element). On le réécrit ici en « Carte bancaire » — seul point qui atteint
 * le rendu interne de l'Element. Aucun autre paramètre de la passerelle touché.
 *
 * @param array $params Paramètres JS de l'UPE.
 * @return array Paramètres filtrés.
 */
function _180c_checkout_stripe_upe_title( $params ) {
	if ( ! is_array( $params ) ) {
		return $params;
	}

	$label = __( 'Carte bancaire', '180c' );

	if ( isset( $params['title'] ) ) {
		$params['title'] = $label;
	}

	// Clé `card` = méthode carte ET Optimized Checkout (les deux valent 'card').
	if ( isset( $params['paymentMethodsConfig']['card']['title'] ) ) {
		$params['paymentMethodsConfig']['card']['title'] = $label;
	}

	return $params;
}
add_filter( 'wc_stripe_upe_params', '_180c_checkout_stripe_upe_title', 20, 1 );

/**
 * Renomme le libellé « Options de paiement » de l'Optimized Checkout Stripe.
 *
 * Quand l'Optimized Checkout (OC) est ACTIF, `WC_Stripe_UPE_Payment_Gateway::get_title()`
 * ne passe PAS par `woocommerce_gateway_title` : il retourne le titre générique
 * de l'OC (« Payment options » → « Options de paiement ») exposé par le filtre
 * dédié `wc_stripe_optimized_checkout_title`. C'est CE filtre — et lui seul —
 * qui pilote le libellé rendu dans l'en-tête de l'item de paiement. On le force
 * en « Carte bancaire » (tous contextes — le titre par défaut et la surface
 * passés par le filtre sont volontairement ignorés).
 *
 * @return string Titre filtré.
 */
function _180c_checkout_oc_title() {
	return __( 'Carte bancaire', '180c' );
}
add_filter( 'wc_stripe_optimized_checkout_title', '_180c_checkout_oc_title', 20 );

/**
 * Microcopy de réassurance dans le panneau d'une méthode de paiement.
 *
 * Le texte est rendu par la passerelle elle-même via `get_description()` (PayPal
 * via le rendu natif), donc à l'intérieur du `.payment_box` piloté par
 * WooCommerce. Garantit aussi qu'une description non vide existe pour PayPal
 * (sans champ propre), ce qui maintient son panneau dans l'accordéon.
 *
 * Stripe (carte) n'a PAS de helper : le champ carte parle de lui-même et le
 * panneau reste épuré.
 *
 * @param string $description Description courante de la passerelle.
 * @param string $id          Identifiant de la passerelle.
 * @return string Description filtrée.
 */
function _180c_checkout_gateway_description( $description, $id ) {
	switch ( $id ) {
		case 'paypal':
			return __( 'Vous serez redirigé vers PayPal pour finaliser le paiement.', '180c' );
		default:
			return $description;
	}
}
add_filter( 'woocommerce_gateway_description', '_180c_checkout_gateway_description', 20, 2 );

/**
 * Lit et inline un SVG de paiement local du thème (assets/icons/payment/).
 *
 * @param string $slug Nom de fichier sans extension (ex. 'card', 'paypal').
 * @return string Markup SVG inline, ou chaîne vide si introuvable.
 */
function _180c_checkout_payment_icon_svg( $slug ) {
	$file = get_theme_file_path( 'assets/icons/payment/' . $slug . '.svg' );
	if ( ! is_readable( $file ) ) {
		return '';
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Asset SVG local du thème.
	$svg = file_get_contents( $file );

	return false === $svg ? '' : trim( $svg );
}

/**
 * Substitue à l'icône de la passerelle des SVG monochromes 180°C.
 *
 * Stripe (« Carte bancaire ») → logos réseaux Visa + Mastercard + American
 * Express (assets locaux, recolorés en monochrome theme-aware via
 * `fill: currentColor` en CSS), alignés à droite comme le logo PayPal. Possible
 * parce que l'Optimized Checkout est désactivé (cf. _180c_checkout_disable_stripe_oc),
 * donc Stripe ne réécrit plus notre <label> et notre span survit. PayPal → asset
 * officiel local. Les autres passerelles conservent leur icône native.
 *
 * @param string $icon HTML d'icône courant de la passerelle.
 * @param string $id   Identifiant de la passerelle.
 * @return string HTML d'icône filtré.
 */
function _180c_checkout_gateway_icon( $icon, $id ) {
	switch ( $id ) {
		case 'stripe':
			$svg = _180c_checkout_payment_icon_svg( 'visa' )
				. _180c_checkout_payment_icon_svg( 'mastercard' )
				. _180c_checkout_payment_icon_svg( 'amex' );
			return '' !== trim( $svg ) ? $svg : $icon;
		case 'paypal':
			$svg = _180c_checkout_payment_icon_svg( 'paypal' );
			return '' !== $svg ? $svg : $icon;
		default:
			return $icon;
	}
}

/**
 * Désactive l'Optimized Checkout (OC) de Stripe sur le tunnel front.
 *
 * L'OC fait rendre le formulaire carte par un Payment Element « accordéon » dans
 * une iframe Stripe (cross-origin) : il réécrit le contenu de notre <label>
 * (perte de nos logos/typo), duplique le libellé « Carte bancaire » et affiche
 * des logos de marque dans le champ numéro — tous ces éléments étant DANS
 * l'iframe, donc inatteignables en CSS. On force `optimized_checkout_element =
 * no` côté front (et AJAX checkout) : Stripe rend alors le champ carte simple,
 * notre markup d'accordéon survit, et nos intitulés/logos reprennent la main.
 *
 * L'écran d'admin Stripe (is_admin hors AJAX) n'est PAS filtré → le réglage réel
 * y reste visible et éditable. Aucun autre paramètre de la passerelle n'est
 * modifié, et le moteur de paiement (UPE standard) reste pleinement fonctionnel.
 *
 * @param mixed $settings Réglages de la passerelle Stripe (tableau ou false).
 * @return mixed Réglages filtrés.
 */
function _180c_checkout_disable_stripe_oc( $settings ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $settings;
	}
	if ( is_array( $settings ) ) {
		$settings['optimized_checkout_element'] = 'no';
	}
	return $settings;
}
add_filter( 'option_woocommerce_stripe_settings', '_180c_checkout_disable_stripe_oc', 20 );
add_filter( 'woocommerce_gateway_icon', '_180c_checkout_gateway_icon', 20, 2 );
