<?php
/**
 * Helpers de navigation 180°C.
 *
 * Centralise les fonctions de rendu et les fallbacks hardcodés des
 * `wp_nav_menu()` du header, du side menu et du footer. Si une location
 * n'est pas affectée à un menu en admin, le fallback rend une liste
 * équivalente avec les mêmes classes BEM, pour que le CSS continue de
 * matcher dans tous les cas.
 *
 * Pour assigner une classe spéciale à un menu item depuis l'admin
 * (ex : "site-header__nav-cta" pour le bouton Premium), passer par
 * Apparence > Menus > activer « Classes CSS » via « Options de l'écran »
 * en haut à droite, puis renseigner la classe dans le champ « Classes CSS »
 * de l'item.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fallback du menu primary (header desktop ≥ 1024px).
 *
 * N'est rendu que si **aucun menu WordPress n'est affecté** à la location
 * `primary` (Apparence > Menus) ; dès qu'un menu y est affecté, il prend le
 * relais et cette liste n'apparaît plus.
 *
 * Rend un <ul class="site-header__nav-list"> avec les 4 items historiques :
 * La Gazette / Newsletter / Boutique en ligne / Premium. L'item Newsletter a
 * remplacé « Cahiers de Delphine », dont la page a été supprimée et l'URL
 * redirigée en 301 vers /newsletter/ (voir inc/redirects.php).
 *
 * @return void
 */
function _180c_header_primary_fallback() {
	$items = array(
		array(
			'label' => __( 'La Gazette', '180c' ),
			'url'   => home_url( '/la-gazette/' ),
			'class' => '',
		),
		array(
			'label' => __( 'Newsletter', '180c' ),
			'url'   => home_url( '/newsletter/' ),
			'class' => '',
		),
		array(
			'label' => __( 'Boutique en ligne', '180c' ),
			'url'   => home_url( '/boutique/' ),
			'class' => '',
		),
		array(
			'label' => __( 'Premium', '180c' ),
			'url'   => home_url( '/abonnement/' ),
			'class' => 'site-header__nav-cta',
		),
	);

	echo '<ul class="site-header__nav-list">';
	foreach ( $items as $item ) {
		$current = _180c_url_is_current( $item['url'] ) ? ' current-menu-item' : '';
		// Mesure Umami : le repli hardcodé porte les mêmes attributs que les
		// items du menu administrable (posés par _180c_umami_nav_menu_attrs()).
		$umami = function_exists( '_180c_umami_is_subscribe_url' ) && _180c_umami_is_subscribe_url( $item['url'] )
			? _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'header' ) )
			: '';

		printf(
			'<li class="menu-item%1$s"><a href="%2$s" class="%3$s"%5$s>%4$s</a></li>',
			esc_attr( $current ),
			esc_url( $item['url'] ),
			esc_attr( $item['class'] ),
			esc_html( $item['label'] ),
			$umami // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs déjà échappés par _180c_umami_attrs().
		);
	}
	echo '</ul>';
}

/**
 * Indique si l'URL passée correspond à la page courante.
 *
 * Compare le chemin (sans schéma ni host ni querystring) avec REQUEST_URI.
 * Utilisé pour appliquer la classe `.current-menu-item` aux fallbacks.
 *
 * @param string $url URL absolue à tester.
 * @return bool
 */
function _180c_url_is_current( $url ) {
	$path = wp_parse_url( $url, PHP_URL_PATH );
	if ( ! $path ) {
		return false;
	}
	$current = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	if ( ! $current ) {
		return false;
	}
	return untrailingslashit( $path ) === untrailingslashit( $current );
}

/**
 * Chemins des deux entrées de menu permutées selon l'état d'abonnement.
 *
 * Les deux entrées (« Newsletter » et « Mon carnet ») sont posées en back-office ;
 * le code n'en conserve qu'une à l'affichage. Filtrable si les slugs changent.
 *
 * @return array{newsletter:string, carnet:string} Chemins (sans host ni slash final).
 */
function _180c_member_menu_swap_paths() {
	return apply_filters(
		'180c/member_menu_swap_paths',
		array(
			'newsletter' => '/newsletter',
			'carnet'     => '/mon-carnet',
		)
	);
}

/**
 * Classe une URL d'item de menu parmi les deux entrées permutées.
 *
 * @param string $url URL de l'item.
 * @return string '' (aucune correspondance) | 'newsletter' | 'carnet'.
 */
function _180c_member_menu_match( $url ) {
	$path = wp_parse_url( (string) $url, PHP_URL_PATH );
	if ( ! $path ) {
		return '';
	}
	$path = untrailingslashit( $path );
	foreach ( _180c_member_menu_swap_paths() as $key => $slug ) {
		if ( $path === untrailingslashit( $slug ) ) {
			return $key;
		}
	}
	return '';
}

/**
 * Clé de l'entrée à masquer selon l'état d'abonnement de l'utilisateur courant.
 *
 * Abonné aux recettes (donc forcément logué, cf. _180c_is_recipe_subscriber)
 * → on masque « Newsletter », « Mon carnet » reste. Sinon → l'inverse.
 *
 * @return string 'newsletter' | 'carnet'.
 */
function _180c_member_menu_hidden_key() {
	return _180c_is_recipe_subscriber() ? 'newsletter' : 'carnet';
}

/**
 * Retire de la liste d'items (tableaux `{label,url,…}`) l'entrée à masquer.
 *
 * Utilisé par le rendu custom du side menu. Le pendant pour les menus
 * `wp_nav_menu` (header primary) est _180c_filter_member_menu_objects().
 *
 * @param array<int, array{url?:string}> $items Items du side menu.
 * @return array<int, array> Liste filtrée (réindexée).
 */
function _180c_filter_member_menu_array( array $items ) {
	$hidden = _180c_member_menu_hidden_key();
	$out    = array();
	foreach ( $items as $item ) {
		if ( _180c_member_menu_match( $item['url'] ?? '' ) === $hidden ) {
			continue;
		}
		$out[] = $item;
	}
	return $out;
}

/**
 * Permute « Newsletter » / « Mon carnet » sur le menu header `primary`.
 *
 * Hook `wp_nav_menu_objects` : ne touche qu'à la location `primary` (le side
 * menu a son propre chemin de rendu, filtré via _180c_filter_member_menu_array).
 *
 * @param array    $items Objets d'items de menu.
 * @param stdClass $args  Arguments de wp_nav_menu().
 * @return array Items filtrés.
 */
function _180c_filter_member_menu_objects( $items, $args ) {
	if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $items;
	}
	$hidden = _180c_member_menu_hidden_key();
	foreach ( $items as $key => $item ) {
		if ( _180c_member_menu_match( $item->url ?? '' ) === $hidden ) {
			unset( $items[ $key ] );
		}
	}
	return array_values( $items );
}
add_filter( 'wp_nav_menu_objects', '_180c_filter_member_menu_objects', 10, 2 );

/**
 * Rendu d'un item du side menu :
 *  - soit un lien simple (`a.site-side-menu__link`),
 *  - soit un lien parent + bouton expand avec une sous-liste curée depuis les
 *    enfants admin (`site-side-menu__item--has-children`),
 *  - soit un bouton expand avec une sous-liste alimentée par une taxonomy.
 *
 * Priorité : si l'item a des enfants admin directs, ils priment sur la
 * convention `expand-{taxonomy}` (l'admin a un contrôle explicite). Sinon,
 * si l'item porte une classe CSS `expand-{taxonomy}` (ex. `expand-category`,
 * `expand-recipe_category`, `expand-product_cat`), on rend un expand sur
 * cette taxonomy.
 *
 * @param array{label:string, url:string, expand?:string, children?:array<int, array{label:string, url:string}>} $item Item à rendre.
 * @return void
 */
function _180c_side_menu_render_item( array $item ) {
	$expand   = $item['expand'] ?? '';
	$children = isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array();

	// Priorité aux enfants admin : si l'item a une sous-liste curée en admin
	// ET une classe expand-{taxonomy}, on ignore la taxonomy (redondance).
	if ( ! empty( $children ) && '' !== $expand ) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				sprintf(
					/* translators: %s: libellé de l'item de menu. */
					__( 'L\'item de menu « %s » possède à la fois des enfants admin et une classe expand-{taxonomy} : les enfants admin priment, la classe expand est ignorée.', '180c' ),
					$item['label']
				)
			),
			'180c'
		);
		$expand = '';
	}

	$id   = 'side-' . sanitize_title( $item['label'] );
	$icon = '<svg class="site-side-menu__chevron-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>';

	$active = _180c_url_is_current( $item['url'] ) ? ' current-menu-item' : '';

	// Cas enfants admin : lien parent navigable + bouton chevron séparé +
	// sous-liste rendue depuis les enfants admin directs.
	if ( ! empty( $children ) ) {
		printf(
			'<li class="site-side-menu__item site-side-menu__item--has-children%1$s">',
			esc_attr( $active )
		);
		printf(
			'<a class="site-side-menu__link" href="%1$s">%2$s</a>',
			esc_url( $item['url'] ),
			esc_html( $item['label'] )
		);
		printf(
			'<button type="button" class="site-side-menu__expand" aria-expanded="false" aria-controls="%1$s"><span class="screen-reader-text">%2$s</span><span class="site-side-menu__chevron" aria-hidden="true">%3$s</span></button>',
			esc_attr( $id ),
			esc_html(
				sprintf(
					/* translators: %s: libellé de l'item parent. */
					__( 'Afficher le sous-menu de %s', '180c' ),
					$item['label']
				)
			),
			$icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		printf( '<ul id="%s" class="site-side-menu__sub" hidden>', esc_attr( $id ) );
		foreach ( $children as $child ) {
			printf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( $child['url'] ),
				esc_html( $child['label'] )
			);
		}
		echo '</ul></li>';
		return;
	}

	if ( '' !== $expand && taxonomy_exists( $expand ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $expand,
				'hide_empty' => true,
				'number'     => 12,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			// Pas de termes : on rend comme un lien simple.
			$expand = '';
		}
	}

	if ( '' === $expand ) {
		// Le side menu a son propre rendu (il ne passe pas par wp_nav_menu) : le
		// filtre _180c_umami_nav_menu_attrs() ne s'y applique pas, on balise ici.
		$umami = function_exists( '_180c_umami_is_subscribe_url' ) && _180c_umami_is_subscribe_url( $item['url'] )
			? _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'side_menu' ) )
			: '';

		printf(
			'<li class="site-side-menu__item%1$s"><a class="site-side-menu__link" href="%2$s"%4$s>%3$s</a></li>',
			esc_attr( $active ),
			esc_url( $item['url'] ),
			esc_html( $item['label'] ),
			$umami // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs déjà échappés par _180c_umami_attrs().
		);
		return;
	}

	echo '<li class="site-side-menu__item">';
	printf(
		'<button type="button" class="site-side-menu__expand" aria-expanded="false" aria-controls="%1$s"><span>%2$s</span><span class="site-side-menu__chevron" aria-hidden="true">%3$s</span></button>',
		esc_attr( $id ),
		esc_html( $item['label'] ),
		$icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
	printf( '<ul id="%s" class="site-side-menu__sub" hidden>', esc_attr( $id ) );
	foreach ( $terms as $term ) { // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited
		printf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( get_term_link( $term ) ),
			esc_html( $term->name )
		);
	}
	echo '</ul></li>';
}

/**
 * Résout les items du side menu depuis le menu admin assigné à la location
 * `side`, ou retourne null si aucun menu n'est affecté.
 *
 * Chaque item est aplati en `{label, url, expand, children}`. La taxonomy
 * d'expand est détectée via la classe CSS de l'item admin : `expand-category`,
 * `expand-recipe_category`, `expand-product_cat`, etc. Les enfants admin
 * directs (niveau 2) sont attachés dans `children` ; les niveaux 3+ sont
 * ignorés.
 *
 * @return array<int, array{label:string, url:string, expand:string, children:array<int, array{label:string, url:string}>}>|null
 */
function _180c_side_menu_items_from_admin() {
	if ( ! has_nav_menu( 'side' ) ) {
		return null;
	}

	$locations = get_nav_menu_locations();
	if ( empty( $locations['side'] ) ) {
		return null;
	}

	$menu_items = wp_get_nav_menu_items( (int) $locations['side'], array( 'update_post_term_cache' => false ) );
	if ( empty( $menu_items ) ) {
		return null;
	}

	// Passe unique : indexer les enfants directs par ID de parent (évite le
	// N+1 lors du rendu). Seuls les enfants de niveau 2 nous intéressent ;
	// les niveaux 3+ resteront orphelins de leur parent top-level et ne
	// seront jamais rendus.
	$children_by_parent = array();
	foreach ( $menu_items as $menu_item ) {
		$parent = (int) $menu_item->menu_item_parent;
		if ( $parent > 0 ) {
			$children_by_parent[ $parent ][] = $menu_item;
		}
	}

	$items = array();
	foreach ( $menu_items as $menu_item ) {
		// On ne rend que les items top-level. Les enfants sont attachés via
		// `children` (regroupés ci-dessus), jamais rendus comme top-level.
		if ( (int) $menu_item->menu_item_parent > 0 ) {
			continue;
		}

		$expand  = '';
		$classes = is_array( $menu_item->classes ?? null ) ? $menu_item->classes : array();
		foreach ( $classes as $class ) {
			if ( 0 === strpos( $class, 'expand-' ) ) {
				$expand = substr( $class, strlen( 'expand-' ) );
				break;
			}
		}

		$children    = array();
		$child_items = $children_by_parent[ (int) $menu_item->ID ] ?? array();
		foreach ( $child_items as $child ) {
			$children[] = array(
				'label' => (string) $child->title,
				'url'   => (string) $child->url,
			);
		}

		$items[] = array(
			'label'    => (string) $menu_item->title,
			'url'      => (string) $menu_item->url,
			'expand'   => $expand,
			'children' => $children,
		);
	}

	return $items;
}

/**
 * Rend le contenu du side menu (admin OU fallback). Toujours dans un même
 * `<ul class="site-side-menu__list">`.
 *
 * @return void
 */
function _180c_side_menu_render() {
	$items = _180c_side_menu_items_from_admin();

	if ( null === $items ) {
		_180c_side_menu_fallback();
		return;
	}

	// Permutation Newsletter / Mon carnet selon l'état d'abonnement.
	$items = _180c_filter_member_menu_array( $items );

	echo '<ul class="site-side-menu__list">';
	foreach ( $items as $item ) {
		_180c_side_menu_render_item( $item );
	}
	echo '</ul>';
}

/**
 * Fallback du menu side (panneau latéral).
 *
 * Rend la liste des 6 entrées par défaut. Trois premières en expand
 * (Articles, Recettes, Boutique) sur leur taxonomie respective. Les
 * trois suivantes sont des liens simples.
 *
 * @return void
 */
function _180c_side_menu_fallback() {
	$items = array(
		array(
			'label'  => __( 'Articles', '180c' ),
			'url'    => home_url( '/blog/' ),
			'expand' => 'category',
		),
		array(
			'label'  => __( 'Recettes', '180c' ),
			'url'    => home_url( '/recettes/' ),
			'expand' => 'recipe_category',
		),
		array(
			'label'  => __( 'Boutique en ligne', '180c' ),
			'url'    => home_url( '/boutique/' ),
			'expand' => 'product_cat',
		),
		array(
			'label' => __( 'Newsletter', '180c' ),
			'url'   => home_url( '/newsletter/' ),
		),
		array(
			'label' => __( 'Contact', '180c' ),
			'url'   => home_url( '/contactez-la-redaction/' ),
		),
		array(
			'label' => __( 'À propos', '180c' ),
			'url'   => home_url( '/qui-sommes-nous/' ),
		),
	);

	echo '<ul class="site-side-menu__list">';
	foreach ( $items as $item ) {
		_180c_side_menu_render_item( $item );
	}
	echo '</ul>';
}
