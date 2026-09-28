/**
 * Module — Onboarding abonné (modale de bienvenue, page « order-received »).
 *
 * Branche le carousel server-rendu (template-parts/onboarding/carousel.php) :
 *  - déplace le bloc en enfant de <body> (hors #site-shell, dont le
 *    will-change:transform piégerait le position:fixed de la modale) ;
 *  - le passe en overlay modal (role="dialog", aria-modal), masqué jusqu'à
 *    ouverture ;
 *  - révèle le bouton « Découvrir mon abonnement » (rendu sous le récap) ;
 *  - gère navigation (Passer/Précédent ↔ Suivant/Découvrir + flèches ←/→),
 *    progression (« Étape X / 5 » + barre segmentée), focus piégé, Esc, verrou
 *    de scroll, restitution du focus au bouton, QR codes, astuce favoris.
 *
 * Enhancement progressif : sans JS, le bloc reste empilé et lisible sous le
 * récap et le bouton de lancement reste masqué. Aucune API de bookmark
 * (window.sidebar / window.external) — supprimées des navigateurs.
 *
 * @module onboarding
 */

import qrcode from 'qrcode-generator';

let root = null;
let panel = null;
let total = 5;
let current = 1;
let statusRegion = null;
let stepLabel = null;
let prevBtn = null;
let nextBtn = null;
let segments = [];
let headerEyebrow = null;
let launchBtn = null;
let lastFocus = null;

/**
 * Écrit une annonce dans la région aria-live.
 *
 * @param {string} message
 */
function announce(message) {
  if (statusRegion) {
    statusRegion.textContent = message;
  }
}

/**
 * Rend un QR code SVG (scalable) dans un conteneur [data-onboarding-qr] à partir
 * de son attribut data-qr-url. Correction d'erreur « M ». SVG issu de la lib et
 * d'une URL contrôlée côté serveur.
 *
 * @param {HTMLElement} el
 */
function renderQr(el) {
  const url = el.dataset.qrUrl;
  if (!url) {
    return;
  }

  const qr = qrcode(0, 'M');
  qr.addData(url);
  qr.make();

  el.innerHTML = qr.createSvgTag({ scalable: true, margin: 0 });

  const svg = el.querySelector('svg');
  if (svg) {
    svg.setAttribute('role', 'img');
    svg.setAttribute('focusable', 'false');
  }
}

/**
 * Met à jour la progression (label + segments).
 */
function updateProgress() {
  if (stepLabel) {
    stepLabel.textContent = `Étape ${current} / ${total}`;
  }

  segments.forEach((segment, index) => {
    segment.classList.toggle('is-active', index < current);
  });
}

/**
 * Met à jour les libellés des boutons de navigation selon l'écran courant.
 */
function updateNav() {
  if (prevBtn) {
    prevBtn.textContent = current === 1 ? 'Passer' : 'Précédent';
  }
  if (nextBtn) {
    nextBtn.textContent =
      current === total ? 'Découvrir les recettes' : 'Suivant';
  }
}

/**
 * Affiche l'écran n, déplace le focus sur son titre et annonce l'étape.
 *
 * @param {number} n
 */
function showScreen(n) {
  current = Math.min(Math.max(n, 1), total);

  let activeScreen = null;
  root.querySelectorAll('[data-onboarding-screen]').forEach((screen) => {
    const isActive =
      parseInt(screen.dataset.onboardingScreen, 10) === current;
    screen.classList.toggle('is-active', isActive);
    if (isActive) {
      activeScreen = screen;
    }
  });

  // Reporte l'eyebrow de l'écran courant sur le rail d'en-tête de la modale.
  if (headerEyebrow && activeScreen) {
    const eyebrow = activeScreen.querySelector('._180c-onboarding__eyebrow');
    headerEyebrow.textContent = eyebrow ? eyebrow.textContent.trim() : '';
  }

  updateProgress();
  updateNav();

  const title = document.getElementById(`_180c-onboarding-title-${current}`);
  if (title) {
    title.focus({ preventScroll: true });
    announce(`Écran ${current} sur ${total} : ${title.textContent.trim()}`);
  }
}

/**
 * Avance d'un écran (sans déborder du dernier).
 */
function goNext() {
  if (current < total) {
    showScreen(current + 1);
  }
}

/**
 * Recule d'un écran (sans déborder du premier).
 */
function goPrev() {
  if (current > 1) {
    showScreen(current - 1);
  }
}

/**
 * Éléments focusables actuellement visibles dans le panneau (piège à focus).
 *
 * @returns {HTMLElement[]}
 */
function focusables() {
  const selector =
    'a[href], button:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';
  return Array.from(panel.querySelectorAll(selector)).filter(
    (el) => el.offsetParent !== null
  );
}

/**
 * Piège le focus dans le panneau (Tab / Shift+Tab cyclent).
 *
 * @param {KeyboardEvent} event
 */
function trapFocus(event) {
  const items = focusables();
  if (!items.length) {
    return;
  }

  const first = items[0];
  const last = items[items.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

/**
 * Ouvre la modale : mémorise le focus, déverrouille l'affichage, verrouille le
 * scroll du body, affiche l'écran 1 (qui prend le focus).
 */
function openModal() {
  lastFocus = document.activeElement;
  root.hidden = false;
  document.body.classList.add('has-onboarding-open');
  showScreen(1);
}

/**
 * Ferme la modale : masque, déverrouille le scroll, rend le focus au bouton de
 * lancement.
 */
function closeModal() {
  root.hidden = true;
  document.body.classList.remove('has-onboarding-open');
  if (lastFocus && typeof lastFocus.focus === 'function') {
    lastFocus.focus();
  }
}

/**
 * Clavier (modale ouverte) : Esc ferme, Tab piège le focus, ←/→ naviguent.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
  if (root.hidden) {
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    closeModal();
    return;
  }

  if (event.key === 'Tab') {
    trapFocus(event);
    return;
  }

  const tag = event.target.tagName;
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
    return;
  }

  if (event.key === 'ArrowRight') {
    event.preventDefault();
    goNext();
  } else if (event.key === 'ArrowLeft') {
    event.preventDefault();
    goPrev();
  }
}

/**
 * Clic sur « Passer / Précédent » : écran 1 ferme la modale, sinon recule.
 */
function onPrev() {
  if (current === 1) {
    closeModal();
  } else {
    goPrev();
  }
}

/**
 * Clic sur « Suivant / Découvrir les recettes » : dernier écran → sortie vers
 * /recettes/, sinon avance.
 */
function onNext() {
  if (current === total) {
    const url = nextBtn ? nextBtn.dataset.onboardingRecipesUrl : '';
    if (url) {
      window.location.href = url;
    }
    return;
  }
  goNext();
}

/**
 * Point d'entrée du module.
 */
export function init() {
  root = document.querySelector('[data-onboarding]');
  if (!root) {
    return;
  }

  panel = root.querySelector('[data-onboarding-panel]');
  total = parseInt(root.dataset.onboardingTotal, 10) || 5;
  statusRegion = root.querySelector('._180c-onboarding__status');
  stepLabel = root.querySelector('[data-onboarding-step-label]');
  prevBtn = root.querySelector('[data-onboarding-prev]');
  nextBtn = root.querySelector('[data-onboarding-next]');
  segments = Array.from(root.querySelectorAll('[data-onboarding-segment]'));

  // Déplacement hors #site-shell (will-change:transform → containing block qui
  // piégerait le position:fixed du scrim). Cf. main.css « #site-shell ».
  document.body.appendChild(root);

  // Bascule en mode modale (JS), fermée par défaut.
  root.classList.remove('no-js');
  root.classList.add('js');
  root.hidden = true;
  root.setAttribute('role', 'dialog');
  root.setAttribute('aria-modal', 'true');

  const nav = root.querySelector('[data-onboarding-nav]');
  if (nav) {
    nav.hidden = false;
  }

  const header = root.querySelector('[data-onboarding-header]');
  if (header) {
    header.hidden = false;
  }
  headerEyebrow = root.querySelector('[data-onboarding-rail-eyebrow]');

  const closeBtn = root.querySelector('[data-onboarding-close]');
  if (closeBtn) {
    closeBtn.addEventListener('click', closeModal);
  }

  root.querySelectorAll('[data-onboarding-qr]').forEach(renderQr);

  if (prevBtn) {
    prevBtn.addEventListener('click', onPrev);
  }
  if (nextBtn) {
    nextBtn.addEventListener('click', onNext);
  }

  // Bouton de lancement (rendu sous le récap, hors du bloc onboarding).
  launchBtn = document.querySelector('[data-onboarding-launch]');
  if (launchBtn) {
    const wrap = launchBtn.closest('._180c-onboarding-launch');
    if (wrap) {
      wrap.hidden = false;
    }
    launchBtn.addEventListener('click', openModal);
  }

  // Esc / Tab / flèches : géré au niveau document, neutralisé si modale fermée.
  // Le clic sur le scrim ne ferme volontairement PAS (cible âgée).
  document.addEventListener('keydown', onKeydown);

  // Pré-positionne l'écran 1 (état latent, la modale reste masquée).
  showScreen(1);
}
