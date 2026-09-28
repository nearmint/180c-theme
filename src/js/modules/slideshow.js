/**
 * Moteur de slideshow générique — scroll-snap + flèches + points + clavier.
 *
 * Extrait de la mécanique du carousel de la page Abonnement
 * (`src/js/modules/subscribe.js::initCarousel`) : piste à scroll-snap
 * horizontal, navigation par flèches (`scrollTo`), pagination à points,
 * synchronisation de l'index courant sur scroll manuel via
 * `IntersectionObserver`, et flèches clavier (←/→) quand la piste a le focus.
 *
 * Généralisé ici (agnostique aux classes BEM) pour être réutilisé par la
 * galerie d'article (`src/js/gallery-slideshow.js`). La page Abonnement
 * conserve volontairement sa propre copie inline (markup et contrôles
 * différents, page éprouvée) — duplication maîtrisée et documentée plutôt
 * que refonte risquée d'une page en production.
 *
 * @package 180c-theme
 */

function defaultReducedMotion() {
	return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
}

/**
 * Initialise un slideshow sur des éléments déjà présents dans le DOM.
 *
 * @param {Object}            config
 * @param {HTMLElement}       config.track          Piste scrollable (scroll-snap).
 * @param {HTMLElement[]}     config.slides         Slides (≥ 2 attendus).
 * @param {HTMLButtonElement} [config.prevBtn]      Bouton « précédent ».
 * @param {HTMLButtonElement} [config.nextBtn]      Bouton « suivant ».
 * @param {HTMLButtonElement[]} [config.dots]       Points de pagination.
 * @param {HTMLElement}       [config.counterEl]    Élément affichant l'index (1-based).
 * @param {Function}          [config.onChange]     Callback(index) à chaque changement de slide.
 * @param {Function}          [config.reducedMotion] Prédicat reduced-motion.
 * @param {boolean}           [config.loop]          True → navigation circulaire :
 *                                                   les extrémités se rejoignent et
 *                                                   les flèches ne sont jamais
 *                                                   désactivées. Défaut false
 *                                                   (comportement historique).
 * @return {Object|null} API { goTo, index } ou null si < 2 slides.
 */
export function createSlideshow( config ) {
	const {
		track,
		slides,
		prevBtn = null,
		nextBtn = null,
		dots = [],
		counterEl = null,
		onChange = null,
		reducedMotion = defaultReducedMotion,
		loop = false,
	} = config;

	if ( ! track || ! Array.isArray( slides ) || slides.length < 2 ) {
		return null;
	}

	let index = 0;

	// En mode boucle l'index déborde par modulo au lieu d'être borné. Le `+ n`
	// avant le second modulo ramène les valeurs négatives dans l'intervalle.
	const clamp = ( n ) =>
		loop
			? ( ( n % slides.length ) + slides.length ) % slides.length
			: Math.max( 0, Math.min( n, slides.length - 1 ) );

	const updateUi = () => {
		if ( counterEl ) {
			counterEl.textContent = String( index + 1 );
		}
		// En boucle, aucune extrémité n'est un cul-de-sac : désactiver une
		// flèche mentirait sur ce qui est atteignable.
		if ( prevBtn ) {
			prevBtn.disabled = ! loop && index <= 0;
		}
		if ( nextBtn ) {
			nextBtn.disabled = ! loop && index >= slides.length - 1;
		}
		dots.forEach( ( dot, i ) => {
			const active = i === index;
			dot.classList.toggle( 'is-active', active );
			if ( active ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
		if ( onChange ) {
			onChange( index );
		}
	};

	const goTo = ( n ) => {
		const from = index;
		index = clamp( n );
		const slide = slides[ index ];
		if ( slide ) {
			// Un bouclage traverse toute la piste : l'animer ferait défiler
			// tous les slides intermédiaires. Seuls les sauts adjacents (ou
			// demandés explicitement par la pagination) restent animés.
			const isWrap = loop && Math.abs( index - from ) === slides.length - 1;
			track.scrollTo( {
				left: slide.offsetLeft - track.offsetLeft,
				behavior: isWrap || reducedMotion() ? 'auto' : 'smooth',
			} );
		}
		updateUi();
	};

	prevBtn?.addEventListener( 'click', () => goTo( index - 1 ) );
	nextBtn?.addEventListener( 'click', () => goTo( index + 1 ) );
	dots.forEach( ( dot, i ) => dot.addEventListener( 'click', () => goTo( i ) ) );

	// Clavier (←/→) quand la piste a le focus.
	track.setAttribute( 'tabindex', '0' );
	track.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'ArrowLeft' ) {
			event.preventDefault();
			goTo( index - 1 );
		} else if ( event.key === 'ArrowRight' ) {
			event.preventDefault();
			goTo( index + 1 );
		}
	} );

	// Sync de l'index courant sur scroll manuel (swipe / molette).
	if ( 'IntersectionObserver' in window ) {
		const observer = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( ! entry.isIntersecting ) {
						return;
					}
					const i = slides.indexOf( entry.target );
					if ( i >= 0 ) {
						index = i;
						updateUi();
					}
				} );
			},
			{ root: track, threshold: 0.6 }
		);
		slides.forEach( ( s ) => observer.observe( s ) );
	}

	updateUi();

	return {
		goTo,
		get index() {
			return index;
		},
	};
}

export default createSlideshow;
