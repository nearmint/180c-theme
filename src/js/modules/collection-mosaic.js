/**
 * Mosaïque « Complétez votre collection » — suspension du défilement.
 *
 * Pourquoi
 * --------
 * Le module anime neuf colonnes en `translateY` infini, chacune en
 * `will-change: transform`. L'animation tournait en permanence : hors du
 * viewport comme dans un onglet masqué, le compositeur continuait de repeindre
 * neuf couches pour un contenu que personne ne regarde. Sur mobile, c'est de la
 * batterie et du budget de scroll dépensés à vide.
 *
 * Le CSS ne suspendait qu'au survol et au focus
 * (`.collection-mosaic__stage:hover`), ce qui ne couvre ni l'un ni l'autre cas.
 *
 * Ce que fait ce module
 * ---------------------
 *   - IntersectionObserver : suspend dès que la mosaïque quitte le viewport,
 *     reprend quand elle y revient ;
 *   - `visibilitychange` : suspend quand l'onglet passe en arrière-plan.
 *
 * Reprise sans saut
 * -----------------
 * La suspension passe par `animation-play-state: paused`, JAMAIS par
 * `animation: none` ni par le retrait de la règle. C'est ce qui garantit une
 * reprise sans saut : `paused` fige l'animation à sa position courante et la
 * redémarre exactement là, alors qu'une remise à zéro de la propriété
 * relancerait le cycle depuis `from` — la mosaïque sauterait visiblement à
 * chaque retour dans le viewport.
 *
 * Le CSS reste maître du reste : `prefers-reduced-motion: reduce` fige déjà la
 * mosaïque (`animation: none`), et ce module n'a alors plus rien à suspendre.
 *
 * Sans JS, la mosaïque défile comme avant — l'ancien comportement est le repli.
 *
 * @module collection-mosaic
 */

/** Classe posée sur la section pour suspendre ses colonnes (cf. le CSS). */
const PAUSED_CLASS = 'is-paused';

/**
 * Suspend ou relance une mosaïque.
 *
 * @param {HTMLElement} mosaic
 * @param {boolean}     paused
 */
function setPaused(mosaic, paused) {
  mosaic.classList.toggle(PAUSED_CLASS, paused);
}

/**
 * Câble toutes les mosaïques présentes dans la page.
 *
 * @returns {boolean} True si au moins une mosaïque a été câblée.
 */
export function initCollectionMosaic() {
  const mosaics = document.querySelectorAll('.collection-mosaic');
  if (!mosaics.length) return false;

  // Mémorise la visibilité propre à chaque mosaïque : l'état affiché est la
  // conjonction « dans le viewport ET onglet au premier plan ». Sans cette
  // mémoire, le retour au premier plan relancerait aussi les mosaïques restées
  // hors écran.
  const inViewport = new WeakMap();

  const apply = (mosaic) => {
    setPaused(mosaic, !inViewport.get(mosaic) || document.hidden);
  };

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          inViewport.set(entry.target, entry.isIntersecting);
          apply(entry.target);
        });
      },
      // Marge généreuse : l'animation est relancée juste avant que la mosaïque
      // n'entre réellement en vue, pour qu'elle ne soit jamais surprise à
      // l'arrêt au moment où elle devient visible.
      { rootMargin: '200px 0px' },
    );

    mosaics.forEach((mosaic) => {
      inViewport.set(mosaic, false);
      observer.observe(mosaic);
    });
  } else {
    // Navigateur sans IntersectionObserver : on ne suspend que sur l'onglet
    // masqué, plutôt que de laisser la mosaïque figée faute d'observateur.
    mosaics.forEach((mosaic) => inViewport.set(mosaic, true));
  }

  document.addEventListener('visibilitychange', () => {
    mosaics.forEach(apply);
  });

  mosaics.forEach(apply);

  return true;
}

export default initCollectionMosaic;
