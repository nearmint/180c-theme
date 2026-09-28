/**
 * Header sticky avec shrink au scroll.
 *
 * Pose la classe `.is-scrolled` sur `.site-header` quand `window.scrollY`
 * dépasse SCROLL_THRESHOLD (80px). Le CSS gère ensuite la transition de
 * hauteur (80px → 56px) et du logo (50px → 36px), ainsi que l'apparition
 * d'une bordure et d'une ombre subtiles.
 *
 * Pose aussi une variable CSS `--wp-admin-bar-height` sur <html> quand
 * `body.admin-bar` est présent, pour que le `top` du sticky reste sous
 * la barre WP (32px ≥ 783px de viewport, 46px en-dessous).
 *
 * Implémentation :
 *  - Listener passif (ne bloque pas le scroll natif).
 *  - rAF throttle : un seul update par frame.
 *  - État initial : on appelle update() immédiatement (cas où la page est
 *    rechargée avec scrollY > 0 — F5 au milieu d'un article par exemple).
 *  - Respecte `prefers-reduced-motion` côté CSS (transitions désactivées).
 */

const SCROLL_THRESHOLD = 80;
const SCROLLED_CLASS = 'is-scrolled';

let header = null;
let ticking = false;

function updateScrollState() {
	if ( ! header ) {
		ticking = false;
		return;
	}
	const scrolled = window.scrollY > SCROLL_THRESHOLD;
	header.classList.toggle( SCROLLED_CLASS, scrolled );
	ticking = false;
}

function onScroll() {
	if ( ticking ) {
		return;
	}
	ticking = true;
	window.requestAnimationFrame( updateScrollState );
}

/**
 * Synchronise la hauteur de la WP admin bar dans une variable CSS.
 * Appelée à l'init et au resize (la WP admin bar passe de 32 à 46px
 * en-dessous de 783px de viewport).
 */
function syncAdminBarHeight() {
	if ( ! document.body.classList.contains( 'admin-bar' ) ) {
		return;
	}
	const height = window.matchMedia( '(max-width: 782px)' ).matches ? 46 : 32;
	document.documentElement.style.setProperty( '--wp-admin-bar-height', height + 'px' );
}

function init() {
	header = document.querySelector( '.site-header' );
	if ( ! header ) {
		return;
	}

	syncAdminBarHeight();

	// État initial via onScroll() et non updateScrollState() en direct :
	// syncAdminBarHeight() vient d'écrire une variable CSS sur <html>, et lire
	// window.scrollY juste derrière force le navigateur à recalculer la mise en
	// page immédiatement. Le rAF repousse la lecture après cette écriture — et
	// s'exécute avant la peinture, donc le header rechargé au milieu d'un
	// article s'affiche déjà réduit, sans transition parasite.
	onScroll();

	window.addEventListener( 'scroll', onScroll, { passive: true } );
	window.addEventListener( 'resize', syncAdminBarHeight, { passive: true } );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init, { once: true } );
} else {
	init();
}
