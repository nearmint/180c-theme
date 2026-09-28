/**
 * Modale de confirmation réutilisable (<dialog> natif).
 *
 * Enhancement progressif : sans JS, les déclencheurs (liens) naviguent
 * normalement. Avec JS, un clic ouvre une modale ; au confirm, l'action
 * d'origine est suivie (href, ou soumission du formulaire parent) ; au cancel
 * ou Échap, la modale se ferme sans rien faire.
 *
 * Le <dialog> natif gère nativement le piège de focus, la touche Échap et
 * l'inertie de l'arrière-plan (showModal).
 *
 * Déclencheur : a[data-confirm] / button[data-confirm], attributs optionnels :
 *   - data-confirm-title          (titre de la modale)
 *   - data-confirm-message        (corps ; à défaut, la valeur de data-confirm)
 *   - data-confirm-confirm-label  (libellé du bouton de confirmation)
 *   - data-confirm-href           (cible ; à défaut, le href du lien)
 *   - data-confirm-post           (chemin REST 180c/v1 ; au confirm, POST
 *                                  authentifié puis reload — prioritaire sur href)
 *   - data-confirm-body           (corps JSON du POST ; optionnel)
 *
 * Les <form data-confirm> ne sont PAS gérés ici (cf. auth.js, suppression de
 * compte) afin d'éviter toute collision.
 *
 * @module confirm-dialog
 */

// restFetch pose le nonce COURANT et rejoue une fois sur nonce invalide : le
// nonce n'est plus inliné dans le HTML (cache WPSC), une constante lue à
// l'import capturait une chaîne vide refusée en 403 par le cœur REST.
import { restFetch } from './session.js';

const SELECTOR = 'a[data-confirm], button[data-confirm]';

let dialog = null;
let titleEl = null;
let messageEl = null;
let confirmBtn = null;
let cancelBtn = null;
let pending = null; // { type: 'href'|'form'|'post', value, body }

/**
 * Construit (une seule fois) l'élément <dialog> et le retourne.
 *
 * @returns {HTMLDialogElement}
 */
function buildDialog() {
  if (dialog) {
    return dialog;
  }

  dialog = document.createElement('dialog');
  dialog.className = 'modal';
  dialog.setAttribute('aria-labelledby', 'confirm-modal-title');
  dialog.innerHTML = [
    '<div class="modal__inner">',
    '<h2 class="modal__title" id="confirm-modal-title"></h2>',
    '<p class="modal__message"></p>',
    '<div class="modal__actions">',
    '<button type="button" class="modal__btn modal__btn--cancel" data-modal-cancel></button>',
    '<button type="button" class="modal__btn modal__btn--confirm" data-modal-confirm></button>',
    '</div>',
    '</div>',
  ].join('');

  document.body.appendChild(dialog);

  titleEl = dialog.querySelector('.modal__title');
  messageEl = dialog.querySelector('.modal__message');
  confirmBtn = dialog.querySelector('[data-modal-confirm]');
  cancelBtn = dialog.querySelector('[data-modal-cancel]');
  cancelBtn.textContent = 'Annuler';

  cancelBtn.addEventListener('click', () => dialog.close());
  confirmBtn.addEventListener('click', runPending);

  // Clic sur le backdrop (en dehors de .modal__inner) → ferme.
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) {
      dialog.close();
    }
  });

  // Échap (cancel natif) : on purge l'action en attente.
  dialog.addEventListener('close', () => {
    pending = null;
  });

  return dialog;
}

/**
 * POST JSON authentifié (cookie + nonce wp_rest) vers une route 180c/v1, puis
 * recharge la page pour refléter le nouvel état. En cas d'échec, la page est
 * tout de même rechargée (l'UI reste cohérente avec l'état serveur réel).
 *
 * @param {string} path Chemin relatif (ex. 'subscription/reactivate').
 * @param {Object} body Corps de la requête.
 */
async function postThenReload(path, body) {
  try {
    await restFetch(path, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body ?? {}),
    });
  } catch (error) {
    // Échec réseau : on recharge quand même pour resynchroniser l'affichage.
    window.console?.warn('[180c/confirm] POST échoué :', error.message);
  }

  window.location.reload();
}

/**
 * Exécute l'action confirmée puis ferme la modale.
 */
function runPending() {
  const action = pending;
  pending = null;
  dialog.close();

  if (!action) {
    return;
  }

  if ('post' === action.type) {
    postThenReload(action.value, action.body);
  } else if ('href' === action.type) {
    window.location.assign(action.value);
  } else if ('form' === action.type) {
    action.value.submit();
  }
}

/**
 * Ouvre la modale pour un déclencheur donné.
 *
 * @param {Event} event Événement click.
 */
function onTrigger(event) {
  const el = event.currentTarget;
  const post = el.dataset.confirmPost || '';
  const href = el.dataset.confirmHref || el.getAttribute('href') || '';
  const form = post || href ? null : el.closest('form');

  if (!post && !href && !form) {
    return; // Rien à confirmer : laisse le comportement par défaut.
  }

  event.preventDefault();
  buildDialog();

  const title = el.dataset.confirmTitle || '';
  titleEl.textContent = title;
  titleEl.hidden = '' === title;
  messageEl.textContent = el.dataset.confirmMessage || el.dataset.confirm || '';
  confirmBtn.textContent = el.dataset.confirmConfirmLabel || 'Confirmer';

  if (post) {
    let body = {};
    try {
      body = el.dataset.confirmBody ? JSON.parse(el.dataset.confirmBody) : {};
    } catch (error) {
      body = {};
    }
    pending = { type: 'post', value: post, body };
  } else {
    pending = href ? { type: 'href', value: href } : { type: 'form', value: form };
  }

  dialog.showModal();
  // Focus par défaut sur « Annuler » (action destructrice → choix sûr).
  cancelBtn.focus();
}

/**
 * Point d'entrée du module.
 */
export function init() {
  const triggers = document.querySelectorAll(SELECTOR);
  if (!triggers.length) {
    return;
  }

  buildDialog();
  triggers.forEach((trigger) => trigger.addEventListener('click', onTrigger));
}

if ('loading' === document.readyState) {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
