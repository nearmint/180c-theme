/**
 * Validation inline du code promo — page panier.
 *
 * Le bouton « Appliquer » est un submit natif (rechargement complet). Champ
 * vide → WooCommerce renvoie une `.woocommerce-error` en haut de page. On
 * intercepte ce cas en amont : on bloque l'envoi et on affiche un message
 * contextuel `.coupon-error-notice` juste sous le champ.
 *
 * Enhancement progressif : sans JS, le submit natif + la notice WooCommerce
 * restent fonctionnels.
 */

const MESSAGE = 'Veuillez saisir un code promo';

/**
 * Initialise la validation inline si le champ coupon du panier est présent.
 */
export function init() {
	const container = document.querySelector( '.cart-table__coupon' );
	if ( ! container ) {
		return;
	}

	const input = container.querySelector( '#coupon_code' );
	const applyBtn = container.querySelector( 'button[name="apply_coupon"]' );
	if ( ! input || ! applyBtn ) {
		return;
	}

	let notice = null;

	const clearNotice = () => {
		input.removeAttribute( 'aria-invalid' );
		if ( notice ) {
			notice.remove();
			notice = null;
		}
	};

	const showNotice = () => {
		input.setAttribute( 'aria-invalid', 'true' );
		if ( ! notice ) {
			notice = document.createElement( 'p' );
			notice.className = 'coupon-error-notice';
			notice.setAttribute( 'role', 'alert' );
			container.appendChild( notice );
		}
		notice.textContent = MESSAGE;
	};

	// preventDefault() sur le clic d'un submit annule l'envoi du formulaire.
	applyBtn.addEventListener( 'click', ( event ) => {
		if ( input.value.trim() === '' ) {
			event.preventDefault();
			showNotice();
			input.focus();
		}
	} );

	input.addEventListener( 'input', clearNotice );
}
