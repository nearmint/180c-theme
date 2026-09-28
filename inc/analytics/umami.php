<?php
/**
 * Umami Cloud — chargement du script de mesure (analytics sans cookies).
 *
 * Coexiste avec GA4 (inc/analytics/ga4.php) sans aucun état partagé : les deux
 * outils mesurent en parallèle, et surtout sous le MÊME régime de consentement.
 *
 * Umami ne dépose ni cookie ni identifiant persistant, ce qui avait justifié de
 * le charger sans condition. C'était une erreur d'appréciation : le simple fait
 * de requérir `cloud.umami.is` transmet l'adresse IP, l'agent utilisateur et
 * l'URL visitée à un tiers, et la préconnexion partait avant même que le
 * visiteur ait vu la modale. Un refus ne coupait donc qu'un des deux outils.
 *
 * Le script est désormais injecté par `window._180c.umami.load()`, appelée par
 * la CMP à l'acceptation (src/js/modules/consent.js), exactement comme
 * gtag.js. Avant choix, ZÉRO requête réseau — préconnexions comprises, elles
 * aussi déplacées dans le chargeur. Au refus, rien ne part et rien n'est mis en
 * file.
 *
 * Le rendu reste identique pour tous les visiteurs anonymes (l'état de
 * consentement est lu en JS, jamais en PHP), donc compatible avec le cache page
 * WP Super Cache en prod. La seule exclusion — les utilisateurs `edit_posts` —
 * porte sur des sessions connectées, jamais servies depuis le cache.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Identifiant du site Umami.
 *
 * Propriété commune au web et aux apps iOS/Android : c'est le même ID que celui
 * embarqué dans les apps, la plateforme se distingue via `data-tag` + hostname.
 * Surchargeable depuis wp-config.php.
 */
if ( ! defined( '_180C_UMAMI_WEBSITE_ID' ) ) {
	define( '_180C_UMAMI_WEBSITE_ID', '94b264d5-6963-4678-9d7f-316583113c91' );
}

/**
 * URL du script Umami Cloud. Surchargeable (instance self-hosted).
 */
if ( ! defined( '_180C_UMAMI_SRC' ) ) {
	define( '_180C_UMAMI_SRC', 'https://cloud.umami.is/script.js' );
}

/**
 * Domaines autorisés à émettre des hits (`data-domains`).
 *
 * Filtre côté script : les environnements local et staging n'envoient rien.
 * NE PAS retirer — c'est la garde qui évite de polluer les stats de prod.
 */
const _180C_UMAMI_DOMAINS = '180c.fr,www.180c.fr';

/**
 * Étiquette de plateforme (`data-tag`).
 *
 * La propriété Umami est partagée entre le site et les apps mobiles : ce tag
 * (avec le hostname) sert à filtrer les hits par plateforme. NE PAS retirer.
 */
const _180C_UMAMI_TAG = 'web';

/**
 * Nom de la propriété de segmentation « abonné », jointe à TOUS les events.
 *
 * Underscore et non tiret, volontairement : le script Umami extrait la clé par
 * le regex `/data-umami-event-([\w-_]+)/` et l'utilise TELLE QUELLE. Un
 * `data-umami-event-is-subscriber` produirait donc la propriété `is-subscriber`,
 * alors que les events émis en JS (src/js/modules/umami-events.js) envoient
 * `is_subscriber`. Deux orthographes = deux propriétés distinctes dans Umami,
 * donc un segment « Abonnés » scindé en deux et faux des deux côtés.
 *
 * C'est pourquoi cette clé contourne le `str_replace( '_', '-' )` appliqué aux
 * autres clés dans _180c_umami_attrs(). NE PAS « harmoniser » en tiret.
 */
const _180C_UMAMI_SUBSCRIBER_KEY = 'is_subscriber';

/**
 * Statut d'abonnement de l'utilisateur courant, au format attendu par Umami.
 *
 * Renvoie la CHAÎNE 'true' ou 'false', jamais un booléen : Umami stocke les
 * propriétés d'event en chaînes, et un booléen PHP interpolé donnerait '1' / ''
 * — cette dernière étant filtrée comme valeur vide par _180c_umami_attrs(),
 * ce qui ferait disparaître la dimension pour les non-abonnés (exactement la
 * moitié qui nous intéresse pour le segment).
 *
 * S'appuie sur _180c_user_is_subscriber() (inc/recipe-access.php), le statut
 * global canonique : Memberships `abonne-recettes` + plans historiques, repli
 * WC Subscriptions, repli meta `access_recipes`. C'est la même source que
 * l'endpoint REST `180c/v1/me` consommé par les apps iOS/Android, donc la
 * segmentation restera cohérente entre le web et les apps — qui partagent cette
 * propriété Umami (voir _180C_UMAMI_WEBSITE_ID).
 *
 * Mémoïsé : une page de paywall porte une vingtaine de CTA balisés, et le
 * helper interroge Memberships puis Subscriptions puis la table usermeta. Sans
 * ce cache statique, le coût serait payé à chaque attribut rendu.
 *
 * @return string 'true' ou 'false'.
 */
function _180c_umami_subscriber_value() {
	static $value = null;

	if ( null !== $value ) {
		return $value;
	}

	$is_subscriber = is_user_logged_in()
		&& function_exists( '_180c_user_is_subscriber' )
		&& _180c_user_is_subscriber();

	/**
	 * Filtre le statut d'abonnement transmis à Umami.
	 *
	 * @param bool $is_subscriber Statut calculé.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	$value = apply_filters( '180c/umami_is_subscriber', $is_subscriber ) ? 'true' : 'false';

	return $value;
}

/**
 * Attribut `data-umami-event-is_subscriber` prêt à être inséré dans une balise.
 *
 * @return string Attribut échappé, précédé d'une espace.
 */
function _180c_umami_subscriber_attr() {
	return sprintf(
		' data-umami-event-%1$s="%2$s"',
		_180C_UMAMI_SUBSCRIBER_KEY, // Littéral de constante, aucune entrée externe.
		esc_attr( _180c_umami_subscriber_value() )
	);
}

/**
 * Cookie éphémère signalant une connexion réussie.
 *
 * `wp_login` est un hook serveur qui se termine par une redirection : l'event
 * `login_success` ne peut donc pas être émis dans la même réponse. Ce cookie
 * est posé ici, puis consommé UNE SEULE FOIS au chargement suivant par
 * src/js/modules/umami-events.js, qui le supprime avant d'émettre.
 *
 * Volontairement NON httponly (lecture JS) et sans donnée : sa seule valeur est
 * « 1 ». Durée de vie courte, le temps d'une redirection.
 */
const _180C_UMAMI_LOGIN_COOKIE = '_180c_umami_login';

/**
 * Dit si le visiteur a accepté la mesure d'audience, d'après son cookie.
 *
 * SEUL point du thème où le consentement est lu en PHP, et il faut dire
 * pourquoi c'est légitime ici alors que c'est proscrit partout ailleurs : cette
 * lecture ne décide d'aucun rendu. Elle n'intervient que sur `wp_login`, donc
 * sur une requête POST de connexion, que WP Super Cache ne sert jamais depuis
 * le cache et dont la réponse n'est jamais mise en cache. Aucun risque de
 * figer le choix d'un visiteur pour les suivants.
 *
 * @return bool
 */
function _180c_umami_consent_granted() {
	if ( empty( $_COOKIE[ _180C_CONSENT_COOKIE ] ) ) {
		return false;
	}

	$raw = json_decode(
		urldecode( sanitize_text_field( wp_unslash( $_COOKIE[ _180C_CONSENT_COOKIE ] ) ) ),
		true
	);

	return is_array( $raw )
		&& isset( $raw['v'], $raw['analytics'] )
		&& (int) _180C_CONSENT_VERSION === (int) $raw['v']
		&& true === $raw['analytics'];
}

/**
 * Indique si la mesure Umami doit être active sur la requête courante.
 *
 * Exclusions :
 *  - identifiant de site vide (constante neutralisée) ;
 *  - contributeurs et plus (capacité `edit_posts`) : les allers-retours de la
 *    rédaction ne doivent pas peser dans les statistiques.
 *
 * @return bool
 */
function _180c_umami_is_enabled() {
	if ( '' === (string) _180C_UMAMI_WEBSITE_ID ) {
		return false;
	}

	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return false;
	}

	/**
	 * Permet de désactiver Umami (page spécifique, environnement, etc.).
	 *
	 * @param bool $enabled État courant.
	 */
	return (bool) apply_filters( '180c/umami_enabled', true );
}

/**
 * Construit les attributs HTML d'un event Umami déclaratif.
 *
 * Le script Umami écoute nativement les clics sur tout élément portant
 * `data-umami-event` ; chaque `data-umami-event-<clé>` devient une donnée
 * jointe. Aucun JS custom n'est nécessaire pour ces events.
 *
 * Retourne une chaîne VIDE si la mesure est désactivée, pour ne pas polluer le
 * HTML servi à la rédaction.
 *
 * IMPORTANT : la valeur retournée est déjà échappée (esc_attr sur chaque
 * portion) et doit être affichée telle quelle — comme _180c_render_svg_icon().
 *
 * @param string               $event Nom de l'event (snake_case).
 * @param array<string,string> $data  Données jointes, indexées par clé.
 * @return string Attributs prêts à être insérés dans une balise ouvrante.
 */
function _180c_umami_attrs( $event, $data = array() ) {
	if ( ! _180c_umami_is_enabled() ) {
		return '';
	}

	$out = sprintf( ' data-umami-event="%s"', esc_attr( $event ) );

	foreach ( (array) $data as $key => $value ) {
		if ( '' === (string) $value ) {
			continue;
		}
		$out .= sprintf(
			' data-umami-event-%1$s="%2$s"',
			esc_attr( str_replace( '_', '-', (string) $key ) ),
			esc_attr( (string) $value )
		);
	}

	// Dimension de segmentation ajoutée d'office : un seul point de passage pour
	// la vingtaine d'appels du thème, aucun site d'appel à modifier.
	return $out . _180c_umami_subscriber_attr();
}

/**
 * Indique si une URL pointe vers la page d'abonnement.
 *
 * Comparaison sur le chemin seul (ni schéma, ni host, ni querystring) pour
 * couvrir les variantes http/https et www.
 *
 * @param string $url URL à tester.
 * @return bool
 */
function _180c_umami_is_subscribe_url( $url ) {
	$path = wp_parse_url( (string) $url, PHP_URL_PATH );
	if ( ! $path ) {
		return false;
	}

	$target = wp_parse_url(
		(string) apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) ),
		PHP_URL_PATH
	);
	if ( ! $target ) {
		return false;
	}

	return untrailingslashit( $path ) === untrailingslashit( $target );
}

/**
 * Étiquette de position `subscribe_cta_click` pour un emplacement de menu.
 *
 * @param string $location Theme location WordPress.
 * @return string Position normalisée.
 */
function _180c_umami_menu_position( $location ) {
	$map = array(
		'primary'         => 'header',
		'side'            => 'side_menu',
		'footer-discover' => 'footer',
		'footer-editions' => 'footer',
		'footer-help'     => 'footer',
		'legals'          => 'footer',
	);

	return isset( $map[ $location ] ) ? $map[ $location ] : 'nav';
}

/**
 * Balise les liens d'abonnement des menus administrables.
 *
 * Couvre en un seul point tous les emplacements rendus par wp_nav_menu (header
 * primary, side menu, colonnes de footer) : inutile d'éditer chaque item côté
 * WP Admin. Les listes de repli hardcodées (voir inc/menus.php et
 * parts/footer/main.php) posent les mêmes attributs de leur côté.
 *
 * @param array    $atts Attributs du <a>.
 * @param WP_Post  $item Item de menu.
 * @param stdClass $args Arguments de wp_nav_menu.
 * @return array Attributs modifiés.
 */
function _180c_umami_nav_menu_attrs( $atts, $item, $args ) {
	if ( ! _180c_umami_is_enabled() ) {
		return $atts;
	}

	if ( empty( $atts['href'] ) || ! _180c_umami_is_subscribe_url( $atts['href'] ) ) {
		return $atts;
	}

	$location = isset( $args->theme_location ) ? (string) $args->theme_location : '';

	$atts['data-umami-event']          = 'subscribe_cta_click';
	$atts['data-umami-event-position'] = _180c_umami_menu_position( $location );

	// Même dimension que _180c_umami_attrs() : ce filtre construit ses attributs
	// à la main et n'emprunte donc pas ce helper.
	$atts[ 'data-umami-event-' . _180C_UMAMI_SUBSCRIBER_KEY ] = _180c_umami_subscriber_value();

	return $atts;
}
add_filter( 'nav_menu_link_attributes', '_180c_umami_nav_menu_attrs', 10, 3 );

/**
 * Pose le drapeau de connexion réussie (event `login_success`).
 *
 * Couvre toutes les entrées de connexion — formulaire du thème, WooCommerce,
 * wp-login.php — puisque toutes passent par `wp_login`.
 *
 * La capacité est testée sur l'objet utilisateur et non via current_user_can() :
 * au moment du hook, le contexte utilisateur courant n'est pas garanti.
 *
 * @param string  $login Identifiant de connexion (non utilisé, jamais mesuré).
 * @param WP_User $user  Utilisateur connecté.
 */
function _180c_umami_flag_login( $login, $user ) {
	if ( '' === (string) _180C_UMAMI_WEBSITE_ID || headers_sent() ) {
		return;
	}

	// Ce cookie sert exclusivement à émettre l'event Umami `login_success` :
	// sa finalité est la mesure d'audience, il ne relève donc d'aucune
	// exemption. Il était pourtant posé sans la moindre condition — un dépôt
	// sur le terminal, à finalité de mesure, sans consentement.
	if ( ! _180c_umami_consent_granted() ) {
		return;
	}

	if ( $user instanceof WP_User && user_can( $user, 'edit_posts' ) ) {
		return;
	}

	setcookie(
		_180C_UMAMI_LOGIN_COOKIE,
		'1',
		array(
			'expires'  => time() + 300,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		)
	);
}
add_action( 'wp_login', '_180c_umami_flag_login', 10, 2 );

/**
 * Marque la page de confirmation d'un abonnement (event `subscribe_complete`).
 *
 * Rend un marqueur invisible sur « order-received » quand la commande contient
 * un produit d'abonnement. L'émission — et surtout la déduplication — est
 * confiée au client : la page de remerciement peut être rechargée, et
 * src/js/modules/umami-events.js verrouille l'émission par commande via
 * sessionStorage.
 *
 * Aucune donnée personnelle : ni montant, ni client. L'ID de commande sert
 * uniquement de clé de déduplication locale et n'est jamais transmis à Umami ;
 * seule la dimension `offer` (slug produit) part avec l'event.
 *
 * @param int $order_id Identifiant de la commande.
 */
function _180c_umami_mark_subscribe_complete( $order_id ) {
	if ( ! _180c_umami_is_enabled() ) {
		return;
	}

	$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( ! function_exists( '_180c_order_has_subscription' ) || ! _180c_order_has_subscription( $order ) ) {
		return;
	}

	// Slug du premier produit d'abonnement de la commande : même identifiant
	// d'offre que celui émis par subscribe_offer_select / subscribe_payment_start.
	$offer_slug = '';
	if ( class_exists( 'WC_Subscriptions_Product' ) ) {
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product && WC_Subscriptions_Product::is_subscription( $product ) ) {
				$offer_slug = (string) $product->get_slug();
				break;
			}
		}
	}

	add_action(
		'wp_footer',
		function () use ( $order, $offer_slug ) {
			printf(
				'<div hidden data-umami-subscribe-complete="%1$s" data-umami-offer="%2$s"></div>',
				esc_attr( (string) $order->get_id() ),
				esc_attr( $offer_slug )
			);
		}
	);
}
add_action( 'woocommerce_thankyou', '_180c_umami_mark_subscribe_complete' );

/**
 * Expose le statut d'abonnement au module JS (`window._umamiSubscriber`).
 *
 * Les events déclaratifs portent la dimension dans leurs attributs ; ceux émis
 * en JS (recherche, newsletter, favoris, login, confirmation d'abonnement) n'ont
 * pas d'élément porteur et lisent donc ce global.
 *
 * Priorité 1 sur `wp_head` : le global doit exister avant TOUT script, aussi
 * bien le collecteur Umami (chargé en `defer` dans le <head>) que le bundle
 * principal qui contient umami-events.js.
 *
 * La valeur est encodée en JSON, donc rendue entre guillemets : le module JS
 * compare à la chaîne 'true'. Émettre le littéral nu `true` (booléen) ferait
 * échouer cette comparaison stricte et classerait TOUS les visiteurs en
 * non-abonnés, silencieusement.
 *
 * Mise en cache : la valeur ne varie que pour les sessions connectées, que
 * WP Super Cache ne sert jamais depuis le cache page — même invariant que celui
 * dont dépend déjà l'exclusion `edit_posts` de _180c_umami_is_enabled(). Un
 * visiteur anonyme obtient toujours 'false', valeur exacte pour lui.
 *
 * @return void
 */
function _180c_umami_subscriber_global() {
	if ( ! _180c_umami_is_enabled() ) {
		return;
	}

	printf(
		'<script>window._umamiSubscriber=%s;</script>' . "\n",
		wp_json_encode( _180c_umami_subscriber_value() ) // Chaîne 'true'/'false', encodage sûr.
	);
}
add_action( 'wp_head', '_180c_umami_subscriber_global', 1 );

/**
 * Origine du collecteur Umami Cloud (endpoint `/api/send`).
 *
 * Distincte de `_180C_UMAMI_SRC` : le script est servi par cloud.umami.is, mais
 * il POSTe ses hits sur gateway.umami.is. Deux origines, donc deux handshakes.
 */
if ( ! defined( '_180C_UMAMI_GATEWAY' ) ) {
	define( '_180C_UMAMI_GATEWAY', 'https://gateway.umami.is' );
}

/**
 * Injecte le chargeur Umami, sous condition de consentement.
 *
 * Miroir exact du dispositif GA4 (inc/analytics/ga4.php) : le <head> ne contient
 * qu'un objet inerte, `window._180c.umami`, et AUCUNE requête ne part avant que
 * la CMP appelle `load()` — préconnexions comprises.
 *
 * Les deux hints de préconnexion vivent donc dans `load()` et non dans le <head>.
 * C'est le point qui rendait le dispositif incohérent : un handshake TCP/TLS
 * vers `cloud.umami.is` et `gateway.umami.is` partait dès le premier octet de
 * la page, soit une adresse IP transmise à un tiers avant tout choix. Les
 * garder après consentement conserve leur bénéfice — PageSpeed mobile ne
 * relevait aucune origine préconnectée alors que la plus longue chaîne critique
 * se terminait sur `gateway.umami.is/api/send` à 514 ms.
 *
 * `crossorigin` sur la seule gateway : le hit est un POST CORS (fetch /
 * sendBeacon), alors que la balise <script> du collecteur n'a pas d'attribut
 * `crossorigin` et emprunte la connexion non anonyme. Se tromper d'attribut
 * ouvre une seconde connexion et annule le bénéfice.
 *
 * Priorité 2 sur `wp_head`, comme GA4 : après le global `_umamiSubscriber`
 * (priorité 1) et avant le bundle principal qui contient la CMP.
 *
 * @return void
 */
function _180c_umami_loader() {
	if ( ! _180c_umami_is_enabled() ) {
		return;
	}

	// La gateway n'existe que pour l'offre Cloud : une instance self-hosted
	// collecte sur sa propre origine, déjà couverte par celle du script.
	$origins = array( array( _180C_UMAMI_SRC, false ) );
	if ( 0 === strpos( _180C_UMAMI_SRC, 'https://cloud.umami.is' ) ) {
		$origins[] = array( _180C_UMAMI_GATEWAY, true );
	}

	$preconnect = array();
	foreach ( $origins as $origin ) {
		$parts = wp_parse_url( $origin[0] );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			continue;
		}
		$preconnect[] = array(
			'href'        => $parts['scheme'] . '://' . $parts['host'],
			'crossorigin' => $origin[1],
		);
	}
	?>
	<!-- Umami — aucun appel réseau avant consentement (préconnexions comprises). -->
	<script>
	(function () {
		var COOKIE = <?php echo wp_json_encode( _180C_CONSENT_COOKIE ); ?>;
		var COOKIE_VERSION = <?php echo (int) _180C_CONSENT_VERSION; ?>;
		var PRECONNECT = <?php echo wp_json_encode( $preconnect ); ?>;

		/**
		 * Lit le cookie de consentement et dit si la mesure est autorisée.
		 * Strictement la même lecture que inc/analytics/ga4.php : les deux
		 * outils doivent répondre au même choix, sans quoi un refus n'en
		 * couperait qu'un.
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

		window._180c = window._180c || {};
		window._180c.umami = {
			loaded: false,

			/**
			 * Pose les préconnexions puis injecte réellement le script Umami.
			 * Idempotent : ne fait rien si déjà chargé.
			 */
			load: function () {
				if (this.loaded) { return; }
				this.loaded = true;

				var head = document.head || document.getElementsByTagName('head')[0];

				PRECONNECT.forEach(function (hint) {
					var link = document.createElement('link');
					link.rel = 'preconnect';
					link.href = hint.href;
					if (hint.crossorigin) { link.crossOrigin = ''; }
					head.appendChild(link);
				});

				var tag = document.createElement('script');
				tag.defer = true;
				tag.src = <?php echo wp_json_encode( _180C_UMAMI_SRC ); ?>;
				tag.setAttribute('data-website-id', <?php echo wp_json_encode( _180C_UMAMI_WEBSITE_ID ); ?>);
				tag.setAttribute('data-tag', <?php echo wp_json_encode( _180C_UMAMI_TAG ); ?>);
				tag.setAttribute('data-domains', <?php echo wp_json_encode( _180C_UMAMI_DOMAINS ); ?>);
				head.appendChild(tag);
			}
		};

		// Visiteur ayant déjà accepté : on charge sans attendre la CMP, pour ne
		// pas perdre le pageview dans le délai d'exécution du bundle.
		if (analyticsGranted()) { window._180c.umami.load(); }
	})();
	</script>
	<?php
}
add_action( 'wp_head', '_180c_umami_loader', 2 );

/*
 * Plus de filtre `wp_resource_hints` ici : WordPress ne pose de `dns-prefetch`
 * automatique que sur l'hôte d'un script ENREGISTRÉ. Le script Umami ne l'étant
 * plus (il est injecté en JS après consentement), il n'y a plus de hint à
 * retirer — le filtre qui s'en chargeait est devenu sans objet.
 */
