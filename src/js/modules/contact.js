/**
 * Module Contact.
 *
 * Formulaire de contact acheminé par l'endpoint natif POST /180c/v1/contact
 * (inc/rest/contact.php), qui envoie le message par `wp_mail()`.
 *  - Validation client inline (blur/change) + au submit, messages exacts.
 *  - Compteur de caractères dynamique (min. 20).
 *  - Honeypot anti-spam (`_gotcha`) : abandon silencieux si rempli. Le serveur
 *    refait la vérification — celle-ci n'est qu'un raccourci.
 *  - Routage : la clé de l'objet (`data-objet` de l'option choisie) est recopiée
 *    dans le champ caché `objet`, seul contrat lu par le serveur.
 *  - Nonce frais récupéré via GET /180c/v1/contact/nonce avant l'envoi
 *    (robustesse cache : une page servie depuis un cache porte un nonce périmé).
 *  - États Default / Submitting / Success / Error annoncés via aria-live.
 *
 * Chargé à la demande depuis main.js (présence de #contact-form).
 *
 * @module contact
 */

import { fetchActionNonce } from './session.js';

const MIN_MESSAGE = 20;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const REST_BASE = window._180c?.restUrl ?? '/wp-json/180c/v1/';

const MESSAGES = {
  name: {
    empty: "Merci d'indiquer votre prénom et votre nom.",
    short: 'Votre nom doit contenir au moins 2 caractères.',
  },
  email: {
    empty: "Merci d'indiquer votre adresse e-mail.",
    invalid: 'Cette adresse e-mail ne semble pas valide.',
  },
  subject: { empty: "Merci de choisir l'objet de votre message." },
  message: {
    empty: "Merci d'écrire votre message.",
    short: 'Votre message doit contenir au moins 20 caractères.',
  },
};

// HTML statique, sans aucune donnée utilisateur : injectable via innerHTML.
const ERROR_MSG =
  'Une erreur est survenue. Merci de réessayer dans quelques instants ou de nous écrire à <a href="mailto:contact@180c.fr">contact@180c.fr</a>.';
const SUCCESS_MSG =
  'Merci pour votre message. Nous revenons vers vous au plus vite.';

/**
 * Récupère un nonce frais via GET /contact/nonce. En cas d'échec, retombe sur le
 * nonce rendu côté serveur dans le champ caché.
 *
 * Implémentation mutualisée dans session.js — même logique pour la newsletter.
 *
 * @param {string} fallback Nonce du champ caché.
 * @returns {Promise<string>}
 */
function fetchFreshNonce(fallback) {
  return fetchActionNonce('contact/nonce', fallback);
}

/**
 * Initialise le formulaire de contact (no-op si absent de la page).
 */
export function init() {
  const form = document.getElementById('contact-form');
  if (!form) return;

  const fields = {
    name: form.querySelector('#cf-name'),
    email: form.querySelector('#cf-email'),
    subject: form.querySelector('#cf-subject'),
    message: form.querySelector('#cf-message'),
  };
  const errors = {
    name: form.querySelector('#cf-name-error'),
    email: form.querySelector('#cf-email-error'),
    subject: form.querySelector('#cf-subject-error'),
    message: form.querySelector('#cf-message-error'),
  };
  const counter = form.querySelector('#cf-message-counter');
  const status = form.querySelector('#cf-status');
  const submit = form.querySelector('.contact-form__submit');
  const gotcha = form.querySelector('.contact-form__gotcha');
  const nonceField = form.querySelector('input[name="cf_nonce"]');
  const objetField = form.querySelector('input[name="objet"]');

  const validators = {
    name: () => {
      const v = fields.name.value.trim();
      if (!v) return MESSAGES.name.empty;
      if (v.length < 2) return MESSAGES.name.short;
      return '';
    },
    email: () => {
      const v = fields.email.value.trim();
      if (!v) return MESSAGES.email.empty;
      if (!EMAIL_RE.test(v)) return MESSAGES.email.invalid;
      return '';
    },
    subject: () => (fields.subject.value ? '' : MESSAGES.subject.empty),
    message: () => {
      const v = fields.message.value.trim();
      if (!v) return MESSAGES.message.empty;
      if (v.length < MIN_MESSAGE) return MESSAGES.message.short;
      return '';
    },
  };

  const setError = (key, msg) => {
    errors[key].textContent = msg || '';
    fields[key].setAttribute('aria-invalid', msg ? 'true' : 'false');
  };

  const validateField = (key) => {
    const msg = validators[key]();
    setError(key, msg);
    return !msg;
  };

  const updateCounter = () => {
    const len = fields.message.value.trim().length;
    counter.textContent = `${len} / ${MIN_MESSAGE} caractères minimum`;
  };

  const setSubmitting = (on) => {
    submit.disabled = on;
    submit.setAttribute('aria-busy', on ? 'true' : 'false');
    submit.textContent = on ? 'Envoi en cours…' : 'Envoyer le message';
  };

  const showStatus = (msg, type) => {
    status.hidden = false;
    // Le message d'erreur porte un lien mailto statique ; le succès est du texte.
    if (type === 'error') {
      status.innerHTML = msg;
    } else {
      status.textContent = msg;
    }
    status.setAttribute('role', type === 'error' ? 'alert' : 'status');
    status.classList.toggle('contact-form__status--error', type === 'error');
    status.classList.toggle('contact-form__status--success', type === 'success');
  };

  // Recopie la clé de routage de l'option choisie dans le champ caché `objet`.
  // C'est cette clé, et elle seule, que le serveur lit pour router le message.
  const syncObjet = () => {
    const opt = fields.subject.selectedOptions[0];
    if (objetField) objetField.value = (opt && opt.dataset.objet) || '';
  };

  // Pré-sélection de l'objet via ?objet=<clé> (ex. lien « Service client » du
  // compte → objet=support-client). Cache-safe car côté client. Param
  // absent/inconnu → placeholder inchangé, aucune validation déclenchée (pas
  // d'erreur affichée d'emblée).
  const preselectFromUrl = () => {
    const objet = new URLSearchParams(window.location.search).get('objet');
    if (!objet) return;
    const opt = fields.subject.querySelector(
      `option[data-objet="${CSS.escape(objet)}"]`
    );
    if (!opt) return;
    fields.subject.value = opt.value;
    syncObjet();
  };
  preselectFromUrl();

  // Compteur.
  fields.message.addEventListener('input', updateCounter);
  updateCounter();

  // Validation au blur (inputs/textarea) ou change (select).
  Object.keys(fields).forEach((key) => {
    const evt = fields[key].tagName === 'SELECT' ? 'change' : 'blur';
    fields[key].addEventListener(evt, () => validateField(key));
  });

  fields.subject.addEventListener('change', syncObjet);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    status.hidden = true;

    // Honeypot : si rempli, abandon silencieux.
    if (gotcha && gotcha.value) return;

    const results = Object.keys(validators).map((k) => validateField(k));
    if (results.includes(false)) {
      const firstInvalid = Object.keys(fields).find(
        (k) => fields[k].getAttribute('aria-invalid') === 'true'
      );
      if (firstInvalid) fields[firstInvalid].focus();
      return;
    }

    // Filet : le champ caché doit être aligné sur l'option courante même si un
    // navigateur a restauré la sélection sans émettre `change`.
    syncObjet();

    setSubmitting(true);
    try {
      const freshNonce = await fetchFreshNonce(nonceField?.value ?? '');

      const res = await fetch(`${REST_BASE}contact`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          objet: objetField?.value ?? '',
          name: fields.name.value.trim(),
          email: fields.email.value.trim(),
          message: fields.message.value.trim(),
          cf_nonce: freshNonce,
          _gotcha: '',
        }),
      });

      if (res.ok) {
        form.reset();
        syncObjet();
        updateCounter();
        Object.keys(fields).forEach((k) => setError(k, ''));
        showStatus(SUCCESS_MSG, 'success');
      } else {
        showStatus(ERROR_MSG, 'error');
      }
    } catch (err) {
      showStatus(ERROR_MSG, 'error');
      // eslint-disable-next-line no-console
      console.error('[180c/contact] Erreur réseau :', err.message);
    } finally {
      setSubmitting(false);
    }
  });
}
