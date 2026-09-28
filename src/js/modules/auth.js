/**
 * Module auth — validation JS + Turnstile + loader submit.
 *
 * - Validation HTML5 augmentée (force mot de passe, format email).
 * - Chargement conditionnel du script Turnstile si widget présent dans le DOM.
 * - Submit handler : affiche un loader sur le bouton sans bloquer le POST natif.
 * - Confirmation de suppression de compte.
 *
 * @module auth
 */

// ============================================================
// Init
// ============================================================

/**
 * Lance le module si des formulaires d'auth sont présents sur la page.
 */
function initAuth() {
  const forms = document.querySelectorAll('[data-auth-form]');
  if (forms.length === 0) return;

  forms.forEach((form) => {
    const type = form.dataset.authForm;

    // Validation augmentée selon le type de formulaire.
    if (type === 'register' || type === 'reset') {
      initPasswordStrength(form);
    }
    if (type === 'register' || type === 'login') {
      initEmailValidation(form);
    }

    // Loader bouton au submit.
    initSubmitLoader(form);
  });

  // Turnstile — injection du script externe si widget présent.
  initTurnstile();

  // Confirmation suppression compte.
  initDeleteConfirm();
}

// ============================================================
// Validation email
// ============================================================

/**
 * Validation email en temps réel avec retour a11y.
 *
 * @param {HTMLFormElement} form
 */
function initEmailValidation(form) {
  const emailInput = form.querySelector('input[type="email"]');
  if (!emailInput) return;

  emailInput.addEventListener('blur', () => {
    const value = emailInput.value.trim();
    const isValid = value === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

    emailInput.setAttribute('aria-invalid', isValid ? 'false' : 'true');

    // Retire ou ajoute le message d'erreur inline.
    let errorEl = emailInput.parentElement.querySelector('.field__error[data-js]');

    if (!isValid) {
      if (!errorEl) {
        errorEl = document.createElement('span');
        errorEl.className = 'field__error';
        errorEl.setAttribute('data-js', 'true');
        errorEl.setAttribute('role', 'alert');
        emailInput.after(errorEl);
      }
      errorEl.textContent = 'Adresse e-mail invalide.';
      emailInput.closest('.field')?.classList.add('field--error');
    } else {
      errorEl?.remove();
      emailInput.closest('.field')?.classList.remove('field--error');
    }
  });
}

// ============================================================
// Validation force mot de passe
// ============================================================

/**
 * Indicateur visuel de force du mot de passe.
 *
 * @param {HTMLFormElement} form
 */
function initPasswordStrength(form) {
  const passwordInput = form.querySelector('input[name="password"]');
  if (!passwordInput) return;

  // Crée l'indicateur de force.
  const strengthBar = document.createElement('div');
  strengthBar.className = 'password-strength';
  strengthBar.setAttribute('aria-live', 'polite');
  strengthBar.setAttribute('aria-atomic', 'true');
  strengthBar.hidden = true;
  passwordInput.after(strengthBar);

  passwordInput.addEventListener('input', () => {
    const value = passwordInput.value;

    if (value.length === 0) {
      strengthBar.hidden = true;
      return;
    }

    strengthBar.hidden = false;
    const score = getPasswordScore(value);
    const labels = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
    const modifiers = ['very-weak', 'weak', 'medium', 'strong', 'very-strong'];

    strengthBar.textContent = `Force : ${labels[score]}`;
    strengthBar.className = `password-strength password-strength--${modifiers[score]}`;
  });

  // Validation longueur au blur.
  passwordInput.addEventListener('blur', () => {
    const isValid = passwordInput.value.length === 0 || passwordInput.value.length >= 10;
    passwordInput.setAttribute('aria-invalid', isValid ? 'false' : 'true');

    let errorEl = passwordInput.parentElement.querySelector('.field__error[data-js-len]');
    if (!isValid) {
      if (!errorEl) {
        errorEl = document.createElement('span');
        errorEl.className = 'field__error';
        errorEl.setAttribute('data-js-len', 'true');
        errorEl.setAttribute('role', 'alert');
        passwordInput.after(errorEl);
      }
      errorEl.textContent = 'Le mot de passe doit faire au moins 10 caractères.';
    } else {
      errorEl?.remove();
    }
  });
}

/**
 * Calcule un score de force de mot de passe (0–4).
 *
 * @param {string} password
 * @returns {number}
 */
function getPasswordScore(password) {
  let score = 0;
  if (password.length >= 10) score++;
  if (password.length >= 14) score++;
  if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
  if (/[0-9]/.test(password)) score++;
  if (/[^A-Za-z0-9]/.test(password)) score++;
  return Math.min(4, score);
}

// ============================================================
// Loader bouton submit
// ============================================================

/**
 * Affiche un état chargement sur le bouton submit pendant le POST.
 *
 * @param {HTMLFormElement} form
 */
function initSubmitLoader(form) {
  form.addEventListener('submit', (e) => {
    // Laisse la validation HTML5 native faire son job.
    if (!form.checkValidity()) return;

    const submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) return;

    const originalText = submitBtn.textContent.trim();
    submitBtn.setAttribute('aria-busy', 'true');
    submitBtn.setAttribute('aria-label', 'Chargement…');
    submitBtn.textContent = 'Chargement…';

    // Restauration en cas d'erreur réseau (le formulaire ne se réinitialise pas).
    const timeout = window.setTimeout(() => {
      submitBtn.removeAttribute('aria-busy');
      submitBtn.removeAttribute('aria-label');
      submitBtn.textContent = originalText;
    }, 15000);

    // Nettoyage si navigation (ex : succès et redirect).
    window.addEventListener('pagehide', () => window.clearTimeout(timeout), { once: true });
  });
}

// ============================================================
// Turnstile
// ============================================================

/**
 * Charge le script Turnstile de manière asynchrone si un widget est présent.
 *
 * Le widget est rendu automatiquement par l'API Turnstile via `data-sitekey`.
 */
function initTurnstile() {
  const containers = document.querySelectorAll('.turnstile-container[data-sitekey]');
  if (containers.length === 0) return;

  // Évite le double chargement.
  if (document.querySelector('script[data-turnstile]')) return;

  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
  script.async = true;
  script.defer = true;
  script.setAttribute('data-turnstile', '1');
  document.head.appendChild(script);

  // Rend les widgets après chargement du script.
  script.addEventListener('load', () => {
    containers.forEach((container) => {
      const sitekey = container.dataset.sitekey;
      if (!sitekey || !window.turnstile) return;

      window.turnstile.render(container, {
        sitekey,
        theme: document.documentElement.dataset.theme === 'light' ? 'light' : 'dark',
        language: 'fr',
      });
    });
  });
}

// ============================================================
// Confirmation suppression de compte
// ============================================================

/**
 * Demande confirmation avant la soumission du formulaire de suppression.
 */
function initDeleteConfirm() {
  const deleteForm = document.querySelector('[data-confirm]');
  if (!deleteForm) return;

  deleteForm.addEventListener('submit', (e) => {
    const message = deleteForm.dataset.confirm;
    // eslint-disable-next-line no-alert
    if (!window.confirm(message)) {
      e.preventDefault();
    }
  });
}

// ============================================================
// Boot
// ============================================================

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAuth);
} else {
  initAuth();
}
