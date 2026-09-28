/**
 * Mini-panier 180°C.
 *
 * Module vanilla, délégation d'événements (résiste au remplacement des
 * fragments WooCommerce). Trois responsabilités :
 *  1. Contrôleur « couche unique » : ouvrir le drawer ferme le dropdown
 *     compte + le side-menu (et inversement) via l'event `180c:close-panels`.
 *  2. Add-to-cart AJAX (fiche + boucles) → ouverture auto du drawer.
 *  3. Édition quantité / suppression en AJAX dans le drawer.
 *
 * Endpoints : window._180cCart (exposé par inc/woo/cart.php). Le nonce, lui,
 * vient de GET /180c/v1/session (session.js) — il n'est plus inliné dans le
 * HTML, qui est mis en cache et lui survivrait.
 */

import { showToast } from './toast.js';
import { cartNonce, ensureSession, refreshSession } from './session.js';

const CLOSE_PANELS_EVENT = '180c:close-panels';
const CART_UPDATED_EVENT = '180c:cart-updated';
const BODY_OPEN_CLASS = 'has-cart-drawer-open';

let drawer = null;
let panel = null;
let lastFocused = null;

/**
 * Exécute fn quand le DOM est prêt (defer implicite des ES modules + garde).
 *
 * @param {Function} fn
 */
function whenReady(fn) {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', fn, { once: true });
	} else {
		fn();
	}
}

whenReady(init);

/**
 * Initialise le module : références + listeners délégués.
 */
function init() {
	drawer = document.getElementById('cart-drawer');
	if (!drawer) {
		return;
	}
	panel = drawer.querySelector('.cart-drawer__panel');

	// Couche unique : un autre panneau qui s'ouvre ferme le drawer.
	document.addEventListener(CLOSE_PANELS_EVENT, () => closeDrawer({ restoreFocus: false }));

	// Toggle de l'icône header (délégué : le bouton est remplacé par fragment).
	document.addEventListener('click', (event) => {
		const toggle = event.target.closest('.js-cart-toggle');
		if (!toggle) {
			return;
		}
		event.preventDefault();
		if (isOpen()) {
			closeDrawer();
		} else {
			openDrawer();
		}
	});

	// Fermeture : croix + overlay (la zone défilante du corps ne ferme pas).
	drawer.addEventListener('click', (event) => {
		if (event.target.closest('.js-cart-close') || event.target.closest('.js-cart-overlay')) {
			closeDrawer();
		}
	});

	// Escape ferme.
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && isOpen()) {
			closeDrawer();
		}
	});

	// Focus-trap : Tab / Shift+Tab bouclent dans le panneau tant qu'il est ouvert.
	drawer.addEventListener('keydown', (event) => {
		if (event.key === 'Tab' && isOpen()) {
			trapFocus(event);
		}
	});

	bindAddToCart();
	bindMutations();
	initCartPage();
}

/**
 * Lie les mutations dans le drawer (qté +/− et suppression), en délégation
 * sur le wrapper qui persiste au remplacement du corps par fragment.
 */
function bindMutations() {
	drawer.addEventListener('click', (event) => {
		const cfg = getConfig();
		if (!cfg) {
			return;
		}
		const line = event.target.closest('.cart-line');
		if (!line) {
			return;
		}
		const key = line.getAttribute('data-cart-item-key');
		if (!key || line.dataset.busy === '1') {
			return;
		}

		// Suppression.
		if (event.target.closest('.js-cart-remove')) {
			event.preventDefault();
			mutate(cfg.endpoints.remove, { cart_item_key: key }, line);
			return;
		}

		// Quantité +/−.
		const inc = event.target.closest('.js-cart-qty-inc');
		const dec = event.target.closest('.js-cart-qty-dec');
		if (inc || dec) {
			event.preventDefault();
			const qtyEl = line.querySelector('[data-cart-qty]');
			const current = qtyEl ? parseInt(qtyEl.textContent, 10) || 0 : 0;
			const next = inc ? current + 1 : current - 1;
			mutate(cfg.endpoints.set_qty, { cart_item_key: key, quantity: next }, line);
		}
	});
}

/**
 * Page panier (/panier/) : steppers +/− et suppression en AJAX, hors drawer.
 *
 * Réutilise les endpoints set_qty / remove (mêmes que le drawer) avec un flag
 * `context=cart_page` : le serveur renvoie alors aussi le fragment `.cart_totals`
 * et le sous-total de la ligne mutée. Délégation sur le formulaire panier (le
 * tableau survit, seules les lignes/totaux changent).
 */
function initCartPage() {
	const form = document.querySelector('.woocommerce-cart-form');
	if (!form) {
		return;
	}
	// Les steppers AJAX rendent le bouton « Mettre à jour » superflu : on le
	// masque uniquement quand le JS est actif (fallback no-JS préservé).
	// Inline style (et non [hidden]) car .btn force display:inline-flex.
	const updateBtn = form.querySelector('[name="update_cart"]');
	if (updateBtn) {
		updateBtn.style.display = 'none';
	}
	form.addEventListener('click', onCartPageClick);
}

/**
 * @param {MouseEvent} event
 */
function onCartPageClick(event) {
	const cfg = getConfig();
	if (!cfg) {
		return;
	}

	const row = event.target.closest('[data-cart-item-key]');
	if (!row || row.dataset.busy === '1') {
		return;
	}
	const key = row.getAttribute('data-cart-item-key');
	if (!key) {
		return;
	}

	// Suppression (icône poubelle / lien remove).
	if (event.target.closest('.cart-table__remove')) {
		event.preventDefault();
		cartPageMutate(cfg.endpoints.remove, { cart_item_key: key }, row, true);
		return;
	}

	// Stepper +/−.
	const inc = event.target.closest('[data-qty-plus]');
	const dec = event.target.closest('[data-qty-minus]');
	if (!inc && !dec) {
		return;
	}
	event.preventDefault();

	const field = row.querySelector('.qty-input__field');
	if (!field) {
		return;
	}
	const step = parseInt(field.getAttribute('step'), 10) || 1;
	const minAttr = parseInt(field.getAttribute('min'), 10);
	const maxAttr = parseInt(field.getAttribute('max'), 10);
	// Plancher du stepper à 1 (la suppression passe par la corbeille, pas par 0).
	const lower = Number.isFinite(minAttr) ? Math.max(minAttr, 1) : 1;
	const current = parseInt(field.value, 10) || lower;
	let next = inc ? current + step : current - step;
	if (next < lower) {
		next = lower;
	}
	if (Number.isFinite(maxAttr) && next > maxAttr) {
		next = maxAttr;
	}
	if (next === current) {
		return;
	}
	field.value = String(next); // optimiste ; le serveur fait foi pour les totaux.
	cartPageMutate(cfg.endpoints.set_qty, { cart_item_key: key, quantity: next }, row, false);
}

/**
 * Mutation AJAX d'une ligne de la page panier (qté / suppression).
 *
 * @param {string}      url
 * @param {Object}      params
 * @param {HTMLElement} row
 * @param {boolean}     isRemove
 */
async function cartPageMutate(url, params, row, isRemove) {
	const cfg = getConfig();
	if (!cfg) {
		return;
	}
	row.dataset.busy = '1';
	row.classList.add('is-loading');

	const formData = new FormData();
	Object.keys(params).forEach((k) => formData.append(k, params[k]));
	formData.append('context', 'cart_page');

	try {
		const data = await postCart(url, formData);
		if (!data || data.error) {
			showToast({ message: (data && data.message) || 'La mise à jour du panier a échoué. Réessayez.' });
			return;
		}
		// Sous-total de ligne (avant suppression éventuelle de la ligne).
		if (!isRemove && data.line_subtotal != null) {
			const cell = row.querySelector('.cart-table__subtotal');
			if (cell) {
				cell.innerHTML = data.line_subtotal;
			}
		}
		// Fragments : icône header + corps drawer + .cart_totals (totaux frais).
		replaceFragments(data.fragments);
		if (isRemove) {
			row.remove();
		}
		// Panier vidé → recharge pour afficher le template panier vide.
		if (data.cart_empty) {
			window.location.reload();
			return;
		}
		document.dispatchEvent(new CustomEvent(CART_UPDATED_EVENT, { detail: { cartHash: data.cart_hash } }));
	} catch (err) {
		showToast({ message: 'La mise à jour du panier a échoué. Réessayez.' });
	} finally {
		if (row.isConnected) {
			row.dataset.busy = '';
			row.classList.remove('is-loading');
		}
	}
}

/**
 * Exécute une mutation panier (set_qty / remove) avec garde anti double-clic.
 *
 * Au succès, le corps du drawer est remplacé par le fragment (la ligne
 * disparaît) ; si le panier est vidé, l'état vide s'affiche et l'icône header
 * disparaît (fragment). Le drawer reste ouvert.
 *
 * @param {string}      url
 * @param {Object}      params
 * @param {HTMLElement} line
 */
async function mutate(url, params, line) {
	const cfg = getConfig();
	if (!cfg) {
		return;
	}
	line.dataset.busy = '1';
	line.classList.add('is-loading');

	const formData = new FormData();
	Object.keys(params).forEach((key) => formData.append(key, params[key]));

	try {
		const data = await postCart(url, formData);
		applyResult(data, { open: false });
	} catch (err) {
		showToast({ message: 'La mise à jour du panier a échoué. Réessayez.' });
	} finally {
		// La ligne est remplacée au succès ; sinon on lève la garde.
		if (line.isConnected) {
			line.dataset.busy = '';
			line.classList.remove('is-loading');
		}
	}
}

/**
 * Éléments focusables visibles du panneau.
 *
 * @returns {HTMLElement[]}
 */
function getFocusables() {
	if (!panel) {
		return [];
	}
	const selector = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])',
	].join(',');
	return Array.from(panel.querySelectorAll(selector)).filter(
		(el) => el.offsetParent !== null || el === document.activeElement
	);
}

/**
 * Piège le focus dans le panneau (le panneau lui-même = point de départ).
 *
 * @param {KeyboardEvent} event
 */
function trapFocus(event) {
	const focusables = getFocusables();
	if (!focusables.length) {
		event.preventDefault();
		if (panel) {
			panel.focus();
		}
		return;
	}
	const first = focusables[0];
	const last = focusables[focusables.length - 1];
	const active = document.activeElement;

	if (event.shiftKey && (active === first || active === panel)) {
		event.preventDefault();
		last.focus();
	} else if (!event.shiftKey && active === last) {
		event.preventDefault();
		first.focus();
	}
}

/**
 * Config injectée par PHP (endpoints wc-ajax). Null si WooCommerce
 * absent → on ne touche à rien (comportement natif conservé).
 *
 * @returns {{endpoints:Object}|null}
 */
function getConfig() {
	return window._180cCart && window._180cCart.endpoints ? window._180cCart : null;
}

/**
 * Remplace les fragments WooCommerce (outerHTML) ciblés par sélecteur.
 *
 * @param {Object<string,string>} fragments
 */
function replaceFragments(fragments) {
	if (!fragments) {
		return;
	}
	Object.keys(fragments).forEach((selector) => {
		document.querySelectorAll(selector).forEach((el) => {
			const tmp = document.createElement('div');
			tmp.innerHTML = String(fragments[selector]).trim();
			const fresh = tmp.firstElementChild;
			if (fresh) {
				el.replaceWith(fresh);
			}
		});
	});
}

/**
 * POST vers un endpoint wc-ajax et parse la réponse JSON.
 *
 * @param {string}   url
 * @param {FormData} formData
 * @returns {Promise<Object>}
 */
async function postCart(url, formData) {
	// Point unique où le nonce entre dans la requête. Le HTML mis en cache ne
	// le porte plus : `ensureSession()` le récupère au premier envoi de la page
	// (et sort sans requête si un déclencheur d'intention l'a déjà amorcé).
	// Le poser ici plutôt qu'à la construction des quatre FormData garantit
	// qu'aucun chemin d'appel ne puisse l'oublier.
	await ensureSession();
	formData.set('nonce', cartNonce());

	const send = () =>
		fetch(url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		});

	let res = await send();

	// 403 = nonce refusé par _180c_cart_ajax_guard(). Sur une page servie
	// depuis le cache page, le nonce inliné peut avoir plus de 24 h : on en
	// récupère un frais et on rejoue UNE fois. Sans ce rejeu, le visiteur
	// reçoit « Session expirée, rechargez la page » sur un ajout au panier
	// parfaitement légitime.
	if (res.status === 403 && (await refreshSession())) {
		formData.set('nonce', cartNonce());
		res = await send();
	}

	return res.json();
}

/**
 * Applique le résultat d'une mutation : erreur → toast ; succès → fragments
 * + event `180c:cart-updated`.
 *
 * @param {Object}  data
 * @param {Object}  [opts]
 * @param {boolean} [opts.open=false] Ouvrir le drawer au succès.
 * @returns {boolean} true si succès.
 */
function applyResult(data, { open = false } = {}) {
	if (!data || data.error) {
		showToast({ message: (data && data.message) || 'Une erreur est survenue. Réessayez.' });
		return false;
	}
	replaceFragments(data.fragments);
	document.dispatchEvent(new CustomEvent(CART_UPDATED_EVENT, { detail: { cartHash: data.cart_hash } }));
	if (open) {
		openDrawer();
	}
	return true;
}

/**
 * Lie l'add-to-cart AJAX : submit de form.cart (fiche) + clic boucle.
 */
function bindAddToCart() {
	// Fiche produit : interception du submit (gère quantité + variations).
	document.addEventListener('submit', (event) => {
		const form = event.target;
		const cfg = getConfig();
		if (!cfg || !form.matches || !form.matches('form.cart')) {
			return;
		}
		// Exclusions : abonnement (redirect natif vers checkout) + opt-out.
		if (
			form.closest('[data-no-ajax-cart]') ||
			form.closest('[class*="product-type-subscription"]')
		) {
			return;
		}
		event.preventDefault();

		const submitter = event.submitter;
		const formData = new FormData(form);
		// Inclut le name/value du bouton cliqué (ex. add-to-cart=ID) que le
		// constructeur FormData omet quand le submitter n'est pas passé.
		if (submitter && submitter.name && !formData.has(submitter.name)) {
			formData.append(submitter.name, submitter.value);
		}

		const button = form.querySelector('.single_add_to_cart_button');
		runAdd(cfg.endpoints.add, formData, button);
	});

	// Boucles (rails / boutique) : clic sur le bouton AJAX WooCommerce.
	document.addEventListener('click', (event) => {
		const button = event.target.closest('.add_to_cart_button');
		const cfg = getConfig();
		if (!button || !cfg) {
			return;
		}
		// Variables / groupés → page produit ; abonnement / opt-out → natif.
		if (
			button.classList.contains('product_type_variable') ||
			button.classList.contains('product_type_grouped') ||
			button.matches('[data-no-ajax-cart]') ||
			button.closest('[data-no-ajax-cart]')
		) {
			return;
		}

		const productId = button.dataset.product_id || addToCartParam(button.getAttribute('href'));
		if (!productId) {
			return;
		}
		event.preventDefault();

		const formData = new FormData();
		formData.append('product_id', productId);
		formData.append('quantity', button.dataset.quantity || '1');

		runAdd(cfg.endpoints.add, formData, button);
	});
}

/**
 * Extrait l'ID `add-to-cart` d'une URL (fallback quand pas de data-product_id).
 *
 * @param {string|null} href
 * @returns {string}
 */
function addToCartParam(href) {
	if (!href) {
		return '';
	}
	const match = href.match(/[?&]add-to-cart=(\d+)/);
	return match ? match[1] : '';
}

/**
 * Exécute l'ajout AJAX avec état de chargement sur le bouton et ouverture
 * automatique du drawer au succès.
 *
 * @param {string}      url
 * @param {FormData}    formData
 * @param {HTMLElement} [button]
 */
/**
 * Émet l'event GA4 add_to_cart (L3) à partir du payload `ga4_add` renvoyé par
 * l'endpoint d'ajout. Consent-gated en amont (gtag absent si refus). Une seule
 * émission par ajout (un appel runAdd = un ajout). cf TRACKING_PLAN.md §4.1.
 *
 * @param {Object} data Réponse JSON de l'ajout au panier.
 */
function trackAddToCart(data) {
	if (!data || !data.ga4_add) {
		return;
	}
	window._180c?.ga4?.event?.('add_to_cart', data.ga4_add);
}

async function runAdd(url, formData, button) {
	if (button) {
		button.classList.add('loading');
		button.setAttribute('aria-busy', 'true');
	}
	try {
		const data = await postCart(url, formData);
		applyResult(data, { open: true });
		trackAddToCart(data);
	} catch (err) {
		showToast({ message: "L'ajout au panier a échoué. Vérifiez votre connexion." });
	} finally {
		if (button) {
			button.classList.remove('loading');
			button.removeAttribute('aria-busy');
		}
	}
}

/**
 * Indique si le drawer est ouvert.
 *
 * @returns {boolean}
 */
function isOpen() {
	return !!drawer && drawer.getAttribute('aria-hidden') === 'false';
}

/**
 * Reflète l'état ouvert/fermé sur l'aria-expanded du bouton header.
 *
 * @param {boolean} expanded
 */
function setToggleExpanded(expanded) {
	const toggle = document.querySelector('.js-cart-toggle');
	if (toggle) {
		toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
	}
}

/**
 * Ouvre le drawer. Ferme d'abord les autres panneaux (couche unique).
 */
function openDrawer() {
	if (!drawer || isOpen()) {
		return;
	}
	lastFocused = document.activeElement;

	// Mutual exclusivity : prévient dropdown compte + side-menu.
	document.dispatchEvent(new CustomEvent(CLOSE_PANELS_EVENT));

	drawer.setAttribute('aria-hidden', 'false');
	document.body.classList.add(BODY_OPEN_CLASS);
	setToggleExpanded(true);

	if (panel) {
		window.requestAnimationFrame(() => panel.focus());
	}
}

/**
 * Ferme le drawer.
 *
 * @param {Object}  [opts]
 * @param {boolean} [opts.restoreFocus=true] Rendre le focus au déclencheur.
 */
function closeDrawer({ restoreFocus = true } = {}) {
	if (!drawer || !isOpen()) {
		return;
	}
	drawer.setAttribute('aria-hidden', 'true');
	document.body.classList.remove(BODY_OPEN_CLASS);
	setToggleExpanded(false);

	if (restoreFocus && lastFocused && typeof lastFocused.focus === 'function') {
		lastFocused.focus();
	}
	lastFocused = null;
}

export { openDrawer, closeDrawer, isOpen, CART_UPDATED_EVENT, CLOSE_PANELS_EVENT };
