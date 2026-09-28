<?php
/**
 * Helpers globaux du thème 180°C.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log structuré écrit dans debug.log si WP_DEBUG_LOG est activé.
 *
 * @param string $message Le message.
 * @param array  $context Contexte additionnel (sérialisé en JSON).
 * @param string $level   Niveau de log (info, warning, error).
 */
function _180c_log( $message, $context = array(), $level = 'info' ) {
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return;
	}

	$entry = sprintf(
		'[%s] [%s] [180c] %s %s',
		gmdate( 'Y-m-d H:i:s' ),
		strtoupper( $level ),
		$message,
		$context ? wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : ''
	);

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $entry );
}

/**
 * Slugs des plans WooCommerce Memberships valant « abonné recettes ».
 *
 * Source unique de la liste : `_180c_is_recipe_subscriber()`,
 * `_180c_active_recipe_plan_slug()` et la synchro du tag premium Mailchimp
 * (inc/mailchimp/membership-sync.php) doivent raisonner sur EXACTEMENT le même
 * périmètre, sans quoi le tag et le paywall divergent.
 *
 * Plans historiques en cours de consolidation vers `abonne-recettes`
 * (cf CLAUDE.md). Filtrable via `180c/recipe_subscriber_plan_slugs`.
 *
 * @return string[] Slugs de plans.
 */
function _180c_recipe_plan_slugs(): array {
	return (array) apply_filters(
		'180c/recipe_subscriber_plan_slugs', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
		array(
			'abonne-recettes',                                // cible v2 (post-migration).
			'abonnement-en-ligne',                            // historique.
			'abonnes-recettes-en-ligne',                      // historique.
			'abonnement-aux-recettes-en-ligne-pour-3-mois',   // historique.
			'abonnes-illimite-recettes-en-ligne',             // historique.
		)
	);
}

/**
 * Vérifie si l'utilisateur courant est abonné aux recettes.
 *
 * Plans historiques en cours de consolidation vers `abonne-recettes`
 * (cf CLAUDE.md). À nettoyer après migration : ne garder que `abonne-recettes`
 * dans la liste, retirer les 4 slugs historiques.
 *
 * Le filter `180c/recipe_subscriber_plan_slugs` permet d'étendre ou de
 * restreindre la liste sans toucher au code.
 *
 * @return bool
 */
function _180c_is_recipe_subscriber() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( ! function_exists( 'wc_memberships_is_user_active_member' ) ) {
		return false;
	}

	$plan_slugs = _180c_recipe_plan_slugs();

	// Note : wc_memberships_is_user_active_member() ne prend qu'un seul slug ;
	// on itère donc plutôt que de lui passer un array (qui ferait toujours false).
	$user_id = get_current_user_id();
	foreach ( (array) $plan_slugs as $slug ) {
		if ( wc_memberships_is_user_active_member( $user_id, $slug ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Retourne le slug du plan d'adhésion recettes actif pour un utilisateur.
 *
 * Dérive le label canonique `subscription_plan` consommé par le tracking GA4
 *  à partir du **vrai** membership actif, plutôt
 * que d'un slug codé en dur. Itère la même liste filtrable que
 * `_180c_is_recipe_subscriber()` et renvoie le premier plan actif trouvé.
 *
 * @param int $user_id ID utilisateur. 0 = utilisateur courant.
 * @return string Slug du plan actif, ou chaîne vide si non-abonné.
 */
function _180c_active_recipe_plan_slug( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	if ( ! $user_id || ! function_exists( 'wc_memberships_is_user_active_member' ) ) {
		return '';
	}

	$plan_slugs = _180c_recipe_plan_slugs();

	foreach ( (array) $plan_slugs as $slug ) {
		if ( wc_memberships_is_user_active_member( $user_id, $slug ) ) {
			return (string) $slug;
		}
	}

	return '';
}

/**
 * Retourne le statut d'abonnement courant d'un utilisateur (custom dimension GA4).
 *
 * Alimente la dimension user-scoped `subscription_status` .
 * Lecture HPOS-safe via `wcs_get_users_subscriptions()` (jamais de postmeta). Si
 * l'utilisateur a plusieurs abonnements, le statut le plus « actif » prime selon
 * l'ordre de priorité défini.
 *
 * @param int $user_id ID utilisateur. 0 = utilisateur courant.
 * @return string Statut (`active`/`on-hold`/`pending-cancel`/`cancelled`/`expired`…) ou '' si aucun abonnement.
 */
function _180c_user_subscription_status( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	if ( ! $user_id || ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return '';
	}

	$subscriptions = wcs_get_users_subscriptions( $user_id );
	if ( empty( $subscriptions ) ) {
		return '';
	}

	$found = array();
	foreach ( $subscriptions as $subscription ) {
		$found[ $subscription->get_status() ] = true;
	}

	// Le statut le plus engageant prime quand plusieurs abonnements coexistent.
	$priority = array( 'active', 'pending-cancel', 'on-hold', 'pending', 'expired', 'cancelled' );
	foreach ( $priority as $status ) {
		if ( isset( $found[ $status ] ) ) {
			return $status;
		}
	}

	// Statut hors énumération connue : renvoyer le premier rencontré.
	return (string) array_key_first( $found );
}

/**
 * Indique si le mode sombre est actif pour la requête courante.
 *
 * Lit le cookie `theme-mode`. Valeurs : 'auto' (défaut), 'light', 'dark'.
 * En mode 'auto', retourne false (la détection réelle est faite côté JS/CSS).
 *
 * @return bool True si le cookie indique explicitement 'dark'.
 */
function _180c_is_dark_mode() {
	$theme_mode = isset( $_COOKIE['theme-mode'] ) ? sanitize_key( $_COOKIE['theme-mode'] ) : 'auto';
	return 'dark' === $theme_mode;
}

/**
 * Retourne le contenu inline d'une icône SVG depuis src/images/icons/.
 *
 * @param string $name Nom de l'icône sans extension (ex: 'user', 'cart').
 * @return string Contenu SVG inline sanitisé, ou chaîne vide si introuvable.
 */
function _180c_render_svg_icon( $name ) {
	$path = _180C_THEME_DIR . '/src/images/icons/' . sanitize_file_name( $name ) . '.svg';

	if ( ! file_exists( $path ) ) {
		return '';
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$svg = file_get_contents( $path );

	if ( false === $svg ) {
		return '';
	}

	return $svg;
}

/**
 * Identifiant du `<symbol>` du logo dans le sprite SVG.
 */
const _180C_LOGO_SYMBOL_ID = 'logo-180c';

/**
 * `viewBox` du logo, lu dans le fichier source.
 *
 * Lu et non recopié en dur : le fichier SVG reste la seule source de vérité,
 * et un futur recadrage du logo n'a pas à être répercuté ici. Mémorisé en
 * statique — la valeur sert au sprite et à chacune de ses instances.
 *
 * @return string `viewBox`, ou chaîne vide si le fichier est illisible.
 */
function _180c_logo_viewbox(): string {
	static $viewbox = null;

	if ( null !== $viewbox ) {
		return $viewbox;
	}

	$viewbox = '';
	$path    = _180C_THEME_DIR . '/src/images/icons/' . _180C_LOGO_SYMBOL_ID . '.svg';

	if ( file_exists( $path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$svg = (string) file_get_contents( $path );
		if ( preg_match( '~<svg[^>]*\sviewBox="([^"]+)"~i', $svg, $m ) ) {
			$viewbox = $m[1];
		}
	}

	return $viewbox;
}

/**
 * Émet le sprite SVG contenant le logo, une seule fois par page.
 *
 * Le tracé du logo pèse ~8 Ko et était inliné À L'IDENTIQUE trois fois par page
 * (en-tête, pied de page, modale de consentement), soit ~24 Ko de HTML mis en
 * cache pour un seul dessin. Il est désormais déclaré une fois ici, et les trois
 * emplacements le référencent par `<use>`.
 *
 * Le fichier `src/images/icons/logo-180c.svg` reste la source unique : seule
 * l'enveloppe `<svg>` racine est remplacée par `<symbol>`, le contenu n'est
 * jamais recopié dans le PHP.
 *
 * Le sprite est masqué par `position: absolute` + dimensions nulles, et NON par
 * `display: none` : plusieurs navigateurs refusent de résoudre un `<use>` qui
 * pointe vers un symbole dans un arbre en `display: none`.
 *
 * Rendu sur `wp_body_open`, donc avant l'en-tête — un `<use>` doit suivre la
 * déclaration de son symbole dans le document.
 *
 * @return void
 */
function _180c_render_logo_sprite() {
	static $done = false;

	if ( $done ) {
		return;
	}
	$done = true;

	$path = _180C_THEME_DIR . '/src/images/icons/' . _180C_LOGO_SYMBOL_ID . '.svg';

	if ( ! file_exists( $path ) ) {
		return;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$svg = file_get_contents( $path );

	if ( false === $svg ) {
		return;
	}

	// Contenu entre la balise <svg> ouvrante et sa fermeture : les <path>, sans
	// l'enveloppe, que <symbol> remplace.
	$inner = preg_replace( '~^.*?<svg[^>]*>(.*)</svg>\s*$~s', '$1', $svg );

	if ( null === $inner || $inner === $svg ) {
		return;
	}

	printf(
		'<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false"><symbol id="%1$s" viewBox="%2$s">%3$s</symbol></svg>' . "\n",
		esc_attr( _180C_LOGO_SYMBOL_ID ),
		esc_attr( _180c_logo_viewbox() ),
		$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique du thème, non issu d'une saisie.
	);
}
add_action( 'wp_body_open', '_180c_render_logo_sprite', 1 );

/**
 * Rend une instance du logo, référencée depuis le sprite.
 *
 * Remplace `_180c_render_svg_icon( 'logo-180c' )` sur les trois emplacements.
 * Les attributs de l'enveloppe reproduisent ceux du fichier source :
 *   - `fill="currentColor"` — le logo suit la couleur du texte, donc le thème
 *     clair / sombre. Il doit être porté par CHAQUE instance : `currentColor`
 *     se résout sur l'élément qui l'emploie, pas sur le symbole ;
 *   - `viewBox` — conservé sur l'instance pour préserver le ratio intrinsèque
 *     dont dépend le dimensionnement CSS (`.site-header__logo > svg`, etc.),
 *     mais avec son ORIGINE RAMENÉE À ZÉRO. C'est indispensable : le viewBox du
 *     fichier est `128.54 172.70 538.18 213.99`, d'origine non nulle. Recopié
 *     tel quel sur l'instance, il s'appliquait DEUX fois — une fois par le
 *     `<symbol>`, qui replace le tracé dans un repère commençant à (0,0), puis
 *     une fois par l'instance, qui recadrait à partir de (128.54, 172.70) et ne
 *     laissait voir qu'un mince filet du dessin. Constaté à l'écran, puis
 *     confirmé au `getBBox()` : contenu en (93,39)-(446,174) contre une fenêtre
 *     démarrant en (128.54,172.70). Seules les DIMENSIONS du viewBox sont donc
 *     reprises ici ; le symbole conserve, lui, le viewBox d'origine ;
 *   - `aria-hidden` / `focusable` — le nom accessible est porté à côté, par le
 *     `screen-reader-text` de l'en-tête et du pied, par l'`aria-label` du
 *     conteneur `role="img"` dans la modale de consentement.
 *
 * @return string HTML du <svg><use></use></svg>.
 */
function _180c_render_logo() {
	// Origine ramenée à (0,0) : cf. l'explication du viewBox dans la docbloc.
	$parts = preg_split( '~\s+~', trim( _180c_logo_viewbox() ) );

	if ( ! is_array( $parts ) || 4 !== count( $parts ) ) {
		return '';
	}

	return sprintf(
		'<svg viewBox="0 0 %1$s %2$s" fill="currentColor" aria-hidden="true" focusable="false"><use href="#%3$s"></use></svg>',
		esc_attr( $parts[2] ),
		esc_attr( $parts[3] ),
		esc_attr( _180C_LOGO_SYMBOL_ID )
	);
}

/**
 * Détermine si une recette doit être derrière paywall pour l'utilisateur courant.
 *
 * Inverse de _180c_user_has_recipe_access() (cf. inc/recipe-access.php), qui
 * centralise toute la logique d'accès. Conservé pour rétro-compatibilité des
 * templates qui raisonnent « verrouillé / déverrouillé ».
 *
 * @param int|null $post_id ID de la recette (par défaut : courante).
 * @return bool True si la recette est verrouillée pour l'utilisateur.
 */
function _180c_recipe_is_paywalled( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id || get_post_type( $post_id ) !== 'recipe' ) {
		return false;
	}

	return ! _180c_user_has_recipe_access( $post_id );
}

/**
 * Calcule le temps total d'une recette (préparation + cuisson + repos).
 *
 * Lit les champs méta optionnels `recipe_prep_time` / `recipe_cook_time` /
 * `recipe_rest_time`. Ces champs ne font pas partie du modèle ACF; la
 * fonction renvoie 0 tant qu'ils ne sont pas alimentés (extension de modèle +
 * migration). Le template n'affiche les temps que s'ils sont > 0.
 *
 * @param int|null $post_id ID de la recette (par défaut : courante).
 * @return int Durée totale en minutes.
 */
function _180c_recipe_total_time( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	if ( ! function_exists( 'get_field' ) ) {
		return 0;
	}
	$prep = (int) get_field( 'recipe_prep_time', $post_id );
	$cook = (int) get_field( 'recipe_cook_time', $post_id );
	$rest = (int) get_field( 'recipe_rest_time', $post_id );
	return $prep + $cook + $rest;
}

/**
 * Découpe une ligne d'ingrédient brute en quantité de tête + reste.
 *
 * Le modèle ACF stocke chaque ingrédient comme une ligne libre
 * (`ingredients_groups → items → line`, ex. « 300 g de petits pois écossés »).
 * On extrait le nombre de tête (entier, décimal `1,5`/`1.5`, ou fraction `1/2`)
 * pour afficher la quantité dans une colonne dédiée. `qty_value` (float) reste
 * exposée pour d'éventuelles consommations futures (apps, REST).
 *
 * @param string $line Ligne d'ingrédient brute.
 * @return array{qty_label:string,qty_value:float,rest:string} Décomposition.
 *               qty_value vaut 0.0 si aucune quantité de tête n'est détectée.
 */
function _180c_recipe_parse_ingredient_line( $line ) {
	$line = trim( (string) $line );

	$result = array(
		'qty_label' => '',
		'qty_value' => 0.0,
		'rest'      => $line,
	);

	if ( '' === $line ) {
		return $result;
	}

	// Quantité de tête : fraction (1/2), décimale (1,5 / 1.5) ou entier (300).
	if ( ! preg_match( '#^(\d+\s*/\s*\d+|\d+(?:[.,]\d+)?)\s*(.*)$#u', $line, $m ) ) {
		return $result;
	}

	$qty_label = trim( $m[1] );
	$rest      = trim( $m[2] );

	// Conversion en valeur numérique.
	if ( false !== strpos( $qty_label, '/' ) ) {
		list( $num, $den ) = array_map( 'trim', explode( '/', $qty_label, 2 ) );
		$den               = (float) $den;
		$qty_value         = $den ? ( (float) $num / $den ) : 0.0;
	} else {
		$qty_value = (float) str_replace( ',', '.', $qty_label );
	}

	$result['qty_label'] = $qty_label;
	$result['qty_value'] = $qty_value;
	$result['rest']      = $rest;

	return $result;
}

/**
 * Formate une durée en minutes en chaîne lisible (ex : 1h30, 45 min).
 *
 * @param int $minutes Durée en minutes.
 * @return string Durée formatée.
 */
function _180c_format_duration( $minutes ) {
	$minutes = (int) $minutes;
	if ( $minutes <= 0 ) {
		return '';
	}
	if ( $minutes < 60 ) {
		/* translators: %d: nombre de minutes */
		return sprintf( __( '%d min', '180c' ), $minutes );
	}
	$hours = (int) floor( $minutes / 60 );
	$mins  = $minutes % 60;
	if ( 0 === $mins ) {
		/* translators: %d: nombre d'heures */
		return sprintf( __( '%dh', '180c' ), $hours );
	}
	/* translators: 1: heures, 2: minutes sur 2 chiffres */
	return sprintf( __( '%1$dh%2$02d', '180c' ), $hours, $mins );
}

/**
 * Rend la card HTML d'une recette à partir de son ID.
 *
 * Wrapper autour de _180c_block_render_recipe_card() pour un usage dans les templates.
 *
 * @param int    $post_id       ID de la recette.
 * @param string $size          Variante de taille : 'sm' | 'md' | 'lg' (défaut 'md').
 * @param string $heading_level Niveau du titre : 'h2'|'h3'|'h4' (défaut 'h3').
 * @return string HTML de la card, ou chaîne vide si introuvable.
 */
function _180c_render_recipe_card( $post_id, $size = 'md', $heading_level = 'h3' ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return '';
	}

	$recipe = get_post( $post_id );
	if ( ! $recipe || 'recipe' !== get_post_type( $recipe ) ) {
		return '';
	}

	if ( ! function_exists( '_180c_block_render_recipe_card' ) ) {
		require_once _180C_THEME_DIR . '/inc/blocks/_helpers.php';
	}

	return _180c_block_render_recipe_card( $recipe, $size, $heading_level );
}

/**
 * Rend une pagination accessible et stylée (composant BEM `.pagination`).
 *
 * Wrapper unifié autour de paginate_links( ['type' => 'array'] ) qui :
 *   - enveloppe la liste dans <nav aria-label> + <ul.pagination> ;
 *   - normalise les liens en <a class="pagination__link"> + <span class="...--current">
 *     (aria-current="page" sur l'élément courant) ;
 *   - reconnaît les liens prev/next (paginate_links les produit avec
 *     class="prev page-numbers" / "next page-numbers") et leur ajoute le
 *     modifier `--prev`/`--next` ;
 *   - retourne une chaîne vide si total ≤ 1.
 *
 * Cible-tactile 44×44 px et états (hover / focus / current) sont définis
 * dans src/css/components/pagination.css.
 *
 * @param array $args {
 *     Arguments compatibles avec paginate_links() + extensions BEM.
 *
 *     @type string $base        Base d'URL (avec marqueur %_%).
 *     @type string $format      Format remplaçant %_% (ex. '?paged=%#%').
 *     @type int    $current     Page courante (1+).
 *     @type int    $total       Nombre total de pages.
 *     @type array  $add_args    Query vars à préserver (ex. autre section).
 *     @type string $add_fragment Fragment ajouté à chaque lien (#articles…).
 *     @type string $aria_label  Libellé du <nav> (défaut « Pagination »).
 * }
 * @return string HTML du <nav> (chaîne vide si pagination inutile).
 */
function _180c_render_pagination( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'base'         => '',
			'format'       => '?paged=%#%',
			'current'      => 1,
			'total'        => 1,
			'add_args'     => array(),
			'add_fragment' => '',
			'aria_label'   => __( 'Pagination', '180c' ),
		)
	);

	$total = (int) $args['total'];
	if ( $total < 2 ) {
		return '';
	}

	$paginate_args = array(
		'base'         => $args['base'],
		'format'       => $args['format'],
		'current'      => max( 1, (int) $args['current'] ),
		'total'        => $total,
		'add_args'     => is_array( $args['add_args'] ) ? array_filter( $args['add_args'] ) : array(),
		'add_fragment' => (string) $args['add_fragment'],
		'type'         => 'array',
		'mid_size'     => 1,
		'end_size'     => 1,
		'prev_text'    => '<span class="sr-only">' . esc_html__( 'Page précédente', '180c' ) . '</span><span aria-hidden="true">&larr;</span>',
		'next_text'    => '<span class="sr-only">' . esc_html__( 'Page suivante', '180c' ) . '</span><span aria-hidden="true">&rarr;</span>',
	);

	$links = paginate_links( $paginate_args );
	if ( empty( $links ) || ! is_array( $links ) ) {
		return '';
	}

	$items = '';
	foreach ( $links as $link_html ) {
		$class = 'pagination__link';

		// Reconnaissance fine du type de lien à partir des classes que
		// paginate_links pose lui-même : "page-numbers current", "prev …",
		// "next …", "dots …".
		if ( false !== strpos( $link_html, 'class="page-numbers current"' )
			|| false !== strpos( $link_html, "class='page-numbers current'" ) ) {
			$class .= ' pagination__link--current';
			// paginate_links() pose la classe `current` mais pas aria-current.
			$link_html = preg_replace(
				'/<span([^>]*class="[^"]*page-numbers current[^"]*"[^>]*)>/',
				'<span$1 aria-current="page">',
				$link_html
			);
		} elseif ( false !== strpos( $link_html, 'prev page-numbers' ) ) {
			$class .= ' pagination__link--prev';
		} elseif ( false !== strpos( $link_html, 'next page-numbers' ) ) {
			$class .= ' pagination__link--next';
		} elseif ( false !== strpos( $link_html, 'page-numbers dots' ) ) {
			$class .= ' pagination__link--dots';
		}

		// Remplace la classe page-numbers de paginate_links par notre BEM
		// pour piloter le style sans !important ni surcharges fragiles.
		$link_html = preg_replace(
			'/class=(["\'])page-numbers([^"\']*)\1/',
			'class=$1' . esc_attr( $class ) . '$1',
			$link_html
		);

		$items .= '<li class="pagination__item">' . $link_html . '</li>';
	}

	return sprintf(
		'<nav class="pagination" aria-label="%1$s"><ul class="pagination__list" role="list">%2$s</ul></nav>',
		esc_attr( $args['aria_label'] ),
		$items
	);
}

/**
 * Rend la card HTML d'un article (post natif) à partir de son ID.
 *
 * Wrapper autour de _180c_block_render_article_card() — pendant
 * naturel de _180c_render_recipe_card() pour les pages auteur et toute autre
 * grille d'articles.
 *
 * @param int    $post_id       ID de l'article.
 * @param string $size          Variante de taille : 'sm' | 'md' | 'lg' (défaut 'md').
 * @param string $heading_level Niveau du titre : 'h2'|'h3'|'h4' (défaut 'h3').
 * @return string HTML de la card, ou chaîne vide si introuvable.
 */
function _180c_render_article_card( $post_id, $size = 'md', $heading_level = 'h3' ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return '';
	}

	$post = get_post( $post_id );
	if ( ! $post || 'post' !== get_post_type( $post ) ) {
		return '';
	}

	if ( ! function_exists( '_180c_block_render_article_card' ) ) {
		require_once _180C_THEME_DIR . '/inc/blocks/_helpers.php';
	}

	return _180c_block_render_article_card( $post_id, $size, $heading_level );
}

/**
 * Vérifie si une recette est dans les favoris du user courant.
 *
 * @param int      $recipe_id ID de la recette.
 * @param int|null $user_id   ID de l'utilisateur (par défaut : user courant).
 * @return bool True si la recette est dans les favoris.
 */
function _180c_is_recipe_favorite( $recipe_id, $user_id = null ) {
	global $wpdb;

	$recipe_id = absint( $recipe_id );
	$user_id   = $user_id ? absint( $user_id ) : get_current_user_id();

	if ( ! $user_id || ! $recipe_id ) {
		return false;
	}

	$table = $wpdb->prefix . 'user_favorites';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$result = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d AND recipe_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id,
			$recipe_id
		)
	);

	return ! empty( $result );
}

/**
 * Récupère la liste des IDs de recettes favorites d'un utilisateur.
 *
 * @param int|null $user_id ID de l'utilisateur (par défaut : user courant).
 * @return int[] Tableau d'IDs de recettes, trié par date d'ajout décroissante.
 */
function _180c_get_user_favorite_ids( $user_id = null ) {
	global $wpdb;

	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

	if ( ! $user_id ) {
		return array();
	}

	$table = $wpdb->prefix . 'user_favorites';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT recipe_id FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id
		)
	);

	return array_map( 'intval', (array) $ids );
}

/**
 * Récupère un slug de l'asset compilé via le manifest Vite.
 *
 * @param string $entry Entrée Vite (ex: 'src/js/main.js').
 * @return array|null { file, css?, imports? } ou null si introuvable.
 */
function _180c_vite_manifest_entry( $entry ) {
	$manifest_path = _180C_THEME_DIR . '/dist/.vite/manifest.json';

	if ( ! file_exists( $manifest_path ) ) {
		return null;
	}

	static $manifest = null;
	if ( null === $manifest ) {
		$manifest = json_decode( file_get_contents( $manifest_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	return $manifest[ $entry ] ?? null;
}

/**
 * Retourne l'origine du serveur de dev Vite si actif, sinon une chaîne vide.
 *
 * Le serveur de dev écrit `dist/hot` (plugin hotFile dans vite.config.js)
 * contenant son origine (ex. http://localhost:5173). Sa présence bascule le
 * thème en mode HMR ; son absence = mode production (lecture du manifest).
 *
 * @return string Origine du serveur de dev (sans slash final) ou ''.
 */
function _180c_vite_dev_server() {
	static $origin = null;

	if ( null !== $origin ) {
		return $origin;
	}

	$hot_path = _180C_THEME_DIR . '/dist/hot';

	if ( ! is_readable( $hot_path ) ) {
		$origin = '';
		return $origin;
	}

	$origin = rtrim( trim( (string) file_get_contents( $hot_path ) ), '/' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	return $origin;
}

/**
 * Construit le markup d'un fil d'Ariane (.breadcrumb, séparateurs chevron).
 *
 * Composant réutilisable. Le dernier item (sans 'url') est la page courante :
 * rendu non cliquable avec aria-current="page". Retourne '' si moins de deux
 * items (pas de fil pertinent).
 *
 * @param array $items Liste ordonnée d'items { label:string, url?:string }.
 * @return string HTML échappé.
 */
function _180c_breadcrumb_markup( array $items ): string {
	$items = array_values(
		array_filter(
			$items,
			static function ( $item ) {
				return ! empty( $item['label'] );
			}
		)
	);

	if ( count( $items ) < 2 ) {
		return '';
	}

	$last = count( $items ) - 1;
	$html = '<nav class="breadcrumb" aria-label="' . esc_attr__( 'Fil d’Ariane', '180c' ) . '">'
		. '<div class="site-container breadcrumb__inner">';

	foreach ( $items as $index => $item ) {
		if ( $index > 0 ) {
			$html .= '<span class="breadcrumb__sep" aria-hidden="true">›</span>';
		}

		if ( $index !== $last && ! empty( $item['url'] ) ) {
			$html .= sprintf(
				'<a class="breadcrumb__item" href="%s">%s</a>',
				esc_url( $item['url'] ),
				esc_html( $item['label'] )
			);
		} elseif ( $index === $last ) {
			$html .= sprintf(
				'<span class="breadcrumb__item" aria-current="page">%s</span>',
				esc_html( $item['label'] )
			);
		} else {
			// Nœud intermédiaire sans URL résolue : libellé seul, jamais aria-current.
			$html .= sprintf(
				'<span class="breadcrumb__item">%s</span>',
				esc_html( $item['label'] )
			);
		}
	}

	$html .= '</div></nav>';

	return $html;
}

/**
 * IDs des pages « home de section » (profondeur 1), résolues par slug.
 *
 * Ces pages servent de nœuds de section dans le fil d'Ariane (Recettes,
 * Boutique, Gazette) et sont par ailleurs masquées du fil quand elles sont la
 * page courante (cf. _180c_should_show_breadcrumb()). Slugs confirmés Phase 0 :
 * la page Gazette est `la-gazette` (cf. inc/menus.php).
 *
 * @return array<string,int> Clé de section => ID de page (présent si résolu).
 */
function _180c_breadcrumb_section_pages(): array {
	$slugs = array(
		'recettes' => 'recettes',
		'boutique' => 'boutique',
		'gazette'  => 'la-gazette',
	);

	$out = array();
	foreach ( $slugs as $key => $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post ) {
			$out[ $key ] = (int) $page->ID;
		}
	}

	return $out;
}

/**
 * Le fil d'Ariane doit-il s'afficher sur le contexte courant ?
 *
 * Masqué uniquement sur la front page et l'index du blog. Les homes de section
 * (Recettes, Articles/Gazette, Boutique) AFFICHENT le fil au-dessus du footer
 * (« Accueil › Section »), avec parité JSON-LD BreadcrumbList.
 *
 * @return bool
 */
function _180c_should_show_breadcrumb(): bool {
	if ( is_front_page() || is_home() ) {
		return false;
	}

	/**
	 * Permet de forcer l'affichage/masquage du fil d'Ariane sur un contexte.
	 *
	 * @param bool $show Visibilité par défaut.
	 */
	return (bool) apply_filters( '_180c_should_show_breadcrumb', true );
}

/**
 * Terme principal d'un post pour une taxonomie donnée.
 *
 * Pour la taxonomie `category`, réutilise le mécanisme « catégorie principale »
 * (_180c_article_primary_category : terme le plus profond, hors
 * catégorie par défaut). Pour les autres taxonomies (recipe_category,
 * product_cat…), aucun mécanisme « primary » natif n'existe : on retient le
 * premier terme assigné. (Pas de Yoast dans ce thème — décision actée.)
 *
 * @param int    $post_id  ID du post.
 * @param string $taxonomy Slug de la taxonomie.
 * @return WP_Term|null
 */
function _180c_breadcrumb_primary_term( int $post_id, string $taxonomy ): ?WP_Term {
	if ( 'category' === $taxonomy && function_exists( '_180c_article_primary_category' ) ) {
		$term = _180c_article_primary_category( $post_id );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}

	$terms = get_the_terms( $post_id, $taxonomy );
	if ( is_array( $terms ) && ! empty( $terms ) && $terms[0] instanceof WP_Term ) {
		return $terms[0];
	}

	return null;
}

/**
 * Construit le fil d'Ariane ordonné de la page courante.
 *
 * Source de vérité UNIQUE du fil : consommée à la fois par le rendu visuel
 * (_180c_render_breadcrumb) et par le JSON-LD BreadcrumbList du module SEO
 * (_180c_schema_breadcrumbs), garantissant que l'affichage et les données
 * structurées ne divergent jamais. Le dernier item est la page courante :
 * son `url` est vide (rendu non cliquable + aria-current="page").
 *
 * @return array<int,array{label:string,url:string}>
 */
function _180c_get_breadcrumb_items(): array {
	$sections = _180c_breadcrumb_section_pages();

	$items = array(
		array(
			'label' => __( 'Accueil', '180c' ),
			'url'   => home_url( '/' ),
		),
	);

	// Résout l'URL d'un nœud de section, avec repli si la page n'existe pas.
	$section_url = static function ( string $key ) use ( $sections ) {
		if ( isset( $sections[ $key ] ) ) {
			$url = get_permalink( $sections[ $key ] );
			if ( $url ) {
				return (string) $url;
			}
		}
		if ( 'recettes' === $key ) {
			$archive = get_post_type_archive_link( 'recipe' );
			return $archive ? (string) $archive : '';
		}
		if ( 'boutique' === $key ) {
			return _180c_shop_url();
		}
		return '';
	};

	$add_section = static function ( string $key, string $label ) use ( &$items, $section_url ) {
		$items[] = array(
			'label' => $label,
			'url'   => $section_url( $key ),
		);
	};
	$add_term    = static function ( ?WP_Term $term ) use ( &$items ) {
		if ( $term instanceof WP_Term ) {
			$link    = get_term_link( $term );
			$items[] = array(
				'label' => $term->name,
				'url'   => is_wp_error( $link ) ? '' : (string) $link,
			);
		}
	};
	$add_current = static function ( string $label ) use ( &$items ) {
		$items[] = array(
			'label' => $label,
			'url'   => '',
		);
	};

	if ( is_singular( 'recipe' ) ) {
		$add_section( 'recettes', __( 'Recettes', '180c' ) );
		$add_term( _180c_breadcrumb_primary_term( (int) get_the_ID(), 'recipe_category' ) );
		$add_current( get_the_title() );
	} elseif ( function_exists( 'is_product' ) && is_product() ) {
		$add_section( 'boutique', __( 'Boutique', '180c' ) );
		$add_term( _180c_breadcrumb_primary_term( (int) get_the_ID(), 'product_cat' ) );
		$add_current( get_the_title() );
	} elseif ( is_singular( 'post' ) ) {
		$add_section( 'gazette', __( 'Gazette', '180c' ) );
		$add_term( _180c_breadcrumb_primary_term( (int) get_the_ID(), 'category' ) );
		$add_current( get_the_title() );
	} elseif ( is_tax( 'recipe_category' ) ) {
		$add_section( 'recettes', __( 'Recettes', '180c' ) );
		$add_current( single_term_title( '', false ) );
	} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
		$add_section( 'boutique', __( 'Boutique', '180c' ) );
		$add_current( single_term_title( '', false ) );
	} elseif ( is_category() ) {
		$add_section( 'gazette', __( 'Gazette', '180c' ) );
		$add_current( single_term_title( '', false ) );
	} elseif ( is_tax( array( 'recipe_season', 'recipe_publication', 'recipe_tag' ) ) ) {
		// Autres taxonomies de recettes : rattachées à la section Recettes.
		$add_section( 'recettes', __( 'Recettes', '180c' ) );
		$add_current( single_term_title( '', false ) );
	} elseif ( is_author() ) {
		$add_section( 'gazette', __( 'Gazette', '180c' ) );
		$author_id = (int) get_queried_object_id();
		$name      = function_exists( '_180c_author_display_name' )
			? _180c_author_display_name( $author_id )
			: (string) get_the_author_meta( 'display_name', $author_id );
		$add_current( $name );
	} elseif ( is_post_type_archive( 'recipe' ) ) {
		$add_section( 'recettes', __( 'Recettes', '180c' ) );
		$add_current( __( 'Toutes les recettes', '180c' ) );
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$add_section( 'boutique', __( 'Boutique', '180c' ) );
		$add_current( __( 'Tous les produits', '180c' ) );
	} elseif ( is_tax() || is_tag() ) {
		// Toute autre taxonomie/étiquette publique : Accueil › Terme (parité JSON-LD).
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$add_current( $term->name );
		}
	} elseif ( is_page() ) {
		$ancestors = array_reverse( get_post_ancestors( (int) get_the_ID() ) );
		foreach ( $ancestors as $ancestor_id ) {
			$items[] = array(
				'label' => get_the_title( $ancestor_id ),
				'url'   => (string) get_permalink( $ancestor_id ),
			);
		}
		$add_current( get_the_title() );
	}

	/**
	 * Filtre les items du fil d'Ariane (source unique : rendu visuel + JSON-LD).
	 *
	 * @param array $items Items ordonnés { label:string, url:string }.
	 */
	return (array) apply_filters( '_180c_breadcrumb_items', $items );
}

/**
 * Rend le fil d'Ariane de la page courante, juste au-dessus du footer.
 *
 * Point d'insertion global et unique (footer.php), conditionné par le helper de
 * visibilité. Le markup est mutualisé via _180c_breadcrumb_markup() ; le
 * JSON-LD BreadcrumbList est émis séparément par le module SEO à partir de la
 * même source (_180c_get_breadcrumb_items()).
 *
 * @return void
 */
function _180c_render_breadcrumb(): void {
	if ( ! _180c_should_show_breadcrumb() ) {
		return;
	}

	$items = _180c_get_breadcrumb_items();

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le helper.
	echo _180c_breadcrumb_markup( $items );
}

/**
 * URL de la fiche App Store de l'app 180°C (badge iOS).
 *
 * Source unique partagée par tous les badges (footer du site, e-mails Mailchimp,
 * e-mails transactionnels) : constante `_180C_APP_STORE_URL` à renseigner dans
 * wp-config. Filtrable. Renvoie une chaîne vide si la constante est absente ou
 * vide : dans ce cas, les badges appelants ne doivent pas être affichés.
 *
 * @return string URL absolue, ou chaîne vide si non renseignée.
 */
function _180c_app_store_url(): string {
	$url = defined( '_180C_APP_STORE_URL' ) ? (string) _180C_APP_STORE_URL : '';

	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (string) apply_filters( '180c/app_store_url', $url );
}

/**
 * URL de la fiche Google Play de l'app 180°C (badge Android).
 *
 * Source unique partagée par tous les badges. Constante `_180C_GOOGLE_PLAY_URL`
 * à renseigner dans wp-config. Filtrable. Renvoie une chaîne vide si la constante
 * est absente ou vide : dans ce cas, les badges appelants ne doivent pas être
 * affichés.
 *
 * @return string URL absolue, ou chaîne vide si non renseignée.
 */
function _180c_google_play_url(): string {
	$url = defined( '_180C_GOOGLE_PLAY_URL' ) ? (string) _180C_GOOGLE_PLAY_URL : '';

	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (string) apply_filters( '180c/google_play_url', $url );
}

/**
 * Résout l'URL de redirection du QR code de téléchargement de l'app.
 *
 * Sert `page-templates/page-app-download.php` (la page `/app`) : iOS → App Store,
 * Android → Google Play, tout le reste (desktop, bots, UA absent) → accueil.
 *
 * Vit ici et non dans le template : un template de page n'est chargé que lorsque
 * WordPress le rend, ce qui rendrait la fonction inappelable depuis un test ou
 * WP-CLI sans déclencher la redirection. Elle est aussi voisine des deux helpers
 * de stores qu'elle consomme.
 *
 * Fonction pure, hors état global : le User-Agent est injectable pour être
 * testable sans requête HTTP. Les URLs viennent de `_180c_app_store_url()` /
 * `_180c_google_play_url()`, qui portent déjà le `defined()` et le filtre — pas
 * de seconde lecture des constantes ici, sinon deux sources de vérité.
 *
 * Tant que les constantes `_180C_APP_STORE_URL` / `_180C_GOOGLE_PLAY_URL` sont
 * absentes ou vides (app non publiée), tous les appareils retombent sur
 * l'accueil, silencieusement et sans mention de l'app — même logique de prudence
 * que `inc/blocks/app-promo/render.php` (grep `APP-RELEASE`).
 *
 * Toute valeur qui n'est pas une URL http(s) absolue est traitée comme absente :
 * une coquille d'opérateur dans wp-config doit dégrader vers l'accueil, jamais
 * produire un en-tête `Location` cassé.
 *
 * @param string|null $user_agent User-Agent à analyser. `null` lit
 *                                `$_SERVER['HTTP_USER_AGENT']`.
 * @return string URL absolue de redirection. Accueil du site en repli.
 */
function _180c_get_app_download_redirect_url( ?string $user_agent = null ): string {
	$fallback = home_url( '/' );

	if ( null === $user_agent ) {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
	}

	if ( preg_match( '/iPhone|iPad|iPod/i', $user_agent ) ) {
		$url = _180c_app_store_url();
	} elseif ( preg_match( '/Android/i', $user_agent ) ) {
		$url = _180c_google_play_url();
	} else {
		// Desktop, bots, UA absent : aucune app à proposer.
		return $fallback;
	}

	$url = trim( (string) $url );

	// Système reconnu mais store non renseigné (app pas encore publiée), ou
	// valeur non exploitable : repli silencieux sur l'accueil.
	if ( '' === $url || ! wp_http_validate_url( $url ) ) {
		return $fallback;
	}

	return $url;
}

/**
 * URL de la boutique, garantie servable.
 *
 * L'option WooCommerce « Page boutique » (`wc_get_page_id('shop')`) pointe sur
 * une page en **brouillon** (`tous-les-produits`). `wc_get_page_permalink()`
 * retombe donc sur `?page_id=…`, et WordPress, ne trouvant aucun contenu publié
 * à cet ID pour un visiteur anonyme, applique sa redirection « devinette »
 * (`redirect_guess_404_permalink`) : le lien atterrit sur un produit au hasard.
 *
 * Ce helper ne fait confiance à l'option WooCommerce que si la page ciblée est
 * réellement **publiée**. Sinon il retombe sur la page `boutique`, puis sur
 * l'accueil.
 *
 * Le correctif de fond est une action d'administration (repointer l'option, ou
 * publier/supprimer le brouillon) ; ce repli garantit qu'aucun lien du thème
 * n'est cassé entre-temps.
 *
 * @return string URL absolue.
 */
function _180c_shop_url(): string {
	if ( function_exists( 'wc_get_page_id' ) ) {
		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_id > 0 && 'publish' === get_post_status( $shop_id ) ) {
			$permalink = get_permalink( $shop_id );
			if ( $permalink ) {
				return (string) $permalink;
			}
		}
	}

	$page = get_page_by_path( 'boutique' );
	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		$permalink = get_permalink( $page );
		if ( $permalink ) {
			return (string) $permalink;
		}
	}

	return home_url( '/' );
}

/**
 * ID de la page boutique, garantie publiée.
 *
 * Pendant de _180c_shop_url() pour les consommateurs qui ont besoin de l'ID
 * (titre de la page, exclusion d'une liste…). Voir cette fonction pour le
 * détail du problème contourné.
 *
 * @return int ID de page, ou 0 si aucune page boutique publiée.
 */
function _180c_shop_page_id(): int {
	if ( function_exists( 'wc_get_page_id' ) ) {
		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_id > 0 && 'publish' === get_post_status( $shop_id ) ) {
			return $shop_id;
		}
	}

	$page = get_page_by_path( 'boutique' );

	return ( $page instanceof WP_Post && 'publish' === $page->post_status ) ? (int) $page->ID : 0;
}
