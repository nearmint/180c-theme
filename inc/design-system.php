<?php
/**
 * Design System — parsing des foundations.
 *
 * Lit les DEUX sources de tokens et retourne les foundations groupées par
 * catégorie, prêtes à être rendues par template-parts/ds/foundations.php :
 *   - src/css/tokens.css        → espacements, typo, rayons, ombres, z-index,
 *                                 breakpoints, transitions, layout, side-menu,
 *                                 hero, alias de vocabulaire couleur.
 *   - src/css/main.css (@theme) → couleurs sémantiques (light) + familles de
 *                                 polices ; valeurs dark depuis [data-theme="dark"].
 *
 * Principe : aucune valeur en dur. Tout est extrait des fichiers CSS à l'exécution.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Liste ordonnée des lots de composants (Phase 3).
 *
 * Chaque entrée = un partial template-parts/ds/components/<slug>.php rendant un
 * groupe cohérent de fiches composant.
 *
 * @return array<string,string> slug => titre du lot.
 */
function _180c_ds_component_lots() {
	return array(
		'atoms'   => __( 'Atomes', '180c' ),
		'chrome'  => __( 'Navigation & chrome', '180c' ),
		'cards'   => __( 'Cards', '180c' ),
		'modules' => __( 'Home modules', '180c' ),
		'blocks'  => __( 'Blocs Gutenberg / ACF', '180c' ),
		'woo'     => __( 'WooCommerce', '180c' ),
		'forms'   => __( 'Formulaires & auth', '180c' ),
		'states'  => __( 'États & feedback', '180c' ),
		'misc'    => __( 'Pages & divers', '180c' ),
	);
}

/**
 * Rend une fiche composant (raccourci sur le partial component-card).
 *
 * @param array $args Voir template-parts/ds/component-card.php.
 * @return void
 */
function _180c_ds_component( array $args ) {
	get_template_part( 'template-parts/ds/component-card', null, $args );
}

/**
 * Capture la sortie d'un partial (pour un aperçu « live » via le vrai partial).
 *
 * @param string $slug Slug du partial (ex. 'parts/empty-state').
 * @param array  $args Arguments transmis au partial.
 * @return string HTML capturé.
 */
function _180c_ds_capture( $slug, $args = array() ) {
	ob_start();
	get_template_part( $slug, null, $args );
	return (string) ob_get_clean();
}

/**
 * Lit un fichier CSS du thème.
 *
 * @param string $relative Chemin relatif au thème (ex. 'src/css/tokens.css').
 * @return string Contenu ou chaîne vide si introuvable.
 */
function _180c_ds_read_css( $relative ) {
	$path = _180C_THEME_DIR . '/' . ltrim( $relative, '/' );

	if ( ! is_readable( $path ) ) {
		return '';
	}

	return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}

/**
 * Extrait les déclarations `--token: value;` d'un fragment CSS.
 *
 * Les commentaires /* … *​/ sont ignorés ; les valeurs multi-espaces sont
 * normalisées. Conserve l'ordre d'apparition. En cas de doublon, la PREMIÈRE
 * occurrence est conservée (la base, pas les overrides @media).
 *
 * @param string $css Fragment CSS.
 * @return array<string,string> token => valeur.
 */
function _180c_ds_extract_declarations( $css ) {
	$out = array();

	if ( '' === $css ) {
		return $out;
	}

	// Retire les commentaires CSS pour ne pas polluer les valeurs.
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );

	if ( preg_match_all( '/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $css, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $m ) {
			$name = $m[1];
			if ( isset( $out[ $name ] ) ) {
				continue; // Garde la première (base).
			}
			$out[ $name ] = trim( preg_replace( '/\s+/', ' ', $m[2] ) );
		}
	}

	return $out;
}

/**
 * Isole le contenu du premier bloc dont le sélecteur matche.
 *
 * @param string $css            CSS complet.
 * @param string $selector_regex Sélecteur en regex (échappé à l'appel si besoin).
 * @return string Contenu entre accolades (sans accolades) ou ''.
 */
function _180c_ds_block_body( $css, $selector_regex ) {
	if ( preg_match( '/' . $selector_regex . '\s*\{([^{}]*)\}/s', $css, $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * Résout récursivement une valeur couleur en sa valeur littérale pour un mode.
 *
 * @param string $value     Valeur (ex. '#FFAE3A' ou 'var(--color-bg)').
 * @param string $mode      'light' ou 'dark'.
 * @param array  $semantic  Map token => ['light'=>..,'dark'=>..].
 * @param array  $constants Map token => valeur littérale (tokens.css).
 * @param int    $depth     Garde-fou anti-boucle.
 * @return string Valeur littérale résolue (peut rester un var() si introuvable).
 */
function _180c_ds_resolve_color( $value, $mode, $semantic, $constants, $depth = 0 ) {
	if ( $depth > 5 ) {
		return $value;
	}

	if ( preg_match( '/^var\(\s*(--[a-z0-9-]+)\s*\)$/i', $value, $m ) ) {
		$ref = $m[1];

		if ( isset( $semantic[ $ref ][ $mode ] ) ) {
			return $semantic[ $ref ][ $mode ];
		}
		if ( isset( $constants[ $ref ] ) ) {
			return _180c_ds_resolve_color( $constants[ $ref ], $mode, $semantic, $constants, $depth + 1 );
		}
		return $value;
	}

	return $value;
}

/**
 * Détermine la catégorie d'un token scalaire (non-couleur) de tokens.css.
 *
 * Première règle qui matche l'emporte (ordre important).
 *
 * @param string $name Nom du token.
 * @return string Clé de catégorie.
 */
function _180c_ds_scalar_category( $name ) {
	$rules = array(
		'font-weight'  => static fn( $n ) => str_starts_with( $n, '--font-weight-' ),
		'font-alias'   => static fn( $n ) => str_starts_with( $n, '--font-size-' ) || in_array( $n, array( '--font-sm', '--font-md', '--font-lg' ), true ),
		'text-fluid'   => static fn( $n ) => (bool) preg_match( '/^--text-(2xl|3xl|4xl|5xl)$/', $n ),
		'text-fixed'   => static fn( $n ) => (bool) preg_match( '/^--text-(xs|sm|base|md|lg|xl)$/', $n ),
		'line-height'  => static fn( $n ) => str_starts_with( $n, '--line-height-' ),
		'space-num'    => static fn( $n ) => (bool) preg_match( '/^--space-\d+$/', $n ),
		'space-tshirt' => static fn( $n ) => str_starts_with( $n, '--space-' ),
		'space-alias'  => static fn( $n ) => str_starts_with( $n, '--spacing-' ),
		'radius'       => static fn( $n ) => str_starts_with( $n, '--radius-' ),
		'shadow'       => static fn( $n ) => str_starts_with( $n, '--shadow-' ),
		'z-index'      => static fn( $n ) => str_starts_with( $n, '--z-' ),
		'breakpoint'   => static fn( $n ) => str_starts_with( $n, '--breakpoint-' ),
		'transition'   => static fn( $n ) => str_starts_with( $n, '--transition-' ),
		'side-menu'    => static fn( $n ) => str_starts_with( $n, '--side-menu-' ),
		'hero'         => static fn( $n ) => str_starts_with( $n, '--hero-' ),
		'layout'       => static fn( $n ) => str_starts_with( $n, '--container-' ) || in_array( $n, array( '--content-width', '--section-y' ), true ),
	);

	foreach ( $rules as $key => $matcher ) {
		if ( $matcher( $name ) ) {
			return $key;
		}
	}

	return 'misc';
}

/**
 * Parse les foundations et les retourne groupées par catégorie.
 *
 * @return array Liste ordonnée de groupes :
 *   [ ['key'=>..,'title'=>..,'render'=>'color|font|scale|value','rows'=>[...]], … ].
 */
function _180c_ds_parse_tokens() {
	$tokens_css = _180c_ds_read_css( 'src/css/tokens.css' );
	$main_css   = _180c_ds_read_css( 'src/css/main.css' );

	// --- Couleurs sémantiques + fonts : bloc @theme (light) + dark override. ---
	$theme_light = _180c_ds_extract_declarations( _180c_ds_block_body( $main_css, '@theme' ) );
	$theme_dark  = _180c_ds_extract_declarations( _180c_ds_block_body( $main_css, '\[data-theme="dark"\]' ) );

	$semantic = array(); // token => light/dark.
	$fonts    = array();
	foreach ( $theme_light as $name => $val ) {
		if ( str_starts_with( $name, '--color-' ) ) {
			$semantic[ $name ] = array(
				'light' => $val,
				'dark'  => $theme_dark[ $name ] ?? $val,
			);
		} elseif ( str_starts_with( $name, '--font-' ) ) {
			$fonts[ $name ] = $val;
		}
	}

	// --- tokens.css (:root de base, doublons @media ignorés). ---
	$root      = _180c_ds_extract_declarations( _180c_ds_block_body( $tokens_css, ':root' ) );
	$constants = $root; // pour la résolution d'alias couleur.

	// Tokens couleur définis dans tokens.css (alias + constantes).
	$color_alias_names = array( '--paper', '--cream', '--ink', '--muted', '--rule', '--primary' );
	$color_const_names = array( '--on-accent', '--color-on-accent', '--media-paper', '--hero-text-on-dark', '--hero-overlay' );

	// Buckets de couleurs.
	$rows_semantic = array();
	foreach ( $semantic as $name => $modes ) {
		$rows_semantic[] = array(
			'token' => $name,
			'light' => $modes['light'],
			'dark'  => $modes['dark'],
		);
	}

	$rows_alias = array();
	foreach ( $color_alias_names as $name ) {
		if ( ! isset( $root[ $name ] ) ) {
			continue;
		}
		$rows_alias[] = array(
			'token' => $name,
			'value' => $root[ $name ],
			'light' => _180c_ds_resolve_color( $root[ $name ], 'light', $semantic, $constants ),
			'dark'  => _180c_ds_resolve_color( $root[ $name ], 'dark', $semantic, $constants ),
		);
	}

	$rows_const = array();
	foreach ( $color_const_names as $name ) {
		if ( ! isset( $root[ $name ] ) ) {
			continue;
		}
		// N'affiche l'expression source que si c'est un alias var(), pas une
		// valeur littérale (sinon doublon avec la valeur résolue).
		$is_var       = (bool) preg_match( '/^var\(/i', $root[ $name ] );
		$rows_const[] = array(
			'token' => $name,
			'value' => $is_var ? $root[ $name ] : '',
			'light' => _180c_ds_resolve_color( $root[ $name ], 'light', $semantic, $constants ),
			'dark'  => _180c_ds_resolve_color( $root[ $name ], 'dark', $semantic, $constants ),
		);
	}

	// --- Catégorisation des tokens scalaires restants. ---
	$skip   = array_merge( $color_alias_names, $color_const_names );
	$by_cat = array();
	foreach ( $root as $name => $val ) {
		if ( in_array( $name, $skip, true ) ) {
			continue;
		}
		$cat              = _180c_ds_scalar_category( $name );
		$by_cat[ $cat ][] = array(
			'token' => $name,
			'value' => $val,
		);
	}

	// --- Familles de polices (rows). ---
	$rows_fonts = array();
	foreach ( $fonts as $name => $val ) {
		$rows_fonts[] = array(
			'token' => $name,
			'value' => $val,
		);
	}

	// --- Composition ordonnée des groupes. ---
	$groups = array();

	$add = static function ( $key, $title, $render, $rows ) use ( &$groups ) {
		if ( ! empty( $rows ) ) {
			$groups[] = array(
				'key'    => $key,
				'title'  => $title,
				'render' => $render,
				'rows'   => $rows,
			);
		}
	};

	$add( 'colors-semantic', __( 'Couleurs — sémantiques (light + dark)', '180c' ), 'color', $rows_semantic );
	$add( 'colors-alias', __( 'Couleurs — alias de vocabulaire', '180c' ), 'color', $rows_alias );
	$add( 'colors-const', __( 'Couleurs — constantes & overlay', '180c' ), 'color', $rows_const );
	$add( 'font-family', __( 'Typographie — familles', '180c' ), 'font', $rows_fonts );
	$add( 'text-fluid', __( 'Typographie — échelle fluide (contenu éditorial)', '180c' ), 'scale', $by_cat['text-fluid'] ?? array() );
	$add( 'text-fixed', __( 'Typographie — échelle fixe (chrome / UI)', '180c' ), 'scale', $by_cat['text-fixed'] ?? array() );
	$add( 'font-alias', __( 'Typographie — alias historiques', '180c' ), 'value', $by_cat['font-alias'] ?? array() );
	$add( 'line-height', __( 'Line heights', '180c' ), 'value', $by_cat['line-height'] ?? array() );
	$add( 'font-weight', __( 'Font weights', '180c' ), 'value', $by_cat['font-weight'] ?? array() );
	$add( 'space-tshirt', __( 'Espacements — échelle t-shirt', '180c' ), 'space', $by_cat['space-tshirt'] ?? array() );
	$add( 'space-num', __( 'Espacements — échelle numérotée', '180c' ), 'space', $by_cat['space-num'] ?? array() );
	$add( 'space-alias', __( 'Espacements — alias historiques', '180c' ), 'value', $by_cat['space-alias'] ?? array() );
	$add( 'radius', __( 'Rayons', '180c' ), 'radius', $by_cat['radius'] ?? array() );
	$add( 'shadow', __( 'Ombres', '180c' ), 'shadow', $by_cat['shadow'] ?? array() );
	$add( 'z-index', __( 'Z-index', '180c' ), 'value', $by_cat['z-index'] ?? array() );
	$add( 'breakpoint', __( 'Breakpoints', '180c' ), 'value', $by_cat['breakpoint'] ?? array() );
	$add( 'transition', __( 'Transitions', '180c' ), 'value', $by_cat['transition'] ?? array() );
	$add( 'layout', __( 'Layout & conteneur', '180c' ), 'value', $by_cat['layout'] ?? array() );
	$add( 'side-menu', __( 'Side menu', '180c' ), 'value', $by_cat['side-menu'] ?? array() );
	$add( 'hero', __( 'Hero', '180c' ), 'value', $by_cat['hero'] ?? array() );
	$add( 'misc', __( 'Autres', '180c' ), 'value', $by_cat['misc'] ?? array() );

	return $groups;
}
