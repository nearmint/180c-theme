/**
 * Modale YouTube — lecture grand format de la façade (blocs-gutenberg-ds v2).
 *
 * Front uniquement. La façade est rendue côté PHP
 * (inc/blocks/youtube-facade.php) : un lien `[data-yt-facade][data-yt-id]`
 * pointant nativement vers la page YouTube (repli a11y / sans-JS). Ce module
 * intercepte le clic et ouvre une MODALE UNIQUE (réutilisée pour tous les
 * embeds de la page) chargeant `youtube-nocookie.com/embed/{id}?autoplay=1`.
 *
 * Le clic vaut action utilisateur explicite → pas de gating par la CMP.
 *
 * Accessibilité : `aria-modal`, focus-trap, restitution du focus à la
 * fermeture, verrou de scroll `body`. Fermeture : bouton ✕ + Esc + clic sur le
 * backdrop. L'iframe est vidée à la fermeture (stoppe la lecture).
 *
 * @package 180c-theme
 */

const CLOSE_ICON =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

const modal = {
	/** @type {HTMLElement|null} */ overlay: null,
	/** @type {HTMLElement|null} */ dialog: null,
	/** @type {HTMLIFrameElement|null} */ iframe: null,
	/** @type {HTMLButtonElement|null} */ closeBtn: null,
	/** @type {HTMLElement|null} */ lastFocus: null,
};

let globalBound = false;

/**
 * Construit la modale (une seule fois) et l'ajoute au <body>.
 *
 * @return {HTMLElement}
 */
function ensureModal() {
	if ( modal.overlay ) {
		return modal.overlay;
	}

	const overlay = document.createElement( 'div' );
	overlay.className = 'yt-modal';
	overlay.setAttribute( 'role', 'dialog' );
	overlay.setAttribute( 'aria-modal', 'true' );
	overlay.setAttribute( 'aria-label', 'Lecteur vidéo' );
	overlay.hidden = true;

	const dialog = document.createElement( 'div' );
	dialog.className = 'yt-modal__dialog';

	const iframe = document.createElement( 'iframe' );
	iframe.className = 'yt-modal__iframe';
	iframe.setAttribute( 'title', 'Vidéo YouTube' );
	iframe.setAttribute(
		'allow',
		'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
	);
	iframe.setAttribute( 'allowfullscreen', '' );

	const closeBtn = document.createElement( 'button' );
	closeBtn.type = 'button';
	closeBtn.className = 'yt-modal__close';
	closeBtn.setAttribute( 'aria-label', 'Fermer la vidéo' );
	closeBtn.innerHTML = CLOSE_ICON;

	dialog.appendChild( iframe );
	overlay.append( dialog, closeBtn );
	document.body.appendChild( overlay );

	// Fermeture : clic backdrop (hors dialog) ou bouton ✕.
	overlay.addEventListener( 'click', ( event ) => {
		if ( event.target === overlay || event.target.closest( '.yt-modal__close' ) ) {
			closeModal();
		}
	} );

	modal.overlay = overlay;
	modal.dialog = dialog;
	modal.iframe = iframe;
	modal.closeBtn = closeBtn;

	bindGlobal();

	return overlay;
}

/**
 * Listeners globaux (Esc + focus-trap) — attachés une seule fois.
 */
function bindGlobal() {
	if ( globalBound ) {
		return;
	}
	globalBound = true;

	document.addEventListener( 'keydown', ( event ) => {
		if ( ! modal.overlay || modal.overlay.hidden ) {
			return;
		}
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			closeModal();
			return;
		}
		if ( event.key === 'Tab' ) {
			// Deux focusables : bouton fermer + iframe. On boucle entre eux.
			const focusables = [ modal.closeBtn, modal.iframe ];
			const active = document.activeElement;
			const idx = focusables.indexOf( active );
			event.preventDefault();
			let next;
			if ( event.shiftKey ) {
				next = idx <= 0 ? focusables[ focusables.length - 1 ] : focusables[ idx - 1 ];
			} else {
				next = idx === focusables.length - 1 ? focusables[ 0 ] : focusables[ idx + 1 ];
			}
			next?.focus();
		}
	} );
}

/**
 * Ouvre la modale sur un ID vidéo.
 *
 * @param {string}      id      ID vidéo YouTube.
 * @param {HTMLElement} trigger Élément déclencheur (retour focus à la fermeture).
 */
function openModal( id, trigger ) {
	if ( ! id ) {
		return;
	}
	ensureModal();

	modal.lastFocus = trigger || document.activeElement;
	modal.iframe.src =
		'https://www.youtube-nocookie.com/embed/' + encodeURIComponent( id ) + '?autoplay=1';

	modal.overlay.hidden = false;
	document.documentElement.classList.add( 'yt-modal-lock' );
	modal.closeBtn.focus();
}

/**
 * Ferme la modale, vide l'iframe (stoppe la lecture), restaure focus + scroll.
 */
function closeModal() {
	if ( ! modal.overlay || modal.overlay.hidden ) {
		return;
	}
	modal.overlay.hidden = true;
	modal.iframe.removeAttribute( 'src' );
	document.documentElement.classList.remove( 'yt-modal-lock' );

	if ( modal.lastFocus && typeof modal.lastFocus.focus === 'function' ) {
		modal.lastFocus.focus();
	}
	modal.lastFocus = null;
}

/**
 * Délégation : clic sur n'importe quelle façade YouTube de la page.
 */
function init() {
	document.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest( '[data-yt-facade]' );
		if ( ! trigger ) {
			return;
		}
		const id = trigger.getAttribute( 'data-yt-id' );
		if ( ! id ) {
			return;
		}
		event.preventDefault();
		openModal( id, trigger );
	} );
}

init();

export { init, openModal, closeModal };
