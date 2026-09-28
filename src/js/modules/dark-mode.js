/**
 * Dark mode 180°C — toggle 3 états.
 *
 * États : auto (prefers-color-scheme système) | light | dark.
 * Persistance : cookie `180c-theme-mode` (1 an) + localStorage (fallback).
 *
 * L'anti-FOUC n'est PAS ici : `data-theme` est posé par un script inline
 * synchrone en tête de <head> (header.php), avant toute feuille de style. Ce
 * module-ci voyage dans le bundle principal, chargé bien après le premier
 * paint — il ne peut que confirmer une décision déjà prise.
 *
 * La résolution a été retirée du PHP : le HTML étant servi par WP Super Cache,
 * la valeur figée dans la page était celle du visiteur ayant régénéré le cache.
 *
 * Ce module reste responsable de la persistance (cookie + localStorage), de la
 * synchronisation visuelle du toggle et de l'application des changements.
 *
 * Toggle HTML attendu dans le footer :
 *   <div data-theme-toggle class="theme-toggle" role="group" aria-label="Thème d'affichage">
 *     <input type="radio" name="theme-mode" id="theme-auto" value="auto">
 *     <label for="theme-auto" title="Automatique"><svg>…</svg></label>
 *     <input type="radio" name="theme-mode" id="theme-light" value="light">
 *     <label for="theme-light" title="Clair"><svg>…</svg></label>
 *     <input type="radio" name="theme-mode" id="theme-dark" value="dark">
 *     <label for="theme-dark" title="Sombre"><svg>…</svg></label>
 *   </div>
 */

const COOKIE_NAME = '180c-theme-mode';
const VALID_MODES = ['auto', 'light', 'dark'];

/**
 * Écrit un cookie (SameSite=Lax, chemin /, durée configurable).
 * @param {string} name
 * @param {string} value
 * @param {number} maxAgeDays
 */
function setCookie(name, value, maxAgeDays) {
  const maxAge = maxAgeDays * 24 * 60 * 60;
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; SameSite=Lax`;
}

/**
 * Lit un cookie par son nom, retourne null si absent.
 * @param {string} name
 * @returns {string|null}
 */
function getCookie(name) {
  const prefix = `${name}=`;
  const parts = document.cookie.split(';');
  for (let i = 0; i < parts.length; i++) {
    const part = parts[i].trimStart();
    if (part.startsWith(prefix)) {
      return decodeURIComponent(part.slice(prefix.length));
    }
  }
  return null;
}

/**
 * Lit le mode persisté (cookie prioritaire, puis localStorage, puis 'dark').
 * Défaut = 'dark' : le site est en dark mode par défaut, aligné sur le rendu
 * serveur (header.php pose data-theme="dark" quand aucun choix n'est persisté).
 * @returns {'auto'|'light'|'dark'}
 */
function getStoredMode() {
  // Cookie prioritaire : lu aussi server-side pour le rendu PHP.
  const fromCookie = getCookie(COOKIE_NAME);
  if (fromCookie && VALID_MODES.includes(fromCookie)) {
    return /** @type {'auto'|'light'|'dark'} */ (fromCookie);
  }

  // Fallback localStorage (contexte où cookie absent mais déjà choisi côté client).
  try {
    const fromStorage = localStorage.getItem(COOKIE_NAME);
    if (fromStorage && VALID_MODES.includes(fromStorage)) {
      return /** @type {'auto'|'light'|'dark'} */ (fromStorage);
    }
  } catch (_e) {
    // localStorage inaccessible (mode navigation privée strict, storage bloqué).
  }

  return 'dark';
}

/**
 * Persiste le mode dans cookie + localStorage.
 * @param {'auto'|'light'|'dark'} mode
 */
function persistMode(mode) {
  setCookie(COOKIE_NAME, mode, 365);
  try {
    localStorage.setItem(COOKIE_NAME, mode);
  } catch (_e) {
    // ignore si inaccessible
  }
}

/**
 * Applique le mode sur <html> via data-theme.
 * 'auto' retire l'attribut (laisse prefers-color-scheme décider via CSS).
 * @param {'auto'|'light'|'dark'} mode
 */
function applyMode(mode) {
  if (mode === 'auto') {
    document.documentElement.removeAttribute('data-theme');
  } else {
    document.documentElement.setAttribute('data-theme', mode);
  }
}

/**
 * Synchronise l'état visuel des inputs radio du toggle.
 * @param {'auto'|'light'|'dark'} mode
 */
function syncToggleUI(mode) {
  const inputs = document.querySelectorAll(
    '[data-theme-toggle] input[type="radio"][name="theme-mode"]'
  );
  inputs.forEach((input) => {
    /** @type {HTMLInputElement} */ (input).checked =
      (/** @type {HTMLInputElement} */ (input)).value === mode;
  });
}

/**
 * Initialisation : lit le mode stocké, l'applique, écoute les changements.
 */
function init() {
  const currentMode = getStoredMode();
  applyMode(currentMode);
  syncToggleUI(currentMode);

  // Écoute les changements sur les inputs radio du toggle footer.
  document.addEventListener('change', (e) => {
    const target = /** @type {HTMLElement} */ (e.target);
    if (
      target.tagName !== 'INPUT' ||
      /** @type {HTMLInputElement} */ (target).type !== 'radio' ||
      /** @type {HTMLInputElement} */ (target).name !== 'theme-mode' ||
      !target.closest('[data-theme-toggle]')
    ) {
      return;
    }

    const newMode = /** @type {HTMLInputElement} */ (target).value;
    if (!VALID_MODES.includes(newMode)) return;

    applyMode(/** @type {'auto'|'light'|'dark'} */ (newMode));
    persistMode(/** @type {'auto'|'light'|'dark'} */ (newMode));
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}
