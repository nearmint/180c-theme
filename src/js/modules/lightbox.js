/**
 * Lightbox partagée — overlay image plein écran (polish v3).
 *
 * Module vanilla ES, extrait du composant historique de la fiche recette
 * (`src/js/modules/recipe.js::initLightbox`) pour être consommé à la fois
 * par la recette (hero zoom) et par l'article (images du corps).
 *
 * API :
 *   import { initLightbox } from './modules/lightbox.js';
 *
 *   // Recette : déclencheurs explicites (boutons existants).
 *   initLightbox({ triggerSelector: '[data-lightbox-trigger]' });
 *
 *   // Article : auto-enhance des <figure> du corps.
 *   initLightbox({
 *     enhanceSelector: '.article__body figure.wp-block-image',
 *     triggerLabel:    'Agrandir l’image',
 *     iconSvg:         '<svg ...>',  // optionnel — l'icône `expand` du set
 *   });
 *
 * L'overlay et ses listeners globaux (Escape, focus trap, scroll lock) sont
 * partagés entre tous les appels — `initLightbox` est idempotent : un seul
 * overlay dans le `<body>`, les triggers / enhancements s'ajoutent.
 */

/* ============================================================
 * État partagé (singleton)
 * ============================================================ */
const lightbox = {
	/** @type {HTMLElement|null} */ overlay: null,
	/** @type {HTMLImageElement|null} */ image: null,
	/** @type {HTMLElement|null}   */ caption: null,
	/** @type {HTMLButtonElement|null} */ closeBtn: null,
	/** @type {HTMLButtonElement|null} */ prevBtn: null,
	/** @type {HTMLButtonElement|null} */ nextBtn: null,
	/** @type {HTMLElement|null} */ lastFocus: null,
};

/**
 * État du jeu courant (défilement) :
 *  - `items` : liste de { src, caption } affichables.
 *  - `current` : index actif.
 * Un appel single-image pose simplement un jeu d'un seul élément (nav masquée).
 */
let items = [];
let current = 0;

let globalListenersBound = false;
const enhancedFigures = new WeakSet();

const CLOSE_ICON_SVG =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

const PREV_ICON_SVG =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>';

const NEXT_ICON_SVG =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';

const EXPAND_ICON_SVG =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="m21 3-7 7"/><path d="m3 21 7-7"/></svg>';

/* ============================================================
 * DOM lazy build (overlay créé au 1er usage)
 * ============================================================ */

/**
 * Construit l'overlay lightbox une seule fois et le retourne.
 *
 * @returns {HTMLElement}
 */
function ensureLightbox() {
	if ( lightbox.overlay ) {
		return lightbox.overlay;
	}

	const overlay = document.createElement( 'div' );
	overlay.className = 'lightbox';
	overlay.setAttribute( 'role', 'dialog' );
	overlay.setAttribute( 'aria-modal', 'true' );
	overlay.setAttribute( 'aria-label', 'Image agrandie' );
	overlay.hidden = true;

	const closeBtn = document.createElement( 'button' );
	closeBtn.type = 'button';
	closeBtn.className = 'lightbox__close overlay-icon-btn';
	closeBtn.setAttribute( 'aria-label', 'Fermer' );
	closeBtn.innerHTML = `<span class="lightbox__close-icon">${ CLOSE_ICON_SVG }</span>`;

	// Flèches de défilement (masquées en mode image unique). Composent
	// .overlay-icon-btn (look rond partagé).
	const prevBtn = document.createElement( 'button' );
	prevBtn.type = 'button';
	prevBtn.className = 'lightbox__nav lightbox__nav--prev overlay-icon-btn';
	prevBtn.setAttribute( 'aria-label', 'Image précédente' );
	prevBtn.innerHTML = PREV_ICON_SVG;
	prevBtn.hidden = true;

	const nextBtn = document.createElement( 'button' );
	nextBtn.type = 'button';
	nextBtn.className = 'lightbox__nav lightbox__nav--next overlay-icon-btn';
	nextBtn.setAttribute( 'aria-label', 'Image suivante' );
	nextBtn.innerHTML = NEXT_ICON_SVG;
	nextBtn.hidden = true;

	const image = document.createElement( 'img' );
	image.className = 'lightbox__image';
	image.alt = '';

	const caption = document.createElement( 'figcaption' );
	caption.className = 'lightbox__caption media-caption';
	caption.hidden = true;

	overlay.append( closeBtn, prevBtn, image, nextBtn, caption );
	document.body.appendChild( overlay );

	// Fermeture : clic fond ou croix (jamais sur les flèches ou l'image).
	overlay.addEventListener( 'click', ( event ) => {
		if ( event.target === overlay || event.target.closest( '.lightbox__close' ) ) {
			closeLightbox();
		}
	} );

	prevBtn.addEventListener( 'click', () => go( -1 ) );
	nextBtn.addEventListener( 'click', () => go( 1 ) );

	lightbox.overlay = overlay;
	lightbox.image = image;
	lightbox.caption = caption;
	lightbox.closeBtn = closeBtn;
	lightbox.prevBtn = prevBtn;
	lightbox.nextBtn = nextBtn;

	bindGlobalListeners();

	return overlay;
}

/**
 * Affiche l'élément courant du jeu (`items[current]`) et synchronise la nav.
 */
function renderItem() {
	const item = items[ current ];
	if ( ! item ) {
		return;
	}
	lightbox.image.src = item.src;

	if ( item.caption ) {
		lightbox.caption.textContent = item.caption;
		lightbox.caption.hidden = false;
	} else {
		lightbox.caption.textContent = '';
		lightbox.caption.hidden = true;
	}

	const multi = items.length > 1;
	lightbox.prevBtn.hidden = ! multi;
	lightbox.nextBtn.hidden = ! multi;
	if ( multi ) {
		lightbox.prevBtn.disabled = current <= 0;
		lightbox.nextBtn.disabled = current >= items.length - 1;
	}
}

/**
 * Défile dans le jeu courant.
 *
 * @param {number} delta -1 (précédent) ou +1 (suivant).
 */
function go( delta ) {
	if ( items.length < 2 ) {
		return;
	}
	current = Math.max( 0, Math.min( current + delta, items.length - 1 ) );
	renderItem();
	// Garde le focus sur une cible visible après désactivation d'une flèche.
	if ( lightbox.nextBtn.disabled && document.activeElement === lightbox.nextBtn ) {
		lightbox.prevBtn.focus();
	} else if ( lightbox.prevBtn.disabled && document.activeElement === lightbox.prevBtn ) {
		lightbox.nextBtn.focus();
	}
}

/**
 * Ouvre l'overlay (commun single + jeu) : scroll lock + focus.
 */
function showOverlay() {
	lightbox.overlay.hidden = false;
	// Scroll lock — classe canonique définie dans lightbox.css.
	document.documentElement.classList.add( 'lightbox-lock' );
	lightbox.closeBtn.focus();
}

/**
 * Listeners globaux (Escape, focus trap) — attachés une seule fois.
 */
function bindGlobalListeners() {
	if ( globalListenersBound ) {
		return;
	}
	globalListenersBound = true;

	document.addEventListener( 'keydown', ( event ) => {
		if ( ! lightbox.overlay || lightbox.overlay.hidden ) {
			return;
		}
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			closeLightbox();
		} else if ( event.key === 'ArrowLeft' ) {
			event.preventDefault();
			go( -1 );
		} else if ( event.key === 'ArrowRight' ) {
			event.preventDefault();
			go( 1 );
		} else if ( event.key === 'Tab' ) {
			// Focus-trap : cycle entre les focusables visibles (croix + flèches
			// actives en mode jeu).
			event.preventDefault();
			const focusables = [ lightbox.closeBtn, lightbox.prevBtn, lightbox.nextBtn ].filter(
				( el ) => el && ! el.hidden && ! el.disabled
			);
			if ( focusables.length === 0 ) {
				return;
			}
			const idx = focusables.indexOf( document.activeElement );
			let nextIdx;
			if ( event.shiftKey ) {
				nextIdx = idx <= 0 ? focusables.length - 1 : idx - 1;
			} else {
				nextIdx = idx === focusables.length - 1 ? 0 : idx + 1;
			}
			focusables[ nextIdx ].focus();
		}
	} );
}

/* ============================================================
 * API publique d'ouverture / fermeture
 * ============================================================ */

/**
 * Ouvre la lightbox sur une image donnée.
 *
 * @param {string} src         URL plein format de l'image.
 * @param {string} captionText Légende (texte). Vide = légende masquée.
 * @param {HTMLElement} trigger Élément ayant déclenché l'ouverture
 *                              (utilisé pour le retour focus à la fermeture).
 */
export function openLightbox( src, captionText, trigger ) {
	if ( ! src ) {
		return;
	}
	ensureLightbox();

	lightbox.lastFocus = trigger || document.activeElement;
	items = [ { src, caption: captionText || '' } ];
	current = 0;
	renderItem();
	showOverlay();
}

/**
 * Ouvre la lightbox sur un JEU de figures avec défilement (flèches + clavier).
 *
 * Réutilise l'overlay partagé : un seul élément → nav masquée (équivaut à
 * `openLightbox`). Sert la galerie d'article (cf. enhanceFigureSet).
 *
 * @param {HTMLElement[]} figures Figures sources (chacune contient une <img>).
 * @param {number}        index   Index de départ.
 * @param {HTMLElement}   trigger Élément déclencheur (retour focus à la fermeture).
 */
export function openLightboxSet( figures, index, trigger ) {
	ensureLightbox();

	items = Array.from( figures )
		.map( ( figure ) => {
			const img = figure.querySelector( 'img' );
			return {
				src: img ? pickFullSource( img ) : '',
				caption: readFigureCaption( figure ),
			};
		} )
		.filter( ( item ) => '' !== item.src );

	if ( items.length === 0 ) {
		items = [];
		return;
	}

	lightbox.lastFocus = trigger || document.activeElement;
	current = Math.max( 0, Math.min( index, items.length - 1 ) );
	renderItem();
	showOverlay();
}

/**
 * Ferme la lightbox et restaure focus + scroll.
 */
export function closeLightbox() {
	if ( ! lightbox.overlay || lightbox.overlay.hidden ) {
		return;
	}
	lightbox.overlay.hidden = true;
	lightbox.image.removeAttribute( 'src' );
	document.documentElement.classList.remove( 'lightbox-lock' );
	items = [];
	current = 0;

	if ( lightbox.lastFocus && typeof lightbox.lastFocus.focus === 'function' ) {
		lightbox.lastFocus.focus();
	}
	lightbox.lastFocus = null;
}

/* ============================================================
 * Sélection plein format
 * ============================================================ */

/**
 * Retourne l'URL pleine résolution candidate pour une image.
 *
 * Stratégie (du plus fiable au plus simple) :
 *  1. Lien parent `<a href>` → souvent « Lien vers fichier média » de
 *     Gutenberg, qui pointe sur l'attachment full-size.
 *  2. Largest `srcset` candidate (`w` descriptor max).
 *  3. `currentSrc` (image actuellement servie par le navigateur).
 *  4. `src` fallback final.
 *
 * @param {HTMLImageElement} img
 * @returns {string}
 */
function pickFullSource( img ) {
	const figure = img.closest( 'figure' );
	if ( figure ) {
		const link = figure.querySelector( 'a[href]' );
		if ( link && /\.(jpe?g|png|webp|avif|gif)(\?|$)/i.test( link.href ) ) {
			return link.href;
		}
	}

	const srcset = img.getAttribute( 'srcset' );
	if ( srcset ) {
		const candidates = srcset.split( ',' ).map( ( raw ) => {
			const parts = raw.trim().split( /\s+/ );
			const url = parts[ 0 ];
			const descriptor = parts[ 1 ] || '';
			const widthMatch = descriptor.match( /^(\d+)w$/ );
			return {
				url,
				width: widthMatch ? parseInt( widthMatch[ 1 ], 10 ) : 0,
			};
		} );
		candidates.sort( ( a, b ) => b.width - a.width );
		if ( candidates[ 0 ] && candidates[ 0 ].url ) {
			return candidates[ 0 ].url;
		}
	}

	return img.currentSrc || img.src || '';
}

/**
 * Texte de légende d'une figure pour la lightbox, en EXCLUANT le bouton
 * « Voir plus » injecté par caption-readmore.js : ce module enveloppe le texte
 * dans `.caption-readmore__text` et y ajoute un `<button>` frère ; lire
 * `figcaption.textContent` collerait son libellé (« Voir plus »/« Voir moins »)
 * à la légende plein écran. On lit donc le span de texte s'il existe.
 *
 * @param {HTMLElement} figure
 * @returns {string}
 */
function readFigureCaption( figure ) {
	const textEl = figure.querySelector( '.caption-readmore__text' );
	if ( textEl ) {
		return textEl.textContent.trim();
	}
	const captionEl = figure.querySelector( 'figcaption' );
	return captionEl ? captionEl.textContent.trim() : '';
}

/* ============================================================
 * Trigger-based init (recette hero)
 * ============================================================ */

/**
 * Attache la lightbox aux déclencheurs explicites (boutons portant
 * `data-lightbox-trigger`). Chaque trigger doit être à l'intérieur d'un
 * élément portant `data-lightbox-src` (la figure source).
 *
 * @param {string} selector
 */
function bindTriggers( selector ) {
	const triggers = document.querySelectorAll( selector );
	triggers.forEach( ( trigger ) => {
		if ( trigger.dataset.lightboxBound === '1' ) {
			return;
		}
		trigger.dataset.lightboxBound = '1';

		trigger.addEventListener( 'click', () => {
			const figure = trigger.closest( '[data-lightbox-src]' );
			if ( ! figure ) {
				return;
			}
			const src = figure.dataset.lightboxSrc || '';
			const caption = figure.dataset.caption || '';
			openLightbox( src, caption, trigger );
		} );
	} );
}

/* ============================================================
 * Enhancement (article corps)
 * ============================================================ */

/**
 * Pour chaque <figure> ciblée, injecte un bouton zoom **ancré sur
 * l'image** (pas sur la figure entière) et attache l'ouverture
 * lightbox au clic. Garde-fou : ignore les images vraiment petites
 * (< minWidth).
 *
 * Ancrage : on enveloppe l'image (ou son anchor parent si elle est
 * dans un `<a>`) dans un cadre `.media-zoom-frame { position: relative }`
 * et on y place le bouton. Le `<figcaption>` reste hors du cadre,
 * donc le bouton ne recouvre jamais la légende — bug v5 où le bouton,
 * positionné en bas-droite de la `<figure>`, se retrouvait par-dessus
 * `figcaption.wp-caption-text`.
 *
 * @param {string} selector  Sélecteur CSS des figures à enhancer.
 * @param {string} ariaLabel Libellé du bouton.
 * @param {string} iconSvg   Markup SVG de l'icône.
 * @param {number} minWidth  Seuil de largeur naturelle (px).
 */
function injectZoomButton( figure, ariaLabel, iconSvg, minWidth, onActivate ) {
	if ( enhancedFigures.has( figure ) ) {
		return;
	}
	const img = figure.querySelector( 'img' );
	if ( ! img ) {
		return;
	}
	// Filtre : images trop petites = icônes décoratives, pas zoomables.
	if ( img.naturalWidth && img.naturalWidth < minWidth ) {
		return;
	}
	// Idempotence : si un cadre existe déjà (re-init / HMR), on ne re-wrappe
	// pas et on saute l'injection du bouton.
	if ( figure.querySelector( '.media-zoom-frame' ) ) {
		enhancedFigures.add( figure );
		return;
	}
	enhancedFigures.add( figure );

	// Cible d'enveloppement :
	//  - si l'image est dans un <a> (lightbox-link classique ou bloc), on
	//    enveloppe l'anchor pour préserver son intéractivité ;
	//  - sinon on enveloppe l'image directement.
	const parent = img.parentElement;
	const wrapTarget = parent && parent.tagName === 'A' ? parent : img;

	const frame = document.createElement( 'span' );
	frame.className = 'media-zoom-frame';

	wrapTarget.parentNode.insertBefore( frame, wrapTarget );
	frame.appendChild( wrapTarget );

	// Marqueur sémantique sur la figure.
	figure.classList.add( 'is-zoomable' );

	const trigger = document.createElement( 'button' );
	trigger.type = 'button';
	trigger.className = 'media-zoom-trigger overlay-icon-btn';
	trigger.setAttribute( 'aria-label', ariaLabel );
	trigger.setAttribute( 'aria-haspopup', 'dialog' );
	trigger.innerHTML = iconSvg;

	trigger.addEventListener( 'click', () => onActivate( trigger ) );

	frame.appendChild( trigger );
}

/**
 * Mode IMAGE UNIQUE : chaque figure ouvre sa propre image (pas de défilement).
 *
 * @param {string} selector  Sélecteur CSS des figures à enhancer.
 * @param {string} ariaLabel Libellé du bouton.
 * @param {string} iconSvg   Markup SVG de l'icône.
 * @param {number} minWidth  Seuil de largeur naturelle (px).
 */
function enhanceFigures( selector, ariaLabel, iconSvg, minWidth ) {
	document.querySelectorAll( selector ).forEach( ( figure ) => {
		injectZoomButton( figure, ariaLabel, iconSvg, minWidth, ( trigger ) => {
			const img = figure.querySelector( 'img' );
			openLightbox(
				img ? pickFullSource( img ) : '',
				readFigureCaption( figure ),
				trigger
			);
		} );
	} );
}

/**
 * Mode JEU (galerie) : un bouton zoom par figure ; le clic ouvre la lightbox
 * sur l'ensemble du jeu, positionnée sur la figure cliquée, avec défilement
 * (flèches + clavier). C'est le composant « afficher en grand » navigable de
 * la galerie d'article (même overlay partagé que la recette/article).
 *
 * @param {HTMLElement[]} figures Figures du jeu (ordre = ordre de défilement).
 * @param {Object}        [options]
 * @param {string}        [options.ariaLabel] Libellé du bouton.
 * @param {string}        [options.iconSvg]   Icône (défaut = expand).
 * @param {number}        [options.minWidth]  Largeur naturelle min (px).
 */
export function enhanceFigureSet( figures, options = {} ) {
	const {
		ariaLabel = "Afficher l'image en grand",
		iconSvg = EXPAND_ICON_SVG,
		minWidth = 0,
	} = options;
	const list = Array.from( figures );
	list.forEach( ( figure ) => {
		injectZoomButton( figure, ariaLabel, iconSvg, minWidth, ( trigger ) => {
			openLightboxSet( list, list.indexOf( figure ), trigger );
		} );
	} );
}

/* ============================================================
 * Entry point
 * ============================================================ */

/**
 * Initialise la lightbox sur la page courante.
 *
 * @param {Object} [options]
 * @param {string} [options.triggerSelector] Sélecteur des déclencheurs
 *                                            explicites (boutons hero).
 * @param {string} [options.enhanceSelector] Sélecteur des <figure> à
 *                                            enhancer (zoom auto-ajouté).
 * @param {string} [options.triggerLabel]    Libellé aria du bouton
 *                                            d'enhancement. Défaut FR.
 * @param {string} [options.iconSvg]         Markup SVG de l'icône (zoom
 *                                            d'enhancement). Défaut = expand.
 * @param {number} [options.minWidth]        Largeur naturelle min pour
 *                                            enhancer une image (px). 200.
 */
export function initLightbox( options = {} ) {
	const {
		triggerSelector = null,
		enhanceSelector = null,
		triggerLabel = "Agrandir l'image",
		iconSvg = EXPAND_ICON_SVG,
		minWidth = 200,
	} = options;

	if ( triggerSelector ) {
		bindTriggers( triggerSelector );
	}

	if ( enhanceSelector ) {
		// Si des images du corps sont lazy ou pas encore décodées,
		// `naturalWidth` peut être 0 au boot. On retente sur 'load' au
		// niveau document pour rattraper les figures décodées en différé.
		enhanceFigures( enhanceSelector, triggerLabel, iconSvg, minWidth );

		window.addEventListener(
			'load',
			() => {
				enhanceFigures( enhanceSelector, triggerLabel, iconSvg, minWidth );
			},
			{ once: true }
		);
	}
}
