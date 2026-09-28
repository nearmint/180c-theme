/**
 * Fiche produit — carrousel de la bande de vignettes (galerie WC native).
 *
 * WooCommerce/flexslider génère la nav de vignettes (`.flex-control-thumbs`)
 * APRÈS son initialisation (jQuery ready). Ce module, enhancement only :
 *   - attend l'apparition de `.flex-control-thumbs` (MutationObserver) ;
 *   - si plus de 4 vignettes, enveloppe la bande entre deux flèches prev/next
 *     qui défilent la piste (scroll horizontal) ;
 *   - active/désactive les flèches selon la position de scroll.
 *
 * Le clic sur une vignette (swap du visuel principal) et le clic sur le visuel
 * principal (lightbox PhotoSwipe) restent gérés nativement par WC/flexslider :
 * on ne déplace que le DOM (les écouteurs liés aux éléments persistent).
 *
 * Sans JS : la bande reste lisible et défilable (overflow-x), le swap natif
 * fonctionne — seules les flèches custom manquent.
 */

const VISIBLE = 4;

/**
 * Enveloppe la bande de vignettes entre deux flèches de défilement.
 *
 * @param {HTMLElement} thumbs Élément `.flex-control-thumbs` (ol).
 * @returns {void}
 */
function buildCarousel( thumbs ) {
	const items = thumbs.querySelectorAll( 'li' );
	if ( items.length <= VISIBLE ) {
		return; // La bande tient sans défilement : pas de flèches.
	}

	const wrap = document.createElement( 'div' );
	wrap.className = 'product-gallery-thumbs';

	const prev = document.createElement( 'button' );
	prev.type = 'button';
	prev.className = 'product-gallery-thumbs__nav product-gallery-thumbs__nav--prev';
	prev.setAttribute( 'aria-label', 'Vignettes précédentes' );
	prev.innerHTML = '<span aria-hidden="true">‹</span>';

	const next = document.createElement( 'button' );
	next.type = 'button';
	next.className = 'product-gallery-thumbs__nav product-gallery-thumbs__nav--next';
	next.setAttribute( 'aria-label', 'Vignettes suivantes' );
	next.innerHTML = '<span aria-hidden="true">›</span>';

	// Insère le wrapper à la place de la bande, puis y déplace la bande entre
	// les flèches (reparentage : les écouteurs flexslider des <li> persistent).
	thumbs.parentNode.insertBefore( wrap, thumbs );
	wrap.appendChild( prev );
	wrap.appendChild( thumbs );
	wrap.appendChild( next );

	const page = () => Math.max( thumbs.clientWidth, 1 );
	prev.addEventListener( 'click', () => thumbs.scrollBy( { left: -page(), behavior: 'smooth' } ) );
	next.addEventListener( 'click', () => thumbs.scrollBy( { left: page(), behavior: 'smooth' } ) );

	const update = () => {
		const max = thumbs.scrollWidth - thumbs.clientWidth - 1;
		prev.disabled = thumbs.scrollLeft <= 0;
		next.disabled = thumbs.scrollLeft >= max;
	};
	thumbs.addEventListener( 'scroll', update, { passive: true } );
	window.addEventListener( 'resize', update );
	update();
}

/**
 * Initialisation (appelée depuis main.js sur la fiche produit).
 *
 * @returns {void}
 */
export function init() {
	const gallery = document.querySelector( '.product-page__gallery' );
	if ( ! gallery ) {
		return;
	}

	const existing = gallery.querySelector( '.flex-control-thumbs' );
	if ( existing ) {
		buildCarousel( existing );
		return;
	}

	// flexslider crée la bande après son init : on observe jusqu'à apparition.
	const observer = new MutationObserver( () => {
		const thumbs = gallery.querySelector( '.flex-control-thumbs' );
		if ( thumbs ) {
			observer.disconnect();
			buildCarousel( thumbs );
		}
	} );
	observer.observe( gallery, { childList: true, subtree: true } );

	// Filet de sécurité : produit à une seule image (pas de flexslider/bande).
	setTimeout( () => observer.disconnect(), 4000 );
}
