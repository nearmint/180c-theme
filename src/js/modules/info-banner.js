/**
 * Bandeau d'information — fermeture + garde date côté client.
 *
 * Chargé en dynamic-import depuis main.js uniquement si `[data-info-banner]`
 * est présent (le markup n'est rendu par PHP que si le bandeau doit s'afficher).
 *
 * Progressive enhancement :
 *  - Sans JS : le bandeau reste lisible (non refermable — dégradation acceptée).
 *  - Avec JS : fermeture mémorisée par version (localStorage), garde date
 *    cache-safe, collapse animé respectant prefers-reduced-motion.
 *
 * La mémorisation vaut pendant toute la durée d'affichage : aucune minuterie
 * « N jours ». La clé devient caduque dès que la version change (contenu /
 * variante modifiés) ou que la fenêtre de dates se termine.
 */

const KEY = '_180c_info_banner_dismissed';

/**
 * Parse une date ISO du dataset, en ignorant les valeurs vides ou invalides.
 *
 * @param {string} value Chaîne ISO 8601 (ou '').
 * @return {Date|null}
 */
const parseDate = ( value ) => {
	if ( ! value ) {
		return null;
	}
	const date = new Date( value );
	return isNaN( date.getTime() ) ? null : date;
};

/**
 * Initialise le bandeau d'information.
 */
const initInfoBanner = () => {
	const el = document.querySelector( '[data-info-banner]' );
	if ( ! el ) {
		return;
	}

	const version = el.dataset.version;

	// 1) Fermeture déjà mémorisée pour cette version (complément de la garde
	//    no-FOUC inline imprimée dans wp_head).
	let dismissed = null;
	try {
		dismissed = localStorage.getItem( KEY );
	} catch ( e ) {
		// localStorage indisponible (mode privé / quota) : on continue.
	}
	if ( dismissed === version ) {
		el.remove();
		return;
	}

	// 2) Garde date (cache-safe) : le HTML peut être servi depuis le cache après
	//    la fin de la fenêtre — on revérifie côté client.
	const now = new Date();
	const start = parseDate( el.dataset.start );
	const end = parseDate( el.dataset.end );
	if ( ( end && now > end ) || ( start && now < start ) ) {
		el.remove();
		return;
	}

	// 3) Bouton de fermeture.
	const closeBtn = el.querySelector( '[data-info-banner-close]' );
	if ( ! closeBtn ) {
		return;
	}

	const reduceMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	const removeBanner = () => {
		el.remove();
	};

	const close = () => {
		// Mémorise la fermeture (non bloquant si localStorage lève).
		try {
			localStorage.setItem( KEY, version );
		} catch ( e ) {
			// Quota / mode privé : la fermeture visuelle a quand même lieu.
		}

		closeBtn.setAttribute( 'disabled', 'disabled' );

		// Reduced motion : retrait immédiat, sans collapse animé.
		if ( reduceMotion ) {
			removeBanner();
			return;
		}

		// Collapse animé : fige la hauteur courante, force un reflow, puis
		// transitionne vers 0 (la transition tokenisée vit dans le CSS).
		el.style.height = `${ el.scrollHeight }px`;
		void el.offsetHeight; // reflow.
		el.style.height = '0';
		el.style.opacity = '0';

		// Retrait sur fin de transition de hauteur, avec fallback minuterie au
		// cas où transitionend ne se déclencherait pas (élément déjà masqué…).
		let done = false;
		const finish = () => {
			if ( done ) {
				return;
			}
			done = true;
			removeBanner();
		};

		el.addEventListener(
			'transitionend',
			( event ) => {
				if ( event.propertyName === 'height' ) {
					finish();
				}
			}
		);
		window.setTimeout( finish, 500 );
	};

	closeBtn.addEventListener( 'click', close );
};

export { initInfoBanner };
