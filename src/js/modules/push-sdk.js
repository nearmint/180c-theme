/**
 * Web push — chargement à la demande du SDK OneSignal et bascule de l'opt-in.
 *
 * Module PARTAGÉ entre les deux points d'entrée : le toggle « Mon compte »
 * (`push-optin.js`) et l'invite flottante (`push-prompt.js`). Les deux
 * exécutent strictement le même chemin d'activation — dupliquer cette logique
 * garantirait qu'elle diverge.
 *
 * RÈGLE ABSOLUE : rien de OneSignal n'est chargé avant une action explicite de
 * l'utilisateur. Aucun script, aucun service worker, aucune écriture navigateur
 * au chargement de la page. C'est ce qui dispense d'une catégorie de
 * consentement supplémentaire : tant que l'utilisateur n'a pas cliqué, il n'y a
 * rien à consentir. Ne jamais appeler `loadSdk()` depuis un `init()`.
 *
 * La configuration vient de `window._180c.push`, imprimé par
 * `inc/push/config-js.php` UNIQUEMENT pour un compte logué et abonné. Son
 * absence est donc la vraie garde d'éligibilité : sans elle, ce module ne peut
 * rien faire.
 *
 * @module push-sdk
 */

import { restFetch } from './session.js';

/** URL du SDK page. La version DOIT rester alignée sur celle du worker. */
const SDK_URL = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';

/** Promesse de chargement du SDK, partagée pour éviter les doubles injections. */
let sdkPromise = null;

/**
 * Configuration serveur du web push, ou `null` si l'utilisateur n'est pas
 * éligible.
 *
 * Lue à CHAQUE appel et non figée dans une constante de module : `window._180c`
 * est complété par plusieurs scripts, et un import peut s'exécuter avant eux.
 *
 * @returns {Object|null}
 */
export function config() {
  return window._180c?.push ?? null;
}

/**
 * Libellé traduit, servi par PHP.
 *
 * @param {string} key Clé du catalogue.
 * @returns {string}
 */
export function message(key) {
  return config()?.messages?.[key] ?? '';
}

/**
 * Indique si le navigateur gère le web push.
 *
 * Les trois API sont nécessaires et distinctes : Safari a longtemps exposé
 * `Notification` sans `PushManager`. Sur iOS, elles n'apparaissent que si le
 * site a été ajouté à l'écran d'accueil.
 *
 * @returns {boolean}
 */
export function isSupported() {
  return (
    typeof window !== 'undefined' &&
    'Notification' in window &&
    'serviceWorker' in navigator &&
    'PushManager' in window
  );
}

/**
 * État de la permission navigateur : `granted`, `denied` ou `default`.
 *
 * @returns {string}
 */
export function permission() {
  return isSupported() ? Notification.permission : 'unsupported';
}

/**
 * Injecte le SDK OneSignal, une seule fois par page.
 *
 * @returns {Promise<Object>} Résout sur l'instance `OneSignal` initialisée.
 */
function loadSdk() {
  if (sdkPromise) {
    return sdkPromise;
  }

  const cfg = config();

  sdkPromise = new Promise((resolve, reject) => {
    // File d'attente officielle du SDK v16 : le tableau est rempli avant que le
    // script soit chargé, et vidé par le SDK une fois prêt.
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    window.OneSignalDeferred.push(async (OneSignal) => {
      try {
        await OneSignal.init({
          appId: cfg.appId,
          // Chemin RELATIF à l'origine : le worker est servi à la racine par
          // inc/push/worker.php, précisément pour que son scope couvre tout le
          // site. Un chemin sous /wp-content/ ne contrôlerait rien.
          serviceWorkerPath: cfg.workerPath,
          serviceWorkerParam: { scope: '/' },
          autoResubscribe: true,
          // Aucune invite automatique : elle est pilotée par le thème, au
          // moment choisi par l'utilisateur.
          //
          // ⚠️ Ces deux clés ne sont PAS la garantie. `promptOptions` passé à
          // init() écrase la configuration de prompts de la console, et
          // `autoPrompt` n'est pas une option de premier niveau documentée en
          // v16 (elle vivait sous `promptOptions.slidedown` auparavant) : si le
          // SDK ignore les clés inconnues, elle est inerte. La seule garantie
          // réelle est la désactivation des invites natives DANS LA CONSOLE
          // (slide prompt, bell, category prompt). Ces clés sont une ceinture,
          // pas la bretelle.
          autoPrompt: false,
          promptOptions: { slidedown: { enabled: false } },
        });
        resolve(OneSignal);
      } catch (error) {
        reject(error);
      }
    });

    const script = document.createElement('script');
    script.src = SDK_URL;
    script.defer = true;
    script.onerror = () => reject(new Error('sdk_load_failed'));
    document.head.appendChild(script);
  });

  return sdkPromise;
}

/**
 * Enregistre l'opt-in côté serveur.
 *
 * Passe par `restFetch()` et NON par un `fetch` direct : le nonce n'est plus
 * inliné dans le HTML depuis qu'il est mis en cache par WP Super Cache, et
 * `session.js` en est la seule source. Un `const NONCE = window._180c?.nonce`
 * lu à l'import capturerait une chaîne vide.
 *
 * @param {boolean} optin État à enregistrer.
 * @returns {Promise<Response>}
 */
function persistOptin(optin) {
  return restFetch('push/account-optin', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ optin }),
  });
}

/**
 * Activation en vol, partagée par les deux points d'entrée.
 *
 * Le toggle « Mon compte » et l'invite flottante coexistent sur `/mon-compte/`
 * (l'invite est rendue depuis `footer.php`, donc sur toutes les pages). Chacun
 * désactive bien son propre contrôle pendant l'appel, mais rien n'empêchait de
 * déclencher l'un puis l'autre : deux `requestPermission()`, deux `login()` et
 * surtout deux POST concurrents. Le serveur est idempotent, donc sans dégât —
 * mais une seule activation doit produire un seul appel.
 *
 * Ce partage n'est possible que parce que Vite sort `push-sdk` en chunk commun
 * aux deux entrées : les deux modules voient la MÊME instance, donc la même
 * variable. Vérifié dans le manifest.
 */
let enableInFlight = null;

/**
 * Active le web push : permission, identité, tag, puis enregistrement serveur.
 *
 * Chemin unique, partagé par le toggle et l'invite. Aucun appel serveur n'est
 * fait si la permission n'est pas accordée : un opt-in enregistré alors que le
 * navigateur refuse produirait un toggle allumé qui ne reçoit rien.
 *
 * @returns {Promise<{ok: boolean, reason?: string}>}
 */
export function enablePush() {
  if (enableInFlight) {
    return enableInFlight;
  }

  enableInFlight = runEnablePush().finally(() => {
    enableInFlight = null;
  });

  return enableInFlight;
}

/**
 * Corps de l'activation. Ne jamais appeler directement : passer par
 * `enablePush()`, qui porte la garde d'appel concurrent.
 *
 * @returns {Promise<{ok: boolean, reason?: string}>}
 */
async function runEnablePush() {
  const cfg = config();

  if (!cfg) {
    return { ok: false, reason: 'unsupported' };
  }

  if (!isSupported()) {
    return { ok: false, reason: 'unsupported' };
  }

  if (permission() === 'denied') {
    return { ok: false, reason: 'denied' };
  }

  let OneSignal;
  try {
    OneSignal = await loadSdk();
  } catch {
    return { ok: false, reason: 'network' };
  }

  try {
    await OneSignal.Notifications.requestPermission();
  } catch {
    // Une exception ici vaut refus : on relit l'état réel juste après.
  }

  if (permission() !== 'granted') {
    return { ok: false, reason: permission() === 'denied' ? 'denied' : 'dismissed' };
  }

  try {
    // `login` AVANT `addTag` : le tag doit atterrir sur l'utilisateur identifié,
    // pas sur l'utilisateur anonyme que le SDK vient de créer.
    await OneSignal.login(cfg.userId);
    await OneSignal.User.addTag('subscription_status', cfg.status);
  } catch {
    // L'identité ou le tag ont échoué, mais la subscription existe. On
    // enregistre quand même l'opt-in : la synchro serveur différée réalignera
    // le tag, et une subscription non taggée vaut mieux qu'un opt-in perdu.
  }

  let response;
  try {
    response = await persistOptin(true);
  } catch {
    return { ok: false, reason: 'network' };
  }

  if (!response.ok) {
    return { ok: false, reason: 'serverError' };
  }

  if (cfg) {
    cfg.optin = true;
  }

  return { ok: true };
}

/**
 * Désactive le web push.
 *
 * Ne charge JAMAIS le SDK pour désactiver : si l'utilisateur n'a pas activé les
 * notifications sur cette page, le charger pour l'en désinscrire serait
 * exactement le chargement non sollicité que ce module évite. L'enregistrement
 * serveur suffit ; la subscription sera de toute façon écartée des envois, le
 * tag retombant à `none`.
 *
 * @returns {Promise<{ok: boolean, reason?: string}>}
 */
export async function disablePush() {
  if (sdkPromise) {
    try {
      const OneSignal = await sdkPromise;
      await OneSignal.User.PushSubscription.optOut();
    } catch {
      // Opt-out client en échec : l'enregistrement serveur reste la vérité.
    }
  }

  let response;
  try {
    response = await persistOptin(false);
  } catch {
    return { ok: false, reason: 'network' };
  }

  if (!response.ok) {
    return { ok: false, reason: 'serverError' };
  }

  const cfg = config();
  if (cfg) {
    cfg.optin = false;
  }

  return { ok: true };
}
