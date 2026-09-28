/**
 * Module recette 180°C — interactivité de la fiche recette (/).
 *
 * Fonctionnalités :
 *  1. Timer de cuisson       : overlay countdown + bip discret en fin.
 *  2. Sticky paywall CTA     : mini-bannière révélée au scroll, masquable 24h.
 *  3. Impression             : bouton desktop → impression native.
 *  4. Lightbox hero          : delegation au module partage `lightbox.js`.
 *
 * Le copier-lien de la barre de partage est géré par le module partagé
 * `share-bar.js` (commun recette/article), pas ici.
 *
 * Vanilla ES module, sans dépendance. S'initialise si une fiche recette
 * (.recipe) est présente dans le DOM.
 *
 * @module recipe
 */

import { initLightbox } from './lightbox.js';

const COOKIE_STICKY = '180c_paywall_dismiss';

// ============================================================
// 1. Timer de cuisson
// ============================================================

let timerState = null;

/**
 * Construit (une fois) l'overlay du timer et le retourne.
 *
 * @returns {HTMLElement}
 */
function ensureTimerOverlay() {
  let overlay = document.querySelector('.recipe-timer');
  if (overlay) return overlay;

  overlay = document.createElement('div');
  overlay.className = 'recipe-timer';
  overlay.setAttribute('role', 'timer');
  overlay.setAttribute('aria-live', 'assertive');
  overlay.hidden = true;
  overlay.innerHTML = `
    <div class="recipe-timer__display" data-timer-display>00:00</div>
    <button type="button" class="recipe-timer__close" data-action="close-timer" aria-label="Fermer le minuteur">✕</button>
  `;
  document.body.appendChild(overlay);

  overlay.addEventListener('click', (e) => {
    if (e.target.closest('[data-action="close-timer"]')) {
      stopTimer();
    }
  });

  return overlay;
}

/**
 * Émet un bip discret via la Web Audio API.
 */
function beep() {
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return;
    const ctx = new Ctx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = 'sine';
    osc.frequency.value = 880;
    gain.gain.setValueAtTime(0.001, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
    osc.start();
    osc.stop(ctx.currentTime + 0.6);
    osc.onended = () => ctx.close();
  } catch {
    /* audio non disponible — échec silencieux */
  }
}

/**
 * Arrête et masque le timer courant.
 */
function stopTimer() {
  if (timerState?.interval) {
    clearInterval(timerState.interval);
  }
  const overlay = document.querySelector('.recipe-timer');
  if (overlay) {
    overlay.hidden = true;
    overlay.classList.remove('recipe-timer--done');
  }
  timerState = null;
}

/**
 * Démarre un timer de N minutes.
 *
 * @param {number} minutes
 */
function startTimer(minutes) {
  if (!minutes || minutes < 1) return;

  const overlay = ensureTimerOverlay();
  const display = overlay.querySelector('[data-timer-display]');
  overlay.hidden = false;
  overlay.classList.remove('recipe-timer--done');

  if (timerState?.interval) {
    clearInterval(timerState.interval);
  }

  let remaining = minutes * 60;

  const update = () => {
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    display.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  };
  update();

  timerState = {
    interval: setInterval(() => {
      remaining -= 1;
      if (remaining <= 0) {
        clearInterval(timerState.interval);
        timerState.interval = null;
        display.textContent = '00:00';
        overlay.classList.add('recipe-timer--done');
        beep();
        return;
      }
      update();
    }, 1000),
  };
}

/**
 * Attache les déclencheurs de timer.
 *
 * @param {HTMLElement} recipe
 */
function initTimers(recipe) {
  recipe.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="start-timer"][data-timer-minutes]');
    if (!btn) return;
    startTimer(parseInt(btn.dataset.timerMinutes, 10));
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && timerState) {
      stopTimer();
    }
  });
}

// ============================================================
// 2. Sticky paywall CTA
// ============================================================

/**
 * Lit un cookie par son nom.
 *
 * @param {string} name
 * @returns {string|null}
 */
function getCookie(name) {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Initialise la mini-bannière sticky pour les non-abonnés.
 */
function initStickyCta() {
  const sticky = document.querySelector('[data-recipe-sticky-cta]');
  const paywall = document.querySelector('.recipe-paywall');
  if (!sticky || !paywall) return;

  if (getCookie(COOKIE_STICKY) === '1') return;

  // La bannière est rendue dans l'article (donc dans #site-shell, dont le
  // `will-change: transform` crée un containing block qui « piège » le
  // position:fixed et la fait apparaître sous le footer). On la déplace en
  // enfant direct de <body>, hors de #site-shell, pour ancrer son
  // positionnement fixe au viewport (même stratégie que les autres overlays).
  if (sticky.parentElement !== document.body) {
    document.body.appendChild(sticky);
  }

  // Révèle la bannière quand le paywall sort du viewport vers le haut.
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const scrolledPast = !entry.isIntersecting && entry.boundingClientRect.top < 0;
        sticky.hidden = !scrolledPast;
        sticky.classList.toggle('recipe-sticky-cta--visible', scrolledPast);
      });
    },
    { threshold: 0 }
  );
  observer.observe(paywall);

  sticky.addEventListener('click', (e) => {
    if (!e.target.closest('[data-action="dismiss-sticky-cta"]')) return;
    document.cookie = `${COOKIE_STICKY}=1; path=/; max-age=86400; samesite=lax`;
    sticky.hidden = true;
    sticky.classList.remove('recipe-sticky-cta--visible');
    observer.disconnect();
  });
}

// ============================================================
// Partage social : le copier-lien + toast est géré par le module
// partagé `share-bar.js` (commun recette/article), chargé globalement
// depuis main.js dès qu'une barre `[data-share-bar]` est présente.
// ============================================================

// ============================================================
// Init
// ============================================================

/**
 * Point d'entrée du module.
 */
/**
 * Bouton « Imprimer » (desktop) → impression native via la feuille @media print.
 *
 * @param {HTMLElement} recipe Racine de la fiche recette.
 */
function initPrint(recipe) {
  const btn = recipe.querySelector('[data-recipe-print]');
  if (!btn) return;

  btn.addEventListener('click', () => {
    if (btn.disabled || btn.getAttribute('aria-disabled') === 'true') return;
    window.print();
  });
}

export function init() {
  const recipe = document.querySelector('.recipe');
  if (!recipe) return;

  initTimers(recipe);
  initStickyCta();
  initPrint(recipe);

  // Lightbox hero : delegation au module partage. Contrat identique
  // (triggers `[data-lightbox-trigger]` dans une figure `[data-lightbox-src]`).
  initLightbox({ triggerSelector: '.recipe [data-lightbox-trigger]' });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
