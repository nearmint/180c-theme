/**
 * « Offrir un abonnement » — module front (accordéon + formulaire cadeau).
 *
 * Sélecteur racine : [data-component="gift"] (module de page-abonnement.php ET
 * page-offrir-un-abonnement.php). Isolé du JS WooCommerce (aucun jQuery).
 *
 * - Accordéon accessible (si .gift__trigger présent) avec animation de hauteur
 *   respectant prefers-reduced-motion.
 * - Ouverture via ?offrir=1|open (déjà posée côté PHP) ou ancre #offrir, avec
 *   défilement doux vers le module.
 * - Soumission : validation inline puis POST 180c/v1/gift/start via
 *   `restFetch()` (nonce wp_rest frais) → redirection vers checkout_url. Sans JS, le formulaire reste lisible (noscript).
 *
 * @module gift
 */

// restFetch pose le nonce COURANT et rejoue une fois sur nonce invalide : le
// nonce n'est plus inliné dans le HTML (cache WPSC), une constante lue à
// l'import capturait une chaîne vide refusée en 403 par le cœur REST.
import { restFetch } from './session.js';

// Bus d'exclusivité partagé avec les offres (subscribe.js, module séparé) :
// ouvrir le module « Offrir » referme les offres, et inversement. Aucun import
// partagé — coordination par événement DOM uniquement.
const ACCORDION_EVENT = '180c:accordion-open';

const MESSAGES = {
  firstName: 'Merci d’indiquer le prénom du bénéficiaire.',
  email: 'L’adresse e-mail du bénéficiaire est invalide.',
  date: 'Choisissez une date d’envoi valide (aujourd’hui ou plus tard).',
  error: 'Une erreur est survenue, merci de réessayer.',
};

function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/* ------------------------------------------------------------------ *
 * Accordéon
 * ------------------------------------------------------------------ */

function openPanel(trigger, panel) {
  trigger.setAttribute('aria-expanded', 'true');
  panel.hidden = false;

  // Notifie le groupe d'exclusivité (offres + module cadeau).
  const item = trigger.closest('[data-accordion-group]');
  if (item?.dataset.accordionGroup) {
    document.dispatchEvent(
      new CustomEvent(ACCORDION_EVENT, {
        detail: { group: item.dataset.accordionGroup, item },
      })
    );
  }

  if (prefersReducedMotion()) {
    panel.style.height = 'auto';
    return;
  }

  const target = panel.scrollHeight;
  panel.style.height = '0px';
  // Force reflow.
  // eslint-disable-next-line no-unused-expressions
  panel.offsetHeight;
  panel.style.height = `${target}px`;

  const onEnd = (event) => {
    if (event.propertyName !== 'height') return;
    panel.style.height = 'auto';
    panel.removeEventListener('transitionend', onEnd);
  };
  panel.addEventListener('transitionend', onEnd);
}

function closePanel(trigger, panel) {
  trigger.setAttribute('aria-expanded', 'false');

  if (prefersReducedMotion()) {
    panel.style.height = '0px';
    panel.hidden = true;
    return;
  }

  const start = panel.scrollHeight;
  panel.style.height = `${start}px`;
  // Force reflow.
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

function bindAccordion(root) {
  const trigger = root.querySelector('.gift__trigger');
  const panel = root.querySelector('.gift__answer');
  if (!trigger || !panel) {
    return null;
  }

  // État initial : ouvert côté serveur (data-open) → synchronise la hauteur.
  if (panel.dataset.open === 'true') {
    trigger.setAttribute('aria-expanded', 'true');
    panel.hidden = false;
    panel.style.height = 'auto';
  }

  trigger.addEventListener('click', (event) => {
    event.preventDefault();
    if (trigger.getAttribute('aria-expanded') === 'true') {
      closePanel(trigger, panel);
    } else {
      openPanel(trigger, panel);
    }
  });

  return { trigger, panel };
}

/**
 * Ouvre le module et défile vers lui si l'URL le demande (?offrir / #offrir).
 *
 * @param {HTMLElement} root
 * @param {{trigger: HTMLElement, panel: HTMLElement}|null} accordion
 */
function handleDeepLink(root, accordion) {
  const params = new URLSearchParams(window.location.search);
  const offrir = (params.get('offrir') ?? '').toLowerCase();
  const wantsOpen =
    offrir === '1' || offrir === 'open' || window.location.hash === '#offrir';

  if (!wantsOpen) {
    return;
  }

  if (accordion && accordion.trigger.getAttribute('aria-expanded') !== 'true') {
    openPanel(accordion.trigger, accordion.panel);
  }

  root.scrollIntoView({
    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    block: 'start',
  });
}

/* ------------------------------------------------------------------ *
 * Formulaire
 * ------------------------------------------------------------------ */

function showError(input, errorEl, message) {
  if (input) input.setAttribute('aria-invalid', 'true');
  if (errorEl) {
    errorEl.textContent = message;
    errorEl.hidden = false;
  }
}

function clearError(input, errorEl) {
  if (input) input.setAttribute('aria-invalid', 'false');
  if (errorEl) {
    errorEl.textContent = '';
    errorEl.hidden = true;
  }
}

function setStatus(statusEl, message) {
  if (!statusEl) return;
  statusEl.textContent = message;
  statusEl.hidden = false;
}

function setLoading(btn, loading) {
  if (!btn) return;
  btn.disabled = loading;
  btn.setAttribute('aria-busy', loading ? 'true' : 'false');
}

function bindForm(root) {
  const form = root.querySelector('[data-gift-form]');
  if (!form) {
    return;
  }

  const sendNow = form.querySelector('#gift-send-now');
  const dateWrap = form.querySelector('[data-gift-date-wrap]');
  const dateInput = form.querySelector('#gift-send-date');

  // Bascule de la visibilité du champ date selon « Envoyer maintenant ».
  const syncDateVisibility = () => {
    const now = sendNow ? sendNow.checked : true;
    if (dateWrap) dateWrap.hidden = now;
    if (dateInput) dateInput.required = !now;
  };
  if (sendNow) {
    sendNow.addEventListener('change', syncDateVisibility);
  }
  syncDateVisibility();

  form.addEventListener('submit', (event) => handleSubmit(event, form));
}

async function handleSubmit(event, form) {
  event.preventDefault();

  const firstNameInput = form.querySelector('#gift-first-name');
  const emailInput = form.querySelector('#gift-email');
  const messageInput = form.querySelector('#gift-message');
  const sendNow = form.querySelector('#gift-send-now');
  const dateInput = form.querySelector('#gift-send-date');
  const submitBtn = form.querySelector('[data-gift-submit]');
  const statusEl = form.querySelector('[data-gift-status]');

  const firstNameError = form.querySelector('#gift-first-name-error');
  const emailError = form.querySelector('#gift-email-error');
  const dateError = form.querySelector('#gift-date-error');

  clearError(firstNameInput, firstNameError);
  clearError(emailInput, emailError);
  clearError(dateInput, dateError);
  if (statusEl) statusEl.hidden = true;

  const isSendNow = sendNow ? sendNow.checked : true;

  // Validation inline.
  if (!firstNameInput || firstNameInput.value.trim() === '') {
    showError(firstNameInput, firstNameError, MESSAGES.firstName);
    firstNameInput?.focus();
    return;
  }
  if (!emailInput || !emailInput.value.trim() || !emailInput.validity.valid) {
    showError(emailInput, emailError, MESSAGES.email);
    emailInput?.focus();
    return;
  }
  if (!isSendNow && (!dateInput || dateInput.value === '')) {
    showError(dateInput, dateError, MESSAGES.date);
    dateInput?.focus();
    return;
  }

  const payload = {
    recipient_first_name: firstNameInput.value.trim(),
    recipient_email: emailInput.value.trim(),
    message: messageInput ? messageInput.value : '',
    send_now: isSendNow,
    send_date: isSendNow ? '' : dateInput.value,
  };

  setLoading(submitBtn, true);

  try {
    const response = await restFetch('gift/start', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => null);

    if (response.ok && data && data.checkout_url) {
      window.location.href = data.checkout_url;
      return;
    }

    // Erreur : message serveur si fourni, sinon générique. Cible le champ
    // pertinent quand le code d'erreur l'indique.
    const message = (data && data.message) || MESSAGES.error;
    const code = data && data.code ? data.code : '';

    if (code.indexOf('email') !== -1) {
      showError(emailInput, emailError, message);
      emailInput.focus();
    } else if (code.indexOf('date') !== -1) {
      showError(dateInput, dateError, message);
      dateInput?.focus();
    } else if (code.indexOf('first_name') !== -1) {
      showError(firstNameInput, firstNameError, message);
      firstNameInput.focus();
    } else {
      setStatus(statusEl, message);
    }
  } catch (err) {
    setStatus(statusEl, MESSAGES.error);
    // eslint-disable-next-line no-console
    console.error('[180c/gift] Erreur réseau :', err.message);
  } finally {
    setLoading(submitBtn, false);
  }
}

/* ------------------------------------------------------------------ */

export function init() {
  const root = document.querySelector('[data-component="gift"]');
  if (!root) {
    return;
  }

  const accordion = bindAccordion(root);
  bindForm(root);

  // Symétrique du bus de subscribe.js : si une offre (ou tout autre membre du
  // groupe) s'ouvre, on referme le panel cadeau. On ne ferme que notre propre
  // panel → zéro couplage avec l'autre module.
  if (accordion) {
    const giftItem = accordion.trigger.closest('[data-accordion-group]');
    document.addEventListener(ACCORDION_EVENT, (event) => {
      const detail = event.detail || {};
      if (detail.group !== 'subscribe' || detail.item === giftItem) {
        return;
      }
      if (accordion.trigger.getAttribute('aria-expanded') === 'true') {
        closePanel(accordion.trigger, accordion.panel);
      }
    });
  }

  handleDeepLink(root, accordion);
}

export default init;
