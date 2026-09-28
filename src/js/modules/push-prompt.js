/**
 * Invite d'activation des notifications web, après deux recettes consultées.
 *
 * Le serveur ne rend l'invite que pour un abonné sans opt-in, et la rend
 * MASQUÉE : trois conditions ne sont connues que du navigateur, et le HTML peut
 * venir du cache. C'est ce module qui décide de l'afficher.
 *
 *   1. deux recettes DISTINCTES consultées — deux visites de la même recette ne
 *      comptent que pour une ;
 *   2. aucune fermeture mémorisée pour la version courante ;
 *   3. `Notification.permission` encore à `default` — une permission déjà
 *      accordée ou déjà refusée rend l'invite sans objet.
 *
 * L'activation elle-même n'est pas réimplémentée : elle appelle `enablePush()`
 * de `push-sdk.js`, le même chemin que le toggle « Mon compte ». Le SDK n'est
 * donc chargé qu'au clic sur le bouton d'acceptation.
 *
 * Persistance sur le modèle du bandeau d'information (`info-banner.js`) : la
 * clé de fermeture stocke la VERSION fermée, pas un booléen. Publier une
 * nouvelle version réaffiche l'invite ; fermer l'ancienne ne masque pas la
 * suivante.
 *
 * @module push-prompt
 */

import { config, enablePush, isSupported, message, permission } from './push-sdk.js';

/** Recettes distinctes déjà consultées. */
const VIEWS_KEY = '_180c_push_recipe_views';

/** Version de l'invite fermée par l'utilisateur. */
const DISMISSED_KEY = '_180c_push_prompt_dismissed';

/** Nombre de recettes distinctes à partir duquel l'invite est proposée. */
const VIEWS_THRESHOLD = 2;

/**
 * Lit une clé de `localStorage` sans jamais lever.
 *
 * Le stockage peut être indisponible (navigation privée stricte, quota, blocage
 * par le navigateur) : la convention du thème est de continuer sans, jamais de
 * bloquer le rendu.
 *
 * @param {string} key Clé.
 * @returns {string|null}
 */
function readStorage(key) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

/**
 * Écrit une clé de `localStorage` sans jamais lever.
 *
 * @param {string} key   Clé.
 * @param {string} value Valeur.
 */
function writeStorage(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch {
    // Quota ou stockage bloqué : la fermeture visuelle a quand même lieu, elle
    // ne sera simplement pas mémorisée d'une page à l'autre.
  }
}

/**
 * Identifiants de recettes déjà consultées, pour la version courante.
 *
 * Le compteur est versionné comme la fermeture : changer la version de l'invite
 * repart d'un compteur neuf, sans quoi un abonné de longue date la verrait
 * réapparaître instantanément.
 *
 * @param {string} version Version courante.
 * @returns {number[]}
 */
function readViews(version) {
  const raw = readStorage(VIEWS_KEY);
  if (!raw) {
    return [];
  }

  try {
    const parsed = JSON.parse(raw);
    if (parsed?.v !== version || !Array.isArray(parsed.ids)) {
      return [];
    }
    return parsed.ids;
  } catch {
    return [];
  }
}

/**
 * Enregistre la consultation d'une recette et renvoie le total distinct.
 *
 * @param {string} version Version courante.
 * @param {number} postId  Identifiant de la recette consultée.
 * @returns {number} Nombre de recettes distinctes consultées.
 */
function recordView(version, postId) {
  const ids = readViews(version);

  if (postId > 0 && !ids.includes(postId)) {
    ids.push(postId);
    writeStorage(VIEWS_KEY, JSON.stringify({ v: version, ids }));
  }

  return ids.length;
}

/**
 * Identifiant de la recette affichée, ou 0 hors page recette.
 *
 * S'appuie sur les classes que WordPress pose sur `<body>` : `single-recipe`
 * pour le type de contenu, `postid-<n>` pour l'identifiant.
 *
 * @returns {number}
 */
function currentRecipeId() {
  const { body } = document;

  if (!body.classList.contains('single-recipe')) {
    return 0;
  }

  const match = /(?:^|\s)postid-(\d+)(?:\s|$)/.exec(body.className);

  return match ? Number.parseInt(match[1], 10) : 0;
}

/**
 * Affiche un message dans la zone de retour de l'invite.
 *
 * @param {HTMLElement} node    Zone de retour.
 * @param {string}      text    Texte.
 * @param {boolean}     isError Vrai pour styliser en erreur.
 */
function setFeedback(node, text, isError) {
  if (!node || text === '') {
    return;
  }
  node.textContent = text;
  node.classList.toggle('push-prompt__feedback--error', Boolean(isError));
  node.hidden = false;
}

/**
 * Initialise l'invite, si elle est présente et si toutes les conditions sont
 * réunies.
 */
export function init() {
  const root = document.querySelector('[data-push-prompt]');
  if (!root || !config()) {
    return;
  }

  const version = root.dataset.version ?? '';

  // Le compteur s'incrémente à CHAQUE page recette, y compris quand l'invite ne
  // sera pas montrée : c'est ce qui permet au seuil d'être atteint un jour.
  const views = recordView(version, currentRecipeId());

  // Une invite déjà fermée pour cette version ne réapparaît pas.
  if (readStorage(DISMISSED_KEY) === version) {
    return;
  }

  // Permission déjà tranchée dans un sens ou dans l'autre : l'invite n'a plus
  // d'objet. `granted` = l'utilisateur reçoit déjà ; `denied` = le navigateur
  // ne redemandera plus rien, l'invite ne pourrait qu'échouer.
  if (!isSupported() || permission() !== 'default') {
    return;
  }

  if (views < VIEWS_THRESHOLD) {
    return;
  }

  const accept = root.querySelector('[data-push-prompt-accept]');
  const dismiss = root.querySelector('[data-push-prompt-dismiss]');
  const feedback = root.querySelector('.push-prompt__feedback');

  root.hidden = false;

  dismiss?.addEventListener('click', () => {
    writeStorage(DISMISSED_KEY, version);
    root.remove();
  });

  accept?.addEventListener('click', async () => {
    accept.disabled = true;

    const result = await enablePush();

    if (result.ok) {
      // Fermeture mémorisée aussi en cas de succès : l'invite ne doit pas
      // revenir sur une autre page avant que le HTML rendu par le serveur ne
      // tienne compte du nouvel opt-in (page en cache, navigation immédiate).
      writeStorage(DISMISSED_KEY, version);
      setFeedback(feedback, message('on'), false);
      window.setTimeout(() => root.remove(), 2000);
      return;
    }

    setFeedback(feedback, message(result.reason), true);

    // Refus définitif : l'invite ne peut plus rien obtenir, on la retire et on
    // mémorise, plutôt que de la laisser proposer un geste sans effet.
    if (result.reason === 'denied') {
      writeStorage(DISMISSED_KEY, version);
      accept.remove();
      return;
    }

    accept.disabled = false;
  });
}
