/**
 * Mon Compte — toggle opt-in newsletter « Cahiers de Delphine ».
 *
 * - Sélecteur racine : [data-account-optin] (rendu dans la section
 *   « Votre abonnement digital » pour les abonnés actifs).
 * - Au changement du switch, poste via `restFetch()` (session.js) vers
 *   POST /180c/v1/newsletter/account-optin (cookie + nonce wp_rest frais), qui
 *   inscrit/désinscrit l'utilisateur sur la même liste Mailchimp que la page
 *   Newsletter.
 * - Retour visuel accessible dans [.my-account-optin__feedback] (role=status).
 *   En cas d'erreur, l'état du switch est restauré.
 *
 * @module account-newsletter-optin
 */

import { TOGGLE_MESSAGES, toggleErrorForStatus } from './newsletter-messages.js';
// restFetch pose le nonce COURANT et rejoue une fois sur nonce invalide. Le
// nonce n'est plus inliné dans le HTML (cache WPSC) : une constante lue à
// l'import capturait une chaîne vide, que le cœur REST refusait en 403
// `rest_cookie_invalid_nonce` — affiché à tort comme « réservé aux abonnés ».
import { restFetch } from './session.js';

/**
 * Affiche un message dans la zone de feedback.
 *
 * @param {HTMLElement} node    Élément .my-account-optin__feedback.
 * @param {string}      message Texte à afficher.
 * @param {boolean}     isError Vrai pour styliser en erreur.
 */
function setFeedback(node, message, isError) {
  if (!node) {
    return;
  }
  node.textContent = message;
  node.classList.toggle('my-account-optin__feedback--error', Boolean(isError));
  node.hidden = false;
}

/**
 * Aligne l'état initial du switch sur l'état réel de l'abonnement Mailchimp.
 *
 * L'affichage serveur part de la meta locale (potentiellement désynchronisée) :
 * on interroge GET newsletter/account-optin pour refléter la réalité Mailchimp.
 * En cas d'échec, on conserve l'état rendu côté serveur.
 *
 * @param {HTMLInputElement} input Le switch opt-in.
 */
async function syncInitialState(input) {
  input.disabled = true;
  try {
    const res = await restFetch('newsletter/account-optin', {
      method: 'GET',
      headers: { Accept: 'application/json' },
    });
    if (res.ok) {
      const data = await res.json().catch(() => ({}));
      if (typeof data.optin === 'boolean') {
        input.checked = data.optin;
      }
    }
  } catch {
    // Conserve l'état serveur : la lecture live a échoué, sans bloquer l'UI.
  } finally {
    input.disabled = false;
  }
}

/**
 * Initialise le toggle opt-in (s'il est présent).
 */
export function init() {
  const root = document.querySelector('[data-account-optin]');
  if (!root) {
    return;
  }

  const input = root.querySelector('[data-account-optin-input]');
  const feedback = root.querySelector('.my-account-optin__feedback');
  if (!input) {
    return;
  }

  syncInitialState(input);

  input.addEventListener('change', async () => {
    const optin = input.checked;
    input.disabled = true;

    try {
      const res = await restFetch('newsletter/account-optin', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        // `source` uniquement à l'opt-in : une désinscription ne doit poser
        // aucun tag de source (les tags de source ne sont jamais retirés).
        body: JSON.stringify(optin ? { optin, source: 'web-account-prefs' } : { optin }),
      });

      if (!res.ok) {
        // Restaure l'état + message spécifique au code HTTP.
        input.checked = !optin;
        setFeedback(feedback, toggleErrorForStatus(res.status), true);
        return;
      }

      setFeedback(feedback, optin ? TOGGLE_MESSAGES.on : TOGGLE_MESSAGES.off, false);
    } catch {
      // Échec réseau : restaure l'état précédent.
      input.checked = !optin;
      setFeedback(feedback, toggleErrorForStatus(0), true);
    } finally {
      input.disabled = false;
    }
  });
}
