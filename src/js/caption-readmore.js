/**
 * Légendes d'image/galerie repliables — « Voir plus » (blocs-gutenberg-ds v2).
 *
 * Enhancement progressif STRICT, front uniquement :
 *  - Sans JS : la légende (`figcaption`) est intégralement visible (le CSS de
 *    troncature n'est posé que via les classes `.caption-readmore*` ajoutées
 *    ici).
 *  - Avec JS : le texte de la légende est enveloppé dans
 *    `<span class="caption-readmore__text">` (tronqué à 1 ligne en CSS). Si le
 *    texte déborde, un `<button class="caption-readmore__toggle">` accessible
 *    (`aria-expanded`) est injecté. Clic → bascule `.is-expanded` (révèle le
 *    crédit complet) et le libellé « Voir plus » ↔ « Voir moins ».
 *
 * Cible : légendes des blocs image et galerie du corps d'article
 * (`.article__body :is(.wp-block-image, .wp-block-gallery) figcaption`).
 *
 * Robustesse :
 *  - idempotent (drapeau `data-caption-readmore` + WeakSet) ;
 *  - re-scan sur `load` (les images lazy modifient la largeur de colonne et
 *    donc le débordement) ;
 *  - re-évaluation des légendes non encore « toggleées » sur resize (une
 *    légende qui tient sur une ligne en large peut déborder en étroit) ;
 *  - délégation : l'écouteur de clic est posé par légende au moment de
 *    l'injection du bouton (markup généré ici, pas de re-rendu serveur).
 *
 * @package 180c-theme
 */

const SCOPE = '.article__body';
// Légendes des blocs IMAGE autonomes uniquement. Les galeries sont exclues :
// leur légende est rendue en plein (sous l'image, sans troncature) par
// gallery-slideshow.js — pas de « Voir plus » en galerie.
const CAPTION_SELECTOR = `${ SCOPE } .wp-block-image figcaption`;

const LABEL_MORE = 'Voir plus';
const LABEL_LESS = 'Voir moins';

/** Légendes enrichies (wrap posé), en attente/équipées d'un toggle. */
const tracked = [];
const wrapped = new WeakSet();

/**
 * Détecte le débordement vertical du texte tronqué (line-clamp: 1).
 *
 * @param {HTMLElement} textEl
 * @return {boolean}
 */
function isOverflowing( textEl ) {
	return textEl.scrollHeight - textEl.clientHeight > 1;
}

/**
 * Injecte le bouton de bascule sur une légende qui déborde.
 *
 * @param {Object} entry { caption, textEl, btn }
 */
function addToggle( entry ) {
	if ( entry.btn ) {
		return;
	}
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = 'caption-readmore__toggle';
	btn.setAttribute( 'aria-expanded', 'false' );
	btn.textContent = LABEL_MORE;

	btn.addEventListener( 'click', () => {
		const open = entry.caption.classList.toggle( 'is-expanded' );
		btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		btn.textContent = open ? LABEL_LESS : LABEL_MORE;
	} );

	entry.caption.appendChild( btn );
	entry.btn = btn;
}

/**
 * Enveloppe le texte de la légende et l'enregistre pour évaluation.
 *
 * @param {HTMLElement} caption
 */
function wrap( caption ) {
	if ( wrapped.has( caption ) ) {
		return;
	}
	// Exclut les légendes de galerie (rendu plein, géré par gallery-slideshow).
	if ( caption.closest( '.wp-block-gallery' ) ) {
		return;
	}
	const text = caption.textContent.trim();
	if ( '' === text ) {
		return;
	}
	wrapped.add( caption );

	const textEl = document.createElement( 'span' );
	textEl.className = 'caption-readmore__text';
	while ( caption.firstChild ) {
		textEl.appendChild( caption.firstChild );
	}
	caption.appendChild( textEl );
	caption.classList.add( 'caption-readmore' );

	tracked.push( { caption, textEl, btn: null } );
}

/**
 * Mesure toutes les légendes suivies et ajoute le toggle à celles qui
 * débordent (et qui n'en ont pas encore). Mesure dans un rAF pour garantir
 * un layout à jour.
 */
function evaluate() {
	window.requestAnimationFrame( () => {
		tracked.forEach( ( entry ) => {
			if ( entry.btn ) {
				return;
			}
			if ( isOverflowing( entry.textEl ) ) {
				addToggle( entry );
			}
		} );
	} );
}

/**
 * Scanne le DOM pour les légendes cibles et les enveloppe.
 */
function scan() {
	document.querySelectorAll( CAPTION_SELECTOR ).forEach( wrap );
	evaluate();
}

let resizeRaf = 0;
function onResize() {
	if ( resizeRaf ) {
		return;
	}
	resizeRaf = window.requestAnimationFrame( () => {
		resizeRaf = 0;
		evaluate();
	} );
}

function init() {
	scan();
	// Les images lazy peuvent recalculer la largeur de colonne après le boot.
	window.addEventListener( 'load', scan, { once: true } );
	window.addEventListener( 'resize', onResize, { passive: true } );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init, { once: true } );
} else {
	init();
}

export { init };
