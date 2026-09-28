/**
 * Mon carnet — filtres taxonomie + recherche dynamique (côté client).
 *
 * Toutes les recettes du carnet sont rendues d'un bloc par
 * `_180c_render_mon_carnet()` (inc/favorites.php), chaque carte portant
 * `data-recipe-types`, `data-recipe-seasons` et `data-search`. Ce module filtre
 * la grille instantanément, sans appel réseau.
 *
 * La barre de filtres réutilise le MÊME module visuel que l'archive
 * « Toutes les recettes » (`.recipe-filters--explore`, archive-recipe.php) :
 * champ de recherche + dropdowns multi-select (cases à cocher). Contrats DOM
 * (préfixe `data-carnet-*` pour distinguer du module REST `recipes-archive`) :
 *
 *   [data-carnet-filters]          racine <form>
 *   [data-carnet-search]           <input type=search>
 *   [data-carnet-dropdown]         conteneur d'un dropdown (data-filter-group)
 *   [data-carnet-dropdown-toggle]  <button> d'ouverture (aria-expanded)
 *   [data-carnet-dropdown-panel]   panneau d'options
 *   [data-carnet-dropdown-count]   badge « n sélectionné(s) »
 *   [data-carnet-filter]           <input checkbox> (data-filter-group/-value)
 *   [data-carnet-reset]            <button> remise à zéro
 *   [data-carnet-item]             <li> de carte (data-recipe-types/-seasons/-search)
 *   [data-carnet-count]            compteur live
 *   [data-carnet-noresults]        état vide
 *
 * Filtrage : OR à l'intérieur d'un groupe, AND entre les groupes ; recherche
 * sous-chaîne normalisée (minuscule, sans accents), debouncée.
 *
 * Chargé conditionnellement depuis main.js via `[data-carnet-filters]`.
 *
 * @module mon-carnet-filters
 */

import { initFilterDropdowns } from './recipe-filters-sheet.js';

const SEARCH_DEBOUNCE_MS = 120;

/**
 * Normalise une chaîne pour comparaison : sans accents, minuscule, trim.
 * Miroir JS de `_180c_normalize_search()` (PHP).
 *
 * @param {string} value Chaîne brute.
 * @return {string} Chaîne normalisée.
 */
function normalize( value ) {
	return ( value || '' )
		.normalize( 'NFD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.toLowerCase()
		.trim();
}

/**
 * Initialise le filtrage/recherche du carnet.
 *
 * @return {void}
 */
export function init() {
	const root = document.querySelector( '[data-carnet-filters]' );
	if ( ! root ) {
		return;
	}

	const items = Array.from( document.querySelectorAll( '[data-carnet-item]' ) );
	const searchInput = root.querySelector( '[data-carnet-search]' );
	const checkboxes = Array.from( root.querySelectorAll( '[data-carnet-filter]' ) );
	const dropdowns = Array.from( root.querySelectorAll( '[data-carnet-dropdown]' ) );
	const resetButtons = Array.from( document.querySelectorAll( '[data-carnet-reset]' ) );
	const navReset = root.querySelector( '[data-carnet-reset]' );
	const countEl = document.querySelector( '[data-carnet-count]' );
	const noResults = document.querySelector( '[data-carnet-noresults]' );

	if ( ! items.length ) {
		return;
	}

	const total = items.length;
	const active = { type: new Set(), season: new Set() };
	let query = '';
	let lastVisible = total;
	// Contrôleur des dropdowns (popover desktop / bottom sheet mobile) — assigné
	// plus bas ; référencé par apply()/reset() qui s'exécutent après l'init.
	let filterUI = null;

	/**
	 * Indique si un filtre ou une recherche est actif.
	 *
	 * @return {boolean} Vrai si au moins un critère est posé.
	 */
	function isFiltered() {
		return query !== '' || active.type.size > 0 || active.season.size > 0;
	}

	/**
	 * Met à jour le compteur de résultats.
	 *
	 * @param {number} visible Nombre de cartes visibles.
	 * @return {void}
	 */
	function updateCount( visible ) {
		if ( ! countEl ) {
			return;
		}
		const plural = ( n ) => ( n > 1 ? 's' : '' );
		countEl.textContent = isFiltered()
			? `${ visible } résultat${ plural( visible ) } sur ${ total }`
			: `${ total } recette${ plural( total ) }`;
	}

	/**
	 * Met à jour le badge de comptage d'un dropdown + son état actif.
	 *
	 * @param {string} group Groupe de filtre (type|season).
	 * @return {void}
	 */
	function updateDropdownBadge( group ) {
		const dropdown = dropdowns.find( ( d ) => d.dataset.filterGroup === group );
		if ( ! dropdown ) {
			return;
		}
		const size = active[ group ] ? active[ group ].size : 0;
		const toggle = dropdown.querySelector( '[data-carnet-dropdown-toggle]' );
		const badge = dropdown.querySelector( '[data-carnet-dropdown-count]' );
		if ( badge ) {
			badge.textContent = size > 0 ? String( size ) : '';
			badge.hidden = size === 0;
		}
		if ( toggle ) {
			toggle.classList.toggle( 'recipe-filters__dropdown-toggle--active', size > 0 );
		}
	}

	/**
	 * Applique l'état courant (filtres + recherche) à la grille.
	 *
	 * @return {void}
	 */
	function apply() {
		let visible = 0;

		items.forEach( ( item ) => {
			const types = ( item.dataset.recipeTypes || '' ).split( ' ' ).filter( Boolean );
			const seasons = ( item.dataset.recipeSeasons || '' ).split( ' ' ).filter( Boolean );
			const haystack = item.dataset.search || '';

			const matchType =
				active.type.size === 0 || types.some( ( t ) => active.type.has( t ) );
			const matchSeason =
				active.season.size === 0 || seasons.some( ( s ) => active.season.has( s ) );
			const matchQuery = query === '' || haystack.includes( query );

			const show = matchType && matchSeason && matchQuery;
			item.hidden = ! show;
			if ( show ) {
				visible += 1;
			}
		} );

		lastVisible = visible;
		updateCount( visible );
		if ( filterUI ) {
			filterUI.refresh();
		}

		if ( noResults ) {
			noResults.hidden = visible !== 0;
		}
		if ( navReset ) {
			navReset.hidden = ! isFiltered();
		}
	}

	/**
	 * Réinitialise tous les filtres et la recherche.
	 *
	 * @return {void}
	 */
	function reset() {
		active.type.clear();
		active.season.clear();
		query = '';

		checkboxes.forEach( ( cb ) => {
			cb.checked = false;
		} );
		[ 'type', 'season' ].forEach( updateDropdownBadge );
		if ( searchInput ) {
			searchInput.value = '';
		}
		if ( filterUI ) {
			filterUI.closeAll();
		}
		apply();
	}

	// --- Dropdowns : popover desktop / bottom sheet mobile (module partagé) --
	filterUI = initFilterDropdowns( root, { getCount: () => lastVisible } );

	// --- Cases à cocher (multi-select) ----------------------------------
	checkboxes.forEach( ( cb ) => {
		cb.addEventListener( 'change', () => {
			const group = cb.dataset.filterGroup;
			const value = cb.dataset.filterValue;
			if ( ! active[ group ] || ! value ) {
				return;
			}
			if ( cb.checked ) {
				active[ group ].add( value );
			} else {
				active[ group ].delete( value );
			}
			updateDropdownBadge( group );
			apply();
		} );
	} );

	// --- Recherche (debouncée) ------------------------------------------
	if ( searchInput ) {
		let timer = 0;
		searchInput.addEventListener( 'input', () => {
			window.clearTimeout( timer );
			timer = window.setTimeout( () => {
				query = normalize( searchInput.value );
				apply();
			}, SEARCH_DEBOUNCE_MS );
		} );
	}

	// --- Boutons de remise à zéro (barre + état vide) -------------------
	resetButtons.forEach( ( btn ) => {
		btn.addEventListener( 'click', reset );
	} );

	apply();
}
