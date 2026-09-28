/**
 * Archive « Toutes les recettes » — filtres dynamiques + scroll infini.
 *
 * Enhancement progressif de l'archive du post type recipe
 * (`archive-recipe.php`, surface `[data-recipes-archive]`). La 1re page est
 * rendue côté serveur ; ce module :
 *
 *   - charge les lots suivants au scroll via IntersectionObserver sur un
 *     sentinel, en interrogeant l'endpoint REST `/wp-json/180c/v1/recipes` ;
 *   - filtre par mot-clé (recherche WP native, debouncée) et par dropdowns
 *     multi-select de taxonomie (recipe_category / recipe_season /
 *     recipe_type — OR dans un groupe, AND entre groupes), en rejouant la
 *     requête depuis la page 1 ;
 *   - gère compteur, état « aucun résultat », loader et bouton de remise à zéro.
 *
 * Sans JS, la 1re page reste lisible (pas de scroll infini, pas de filtres
 * dynamiques) — dégradation acceptée pour cette page d'exploration.
 *
 * Contrats DOM (cf. archive-recipe.php) :
 *   [data-recipes-archive]          racine <main>
 *   [data-recipes-filters]          <form> de filtres
 *   [data-recipes-search]           <input type=search>
 *   [data-recipes-dropdown]         conteneur d'un dropdown (data-filter-group)
 *   [data-recipes-dropdown-toggle]  <button> d'ouverture (aria-expanded)
 *   [data-recipes-dropdown-panel]   panneau d'options
 *   [data-recipes-dropdown-count]   badge « n sélectionné(s) »
 *   [data-recipes-filter]           <input checkbox> (data-filter-group/-value)
 *   [data-recipes-reset]            <button> remise à zéro
 *   [data-recipes-grid]             <ul> (data-page, data-has-more)
 *   [data-recipes-count]            compteur live
 *   [data-recipes-noresults]        état vide
 *   [data-recipes-loader]           indicateur de chargement
 *   [data-recipes-sentinel]         cible IntersectionObserver
 *
 * @module recipes-archive
 */

import { initFilterDropdowns } from './recipe-filters-sheet.js';
// restFetch pose le nonce COURANT et rejoue une fois sur nonce invalide : le
// nonce n'est plus inliné dans le HTML (cache WPSC), une constante lue à
// l'import capturait une chaîne vide refusée en 403 par le cœur REST.
import { restFetch } from './session.js';

const SEARCH_DEBOUNCE_MS = 300;
const REST_BASE = ( window._180c && window._180c.restUrl ) ? window._180c.restUrl : '/wp-json/180c/v1/';

/**
 * Initialise l'archive recettes (filtres + scroll infini).
 *
 * @return {void}
 */
export function init() {
	const root = document.querySelector( '[data-recipes-archive]' );
	if ( ! root ) {
		return;
	}

	const grid = root.querySelector( '[data-recipes-grid]' );
	const sentinel = root.querySelector( '[data-recipes-sentinel]' );
	const searchInput = root.querySelector( '[data-recipes-search]' );
	const checkboxes = Array.from( root.querySelectorAll( '[data-recipes-filter]' ) );
	const dropdowns = Array.from( root.querySelectorAll( '[data-recipes-dropdown]' ) );
	const resetButton = root.querySelector( '[data-recipes-reset]' );
	const countEl = root.querySelector( '[data-recipes-count]' );
	const noResults = root.querySelector( '[data-recipes-noresults]' );
	const loader = root.querySelector( '[data-recipes-loader]' );

	if ( ! grid ) {
		return;
	}

	// État.
	const active = { category: new Set(), season: new Set(), type: new Set() };
	let query = '';
	let page = parseInt( grid.dataset.page, 10 ) || 1;
	let hasMore = grid.dataset.hasMore === 'true';
	let loading = false;
	let requestId = 0;
	let controller = null;
	let lastTotal = null;
	// Contrôleur des dropdowns (popover desktop / bottom sheet mobile) — assigné
	// plus bas ; référencé par load()/reset() qui s'exécutent après l'init.
	let filterUI = null;

	/**
	 * Indique si un filtre ou une recherche est actif.
	 *
	 * @return {boolean} Vrai si au moins un critère est posé.
	 */
	function isFiltered() {
		return query !== '' || active.category.size > 0 || active.season.size > 0 || active.type.size > 0;
	}

	/**
	 * Construit l'URL REST pour une page donnée selon l'état des filtres.
	 *
	 * @param {number} pageToLoad Numéro de page à charger.
	 * @return {string} URL absolue.
	 */
	function buildUrl( pageToLoad ) {
		const url = new URL( REST_BASE.replace( /\/+$/, '' ) + '/recipes', window.location.origin );
		url.searchParams.set( 'page', String( pageToLoad ) );
		if ( query ) {
			url.searchParams.set( 'search', query );
		}
		Object.keys( active ).forEach( ( group ) => {
			if ( active[ group ].size > 0 ) {
				url.searchParams.set( group, Array.from( active[ group ] ).join( ',' ) );
			}
		} );
		return url.toString();
	}

	/**
	 * Enveloppe les cartes renvoyées (<article>) dans des <li> de grille.
	 *
	 * @param {string} html HTML des cartes.
	 * @return {void}
	 */
	function appendCards( html ) {
		if ( ! html ) {
			return;
		}
		const tmp = document.createElement( 'div' );
		tmp.innerHTML = html;
		const frag = document.createDocumentFragment();
		Array.from( tmp.children ).forEach( ( card ) => {
			const li = document.createElement( 'li' );
			li.className = 'recipes-grid__item';
			li.setAttribute( 'role', 'listitem' );
			li.appendChild( card );
			frag.appendChild( li );
		} );
		grid.appendChild( frag );
	}

	/**
	 * Met à jour le compteur de résultats.
	 *
	 * @param {number} total Nombre total de recettes correspondant.
	 * @return {void}
	 */
	function updateCount( total ) {
		if ( ! countEl ) {
			return;
		}
		const s = total > 1 ? 's' : '';
		countEl.textContent = isFiltered()
			? `${ total } résultat${ s }`
			: `${ total } recette${ s }`;
	}

	/**
	 * Met à jour le badge de comptage d'un dropdown + son état actif.
	 *
	 * @param {string} group Groupe de filtre (category|season|type).
	 * @return {void}
	 */
	function updateDropdownBadge( group ) {
		const dropdown = dropdowns.find( ( d ) => d.dataset.filterGroup === group );
		if ( ! dropdown ) {
			return;
		}
		const size = active[ group ] ? active[ group ].size : 0;
		const toggle = dropdown.querySelector( '[data-recipes-dropdown-toggle]' );
		const badge = dropdown.querySelector( '[data-recipes-dropdown-count]' );
		if ( badge ) {
			badge.textContent = size > 0 ? String( size ) : '';
			badge.hidden = size === 0;
		}
		if ( toggle ) {
			toggle.classList.toggle( 'recipe-filters__dropdown-toggle--active', size > 0 );
		}
	}

	/**
	 * Reflète l'état courant (résultats vides + bouton reset).
	 *
	 * @return {void}
	 */
	function updateStates() {
		const empty = grid.children.length === 0;
		if ( noResults ) {
			noResults.hidden = ! empty;
		}
		if ( resetButton ) {
			resetButton.hidden = ! isFiltered();
		}
	}

	/**
	 * Affiche ou masque le loader.
	 *
	 * @param {boolean} on État souhaité.
	 * @return {void}
	 */
	function setLoader( on ) {
		if ( loader ) {
			loader.hidden = ! on;
		}
	}

	/**
	 * Charge une page de résultats.
	 *
	 * @param {number}  pageToLoad Page à charger.
	 * @param {boolean} replace    Vrai pour remplacer la grille (changement de
	 *                             filtre) ; faux pour appondre (scroll infini).
	 * @return {Promise<void>}
	 */
	async function load( pageToLoad, replace ) {
		if ( controller ) {
			controller.abort();
		}
		controller = new AbortController();
		const id = ++requestId;
		loading = true;
		setLoader( true );

		try {
			const response = await restFetch( buildUrl( pageToLoad ), {
				method: 'GET',
				headers: { Accept: 'application/json' },
				signal: controller.signal,
			} );

			if ( ! response.ok ) {
				throw new Error( 'REST recipes failed: ' + response.status );
			}

			const data = await response.json();

			// Réponse superséedée par un filtrage plus récent.
			if ( id !== requestId ) {
				return;
			}

			if ( replace ) {
				grid.innerHTML = '';
			}
			appendCards( typeof data.items === 'string' ? data.items : '' );

			page = data.page || pageToLoad;
			hasMore = !! data.has_more;
			grid.dataset.page = String( page );
			grid.dataset.hasMore = String( hasMore );

			lastTotal = typeof data.total === 'number' ? data.total : grid.children.length;
			updateCount( lastTotal );
			if ( filterUI ) {
				filterUI.refresh();
			}
			updateStates();
		} catch ( error ) {
			if ( error.name === 'AbortError' ) {
				return;
			}
			// eslint-disable-next-line no-console
			console.warn( '[180c] Recipes archive load failed', error );
		} finally {
			// Seule la requête la plus récente pilote l'UI partagée.
			if ( id === requestId ) {
				loading = false;
				setLoader( false );
			}
		}
	}

	/**
	 * Rejoue la requête depuis la page 1 après un changement de filtre.
	 *
	 * @return {void}
	 */
	function refilter() {
		load( 1, true );
	}

	/**
	 * Réinitialise filtres + recherche.
	 *
	 * @return {void}
	 */
	function reset() {
		active.category.clear();
		active.season.clear();
		active.type.clear();
		query = '';
		checkboxes.forEach( ( cb ) => {
			cb.checked = false;
		} );
		[ 'category', 'season', 'type' ].forEach( updateDropdownBadge );
		if ( searchInput ) {
			searchInput.value = '';
		}
		if ( filterUI ) {
			filterUI.closeAll();
		}
		refilter();
	}

	// --- Dropdowns : popover desktop / bottom sheet mobile (module partagé) --
	filterUI = initFilterDropdowns( root, { getCount: () => lastTotal } );

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
			refilter();
		} );
	} );

	// --- Recherche (debouncée) ------------------------------------------
	if ( searchInput ) {
		let timer = 0;
		searchInput.addEventListener( 'input', () => {
			window.clearTimeout( timer );
			timer = window.setTimeout( () => {
				query = searchInput.value.trim();
				refilter();
			}, SEARCH_DEBOUNCE_MS );
		} );
	}

	// --- Bouton de remise à zéro ----------------------------------------
	if ( resetButton ) {
		resetButton.addEventListener( 'click', reset );
	}

	// --- Scroll infini ---------------------------------------------------
	if ( sentinel && 'IntersectionObserver' in window ) {
		const observer = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( entry.isIntersecting && hasMore && ! loading ) {
						load( page + 1, false );
					}
				} );
			},
			{ rootMargin: '600px 0px' }
		);
		observer.observe( sentinel );
	}

	// --- Révélation au scroll --------------------------------------------
	// La barre de filtres n'apparaît qu'une fois l'en-tête d'archive
	// (.archive-header) sorti du viewport. Rendue masquée côté serveur
	// (.recipe-filters--collapsed) pour éviter tout flash au chargement.
	const filtersForm = root.querySelector( '[data-recipes-filters]' );
	const archiveHeader = document.querySelector( '.archive-header' );
	if ( filtersForm ) {
		if ( archiveHeader && 'IntersectionObserver' in window ) {
			const headerObserver = new IntersectionObserver(
				( entries ) => {
					const headerVisible = entries[ 0 ].isIntersecting;
					filtersForm.classList.toggle( 'recipe-filters--collapsed', headerVisible );
					filtersForm.classList.toggle( 'recipe-filters--revealed', ! headerVisible );
				},
				{ threshold: 0 }
			);
			headerObserver.observe( archiveHeader );
		} else {
			// Repli (pas d'observer ou d'en-tête) : barre toujours visible.
			filtersForm.classList.remove( 'recipe-filters--collapsed' );
		}
	}

	// Synchronise l'UI au chargement (cas improbable d'état initial filtré).
	updateStates();
}
