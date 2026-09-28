/**
 * Entrée JS du Design System.
 *
 * Rôle : isoler les styles propres aux pages Design System (cf. design-system.css)
 * et apporter une interaction légère : scroll-spy de la nav latérale (met en
 * avant l'ancre de section visible). Vanilla ES module, sans dépendance.
 */
import '../css/design-system.css';

/**
 * Scroll-spy : surligne le lien d'ancre correspondant à la section visible.
 *
 * Observe chaque section ciblée par un lien `.ds-nav__sub-link[href^="#"]` et
 * pose `aria-current="true"` + la classe active sur le lien dont la section est
 * la plus haute dans le viewport.
 */
function initScrollSpy() {
  const subLinks = Array.from(
    document.querySelectorAll('.ds-nav__sub-link[href^="#"]')
  );

  if (subLinks.length === 0) {
    return;
  }

  const sections = [];

  subLinks.forEach((link) => {
    const id = decodeURIComponent(link.hash.slice(1));
    const section = id ? document.getElementById(id) : null;
    if (section) {
      sections.push(section);
    }
  });

  if (sections.length === 0) {
    return;
  }

  const setActive = (id) => {
    subLinks.forEach((link) => {
      const isActive = decodeURIComponent(link.hash.slice(1)) === id;
      link.classList.toggle('ds-nav__sub-link--active', isActive);
      if (isActive) {
        link.setAttribute('aria-current', 'true');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  };

  const observer = new IntersectionObserver(
    (entries) => {
      // Garde la section visible la plus proche du haut.
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

      if (visible.length > 0) {
        setActive(visible[0].target.id);
      }
    },
    {
      // Déclenche quand la section entre dans le tiers supérieur du viewport.
      rootMargin: '0px 0px -70% 0px',
      threshold: 0,
    }
  );

  sections.forEach((section) => observer.observe(section));
}

if (document.readyState !== 'loading') {
  initScrollSpy();
} else {
  document.addEventListener('DOMContentLoaded', initScrollSpy);
}
