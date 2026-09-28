<?php
/**
 * GA4 + Consent Mode v2 (gtag.js inline).
 *
 * Phase 8 — Analytics complète :
 *  - Bootstrap Consent Mode v2 « basic » dans <head> : gtag.js n'est chargé
 *    qu'après acceptation (aucune requête vers Google avant le choix)
 *  - Helpers PHP pour flaguer les events côté serveur (login, signup, purchase)
 *  - Listeners sur les hooks WordPress (180c/user_registered, wp_login, etc.)
 *  - Events côté client via JS (favoris, paywall CTA, app promo, search)
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'GA4_MEASUREMENT_ID' ) ) {
	define( 'GA4_MEASUREMENT_ID', '' );
}

/*
 * _180C_CONSENT_COOKIE et _180C_CONSENT_VERSION vivaient ici. Elles sont
 * remontées dans inc/consent.php, qui est désormais la source unique : la
 * version du schéma était répétée en quatre endroits sans qu'aucun ne fasse
 * autorité, et c'est elle qui décide de re-solliciter ou non le visiteur.
 */

/**
 * Injecte le bootstrap GA4 + Consent Mode v2 dans le <head>.
 * Priority 2 pour que le script s'exécute AVANT tout autre script GA.
 */
add_action( 'wp_head', '_180c_analytics_print_gtag', 2 );

/**
 * Imprime le bootstrap GA4 (Consent Mode v2, mode « basic »).
 *
 * Principe : AUCUNE requête vers Google n'est émise tant que l'utilisateur n'a
 * pas accepté. Ce script ne fait qu'installer `dataLayer` + le shim `gtag()` et
 * exposer `window._180c.ga4` ; le tag googletagmanager.com n'est injecté que par
 * `_180c.ga4.load()`, appelé soit immédiatement si le cookie de consentement
 * porte déjà `analytics_storage: granted`, soit par la CMP au clic « Accepter ».
 *
 * Le consentement est relu EN JS (et non en PHP) à dessein : le HTML reste ainsi
 * identique pour tous les visiteurs anonymes et donc compatible avec le cache
 * page (WP Super Cache en prod), qui ne varie pas sur les cookies custom.
 *
 * Les événements sont mis en file via `_180c.ga4.event()` et ne partent qu'après
 * le `config` — jamais avant, y compris quand le consentement arrive en cours de
 * page.
 */
function _180c_analytics_print_gtag() {
	if ( ! GA4_MEASUREMENT_ID ) {
		return;
	}

	$ga_id = GA4_MEASUREMENT_ID;

	// User properties + user_id posés AVANT config : ainsi le page_view (émis
	// par config) les porte. platform + user_status pour TOUS les hits (anonyme
	// inclus, cf TRACKING_PLAN.md §3) ; `platform` = constante web (les apps
	// posent 'ios'/'android'). Données d'abonnement + user_id uniquement pour
	// les connectés ; `subscription_status` omis si aucun abonnement.
	$user_props = array(
		'platform'    => 'web',
		'user_status' => _180c_analytics_user_status(),
	);
	if ( is_user_logged_in() ) {
		$user_props['subscription_plan'] = _180c_active_recipe_plan_slug();
		$subscription_status             = _180c_user_subscription_status();
		if ( '' !== $subscription_status ) {
			$user_props['subscription_status'] = $subscription_status;
		}
	}

	$user_id = is_user_logged_in() ? (string) get_current_user_id() : '';

	// Events flagués en session (login, signup, etc.) : mis en file, ils ne
	// partiront qu'une fois le consentement donné.
	$flagged_events = _180c_analytics_pop_flagged_events();
	$queued_events  = array();
	foreach ( $flagged_events as $event ) {
		$queued_events[] = array(
			'name'   => $event['name'],
			'params' => (object) $event['params'],
		);
	}
	?>
	<!-- GA4 — Consent Mode v2 « basic » : aucun appel réseau avant consentement. -->
	<script>
	(function () {
		var GA_ID = <?php echo wp_json_encode( $ga_id ); ?>;
		var COOKIE = <?php echo wp_json_encode( _180C_CONSENT_COOKIE ); ?>;
		var COOKIE_VERSION = <?php echo (int) _180C_CONSENT_VERSION; ?>;
		var USER_PROPS = <?php echo wp_json_encode( (object) $user_props ); ?>;
		var USER_ID = <?php echo wp_json_encode( $user_id ); ?>;
		var QUEUED = <?php echo wp_json_encode( $queued_events ); ?>;

		window.dataLayer = window.dataLayer || [];
		function gtag() { dataLayer.push(arguments); }
		window.gtag = gtag;

		/**
		 * Lit le cookie de consentement et dit si la mesure est autorisée.
		 */
		function analyticsGranted() {
			var parts = document.cookie.split('; ');
			for (var i = 0; i < parts.length; i++) {
				if (parts[i].indexOf(COOKIE + '=') !== 0) { continue; }
				try {
					var stored = JSON.parse(decodeURIComponent(parts[i].slice(COOKIE.length + 1)));
					return !!stored && stored.v === COOKIE_VERSION && stored.analytics === true;
				} catch (e) {
					return false;
				}
			}
			return false;
		}

		var granted = analyticsGranted();

		// Consent defaults. functionality_storage / security_storage restent
		// granted (strictement nécessaires). Seul analytics_storage suit le
		// cookie : sur un visiteur qui a déjà accepté, le default est granted dès
		// le <head>, donc le page_view est correctement attribué (pas de fenêtre
		// wait_for_update).
		//
		// Les trois signaux publicitaires sont figés à `denied` dans les DEUX
		// branches : 180°C ne diffuse pas de publicité et ne fait pas de
		// remarketing, donc les accorder ne servirait aucune finalité réelle.
		// Miroir de buildGtagPayload() dans src/js/modules/consent.js — les
		// deux doivent rester alignés, sinon le `default` et l'`update` se
		// contredisent.
		gtag('consent', 'default', {
			'ad_storage': 'denied',
			'ad_user_data': 'denied',
			'ad_personalization': 'denied',
			'analytics_storage': granted ? 'granted' : 'denied',
			'functionality_storage': 'granted',
			'security_storage': 'granted'
		});

		// URL passthrough et redaction activées.
		gtag('set', 'url_passthrough', true);
		gtag('set', 'ads_data_redaction', true);

		var pending = [];

		window._180c = window._180c || {};
		window._180c.userStatus = <?php echo wp_json_encode( _180c_analytics_user_status() ); ?>;
		window._180c.ga4 = {
			id: GA_ID,
			loaded: false,

			/**
			 * Injecte réellement gtag.js puis pose la configuration GA4.
			 * Idempotent : ne fait rien si déjà chargé.
			 */
			load: function () {
				if (this.loaded) { return; }
				this.loaded = true;

				// Preconnect posés ici seulement : avant consentement, même un
				// handshake TCP/TLS vers Google est une donnée transmise.
				var head = document.head || document.getElementsByTagName('head')[0];
				['https://www.googletagmanager.com', 'https://www.google-analytics.com'].forEach(function (origin, i) {
					var link = document.createElement('link');
					link.rel = 'preconnect';
					link.href = origin;
					if (i === 1) { link.crossOrigin = ''; }
					head.appendChild(link);
				});

				var tag = document.createElement('script');
				tag.async = true;
				tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA_ID);
				head.appendChild(tag);

				// Configuration : posée APRÈS le consent update, donc le page_view
				// part avec l'état de consentement correct.
				gtag('js', new Date());
				gtag('set', 'user_properties', USER_PROPS);
				if (USER_ID) { gtag('set', { 'user_id': USER_ID }); }
				gtag('config', GA_ID, {
					'anonymize_ip': true,
					'send_page_view': true
				});

				// Vidange des files : events serveur puis events de page.
				QUEUED.forEach(function (e) { gtag('event', e.name, e.params); });
				QUEUED = [];
				pending.forEach(function (e) { gtag('event', e[0], e[1]); });
				pending = [];
			},

			/**
			 * Point d'entrée unique des events GA4 côté page.
			 * Avant consentement, l'event est mis en file (rien ne part sur le
			 * réseau) ; il est émis à la vidange si l'utilisateur accepte, et
			 * simplement abandonné s'il refuse.
			 */
			event: function (name, params) {
				if (this.loaded) {
					gtag('event', name, params || {});
					return;
				}
				pending.push([name, params || {}]);
			}
		};

		if (granted) { window._180c.ga4.load(); }
	})();
	</script>
	<?php
}

/**
 * Retourne le statut utilisateur pour les events GA4.
 *
 * @return string anonymous|free|subscribed
 */
function _180c_analytics_user_status() {
	if ( ! is_user_logged_in() ) {
		return 'anonymous';
	}

	if ( _180c_is_recipe_subscriber() ) {
		return 'subscribed';
	}

	return 'free';
}

/**
 * Marque un event à pousser au prochain rendu (utilisé après login, signup, etc.).
 *
 * @param string $name   Nom de l'event GA4.
 * @param array  $params Paramètres additionnels.
 */
function _180c_analytics_flag_event( $name, $params = array() ) {
	$events   = get_transient( '_180c_flagged_events_' . _180c_analytics_session_id() ) ?: array();
	$events[] = array(
		'name'   => $name,
		'params' => $params,
	);
	set_transient( '_180c_flagged_events_' . _180c_analytics_session_id(), $events, 60 );
}

/**
 * Récupère et efface les events flagués.
 *
 * @return array Liste des events flagués.
 */
function _180c_analytics_pop_flagged_events() {
	$key    = '_180c_flagged_events_' . _180c_analytics_session_id();
	$events = get_transient( $key );
	if ( $events ) {
		delete_transient( $key );
	}
	return $events ?: array();
}

/**
 * Récupère ou génère un ID de session pour la requête courante.
 *
 * @return string ID de session (hash MD5).
 */
function _180c_analytics_session_id() {
	// Hash IP + User-Agent. Cette fonction lisait d'abord un cookie `_180c_sid`
	// « préféré » — mais RIEN, dans le thème comme dans les extensions, ne l'a
	// jamais écrit : la branche était morte depuis son écriture, et seul ce
	// hash a jamais servi. Le retirer supprime aussi de l'inventaire cookies un
	// nom que rien ne dépose.
	return md5(
		( $_SERVER['REMOTE_ADDR'] ?? '' ) .
		( $_SERVER['HTTP_USER_AGENT'] ?? '' )
	);
}

/**
 * ===== AUTO-EVENTS : Listeners sur les hooks WordPress =====
 *
 * Chaque événement important déclenche un flag_event ou une injection directe.
 */

/**
 * Event : sign_up — utilisateur inscrit.
 * Hook : 180c/user_registered (émis par inc/auth/register.php)
 * Params : method=email
 */
add_action(
	'180c/user_registered',
	function ( $user_id ) {
		_180c_analytics_flag_event(
			'sign_up',
			array( 'method' => 'email' )
		);
	}
);

/**
 * Event : login — utilisateur connecté.
 * Hook : wp_login (WordPress natif)
 * Params : method=email
 */
add_action(
	'wp_login',
	function ( $login, $user ) {
		_180c_analytics_flag_event(
			'login',
			array( 'method' => 'email' )
		);
	},
	10,
	2
);

/*
 * Event : newsletter_signup — émis CÔTÉ CLIENT uniquement (L7 / dédup).
 *
 * Auparavant émis EN DOUBLE sur le web : une fois côté client (newsletter.js,
 * avec `location` explicite via data-ga-location) ET une fois ici sur le hook
 * 180c/newsletter_subscribed (avec `location` deviné par referrer). On retire
 * l'émission serveur : le client est la source unique côté web, avec un
 * `location` fiable. Les apps émettront leur propre newsletter_signup via
 * Firebase (hors périmètre). cf TRACKING_PLAN.md §4.3.
 */

/*
 * Event : add_to_cart — émis CÔTÉ CLIENT (L3, cf TRACKING_PLAN.md §4.1).
 *
 * L'ancien flag transient server-side était rendu au pageload suivant, donc KO
 * avec l'ajout AJAX (drawer panier) qui ne recharge pas la page. L'event est
 * désormais émis par src/js/modules/cart.js sur la réponse AJAX d'ajout, à
 * partir du payload `ga4_add` (currency, value, items) construit côté serveur
 * dans inc/woo/cart.php (_180c_cart_ajax_add). Une seule émission par ajout.
 */

/**
 * Event : begin_checkout — utilisateur a cliqué sur checkout.
 * Hook : woocommerce_before_checkout_form (Woo natif)
 * Params : value (total panier), items (array des produits)
 */
add_action(
	'woocommerce_before_checkout_form',
	function () {
		if ( ! WC()->cart ) {
			return;
		}

		$items = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			$items[] = array(
				'item_id'   => (string) $product->get_id(),
				'item_name' => $product->get_name(),
				'price'     => (float) $product->get_price(),
				'quantity'  => (int) $cart_item['quantity'],
			);
		}

		// Injection inline sur la page checkout (pas un flag, car on est déjà sur la page).
		add_action(
			'wp_footer',
			function () use ( $items ) {
				$total = WC()->cart->get_total( false );
				?>
				<script>
					window._180c && window._180c.ga4 && window._180c.ga4.event('begin_checkout', {
						'value': <?php echo (float) $total; ?>,
						'currency': 'EUR',
						'items': <?php echo wp_json_encode( $items ); ?>
					});
				</script>
				<?php
			}
		);
	}
);

/**
 * Event : purchase — commande complètée.
 * Hook : woocommerce_thankyou (Woo natif, called on order confirmation page)
 * Params : transaction_id, value, currency, items
 *
 * Déduplication : la page de remerciement peut être rechargée (refresh, retour
 * navigateur). On marque la commande via un meta HPOS-safe au premier rendu pour
 * ne JAMAIS réémettre purchase (cf TRACKING_PLAN.md §4.1).
 */
add_action(
	'woocommerce_thankyou',
	function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Anti-double-comptage : une commande déjà trackée n'est plus réémise.
		if ( $order->get_meta( '_180c_ga4_purchase_tracked' ) ) {
			return;
		}
		$order->update_meta_data( '_180c_ga4_purchase_tracked', '1' );
		$order->save();

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = array(
				'item_id'   => (string) $item->get_product_id(),
				'item_name' => $item->get_name(),
				'price'     => (float) $order->get_item_total( $item ),
				'quantity'  => (int) $item->get_quantity(),
			);
		}

		// Injection directe dans le footer de la page de remerciement.
		add_action(
			'wp_footer',
			function () use ( $order, $items ) {
				?>
				<script>
					window._180c && window._180c.ga4 && window._180c.ga4.event('purchase', {
						'transaction_id': <?php echo wp_json_encode( $order->get_order_number() ); ?>,
						'value': <?php echo (float) $order->get_total(); ?>,
						'currency': <?php echo wp_json_encode( $order->get_currency() ); ?>,
						'items': <?php echo wp_json_encode( $items ); ?>
					});
				</script>
				<?php
			}
		);
	}
);

/**
 * Event : subscription_start — abonnement activé.
 * Hook : woocommerce_subscription_status_active (si WC Subscriptions est actif)
 * Params : value, currency, subscription_plan, billing_period
 *
 * Note : on ne pousse que si c'est la première activation (pas un renouvellement).
 * Ce hook n'existe que si WooCommerce Subscriptions est actif.
 */
if ( class_exists( 'WC_Subscription' ) ) {
	add_action(
		'woocommerce_subscription_status_active',
		function ( $subscription ) {
			// Première activation uniquement : un abonnement renouvelé possède au
			// moins une commande de renouvellement. On évite ainsi le double-fire à
			// chaque réactivation post-renouvellement (L8, fiable et sans écriture).
			$renewal_orders = method_exists( $subscription, 'get_related_orders' )
				? $subscription->get_related_orders( 'ids', 'renewal' )
				: array();
			if ( ! empty( $renewal_orders ) ) {
				return;
			}

			// Période de facturation brute (TRACKING_PLAN.md §4.1 : "month" | "year").
			$billing_period = method_exists( $subscription, 'get_billing_period' ) ? $subscription->get_billing_period() : '';

			_180c_analytics_flag_event(
				'subscription_start',
				array(
					'value'             => (float) $subscription->get_total(),
					'currency'          => $subscription->get_currency(),
					// Slug réel du membership actif (cf TRACKING_PLAN.md §3), dérivé du
					// client de l'abonnement plutôt que codé en dur.
					'subscription_plan' => _180c_active_recipe_plan_slug( $subscription->get_user_id() ),
					'billing_period'    => $billing_period,
				)
			);
		}
	);
}


/**
 * Event : subscription_cancel — abonnement annulé.
 * Hook : woocommerce_subscription_status_cancelled (WC Subscriptions)
 * Params : subscription_id, reason (motif du stepper de rétention si présent).
 *
 * Le motif (slug, énumération fermée) est persité en transient par l'endpoint
 * REST 180c/v1/unsubscribe/feedback juste avant la résiliation (L5) ; on le relit
 * ici puis on le purge. Absent si l'annulation n'a pas transité par le sondage.
 * Ce hook n'existe que si WooCommerce Subscriptions est actif.
 */
if ( class_exists( 'WC_Subscription' ) ) {
	add_action(
		'woocommerce_subscription_status_cancelled',
		function ( $subscription ) {
			$reason_key = '_180c_cancel_reason_' . $subscription->get_id();
			$reason     = get_transient( $reason_key );
			if ( false !== $reason ) {
				delete_transient( $reason_key );
			}

			_180c_analytics_flag_event(
				'subscription_cancel',
				array(
					'subscription_id' => (string) $subscription->get_id(),
					'reason'          => $reason ? (string) $reason : '',
				)
			);
		}
	);
}

/**
 * Event : recipe_view — page recette affichée.
 * Hook : the_post (WordPress natif, pour single-recipe)
 * Params : recipe_id, recipe_name, is_paywall, user_status
 *
 * `the_post` se déclenche à CHAQUE itération de boucle : sans garde, une archive
 * ou une page de résultats de recherche listant N recettes émettait N
 * `recipe_view` (constaté : 14 events sur /?s=tarte). On restreint donc à la
 * vue single de la recette effectivement demandée, et on ne compte qu'une fois
 * par requête (le contenu principal peut relancer la boucle).
 */
add_action(
	'the_post',
	function ( $post ) {
		static $tracked = false;

		if ( $tracked || 'recipe' !== get_post_type( $post ) ) {
			return;
		}

		if ( ! is_singular( 'recipe' ) || get_queried_object_id() !== $post->ID ) {
			return;
		}

		$tracked = true;

		// Injection dans wp_footer.
		add_action(
			'wp_footer',
			function () use ( $post ) {
				$is_paywalled = _180c_recipe_is_paywalled( $post->ID );
				?>
				<script>
					window._180c && window._180c.ga4 && window._180c.ga4.event('recipe_view', {
						'recipe_id': <?php echo wp_json_encode( (string) $post->ID ); ?>,
						'recipe_name': <?php echo wp_json_encode( $post->post_title ); ?>,
						'is_paywall': <?php echo wp_json_encode( $is_paywalled ); ?>,
						'user_status': window._180c.userStatus || 'anonymous'
					});
				</script>
				<?php
			}
		);
	}
);

/**
 * Event : recipe_favorite (action=add) — recette ajoutée aux favoris.
 * Hook : 180c/favorite_added (émis par inc/rest/favorites.php)
 * Params : recipe_id, action=add, location=recipe_page|app
 *
 * Note : l'API REST envoie seulement 2 paramètres (user_id, recipe_id).
 * Les appels depuis l'app peuvent inclure une source, traitée ici avec fallback.
 */
add_action(
	'180c/favorite_added',
	function ( $user_id, $recipe_id ) {
		// Par défaut, location = recipe_page (depuis la page web).
		// L'app pourrait émettre do_action('180c/favorite_added', $user_id, $recipe_id, 'app')
		// mais ce n'est pas implémenté en v1. Pour l'instant, tout vient de recipe-page.
		_180c_analytics_flag_event(
			'recipe_favorite',
			array(
				'recipe_id' => (string) $recipe_id,
				'action'    => 'add',
				'location'  => 'recipe_page',
			)
		);
	},
	10,
	2
);

/**
 * Event : recipe_favorite (action=remove) — recette retirée des favoris.
 * Hook : 180c/favorite_removed (émis par inc/rest/favorites.php)
 * Params : recipe_id, action=remove
 */
add_action(
	'180c/favorite_removed',
	function ( $user_id, $recipe_id ) {
		_180c_analytics_flag_event(
			'recipe_favorite',
			array(
				'recipe_id' => (string) $recipe_id,
				'action'    => 'remove',
				'location'  => 'recipe_page',
			)
		);
	},
	10,
	2
);

/**
 * Event : search — recherche effectuée.
 * Hook : wp_footer (sur is_search)
 * Params : search_term, results_count
 */
add_action(
	'wp_footer',
	function () {
		if ( ! is_search() ) {
			return;
		}

		global $wp_query;
		$search_term   = get_search_query();
		$results_count = (int) $wp_query->found_posts;
		?>
		<script>
			window._180c && window._180c.ga4 && window._180c.ga4.event('search', {
				'search_term': <?php echo wp_json_encode( $search_term ); ?>,
				'results_count': <?php echo (int) $results_count; ?>
			});
		</script>
		<?php
	}
);

/**
 * ===== EVENTS CLIENT-SIDE =====
 *
 * Les événements suivants sont trackés côté JS car ils dépendent du DOM :
 *  - paywall_view (data-event-fire attribute dans paywall.php)
 *  - paywall_cta_click (JS listener sur [data-event="paywall_cta_click"])
 *  - app_promo_click (JS listener sur .js-app-promo-link)
 *  - view_item (product page — hook woocommerce_after_single_product, injection inline)
 *
 * Ces événements sont implémentés dans src/js/modules/analytics-ga4.js.
 */

/**
 * Construit les items GA4 de l'offre d'abonnement (mensuel + annuel).
 *
 * Prix lus en direct depuis WooCommerce (jamais en dur). cf TRACKING_PLAN.md §4.1.
 *
 * @return array Liste d'items canoniques (item_id, item_name, item_variant, price).
 */
function _180c_analytics_subscription_offer_items() {
	// IDs des 2 produits d'abonnement (mensuel / annuel) → item_variant canonique.
	$variants = array(
		13121518 => 'monthly',
		13121519 => 'annual',
	);

	$items = array();
	foreach ( $variants as $product_id => $variant ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			continue;
		}
		$items[] = array(
			'item_id'      => (string) $product_id,
			'item_name'    => $product->get_name(),
			'item_variant' => $variant,
			'price'        => (float) wc_get_price_to_display( $product ),
		);
	}

	return $items;
}

/**
 * Event : view_subscription_offer — vue de la page offre d'abonnement.
 *
 * Déclenché depuis page-abonnement.php (add_action wp_footer). Émission
 * consent-gated via la file `_180c.ga4.event()` : rien ne part avant acceptation.
 *
 * @return void
 */
function _180c_analytics_print_subscription_offer() {
	$items = _180c_analytics_subscription_offer_items();
	if ( empty( $items ) ) {
		return;
	}
	?>
	<script>
		window._180c && window._180c.ga4 && window._180c.ga4.event('view_subscription_offer', {
			'currency': 'EUR',
			'items': <?php echo wp_json_encode( $items ); ?>
		});
	</script>
	<?php
}

/**
 * Event : view_item — produit affiché (page produit).
 * Hook : woocommerce_after_single_product (Woo natif, on single-product)
 * Params : item_id, item_name, item_category, price
 */
add_action(
	'woocommerce_after_single_product',
	function () {
		$product = wc_get_product();
		if ( ! $product ) {
			return;
		}

		$categories = wc_get_product_category_list( $product->get_id(), ',', '<span>', '</span>' );
		$category   = $categories ? wp_strip_all_tags( $categories ) : '';

		add_action(
			'wp_footer',
			function () use ( $product, $category ) {
				?>
				<script>
					window._180c && window._180c.ga4 && window._180c.ga4.event('view_item', {
						'item_id': <?php echo wp_json_encode( (string) $product->get_id() ); ?>,
						'item_name': <?php echo wp_json_encode( $product->get_name() ); ?>,
						'item_category': <?php echo wp_json_encode( $category ); ?>,
						'price': <?php echo (float) $product->get_price(); ?>,
						'currency': 'EUR'
					});
				</script>
				<?php
			}
		);
	}
);
