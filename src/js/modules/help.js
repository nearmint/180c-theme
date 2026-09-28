/**
 * Centre d'aide — accordéons accessibles + deep-linking + TOC highlight.
 *
 * Markup attendu : .centre-aide (cf. page-aide.php).
 * Style associé : src/css/components/help.css.
 *
 * @package 180c-theme
 */

const ROOT_SELECTOR = '[data-component="centre-aide"]';

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Ouvre un panneau accordéon avec animation height.
 *
 * @param {HTMLElement} item L'élément .centre-aide__item.
 */
function openItem(item) {
    const trigger = item.querySelector('.centre-aide__trigger');
    const panel = item.querySelector('.centre-aide__answer');
    if (!trigger || !panel) return;

    trigger.setAttribute('aria-expanded', 'true');
    panel.hidden = false;

    if (prefersReducedMotion()) {
        panel.style.height = 'auto';
        panel.classList.add('is-open');
        return;
    }

    // Mesure et anime
    const target = panel.scrollHeight;
    panel.style.height = '0px';
    // Force reflow
    // eslint-disable-next-line no-unused-expressions
    panel.offsetHeight;
    panel.style.height = target + 'px';

    const onEnd = (event) => {
        if (event.propertyName !== 'height') return;
        panel.style.height = 'auto';
        panel.classList.add('is-open');
        panel.removeEventListener('transitionend', onEnd);
    };
    panel.addEventListener('transitionend', onEnd);
}

/**
 * Ferme un panneau accordéon avec animation height.
 *
 * @param {HTMLElement} item L'élément .centre-aide__item.
 */
function closeItem(item) {
    const trigger = item.querySelector('.centre-aide__trigger');
    const panel = item.querySelector('.centre-aide__answer');
    if (!trigger || !panel) return;

    trigger.setAttribute('aria-expanded', 'false');
    panel.classList.remove('is-open');

    if (prefersReducedMotion()) {
        panel.style.height = '0px';
        panel.hidden = true;
        return;
    }

    // Mesure, fixe la hauteur, puis anime vers 0
    const start = panel.scrollHeight;
    panel.style.height = start + 'px';
    // Force reflow
    // eslint-disable-next-line no-unused-expressions
    panel.offsetHeight;
    panel.style.height = '0px';

    const onEnd = (event) => {
        if (event.propertyName !== 'height') return;
        panel.hidden = true;
        panel.removeEventListener('transitionend', onEnd);
    };
    panel.addEventListener('transitionend', onEnd);
}

function isOpen(item) {
    const trigger = item.querySelector('.centre-aide__trigger');
    return trigger?.getAttribute('aria-expanded') === 'true';
}

function toggleItem(item, { updateHash = true } = {}) {
    if (isOpen(item)) {
        closeItem(item);
        if (updateHash && window.location.hash === '#' + item.id) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    } else {
        openItem(item);
        if (updateHash && item.id) {
            history.replaceState(null, '', '#' + item.id);
        }
    }
}

/**
 * Initialise les triggers d'accordéons.
 *
 * @param {HTMLElement} root Racine du composant.
 */
function bindTriggers(root) {
    const triggers = root.querySelectorAll('.centre-aide__trigger');
    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            const item = trigger.closest('.centre-aide__item');
            if (item) toggleItem(item);
        });
    });
}

/**
 * Ouvre l'accordéon ciblé par le hash de l'URL, le cas échéant.
 *
 * @param {HTMLElement} root Racine du composant.
 */
function openFromHash(root) {
    const hash = window.location.hash.slice(1);
    if (!hash) return;

    // Cas 1 : ancre directe d'une question
    const item = root.querySelector(`.centre-aide__item[id="${CSS.escape(hash)}"]`);
    if (item) {
        openItem(item);
        // Scroll après un tick pour laisser le panel s'ouvrir
        window.requestAnimationFrame(() => {
            item.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
        });
        return;
    }

    // Cas 2 : ancre de section (#section-xxx) — déjà gérée nativement par scroll-margin-top
    // Rien à faire de plus.
}

/**
 * TOC : smooth scroll + active state lié à l'IntersectionObserver des sections.
 *
 * @param {HTMLElement} root Racine du composant.
 */
function bindToc(root) {
    const tocLinks = root.querySelectorAll('.centre-aide__toc-link');
    if (!tocLinks.length) return;

    tocLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const targetId = link.getAttribute('data-toc-target');
            if (!targetId) return;
            const target = root.querySelector(`#${CSS.escape(targetId)}`);
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
            history.replaceState(null, '', '#' + targetId);
        });
    });

    const sections = Array.from(root.querySelectorAll('.centre-aide__section'));
    if (!sections.length || !('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const id = entry.target.id;
            const link = root.querySelector(`.centre-aide__toc-link[data-toc-target="${id}"]`);
            if (!link) return;
            if (entry.isIntersecting) {
                tocLinks.forEach((l) => l.classList.remove('is-active'));
                link.classList.add('is-active');
            }
        });
    }, {
        rootMargin: '-25% 0px -55% 0px',
        threshold: 0,
    });

    sections.forEach((s) => observer.observe(s));
}

/**
 * Helper pour exécuter dès que le DOM est prêt, ES module-safe.
 *
 * @param {Function} fn Callback à exécuter.
 */
function whenReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
}

whenReady(() => {
    const root = document.querySelector(ROOT_SELECTOR);
    if (!root) return;

    bindTriggers(root);
    bindToc(root);
    openFromHash(root);

    // Réagit aux changements de hash externes (back/forward, lien interne)
    window.addEventListener('hashchange', () => openFromHash(root));
});
