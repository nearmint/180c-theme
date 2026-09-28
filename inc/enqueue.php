<?php
/**
 * Enqueue des assets compilés par Vite.
 *
 * Deux modes, transparents pour les templates :
 *  - DEV (HMR)  : si dist/hot existe (serveur `npm run dev` lancé), on charge
 *                 le client HMR Vite + les entrées depuis le serveur de dev.
 *  - PROD       : sinon on lit dist/.vite/manifest.json et on enqueue les
 *                 fichiers hashés.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fenêtre minimale entre deux purges du cache page, en secondes.
 *
 * Borne la boucle de purge qu'un appelant malveillant pourrait tenter avec le
 * paramètre public `deploy-cache-purge`. Trente secondes : sans commune mesure
 * avec l'espacement réel de deux déploiements (plusieurs minutes), donc sans
 * risque d'en bloquer un.
 */
const _180C_PURGE_MIN_INTERVAL = 30;

/**
 * Vide le cache page au premier passage suivant un déploiement.
 *
 * Les assets Vite sont hashés et le déploiement SFTP tourne en `mirror --delete` :
 * l'ancien `dist/assets/main-XXXX.js` disparaît du serveur au moment même où le
 * nouveau arrive. Or WP Super Cache continue de servir le HTML généré AVANT le
 * déploiement, qui référence l'ancien hash — désormais en 404. Le thème n'a donc
 * plus aucun JS sur les pages en cache (constaté le 2026-07-26 : CMP invisible,
 * liens `data-action` non interceptés, et l'ancien gtag.js du HTML périmé qui se
 * chargeait sans consentement).
 *
 * Pourquoi l'empreinte du manifest ne suffisait pas
 * ------------------------------------------------
 * Cette purge se déclenchait sur un changement du md5 de
 * `dist/.vite/manifest.json`. Un déploiement qui ne touche QUE du PHP laisse ce
 * manifest identique : aucune purge n'avait lieu, et l'ancien HTML était servi
 * indéfiniment. Le step « Purge du cache page » du workflow répondait 200 et
 * passait au vert sans rien purger — un step vert ne prouvait donc rien.
 * Constaté deux fois le 2026-08-29 (icône X du pied de page, puis l'entrée `/`
 * restée figée après un merge).
 *
 * Le signal retenu est désormais CAUSAL — « un déploiement a eu lieu » — et non
 * plus corrélé — « le JS a changé ». Le workflow appelle déjà
 * `https://www.180c.fr/?deploy-cache-purge=${GITHUB_RUN_ID}` juste après le
 * mirror (.github/workflows/deploy.yml) : on mémorise ce run id et on purge dès
 * qu'il diffère du dernier vu.
 *
 * Authenticité du paramètre
 * -------------------------
 * Il est public : n'importe qui peut appeler l'URL avec une valeur arbitraire.
 * Aucun secret n'est introduit ici — ce serait une autre option, avec une
 * surface et une rotation à maintenir. La protection repose sur trois points, et
 * surtout sur le constat que ce paramètre n'accorde AUCUNE capacité nouvelle :
 * forcer un rendu non caché est déjà trivial aujourd'hui avec n'importe quelle
 * query string unique, et c'est même plus efficace pour qui voudrait charger le
 * serveur. Le pire cas ici est un cache froid.
 *
 *  1. Contrainte de format : un identifiant de run GitHub est un entier long.
 *     Tout ce qui n'est pas 6 à 20 chiffres est ignoré sans autre effet.
 *  2. Fenêtre minimale entre deux purges : bornée à une purge par
 *     `_180C_PURGE_MIN_INTERVAL` secondes, ce qui empêche la boucle de purge.
 *     Les déploiements sont espacés de plusieurs minutes, la fenêtre ne peut pas
 *     en bloquer un en pratique.
 *  3. Idempotence : le run id n'est mémorisé QUE lorsqu'une purge a réellement
 *     eu lieu, et un id déjà vu ne purge jamais deux fois.
 *
 * Volontairement PAS de contrainte de croissance sur l'identifiant : elle serait
 * plus stricte, mais un unique appel portant un entier énorme figerait la
 * comparaison pour toujours et désactiverait silencieusement la purge — soit
 * exactement le défaut qu'on corrige ici, en pire.
 *
 * Les pages servies depuis le cache ne bootent pas WordPress ; c'est sans
 * conséquence puisque la requête de purge porte une query string, ce qui
 * contourne le cache par construction.
 *
 * @return void
 */
function _180c_purge_page_cache_on_deploy() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Paramètre de déploiement public, sans effet de bord autre qu'un vidage de cache ; cf. la note d'authenticité ci-dessus.
	$run_id = isset( $_GET['deploy-cache-purge'] ) ? sanitize_text_field( wp_unslash( $_GET['deploy-cache-purge'] ) ) : '';

	if ( '' === $run_id ) {
		return;
	}

	// 1. Format : identifiant de run GitHub (entier long).
	$length = strlen( $run_id );
	if ( ! ctype_digit( $run_id ) || $length < 6 || $length > 20 ) {
		return;
	}

	// 3. Idempotence : deux appels portant le même run id ne purgent qu'une fois.
	if ( get_option( '_180c_deploy_run_id' ) === $run_id ) {
		return;
	}

	// 2. Fenêtre minimale entre deux purges.
	$last = (int) get_option( '_180c_deploy_purged_at' );
	if ( $last && ( time() - $last ) < _180C_PURGE_MIN_INTERVAL ) {
		// Volontairement SANS mémoriser le run id : la purge reste due, un appel
		// ultérieur portant le même identifiant l'effectuera.
		return;
	}

	// Mémorisés AVANT la purge : si celle-ci échoue, on ne boucle pas à chaque
	// requête. `false` = pas d'autoload, ces options ne sont lues qu'ici.
	update_option( '_180c_deploy_run_id', $run_id, false );
	update_option( '_180c_deploy_purged_at', time(), false );

	// Fournie par WP Super Cache (absente en local, d'où la garde).
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
	}

	/**
	 * Déclenché après une purge du cache page consécutive à un déploiement.
	 *
	 * @param string $run_id Identifiant du run de déploiement.
	 */
	do_action( '180c/page_cache_purged', $run_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}
add_action( 'init', '_180c_purge_page_cache_on_deploy' );

/**
 * Injecte les données globales du thème (URL API REST, état connexion) dans
 * window._180c pour les modules JS.
 *
 * Utilise un script fantôme (handle sans fichier) pour profiter de
 * wp_add_inline_script() sans charger un JS supplémentaire.
 *
 * AUCUN nonce ici, volontairement. Ce HTML est servi par WP Super Cache depuis
 * un fichier statique qui survit à l'expiration du nonce (24 h au plus) : un
 * nonce inliné y devient périmé sans que rien ne l'indique, et tout appel qui
 * s'en sert échoue en 403. Les nonces sont récupérés à la demande sur
 * `GET /180c/v1/session` (route jamais cachée, cf. inc/rest/session.php), via
 * `ensureSession()` de src/js/modules/session.js.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_script( '180c-data', '', array(), _180C_VERSION, false );
		wp_add_inline_script(
			'180c-data',
			sprintf(
				// `_180cFavorites` : objet dédié attendu par le module favoris
				// ; alias additif de `_180c` (back-compat autres modules).
				// Object.assign et NON affectation directe : inc/analytics/ga4.php
				// pose déjà `_180c.ga4` / `_180c.userStatus` sur wp_head priorité 2,
				// donc AVANT ce script. Un `window._180c = {…}` les effaçait — le
				// `user_status` de recipe_view retombait systématiquement sur
				// 'anonymous' et la file d'events consent-gated était inatteignable.
				'window._180c = Object.assign(window._180c || {}, { restUrl: %1$s, isLoggedIn: %2$s, loginUrl: %3$s, carnetUrl: %4$s, nlMessages: %5$s });'
				. ' window._180cFavorites = { restUrl: %1$s, isLoggedIn: %2$s, loginUrl: %3$s, carnetUrl: %4$s };',
				wp_json_encode( esc_url_raw( rest_url( '180c/v1/' ) ) ),
				wp_json_encode( is_user_logged_in() ),
				wp_json_encode( esc_url_raw( home_url( '/connexion/' ) ) ),
				wp_json_encode( esc_url_raw( home_url( '/mon-carnet/' ) ) ),
				// Catalogue i18n newsletter (D6) : libellés traduits côté serveur,
				// consommés par les modules JS au lieu de chaînes en dur.
				wp_json_encode( function_exists( '_180c_newsletter_js_i18n' ) ? _180c_newsletter_js_i18n() : array() )
			)
		);
		wp_enqueue_script( '180c-data' );
	},
	5 // Priorité 5 pour être chargé avant 180c-main.
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$dev = _180c_vite_dev_server();

		// ---- Mode DEV : HMR via le serveur de dev Vite. ----
		// Version volontairement à null : les URLs du serveur de dev ne doivent
		// pas être versionnées (le HMR de Vite gère la fraîcheur des modules).
		if ( '' !== $dev ) {
			// Le client HMR de Vite (gère l'injection live du CSS et le rechargement).
			wp_enqueue_script( '180c-vite-client', $dev . '/@vite/client', array(), null, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			// Entrée principale (importe le CSS, injecté à chaud par Vite côté JS).
			wp_enqueue_script( '180c-main', $dev . '/src/js/main.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			return;
		}

		// ---- Mode PROD : assets hashés via le manifest. ----
		$main = _180c_vite_manifest_entry( 'src/js/main.js' );

		if ( ! $main ) {
			return;
		}

		// JS principal.
		//
		// Version à `null` — impératif, pas une commodité. Voir le bloc
		// « Pourquoi les entrées Vite ne portent JAMAIS de ?ver » plus bas.
		wp_enqueue_script(
			'180c-main',
			_180C_THEME_URI . '/dist/' . $main['file'],
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Nom de fichier hashé par Vite ; un ?ver dupliquerait le module ES.
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// CSS associés.
		if ( ! empty( $main['css'] ) ) {
			foreach ( $main['css'] as $i => $css_file ) {
				wp_enqueue_style(
					'180c-main-' . $i,
					_180C_THEME_URI . '/dist/' . $css_file,
					array(),
					_180C_VERSION
				);
			}
		}
	}
);

/**
 * Indique si la page courante a besoin du bundle CSS « commerce ».
 *
 * Volontairement PLUS étroit que `_180c_is_commerce_context()` (inc/perf-assets.php),
 * qui inclut les fiches recette pour conserver les assets Memberships du
 * paywall : aucun sélecteur de `src/css/commerce.css` ne peut matcher sur une
 * recette, et les recettes pèsent lourd dans le trafic. On s'en tient donc aux
 * vraies pages WooCommerce.
 *
 * @return bool
 */
function _180c_needs_commerce_styles() {
	$needs = (
		( function_exists( 'is_woocommerce' ) && is_woocommerce() )
		|| ( function_exists( 'is_cart' ) && is_cart() )
		|| ( function_exists( 'is_checkout' ) && is_checkout() )
		|| ( function_exists( 'is_account_page' ) && is_account_page() )
		|| is_singular( 'product' )
	);

	/**
	 * Permet de charger le bundle commerce sur une page éditoriale qui
	 * embarquerait un shortcode WooCommerce.
	 *
	 * @param bool $needs État courant.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (bool) apply_filters( '180c/needs_commerce_styles', $needs );
}

/**
 * Bundle CSS « commerce », chargé uniquement sur les pages WooCommerce.
 *
 * `main-*.css` est la seule feuille render-blocking du thème : PageSpeed mobile
 * la chiffrait à 42,8 Ko transférés / ~600 ms sur le chemin critique. Environ
 * 30 % de son poids (100 Ko bruts) ne servait que sur boutique / fiche produit /
 * panier / commande / Mon Compte. Ces feuilles vivent désormais dans une entrée
 * Vite dédiée (`src/css/commerce.css`), chargée après main.css et seulement
 * quand elle a une chance de matcher quelque chose.
 *
 * Priorité par défaut : la feuille doit arriver APRÈS `180c-main-0` dans le
 * <head> pour que la cascade reste identique à l'ancien fichier unique.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! _180c_needs_commerce_styles() ) {
			return;
		}

		$dev = _180c_vite_dev_server();

		// ---- Mode DEV : le serveur Vite sert le CSS comme module JS
		// (injection d'un <style> + HMR), d'où l'enqueue en script. ----
		if ( '' !== $dev ) {
			wp_enqueue_script( '180c-commerce', $dev . '/src/css/commerce.css', array(), null, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			return;
		}

		// ---- Mode PROD : entrée CSS pure, `file` pointe directement le .css. ----
		$commerce = _180c_vite_manifest_entry( 'src/css/commerce.css' );

		if ( ! $commerce || empty( $commerce['file'] ) ) {
			return;
		}

		wp_enqueue_style(
			'180c-commerce',
			_180C_THEME_URI . '/dist/' . $commerce['file'],
			array( '180c-main-0' ),
			_180C_VERSION
		);
	}
);

/**
 * Garantit le script `wc-add-to-cart` sur les pages du builder home.
 *
 * Les rails/grilles produits (carte _180c_block_render_product_card) rendent
 * un CTA AJAX (.ajax_add_to_cart) quand l'option WooCommerce « Activer l'ajout
 * au panier AJAX » est active. WooCommerce enqueue déjà ce handle globalement
 * dans ce cas ; cet enqueue conditionnel est un filet de sécurité ciblé sur la
 * home / les pages template-home (front_page, Gazette, Recettes, Boutique) et
 * reste un no-op si le script est déjà en file. La localisation
 * `wc_add_to_cart_params` est posée par WC (localize_printed_scripts itère tous
 * les handles enregistrés).
 *
 * Priorité 20 : après WC_Frontend_Scripts::load_scripts() (priorité par défaut).
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$is_home_builder = is_front_page() || is_page_template( 'template-home.php' );
		if ( ! $is_home_builder ) {
			return;
		}

		if ( 'yes' !== get_option( 'woocommerce_enable_ajax_add_to_cart' ) ) {
			return;
		}

		if ( ! wp_script_is( 'wc-add-to-cart', 'enqueued' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
		}
	},
	20
);

/**
 * Assets dédiés aux pages Design System.
 *
 * Enqueue conditionnel : uniquement sur les pages utilisant
 * template-design-system.php. La chrome de doc (src/css/design-system.css,
 * importée par src/js/design-system.js) s'ajoute au CSS global du thème déjà
 * chargé, sans le dupliquer.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_page_template( 'template-design-system.php' ) ) {
			return;
		}

		$dev = _180c_vite_dev_server();

		// ---- Mode DEV : HMR via le serveur de dev Vite. ----
		if ( '' !== $dev ) {
			wp_enqueue_script( '180c-ds', $dev . '/src/js/design-system.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			return;
		}

		// ---- Mode PROD : assets hashés via le manifest. ----
		$ds = _180c_vite_manifest_entry( 'src/js/design-system.js' );

		if ( ! $ds ) {
			return;
		}

		// Version à `null` : même raison que `180c-main` (entrée ES module hashée).
		wp_enqueue_script(
			'180c-ds',
			_180C_THEME_URI . '/dist/' . $ds['file'],
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Nom de fichier hashé par Vite ; un ?ver dupliquerait le module ES.
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		if ( ! empty( $ds['css'] ) ) {
			foreach ( $ds['css'] as $i => $css_file ) {
				wp_enqueue_style(
					'180c-ds-' . $i,
					_180C_THEME_URI . '/dist/' . $css_file,
					array(),
					_180C_VERSION
				);
			}
		}
	}
);

/**
 * Assets de l'éditeur de blocs (Gutenberg).
 *
 * Le fichier src/js/admin/index.js est l'entrée dédiée à l'éditeur
 * (enregistrement de variations, scripts d'édition des blocs custom). Chargée
 * uniquement dans l'éditeur via enqueue_block_editor_assets, en dev comme en prod.
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		$dev = _180c_vite_dev_server();

		// Dépendances WP exposant les globales window.wp.* consommées par
		// src/js/admin/index.js (enregistrement des blocs custom : aperçu SSR
		// + InspectorControls).
		$editor_deps = array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' );

		if ( '' !== $dev ) {
			wp_enqueue_script( '180c-vite-client', $dev . '/@vite/client', array(), null, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_script( '180c-admin', $dev . '/src/js/admin/index.js', $editor_deps, null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			return;
		}

		$admin = _180c_vite_manifest_entry( 'src/js/admin/index.js' );

		if ( ! $admin ) {
			return;
		}

		// Version à `null` : même raison que `180c-main` (entrée ES module hashée).
		wp_enqueue_script(
			'180c-admin',
			_180C_THEME_URI . '/dist/' . $admin['file'],
			$editor_deps,
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Nom de fichier hashé par Vite ; un ?ver dupliquerait le module ES.
			array( 'in_footer' => true )
		);

		if ( ! empty( $admin['css'] ) ) {
			foreach ( $admin['css'] as $i => $css_file ) {
				wp_enqueue_style(
					'180c-admin-' . $i,
					_180C_THEME_URI . '/dist/' . $css_file,
					array(),
					_180C_VERSION
				);
			}
		}
	}
);

/**
 * Marque les bundles Vite comme ES modules.
 *
 * Vite émet du code ESM (import.meta, top-level imports, et en dev le client
 * HMR). Sans type="module", le navigateur le parse comme script classique et
 * casse au premier import.meta, ce qui désactive l'intégralité du JS du thème.
 *
 * Pourquoi les entrées Vite ne portent JAMAIS de ?ver
 * ---------------------------------------------------
 * Le navigateur indexe le registre des modules ES par **URL complète, query
 * string comprise**. Les chunks lazy émis par Vite importent le chunk d'entrée
 * par chemin relatif nu (`from "./main-<hash>.js"`, cf. newsletter-*.js, qui
 * partage du code avec l'entrée). Si la balise `<script type="module">` pointe
 * `main-<hash>.js?ver=0.1.0`, le navigateur voit **deux modules distincts** pour
 * le même fichier et **évalue l'entrée deux fois**.
 *
 * Conséquences observées en production le 05/08/2026 :
 *  - side-menu.js liait deux listeners sur le burger → un clic appelait
 *    `toggle()` deux fois → le panneau s'ouvrait puis se refermait dans le même
 *    event, donnant l'illusion d'un bouton mort ;
 *  - order-attribution-consent.js injectait deux fois les scripts WooCommerce
 *    d'attribution → `NotSupportedError: the name "wc-order-attribution-inputs"
 *    has already been used with this registry`.
 *
 * Le cache-busting n'est pas perdu pour autant : Vite hashe le **contenu** dans
 * le nom de fichier (`main-BTrZL31h.js`), ce qui est strictement plus fiable
 * qu'un `?ver` calé sur `_180C_VERSION` (inchangé entre deux builds).
 *
 * Les feuilles de style, elles, gardent `_180C_VERSION` : le CSS n'a pas de
 * registre de modules, un doublon d'URL y est sans effet.
 */
add_filter(
	'script_loader_tag',
	function ( $tag, $handle ) {
		// `180c-commerce` n'est un script qu'en mode dev (le serveur Vite sert
		// le CSS sous forme de module JS) ; en prod c'est une feuille de style,
		// que ce filtre ne voit jamais.
		$module_handles = array( '180c-main', '180c-admin', '180c-vite-client', '180c-ds', '180c-commerce' );

		if ( in_array( $handle, $module_handles, true ) ) {
			return str_replace( '<script ', '<script type="module" ', $tag );
		}

		return $tag;
	},
	10,
	2
);

/**
 * Preload de la font critique (mode prod uniquement).
 *
 * Les fonts variables sont déclarées via @font-face dans src/css/fonts.css et
 * bundlées par Vite vers dist/assets/[name]-[hash].woff2. On glob les fichiers
 * présents pour récupérer leur nom hashé réel et émettre un <link rel="preload">.
 * Si l'on ajoute/remplace un fichier dans src/fonts/ et relance le build, la
 * preload reste alignée automatiquement.
 *
 * PERF — SEUL Oswald est préchargé, délibérément.
 *
 * Un <link rel="preload"> place la font en priorité `High` et la fait partir
 * AVANT même la feuille de style. Une trace Lighthouse mobile de la home a
 * montré les deux fonts démarrant à 2 424 ms (avant le CSS à 2 437 ms) et
 * monopolisant le lien jusqu'à 4 651 ms (Oswald) et 6 207 ms (Playfair), tandis
 * que l'image LCP — pourtant `fetchpriority=high` et demandée à 2 459 ms —
 * n'arrivait qu'à 9 205 ms, affamée en bande passante.
 *
 * Or les deux fonts sont en `font-display: swap` : elles ne bloquent jamais le
 * rendu. Précharger Playfair (104 Ko, corps de texte) préemptait donc 104 Ko de
 * bande passante haute priorité devant l'image LCP pour un gain de peinture nul.
 * Playfair est désormais chargée normalement, à la découverte par le CSS.
 *
 * Oswald (70 Ko) reste préchargée : c'est la font display du titre de hero,
 * au-dessus de la ligne de flottaison. Si le LCP mobile reste contraint par la
 * bande passante, la retirer d'ici est le levier suivant — au prix d'un FOUT
 * sur les titres.
 *
 * En mode dev (serveur Vite actif), les fonts sont servies par le serveur de
 * dev : on saute la preload des fichiers hashés (potentiellement obsolètes).
 *
 * Extensions par ordre de préférence : woff2 > woff > ttf.
 */
add_action(
	'wp_head',
	function () {
		// En dev, pas de preload des assets buildés (peuvent être périmés).
		if ( '' !== _180c_vite_dev_server() ) {
			return;
		}

		$assets_dir = _180C_THEME_DIR . '/dist/assets';
		if ( ! is_dir( $assets_dir ) ) {
			return;
		}

		// Voir le PHPDoc : Playfair est volontairement absente de cette liste.
		$prefixes = array( 'Oswald-VariableFont_wght' );
		$mime_map = array(
			'woff2' => 'font/woff2',
			'woff'  => 'font/woff',
			'ttf'   => 'font/ttf',
		);

		foreach ( $prefixes as $prefix ) {
			// Cherche la meilleure extension dispo, woff2 > woff > ttf.
			$found = '';
			$ext   = '';
			foreach ( array_keys( $mime_map ) as $candidate ) {
				$matches = glob( $assets_dir . '/' . $prefix . '-*.' . $candidate );
				if ( $matches ) {
					$found = basename( $matches[0] );
					$ext   = $candidate;
					break;
				}
			}

			if ( '' === $found ) {
				continue;
			}

			printf(
				'<link rel="preload" href="%s" as="font" type="%s" crossorigin>' . "\n",
				esc_url( _180C_THEME_URI . '/dist/assets/' . $found ),
				esc_attr( $mime_map[ $ext ] )
			);
		}
	},
	1
);
