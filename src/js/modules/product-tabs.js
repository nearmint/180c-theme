/**
 * Onglets de la fiche produit — interaction accessible (ARIA Tabs).
 *
 * Enhancement progressif : sans JS, tous les panneaux sont empilés et
 * visibles (rendu PHP, inc/woo/product-tabs-render.php). À l'init, ce
 * module :
 *   - pose `data-tabs-ready` sur la racine (bascule les titres de panneaux
 *     en sr-only via CSS) ;
 *   - masque tous les panneaux sauf l'actif (attribut natif [hidden]) ;
 *   - câble la navigation souris + clavier (←/→, Home/End) avec roving
 *     tabindex et gestion de aria-selected ;
 *   - active l'onglet ciblé par le hash de l'URL au chargement
 *     (#reportages, #recettes, #informations-techniques).
 *
 * Vanilla ES module, sans dépendance, idempotent.
 */

/**
 * Initialise un conteneur d'onglets.
 *
 * @param {HTMLElement} root Élément [data-product-tabs].
 */
function initTabs( root ) {
	if ( root.dataset.tabsReady === 'true' ) {
		return;
	}

	const tablist = root.querySelector( '[role="tablist"]' );
	const tabs = Array.from( root.querySelectorAll( '[role="tab"]' ) );
	if ( ! tablist || tabs.length === 0 ) {
		return;
	}

	// Apparie chaque onglet à son panneau via aria-controls.
	const pairs = tabs
		.map( ( tab ) => ( {
			tab,
			panel: document.getElementById( tab.getAttribute( 'aria-controls' ) ),
		} ) )
		.filter( ( pair ) => pair.panel );

	if ( pairs.length === 0 ) {
		return;
	}

	/**
	 * Active l'onglet d'index donné (et masque les autres panneaux).
	 *
	 * @param {number}  index    Index dans `pairs`.
	 * @param {boolean} setFocus Déplacer le focus sur l'onglet activé.
	 */
	function activate( index, setFocus ) {
		pairs.forEach( ( { tab, panel }, i ) => {
			const selected = i === index;
			tab.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			tab.tabIndex = selected ? 0 : -1;
			panel.hidden = ! selected;
		} );
		if ( setFocus ) {
			pairs[ index ].tab.focus();
		}
	}

	// Clic souris / tactile.
	pairs.forEach( ( { tab }, index ) => {
		tab.addEventListener( 'click', () => activate( index, false ) );
	} );

	// Navigation clavier sur le tablist.
	tablist.addEventListener( 'keydown', ( event ) => {
		const currentIndex = pairs.findIndex(
			( { tab } ) => tab === document.activeElement
		);
		if ( currentIndex === -1 ) {
			return;
		}

		let nextIndex = null;
		switch ( event.key ) {
			case 'ArrowRight':
			case 'ArrowDown':
				nextIndex = ( currentIndex + 1 ) % pairs.length;
				break;
			case 'ArrowLeft':
			case 'ArrowUp':
				nextIndex = ( currentIndex - 1 + pairs.length ) % pairs.length;
				break;
			case 'Home':
				nextIndex = 0;
				break;
			case 'End':
				nextIndex = pairs.length - 1;
				break;
			default:
				return;
		}

		event.preventDefault();
		activate( nextIndex, true );
	} );

	// Détermine l'onglet initial : hash d'URL prioritaire, sinon le 1er.
	let initialIndex = 0;
	const hashKey = window.location.hash.replace( /^#/, '' );
	if ( hashKey ) {
		const hashIndex = pairs.findIndex( ( { panel } ) =>
			panel.id.endsWith( '-' + hashKey )
		);
		if ( hashIndex !== -1 ) {
			initialIndex = hashIndex;
		}
	}

	// dataset.tabsReady pose l'attribut `data-tabs-ready` (cf. CSS + garde idempotente).
	root.dataset.tabsReady = 'true';
	activate( initialIndex, false );
}

/**
 * Point d'entrée : initialise tous les conteneurs d'onglets de la page.
 */
export function init() {
	document
		.querySelectorAll( '[data-product-tabs]' )
		.forEach( ( root ) => initTabs( root ) );
}

export default init;
