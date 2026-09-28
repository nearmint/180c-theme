/**
 * Page Abonnement — enrichissement carousel + accordéons d'offres/FAQ.
 *
 * Markup attendu : [data-component="subscribe"] (cf. page-abonnement.php).
 * Styles associés : src/css/components/subscribe.css.
 *
 * Le module help.js du Centre d'aide ne cible que [data-component="centre-aide"] :
 * on réimplémente ici l'accordéon (mêmes classes/ARIA) pour la page abonnement.
 * Tout reste fonctionnel sans JS : liens d'ajout au panier natifs + scroll
 * latéral du carousel.
 *
 * @package 180c-theme
 */

const ROOT_SELECTOR = '[data-component="subscribe"]';

// Bus d'exclusivité partagé entre les accordéons d'un même groupe
// (data-accordion-group). Les offres ET le module « Offrir » (gift.js, module
// séparé) émettent/écoutent cet événement : ouvrir un panel referme les autres.
// Découplage total — aucun import partagé entre les deux modules.
const ACCORDION_EVENT = '180c:accordion-open';

function prefersReducedMotion() {
	return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
}

/**
 * Signale au groupe qu'un panel vient de s'ouvrir. Sans data-accordion-group
 * (ex. items de la FAQ), aucun événement n'est émis → indépendance préservée.
 *
 * @param {HTMLElement} item Wrapper d'accordéon ouvert.
 */
function notifyAccordionOpen( item ) {
	const group = item?.dataset?.accordionGroup;
	if ( ! group ) return;
	document.dispatchEvent(
		new CustomEvent( ACCORDION_EVENT, { detail: { group, item } } )
	);
}

/* ------------------------------------------------------------------ *
 * Accordéons (réutilisation du pattern Centre d'aide)
 * ------------------------------------------------------------------ */

function openPanel( item ) {
	const trigger = item.querySelector( '.centre-aide__trigger' );
	const panel = item.querySelector( '.centre-aide__answer' );
	if ( ! trigger || ! panel ) return;

	trigger.setAttribute( 'aria-expanded', 'true' );
	panel.hidden = false;

	notifyAccordionOpen( item );

	if ( prefersReducedMotion() ) {
		panel.style.height = 'auto';
		return;
	}

	const target = panel.scrollHeight;
	panel.style.height = '0px';
	// Force reflow.
	// eslint-disable-next-line no-unused-expressions
	panel.offsetHeight;
	panel.style.height = target + 'px';

	const onEnd = ( event ) => {
		if ( event.propertyName !== 'height' ) return;
		panel.style.height = 'auto';
		panel.removeEventListener( 'transitionend', onEnd );
	};
	panel.addEventListener( 'transitionend', onEnd );
}

function closePanel( item ) {
	const trigger = item.querySelector( '.centre-aide__trigger' );
	const panel = item.querySelector( '.centre-aide__answer' );
	if ( ! trigger || ! panel ) return;

	trigger.setAttribute( 'aria-expanded', 'false' );

	if ( prefersReducedMotion() ) {
		panel.style.height = '0px';
		panel.hidden = true;
		return;
	}

	const start = panel.scrollHeight;
	panel.style.height = start + 'px';
	// Force reflow.
	// eslint-disable-next-line no-unused-expressions
	panel.offsetHeight;
	panel.style.height = '0px';

	const onEnd = ( event ) => {
		if ( event.propertyName !== 'height' ) return;
		panel.hidden = true;
		panel.removeEventListener( 'transitionend', onEnd );
	};
	panel.addEventListener( 'transitionend', onEnd );
}

function isOpen( item ) {
	const trigger = item.querySelector( '.centre-aide__trigger' );
	return trigger?.getAttribute( 'aria-expanded' ) === 'true';
}

function bindAccordions( root ) {
	const triggers = root.querySelectorAll( '.centre-aide__trigger' );
	triggers.forEach( ( trigger ) => {
		const item = trigger.closest( '.centre-aide__item' );
		if ( ! item ) return;

		// Synchronise l'état initial : une offre « mise en avant » est rendue
		// ouverte côté serveur (data-open) ; sinon elle est fermée.
		const panel = item.querySelector( '.centre-aide__answer' );
		if ( panel && panel.dataset.open === 'true' ) {
			trigger.setAttribute( 'aria-expanded', 'true' );
			panel.hidden = false;
			panel.style.height = 'auto';
		}

		trigger.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			if ( isOpen( item ) ) {
				closePanel( item );
				return;
			}
			// L'exclusivité (repli des autres panels du groupe) est portée par
			// le bus d'événement : openPanel émet ACCORDION_EVENT, capté ci-
			// dessous (bindGroupExclusivity) et par gift.js pour le 3e panel.
			openPanel( item );
		} );
	} );
}

/**
 * Replie les autres offres du groupe quand l'une d'elles — ou le module
 * « Offrir » (gift.js) — s'ouvre. La FAQ (sans data-accordion-group) n'est pas
 * concernée. Symétrique du listener de gift.js : chaque module ne ferme que ses
 * propres panels, ce qui évite tout couplage.
 */
function bindGroupExclusivity() {
	document.addEventListener( ACCORDION_EVENT, ( event ) => {
		const detail = event.detail || {};
		if ( detail.group !== 'subscribe' ) return;
		document
			.querySelectorAll( '.subscribe-offer[data-accordion-group="subscribe"]' )
			.forEach( ( other ) => {
				if ( other !== detail.item && isOpen( other ) ) {
					closePanel( other );
				}
			} );
	} );
}

/* ------------------------------------------------------------------ *
 * Carousel de bénéfices
 * ------------------------------------------------------------------ */

function initCarousel( root ) {
	const carousel = root.querySelector( '[data-subscribe-carousel]' );
	if ( ! carousel ) return;

	const track = carousel.querySelector( '.subscribe-carousel__track' );
	const slides = Array.from( carousel.querySelectorAll( '.subscribe-carousel__slide' ) );
	if ( ! track || slides.length < 2 ) return;

	// Contrôles desktop (flèches + compteur, dans l'overlay) et nav-dots mobile.
	const controls = carousel.querySelector( '[data-subscribe-carousel-controls]' );
	const prevBtn = carousel.querySelector( '[data-subscribe-prev]' );
	const nextBtn = carousel.querySelector( '[data-subscribe-next]' );
	const currentEl = carousel.querySelector( '[data-subscribe-current]' );
	const dots = Array.from( carousel.querySelectorAll( '[data-subscribe-dot]' ) );

	// Révèle les contrôles maintenant que le JS est actif.
	if ( controls ) controls.hidden = false;

	let index = 0;

	const clamp = ( n ) => Math.max( 0, Math.min( n, slides.length - 1 ) );

	const updateUi = () => {
		if ( currentEl ) currentEl.textContent = String( index + 1 );
		if ( prevBtn ) prevBtn.disabled = index <= 0;
		if ( nextBtn ) nextBtn.disabled = index >= slides.length - 1;

		// Synchronise la pagination à points (mobile) sur le slide courant.
		dots.forEach( ( dot, i ) => {
			const active = i === index;
			dot.classList.toggle( 'subscribe-carousel__dot--active', active );
			if ( active ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
	};

	const goTo = ( n ) => {
		index = clamp( n );
		const slide = slides[ index ];
		if ( slide ) {
			track.scrollTo( {
				left: slide.offsetLeft - track.offsetLeft,
				behavior: prefersReducedMotion() ? 'auto' : 'smooth',
			} );
		}
		updateUi();
	};

	prevBtn?.addEventListener( 'click', () => goTo( index - 1 ) );
	nextBtn?.addEventListener( 'click', () => goTo( index + 1 ) );

	// Tap sur un point → défile au slide correspondant (le swipe natif reste géré
	// par le scroll-snap de la piste, synchronisé via l'IntersectionObserver).
	dots.forEach( ( dot, i ) => {
		dot.addEventListener( 'click', () => goTo( i ) );
	} );

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
					if ( ! entry.isIntersecting ) return;
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
}

/* ------------------------------------------------------------------ */

export function init() {
	const root = document.querySelector( ROOT_SELECTOR );
	if ( ! root ) return;

	bindGroupExclusivity();
	bindAccordions( root );
	initCarousel( root );
}

export default init;
