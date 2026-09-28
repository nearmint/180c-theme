/**
 * Formulaires newsletter — soumission opt-in unifiée (single opt-in).
 *
 * Surface unique pour TOUTES les inscriptions du site (page /newsletter/, module
 * home « Formulaire newsletter », bloc Gutenberg 180c/newsletter-form) :
 *  - Sélecteur : [data-newsletter-form] (toutes les instances de la page).
 *  - POST /180c/v1/newsletter/public-subscribe — single opt-in, idempotent,
 *    protégé par nonce frais + honeypot + rate-limit IP (clé Mailchimp jamais
 *    exposée).
 *  - Feedback : composant DS partagé `.newsletter-form__feedback`
 *    (--success / --error). Wording centralisé dans newsletter-messages.js,
 *    messages d'erreur spécifiques au code HTTP.
 *
 * @module newsletter
 */

import { FORM_MESSAGES, formErrorForStatus } from './newsletter-messages.js';
import { fetchActionNonce } from './session.js';

const REST_BASE = window._180c?.restUrl ?? '/wp-json/180c/v1/';

/**
 * Emplacement du formulaire (`data-ga-location`, déjà posé pour GA4) → slug de
 * source transmis au serveur, qui le résout en tags `src-plt:*` / `src-loc:*`.
 * Un emplacement non mappé part en `web-<location>` : le serveur le rejette via
 * son allow-list et journalise la valeur exacte, ce qui rend l'oubli visible
 * plutôt que silencieusement mal attribué.
 */
const SOURCE_BY_LOCATION = {
  page: 'web-newsletter-page',
  block: 'web-block-form',
  home: 'web-home-module',
};

/**
 * Résout le slug de source d'un formulaire depuis son emplacement GA4.
 *
 * @param {HTMLFormElement} form
 * @returns {string}
 */
function sourceForForm(form) {
  const location = form.dataset.gaLocation ?? '';
  return SOURCE_BY_LOCATION[location] ?? `web-${location}`;
}

/**
 * Récupère un nonce frais via GET /newsletter/nonce (robustesse cache : une page
 * servie depuis un cache peut porter un nonce périmé). En cas d'échec, retombe
 * sur le nonce du champ caché rendu côté serveur.
 *
 * Implémentation mutualisée dans session.js — même logique pour le contact.
 *
 * @param {string} fallback Nonce du champ caché.
 * @returns {Promise<string>}
 */
function fetchFreshNonce(fallback) {
  return fetchActionNonce('newsletter/nonce', fallback);
}

/**
 * Initialise tous les formulaires newsletter présents dans la page.
 */
export function init() {
  const forms = document.querySelectorAll('[data-newsletter-form]');
  if (forms.length === 0) {
    return;
  }
  forms.forEach((form) => {
    form.addEventListener('submit', (event) => handleSubmit(event, form));
  });
}

/**
 * Gère la soumission d'un formulaire newsletter.
 *
 * @param {SubmitEvent}     event
 * @param {HTMLFormElement} form
 */
async function handleSubmit(event, form) {
  event.preventDefault();

  const emailInput = form.querySelector('input[type="email"], input[name="email"]');
  const submitBtn =
    form.querySelector('[data-newsletter-submit]') ?? form.querySelector('button[type="submit"]');
  const feedback = form.querySelector('.newsletter-form__feedback');
  const gotcha = form.querySelector('input[name="_gotcha"]');
  const nonceField = form.querySelector('input[name="nl_nonce"]');

  clearFeedback(feedback, emailInput);

  // Honeypot rempli → bot : abandon silencieux (aucun feedback, aucun appel).
  if (gotcha && gotcha.value.trim() !== '') {
    return;
  }

  const email = emailInput?.value.trim() ?? '';

  // Validation e-mail côté client : champ vide vs format invalide (messages
  // distincts, catalogue D6).
  if (!email) {
    showFeedback(feedback, emailInput, FORM_MESSAGES.emptyEmail, true);
    emailInput?.focus();
    return;
  }
  if (emailInput && !emailInput.validity.valid) {
    showFeedback(feedback, emailInput, FORM_MESSAGES.invalidEmail, true);
    emailInput?.focus();
    return;
  }

  setLoading(submitBtn, true);

  try {
    const freshNonce = await fetchFreshNonce(nonceField?.value ?? '');

    const response = await fetch(`${REST_BASE}newsletter/public-subscribe`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        email,
        nl_nonce: freshNonce,
        _gotcha: '',
        source: sourceForForm(form),
      }),
    });

    if (response.ok) {
      showFeedback(feedback, emailInput, FORM_MESSAGES.success, false);
      form.reset();
      trackSubscribe(form);
    } else {
      const isInvalid = response.status === 400;
      showFeedback(feedback, isInvalid ? emailInput : null, formErrorForStatus(response.status), true);
      if (isInvalid) {
        emailInput?.focus();
      }
    }
  } catch {
    showFeedback(feedback, null, FORM_MESSAGES.network, true);
  } finally {
    setLoading(submitBtn, false);
  }
}

/**
 * Signale une inscription réussie aux outils de mesure.
 *
 * GA4 : event `newsletter_signup` via la file consent-gated.
 * Umami : CustomEvent neutre `180c:newsletter:subscribed`, consommé par
 * src/js/modules/umami-events.js (ce module n'a pas à connaître Umami).
 *
 * @param {HTMLFormElement} form
 */
function trackSubscribe(form) {
  const location = form.dataset.gaLocation ?? 'newsletter';

  window._180c?.ga4?.event?.('newsletter_signup', {
    nl_type: form.dataset.newsletterList === 'premium' ? 'premium' : 'gratuite',
    location,
  });

  document.dispatchEvent(
    new CustomEvent('180c:newsletter:subscribed', { detail: { location } })
  );
}

/**
 * Active / désactive l'état de chargement du bouton.
 *
 * @param {HTMLButtonElement|null} btn
 * @param {boolean}                loading
 */
function setLoading(btn, loading) {
  if (!btn) {
    return;
  }
  btn.disabled = loading;
  btn.setAttribute('aria-busy', loading ? 'true' : 'false');
  if (loading) {
    btn.dataset.originalText = btn.textContent.trim();
    btn.textContent = 'Inscription…';
  } else {
    btn.textContent = btn.dataset.originalText ?? btn.textContent;
    delete btn.dataset.originalText;
  }
}

/**
 * Affiche un message dans la région de feedback partagée (composant DS).
 *
 * @param {HTMLElement|null}      feedback Élément .newsletter-form__feedback.
 * @param {HTMLInputElement|null} input    Champ e-mail (marqué invalide si erreur).
 * @param {string}                message  Texte à afficher.
 * @param {boolean}               isError  Vrai pour styliser en erreur.
 */
function showFeedback(feedback, input, message, isError) {
  if (input) {
    input.setAttribute('aria-invalid', isError ? 'true' : 'false');
  }
  if (!feedback) {
    return;
  }
  feedback.textContent = message;
  feedback.classList.toggle('newsletter-form__feedback--error', isError);
  feedback.classList.toggle('newsletter-form__feedback--success', !isError);
  feedback.hidden = false;
}

/**
 * Réinitialise la région de feedback et l'état d'erreur du champ.
 *
 * @param {HTMLElement|null}      feedback
 * @param {HTMLInputElement|null} input
 */
function clearFeedback(feedback, input) {
  if (input) {
    input.setAttribute('aria-invalid', 'false');
  }
  if (!feedback) {
    return;
  }
  feedback.hidden = true;
  feedback.textContent = '';
  feedback.classList.remove('newsletter-form__feedback--error', 'newsletter-form__feedback--success');
}
