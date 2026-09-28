/**
 * Module favoris — toggle « Mon carnet » via REST 180c/v1/favorites.
 *
 * - Délégation d'événements sur [data-favorite-toggle][data-recipe-id].
 * - Hydratation côté serveur : l'état initial (aria-pressed) est rendu en PHP
 *   via _180c_favorite_is_marked() (1 requête/page, O(1) par carte). Aucune
 *   requête d'hydratation JS n'est donc nécessaire au chargement.
 * - Optimistic UI : bascule visuelle immédiate, rollback + statut sur erreur.
 * - Verrou anti double-clic (aria-busy + disabled) pendant l'appel réseau.
 * - Déconnecté : redirige vers /connexion/?redirect_to=… sans appel API.
 * - Statut annoncé via [data-favorite-status][aria-live="polite"].
 *
 * Tracking : NON câblé ici. L'event recipe_favorite (action=add|remove) est
 * émis côté serveur par les hooks 180c/favorite_added|removed (cf.
 * inc/analytics/ga4.php) à chaque POST/DELETE — dupliquer en JS double-compterait.
 *
 * @module favorites
 */

import { showToast } from './toast.js';
// restFetch pose le nonce COURANT et rejoue une fois sur nonce invalide : le
// nonce ne peut plus être figé à l'import, sinon une page servie depuis le
// cache enverrait indéfiniment une valeur périmée.
import { restFetch } from './session.js';

// ============================================================
// Configuration (objet dédié, fallback sur l'objet global thème)
// ============================================================

// Le préfixe REST n'est plus lu ici : restFetch() le résout lui-même, seul
// endroit où il est désormais connu.
const CONFIG = window._180cFavorites ?? window._180c ?? {};
const IS_LOGGED_IN = CONFIG.isLoggedIn ?? false;
const LOGIN_URL = CONFIG.loginUrl ?? '/connexion/';
const CARNET_URL = CONFIG.carnetUrl ?? '/mon-carnet/';

const LABEL_ADD = 'Ajouter à mon carnet';
const LABEL_REMOVE = 'Retirer de mon carnet';
const STATUS_ADDED = 'Recette ajoutée à votre carnet';
const STATUS_ALREADY = 'Recette déjà dans votre carnet';
const STATUS_REMOVED = 'Retiré de mon carnet';
const STATUS_ERROR = 'Une erreur est survenue, réessayez.';
const CARNET_ACTION = 'Voir mon carnet';

// ============================================================
// Init
// ============================================================

/**
 * Attache le listener délégué si au moins un bouton favori est présent.
 */
function initFavorites() {
  if (!document.querySelector('[data-favorite-toggle]')) return;
  document.addEventListener('click', handleFavoriteClick);
}

// ============================================================
// Gestionnaire d'événement
// ============================================================

/**
 * Intercepte les clics sur les boutons favoris.
 *
 * @param {MouseEvent} e
 */
async function handleFavoriteClick(e) {
  const trigger = e.target.closest('[data-favorite-toggle][data-recipe-id]');
  if (!trigger) return;

  e.preventDefault();

  // Déconnecté : redirige vers la connexion, aucun appel API.
  if (!IS_LOGGED_IN) {
    const redirect = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `${LOGIN_URL}?redirect_to=${redirect}`;
    return;
  }

  const recipeId = parseInt(trigger.dataset.recipeId, 10);
  if (!recipeId) return;

  // Source de vérité au niveau DOM : TOUS les boutons de cette recette
  // (une même recette peut apparaître plusieurs fois : hero + rail, etc.).
  const buttons = recipeButtons(recipeId);

  // Verrou anti double-clic porté par la recette : si un jumeau est déjà en
  // vol, on ignore le clic — c'est ce qui empêche un POST/DELETE concurrent
  // déclenché par un jumeau non verrouillé (cause de l'inversion d'état).
  if (trigger.getAttribute('aria-busy') === 'true') return;

  const wasFavorite = trigger.getAttribute('aria-pressed') === 'true';

  // Optimistic UI : bascule + verrou sur TOUS les jumeaux avant la requête.
  setStateAll(buttons, !wasFavorite);
  lockAll(buttons, true);

  try {
    if (wasFavorite) {
      // Retrait : silencieux côté toast, le cœur outline suffit. Annonce SR
      // sur le seul bouton déclencheur (pas N fois).
      await removeFavorite(recipeId);
      announce(trigger, STATUS_REMOVED);
    } else {
      // Ajout : un seul toast par action (pas un par jumeau). Le toast porte
      // lui-même l'annonce role=status — pas d'annonce in-button en double.
      await addFavorite(recipeId);
      showToast({
        message: STATUS_ADDED,
        action: { label: CARNET_ACTION, href: CARNET_URL },
      });
    }
    updateCountBadge(!wasFavorite);
  } catch (err) {
    // Échec : rollback de TOUS les jumeaux vers l'état serveur connu.
    setStateAll(buttons, wasFavorite);
    announce(trigger, STATUS_ERROR);
    // eslint-disable-next-line no-console
    console.error('[180c/favorites] Erreur lors du toggle favori :', err.message);
  } finally {
    lockAll(buttons, false);
  }
}

// ============================================================
// Appels REST
// ============================================================

/**
 * Ajoute une recette aux favoris via POST /favorites/.
 *
 * @param {number} recipeId
 * @returns {Promise<void>}
 */
async function addFavorite(recipeId) {
  const response = await restFetch('favorites', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recipe_id: recipeId }),
  });

  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    throw new Error(data.message ?? `HTTP ${response.status}`);
  }

  // Signal neutre pour la mesure (consommé par umami-events.js). Ce module n'a
  // pas à connaître les outils d'analytics.
  document.dispatchEvent(
    new CustomEvent('180c:favorite:added', { detail: { recipeId } })
  );
}

/**
 * Retire une recette des favoris via DELETE /favorites/{id}/.
 *
 * @param {number} recipeId
 * @returns {Promise<void>}
 */
async function removeFavorite(recipeId) {
  const response = await restFetch(`favorites/${recipeId}`, {
    method: 'DELETE',
  });

  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    throw new Error(data.message ?? `HTTP ${response.status}`);
  }
}

// ============================================================
// Helpers UI
// ============================================================

/**
 * Retourne tous les boutons favoris d'une recette présents sur la page.
 *
 * @param {number} recipeId
 * @returns {NodeListOf<HTMLElement>}
 */
function recipeButtons(recipeId) {
  return document.querySelectorAll(
    `[data-favorite-toggle][data-recipe-id="${recipeId}"]`,
  );
}

/**
 * Applique l'état favori à tous les boutons d'une recette.
 *
 * @param {NodeListOf<HTMLElement>} buttons
 * @param {boolean}                 active
 */
function setStateAll(buttons, active) {
  buttons.forEach((button) => setButtonState(button, active));
}

/**
 * Verrouille / déverrouille tous les boutons d'une recette.
 *
 * @param {NodeListOf<HTMLElement>} buttons
 * @param {boolean}                 busy
 */
function lockAll(buttons, busy) {
  buttons.forEach((button) => lockButton(button, busy));
}

/**
 * Met à jour l'état visuel + ARIA d'un bouton favori.
 *
 * @param {HTMLElement} button
 * @param {boolean}     active True = favori actif.
 */
function setButtonState(button, active) {
  button.setAttribute('aria-pressed', active ? 'true' : 'false');

  const labelAdd = button.dataset.labelAdd ?? LABEL_ADD;
  const labelRemove = button.dataset.labelRemove ?? LABEL_REMOVE;
  const text = active ? labelRemove : labelAdd;

  button.setAttribute('aria-label', text);

  // Surface « single » : synchronise aussi le label visible.
  const label = button.querySelector('.favorite-button__label');
  if (label) label.textContent = text;
}

/**
 * Verrouille / déverrouille un bouton pendant l'appel réseau.
 *
 * @param {HTMLElement} button
 * @param {boolean}     busy
 */
function lockButton(button, busy) {
  if (busy) {
    button.setAttribute('aria-busy', 'true');
    button.disabled = true;
  } else {
    button.removeAttribute('aria-busy');
    button.disabled = false;
  }
}

/**
 * Annonce un changement d'état dans la zone live du bouton (lecteur d'écran).
 *
 * @param {HTMLElement} button
 * @param {string}      message
 */
function announce(button, message) {
  const status = button.querySelector('[data-favorite-status]');
  if (status) status.textContent = message;
}

/**
 * Met à jour le badge compteur de favoris s'il est présent dans le DOM.
 *
 * @param {boolean} added True si ajout, false si retrait.
 */
function updateCountBadge(added) {
  const badge = document.querySelector('[data-favorites-count]');
  if (!badge) return;

  const current = parseInt(badge.textContent, 10) || 0;
  const next = added ? current + 1 : Math.max(0, current - 1);

  badge.textContent = String(next);
  badge.hidden = next === 0;
}

// ============================================================
// Flash post-deeplink (/mon-carnet/?add={slug} → recette ?carnet=added|already)
// ============================================================

/**
 * Affiche le toast de confirmation après un ajout par deeplink, puis nettoie
 * l'URL. Le handler PHP (inc/carnet-deeplink.php) redirige vers la fiche recette
 * avec `?carnet=added` (nouvel ajout) ou `?carnet=already` (déjà au carnet) ; on
 * réutilise le toast existant, puis on retire le seul param `carnet` via
 * history.replaceState afin qu'il ne survive ni au partage ni au bouton retour.
 */
function initCarnetFlash() {
  const params = new URLSearchParams(window.location.search);
  const state = params.get('carnet');
  if (state !== 'added' && state !== 'already') return;

  showToast({
    message: state === 'added' ? STATUS_ADDED : STATUS_ALREADY,
    action: { label: CARNET_ACTION, href: CARNET_URL },
  });

  // Nettoyage : on ne retire que `carnet`, en préservant les autres params et
  // l'ancre, sans empiler d'entrée d'historique (replaceState).
  params.delete('carnet');
  const query = params.toString();
  const cleanUrl = window.location.pathname + (query ? `?${query}` : '') + window.location.hash;
  window.history.replaceState({}, '', cleanUrl);
}

// ============================================================
// Boot
// ============================================================

function boot() {
  initFavorites();
  initCarnetFlash();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
