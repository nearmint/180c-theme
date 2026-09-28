/**
 * Attribution des commandes WooCommerce — chargement après consentement.
 *
 * `inc/analytics/order-attribution-consent.php` retire `sourcebuster-js` et
 * `wc-order-attribution` de la file d'attente sur toutes les pages : avant
 * accord, aucun cookie `sbjs_*` n'est déposé. Ce module les réinjecte, dans
 * l'ordre, quand — et seulement quand — le visiteur accepte.
 *
 * Même mécanisme que `window._180c.ga4.load()` : injection dynamique, sans
 * rechargement de page. Un dequeue PHP conditionné au cookie serait inopérant,
 * le HTML étant servi par WP Super Cache — la variante « consentie » finirait en
 * cache et serait resservie à des visiteurs qui n'ont rien accepté.
 *
 * @module order-attribution-consent
 */

const CONSENT_COOKIE = '_180c_consent';

/**
 * Garde d'idempotence : les scripts ne sont injectés qu'une fois.
 *
 * Portée `window` et non module, pour la même raison que le dataset du trigger
 * dans side-menu.js : si l'entrée est évaluée deux fois (deux URLs pour le même
 * fichier), chaque instance a ses propres variables et un `let` local ne voit
 * pas l'injection de l'autre. `order-attribution.js` appelant
 * `customElements.define('wc-order-attribution-inputs', …)` sans garde, la
 * seconde injection lève un `NotSupportedError`.
 *
 * @returns {boolean}
 */
function isLoaded() {
  return true === window._180cOrderAttributionLoaded;
}

/**
 * Marque l'état d'injection.
 *
 * @param {boolean} value
 */
function setLoaded(value) {
  window._180cOrderAttributionLoaded = value;
}

/**
 * Lit l'état de consentement depuis le cookie.
 *
 * Lecture en JS et non en PHP, pour la même raison que le reste de la CMP : le
 * HTML est mutualisé entre tous les visiteurs anonymes par le cache page.
 *
 * @returns {Object|null}
 */
function storedConsent() {
  const raw = document.cookie
    .split('; ')
    .find((row) => row.startsWith(`${CONSENT_COOKIE}=`));

  if (!raw) return null;

  try {
    return JSON.parse(decodeURIComponent(raw.split('=')[1]));
  } catch {
    return null;
  }
}

/**
 * L'attribution marketing est-elle autorisée ?
 *
 * Elle relève de `ad_storage` et non d'`analytics_storage` : sourcebuster trace
 * la source d'acquisition dans le temps pour attribuer une commande à une
 * campagne, finalité publicitaire et non de mesure d'audience. La CMP étant
 * binaire (tout ou rien), les deux clés bougent de concert — le choix de clé
 * reste néanmoins celui qui décrit correctement la finalité.
 *
 * L'ancien format de cookie stockait des booléens ; il est toléré ici comme dans
 * consent.js.
 *
 * @param {Object|null} consent
 * @returns {boolean}
 */
function isGranted(consent) {
  if (!consent) return false;
  const value = consent.ad_storage;
  return typeof value === 'string' ? value === 'granted' : Boolean(value);
}

/**
 * Charge un script et résout à son exécution.
 *
 * @param {string} src
 * @returns {Promise<void>}
 */
function injectScript(src) {
  return new Promise((resolve, reject) => {
    const tag = document.createElement('script');
    tag.src = src;
    tag.async = false; // Préserve l'ordre d'exécution des deux fichiers.
    tag.onload = () => resolve();
    tag.onerror = () => reject(new Error(`Échec du chargement de ${src}`));
    document.head.appendChild(tag);
  });
}

/**
 * Injecte le namespace puis les deux scripts, dans l'ordre.
 *
 * `window.wc_order_attribution` doit exister AVANT `order-attribution.js`, qui
 * lit `wc_order_attribution.params` dès sa première ligne. En temps normal c'est
 * le `wp_localize_script()` de WooCommerce qui le pose ; il disparaît avec le
 * dequeue, d'où le payload servi par le PHP du thème.
 *
 * @returns {Promise<void>}
 */
async function loadAttribution() {
  if (isLoaded()) return;

  const config = window._180cOrderAttribution;
  if (!config?.scripts?.length) return;

  setLoaded(true);

  window.wc_order_attribution = {
    ...(window.wc_order_attribution ?? {}),
    ...config.data,
  };

  try {
    // Séquentiel et non Promise.all : order-attribution.js dépend de `sbjs`,
    // défini par sourcebuster.js.
    for (const src of config.scripts) {
      await injectScript(src);
    }
  } catch {
    // Script bloqué (adblock, réseau) : l'attribution est perdue, le tunnel
    // d'achat n'est pas affecté. Rien à signaler au visiteur.
    setLoaded(false);
  }
}

/**
 * Initialise : charge si le consentement est déjà acquis, sinon écoute la CMP.
 */
function init() {
  if (isGranted(storedConsent())) {
    loadAttribution();
  }
}

// `consent:updated` est émis par consent.js à chaque choix, acceptation
// initiale comme changement d'avis depuis le footer.
document.addEventListener('consent:updated', (event) => {
  if (isGranted(event.detail)) {
    loadAttribution();
  }
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}

export { loadAttribution };
