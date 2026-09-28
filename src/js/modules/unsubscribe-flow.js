/**
 * Module — Stepper de résiliation (parcours de rétention).
 *
 * Branche le <dialog> server-rendu (#_180c-unsub, cf.
 * parts/account/unsubscribe-stepper.php) : ouverture depuis le lien discret
 * [data-unsub-open], bascule d'écrans, validation du motif, appels REST
 * (feedback + cancel), écran de récap, accessibilité (focus + aria-live) et
 * événements GA4. Enhancement progressif : sans JS, le lien discret navigue
 * vers /centre-daide/#resiliation (fallback).
 *
 * Le <dialog> natif gère le piège de focus et la touche Échap. La fermeture
 * (Échap / clic backdrop / « Rester abonné ») = aucune action serveur.
 *
 * @module unsubscribe-flow
 */

// restFetch pose le nonce COURANT et rejoue une fois sur nonce invalide : le
// nonce n'est plus inliné dans le HTML (cache WPSC), une constante lue à
// l'import capturait une chaîne vide refusée en 403 par le cœur REST.
import { restFetch } from './session.js';

const TOTAL_STEPS = 4;

let dialog = null;
let subscriptionId = 0;
let statusRegion = null;

/**
 * Émet un événement GA4 si gtag est présent (consent-gated en amont).
 *
 * @param {string} event  Nom de l'événement.
 * @param {Object} params Paramètres associés.
 */
function track(event, params = {}) {
  window._180c?.ga4?.event?.(event, params);
}

/**
 * POST JSON authentifié (cookie + nonce wp_rest) vers une route 180c/v1.
 *
 * @param {string} path    Chemin relatif (ex. 'subscription/cancel').
 * @param {Object} payload Corps de la requête.
 * @returns {Promise<Object>} Réponse JSON (objet vide si corps illisible).
 */
async function postJson(path, payload) {
  const response = await restFetch(path, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    throw new Error(data.message ?? `HTTP ${response.status}`);
  }

  return response.json().catch(() => ({}));
}

/**
 * Numéro de l'écran actuellement visible.
 *
 * @returns {number}
 */
function currentStep() {
  const visible = dialog.querySelector('[data-unsub-step]:not([hidden])');
  return visible ? parseInt(visible.dataset.unsubStep, 10) : 1;
}

/**
 * Écrit une annonce dans la région aria-live.
 *
 * @param {string} message
 */
function announce(message) {
  if (statusRegion) {
    statusRegion.textContent = message;
  }
}

/**
 * Affiche l'écran n, déplace le focus sur son titre et annonce l'étape.
 *
 * @param {number} n
 */
function showStep(n) {
  dialog.querySelectorAll('[data-unsub-step]').forEach((step) => {
    step.hidden = parseInt(step.dataset.unsubStep, 10) !== n;
  });

  const title = document.getElementById(`_180c-unsub-title-${n}`);
  if (title) {
    dialog.setAttribute('aria-labelledby', `_180c-unsub-title-${n}`);
    title.focus();
    announce(`Étape ${n} sur ${TOTAL_STEPS} : ${title.textContent.trim()}`);
  }

  track('unsub_step_view', { step: n });
}

/**
 * Élément d'erreur (role=alert) de l'écran 3, créé à la demande et relié au
 * fieldset via aria-describedby.
 *
 * @returns {HTMLElement|null}
 */
function getErrorEl() {
  let el = dialog.querySelector('._180c-unsub__error');
  if (el) {
    return el;
  }

  const fieldset = dialog.querySelector('._180c-unsub__fieldset');
  if (!fieldset) {
    return null;
  }

  el = document.createElement('p');
  el.className = '_180c-unsub__error';
  el.id = '_180c-unsub-error';
  el.setAttribute('role', 'alert');
  el.hidden = true;
  fieldset.setAttribute('aria-describedby', '_180c-unsub-error');
  fieldset.insertAdjacentElement('afterend', el);

  return el;
}

/**
 * Affiche un message d'erreur sur l'écran 3.
 *
 * @param {string} message
 */
function showError(message) {
  const el = getErrorEl();
  if (el) {
    el.textContent = message;
    el.hidden = false;
  }
}

/**
 * Masque le message d'erreur.
 */
function hideError() {
  const el = dialog.querySelector('._180c-unsub__error');
  if (el) {
    el.hidden = true;
    el.textContent = '';
  }
}

/**
 * Réinitialise le formulaire (radios, commentaire, erreur, bouton submit).
 */
function resetForm() {
  dialog.querySelectorAll('input[name="unsub_reason"]').forEach((radio) => {
    radio.checked = false;
  });

  const comment = dialog.querySelector('[data-unsub-comment]');
  if (comment) {
    comment.value = '';
  }

  hideError();

  const submit = dialog.querySelector('[data-unsub-submit]');
  if (submit) {
    submit.disabled = false;
    submit.removeAttribute('aria-busy');
  }
}

/**
 * Ouvre le parcours (override du fallback /centre-daide/).
 *
 * @param {Event} event
 */
function openFlow(event) {
  event.preventDefault();
  resetForm();

  if (typeof dialog.showModal === 'function') {
    dialog.showModal();
  } else {
    dialog.setAttribute('open', '');
  }

  track('unsub_flow_open');
  showStep(1);
}

/**
 * Avance vers l'écran suivant depuis « Non merci, se désabonner ».
 * Écran 1 → pause (si dispo) sinon sondage ; écran 2 → sondage.
 */
function goNext() {
  const step = currentStep();
  const hasPause = !!dialog.querySelector('[data-unsub-pause]');

  if (step === 1) {
    showStep(hasPause ? 2 : 3);
  } else if (step === 2) {
    showStep(3);
  }
}

/**
 * Applique le résultat de la résiliation à l'écran de récap.
 *
 * @param {Object} data Réponse { success, status, end_date }.
 */
function applyCancelResult(data) {
  const span = dialog.querySelector('[data-unsub-end-date]');
  if (!span) {
    return;
  }

  if (data && data.end_date) {
    span.textContent = data.end_date;
    return;
  }

  // Pas de période payée d'avance → résiliation immédiate (cancelled).
  const paragraph = span.closest('p');
  if (paragraph) {
    paragraph.textContent = 'Votre abonnement a été résilié et ne sera pas renouvelé.';
  }
}

/**
 * Soumet le sondage puis la résiliation (écran 3 → 4). Idempotent : le bouton
 * est désactivé pendant l'appel. L'échec d'écriture du sondage ne bloque pas la
 * résiliation.
 *
 * @param {HTMLButtonElement} button Bouton « Continuer ».
 */
async function handleSubmit(button) {
  const checked = dialog.querySelector('input[name="unsub_reason"]:checked');
  if (!checked) {
    showError('Veuillez sélectionner un motif pour continuer.');
    const firstRadio = dialog.querySelector('input[name="unsub_reason"]');
    if (firstRadio) {
      firstRadio.focus();
    }
    return;
  }

  hideError();

  const reason = checked.value;
  const commentEl = dialog.querySelector('[data-unsub-comment]');
  const comment = commentEl ? commentEl.value : '';

  button.disabled = true;
  button.setAttribute('aria-busy', 'true');

  try {
    // a) Feedback — best effort : ne pas bloquer la résiliation si l'écriture échoue.
    try {
      await postJson('unsubscribe/feedback', {
        subscription_id: subscriptionId,
        reason,
        comment,
      });
    } catch (feedbackError) {
      console.warn('[180c/unsub] Sondage non enregistré :', feedbackError.message);
    }

    // b) Résiliation.
    const data = await postJson('subscription/cancel', { subscription_id: subscriptionId });
    applyCancelResult(data);
    track('unsub_completed', { reason });
    showStep(4);
  } catch (cancelError) {
    console.warn('[180c/unsub] Résiliation échouée :', cancelError.message);
    showError('Une erreur est survenue. Veuillez réessayer.');
    button.disabled = false;
    button.removeAttribute('aria-busy');
  }
}

/**
 * Délégation des clics à l'intérieur du <dialog>.
 *
 * @param {MouseEvent} event
 */
function onDialogClick(event) {
  // Clic sur le backdrop (hors contenu) = abandon → rester abonné.
  if (event.target === dialog) {
    track('unsub_stay_clicked', { step: currentStep(), via: 'dismiss' });
    dialog.close();
    return;
  }

  // Croix de fermeture (plein écran) = abandon → rester abonné.
  if (event.target.closest('[data-unsub-close]')) {
    track('unsub_stay_clicked', { step: currentStep(), via: 'close' });
    dialog.close();
    return;
  }

  if (event.target.closest('[data-unsub-stay]')) {
    track('unsub_stay_clicked', { step: currentStep() });
    dialog.close();
    return;
  }

  if (event.target.closest('[data-unsub-next]')) {
    event.preventDefault();
    goNext();
    return;
  }

  if (event.target.closest('[data-unsub-pause]')) {
    // Laisse la navigation native vers le suspend WC (lien nonce-protégé).
    // Event canonique : pause à l'initiative de l'utilisateur (cf TRACKING_PLAN.md §4.3).
    track('subscription_pause', { subscription_id: String(subscriptionId) });
    return;
  }

  const submit = event.target.closest('[data-unsub-submit]');
  if (submit) {
    event.preventDefault();
    handleSubmit(submit);
  }

  // [data-unsub-done] : <a href=/mon-compte/> → navigation native (reload qui
  // rafraîchit l'état #gerer en pending-cancel). Aucune interception.
}

/**
 * Point d'entrée du module.
 */
export function init() {
  dialog = document.getElementById('_180c-unsub');
  if (!dialog) {
    return;
  }

  subscriptionId = parseInt(dialog.dataset.subscriptionId ?? '0', 10);
  statusRegion = dialog.querySelector('._180c-unsub__status');

  document.querySelectorAll('[data-unsub-open]').forEach((trigger) => {
    trigger.addEventListener('click', openFlow);
  });

  dialog.addEventListener('click', onDialogClick);

  // Fermeture native (Échap) = abandon → rester abonné.
  dialog.addEventListener('cancel', () => {
    track('unsub_stay_clicked', { step: currentStep(), via: 'dismiss' });
  });
}
