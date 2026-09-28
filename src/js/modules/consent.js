/**
 * Module CMP 180°C — choix binaire Refuser / Accepter.
 *
 * Le visiteur ne se prononce que sur une chose : la mesure d'audience. Une
 * seule catégorie, pas de second niveau, pas de réglage par service.
 *
 * Ce module :
 *   - lit / écrit le cookie de consentement ;
 *   - pousse le `consent update` Consent Mode v2 vers gtag ;
 *   - déclenche `_180c.ga4.load()` ET `_180c.umami.load()` à l'acceptation ;
 *   - purge les cookies GA déjà posés au refus, puis recharge la page ;
 *   - poste la preuve du consentement sur `POST /wp-json/180c/v1/consent` ;
 *   - pilote la modale : piège de focus, Échap = refus ;
 *   - pilote l'interrupteur de la page /cookies/ (shortcode
 *     `[180c_consent_toggle]`), seul endroit où l'on revient sur son choix.
 *
 * Configuration : `window._180cConsent`, imprimé par inc/consent.php en
 * priorité 1 sur `wp_head`. Ce module ne code EN DUR ni le nom du cookie, ni la
 * version, ni la durée : la source unique est PHP.
 *
 * Consent Mode « basic » : ni gtag.js ni le script Umami ne sont injectés avant
 * consentement — inc/analytics/ga4.php et inc/analytics/umami.php ne posent
 * qu'un objet inerte dans le <head>, et seule la CMP appelle leur `load()`.
 *
 * @package 180c
 */

/**
 * Configuration serveur, avec des valeurs de repli identiques aux constantes
 * PHP. Le repli ne sert qu'au cas où le <head> aurait été amputé : il ne doit
 * jamais devenir la source réelle, sous peine de recréer la duplication que
 * inc/consent.php supprime.
 */
const CFG = Object.assign(
	{
		cookie: '_180c_consent',
		version: 2,
		ttlDays: 182,
		endpoint: '',
		policyUrl: '/cookies/',
	},
	window._180cConsent || {}
);

const MODAL_OPEN_CLASS = 'is-modal-open';

/** Éléments focalisables retenus pour le piège de focus. */
const FOCUSABLE =
	'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/** Élément qui avait le focus avant l'ouverture, pour le lui rendre. */
let lastFocused = null;

/**
 * Récupère les préférences stockées en cookie.
 *
 * @returns {Object|null} Objet de consentement, ou null si absent / illisible.
 */
function getStoredConsent() {
	const raw = document.cookie
		.split('; ')
		.find((r) => r.startsWith(CFG.cookie + '='));

	if (!raw) return null;

	try {
		return JSON.parse(decodeURIComponent(raw.slice(CFG.cookie.length + 1)));
	} catch (e) {
		return null;
	}
}

/**
 * Dit si un cookie stocké vaut consentement à la mesure d'audience.
 *
 * Un cookie d'une autre version est traité comme absent : la liste des
 * traceurs a changé depuis, l'accord d'alors ne porte pas sur celle d'aujourd'hui.
 *
 * @param {Object|null} stored
 * @returns {boolean}
 */
function isGranted(stored) {
	return !!stored && stored.v === CFG.version && stored.analytics === true;
}

/**
 * Construit le payload Consent Mode v2 à partir de l'état binaire.
 *
 * Les trois signaux publicitaires sont figés à `denied`, en dur et dans TOUTES
 * les branches : 180°C ne diffuse pas de publicité et ne fait pas de
 * remarketing, donc les accorder ne servirait aucune finalité réelle — c'est
 * exactement ce qu'interdit le principe de minimisation. Accepter la mesure
 * d'audience ne doit pas valoir consentement publicitaire.
 *
 * Ne pas « factoriser » en réutilisant `analytics` pour les quatre clés : la
 * distinction EST le correctif.
 *
 * `functionality_storage` / `security_storage` ne figurent pas ici — strictement
 * nécessaires, ils restent `granted`, posés au `consent default` par
 * inc/analytics/ga4.php.
 *
 * @param {boolean} analytics
 * @returns {Object}
 */
function buildGtagPayload(analytics) {
	return {
		ad_storage: 'denied',
		ad_user_data: 'denied',
		ad_personalization: 'denied',
		analytics_storage: analytics ? 'granted' : 'denied',
	};
}

/**
 * Pousse un `consent update` à gtag (ou bufferise dans dataLayer si gtag absent).
 *
 * NB : gtag.js n'interprète comme commande QUE les entrées `arguments` de
 * dataLayer — un `push([...])` (Array) est ignoré. Le garde-fou reproduit donc
 * le shim officiel plutôt que de pousser un tableau.
 *
 * @param {Object} payload
 */
function pushConsentUpdate(payload) {
	if (typeof window.gtag === 'function') {
		window.gtag('consent', 'update', payload);
		return;
	}

	window.dataLayer = window.dataLayer || [];
	(function () {
		window.dataLayer.push(arguments);
	})('consent', 'update', payload);
}

/**
 * Supprime les cookies de mesure Google déjà déposés (`_ga`, `_ga_XXXX`,
 * `_gid`, `_gat*`).
 *
 * Requis pour que le retrait soit effectif : un `consent update denied` empêche
 * les écritures futures mais ne nettoie pas l'existant.
 *
 * Les cookies GA sont posés sur le domaine racine (`.180c.fr`) : on tente donc
 * l'expiration sur l'hôte courant ET sur chaque domaine parent.
 */
function clearAnalyticsCookies() {
	const names = document.cookie
		.split('; ')
		.map((pair) => pair.split('=')[0])
		.filter((name) => /^_ga($|_)|^_gid$|^_gat/.test(name));

	if (!names.length) return;

	// Hôte courant + domaines parents (a.b.example.com → b.example.com, example.com).
	const parts = location.hostname.split('.');
	const domains = [''];
	for (let i = 0; i < parts.length - 1; i += 1) {
		domains.push('; domain=.' + parts.slice(i).join('.'));
	}

	names.forEach((name) => {
		domains.forEach((domain) => {
			document.cookie = `${name}=; path=/; max-age=0${domain}`;
		});
	});
}

/**
 * Identifiant de consentement, unique et sans lien avec un compte.
 *
 * `crypto.randomUUID` n'existe pas partout (Safari < 15.4, contextes non
 * sécurisés) ; le repli tire les mêmes 122 bits d'aléa via `getRandomValues`.
 * Le dernier repli, `Math.random`, n'est pas cryptographique — il ne sert qu'à
 * ne jamais empêcher un visiteur de donner son avis parce que son navigateur
 * n'expose pas `crypto`.
 *
 * @returns {string} UUID v4 canonique.
 */
function uuid() {
	if (window.crypto && typeof window.crypto.randomUUID === 'function') {
		return window.crypto.randomUUID();
	}

	const bytes = new Uint8Array(16);
	if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
		window.crypto.getRandomValues(bytes);
	} else {
		for (let i = 0; i < 16; i += 1) bytes[i] = Math.floor(Math.random() * 256);
	}

	bytes[6] = (bytes[6] & 0x0f) | 0x40; // Version 4.
	bytes[8] = (bytes[8] & 0x3f) | 0x80; // Variante RFC 4122.

	const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
	return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Écrit le cookie de consentement.
 *
 * @param {Object} record Enregistrement complet ({v, id, ts, analytics}).
 */
function storeConsent(record) {
	const value = encodeURIComponent(JSON.stringify(record));
	const secure = location.protocol === 'https:' ? '; Secure' : '';
	const maxAge = CFG.ttlDays * 24 * 60 * 60;
	document.cookie = `${CFG.cookie}=${value}; path=/; max-age=${maxAge}; SameSite=Lax${secure}`;
}

/**
 * Poste la preuve du consentement.
 *
 * `keepalive` : le refus se termine par un rechargement de page, qui annulerait
 * une requête ordinaire en vol. Sans ce drapeau, les refus seraient
 * systématiquement absents du journal — exactement les preuves qu'on tient le
 * plus à conserver.
 *
 * L'échec est silencieux et sans conséquence : le journal est une preuve, pas
 * un verrou. Le choix du visiteur vit dans son cookie et reste effectif même si
 * le serveur ne répond pas.
 *
 * @param {Object} record Enregistrement complet ({v, id, ts, analytics}).
 */
function postProof(record) {
	if (!CFG.endpoint) return;

	try {
		fetch(CFG.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(record),
			keepalive: true,
			credentials: 'omit',
		}).catch(() => {});
	} catch (e) {
		/* Rien à faire : la preuve est un plus, jamais une condition. */
	}
}

/* ============================================================
 * Modale — ouverture, fermeture, piège de focus
 * ============================================================ */

/**
 * Retourne les éléments focalisables actuellement VISIBLES de la modale.
 *
 * Le filtre sur `offsetParent` n'est pas cosmétique : la modale porte deux
 * pieds de page mutuellement exclusifs (choix initial / gestion) et un bloc
 * d'état dégradé. Sans ce filtre, le piège enfermerait le focus sur des boutons
 * masqués, ce qui est pire que pas de piège du tout.
 *
 * @param {HTMLElement} modal
 * @returns {HTMLElement[]}
 */
function focusableIn(modal) {
	return [...modal.querySelectorAll(FOCUSABLE)].filter(
		(el) => el.offsetParent !== null || el === document.activeElement
	);
}

/**
 * Confine le focus dans la modale.
 *
 * Sans ce piège, la tabulation quittait la modale dès la première pression :
 * un visiteur au clavier ou au lecteur d'écran pouvait parcourir tout le site
 * sans jamais atteindre les deux boutons. Le blocage n'était que visuel, et
 * `aria-modal="true"` promettait ce que la navigation démentait.
 *
 * @param {KeyboardEvent} e
 */
function trapFocus(e) {
	const modal = document.getElementById('consent');
	if (!modal || modal.hasAttribute('hidden')) return;

	if (e.key === 'Escape') {
		// Échap vaut refus, exactement comme un clic sur « Refuser ». Fermer
		// sans rien décider laisserait le visiteur sans choix ET sans modale.
		e.preventDefault();
		setConsent(false);
		return;
	}

	if (e.key !== 'Tab') return;

	const items = focusableIn(modal);
	if (!items.length) return;

	const first = items[0];
	const last = items[items.length - 1];

	// Le focus a pu s'échapper (clic dans la page, restauration navigateur) :
	// on le ramène plutôt que de laisser filer la tabulation suivante.
	if (!modal.contains(document.activeElement)) {
		e.preventDefault();
		first.focus();
		return;
	}

	if (e.shiftKey && document.activeElement === first) {
		e.preventDefault();
		last.focus();
	} else if (!e.shiftKey && document.activeElement === last) {
		e.preventDefault();
		first.focus();
	}
}

/**
 * Affiche la modale, verrouille le scroll, arme le piège de focus.
 *
 * La modale ne sert qu'au choix initial : elle ne contient aucune case à
 * cocher, donc rien à pré-remplir. La modification après coup se fait sur la
 * page /cookies/.
 */
function openModal() {
	const modal = document.getElementById('consent');
	if (!modal) return;

	lastFocused = document.activeElement;

	modal.removeAttribute('hidden');
	document.documentElement.classList.add(MODAL_OPEN_CLASS);

	document.addEventListener('keydown', trapFocus, true);

	// Focus initial sur le titre plutôt que sur un bouton : un lecteur d'écran
	// annonce ainsi le sujet avant les actions, et aucune des deux options n'est
	// mise en avant par la position du focus.
	const title = modal.querySelector('.consent__title');
	if (title) {
		title.setAttribute('tabindex', '-1');
		title.focus();
	}
}

/**
 * Cache la modale, restaure le scroll et le focus, désarme le piège.
 */
function closeModal() {
	const modal = document.getElementById('consent');
	if (modal) modal.setAttribute('hidden', '');
	document.documentElement.classList.remove(MODAL_OPEN_CLASS);

	document.removeEventListener('keydown', trapFocus, true);

	if (lastFocused && typeof lastFocused.focus === 'function') {
		lastFocused.focus();
		lastFocused = null;
	}
}

/* ============================================================
 * Décision
 * ============================================================ */

/**
 * Applique et mémorise un choix.
 *
 * @param {boolean} analytics Vrai si la mesure d'audience est acceptée.
 */
function setConsent(analytics) {
	const previous = isGranted(getStoredConsent());

	const record = {
		v: CFG.version,
		id: uuid(),
		ts: new Date().toISOString(),
		analytics: !!analytics,
	};

	// Le cookie est écrit AVANT le chargement des tags : gtag.js le lit pour son
	// propre état, et un rechargement repart sur le bon `consent default`.
	storeConsent(record);
	pushConsentUpdate(buildGtagPayload(record.analytics));
	postProof(record);

	document.dispatchEvent(
		new CustomEvent('consent:updated', { detail: record })
	);

	if (record.analytics) {
		window._180c?.ga4?.load?.();
		window._180c?.umami?.load?.();
		closeModal();
		showSaved();
		return;
	}

	clearAnalyticsCookies();

	// Retrait d'un consentement précédemment donné : rechargement obligatoire.
	// Un `consent update denied` empêche gtag d'écrire à nouveau, mais gtag.js
	// et le script Umami restent chargés et vivants dans la page — Umami
	// n'ayant, lui, aucune notion de Consent Mode, rien ne l'arrête. Seul un
	// document neuf, où ni l'un ni l'autre n'est injecté, rend le retrait réel.
	if (previous) {
		location.reload();
		return;
	}

	closeModal();
	showSaved();
}

/**
 * Révèle la confirmation d'enregistrement de la page /cookies/.
 *
 * Sur la page, contrairement à la modale qui se ferme, rien ne bouge quand on
 * enregistre : sans ce message, le visiteur ne sait pas si son clic a porté.
 * Elle n'existe que dans le markup du shortcode — ailleurs, cette fonction ne
 * fait rien.
 *
 * Le cas du RETRAIT n'arrive jamais ici : il recharge la page avant d'y
 * parvenir, et le rechargement est en lui-même la confirmation visible.
 */
function showSaved() {
	const status = document.querySelector('[data-consent-saved]');
	if (status) status.hidden = false;
}

/**
 * Aligne l'interrupteur de la page /cookies/ sur le choix mémorisé.
 *
 * Le markup le rend systématiquement décoché : l'état ne peut pas être écrit
 * côté serveur sans figer le choix du premier visiteur dans le cache page pour
 * tous les suivants. C'est donc ici, et seulement ici, qu'il prend sa valeur.
 */
function syncPageToggle() {
	const toggle = document.querySelector('[data-consent-toggle]');
	if (toggle) toggle.checked = isGranted(getStoredConsent());
}

/* ============================================================
 * Init
 * ============================================================ */

/**
 * Vérifie le cookie et ouvre la modale ou applique le choix mémorisé.
 */
function init() {
	// Désarme le chien de garde du script inline de parts/consent-modal.php :
	// ce module a pris la main, la modale affichée est pilotable.
	document.documentElement.dataset.consentReady = '1';

	// L'interrupteur de la page reflète le choix courant, y compris quand aucun
	// choix valide n'existe encore — il part alors décoché, ce qui est exact.
	syncPageToggle();

	const stored = getStoredConsent();

	if (stored && stored.v === CFG.version) {
		const granted = isGranted(stored);
		pushConsentUpdate(buildGtagPayload(granted));
		if (granted) {
			window._180c?.ga4?.load?.();
			window._180c?.umami?.load?.();
		}
		return;
	}

	// Cookie absent, illisible, ou d'une version antérieure : choix forcé.
	openModal();
}

/* ============================================================
 * Écoutes
 * ============================================================ */

document.addEventListener('click', (e) => {
	if (e.target.closest('[data-action="consent-decline"]')) {
		e.preventDefault();
		setConsent(false);
		return;
	}

	if (e.target.closest('[data-action="consent-accept"]')) {
		e.preventDefault();
		setConsent(true);
		return;
	}

	// Enregistrement depuis la page /cookies/ : l'état vient de l'interrupteur.
	if (e.target.closest('[data-action="consent-save"]')) {
		e.preventDefault();
		const toggle = document.querySelector('[data-consent-toggle]');
		setConsent(!!(toggle && toggle.checked));
		return;
	}

	/*
	 * Repli de compatibilité. `open-consent-settings` ouvrait la modale en mode
	 * gestion ; ce mode n'existe plus, la gestion se faisant sur la page. Le
	 * thème n'émet plus cet attribut, mais le contenu de la page /cookies/ vit
	 * en base et peut encore le porter tant qu'il n'a pas été mis à jour à la
	 * main. Sans ce repli, ce bouton — dont le `href` vaut « # » — ne ferait
	 * plus rien du tout entre le déploiement et l'édition de la page.
	 */
	const legacy = e.target.closest('[data-action="open-consent-settings"]');
	if (legacy) {
		const target = CFG.policyUrl;
		if (target && !location.href.startsWith(target)) {
			e.preventDefault();
			location.href = target;
		}
	}
});

document.addEventListener('DOMContentLoaded', init);

export { getStoredConsent, isGranted, setConsent, openModal, closeModal };
