/**
 * Admin — panneau « Automation » sous la liste des notifications push.
 *
 * Deux comportements, sans aucune dépendance (pas de jQuery, pas de build) :
 *   1. insertion d'un jeton à la position du curseur au clic sur sa pastille ;
 *   2. compteur de caractères vivant sous chaque champ de gabarit.
 *
 * Le panneau reste entièrement utilisable sans JavaScript : les jetons peuvent
 * être tapés à la main et les compteurs sont déjà rendus côté serveur.
 */
( function () {
	'use strict';

	var panel = document.getElementById( '180c-notif-automation' );

	if ( ! panel ) {
		return;
	}

	/**
	 * Insère un texte à la position du curseur dans un champ.
	 *
	 * @param {HTMLInputElement|HTMLTextAreaElement} field Champ visé.
	 * @param {string}                               text  Texte à insérer.
	 */
	function insertAtCursor( field, text ) {
		var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
		var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : field.value.length;

		field.value = field.value.slice( 0, start ) + text + field.value.slice( end );
		field.focus();
		field.setSelectionRange( start + text.length, start + text.length );
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	panel.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.notif-automation__token' );

		if ( ! button ) {
			return;
		}

		var field = document.getElementById( button.getAttribute( 'data-target' ) );

		if ( field ) {
			insertAtCursor( field, button.getAttribute( 'data-token' ) );
		}
	} );

	/**
	 * Rafraîchit le compteur associé à un champ.
	 *
	 * @param {HTMLElement} field Champ visé.
	 */
	function refreshCounter( field ) {
		var counter = panel.querySelector( '[data-counter-for="' + field.id + '"]' );

		if ( ! counter ) {
			return;
		}

		var count = counter.querySelector( '.notif-automation__count' );
		// Longueur du GABARIT saisi, pas du texte rendu : le rendu réel dépend
		// de la recette et s'affiche dans le bloc « Aperçu » après
		// enregistrement.
		var length = Array.from( field.value ).length;

		if ( count ) {
			count.textContent = String( length );
		}

		var recommended = parseInt( ( counter.textContent.match( /(\d+)\s*max/ ) || [ 0, 0 ] )[ 1 ], 10 );
		counter.classList.toggle( 'is-over', recommended > 0 && length > recommended );
	}

	[ '180c-notif-heading', '180c-notif-subtitle', '180c-notif-content' ].forEach( function ( id ) {
		var field = document.getElementById( id );

		if ( ! field ) {
			return;
		}

		field.addEventListener( 'input', function () {
			refreshCounter( field );
		} );
	} );
}() );
