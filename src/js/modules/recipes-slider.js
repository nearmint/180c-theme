/**
 * Module home « Slider de recettes » — une recette en vedette à la fois.
 *
 * Enhancement progressif, sur le modèle de rail.js : le viewport est un
 * scroller horizontal NATIF avec scroll-snap (cf. recipes-slider.css). Sans ce
 * module, le premier slide reste lisible et les suivants restent atteignables
 * au doigt ou à la molette — rien n'est cassé, seuls les contrôles manquent.
 *
 * La mécanique de navigation (scrollTo, flèches, clavier, resynchronisation de
 * l'index sur scroll manuel via IntersectionObserver, pagination) est celle du
 * moteur générique `createSlideshow`, utilisé en mode `loop` — le handoff exige
 * un bouclage et des flèches jamais désactivées.
 *
 * Ce module n'ajoute donc que ce qui lui est propre :
 *  - révèle compteur, flèches et frise de pagination (classe `is-enhanced`) ;
 *    ils sont masqués tant qu'ils ne pilotent rien ;
 *  - le compteur « 01 / 06 » et l'annonce `aria-live` ;
 *  - le drag à la souris (le tactile est déjà couvert nativement).
 *
 * @package 180c-theme
 */

import { createSlideshow } from './slideshow.js';

// Distance minimale d'un drag souris pour valider un changement de slide (px).
const DRAG_THRESHOLD = 60;

// En deçà, le geste est un clic : on ne l'intercepte pas. Au-delà, c'est un
// drag, et le clic qui le termine doit être annulé.
const CLICK_SLOP = 5;

/**
 * Branche le drag souris sur le viewport.
 *
 * Le tactile est déjà pris en charge par le scroller natif (avec inertie et
 * snap) : on ne branche QUE le pointeur souris, pour ne pas remplacer un
 * comportement système par une approximation.
 *
 * @param {HTMLElement} viewport Élément scrollable.
 * @param {Object}      show     API retournée par createSlideshow.
 */
function initDrag( viewport, show ) {
	let startX = null;
	let startScroll = 0;
	let startIndex = 0;
	let moved = false;

	/**
	 * Avale le `click` qui clôt un drag.
	 *
	 * @param {MouseEvent} event Clic terminal du geste.
	 */
	const swallowClick = ( event ) => {
		event.preventDefault();
		event.stopPropagation();
	};

	const onMove = ( event ) => {
		if ( startX === null ) {
			return;
		}

		const delta = event.clientX - startX;

		// Tant que le seuil n'est pas franchi, on ne touche à RIEN : le geste
		// est peut-être un simple clic, et le moindre scroll ou preventDefault
		// perturberait la sélection de texte comme la navigation.
		if ( ! moved && Math.abs( delta ) <= CLICK_SLOP ) {
			return;
		}

		if ( ! moved ) {
			moved = true;
			viewport.classList.add( 'is-dragging' );
		}

		// Empêche le navigateur de démarrer son propre drag d'image/texte.
		event.preventDefault();
		viewport.scrollLeft = startScroll - delta;
	};

	const endDrag = ( event ) => {
		if ( startX === null ) {
			return;
		}

		const delta = event.clientX - startX;
		const dragged = moved;

		startX = null;
		moved = false;
		viewport.classList.remove( 'is-dragging' );
		window.removeEventListener( 'pointermove', onMove );
		window.removeEventListener( 'pointerup', endDrag );
		window.removeEventListener( 'pointercancel', endDrag );

		if ( ! dragged ) {
			// Simple clic : le lien fait son travail, on ne s'en mêle pas.
			return;
		}

		// Un drag se termine par un `click` que le navigateur envoie au lien
		// sous le curseur : sans cette annulation, tout glissement de la
		// souris naviguerait vers la recette. Le garde est retiré au tick
		// suivant plutôt qu'avec `once` — un geste clos par pointercancel
		// n'émet aucun clic, et le garde survivrait pour avaler le clic
		// LÉGITIME d'après.
		viewport.addEventListener( 'click', swallowClick, { capture: true } );
		window.setTimeout( () => {
			viewport.removeEventListener( 'click', swallowClick, {
				capture: true,
			} );
		}, 0 );

		// Le snap natif est désactivé pendant le geste : personne ne recollera
		// sur un slide à notre place.
		show.goTo(
			Math.abs( delta ) > DRAG_THRESHOLD
				? startIndex + ( delta < 0 ? 1 : -1 )
				: startIndex
		);
	};

	viewport.addEventListener( 'pointerdown', ( event ) => {
		if ( event.pointerType !== 'mouse' || event.button !== 0 ) {
			return;
		}
		// Aucun filtrage sur la cible : depuis que le slide est entièrement
		// cliquable (stretched-link), le ::after du CTA recouvre toute la
		// carte, donc `closest('a')` matcherait partout et le drag ne
		// démarrerait jamais.
		startX = event.clientX;
		startScroll = viewport.scrollLeft;
		startIndex = show.index;
		moved = false;

		// Suivi sur `window` et SURTOUT PAS via setPointerCapture. La capture
		// retarge aussi les événements souris de compatibilité : mousedown ET
		// mouseup viseraient le viewport, donc le `click` serait dispatché sur
		// le viewport et jamais sur le <a>. C'était la cause du « clic qui ne
		// marche pas » signalé sur Chrome/macOS — le lien ne recevait
		// simplement plus rien. `window` suit le curseur hors du viewport sans
		// rien retarger.
		window.addEventListener( 'pointermove', onMove );
		window.addEventListener( 'pointerup', endDrag );
		window.addEventListener( 'pointercancel', endDrag );
	} );
}

/**
 * Active un slider.
 *
 * @param {HTMLElement} slider Élément `.recipes-slider`.
 */
function initSlider( slider ) {
	const viewport = slider.querySelector( '[data-slider-viewport]' );
	const slides = Array.from( slider.querySelectorAll( '[data-slider-slide]' ) );

	// Un seul slide : les contrôles n'auraient nulle part où aller. On laisse
	// le module en l'état (slide unique, lisible) plutôt que d'afficher une
	// pagination à une entrée et deux flèches sans effet.
	if ( ! viewport || slides.length < 2 ) {
		return;
	}

	const live = slider.querySelector( '[data-slider-live]' );
	const thumbs = Array.from( slider.querySelectorAll( '[data-slider-go]' ) );
	const total = slides.length;

	const titles = thumbs.map( ( button ) => {
		const label = button.querySelector( '.recipes-slider__thumb-label' );
		return label ? label.textContent.trim() : '';
	} );

	// Premier appel d'onChange = rendu initial : rien n'a changé, donc rien à
	// annoncer. Sans ce garde-fou le lecteur d'écran parle au chargement.
	let announced = false;

	const show = createSlideshow( {
		track: viewport,
		slides,
		prevBtn: slider.querySelector( '[data-slider-prev]' ),
		nextBtn: slider.querySelector( '[data-slider-next]' ),
		dots: thumbs,
		loop: true,
		onChange: ( index ) => {
			if ( live && announced ) {
				live.textContent = `Recette ${ index + 1 } sur ${ total }${
					titles[ index ] ? ` : ${ titles[ index ] }` : ''
				}`;
			}
			announced = true;
		},
	} );

	if ( ! show ) {
		return;
	}

	slider.classList.add( 'is-enhanced' );
	initDrag( viewport, show );

	// La position de scroll d'un slide dépend de la largeur du viewport : après
	// un resize, l'index courant et le scroll ne coïncident plus.
	window.addEventListener( 'resize', () => show.goTo( show.index ) );
}

/**
 * Point d'entrée du module (importé à la demande par main.js).
 */
export function init() {
	document.querySelectorAll( '[data-recipes-slider]' ).forEach( initSlider );
}
