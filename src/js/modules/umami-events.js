/**
 * Module Umami — events custom qui ne peuvent pas être déclaratifs.
 *
 * La majorité des events passe par les attributs `data-umami-event` posés côté
 * PHP (cf. inc/analytics/umami.php) : le script Umami les écoute nativement,
 * sans une ligne de JS. Ce module ne couvre que les cas qui échappent au clic
 * simple sur un élément :
 *
 *  - `subscribe_offer_select` : ne compter que l'OUVERTURE d'un accordéon
 *    d'offre (un attribut natif compterait aussi les replis) ;
 *  - `newsletter_submit` / `recipe_favorite` : succès d'un appel REST, signalé
 *    par un CustomEvent neutre émis par le module métier concerné ;
 *  - `recipe_search` : soumission de formulaire, avec normalisation de la
 *    requête ;
 *  - `login_success` / `login_error` / `subscribe_complete` : états portés par
 *    le rendu serveur (cookie éphémère ou attribut), lus au chargement.
 *
 * Tous les events émis ici portent la dimension `is_subscriber` (voir track()),
 * de même que les events déclaratifs côté PHP : c'est ce qui permet le segment
 * « Abonnés vs Non-abonnés » dans Umami.
 *
 * Aucune donnée personnelle n'est émise : ni e-mail, ni nom, ni identifiant de
 * compte. Les seuls identifiants transmis sont des ID de contenu publics et des
 * slugs d'offre. `is_subscriber` est un booléen agrégé, non un identifiant.
 *
 * Tolérance à l'absence d'Umami (bloqueur de pub, script indisponible) : tous
 * les appels passent par track(), qui sort sans rien faire si `window.umami`
 * n'existe pas. Aucune erreur console dans ce cas.
 *
 * @package 180c
 */

/** Longueur maximale d'une requête de recherche transmise. */
const MAX_QUERY_LENGTH = 50;

/** Cookie éphémère posé par PHP au `wp_login` (cf. inc/analytics/umami.php). */
const LOGIN_FLAG_COOKIE = '_180c_umami_login';

/** Attente maximale de la disponibilité du script Umami (ms). */
const READY_TIMEOUT = 2000;

/** Intervalle de scrutation de `window.umami` (ms). */
const READY_INTERVAL = 100;

/**
 * Nom de la propriété de segmentation « abonné », jointe à TOUS les events.
 *
 * Doit rester STRICTEMENT identique à _180C_UMAMI_SUBSCRIBER_KEY côté PHP
 * (inc/analytics/umami.php), qui la pose en attribut sur les events déclaratifs.
 * Une orthographe divergente (`is-subscriber`) créerait une seconde propriété
 * dans Umami et scinderait le segment « Abonnés vs Non-abonnés » en deux.
 */
const SUBSCRIBER_KEY = 'is_subscriber';

/**
 * Statut d'abonnement injecté par PHP dans `window._umamiSubscriber`.
 *
 * Chaîne 'true' / 'false' et non booléen : c'est le format des propriétés
 * d'event Umami, et celui que posent les attributs `data-umami-event-*`.
 *
 * Relu à CHAQUE émission plutôt que mémoïsé au chargement du module : les
 * contenus injectés après coup (CTA ajoutés en AJAX, paywall rendu tardivement)
 * doivent porter la même valeur, et un futur code serveur pourra réécrire le
 * global sans que ce module ait à s'en soucier.
 *
 * Repli sur 'false' si le global est absent (script inline non rendu, mesure
 * désactivée) : jamais `undefined`, qui créerait une troisième valeur parasite
 * dans le segment.
 *
 * @returns {string} 'true' ou 'false'.
 */
function subscriberValue() {
  return window._umamiSubscriber === 'true' ? 'true' : 'false';
}

/**
 * Émet un event Umami. No-op silencieux si le script est absent ou bloqué.
 *
 * La dimension de segmentation est ajoutée ici, en un point de passage unique :
 * tous les events du module transitent par cette fonction, y compris ceux
 * différés par trackWhenReady(). Aucun site d'appel n'a à y penser.
 *
 * @param {string} name Nom de l'event (snake_case).
 * @param {Object} [data] Données jointes.
 */
function track(name, data) {
  if (typeof window.umami === 'undefined') return;

  try {
    // `data` est souvent omis (login_success) : l'étalement d'undefined donne
    // un objet vide, l'event part donc avec la seule dimension d'abonnement.
    window.umami.track(name, { ...data, [SUBSCRIBER_KEY]: subscriberValue() });
  } catch {
    // La mesure ne doit jamais interrompre le parcours utilisateur.
  }
}

/** File des events de chargement en attente du script Umami. */
const pending = [];

/** Garde d'armement du vidage : un seul jeu d'écouteurs par page. */
let flushArmed = false;

/**
 * Vide la file dans Umami.
 */
function flushPending() {
  while (pending.length) {
    const [name, data] = pending.shift();
    track(name, data);
  }
}

/**
 * Abandonne la file. Appelé au refus et à l'expiration de l'attente.
 */
function dropPending() {
  pending.length = 0;
}

/**
 * Attend la disponibilité de `window.umami`, au plus READY_TIMEOUT.
 *
 * Le compte à rebours ne démarre qu'une fois le chargement DÉCLENCHÉ (cf.
 * `armFlush`) : depuis que le script est soumis au consentement, il peut
 * arriver plusieurs minutes après le chargement de la page, et une attente
 * lancée trop tôt expirerait toujours avant l'acceptation.
 *
 * @param {Function} done Appelée quand Umami répond.
 */
function whenUmamiReady(done) {
  if (typeof window.umami !== 'undefined') {
    done();
    return;
  }

  let waited = 0;
  const timer = setInterval(() => {
    waited += READY_INTERVAL;

    if (typeof window.umami !== 'undefined') {
      clearInterval(timer);
      done();
      return;
    }

    if (waited >= READY_TIMEOUT) {
      clearInterval(timer);
      dropPending();
    }
  }, READY_INTERVAL);
}

/**
 * Arme le vidage de la file, une seule fois par page.
 *
 * Deux cas, selon que le consentement est déjà acquis ou non :
 *  - déjà acquis : le chargeur PHP a appelé `load()` dans le <head>, le script
 *    arrive dans la foulée — on l'attend directement ;
 *  - pas encore : on attend la décision de la CMP (`consent:updated`, émis par
 *    src/js/modules/consent.js). Acceptation → attente puis vidage ;
 *    refus → abandon, rien ne part jamais.
 */
function armFlush() {
  if (flushArmed) return;
  flushArmed = true;

  if (window._180c?.umami?.loaded) {
    whenUmamiReady(flushPending);
    return;
  }

  document.addEventListener(
    'consent:updated',
    (event) => {
      if (event.detail?.analytics_storage === 'granted') {
        whenUmamiReady(flushPending);
      } else {
        dropPending();
      }
    },
    { once: true }
  );
}

/**
 * Émet un event dès que le script Umami est disponible.
 *
 * Utilisé pour les events déclenchés au CHARGEMENT de la page. Umami n'étant
 * plus injecté qu'après consentement, ces events sont mis en FILE et émis à
 * l'acceptation — sans quoi ils seraient tous perdus, le visiteur mettant
 * nécessairement plus que READY_TIMEOUT à lire la modale et à choisir.
 *
 * Au refus, la file est abandonnée : aucun event n'est émis, jamais.
 *
 * NB : les events DÉCLARATIFS (`data-umami-event`, posés en PHP par
 * `_180c_umami_attrs()`) ne sont volontairement PAS mis en file. Ils ne se
 * déclenchent qu'au clic sur un élément de la page, or la modale de
 * consentement couvre tout le viewport (`position: fixed; inset: 0`) tant
 * qu'aucun choix n'est fait : un tel clic est impossible avant décision. La
 * fenêtre à couvrir est vide, et la couvrir imposerait de réimplémenter la
 * liaison d'Umami au risque de doubler les events une fois le vrai script lié.
 *
 * @param {string} name Nom de l'event.
 * @param {Object} [data] Données jointes.
 */
function trackWhenReady(name, data) {
  if (typeof window.umami !== 'undefined') {
    track(name, data);
    return;
  }

  pending.push([name, data]);
  armFlush();
}

/**
 * Lit un cookie par son nom.
 *
 * @param {string} name Nom du cookie.
 * @returns {string|null} Valeur décodée, ou null si absent.
 */
function readCookie(name) {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Supprime un cookie posé sur le chemin racine.
 *
 * @param {string} name Nom du cookie.
 */
function deleteCookie(name) {
  document.cookie = `${name}=; path=/; max-age=0; samesite=lax`;
}

/**
 * `newsletter_submit` — inscription newsletter réussie.
 *
 * Alimenté par le CustomEvent `180c:newsletter:subscribed` émis par
 * src/js/modules/newsletter.js sur la réponse REST OK. `location` reprend
 * l'emplacement déclaré du formulaire (`data-ga-location`).
 */
function bindNewsletter() {
  document.addEventListener('180c:newsletter:subscribed', (event) => {
    track('newsletter_submit', {
      location: event.detail?.location || 'unknown',
    });
  });
}

/**
 * `recipe_favorite` — recette ajoutée au carnet.
 *
 * Alimenté par le CustomEvent `180c:favorite:added` émis par
 * src/js/modules/favorites.js. Seuls les AJOUTS sont mesurés (les retraits ne
 * font pas partie du plan de marquage).
 */
function bindFavorites() {
  document.addEventListener('180c:favorite:added', (event) => {
    const recipeId = event.detail?.recipeId;
    if (!recipeId) return;

    track('recipe_favorite', { recipe_id: String(recipeId) });
  });
}

/**
 * `recipe_search` — soumission d'un formulaire de recherche.
 *
 * Écoute déléguée sur tous les formulaires `role="search"` (recherche globale,
 * side menu, archive recettes, carnet). La requête est normalisée : espaces
 * rognés, minuscules, tronquée à MAX_QUERY_LENGTH. Une recherche vide n'est pas
 * mesurée.
 */
function bindSearch() {
  document.addEventListener('submit', (event) => {
    const form = event.target?.closest?.('form[role="search"]');
    if (!form) return;

    const input = form.querySelector('input[name="s"], input[type="search"]');
    const query = (input?.value ?? '').trim().toLowerCase().slice(0, MAX_QUERY_LENGTH);
    if (!query) return;

    track('recipe_search', { query });
  });
}

/**
 * `subscribe_offer_select` — ouverture d'une offre sur /abonnement/.
 *
 * Le déclencheur d'accordéon porte un crochet neutre `data-umami-offer` plutôt
 * qu'un `data-umami-event` : on lit l'état APRÈS le toggle de subscribe.js pour
 * ne compter que les ouvertures, un attribut natif comptant aussi les replis.
 */
function bindSubscribeOffers() {
  document.addEventListener('click', (event) => {
    const trigger = event.target?.closest?.('[data-umami-offer]');
    if (!trigger) return;

    const offer = trigger.dataset.umamiOffer;
    if (!offer) return;

    // Lecture au tick suivant : l'état final de l'accordéon fait foi, quel que
    // soit l'ordre d'exécution des écouteurs.
    setTimeout(() => {
      if (trigger.getAttribute('aria-expanded') !== 'true') return;

      track('subscribe_offer_select', { offer });
    }, 0);
  });
}

/**
 * `subscribe_complete` — affichage de la confirmation post-paiement.
 *
 * Le marqueur est rendu par PHP sur « order-received » uniquement quand la
 * commande contient un abonnement (cf. inc/analytics/umami.php).
 *
 * Garde-fou anti-rechargement : l'ID de commande sert de clé sessionStorage et
 * n'est PAS transmis. Si sessionStorage est indisponible (navigation privée
 * stricte), on émet quand même — mieux vaut un doublon rare qu'un trou.
 */
function trackSubscribeComplete() {
  const marker = document.querySelector('[data-umami-subscribe-complete]');
  if (!marker) return;

  const orderKey = `_180c_umami_subscribe_complete_${marker.dataset.umamiSubscribeComplete}`;

  try {
    if (sessionStorage.getItem(orderKey)) return;
    sessionStorage.setItem(orderKey, '1');
  } catch {
    // sessionStorage inaccessible : on poursuit sans déduplication.
  }

  const offer = marker.dataset.umamiOffer;
  trackWhenReady('subscribe_complete', offer ? { offer } : undefined);
}

/**
 * `login_success` — connexion réussie.
 *
 * Le succès est constaté côté serveur (hook `wp_login`), qui pose un cookie
 * éphémère. On le consomme UNE SEULE FOIS au chargement suivant : le cookie est
 * supprimé avant l'émission, donc un rechargement de page ne réémet rien.
 */
function trackLoginSuccess() {
  if (!readCookie(LOGIN_FLAG_COOKIE)) return;

  deleteCookie(LOGIN_FLAG_COOKIE);
  trackWhenReady('login_success');
}

/**
 * `login_error` — erreur affichée sur le formulaire de connexion.
 *
 * `reason` est le CODE d'erreur générique rendu par PHP (credentials, empty,
 * blocked, nonce, turnstile) — jamais l'identifiant saisi.
 */
function trackLoginError() {
  const holder = document.querySelector('[data-umami-login-error]');
  const reason = holder?.dataset.umamiLoginError;
  if (!reason) return;

  trackWhenReady('login_error', { reason });
}

/**
 * Initialisation.
 */
function init() {
  bindNewsletter();
  bindFavorites();
  bindSearch();
  bindSubscribeOffers();

  trackLoginSuccess();
  trackLoginError();
  trackSubscribeComplete();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

export { track, trackWhenReady };
