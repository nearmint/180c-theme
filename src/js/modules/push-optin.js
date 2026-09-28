/**
 * Mon Compte — toggle d'activation des notifications web.
 *
 * - Racine : `[data-push-optin]`, rendue dans la section « Votre abonnement
 *   digital » pour les comptes abonnés (parts/account/dashboard.php).
 * - Le chemin d'activation vit dans `push-sdk.js` : ce module ne fait que
 *   piloter l'état visuel et le retour accessible.
 * - Aucun script tiers n'est chargé tant que l'utilisateur n'a pas basculé
 *   l'interrupteur.
 *
 * @module push-optin
 */

import {
  config,
  disablePush,
  enablePush,
  isSupported,
  message,
  permission,
} from './push-sdk.js';

/**
 * Affiche un message dans la zone de retour.
 *
 * @param {HTMLElement} node    Élément `.my-account-optin__feedback`.
 * @param {string}      text    Texte à afficher.
 * @param {boolean}     isError Vrai pour styliser en erreur.
 */
function setFeedback(node, text, isError) {
  if (!node || text === '') {
    return;
  }
  node.textContent = text;
  node.classList.toggle('my-account-optin__feedback--error', Boolean(isError));
  node.hidden = false;
}

/**
 * Aligne l'affichage sur l'état RÉEL du navigateur.
 *
 * La meta serveur ne peut pas savoir qu'une permission a été révoquée depuis
 * les réglages du navigateur : elle resterait à « activé » alors que plus rien
 * n'arrive. C'est ici, et seulement ici, que la divergence est rattrapée.
 *
 * @param {HTMLInputElement} input    Interrupteur.
 * @param {HTMLElement}      feedback Zone de retour.
 */
function syncToBrowserState(input, feedback) {
  if (!isSupported()) {
    input.checked = false;
    input.disabled = true;
    setFeedback(feedback, message('unsupported'), false);
    return;
  }

  if (permission() === 'denied') {
    input.checked = false;
    input.disabled = true;
    setFeedback(feedback, message('denied'), true);
    return;
  }

  // Opt-in enregistré côté serveur mais permission jamais accordée dans CE
  // navigateur : l'utilisateur a activé les notifications ailleurs. L'état
  // honnête est « éteint » ici.
  if (input.checked && permission() !== 'granted') {
    input.checked = false;
  }
}

/**
 * Initialise le toggle, s'il est présent.
 */
export function init() {
  const root = document.querySelector('[data-push-optin]');
  if (!root) {
    return;
  }

  // Sans configuration serveur, le compte n'est pas éligible : le markup ne
  // devrait pas être là, mais on ne prend pas le risque d'un toggle inerte.
  if (!config()) {
    root.hidden = true;
    return;
  }

  const input = root.querySelector('[data-push-optin-input]');
  const feedback = root.querySelector('.my-account-optin__feedback');
  if (!input) {
    return;
  }

  syncToBrowserState(input, feedback);

  input.addEventListener('change', async () => {
    const wanted = input.checked;
    input.disabled = true;

    const result = wanted ? await enablePush() : await disablePush();

    if (result.ok) {
      setFeedback(feedback, message(wanted ? 'on' : 'off'), false);
    } else {
      // Échec : l'interrupteur doit refléter la réalité, pas l'intention.
      input.checked = !wanted;
      setFeedback(feedback, message(result.reason), true);
    }

    // Une permission refusée définitivement condamne l'interrupteur : le
    // réactiver laisserait croire qu'un nouvel essai peut aboutir, alors que
    // le navigateur ne redemandera plus rien.
    input.disabled = permission() === 'denied' || !isSupported();
  });
}
