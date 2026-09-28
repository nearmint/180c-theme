/**
 * Account dropdown 180°C.
 *
 * Pattern WAI-ARIA Menu Button. Le bouton .js-account-toggle ouvre / ferme
 * un menu .site-header__account-dropdown qui contient un set d'items
 * role="menuitem". Pas de focus trap : Tab depuis le dernier item ferme
 * le dropdown et continue le flow normal du document.
 *
 * Comportement clavier :
 *  - Enter / Space sur le bouton → ouvre (focus 1er item)
 *  - ArrowDown sur le bouton → ouvre (focus 1er item)
 *  - ArrowUp sur le bouton → ouvre (focus dernier item)
 *  - Escape → ferme + focus retour bouton
 *  - ArrowDown / ArrowUp dans le menu → navigue avec boucle
 *  - Home / End → premier / dernier item
 *  - Tab depuis le dernier item → ferme et continue
 *  - Shift+Tab depuis le premier item → ferme et continue
 *  - Click extérieur → ferme
 *
 * Markup attendu :
 *   .js-account-dropdown
 *     button.js-account-toggle[aria-expanded][aria-controls=…]
 *     div.site-header__account-dropdown[role=menu]
 *       a[role=menuitem] × N
 */

const OPEN_MODIFIER_BUTTON = 'site-header__account--open';
const OPEN_MODIFIER_DROPDOWN = 'site-header__account-dropdown--open';

function whenReady(fn) {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', fn, { once: true });
	} else {
		fn();
	}
}

whenReady(() => {
	const wraps = document.querySelectorAll('.js-account-dropdown');
	wraps.forEach((wrap) => initOne(wrap));
});

/**
 * Initialise un dropdown.
 *
 * @param {HTMLElement} wrap
 */
function initOne(wrap) {
	const button = wrap.querySelector('.js-account-toggle');
	const dropdown = wrap.querySelector('.site-header__account-dropdown');

	if (!button || !dropdown) {
		return;
	}

	const items = Array.from(dropdown.querySelectorAll('[role="menuitem"]'));

	if (items.length === 0) {
		return;
	}

	const state = { open: false };

	// --- Toggle bouton -----------------------------------------------------
	button.addEventListener('click', (event) => {
		event.stopPropagation();
		if (state.open) {
			close({ focusButton: false });
		} else {
			open({ focusIndex: 0 });
		}
	});

	button.addEventListener('keydown', (event) => {
		if (event.key === 'ArrowDown') {
			event.preventDefault();
			open({ focusIndex: 0 });
		} else if (event.key === 'ArrowUp') {
			event.preventDefault();
			open({ focusIndex: items.length - 1 });
		}
	});

	// --- Navigation clavier dans le menu -----------------------------------
	dropdown.addEventListener('keydown', (event) => {
		if (!state.open) {
			return;
		}

		const currentIndex = items.indexOf(document.activeElement);

		switch (event.key) {
			case 'Escape':
				event.preventDefault();
				close({ focusButton: true });
				break;

			case 'ArrowDown':
				event.preventDefault();
				focusItem(currentIndex < 0 ? 0 : (currentIndex + 1) % items.length);
				break;

			case 'ArrowUp':
				event.preventDefault();
				focusItem(currentIndex <= 0 ? items.length - 1 : currentIndex - 1);
				break;

			case 'Home':
				event.preventDefault();
				focusItem(0);
				break;

			case 'End':
				event.preventDefault();
				focusItem(items.length - 1);
				break;

			case 'Tab': {
				// Pas de focus trap : Tab depuis le dernier item (ou Shift+Tab
				// depuis le premier) ferme et laisse le flow continuer.
				const isLast = !event.shiftKey && currentIndex === items.length - 1;
				const isFirstReverse = event.shiftKey && currentIndex === 0;
				if (isLast || isFirstReverse) {
					close({ focusButton: false });
				}
				break;
			}

			default:
				break;
		}
	});

	// --- Click extérieur ---------------------------------------------------
	document.addEventListener('click', (event) => {
		if (!state.open) {
			return;
		}
		if (wrap.contains(event.target)) {
			return;
		}
		close({ focusButton: false });
	});

	// --- Escape global (au cas où le focus aurait quitté le menu) ----------
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && state.open) {
			close({ focusButton: true });
		}
	});

	// --- Couche unique ------------------------------------------
	// Un autre panneau (drawer panier / side-menu) qui s'ouvre demande la
	// fermeture de tous les panneaux : on ferme ce dropdown s'il est ouvert.
	document.addEventListener('180c:close-panels', () => {
		if (state.open) {
			close({ focusButton: false });
		}
	});

	// --- Helpers internes --------------------------------------------------

	function open({ focusIndex = 0 } = {}) {
		if (state.open) {
			return;
		}
		// Couche unique : ferme drawer panier + side-menu avant d'ouvrir.
		document.dispatchEvent(new CustomEvent('180c:close-panels'));
		state.open = true;
		button.setAttribute('aria-expanded', 'true');
		button.classList.add(OPEN_MODIFIER_BUTTON);
		dropdown.classList.add(OPEN_MODIFIER_DROPDOWN);

		// Le focus initial sur un item peut interférer avec la transition.
		// On attend une frame que `visibility: visible` soit appliqué.
		window.requestAnimationFrame(() => {
			focusItem(focusIndex);
		});
	}

	function close({ focusButton = false } = {}) {
		if (!state.open) {
			return;
		}
		state.open = false;
		button.setAttribute('aria-expanded', 'false');
		button.classList.remove(OPEN_MODIFIER_BUTTON);
		dropdown.classList.remove(OPEN_MODIFIER_DROPDOWN);

		if (focusButton) {
			button.focus();
		}
	}

	function focusItem(index) {
		const safeIndex = Math.max(0, Math.min(index, items.length - 1));
		const target = items[safeIndex];
		if (target) {
			target.focus();
		}
	}
}
