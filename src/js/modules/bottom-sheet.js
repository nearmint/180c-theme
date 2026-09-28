/**
 * Bottom sheet générique (mobile) — primitive partagée.
 *
 * Transforme un panneau DOM existant en feuille basse modale, à la demande. À
 * l'ouverture, le panneau est **déplacé dans un overlay ajouté à <body>**
 * (portal) : son `position: fixed`/flux est alors résolu par rapport au
 * viewport quoi qu'il arrive — immunisé contre un ancêtre `transform` /
 * `overflow` (bug « rien au click »). Le contenu d'origine du panneau est
 * enrobé dans `.bottom-sheet__body` (zone défilante) ; un en-tête (poignée +
 * titre + fermeture) et un pied optionnel sont injectés. Un placeholder (nœud
 * commentaire) mémorise la position d'origine pour restaurer proprement à la
 * fermeture.
 *
 * Habillage visuel : `src/css/components/bottom-sheet.css`.
 *
 * Consommateurs : filtres recettes (recipe-filters-sheet.js), menu compte
 * mobile (account-menu-toggle.js).
 *
 * @module bottom-sheet
 */

const BODY_LOCK_CLASS = 'bottom-sheet-lock';
const CLOSE_SVG =
	'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

/**
 * Ouvre `panel` en feuille basse.
 *
 * @param {HTMLElement} panel              Panneau à présenter (déplacé/restauré).
 * @param {Object}      [options]          Options.
 * @param {string}      [options.title]    Titre affiché dans l'en-tête.
 * @param {HTMLElement} [options.footer]   Élément de pied optionnel (actions).
 * @param {Function}    [options.onClose]  Rappel exécuté après fermeture.
 * @return {{ close: Function, panel: HTMLElement, body: HTMLElement }} Contrôleur.
 */
export function openBottomSheet( panel, { title = '', footer = null, onClose = null } = {} ) {
	const placeholder = document.createComment( 'bottom-sheet' );
	const wasHidden = panel.hidden;

	const overlay = document.createElement( 'div' );
	overlay.className = 'bottom-sheet__overlay';

	const head = document.createElement( 'div' );
	head.className = 'bottom-sheet__head';
	const titleEl = document.createElement( 'span' );
	titleEl.className = 'bottom-sheet__title';
	titleEl.textContent = title;
	const closeBtn = document.createElement( 'button' );
	closeBtn.type = 'button';
	closeBtn.className = 'bottom-sheet__close';
	closeBtn.setAttribute( 'aria-label', 'Fermer' );
	closeBtn.innerHTML = CLOSE_SVG;
	head.append( titleEl, closeBtn );

	const body = document.createElement( 'div' );
	body.className = 'bottom-sheet__body';

	// Portal : placeholder à la place du panneau, contenu déplacé dans body.
	panel.replaceWith( placeholder );
	while ( panel.firstChild ) {
		body.append( panel.firstChild );
	}
	panel.classList.add( 'bottom-sheet__panel' );
	panel.hidden = false;
	panel.append( head, body );
	if ( footer ) {
		panel.append( footer );
	}
	overlay.append( panel );
	document.body.append( overlay );
	document.body.classList.add( BODY_LOCK_CLASS );

	let closed = false;

	/**
	 * Ferme la feuille et restaure le panneau à sa position d'origine.
	 *
	 * @return {void}
	 */
	function close() {
		if ( closed ) {
			return;
		}
		closed = true;
		document.removeEventListener( 'keydown', onKey );

		// Dé-enrobage : remet le contenu dans le panneau, retire l'habillage.
		head.remove();
		if ( footer ) {
			footer.remove();
		}
		while ( body.firstChild ) {
			panel.append( body.firstChild );
		}
		body.remove();
		panel.classList.remove( 'bottom-sheet__panel' );
		panel.hidden = wasHidden;
		placeholder.replaceWith( panel );
		overlay.remove();
		document.body.classList.remove( BODY_LOCK_CLASS );

		if ( typeof onClose === 'function' ) {
			onClose();
		}
	}

	function onKey( event ) {
		if ( event.key === 'Escape' ) {
			close();
		}
	}

	overlay.addEventListener( 'click', ( event ) => {
		if ( event.target === overlay ) {
			close();
		}
	} );
	closeBtn.addEventListener( 'click', close );
	document.addEventListener( 'keydown', onKey );

	// Focus l'action de fermeture (a11y).
	closeBtn.focus();

	return { close, panel, body };
}
