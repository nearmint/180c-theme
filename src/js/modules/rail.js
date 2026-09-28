/**
 * Rails horizontaux des modules home / Gazette.
 *
 * Enhancement progressif (le rail fonctionne sans JS via le scroll natif) :
 *  - Flèches desktop : révélées uniquement si la piste déborde ET viewport ≥ lg.
 *  - État disabled des flèches selon la position de scroll.
 *  - Skeleton : retire le shimmer dès que l'image de la carte est peinte.
 *
 * Aucune dépendance, aucune lib de carrousel.
 */

const DESKTOP_MQ = window.matchMedia( '(min-width: 1024px)' );

/**
 * Rails enregistrés, mesurés et mis à jour en une seule passe.
 *
 * @type {Array<{measure: Function, apply: Function}>}
 */
const rails = [];

let updateScheduled = false;

/**
 * Rafraîchit l'état de TOUS les rails en séparant strictement lectures et
 * écritures.
 *
 * L'ancienne implémentation entrelaçait les deux au sein d'un même rail
 * (scrollWidth → classList.toggle → scrollWidth → disabled) : chaque lecture
 * qui suit une écriture invalidante oblige le navigateur à recalculer la mise
 * en page sur-le-champ. Avec quatre rails sur la home, cela faisait autant de
 * reflows forcés — le poste relevé par PageSpeed sur rail.js.
 *
 * On mesure donc d'abord tous les rails, puis on écrit tous les états : une
 * seule passe de layout par frame, quel que soit le nombre de rails. Le rAF
 * sert aussi de throttle sur scroll et resize, et s'exécute avant la peinture
 * (aucun état intermédiaire visible).
 */
function scheduleUpdate() {
	if ( updateScheduled ) {
		return;
	}
	updateScheduled = true;

	window.requestAnimationFrame( () => {
		updateScheduled = false;

		// Phase de LECTURE — aucune écriture ici.
		const measures = rails.map( ( rail ) => rail.measure() );

		// Phase d'ÉCRITURE.
		measures.forEach( ( measure, i ) => rails[ i ].apply( measure ) );
	} );
}

/**
 * Active la navigation par flèches d'un rail.
 *
 * @param {HTMLElement} rail Élément `.rail-180c`.
 */
function initRail( rail ) {
	const track = rail.querySelector( '.rail-180c__track' );
	const prev = rail.querySelector( '.rail-180c__arrow--prev' );
	const next = rail.querySelector( '.rail-180c__arrow--next' );

	if ( ! track || ! prev || ! next ) {
		return;
	}

	rails.push( {
		measure: () => {
			const max = track.scrollWidth - track.clientWidth;
			return {
				overflowing: max > 1,
				atStart: track.scrollLeft <= 0,
				atEnd: track.scrollLeft >= max - 1,
			};
		},
		apply: ( { overflowing, atStart, atEnd } ) => {
			rail.classList.toggle(
				'rail-180c--has-nav',
				overflowing && DESKTOP_MQ.matches
			);
			prev.disabled = atStart;
			next.disabled = atEnd;
		},
	} );

	const step = ( direction ) => {
		track.scrollBy( {
			left: direction * track.clientWidth * 0.8,
			behavior: 'smooth',
		} );
	};

	prev.addEventListener( 'click', () => step( -1 ) );
	next.addEventListener( 'click', () => step( 1 ) );
	track.addEventListener( 'scroll', scheduleUpdate, { passive: true } );
	DESKTOP_MQ.addEventListener( 'change', scheduleUpdate );
	window.addEventListener( 'resize', scheduleUpdate );
}

/**
 * Marque le conteneur média d'une image comme chargé (stoppe le shimmer).
 *
 * @param {HTMLImageElement} img Image de carte ou de tuile.
 */
function markLoaded( img ) {
	const media = img.closest( '.card-180c__media' );
	if ( media ) {
		media.classList.add( 'is-loaded' );
	}
}

/**
 * Reveal au scroll des modules home — pose .is-visible sur chaque
 * `.home-module` dès qu'il entre dans le viewport (fondu + léger glissement
 * gérés en CSS).
 *
 * Seuls les modules situés SOUS la ligne de flottaison au chargement sont
 * inscrits au reveal, via la classe .is-reveal-pending qui porte l'état
 * masqué. Un module déjà visible ne peut pas « entrer » au scroll : le
 * masquer puis le refondre ne ferait que retarder sa peinture. C'était la
 * cause d'un LCP dégradé sur la home — le hero éditorial portait l'image LCP
 * et se retrouvait en opacity:0 dès l'exécution de ce module, soit ~600 ms
 * après un téléchargement d'image pourtant terminé à ~440 ms.
 *
 * Sans JS (ou en reduced-motion / IO absent), aucune classe n'est posée et
 * tous les modules restent visibles d'emblée.
 *
 * Réutilise le pattern IntersectionObserver du module Cahiers de Delphine.
 */
function initReveal() {
	const prefersReducedMotion =
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( prefersReducedMotion || typeof IntersectionObserver === 'undefined' ) {
		return;
	}

	const container = document.querySelector( '.home-modules' );
	const modules = container
		? container.querySelectorAll( '.home-module' )
		: [];

	if ( ! container || ! modules.length ) {
		return;
	}

	// Phase de LECTURE seule : on mesure tous les modules avant d'écrire la
	// moindre classe, pour ne déclencher qu'une seule passe de layout.
	const viewportHeight =
		window.innerHeight || document.documentElement.clientHeight;
	const pending = [];

	modules.forEach( ( m ) => {
		if ( m.getBoundingClientRect().top < viewportHeight ) {
			return;
		}
		pending.push( m );
	} );

	if ( ! pending.length ) {
		return;
	}

	const observer = new IntersectionObserver(
		( entries, obs ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					obs.unobserve( entry.target );
				}
			} );
		},
		{
			rootMargin: '0px 0px -10% 0px',
			threshold:  0.05,
		}
	);

	// Phase d'ÉCRITURE.
	pending.forEach( ( m ) => {
		m.classList.add( 'is-reveal-pending' );
		observer.observe( m );
	} );
}

/**
 * Point d'entrée du module (importé à la demande par main.js).
 */
export function init() {
	document.querySelectorAll( '.rail-180c' ).forEach( initRail );

	initReveal();

	// État initial des flèches : dans le rAF, donc APRÈS les écritures de
	// initReveal(). Une seule invalidation de layout pour toute l'init.
	scheduleUpdate();

	document
		.querySelectorAll( '.home-module .card-180c__image' )
		.forEach( ( img ) => {
			if ( img.complete ) {
				markLoaded( img );
				return;
			}
			img.addEventListener( 'load', () => markLoaded( img ), { once: true } );
			img.addEventListener( 'error', () => markLoaded( img ), { once: true } );
		} );
}
