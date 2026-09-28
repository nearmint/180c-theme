<?php
/**
 * Icônes du site : favicon, icône iOS, icônes du manifeste web.
 *
 * Le jeu d'icônes est SERVI PAR LE THÈME (`assets/favicon/`), pas par l'option
 * `site_icon` de WordPress. Deux raisons :
 *
 *  1. `wp_site_icon()` ne sait produire que des PNG/WebP redimensionnés depuis
 *     la médiathèque : ni `.ico` multi-résolutions, ni icône maskable, ni
 *     manifeste. En production le site servait un `.webp` en `rel="icon"`,
 *     format qu'une partie des crawlers et des vieux clients ne lisent pas.
 *  2. L'icône fait partie de l'identité livrée avec le thème : la versionner
 *     avec le code évite qu'un déploiement et un réglage d'admin divergent.
 *
 * L'option `site_icon` reste en base et continue d'alimenter ses autres
 * consommateurs (repli du logo dans `inc/seo/schema.php`, aperçu des
 * notifications) — seule sa sortie dans le `<head>` est reprise ici.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dossier des icônes, relatif à la racine du thème.
 */
const _180C_FAVICON_PATH = '/assets/favicon';

/**
 * Le jeu d'icônes du thème est-il présent sur le disque ?
 *
 * Sert de garde-fou : si `assets/favicon/` manquait (artefact de déploiement
 * incomplet), le thème laisse WordPress reprendre la main plutôt que d'émettre
 * des `<link>` vers des 404.
 *
 * @return bool
 */
function _180c_has_theme_favicon() {
	static $has = null;

	if ( null === $has ) {
		$has = file_exists( _180C_THEME_DIR . _180C_FAVICON_PATH . '/favicon.ico' );
	}

	return $has;
}

/**
 * URL versionnée d'un fichier du jeu d'icônes.
 *
 * Les navigateurs cachent les favicons pour des semaines et le cache page de
 * production n'est purgé qu'au changement du manifeste Vite : sans empreinte
 * dans l'URL, un changement d'icône resterait invisible très longtemps. La
 * date de modification du `.ico` sert d'empreinte commune à tout le jeu.
 *
 * @param string $file Nom de fichier dans `assets/favicon/`.
 * @return string URL absolue.
 */
function _180c_favicon_url( $file ) {
	static $version = null;

	if ( null === $version ) {
		$ico     = _180C_THEME_DIR . _180C_FAVICON_PATH . '/favicon.ico';
		$mtime   = file_exists( $ico ) ? filemtime( $ico ) : false;
		$version = false !== $mtime ? (string) $mtime : _180C_VERSION;
	}

	return _180C_THEME_URI . _180C_FAVICON_PATH . '/' . $file . '?v=' . rawurlencode( $version );
}

/**
 * Désenregistre la sortie d'icônes de WordPress.
 *
 * `wp_site_icon()` est accroché à trois endroits, avec deux priorités
 * différentes ; les retirer toutes les trois évite d'avoir deux jeux de
 * `<link rel="icon">` concurrents dans le même `<head>`.
 *
 * @return void
 */
function _180c_unhook_wp_site_icon() {
	if ( ! _180c_has_theme_favicon() ) {
		return;
	}

	remove_action( 'wp_head', 'wp_site_icon', 99 );
	remove_action( 'login_head', 'wp_site_icon', 99 );
	remove_action( 'admin_head', 'wp_site_icon' );
}
add_action( 'init', '_180c_unhook_wp_site_icon' );

/**
 * Émet les `<link>` d'icônes.
 *
 * Jeu volontairement minimal — c'est l'état de l'art actuel, pas la liste
 * historique des vingt balises de 2015 :
 *
 *  - `.ico` (16/32/48) : encore réclamé par des crawlers et des lecteurs RSS ;
 *  - un PNG 96 : ce que servent réellement les navigateurs modernes ;
 *  - `apple-touch-icon` 180 : écran d'accueil iOS ;
 *  - le manifeste : icônes 192/512 + maskable pour Android.
 *
 * Volontairement absents : `msapplication-TileImage` (tuiles Windows, mortes)
 * et `rel="mask-icon"` (onglet épinglé Safari, qui exige un SVG monochrome —
 * la source disponible est un bitmap, un tracé automatique donnerait une forme
 * approximative plutôt qu'une absence franche).
 *
 * @param bool $with_manifest Émettre aussi le lien vers le manifeste web.
 * @return void
 */
function _180c_render_favicon_links( $with_manifest = true ) {
	if ( ! _180c_has_theme_favicon() ) {
		return;
	}

	printf(
		'<link rel="icon" href="%s" sizes="32x32">' . "\n",
		esc_url( _180c_favicon_url( 'favicon.ico' ) )
	);
	printf(
		'<link rel="icon" type="image/png" href="%s" sizes="96x96">' . "\n",
		esc_url( _180c_favicon_url( 'favicon-96.png' ) )
	);
	printf(
		'<link rel="apple-touch-icon" href="%s">' . "\n",
		esc_url( _180c_favicon_url( 'apple-touch-icon.png' ) )
	);

	if ( $with_manifest ) {
		printf(
			'<link rel="manifest" href="%s">' . "\n",
			esc_url( _180c_favicon_url( 'site.webmanifest' ) )
		);
	}
}

/**
 * Icônes du front.
 *
 * @return void
 */
function _180c_render_favicon_links_front() {
	_180c_render_favicon_links( true );
}
add_action( 'wp_head', '_180c_render_favicon_links_front', 2 );

/**
 * Icônes de l'admin et de la page de connexion.
 *
 * Sans manifeste : il décrit le site public, il n'a rien à faire derrière
 * `wp-admin`.
 *
 * @return void
 */
function _180c_render_favicon_links_admin() {
	_180c_render_favicon_links( false );
}
add_action( 'admin_head', '_180c_render_favicon_links_admin' );
add_action( 'login_head', '_180c_render_favicon_links_admin' );

/**
 * Sert `/favicon.ico` (racine du domaine).
 *
 * Beaucoup de clients demandent `/favicon.ico` sans lire le `<head>`. Par
 * défaut WordPress y répond en redirigeant vers `site_icon` — donc vers le
 * `.webp` de la médiathèque. On préempte cette redirection ici : `do_faviconico`
 * est déclenché par `do_favicon()` AVANT son propre `wp_redirect()`.
 *
 * Ne s'applique que si aucun `favicon.ico` physique n'existe à la racine du
 * document : dans ce cas Apache le sert directement et WordPress n'est jamais
 * appelé (voir les actions manuelles de déploiement).
 *
 * @return void
 */
function _180c_serve_root_favicon() {
	if ( ! _180c_has_theme_favicon() ) {
		return;
	}

	// 302 et non 301 : la cible dépend du dossier du thème, une redirection
	// permanente mise en cache par les navigateurs serait pénible à corriger.
	wp_safe_redirect( _180c_favicon_url( 'favicon.ico' ), 302 );
	exit;
}
add_action( 'do_faviconico', '_180c_serve_root_favicon' );
