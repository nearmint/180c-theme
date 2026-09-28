/**
 * Checkout 180°C — interactions.
 *
 * Vanilla pur, AUCUNE dépendance jQuery, strictement isolé du JS WooCommerce :
 * on ne redéfinit aucun event WC, on n'intercepte pas l'update AJAX
 * (`update_checkout`) ni le rendu du paiement. Si `window.jQuery` existe, on ne
 * l'utilise pas.
 *
 * Périmètre :
 *  1. Accordéon coupon (notre déclencheur ; WC masque le formulaire à l'init).
 *  2. Verrou du récap déplié sur desktop (le <details> reste ouvert ≥ 1024px).
 *  3. États du bouton de paiement (disabled + aria-busy + label) au submit,
 *     reset sur apparition d'une .woocommerce-error.
 *  4. Montant dynamique dans le label du bouton (« Payer 39,00 € »), réactualisé
 *     après chaque update AJAX du récap.
 *
 * Validation : entièrement déléguée à WooCommerce (validation serveur + son
 * affichage natif). Le thème n'ajoute AUCUNE validation côté client, pour ne
 * pas dupliquer les messages d'erreur (« Ce champ est requis. » + « … est
 * obligatoire. »).
 */

const LG_QUERY = '(min-width: 1024px)';
const BASE_LABEL = 'Payer';
const BUSY_LABEL = 'Paiement en cours…';

let idleLabel = BASE_LABEL;

// add_payment_info (L4) : une seule émission par session de checkout.
let paymentInfoSent = false;

/* ------------------------------------------------------------------ *
 * 1. Accordéon coupon
 * ------------------------------------------------------------------ */

function initCoupon() {
  const trigger = document.querySelector( '.checkout__coupon-trigger' );
  const panel = document.querySelector( 'form.checkout_coupon' );
  if ( ! trigger || ! panel ) {
    return;
  }

  const setOpen = ( open ) => {
    trigger.setAttribute( 'aria-expanded', String( open ) );
    panel.style.display = open ? '' : 'none';
    if ( open ) {
      const input = panel.querySelector( '#coupon_code' );
      if ( input ) {
        input.focus();
      }
    }
  };

  // WC masque le formulaire à l'init ; on aligne notre état (replié).
  setOpen( false );

  // Un <button> déclenche déjà click sur Enter/Espace : un seul handler suffit.
  trigger.addEventListener( 'click', () => {
    setOpen( trigger.getAttribute( 'aria-expanded' ) !== 'true' );
  } );
}

/* ------------------------------------------------------------------ *
 * 2. Verrou du récap déplié sur desktop
 * ------------------------------------------------------------------ */

function initSummaryLock() {
  const details = document.querySelector( '.checkout__summary' );
  if ( ! details || details.tagName !== 'DETAILS' ) {
    return;
  }
  const summary = details.querySelector( '.checkout__summary-toggle' );
  const mq = window.matchMedia( LG_QUERY );
  const isDesktop = () => mq.matches;

  // Empêche le repli au clic sur desktop.
  if ( summary ) {
    summary.addEventListener( 'click', ( event ) => {
      if ( isDesktop() ) {
        event.preventDefault();
      }
    } );
  }

  // Ré-assertion sur l'event toggle (sécurité clavier / autres déclencheurs).
  details.addEventListener( 'toggle', () => {
    if ( isDesktop() && ! details.open ) {
      details.open = true;
    }
  } );

  const apply = () => {
    if ( isDesktop() ) {
      details.open = true;
    }
  };
  apply();

  if ( mq.addEventListener ) {
    mq.addEventListener( 'change', apply );
  } else {
    window.addEventListener( 'resize', apply );
  }
}

/* ------------------------------------------------------------------ *
 * 3 & 4. États du bouton + montant dynamique
 * ------------------------------------------------------------------ */

function getPlaceOrder() {
  return document.getElementById( 'place_order' );
}

function setButtonLabel( btn, text ) {
  // Évite toute mutation inutile (sinon boucle avec le MutationObserver récap).
  if ( btn.textContent !== text ) {
    btn.textContent = text;
  }
}

function readTotal() {
  const el = document.querySelector( '.order-total .amount' );
  return el ? el.textContent.trim() : '';
}

function refreshIdleLabel() {
  const btn = getPlaceOrder();
  if ( ! btn ) {
    return;
  }
  const total = readTotal();
  idleLabel = total ? `${ BASE_LABEL } ${ total }` : BASE_LABEL;
  if ( btn.getAttribute( 'aria-busy' ) !== 'true' ) {
    setButtonLabel( btn, idleLabel );
  }
}

function setBusy( btn ) {
  btn.disabled = true;
  btn.setAttribute( 'aria-busy', 'true' );
  setButtonLabel( btn, BUSY_LABEL );
}

function clearBusy( btn ) {
  btn.disabled = false;
  btn.removeAttribute( 'aria-busy' );
  setButtonLabel( btn, idleLabel );
}

function initButton( form ) {
  refreshIdleLabel();

  // Réactualise le montant après chaque update AJAX du récap (sans jQuery).
  const review = document.getElementById( 'order_review' );
  if ( review ) {
    const amountObserver = new MutationObserver( () => refreshIdleLabel() );
    amountObserver.observe( review, { childList: true, subtree: true } );
  }

  // États au submit (bubbling). On défère pour laisser WC sérialiser le form.
  // La validation est entièrement déléguée à WooCommerce : on passe le bouton
  // en état occupé, qui sera réinitialisé si une .woocommerce-error apparaît
  // (voir errorObserver ci-dessous).
  form.addEventListener( 'submit', () => {
    // L4 : l'info de paiement est saisie (Stripe Payment Element hosté) et
    // l'utilisateur valide → proxy fiable pour add_payment_info. Le thème reste
    // isolé du JS Stripe : on n'accède pas à l'instance Elements (cf. en-tête).
    trackAddPaymentInfo();
    const btn = getPlaceOrder();
    if ( btn ) {
      window.setTimeout( () => setBusy( btn ), 0 );
    }
  } );

  // Reset du bouton dès qu'une .woocommerce-error apparaît dans le form
  // (on n'écoute aucun event jQuery WC).
  const errorObserver = new MutationObserver( () => {
    if ( form.querySelector( '.woocommerce-error' ) ) {
      const btn = getPlaceOrder();
      if ( btn && btn.getAttribute( 'aria-busy' ) === 'true' ) {
        clearBusy( btn );
      }
    }
  } );
  errorObserver.observe( form, { childList: true, subtree: true } );
}

/* ------------------------------------------------------------------ *
 * 5. Accordéon paiement — affordance ARIA (chevron)
 *
 * Reflète l'état de l'accordéon (aria-expanded sur l'en-tête → chevron pivoté
 * en CSS) sur le radio natif sélectionné. On NE gère PAS le show/hide du
 * `.payment_box` : c'est le JS de WooCommerce (slideUp/slideDown) qui le pilote.
 * On se contente d'écouter passivement le `change` du radio (aucun
 * preventDefault, aucun event jQuery WC, aucune interception de l'update AJAX).
 *
 * Délégation sur `document` : le bloc `#payment` est remplacé à chaque
 * `update_checkout` (fragment AJAX) — un listener délégué survit au remplacement,
 * et le markup re-rendu porte déjà le bon aria-expanded côté serveur (chosen).
 * ------------------------------------------------------------------ */

function syncPaymentAccordion() {
  const radios = document.querySelectorAll(
    '#payment input[name="payment_method"]'
  );
  radios.forEach( ( radio ) => {
    const header = document.querySelector(
      `label.checkout-payment__header[for="${ radio.id }"]`
    );
    // aria-expanded n'existe que sur les en-têtes avec panneau (aria-controls).
    if ( header && header.hasAttribute( 'aria-controls' ) ) {
      header.setAttribute( 'aria-expanded', radio.checked ? 'true' : 'false' );
    }
  } );
}

function initPaymentAccordion() {
  document.addEventListener( 'change', ( event ) => {
    const target = event.target;
    if (
      target instanceof HTMLInputElement &&
      target.name === 'payment_method'
    ) {
      syncPaymentAccordion();
    }
  } );

  // Synchronise l'état initial (et reste sans effet si le bloc paiement est absent).
  syncPaymentAccordion();
}

/* ------------------------------------------------------------------ *
 * 6. Placeholder du champ de recherche select2 (sélecteur Pays)
 * ------------------------------------------------------------------ */

const COUNTRY_SEARCH_PLACEHOLDER = 'Rechercher un pays';

function initCountrySearchPlaceholder() {
  // select2 (WooCommerce) injecte le champ de recherche en fin de <body> à
  // l'ouverture du dropdown et lui donne le focus : le champ n'existe pas avant.
  // Délégation focusin (aucune dépendance jQuery, aucun MutationObserver
  // permanent). On ne cible que le dropdown du sélecteur Pays — l'id de la liste
  // de résultats vaut `select2-billing_country-results` / `…shipping_country…` —
  // afin de ne PAS poser ce libellé sur le select2 d'un éventuel champ « État ».
  document.addEventListener( 'focusin', ( event ) => {
    const field = event.target;
    if (
      ! ( field instanceof HTMLInputElement ) ||
      ! field.classList.contains( 'select2-search__field' ) ||
      field.placeholder
    ) {
      return;
    }

    const dropdown = field.closest( '.select2-dropdown' );
    const results = dropdown && dropdown.querySelector( '.select2-results__options' );
    if ( results && /country/i.test( results.id ) ) {
      field.placeholder = COUNTRY_SEARCH_PLACEHOLDER;
    }
  } );
}

/* ------------------------------------------------------------------ *
 * add_payment_info (L4) — tracking GA4
 *
 * Les champs Stripe (Payment Element) sont hostés dans une iframe : le thème,
 * volontairement isolé du JS WooCommerce/Stripe, n'a pas accès à l'instance
 * Elements pour écouter son event `change`. On émet donc add_payment_info au
 * moment où l'utilisateur valide la commande (proxy fiable, non fragile), une
 * seule fois par session. cf TRACKING_PLAN.md §4.1.
 * ------------------------------------------------------------------ */

function readTotalAmount() {
  const num = parseFloat( readTotal().replace( /[^\d,.-]/g, '' ).replace( ',', '.' ) );
  return Number.isFinite( num ) ? num : undefined;
}

function selectedPaymentType() {
  const checked = document.querySelector(
    '#payment input[name="payment_method"]:checked'
  );
  const id = checked ? checked.value : '';
  // Stripe = gateway encaissante (cf audit Phase 0) → "card" ; sinon l'id brut.
  if ( id === 'stripe' || id.indexOf( 'stripe' ) === 0 ) {
    return 'card';
  }
  return id || undefined;
}

function trackAddPaymentInfo() {
  if ( paymentInfoSent ) {
    return;
  }
  paymentInfoSent = true;

  const params = { currency: 'EUR' };
  const value = readTotalAmount();
  if ( value !== undefined ) {
    params.value = value;
  }
  const paymentType = selectedPaymentType();
  if ( paymentType ) {
    params.payment_type = paymentType;
  }
  window._180c?.ga4?.event?.( 'add_payment_info', params );
}

/* ------------------------------------------------------------------ *
 * Init
 * ------------------------------------------------------------------ */

export function init() {
  const form = document.querySelector( 'form.checkout' );
  if ( ! form ) {
    return;
  }

  initCoupon();
  initSummaryLock();
  initButton( form );
  initPaymentAccordion();
  initCountrySearchPlaceholder();
}
