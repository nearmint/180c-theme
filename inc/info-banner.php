<?php
/**
 * Bandeau d'information administrable.
 *
 * Bandeau promo / info / alerte piloté depuis Marketing > Bandeau Boutique (ACF
 * options page). Rendu sur `wp_body_open` au-dessus du header, ciblage par page
 * et par fenêtre de dates, fermeture mémorisée côté client (localStorage
 * versionné).
 *
 * Ce fichier regroupe :
 *  - l'enregistrement de la page d'options + la toolbar WYSIWYG restreinte et la
 *    validation de longueur (Phase 1) ;
 *  - les helpers de lecture / décision / rendu (Phases 2-3).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre la sous-page d'options « Bandeau Boutique ».
 *
 * Elle est déclarée sous Apparence, son parent d'enregistrement, puis déplacée
 * sous Marketing par `inc/admin-menu.php` — qui aliase au passage le hookname
 * de rendu, lequel dépend du parent.
 *
 * Le menu Marketing existe pourtant dès la priorité 6 : `parent_slug` pourrait
 * donc valoir `woocommerce-marketing` d'emblée. On s'en abstient parce que
 * l'entrée tomberait alors sous `Marketing::reorder_marketing_submenu`, qui
 * trie le sous-menu par ordre alphabétique en priorité 99 — la même qu'ACF,
 * donc dans un ordre non garanti. Le déplacement en priorité 9999 est ce qui
 * assure la dernière position demandée.
 *
 * `page_title` et `menu_title` sont tenus identiques : ACF rend son propre
 * `page_title`, hors de portée de `$submenu`, donc renommer le seul menu
 * laisserait un écran au titre divergent.
 *
 * Le slug `180c-info-banner` est référencé par la localisation du groupe ACF
 * `group_180c_info_banner` (acf-json).
 */
add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
			return;
		}

		acf_add_options_sub_page(
			array(
				'page_title'  => __( 'Bandeau Boutique', '180c' ),
				'menu_title'  => __( 'Bandeau Boutique', '180c' ),
				'menu_slug'   => '180c-info-banner',
				'parent_slug' => 'themes.php',
				'capability'  => 'edit_theme_options',
				'position'    => false,
			)
		);
	}
);

/**
 * Déclare une toolbar WYSIWYG minimale `basic_180c` : gras, italique, lien.
 *
 * Le champ `info_banner_content` l'utilise pour interdire titres, listes,
 * médias, etc. — le contenu doit rester une phrase courte inline.
 *
 * @param array $toolbars Toolbars TinyMCE existantes.
 * @return array
 */
add_filter(
	'acf/fields/wysiwyg/toolbars',
	function ( $toolbars ) {
		$toolbars['basic_180c'] = array(
			1 => array( 'bold', 'italic', 'link', 'unlink' ),
		);

		return $toolbars;
	}
);

/**
 * Limite le contenu du bandeau à 120 caractères (balises non comptées).
 *
 * @param bool|string $valid True si valide, sinon message d'erreur.
 * @param mixed       $value Valeur soumise.
 * @return bool|string
 */
add_filter(
	'acf/validate_value/name=info_banner_content',
	function ( $valid, $value ) {
		// Une erreur amont (ex. champ requis) court-circuite notre contrôle.
		if ( true !== $valid ) {
			return $valid;
		}

		$length = mb_strlen( wp_strip_all_tags( (string) $value ) );

		if ( $length > 120 ) {
			return sprintf(
				/* translators: %d: number of characters over the limit. */
				__( 'Le contenu du bandeau ne doit pas dépasser 120 caractères (actuellement %d).', '180c' ),
				$length
			);
		}

		return $valid;
	},
	10,
	2
);

/**
 * Phase 2 — Helpers : lecture, décision d'affichage, rendu HTML, version.
 */

/**
 * Lecture normalisée des champs de la page d'options.
 *
 * Résultat mémoïsé pour la requête (les helpers s'appellent plusieurs fois :
 * garde no-FOUC dans wp_head + rendu sur wp_body_open).
 *
 * @return array{
 *     enabled:bool, variant:string, content:string, scope:string,
 *     pages_specific:int[], pages_excluded:int[], date_start:string,
 *     date_end:string, show_desktop:bool, show_mobile:bool, dismissible:bool
 * }
 */
function _180c_info_banner_options() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	if ( ! function_exists( 'get_field' ) ) {
		$cache = array(
			'enabled'        => false,
			'variant'        => 'promo',
			'content'        => '',
			'scope'          => 'site',
			'pages_specific' => array(),
			'pages_excluded' => array(),
			'date_start'     => '',
			'date_end'       => '',
			'show_desktop'   => true,
			'show_mobile'    => true,
			'dismissible'    => true,
		);
		return $cache;
	}

	$variant = (string) get_field( 'info_banner_variant', 'option' );
	$scope   = (string) get_field( 'info_banner_display_scope', 'option' );

	$cache = array(
		'enabled'        => (bool) get_field( 'info_banner_enabled', 'option' ),
		'variant'        => in_array( $variant, array( 'info', 'promo', 'alert' ), true ) ? $variant : 'promo',
		'content'        => (string) get_field( 'info_banner_content', 'option' ),
		'scope'          => in_array( $scope, array( 'home', 'site', 'site_except', 'specific' ), true ) ? $scope : 'site',
		'pages_specific' => array_map( 'intval', (array) get_field( 'info_banner_pages_specific', 'option' ) ),
		'pages_excluded' => array_map( 'intval', (array) get_field( 'info_banner_pages_excluded', 'option' ) ),
		'date_start'     => (string) get_field( 'info_banner_date_start', 'option' ),
		'date_end'       => (string) get_field( 'info_banner_date_end', 'option' ),
		'show_desktop'   => (bool) get_field( 'info_banner_show_desktop', 'option' ),
		'show_mobile'    => (bool) get_field( 'info_banner_show_mobile', 'option' ),
		'dismissible'    => (bool) get_field( 'info_banner_dismissible', 'option' ),
	);

	return $cache;
}

/**
 * Détermine si le bandeau doit être rendu pour la requête courante.
 *
 * Conditions cumulées : activé ET contenu non vide ET fenêtre de dates ouverte
 * ET ciblage page satisfait ET au moins un device autorisé.
 *
 * @return bool
 */
function _180c_info_banner_should_display() {
	$opts = _180c_info_banner_options();

	if ( ! $opts['enabled'] ) {
		return false;
	}

	// Contenu réellement présent (balises ignorées).
	if ( '' === trim( wp_strip_all_tags( $opts['content'] ) ) ) {
		return false;
	}

	// Au moins un device autorisé.
	if ( ! $opts['show_desktop'] && ! $opts['show_mobile'] ) {
		return false;
	}

	// Fenêtre de dates (fuseau Europe/Paris).
	if ( ! _180c_info_banner_in_date_window( $opts['date_start'], $opts['date_end'] ) ) {
		return false;
	}

	// Ciblage page.
	return _180c_info_banner_matches_scope( $opts );
}

/**
 * Évalue la fenêtre de dates en fuseau Europe/Paris.
 *
 * Start vide = pas de borne basse ; end vide = pas de borne haute.
 *
 * @param string $start Date de début au format Y-m-d H:i:s (ou '').
 * @param string $end   Date de fin au format Y-m-d H:i:s (ou '').
 * @return bool
 */
function _180c_info_banner_in_date_window( $start, $end ) {
	$tz  = new DateTimeZone( 'Europe/Paris' );
	$now = new DateTimeImmutable( 'now', $tz );

	if ( '' !== $start ) {
		$start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $start, $tz );
		if ( $start_dt && $now < $start_dt ) {
			return false;
		}
	}

	if ( '' !== $end ) {
		$end_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $end, $tz );
		if ( $end_dt && $now > $end_dt ) {
			return false;
		}
	}

	return true;
}

/**
 * Évalue le ciblage page selon le scope configuré.
 *
 * @param array $opts Options normalisées.
 * @return bool
 */
function _180c_info_banner_matches_scope( array $opts ) {
	switch ( $opts['scope'] ) {
		case 'home':
			return is_front_page();

		case 'site_except':
			return ! in_array( get_queried_object_id(), $opts['pages_excluded'], true );

		case 'specific':
			if ( empty( $opts['pages_specific'] ) ) {
				return false;
			}
			return in_array( get_queried_object_id(), $opts['pages_specific'], true );

		case 'site':
		default:
			return true;
	}
}

/**
 * Contenu du bandeau assaini pour le rendu.
 *
 * Whitelist stricte (gras/italique/lien). Les liens en `target="_blank"`
 * reçoivent `rel="noopener"`. wp_kses valide les protocoles d'URL des href
 * (équivalent esc_url côté sortie).
 *
 * @return string HTML sûr.
 */
function _180c_info_banner_content_html() {
	$opts = _180c_info_banner_options();

	$allowed = array(
		'strong' => array(),
		'em'     => array(),
		'a'      => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
		),
	);

	$clean = wp_kses( $opts['content'], $allowed );

	// wp_targeted_link_rel() est déprécié depuis WP 6.7 : on force nous-mêmes
	// rel="noopener" sur tout lien ouvrant un nouvel onglet (anti tabnabbing).
	return preg_replace_callback(
		'/<a\b[^>]*\btarget=(["\'])_blank\1[^>]*>/i',
		function ( $matches ) {
			$tag = $matches[0];

			if ( preg_match( '/\brel=(["\'])(.*?)\1/i', $tag, $rel ) ) {
				if ( false === stripos( $rel[2], 'noopener' ) ) {
					$tag = str_replace( $rel[0], 'rel="' . trim( $rel[2] . ' noopener' ) . '"', $tag );
				}
				return $tag;
			}

			return preg_replace( '/<a\b/i', '<a rel="noopener"', $tag, 1 );
		},
		$clean
	);
}

/**
 * Empreinte de version du bandeau (contenu + variante).
 *
 * Sert de clé de mémorisation côté client : dès que le contenu ou la variante
 * change, la version change et la fermeture précédente devient caduque.
 *
 * @return string Hash de 8 caractères.
 */
function _180c_info_banner_version() {
	$opts = _180c_info_banner_options();

	return substr( md5( $opts['content'] . $opts['variant'] ), 0, 8 );
}

/**
 * Phase 3 — Rendu sur wp_body_open + garde no-FOUC.
 */

/**
 * Convertit une date stockée (Y-m-d H:i:s, Europe/Paris) en ISO 8601 avec
 * offset, pour la garde date côté client (cache-safe).
 *
 * @param string $stored Valeur stockée (ou '').
 * @return string ISO 8601 (ex. 2026-06-01T10:00:00+02:00) ou ''.
 */
function _180c_info_banner_iso_date( $stored ) {
	if ( '' === $stored ) {
		return '';
	}

	$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $stored, new DateTimeZone( 'Europe/Paris' ) );

	return $dt ? $dt->format( 'c' ) : '';
}

/**
 * Rendu du bandeau sur wp_body_open (au-dessus du header).
 *
 * Le CSS du composant vit dans le bundle principal (import dans main.css) et le
 * JS s'auto-charge via main.js dès que `[data-info-banner]` est présent : aucun
 * enqueue explicite ici — la présence du markup est le déclencheur (Option A,
 * convention du thème). Si le bandeau ne doit pas s'afficher, rien n'est imprimé
 * et le module JS ne s'initialise jamais.
 */
add_action(
	'wp_body_open',
	function () {
		if ( ! _180c_info_banner_should_display() ) {
			return;
		}

		$opts    = _180c_info_banner_options();
		$version = _180c_info_banner_version();

		$classes = array( 'info-banner', 'info-banner--' . $opts['variant'] );

		// Modificateurs device : seulement si UN seul device est ciblé (le CSS
		// masque l'autre). Les deux cochés → pas de modificateur (partout).
		if ( $opts['show_desktop'] && ! $opts['show_mobile'] ) {
			$classes[] = 'info-banner--desktop';
		} elseif ( $opts['show_mobile'] && ! $opts['show_desktop'] ) {
			$classes[] = 'info-banner--mobile';
		}

		printf(
			'<div class="%1$s" data-info-banner data-version="%2$s" data-start="%3$s" data-end="%4$s" role="region" aria-label="%5$s">',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $version ),
			esc_attr( _180c_info_banner_iso_date( $opts['date_start'] ) ),
			esc_attr( _180c_info_banner_iso_date( $opts['date_end'] ) ),
			esc_attr__( 'Information 180°C', '180c' )
		);

		echo '<div class="info-banner__inner site-container">';
		echo '<p class="info-banner__text">' . _180c_info_banner_content_html() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assaini par wp_kses dans le helper.

		if ( $opts['dismissible'] ) {
			printf(
				'<button type="button" class="info-banner__close" data-info-banner-close aria-label="%s">',
				esc_attr__( 'Fermer ce bandeau', '180c' )
			);
			echo '<svg class="info-banner__close-icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false">';
			echo '<path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>';
			echo '</svg>';
			echo '</button>';
		}

		echo '</div></div>';
	}
);

/**
 * Garde no-FOUC : masque le bandeau avant le paint si déjà fermé.
 *
 * Imprimée dans wp_head uniquement si le bandeau doit s'afficher et qu'il est
 * refermable. Lit `localStorage['_180c_info_banner_dismissed']`, le compare à la
 * version courante (rendue ici par PHP) et pose `info-banner--prehidden` sur
 * <html> en cas de correspondance — le CSS masque alors le bandeau d'emblée.
 */
add_action(
	'wp_head',
	function () {
		if ( ! _180c_info_banner_should_display() ) {
			return;
		}

		$opts = _180c_info_banner_options();
		if ( ! $opts['dismissible'] ) {
			return;
		}

		// wp_json_encode produit un littéral JS sûr (échappement contexte script).
		$version = wp_json_encode( _180c_info_banner_version() );

		// Script inline minimal, défensif (try/catch : localStorage peut lever).
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $version encodé via wp_json_encode (contexte JS).
		echo '<script>(function(){try{if(localStorage.getItem("_180c_info_banner_dismissed")===' . $version . '){document.documentElement.classList.add("info-banner--prehidden");}}catch(e){}})();</script>' . "\n";
	},
	1
);
