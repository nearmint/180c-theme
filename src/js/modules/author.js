/**
 * Page auteur — enhancement « Charger plus ».
 *
 * Progressive enhancement : transforme la nav de pagination native en bouton
 * AJAX qui ajoute des cartes à la grille existante. Si le module ne se
 * charge pas (JS désactivé, erreur réseau, REST KO), la pagination serveur
 * reste pleinement fonctionnelle — c'est le mode par défaut.
 *
 * Contrats DOM (cf. patterns/author-{articles,recipes}.php) :
 *   section[data-author-section] — "post" | "recipe"
 *   section[data-author-id]      — entier
 *   section[data-current-page]   — entier (1+)
 *   section[data-max-pages]      — entier (1+)
 *   ul[data-author-section-grid]  — réceptacle (aria-live="polite")
 *   nav[data-author-section-pagination] — remplacé par le bouton si JS OK
 *
 * Endpoint REST : GET /wp-json/180c/v1/author-content
 *   ?author_id=…&type=post|recipe&page=…
 *
 * Pas de jQuery, vanilla ES module, aucun framework.
 */

// restFetch pose le nonce COURANT et rejoue une fois sur nonce invalide : le
// nonce n'est plus inliné dans le HTML (cache WPSC), une constante lue à
// l'import capturait une chaîne vide refusée en 403 par le cœur REST.
import { restFetch } from './session.js';

const REST_BASE = ( window._180c && window._180c.restUrl ) ? window._180c.restUrl : '/wp-json/180c/v1/';

/**
 * Initialise une section auteur (articles ou recettes).
 *
 * @param {HTMLElement} section Élément <section data-author-section="…">.
 */
function initSection( section ) {
	const type      = section.dataset.authorSection;
	const authorId  = parseInt( section.dataset.authorId, 10 );
	const maxPages  = parseInt( section.dataset.maxPages, 10 ) || 1;
	const grid      = section.querySelector( '[data-author-section-grid]' );
	const paginator = section.querySelector( '[data-author-section-pagination]' );

	if ( ! grid || ! paginator || ! authorId || maxPages < 2 ) {
		// Section déjà complète (≤ 12 items) ou DOM incomplet → on laisse la
		// nav serveur en place et on n'attache rien.
		return;
	}

	let currentPage = parseInt( section.dataset.currentPage, 10 ) || 1;

	// Si l'utilisateur est arrivé sur une page paginée (?articles_page=3), on
	// laisse la nav serveur faire son travail : changer dynamiquement la page
	// courante côté JS sans déterminer l'historique de navigation est plus
	// confus que utile pour cette init. Donc enhancement uniquement page 1.
	if ( currentPage !== 1 ) {
		return;
	}

	// Crée le bouton "Charger plus" et remplace la nav serveur.
	const wrapper = document.createElement( 'div' );
	wrapper.className = 'author-section__load-more-wrapper';

	const button = document.createElement( 'button' );
	button.type        = 'button';
	button.className   = 'author-section__load-more';
	button.textContent = ( type === 'recipe' )
		? 'Voir plus de recettes'
		: 'Voir plus d’articles';
	button.setAttribute( 'aria-controls', grid.id || '' );

	wrapper.appendChild( button );
	paginator.replaceWith( wrapper );

	button.addEventListener( 'click', async () => {
		const nextPage = currentPage + 1;
		if ( nextPage > maxPages ) {
			return;
		}

		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );

		try {
			const url = new URL( REST_BASE.replace( /\/+$/, '' ) + '/author-content', window.location.origin );
			url.searchParams.set( 'author_id', authorId );
			url.searchParams.set( 'type', type );
			url.searchParams.set( 'page', nextPage );

			const response = await restFetch( url.toString(), {
				method: 'GET',
				headers: {
					Accept: 'application/json',
				},
			} );

			if ( ! response.ok ) {
				throw new Error( 'REST author-content failed: ' + response.status );
			}

			const data = await response.json();

			if ( data && typeof data.items === 'string' && data.items.length > 0 ) {
				// Les cartes renvoyées par le serveur sont déjà des <article>
				// directement. On les wrap dans des <li> pour préserver la
				// sémantique de la grille (<ul role="list">).
				const tmp = document.createElement( 'div' );
				tmp.innerHTML = data.items;
				Array.from( tmp.children ).forEach( ( card ) => {
					const li = document.createElement( 'li' );
					li.className = 'author-section__item';
					li.appendChild( card );
					grid.appendChild( li );
				} );
			}

			currentPage = data.page || nextPage;

			if ( ! data.has_more || currentPage >= maxPages ) {
				wrapper.remove();
			}
		} catch ( error ) {
			// En cas d'échec, on rétablit le bouton et on log dans la console
			// (l'UX dégradée reste meilleure que de casser la page).
			// eslint-disable-next-line no-console
			console.warn( '[180c] Author load-more failed', error );
			button.disabled = false;
			button.removeAttribute( 'aria-busy' );
		}

		button.removeAttribute( 'aria-busy' );
		button.disabled = false;
	} );
}

/**
 * Point d'entrée — appelé une fois le DOM prêt.
 *
 * @returns {void}
 */
export function init() {
	const sections = document.querySelectorAll( '[data-author-section]' );
	sections.forEach( initSection );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
