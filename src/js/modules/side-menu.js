/**
 * Side menu push 180°C.
 *
 * Comportement :
 *  - .js-side-menu-toggle (dans le header) ouvre/ferme le panneau
 *  - .js-side-menu-close (dans le panneau) ferme
 *  - Escape ferme
 *  - Click hors du panneau (sur #site-shell) ferme quand ouvert
 *  - body.has-side-menu-open déclenche la translation CSS de #site-shell
 *  - aria-hidden du panneau reflète l'état
 *  - aria-expanded du trigger reflète l'état
 *  - Focus piégé dans le panneau pendant l'ouverture
 *  - Focus retour sur le trigger après fermeture
 *  - Scroll body bloqué (via body.has-side-menu-open + overflow:hidden côté CSS)
 *
 * Sous-listes :
 *  - .site-side-menu__expand toggle son aria-expanded
 *  - le <ul> correspondant via aria-controls voit son attribut hidden enlevé/remis
 */

const BODY_OPEN_CLASS = 'has-side-menu-open';
const FOCUSABLE_SELECTOR = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[tabindex]:not([tabindex="-1"])',
].join(',');

/**
 * Init unique exécuté quand le DOM est prêt.
 *
 * Les ES modules ont un defer implicite, mais selon la position du tag
 * dans la page, document.readyState peut être 'loading' OU déjà 'interactive'
 * (voire 'complete') au moment de l'évaluation. On gère les deux cas.
 */
function whenReady(fn) {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', fn, { once: true });
	} else {
		fn();
	}
}

whenReady(() => {
	const sideMenu = document.getElementById('site-side-menu');
	const trigger = document.querySelector('.js-side-menu-toggle');
	const closeBtn = sideMenu ? sideMenu.querySelector('.js-side-menu-close') : null;
	const shell = document.getElementById('site-shell');

	if (!sideMenu || !trigger) {
		return;
	}

	// Garde d'idempotence portée par le DOM et non par le module.
	//
	// Une double évaluation de l'entrée (deux URLs pour le même fichier, cf. le
	// bloc « Pourquoi les entrées Vite ne portent JAMAIS de ?ver » dans
	// inc/enqueue.php) crée DEUX instances de ce module, chacune avec ses propres
	// variables : un drapeau au niveau module ne verrait rien. Le dataset du
	// trigger, lui, est partagé — deux liaisons y deviennent visibles.
	//
	// Sans cette garde, un clic déclenche `toggle()` deux fois : le panneau
	// s'ouvre puis se referme dans le même event et le bouton paraît mort.
	if (trigger.dataset.sideMenuBound === '1') {
		return;
	}
	trigger.dataset.sideMenuBound = '1';

	bindToggles({ sideMenu, trigger, closeBtn, shell });
	bindExpandables(sideMenu);

	// Couche unique : un autre panneau qui s'ouvre (drawer panier /
	// dropdown compte) ferme le side-menu s'il est ouvert.
	document.addEventListener('180c:close-panels', () => {
		if (isOpen()) {
			close({ sideMenu, trigger });
		}
	});
});

/**
 * Lie les événements d'ouverture/fermeture du panneau.
 */
function bindToggles({ sideMenu, trigger, closeBtn, shell }) {
	trigger.addEventListener('click', (event) => {
		// Empêche que le click bulle jusqu'à #site-shell (où un listener
		// "click-outside-to-close" est attaché) — sans ce stop, le panneau
		// s'ouvrirait puis se refermerait immédiatement dans le même event.
		event.stopPropagation();
		toggle({ sideMenu, trigger });
	});

	if (closeBtn) {
		closeBtn.addEventListener('click', () => close({ sideMenu, trigger }));
	}

	// Escape ferme.
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && isOpen()) {
			close({ sideMenu, trigger });
		}
	});

	// Click sur le shell (hors panneau) ferme — comportement push attendu.
	// Le panneau lui-même est en dehors du shell, donc ses clicks n'arrivent
	// jamais ici. On garde un guard pour le trigger au cas où stopPropagation
	// serait court-circuité par un autre handler.
	if (shell) {
		shell.addEventListener('click', (event) => {
			if (!isOpen()) {
				return;
			}
			if (event.target.closest('.js-side-menu-toggle')) {
				return;
			}
			close({ sideMenu, trigger });
			event.preventDefault();
		});
	}

	// Focus trap dans le panneau.
	sideMenu.addEventListener('keydown', (event) => {
		if (event.key !== 'Tab' || !isOpen()) {
			return;
		}
		trapFocus(event, sideMenu);
	});
}

/**
 * Lie les boutons d'expansion (Articles / Recettes / Boutique).
 */
function bindExpandables(sideMenu) {
	const expanders = sideMenu.querySelectorAll('.site-side-menu__expand');
	expanders.forEach((btn) => {
		btn.addEventListener('click', () => {
			const expanded = btn.getAttribute('aria-expanded') === 'true';
			const targetId = btn.getAttribute('aria-controls');
			const target = targetId ? document.getElementById(targetId) : null;

			btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');

			if (target) {
				if (expanded) {
					target.setAttribute('hidden', '');
				} else {
					target.removeAttribute('hidden');
				}
			}
		});
	});
}

/**
 * Bascule l'état ouvert / fermé.
 */
function toggle({ sideMenu, trigger }) {
	if (isOpen()) {
		close({ sideMenu, trigger });
	} else {
		open({ sideMenu, trigger });
	}
}

/**
 * Ouvre le panneau.
 */
function open({ sideMenu, trigger }) {
	if (isOpen()) {
		return;
	}
	// Couche unique : ferme drawer panier + dropdown compte avant d'ouvrir.
	document.dispatchEvent(new CustomEvent('180c:close-panels'));
	document.body.classList.add(BODY_OPEN_CLASS);
	sideMenu.setAttribute('aria-hidden', 'false');
	trigger.setAttribute('aria-expanded', 'true');

	// Focus le premier élément focusable du panneau (après la transition).
	window.setTimeout(() => {
		const focusables = getFocusables(sideMenu);
		if (focusables.length) {
			focusables[0].focus();
		}
	}, 50);
}

/**
 * Ferme le panneau.
 */
function close({ sideMenu, trigger }) {
	if (!isOpen()) {
		return;
	}
	document.body.classList.remove(BODY_OPEN_CLASS);
	sideMenu.setAttribute('aria-hidden', 'true');
	trigger.setAttribute('aria-expanded', 'false');

	trigger.focus();
}

/**
 * Indique si le panneau est ouvert.
 *
 * @returns {boolean}
 */
function isOpen() {
	return document.body.classList.contains(BODY_OPEN_CLASS);
}

/**
 * Retourne la liste des éléments focusables visibles dans le panneau.
 *
 * @returns {HTMLElement[]}
 */
function getFocusables(sideMenu) {
	const nodes = sideMenu.querySelectorAll(FOCUSABLE_SELECTOR);
	return Array.from(nodes).filter((el) => {
		if (el.closest('[hidden]')) {
			return false;
		}
		return el.offsetParent !== null || el === document.activeElement;
	});
}

/**
 * Piège le focus dans le panneau pendant Tab / Shift+Tab.
 *
 * @param {KeyboardEvent} event
 * @param {HTMLElement} sideMenu
 */
function trapFocus(event, sideMenu) {
	const focusables = getFocusables(sideMenu);
	if (!focusables.length) {
		event.preventDefault();
		return;
	}

	const first = focusables[0];
	const last = focusables[focusables.length - 1];
	const active = document.activeElement;

	if (event.shiftKey && active === first) {
		event.preventDefault();
		last.focus();
	} else if (!event.shiftKey && active === last) {
		event.preventDefault();
		first.focus();
	}
}
