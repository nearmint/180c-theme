/**
 * Menu compte (mobile) — ouverture en bottom-sheet.
 *
 * En < 1024px (le bouton `.js-my-account-toggle` n'est visible qu'à ces
 * résolutions ; masqué au-delà), un tap ouvre le panneau
 * `.my-account__mobile-panels` (nav + aide + déconnexion) en **feuille basse**,
 * via la primitive partagée `openBottomSheet` — le même module que les filtres
 * de la page « Mon carnet ». En ≥1024px le panneau reste affiché en flux dans
 * la sidebar (aucune feuille).
 *
 * Markup attendu (parts/account/sidebar.php) :
 *   button.my-account__mobile-trigger.js-my-account-toggle[aria-expanded][aria-controls]
 *   div.my-account__mobile-panels#…
 *
 * @module account-menu-toggle
 */

import { openBottomSheet } from './bottom-sheet.js';

const DESKTOP_MQ = '(min-width: 1024px)';

/**
 * Initialise tous les boutons de menu compte présents.
 *
 * Chargé dynamiquement depuis main.js (import conditionnel sur
 * `.js-my-account-toggle`) : ce module — et sa dépendance partagée
 * `bottom-sheet.js` — reste hors du graphe statique de l'entrée, évitant que
 * Vite ne fasse ré-importer l'entrée par les chunks dynamiques.
 *
 * @return {void}
 */
export function init() {
	document.querySelectorAll( '.js-my-account-toggle' ).forEach( ( trigger ) => initOne( trigger ) );
}

/**
 * Initialise un bouton de menu compte.
 *
 * @param {HTMLElement} trigger
 * @return {void}
 */
function initOne( trigger ) {
	const panelId = trigger.getAttribute( 'aria-controls' );
	const panel = panelId
		? document.getElementById( panelId )
		: trigger.closest( '.my-account__sidebar' )?.querySelector( '.my-account__mobile-panels' );
	if ( ! panel ) {
		return;
	}

	const labelEl = trigger.querySelector( '.my-account__mobile-trigger-label' );
	const title = labelEl ? labelEl.textContent.trim() : 'Menu compte';
	const desktop = window.matchMedia( DESKTOP_MQ );
	let sheet = null;

	trigger.addEventListener( 'click', () => {
		// Sécurité : le bouton est masqué en ≥1024px, mais on ne fait rien si
		// jamais il devenait cliquable (le panneau y est déjà affiché en flux).
		if ( desktop.matches ) {
			return;
		}
		if ( sheet ) {
			sheet.close();
			return;
		}
		trigger.setAttribute( 'aria-expanded', 'true' );
		sheet = openBottomSheet( panel, {
			title,
			onClose: () => {
				trigger.setAttribute( 'aria-expanded', 'false' );
				sheet = null;
			},
		} );
	} );

	// Repasse en desktop pendant que la feuille est ouverte : on la referme.
	desktop.addEventListener( 'change', ( event ) => {
		if ( event.matches && sheet ) {
			sheet.close();
		}
	} );
}
