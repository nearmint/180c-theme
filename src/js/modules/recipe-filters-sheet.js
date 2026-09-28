/**
 * Dropdowns de filtres recettes — popover desktop + bottom-sheet mobile.
 *
 * Comportement partagé par les deux surfaces de filtres qui réutilisent le même
 * markup `.recipe-filters__dropdown*` :
 *   - archive « Toutes les recettes » (recipes-archive.js, `data-recipes-*`) ;
 *   - « Mon carnet » (mon-carnet-filters.js, `data-carnet-*`).
 *
 * Desktop (≥768px) : popover absolu classique sous le bouton (état via
 * `aria-expanded` + `[hidden]`), fermé au clic extérieur / Échap.
 *
 * Mobile (<768px) : feuille basse (bottom sheet) via la primitive partagée
 * `openBottomSheet` (bottom-sheet.js) — même module que le menu compte. Ce
 * helper n'ajoute que le pied spécifique aux filtres (« Effacer » + « Voir les
 * résultats ») et le libellé de comptage.
 *
 * Les cases à cocher et leurs écouteurs `change` restent gérés par les modules
 * appelants : « Effacer » se contente de décocher les cases du groupe et de
 * redéclencher un `change` (les modules réagissent et rejouent le filtrage).
 *
 * @module recipe-filters-sheet
 */

import { openBottomSheet } from './bottom-sheet.js';

const MOBILE_MQ = '(max-width: 767px)';

/**
 * Câble les dropdowns de filtres d'une barre (popover desktop / sheet mobile).
 *
 * @param {HTMLElement} root                Racine du <form> de filtres.
 * @param {Object}      [options]           Options.
 * @param {Function}    [options.getCount]  () => number|null — nombre de
 *                                          résultats courant, pour libeller le
 *                                          bouton « Voir les résultats ».
 * @return {{ closeAll: Function, refresh: Function }} Contrôleur.
 */
export function initFilterDropdowns( root, { getCount } = {} ) {
	const dropdowns = Array.from( root.querySelectorAll( '.recipe-filters__dropdown' ) );
	if ( ! dropdowns.length ) {
		return { closeAll() {}, refresh() {} };
	}

	const mq = window.matchMedia( MOBILE_MQ );
	let sheet = null; // Contrôleur openBottomSheet actif, sinon null.
	let applyBtn = null; // Bouton « Voir les résultats » de la feuille active.

	const isMobile = () => mq.matches;

	// --- Popover desktop ---------------------------------------------------

	function setPopover( dropdown, open ) {
		const toggle = dropdown.querySelector( '.recipe-filters__dropdown-toggle' );
		const panel = dropdown.querySelector( '.recipe-filters__dropdown-panel' );
		if ( ! toggle || ! panel ) {
			return;
		}
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		panel.hidden = ! open;
	}

	function closePopovers( except ) {
		dropdowns.forEach( ( dropdown ) => {
			if ( dropdown !== except ) {
				setPopover( dropdown, false );
			}
		} );
	}

	// --- Bottom sheet mobile ----------------------------------------------

	/**
	 * Met à jour le libellé « Voir les résultats (N) » de la feuille ouverte.
	 *
	 * @return {void}
	 */
	function applyLabel() {
		if ( ! applyBtn ) {
			return;
		}
		const count = typeof getCount === 'function' ? getCount() : null;
		applyBtn.textContent =
			typeof count === 'number'
				? `Voir les résultats (${ count })`
				: 'Voir les résultats';
	}

	function openSheet( dropdown ) {
		closeAll();

		const toggle = dropdown.querySelector( '.recipe-filters__dropdown-toggle' );
		const panel = dropdown.querySelector( '.recipe-filters__dropdown-panel' );
		if ( ! toggle || ! panel ) {
			return;
		}
		const labelEl = dropdown.querySelector( '.recipe-filters__dropdown-label' );
		const labelText = labelEl ? labelEl.textContent.trim() : '';

		// Pied spécifique filtres : « Effacer » (groupe) + « Voir les résultats ».
		const foot = document.createElement( 'div' );
		foot.className = 'bottom-sheet__foot';
		const clearBtn = document.createElement( 'button' );
		clearBtn.type = 'button';
		clearBtn.className = 'recipe-filters__sheet-clear';
		clearBtn.textContent = 'Effacer';
		applyBtn = document.createElement( 'button' );
		applyBtn.type = 'button';
		applyBtn.className = 'recipe-filters__sheet-apply';
		foot.append( clearBtn, applyBtn );

		toggle.setAttribute( 'aria-expanded', 'true' );
		sheet = openBottomSheet( panel, {
			title: labelText,
			footer: foot,
			onClose: () => {
				toggle.setAttribute( 'aria-expanded', 'false' );
				panel.removeEventListener( 'change', applyLabel );
				sheet = null;
				applyBtn = null;
			},
		} );
		applyLabel();

		// Décocher toutes les cases du groupe et rejouer le filtrage.
		clearBtn.addEventListener( 'click', () => {
			panel
				.querySelectorAll( 'input[type="checkbox"]' )
				.forEach( ( cb ) => {
					if ( cb.checked ) {
						cb.checked = false;
						cb.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
				} );
			applyLabel();
		} );
		// Le filtrage se met à jour en direct : rafraîchit le libellé au fil des cases.
		panel.addEventListener( 'change', applyLabel );
		applyBtn.addEventListener( 'click', () => sheet && sheet.close() );
	}

	function closeSheet() {
		if ( sheet ) {
			sheet.close();
		}
	}

	// --- API + câblage -----------------------------------------------------

	function closeAll() {
		closePopovers( null );
		closeSheet();
	}

	dropdowns.forEach( ( dropdown ) => {
		const toggle = dropdown.querySelector( '.recipe-filters__dropdown-toggle' );
		if ( ! toggle ) {
			return;
		}
		toggle.addEventListener( 'click', () => {
			const expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
			if ( isMobile() ) {
				if ( expanded ) {
					closeSheet();
				} else {
					openSheet( dropdown );
				}
			} else if ( expanded ) {
				setPopover( dropdown, false );
			} else {
				closePopovers( dropdown );
				setPopover( dropdown, true );
			}
		} );
	} );

	// Clic extérieur (popover desktop uniquement ; la feuille a son backdrop).
	document.addEventListener( 'click', ( event ) => {
		if ( sheet ) {
			return;
		}
		if ( ! event.target.closest( '.recipe-filters__dropdown' ) ) {
			closePopovers( null );
		}
	} );

	// Échap ferme le popover (la feuille gère son propre Échap).
	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' && ! sheet ) {
			closePopovers( null );
		}
	} );

	// Changement de palier (rotation / resize) : évite un état bloqué.
	mq.addEventListener( 'change', () => closeAll() );

	return { closeAll, refresh: applyLabel };
}
