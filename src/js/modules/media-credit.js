/**
 * Media credit — légende repliable d'image (©).
 *
 * Progressive enhancement strict :
 *  - Sans JS : la légende est visible (le CSS ne masque la légende QUE
 *    sous `.media-credit.js-enhanced`).
 *  - Avec JS : le wrapper reçoit `js-enhanced` au boot, le toggle reçoit
 *    `aria-expanded="false"` + `aria-controls={caption.id}`, la légende
 *    se replie. Un clic toggle `aria-expanded` + classe `is-open` sur le
 *    wrapper (le CSS pilote l'ouverture vers la gauche).
 *
 * No-op silencieux si aucun `.media-credit` n'est présent.
 */

let counter = 0;

const enhance = ( wrapper ) => {
	const toggle = wrapper.querySelector( '.media-credit__toggle' );
	const caption = wrapper.querySelector( '.media-credit__caption' );

	if ( ! toggle || ! caption ) {
		return;
	}

	// Garantit un id unique sur la légende pour aria-controls.
	if ( ! caption.id ) {
		counter += 1;
		caption.id = `media-credit-caption-${ counter }`;
	}

	toggle.setAttribute( 'aria-expanded', 'false' );
	toggle.setAttribute( 'aria-controls', caption.id );
	wrapper.classList.add( 'js-enhanced' );

	toggle.addEventListener( 'click', () => {
		const isOpen = wrapper.classList.toggle( 'is-open' );
		toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
	} );
};

const init = () => {
	const wrappers = document.querySelectorAll( '.media-credit' );
	if ( ! wrappers.length ) {
		return;
	}
	wrappers.forEach( enhance );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init, { once: true } );
} else {
	init();
}

export { init };
