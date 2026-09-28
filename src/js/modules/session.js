/**
 * Session — nonces frais et rejeu unique sur nonce invalide.
 *
 * Pourquoi
 * --------
 * WP Super Cache sert le HTML anonyme depuis un fichier statique. Les nonces
 * inlinés par `inc/enqueue.php` (`_180c.nonce`) et `inc/woo/cart.php`
 * (`_180cCart.nonce`) vieillissent donc avec la page, alors qu'un nonce
 * WordPress expire au bout de 24 h. Sur une page en cache depuis longtemps,
 * l'ajout au panier et les appels REST échouent — sans message, le bouton ne
 * fait simplement rien.
 *
 * Depuis le retrait des nonces du HTML mis en cache, ce module en est la SEULE
 * source : il les lit sur `GET /180c/v1/session` (route non cachée, cf.
 * inc/rest/session.php) et rejoue une fois toute requête refusée pour cause de
 * nonce invalide.
 *
 * La récupération est PARESSEUSE, à dessein. Un appel au chargement ajoutait
 * une requête à chaque page vue, alors que l'immense majorité des visiteurs ne
 * soumet rien. `ensureSession()` ne part donc qu'au premier besoin réel — ou
 * au premier signe d'intention, si un déclencheur l'a amorcée avant le clic.
 *
 * Contrat
 * -------
 *   restNonce() / cartNonce()  → nonce COURANT, jamais une copie figée.
 *   ensureSession()            → garantit un nonce, au plus une requête/page.
 *   initIntentPrefetch()       → amorce ensureSession() au premier survol/focus.
 *   refreshSession()           → force une relecture (dédupliquée).
 *   restFetch()                → fetch REST avec X-WP-Nonce + rejeu unique.
 *
 * Les consommateurs doivent appeler `restNonce()` au moment de la requête et
 * NON figer la valeur dans une constante de module : une constante lue à
 * l'import capture le nonce périmé du HTML et le rafraîchissement n'a alors
 * plus aucun effet.
 *
 * @module session
 */

const REST_BASE = window._180c?.restUrl ?? '/wp-json/180c/v1/';

/**
 * État de connexion tel que le SERVEUR a rendu la page, lu avant toute
 * réécriture par `applySession()`.
 *
 * Sert de garde-fou : un nonce de session anonyme ne doit jamais remplacer
 * celui d'une page rendue pour un compte connecté (cf. `applySession`).
 */
const PAGE_LOGGED_IN = window._180c?.isLoggedIn === true;

/** Requête de rafraîchissement en vol, partagée pour éviter les appels en rafale. */
let inflight = null;

/**
 * Promesse mémorisée d'`ensureSession()`.
 *
 * Garde par PROMESSE et non par booléen posé après résolution : plusieurs
 * déclencheurs peuvent se croiser pendant le vol (survol du panier puis focus
 * sur le champ e-mail, par exemple). Un booléen ne serait posé qu'à l'arrivée
 * de la réponse et laisserait partir des requêtes concurrentes d'ici là ; la
 * promesse, elle, est partagée dès le premier appel.
 */
let ensured = null;

/**
 * Nonce courant de l'API REST WP (en-tête `X-WP-Nonce`).
 *
 * @returns {string}
 */
export function restNonce() {
  return window._180c?.nonce ?? '';
}

/**
 * Nonce courant des endpoints `wc-ajax` du panier.
 *
 * @returns {string}
 */
export function cartNonce() {
  return window._180cCart?.nonce ?? '';
}

/**
 * Écrit les nonces frais dans tous les porteurs de la page.
 *
 * `_180cFavorites` est un objet distinct de `_180c` et doit être mis
 * à jour lui aussi, sans quoi les favoris continueraient d'envoyer l'ancienne
 * valeur.
 *
 * Les champs cachés `nl_nonce` et `cf_nonce` portent des actions différentes
 * (`180c_newsletter_public`, `180c_contact_public`) et sont de toute façon
 * rafraîchis juste avant l'envoi par `fetchActionNonce()`. On les réécrit tout
 * de même : cela ne sert pas le cas nominal mais le REPLI — si le réseau lâche
 * à la soumission, le champ porte alors une valeur récupérée au premier signe
 * d'intention, et non la valeur vide du HTML en cache.
 *
 * @param {{nonce?: string, cart_nonce?: string, nl_nonce?: string, is_logged_in?: boolean}} data
 */
function applySession(data) {
  // Repli de sûreté. `GET /session` réhydrate la session depuis le cookie
  // (inc/rest/session.php) ; si la garde d'origine de cette route n'a pas pu
  // conclure, elle répond en anonyme. Écraser alors les nonces d'une page
  // rendue pour un compte connecté remplacerait une valeur VALIDE par une
  // valeur systématiquement rejetée — exactement la panne que ce module doit
  // éviter. On garde les nonces de la page et on n'applique que l'état.
  //
  // Le cas « déconnecté dans un autre onglet » aboutit lui aussi ici : les
  // nonces conservés ne vaudront plus rien, mais aucun nonce ne vaudrait plus
  // rien pour une session close — et `isLoggedIn` est mis à jour, lui.
  const isDowngrade = PAGE_LOGGED_IN && data?.is_logged_in === false;

  if (!isDowngrade && typeof data?.nonce === 'string' && data.nonce !== '') {
    window._180c = window._180c || {};
    window._180c.nonce = data.nonce;

    if (window._180cFavorites) {
      window._180cFavorites.nonce = data.nonce;
    }
  }

  if (!isDowngrade && typeof data?.cart_nonce === 'string' && data.cart_nonce !== '') {
    window._180cCart = window._180cCart || {};
    window._180cCart.nonce = data.cart_nonce;
  }

  if (!isDowngrade && typeof data?.nl_nonce === 'string' && data.nl_nonce !== '') {
    document
      .querySelectorAll('input[name="nl_nonce"]')
      .forEach((field) => {
        field.value = data.nl_nonce;
      });
  }

  // Même mécanique pour le formulaire de contact : le champ est rendu vide (la
  // page est mise en cache), contact.js va chercher un nonce frais sur
  // `contact/nonce` juste avant l'envoi, et cette écriture ne sert que de repli
  // si le réseau lâche à ce moment-là.
  if (!isDowngrade && typeof data?.cf_nonce === 'string' && data.cf_nonce !== '') {
    document
      .querySelectorAll('input[name="cf_nonce"]')
      .forEach((field) => {
        field.value = data.cf_nonce;
      });
  }

  if (typeof data?.is_logged_in === 'boolean') {
    window._180c = window._180c || {};
    window._180c.isLoggedIn = data.is_logged_in;
  }
}

/**
 * Récupère des nonces frais et les applique.
 *
 * En cas d'échec (réseau coupé, 5xx, endpoint absent), les nonces inlinés sont
 * conservés tels quels et la promesse se résout quand même : un rafraîchissement
 * raté ne doit jamais bloquer l'interface, la requête d'origine part avec la
 * valeur du HTML — qui est valide dans l'écrasante majorité des cas.
 *
 * Les appels concurrents partagent la même requête.
 *
 * @returns {Promise<boolean>} true si des nonces frais ont été appliqués.
 */
export function refreshSession() {
  if (inflight) {
    return inflight;
  }

  inflight = fetch(`${REST_BASE}session`, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
    cache: 'no-store',
  })
    .then((res) => (res.ok ? res.json() : null))
    .then((data) => {
      if (!data) {
        return false;
      }
      applySession(data);
      return true;
    })
    .catch(() => false)
    .finally(() => {
      inflight = null;
    });

  return inflight;
}

/**
 * Détecte un refus pour cause de nonce invalide.
 *
 * Deux surfaces, deux signatures :
 *  - API REST WP → 403 + code `rest_cookie_invalid_nonce` dans le corps ;
 *  - `wc-ajax` panier → 403 posé par `_180c_cart_ajax_guard()`, sans code.
 *
 * Le corps est lu sur un clone : la réponse d'origine doit rester consommable
 * par l'appelant si finalement on ne rejoue pas.
 *
 * @param {Response} response
 * @returns {Promise<boolean>}
 */
async function isInvalidNonce(response) {
  if (response.status !== 403) {
    return false;
  }

  try {
    const data = await response.clone().json();
    // Un 403 REST sans code identifié est un refus de permission légitime
    // (route réservée) : le rejouer ne servirait à rien.
    if (typeof data?.code === 'string') {
      return data.code === 'rest_cookie_invalid_nonce';
    }
  } catch {
    // Corps illisible : on retombe sur le seul statut.
  }

  return true;
}

/**
 * `fetch` vers l'API REST 180°C, avec nonce courant et rejeu unique.
 *
 * Au premier 403 « nonce invalide », rafraîchit la session et rejoue la requête
 * UNE fois. Pas de boucle : si le second essai échoue, la réponse est rendue
 * telle quelle à l'appelant, qui affiche son message d'erreur habituel.
 *
 * @param {string}      path    Chemin relatif au namespace (ex. `favorites`).
 * @param {RequestInit} [init]  Options fetch. Les en-têtes sont complétés,
 *                              jamais remplacés.
 * @returns {Promise<Response>}
 */
export async function restFetch(path, init = {}) {
  const url = path.startsWith('http') ? path : `${REST_BASE}${path}`;

  // Le HTML mis en cache ne porte plus de nonce : sans ce point d'entrée, le
  // tout premier appel partirait systématiquement sans `X-WP-Nonce` et se
  // ferait refuser. `ensureSession()` sort sans requête si un déclencheur
  // d'intention l'a déjà amorcée.
  await ensureSession();

  const send = () =>
    fetch(url, {
      credentials: 'same-origin',
      ...init,
      headers: {
        ...(init.headers ?? {}),
        'X-WP-Nonce': restNonce(),
      },
    });

  const response = await send();

  if (await isInvalidNonce(response)) {
    const refreshed = await refreshSession();
    if (refreshed) {
      return send();
    }
  }

  return response;
}

/**
 * Récupère un nonce frais sur une route dédiée, avec repli.
 *
 * Les formulaires publics (newsletter, contact) n'utilisent PAS le nonce
 * `wp_rest` mais un nonce d'action propre (`180c_newsletter_public`,
 * `_180C_CONTACT_NONCE_ACTION`), que `GET /session` ne porte pas — et n'a pas à
 * porter : ces actions sont indépendantes de la session REST. Chacune expose sa
 * route (`newsletter/nonce`, `contact/nonce`) ; cette fonction en
 * mutualise la consommation, seul point où la logique repli-si-échec est écrite.
 *
 * @param {string} path     Chemin de la route (ex. `newsletter/nonce`).
 * @param {string} fallback Nonce du champ caché, conservé si l'appel échoue.
 * @returns {Promise<string>}
 */
export async function fetchActionNonce(path, fallback) {
  try {
    const res = await fetch(`${REST_BASE}${path}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
    });
    const data = res.ok ? await res.json() : null;
    if (typeof data?.nonce === 'string' && data.nonce !== '') {
      return data.nonce;
    }
  } catch {
    // Réseau indisponible : le nonce du HTML reste valide dans la quasi-totalité
    // des cas, on ne bloque pas la soumission pour autant.
  }
  return fallback;
}

/**
 * Garantit qu'un nonce est disponible, au plus une requête par page.
 *
 * Sort immédiatement si un nonce est déjà en main — cas d'une page rendue pour
 * un compte connecté, ou d'un appel qui suit un déclencheur d'intention.
 * Sinon, délègue à `refreshSession()` et mémorise la promesse.
 *
 * Peut être appelée aussi souvent qu'on veut : c'est le point d'entrée des
 * déclencheurs d'intention comme des chemins de repli.
 *
 * @returns {Promise<boolean>}
 */
export function ensureSession() {
  if (restNonce() !== '' && cartNonce() !== '') {
    return Promise.resolve(true);
  }
  if (!ensured) {
    ensured = refreshSession();
  }
  return ensured;
}

/**
 * Éléments dont l'approche annonce une action qui aura besoin d'un nonce.
 *
 * Volontairement court : chaque entrée doit correspondre à un chemin qui finit
 * réellement sur `restFetch()` ou `postCart()`. Élargir cette liste ferait
 * repartir des requêtes sur des pages où personne ne soumet rien — exactement
 * ce que le passage en « à la demande » a supprimé.
 */
const INTENT_SELECTOR = [
  '[data-newsletter-form] input[type="email"]',
  // Le formulaire entier, et non son seul champ e-mail : on ne sait pas par
  // quel champ le visiteur commencera à le remplir.
  '#contact-form',
  '.js-cart-toggle',
  '.add_to_cart_button',
  '.single_add_to_cart_button',
].join(',');

/**
 * Amorce la session au premier signe d'intention.
 *
 * Sans cela, `ensureSession()` ne partirait qu'au submit ou au clic, ajoutant
 * son aller-retour à la latence perçue de l'action. Déclenché au survol et à
 * la prise de focus, le nonce est déjà là quand l'action arrive, et rien ne
 * part pour un visiteur qui se contente de lire.
 *
 * Délégation sur `document` : les formulaires et boutons d'ajout peuvent être
 * rendus après coup (fragments de panier, contenu injecté). `focusin` et
 * `pointerover` sont retenus parce qu'ils remontent — contrairement à `focus`
 * et `pointerenter`, qui imposeraient un écouteur par élément.
 *
 * Les écouteurs se retirent dès le premier déclenchement : la promesse est
 * mémorisée, les repasses n'auraient plus rien à faire.
 *
 * @returns {void}
 */
export function initIntentPrefetch() {
  const onIntent = (event) => {
    const target = event.target;
    if (!(target instanceof Element) || !target.closest(INTENT_SELECTOR)) {
      return;
    }
    document.removeEventListener('focusin', onIntent);
    document.removeEventListener('pointerover', onIntent);
    ensureSession();
  };

  document.addEventListener('focusin', onIntent, { passive: true });
  document.addEventListener('pointerover', onIntent, { passive: true });
}
