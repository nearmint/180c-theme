/**
 * Fiche produit 180°C — barre d'achat sticky + hook GA4.
 *
 * Pas de gestion de galerie ici : la fiche conserve la galerie native
 * WooCommerce (zoom/lightbox/slider via theme support). Ce module se limite à :
 *
 *  1. Barre d'achat fixe (bas d'écran) qui apparaît quand le CTA principal
 *     sort du viewport (IntersectionObserver, listeners passifs, pas de CLS :
 *     la barre est en position:fixed + translateY, hors flux).
 *  2. Reprise du CTA réel de la colonne `.summary`, selon sa nature :
 *     - CTA lien (produit externe pointant vers un marchand) → la barre rend
 *       un vrai `<a>` qui copie `href` / `target` / `rel`. Un lien reste un
 *       lien : clic milieu, Cmd-clic, « ouvrir dans un nouvel onglet » et
 *       aperçu de la destination fonctionnent, ce qu'un bouton proxy détruit.
 *     - CTA bouton (simple / abonnement) → `<button>` qui proxy le contrôle
 *       réel via `realCta.click()`, lequel soumet le `<form.cart>`. Aucune
 *       soumission de form n'est hardcodée.
 *  3. Hook GA4 (hook seul, pas de config) : push dataLayer `add_to_cart` +
 *     CustomEvent `180c:add_to_cart` à l'activation du CTA réel.
 *
 * Le retour d'ajout au panier (lien « Passer commande ») est rendu côté
 * serveur dans la notice WooCommerce (inc/woo/single-product.php), l'ajout
 * panier d'une fiche produit n'étant pas AJAX par défaut.
 */

/**
 * Récupère l'identifiant produit (best effort) pour le payload GA4.
 * @param {HTMLElement} cta CTA réel.
 * @returns {string}
 */
function readProductId( cta ) {
  if ( cta instanceof HTMLButtonElement && cta.value ) {
    return cta.value;
  }
  const match = document.body.className.match( /postid-(\d+)/ );
  return match ? match[ 1 ] : '';
}

/**
 * Pousse l'évènement add_to_cart vers dataLayer + un CustomEvent interne.
 * @param {{ id: string, name: string, price: string }} detail
 */
function pushAddToCart( detail ) {
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push( {
    event: 'add_to_cart',
    ecommerce: {
      items: [ { item_id: detail.id, item_name: detail.name, price: detail.price } ],
    },
  } );
  document.dispatchEvent(
    new CustomEvent( '180c:add_to_cart', { detail } )
  );
}

/**
 * Construit la barre sticky et l'ajoute au <body>.
 * @param {{ title: string, price: string, thumb: string, ctaLabel: string, disabled?: boolean, link?: { href: string, target: string, rel: string, classes: string[] } }} data
 * @returns {{ bar: HTMLElement, button: HTMLElement, live: HTMLElement }}
 */
function buildBar( data ) {
  const bar = document.createElement( 'div' );
  bar.className = 'product-sticky-bar';
  bar.setAttribute( 'role', 'region' );
  bar.setAttribute( 'aria-label', 'Ajouter au panier' );

  const info = document.createElement( 'div' );
  info.className = 'product-sticky-bar__info';

  if ( data.thumb ) {
    const img = document.createElement( 'img' );
    img.className = 'product-sticky-bar__thumb';
    img.src = data.thumb;
    img.alt = '';
    img.setAttribute( 'aria-hidden', 'true' );
    info.appendChild( img );
  }

  const meta = document.createElement( 'div' );
  meta.className = 'product-sticky-bar__meta';

  const title = document.createElement( 'span' );
  title.className = 'product-sticky-bar__title';
  title.textContent = data.title;
  meta.appendChild( title );

  if ( data.price ) {
    const price = document.createElement( 'span' );
    price.className = 'product-sticky-bar__price';
    price.textContent = data.price;
    meta.appendChild( price );
  }

  info.appendChild( meta );
  bar.appendChild( info );

  // CTA lien : on rend un <a>, pas un bouton qui simule un clic. Les classes
  // du CTA réel sont recopiées pour que la barre hérite du même habillage.
  const button = data.link
    ? document.createElement( 'a' )
    : document.createElement( 'button' );

  if ( data.link ) {
    button.href = data.link.href;
    if ( data.link.target ) {
      button.target = data.link.target;
    }
    if ( data.link.rel ) {
      button.rel = data.link.rel;
    }
    button.className = [
      ...data.link.classes,
      'btn',
      'btn--primary',
      'product-sticky-bar__cta',
    ].filter( ( c, i, all ) => c && all.indexOf( c ) === i ).join( ' ' );
  } else {
    button.type = 'button';
    button.className = 'btn btn--primary product-sticky-bar__cta';
  }

  // textContent et non innerHTML : le libellé vient de `data-sticky-label` ou
  // du texte du CTA réel, jamais de son markup — le « (nouvelle fenêtre) »
  // en .sr-only et l'icône du CTA n'ont rien à faire dans la barre.
  button.textContent = data.ctaLabel || 'Ajouter au panier';

  // Indisponibilité (rupture de stock / produit en librairie) : la barre
  // reflète l'état désactivé du CTA réel (libellé + bouton inerte).
  if ( data.disabled ) {
    button.disabled = true;
    button.setAttribute( 'aria-disabled', 'true' );
    button.classList.add( 'is-disabled' );
  }
  bar.appendChild( button );

  // Région d'annonce (lecteurs d'écran) pour le feedback d'activation.
  const live = document.createElement( 'span' );
  live.className = 'sr-only';
  live.setAttribute( 'aria-live', 'polite' );
  bar.appendChild( live );

  document.body.appendChild( bar );
  return { bar, button, live };
}

/**
 * Initialisation du module (appelée depuis main.js sur la fiche produit).
 */
export function init() {
  const summary = document.querySelector( '.product-page .summary' );
  if ( ! summary ) {
    return;
  }

  // CTA réel : bouton d'ajout (simple/abonnement) OU CTA externe, rendu par
  // _180c_product_external_cta() (inc/woo/single-product.php) — un <a> vers le
  // marchand, ou un <button> désactivé si _product_url est vide ou pointe sur
  // le site. Le badge .product__status (rendu par price.php pour tout produit
  // externe) n'est pas un CTA et n'est jamais repris ici.
  const realCta = /** @type {HTMLElement|null} */ (
    summary.querySelector( '.single_add_to_cart_button, .product__external-cta' )
  );
  if ( ! realCta ) {
    return;
  }

  // Données pour la barre + le payload GA4.
  const titleEl = summary.querySelector( '.product_title' )
    || document.querySelector( '.product-page h1' );
  const priceEl = summary.querySelector( '.price' );
  const thumbEl = document.querySelector(
    '.woocommerce-product-gallery__image img, .product-page__gallery img'
  );

  const detail = {
    id: readProductId( realCta ),
    name: titleEl ? titleEl.textContent.trim() : document.title,
    price: priceEl ? priceEl.textContent.trim() : '',
  };

  // Indisponibilité : CTA réel rendu désactivé côté serveur (rupture de stock
  // sur produit simple, ou produit externe « en librairie »). La barre se
  // contente alors d'afficher le même libellé, sans proxy ni hook GA4.
  const isDisabled = realCta.disabled
    || realCta.getAttribute( 'aria-disabled' ) === 'true'
    || realCta.classList.contains( 'is-disabled' );

  // Hook GA4 sur l'activation réelle du CTA (form submit OU clic lien externe).
  const form = realCta.closest( 'form.cart' );
  if ( ! isDisabled ) {
    if ( form ) {
      form.addEventListener( 'submit', () => pushAddToCart( detail ) );
    } else {
      realCta.addEventListener( 'click', () => pushAddToCart( detail ) );
    }
  }

  // Un CTA lien est miroité en <a> ; tout le reste garde le proxy historique.
  const link = realCta instanceof HTMLAnchorElement && ! isDisabled
    ? {
      href: realCta.href,
      target: realCta.target,
      rel: realCta.rel,
      classes: Array.from( realCta.classList ),
    }
    : null;

  const { bar, button, live } = buildBar( {
    title: detail.name,
    price: detail.price,
    thumb: thumbEl ? thumbEl.currentSrc || thumbEl.src : '',
    // `data-sticky-label` est posé par le helper PHP : il donne le libellé nu,
    // sans le « (nouvelle fenêtre) » que textContent ramasserait.
    ctaLabel: realCta.dataset.stickyLabel || realCta.textContent.trim(),
    disabled: isDisabled,
    link,
  } );

  // Le proxy ne concerne que le CTA bouton : .click() soumet le form
  // (simple/abonnement). Le CTA lien, lui, EST déjà un lien dans la barre —
  // le proxy ouvrirait un second onglet. Produit indisponible : pas de proxy
  // (bouton inerte, état informatif).
  if ( ! isDisabled && ! link ) {
    button.addEventListener( 'click', () => {
      // Le cas « site marchand » ne passe plus par ici : un CTA lien est
      // miroité en <a> juste au-dessus, jamais proxifié.
      live.textContent = form ? 'Ajout au panier en cours…' : 'Activation en cours…';
      realCta.click();
    } );
  }

  if ( link ) {
    button.addEventListener( 'click', () => {
      live.textContent = 'Ouverture du site marchand…';
      pushAddToCart( detail );
    } );
  }

  // Affiche la barre quand le CTA réel quitte le viewport (scroll).
  const observer = new IntersectionObserver(
    ( entries ) => {
      bar.classList.toggle( 'is-visible', ! entries[ 0 ].isIntersecting );
    },
    { rootMargin: '0px 0px -10% 0px' }
  );
  observer.observe( realCta );
}
