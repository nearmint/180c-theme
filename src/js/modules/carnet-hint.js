/**
 * Popover « carnet » — touch-point d'abonnement sur le bouton favori.
 *
 * Rendu côté PHP par `_180c_render_favorite_button()` pour les visiteurs NON
 * connectés uniquement : ce module ne fait qu'ouvrir et fermer le panneau déjà
 * présent dans le DOM. Sans JS, le panneau reste replié — aucune régression,
 * le bouton favori garde exactement son comportement d'origine.
 *
 * Déclencheurs :
 *   - survol       : appareils pointeurs seulement (`(hover: hover)`), sur
 *                    TOUS les wrappers ;
 *   - focus clavier : idem, via focusin/focusout (le wrapper porte tabindex=0
 *                    quand le bouton qu'il enveloppe est désactivé, donc non
 *                    focusable) ;
 *   - tap           : wrappers `--locked` uniquement, c'est-à-dire les seuls
 *                    boutons restés désactivés (recette premium verrouillée).
 *                    Un bouton favori ACTIF conserve son clic, qui redirige
 *                    vers /connexion/ : on ne l'intercepte jamais. Depuis que
 *                    l'état de connexion ne désactive plus rien, c'est le cas
 *                    de tous les boutons de la home.
 *
 * Fermeture : `Échap`, clic hors du panneau, sortie du survol, perte de focus,
 * ou croix explicite — désormais affichée et focusable sur TOUS les appareils.
 * Elle était masquée en `display: none` hors tactile, donc hors de l'ordre de
 * tabulation : au clavier, rien ne permettait de refermer depuis le panneau.
 * `Échap` comme la croix rendent le focus au bouton favori.
 *
 * Un seul panneau ouvert à la fois.
 *
 * @module carnet-hint
 */

const OPEN_CLASS = 'is-open';
const FLIP_X_CLASS = 'is-flipped-x';
const FLIP_Y_CLASS = 'is-flipped-y';

/** Marge minimale conservée entre le panneau et le bord qui le borne (px). */
const EDGE_MARGIN = 8;

/**
 * Délai de grâce avant fermeture à la sortie du survol (ms).
 *
 * Le panneau porte un CTA : le refermer à l'instant où le pointeur quitte le
 * bouton rendrait ce CTA inatteignable dès que la trajectoire s'écarte un peu.
 * Le délai satisfait aussi le critère WCAG 1.4.13 « Content on Hover », qui
 * exige qu'un contenu révélé au survol reste affiché assez longtemps pour être
 * consulté.
 */
const CLOSE_DELAY = 3000;

// Évalué à chaque événement plutôt qu'une fois à l'import : un appareil hybride
// (tablette + clavier détachable) bascule entre les deux modes en cours de vie
// de page.
const hoverQuery = window.matchMedia('(hover: hover)');

/** @type {HTMLElement|null} Wrapper dont le panneau est actuellement ouvert. */
let openHint = null;

/** @type {number|undefined} Fermeture différée en attente (sortie de survol). */
let closeTimer;

// ============================================================
// Ouverture / fermeture
// ============================================================

/**
 * Retourne le panneau d'un wrapper.
 *
 * @param {HTMLElement} hint
 * @returns {HTMLElement|null}
 */
function panelOf(hint) {
  return hint.querySelector('[data-carnet-hint-panel]');
}

/**
 * Ouvre le panneau d'un wrapper, en refermant celui qui l'était déjà.
 *
 * @param {HTMLElement} hint
 */
function open(hint) {
  cancelScheduledClose();

  if (openHint === hint) return;

  close();

  const panel = panelOf(hint);
  if (!panel) return;

  // Pas d'`aria-expanded` : le wrapper est un conteneur générique, l'attribut
  // n'y est pas valide. La description est portée par `aria-describedby` (posé
  // en PHP) et n'est exposée que panneau ouvert — un panneau replié est en
  // `display: none`, donc absent de l'arbre d'accessibilité.
  panel.classList.add(OPEN_CLASS);
  positionPanel(hint, panel);
  openHint = hint;
}

/**
 * Ré-ancre le panneau du gabarit « single » quand son placement par défaut
 * (sous le bouton, aligné à gauche) le ferait sortir de la zone visible.
 *
 * Deux bascules indépendantes, mesurées à l'ouverture :
 *   - horizontale : ancrage à droite quand les 300 px débordent à droite ;
 *   - verticale   : ouverture VERS LE HAUT quand la place manque en dessous.
 *
 * La zone de référence n'est pas le viewport seul : tout ancêtre qui clippe
 * borne aussi le panneau. C'est le cas du slider recettes, dont le viewport est
 * un scroller en `overflow-y: hidden` — un panneau ouvert vers le bas y était
 * coupé net. Le calcul remonte donc la chaîne des ancêtres clippants.
 *
 * Chaque bascule n'est conservée que si elle améliore les choses : sinon on
 * garde l'ancrage d'origine plutôt que d'échanger un débordement contre l'autre.
 *
 * Le gabarit « card » est inset dans sa carte et ancré sous un bouton déjà collé
 * au bord haut : il n'a rien à basculer.
 *
 * @param {HTMLElement} hint
 * @param {HTMLElement} panel
 */
function positionPanel(hint, panel) {
  panel.classList.remove(FLIP_X_CLASS, FLIP_Y_CLASS);

  if (!hint.classList.contains('carnet-hint--single')) return;

  const bounds = clippingBounds(hint);

  if (panel.getBoundingClientRect().right > bounds.right) {
    panel.classList.add(FLIP_X_CLASS);
    if (panel.getBoundingClientRect().left < bounds.left) {
      panel.classList.remove(FLIP_X_CLASS);
    }
  }

  if (panel.getBoundingClientRect().bottom > bounds.bottom) {
    panel.classList.add(FLIP_Y_CLASS);
    if (panel.getBoundingClientRect().top < bounds.top) {
      panel.classList.remove(FLIP_Y_CLASS);
    }
  }
}

/**
 * Rectangle dans lequel le panneau doit tenir : le viewport, resserré par
 * chaque ancêtre qui clippe son contenu (`overflow` autre que `visible`).
 *
 * @param {HTMLElement} el Élément de départ (le wrapper).
 * @returns {{left: number, top: number, right: number, bottom: number}}
 */
function clippingBounds(el) {
  const bounds = {
    left: EDGE_MARGIN,
    top: EDGE_MARGIN,
    right: window.innerWidth - EDGE_MARGIN,
    bottom: window.innerHeight - EDGE_MARGIN,
  };

  for (let node = el.parentElement; node && node !== document.body; node = node.parentElement) {
    const style = window.getComputedStyle(node);
    const clips = [style.overflow, style.overflowX, style.overflowY].some(
      (value) => value !== 'visible',
    );
    if (!clips) continue;

    const rect = node.getBoundingClientRect();
    bounds.left = Math.max(bounds.left, rect.left);
    bounds.top = Math.max(bounds.top, rect.top);
    bounds.right = Math.min(bounds.right, rect.right);
    bounds.bottom = Math.min(bounds.bottom, rect.bottom);
  }

  return bounds;
}

/**
 * Rend le focus au déclencheur du panneau.
 *
 * Le bouton favori n'étant plus jamais `disabled` sur l'état de connexion, il
 * est focusable et redevient la cible naturelle. Repli sur le wrapper pour les
 * seuls cas où il reste désactivé (recette premium verrouillée) : il porte
 * alors `tabindex="0"`, posé en PHP.
 *
 * @param {HTMLElement} hint
 */
function focusTrigger(hint) {
  const button = hint.querySelector('.favorite-button:not([disabled])');
  if (button) {
    button.focus();
    return;
  }
  if (hint.tabIndex >= 0) hint.focus();
}

/**
 * Fermeture explicite par la croix : referme ET rend le focus au déclencheur.
 *
 * Sans cette restitution, fermer au clavier laisserait le focus sur un bouton
 * qui vient de disparaître (`display: none` du panneau replié) — le navigateur
 * le renverrait alors sur `<body>`, faisant repartir la tabulation du début de
 * la page.
 *
 * @param {MouseEvent} e
 */
function handleCloseClick(e) {
  const hint = e.currentTarget.closest('[data-carnet-hint]');
  close();
  if (hint) focusTrigger(hint);
}

/**
 * Referme le panneau ouvert, s'il y en a un.
 */
function close() {
  cancelScheduledClose();

  if (!openHint) return;

  const panel = panelOf(openHint);
  if (panel) panel.classList.remove(OPEN_CLASS);
  openHint = null;
}

/**
 * Programme une fermeture différée (sortie de survol).
 *
 * Toute ré-entrée dans le wrapper, comme toute fermeture explicite, annule le
 * compte à rebours — c'est ce qui rend le CTA atteignable à la souris.
 */
function scheduleClose() {
  cancelScheduledClose();
  closeTimer = window.setTimeout(close, CLOSE_DELAY);
}

/**
 * Annule la fermeture différée en attente.
 */
function cancelScheduledClose() {
  window.clearTimeout(closeTimer);
  closeTimer = undefined;
}

// ============================================================
// Gestionnaires
// ============================================================

/**
 * Survol : ouvre sur appareil pointeur uniquement.
 *
 * Sur tactile, certains navigateurs émettent un `mouseenter` de compatibilité
 * juste avant le clic ; l'ignorer ici évite qu'un panneau s'ouvre puis se
 * referme dans le même geste.
 *
 * @param {MouseEvent} e
 */
function handleEnter(e) {
  if (!hoverQuery.matches) return;
  open(e.currentTarget);
}

/**
 * Fin de survol : programme la fermeture après le délai de grâce.
 *
 * Le panneau étant un descendant du wrapper, y entrer ne déclenche pas
 * `mouseleave` : la temporisation ne couvre donc pas le trajet bouton →
 * panneau (déjà traité par le pont CSS), mais le cas où le pointeur s'écarte
 * franchement avant de revenir chercher le CTA.
 *
 * @param {MouseEvent} e
 */
function handleLeave(e) {
  if (!hoverQuery.matches) return;
  if (openHint === e.currentTarget) scheduleClose();
}

/**
 * Focus clavier entrant : ouvre le panneau pour que sa description et son CTA
 * soient atteignables.
 *
 * @param {FocusEvent} e
 */
function handleFocusIn(e) {
  open(e.currentTarget);
}

/**
 * Focus sortant : referme dès que le focus quitte le wrapper (panneau inclus).
 *
 * @param {FocusEvent} e
 */
function handleFocusOut(e) {
  const hint = e.currentTarget;
  if (hint.contains(e.relatedTarget)) return;
  if (openHint === hint) close();
}

/**
 * Tap sur un wrapper verrouillé (bouton désactivé) : bascule le panneau.
 *
 * Réservé aux appareils SANS survol. Sur un appareil pointeur, le panneau est
 * déjà ouvert par le `mouseenter` qui précède tout clic : y basculer le
 * refermerait aussitôt, faisant disparaître le popover au premier clic.
 *
 * Le CTA et la croix vivent dans le panneau : on ne bascule que si le tap vient
 * d'ailleurs, sinon toucher « Je m'abonne » refermerait le panneau avant que la
 * navigation ne parte.
 *
 * @param {MouseEvent} e
 */
function handleLockedClick(e) {
  if (hoverQuery.matches) return;

  const hint = e.currentTarget;
  const panel = panelOf(hint);
  if (panel && panel.contains(e.target)) return;

  if (openHint === hint) {
    close();
  } else {
    open(hint);
  }
}

/**
 * Clic ailleurs dans la page : referme le panneau ouvert.
 *
 * Enregistré au niveau document, en phase de capture, pour rester insensible
 * à un éventuel `stopPropagation()` d'un autre module.
 *
 * @param {MouseEvent} e
 */
function handleDocumentClick(e) {
  if (!openHint) return;
  if (openHint.contains(e.target)) return;
  close();
}

/**
 * `Échap` referme le panneau et rend le focus au wrapper.
 *
 * @param {KeyboardEvent} e
 */
function handleKeydown(e) {
  if (e.key !== 'Escape' || !openHint) return;

  const hint = openHint;
  close();

  // Ne ramène le focus que s'il était DANS le wrapper : sinon on le volerait
  // à l'élément que l'utilisateur venait d'atteindre.
  if (hint.contains(document.activeElement)) {
    focusTrigger(hint);
  }
}

// ============================================================
// Init
// ============================================================

/**
 * Câble tous les wrappers présents dans le DOM.
 *
 * @returns {boolean} True si au moins un wrapper a été câblé.
 */
export function initCarnetHint() {
  const hints = document.querySelectorAll('[data-carnet-hint]');
  if (!hints.length) return false;

  hints.forEach((hint) => {
    hint.addEventListener('mouseenter', handleEnter);
    hint.addEventListener('mouseleave', handleLeave);
    hint.addEventListener('focusin', handleFocusIn);
    hint.addEventListener('focusout', handleFocusOut);

    if (hint.classList.contains('carnet-hint--locked')) {
      hint.addEventListener('click', handleLockedClick);
    }

    const closeBtn = hint.querySelector('[data-carnet-hint-close]');
    if (closeBtn) closeBtn.addEventListener('click', handleCloseClick);
  });

  document.addEventListener('click', handleDocumentClick, true);
  document.addEventListener('keydown', handleKeydown);

  return true;
}

export default initCarnetHint;
