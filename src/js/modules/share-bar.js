/**
 * Barre de partage — bouton « Copier le lien » (recette + article).
 *
 * Délégation globale sur toute barre `[data-share-bar]` rendue par
 * `_180c_render_share_actions()` (inc/share-actions.php), partagée par
 * single-recipe.php et single.php. Au clic sur `[data-share-copy]`, copie
 * l'URL dans le presse-papier puis confirme via le toast réutilisable.
 *
 * Sans JS, les liens réseaux restent fonctionnels ; seul le bouton copier
 * (enhancement) est inerte.
 *
 * Importé STATIQUEMENT depuis main.js (comme cart.js / favorites.js, qui
 * importent aussi toast.js) puis auto-initialisé. Surtout PAS en import
 * dynamique : créer un chunk lazy important toast.js (déjà présent dans le
 * bundle principal) force Rollup à ré-évaluer l'entrée → tous les modules
 * always-on (side-menu, panier…) se lient deux fois et leurs toggles
 * s'annulent (régression « menu/panier ne s'ouvrent plus après copie »).
 *
 * @module share-bar
 */

import { showToast } from './toast.js';

/**
 * Exécute fn quand le DOM est prêt (defer implicite des ES modules + garde).
 *
 * @param {Function} fn
 */
function whenReady( fn ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', fn, { once: true } );
	} else {
		fn();
	}
}

/**
 * Copie un texte dans le presse-papier (API moderne + repli execCommand).
 *
 * @param {string} text
 * @returns {Promise<void>}
 */
function copyToClipboard( text ) {
	if ( navigator.clipboard?.writeText ) {
		return navigator.clipboard.writeText( text );
	}

	return new Promise( ( resolve, reject ) => {
		try {
			const temp = document.createElement( 'textarea' );
			temp.value = text;
			temp.setAttribute( 'readonly', '' );
			temp.style.position = 'absolute';
			temp.style.left = '-9999px';
			document.body.appendChild( temp );
			temp.select();
			document.execCommand( 'copy' );
			document.body.removeChild( temp );
			resolve();
		} catch ( err ) {
			reject( err );
		}
	} );
}

/**
 * Initialise le copier-lien sur toutes les barres de partage de la page.
 */
function init() {
	const bars = document.querySelectorAll( '[data-share-bar]' );
	if ( ! bars.length ) {
		return;
	}

	bars.forEach( ( bar ) => {
		bar.addEventListener( 'click', ( e ) => {
			const copy = e.target.closest( '[data-share-copy]' );
			if ( ! copy || ! bar.contains( copy ) ) {
				return;
			}
			e.preventDefault();

			const link =
				copy.dataset.shareCopy || bar.dataset.shareUrl || window.location.href;

			copyToClipboard( link )
				.then( () => showToast( { message: 'Lien copié' } ) )
				.catch( () =>
					showToast( { message: 'Impossible de copier le lien' } )
				);
		} );
	} );
}

whenReady( init );
