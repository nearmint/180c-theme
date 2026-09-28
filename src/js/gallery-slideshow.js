/**
 * Galerie d'article en slideshow + « afficher en grand » (blocs-gutenberg-ds v2).
 *
 * Enhancement progressif, FRONT uniquement :
 *  - Sans JS : la galerie Gutenberg native (grille) reste affichée telle quelle.
 *  - Avec JS : chaque `.wp-block-gallery` du corps d'article contenant > 1 image
 *    est transformée en slideshow via le moteur partagé `modules/slideshow.js`
 *    (extrait du carousel Abonnement). Navigation flèches + points + clavier +
 *    swipe (scroll-snap). Une seule image → on ne transforme pas (galerie
 *    native conservée).
 *
 * « Afficher en grand » : on réutilise la lightbox existante
 * (`modules/lightbox.js`) qui injecte un bouton zoom par figure d'image. Le
 * WeakSet interne de la lightbox garantit l'idempotence vis-à-vis de
 * l'enhancement global déjà déclenché par `main.js`.
 *
 * CONTRAINTE ÉDITORIALE (HARD) : aucune photo recadrée, aucun overlay sur les
 * images. Les images gardent leur ratio natif ; seules des CHROME UI (flèches,
 * points, bouton zoom) sont ajoutées — jamais de filtre/voile sur la photo.
 *
 * @package 180c-theme
 */

import { createSlideshow } from './modules/slideshow.js';
import { enhanceFigureSet } from './modules/lightbox.js';

const GALLERY_SELECTOR = '.article__body .wp-block-gallery';

const enhanced = new WeakSet();

const ARROW_LEFT =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>';
const ARROW_RIGHT =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';

/**
 * Construit un bouton flèche.
 *
 * @param {string} dir   'prev' | 'next'.
 * @param {string} label aria-label.
 * @param {string} icon  Markup SVG.
 * @return {HTMLButtonElement}
 */
function makeArrow( dir, label, icon ) {
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = `gallery-slideshow__arrow gallery-slideshow__arrow--${ dir }`;
	btn.setAttribute( 'aria-label', label );
	btn.innerHTML = icon;
	return btn;
}

/**
 * Transforme une galerie en slideshow.
 *
 * @param {HTMLElement} gallery
 */
function enhanceGallery( gallery ) {
	if ( enhanced.has( gallery ) ) {
		return;
	}

	// Items d'image (enfants directs) de la galerie Gutenberg v2 (nested images).
	const figures = Array.from(
		gallery.querySelectorAll( ':scope > figure.wp-block-image' )
	);
	// Légende au niveau galerie (sous toutes les images) — conservée hors piste.
	const galleryCaption = gallery.querySelector( ':scope > figcaption' );

	if ( figures.length === 0 ) {
		return;
	}
	enhanced.add( gallery );

	// Galerie à 1 image : pas de slideshow (image simple), mais on conserve le
	// bouton « afficher en grand » (la galerie est exclue de l'enhancement
	// global de main.js → c'est ici qu'on enhance ses figures).
	if ( figures.length < 2 ) {
		enhanceFigureSet( figures );
		return;
	}

	// Piste + slides (déplacement des figures existantes : on préserve <img>,
	// légende par image, et tout cadre lightbox déjà injecté).
	const viewport = document.createElement( 'div' );
	viewport.className = 'gallery-slideshow__viewport';

	const track = document.createElement( 'ul' );
	track.className = 'gallery-slideshow__track';
	track.setAttribute( 'role', 'list' );

	const slides = figures.map( ( fig ) => {
		const li = document.createElement( 'li' );
		li.className = 'gallery-slideshow__slide';
		li.appendChild( fig );
		track.appendChild( li );
		return li;
	} );
	viewport.appendChild( track );

	// Contrôles flèches groupés (bas-droite via CSS). Pas de pagination à points.
	const prevBtn = makeArrow( 'prev', 'Image précédente', ARROW_LEFT );
	const nextBtn = makeArrow( 'next', 'Image suivante', ARROW_RIGHT );
	const controls = document.createElement( 'div' );
	controls.className = 'gallery-slideshow__controls';
	controls.append( prevBtn, nextBtn );
	viewport.appendChild( controls );

	// Légende synchronisée SOUS l'image (hors piste, pour que la piste — donc le
	// viewport — n'ait que la hauteur de l'image et que les flèches restent bien
	// posées sur l'image). Les légendes par image restent dans leurs figures
	// (masquées dans la piste) : elles alimentent la lightbox ET cette légende.
	const captions = figures.map( ( fig ) => {
		const cap = fig.querySelector( 'figcaption' );
		return cap ? cap.textContent.trim() : '';
	} );
	const captionEl = document.createElement( 'p' );
	captionEl.className = 'gallery-slideshow__caption';
	const setCaption = ( i ) => {
		const text = captions[ i ] || '';
		captionEl.textContent = text;
		captionEl.hidden = '' === text;
	};
	setCaption( 0 );

	// Recompose la galerie : viewport + légende synchronisée (+ légende galerie).
	// Les figures sont déjà déplacées dans `track` (détaché) → sûr de vider.
	gallery.replaceChildren( viewport, captionEl );
	if ( galleryCaption ) {
		gallery.appendChild( galleryCaption );
	}

	gallery.classList.add( 'gallery-slideshow', 'is-enhanced' );

	createSlideshow( { track, slides, prevBtn, nextBtn, onChange: setCaption } );

	// « Afficher en grand » NAVIGABLE : un bouton zoom par image ; le clic ouvre
	// la lightbox partagée sur l'ensemble de la galerie, avec défilement
	// (flèches + clavier) — même composant que la recette/article.
	enhanceFigureSet( figures );
}

function init() {
	document.querySelectorAll( GALLERY_SELECTOR ).forEach( enhanceGallery );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init, { once: true } );
} else {
	init();
}

export { init };
