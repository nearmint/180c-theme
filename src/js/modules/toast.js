/**
 * Toast — notification éphémère réutilisable (composant unique).
 *
 * Un seul toast actif à la fois : un nouvel appel remplace le précédent (pas
 * d'empilement). Supporte une action optionnelle `{ label, href }` rendue en
 * vrai lien focusable, et une croix de fermeture manuelle.
 *
 * A11y :
 *   - le message vit dans une région `role="status"` / `aria-live="polite"`
 *     (texte injecté après insertion DOM pour une annonce fiable) ;
 *   - le lien d'action et la croix sont HORS de la zone re-annoncée (pas de
 *     double lecture) ;
 *   - aucun vol de focus ;
 *   - auto-dismiss ~6 s, en PAUSE au survol/focus (WCAG 2.2.1), reprise ensuite.
 *
 * @module toast
 */

const AUTO_DISMISS_MS = 6000;

let region = null;
let active = null; // { root, timerId, remaining, startedAt }

/**
 * Retourne (en la créant au besoin) la région conteneur des toasts.
 *
 * @returns {HTMLElement}
 */
function getRegion() {
  if (region && document.body.contains(region)) return region;
  region = document.createElement('div');
  region.className = 'toast-region';
  document.body.appendChild(region);
  return region;
}

/**
 * Retire le toast courant et purge son timer.
 */
function clearActive() {
  if (!active) return;
  if (active.timerId) clearTimeout(active.timerId);
  active.root.remove();
  active = null;
}

/**
 * (Re)démarre le timer d'auto-dismiss.
 *
 * @param {number} ms Durée restante en ms.
 */
function startTimer(ms) {
  if (!active) return;
  active.startedAt = Date.now();
  active.remaining = ms;
  active.timerId = setTimeout(clearActive, ms);
}

/**
 * Met le timer en pause (survol / focus) en mémorisant le temps restant.
 */
function pauseTimer() {
  if (!active || !active.timerId) return;
  clearTimeout(active.timerId);
  active.timerId = null;
  active.remaining -= Date.now() - active.startedAt;
}

/**
 * Reprend le timer avec le temps restant (sortie du survol / focus).
 */
function resumeTimer() {
  if (!active || active.timerId) return;
  startTimer(Math.max(active.remaining, 1500));
}

/**
 * Affiche un toast (remplace le toast courant s'il y en a un).
 *
 * @param {Object} opts
 * @param {string} opts.message          Texte de confirmation.
 * @param {Object} [opts.action]         Action optionnelle.
 * @param {string} opts.action.label     Libellé du lien.
 * @param {string} opts.action.href      URL du lien.
 */
export function showToast({ message, action } = {}) {
  if (!message) return;

  clearActive();
  const host = getRegion();

  const root = document.createElement('div');
  root.className = 'toast';

  // Message : région live (texte injecté après insertion → annonce fiable).
  const msg = document.createElement('p');
  msg.className = 'toast__message';
  msg.setAttribute('role', 'status');
  msg.setAttribute('aria-live', 'polite');
  root.appendChild(msg);

  // Action optionnelle (hors région live).
  if (action && action.label && action.href) {
    const link = document.createElement('a');
    link.className = 'toast__action';
    link.href = action.href;
    link.textContent = action.label;
    root.appendChild(link);
  }

  // Fermeture manuelle.
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'toast__close';
  close.setAttribute('aria-label', 'Fermer');
  close.innerHTML = '<span aria-hidden="true">×</span>';
  close.addEventListener('click', clearActive);
  root.appendChild(close);

  // Pause / reprise du timer au survol et au focus.
  root.addEventListener('mouseenter', pauseTimer);
  root.addEventListener('mouseleave', resumeTimer);
  root.addEventListener('focusin', pauseTimer);
  root.addEventListener('focusout', resumeTimer);

  host.appendChild(root);
  active = { root, timerId: null, remaining: AUTO_DISMISS_MS, startedAt: 0 };

  // Injecte le texte après insertion pour fiabiliser l'annonce du lecteur d'écran.
  requestAnimationFrame(() => {
    msg.textContent = message;
  });

  startTimer(AUTO_DISMISS_MS);
}
