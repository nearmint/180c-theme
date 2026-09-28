/**
 * Suggestion de correction du domaine d'une adresse e-mail.
 *
 * « Vouliez-vous dire …@wanadoo.fr ? » sous tout champ `input[type="email"]`
 * du front (inscription, checkout, Mon compte, formulaires newsletter…) dont le
 * domaine est à faible distance d'un domaine courant. Un clic sur la
 * suggestion remplace l'adresse. Jamais bloquant : la saisie reste libre.
 *
 * Les listes de domaines viennent du serveur (`window._180c.emailDomains`,
 * source unique : inc/email-typo.php). Sans elles, le module ne fait rien.
 *
 * Désactivation ponctuelle : `data-email-suggest="off"` sur le champ.
 *
 * A11y : la zone de suggestion est une région `aria-live="polite"` créée vide
 * au premier focus, puis remplie — une région insérée déjà pleine n'est pas
 * annoncée de façon fiable. Aucun vol de focus.
 *
 * @module email-suggest
 */

const SELECTOR = 'input[type="email"]:not([data-email-suggest="off"])';

/**
 * Distance de Levenshtein entre deux chaînes.
 *
 * @param {string} a
 * @param {string} b
 * @returns {number}
 */
export function levenshtein(a, b) {
  let prev = Array.from({ length: b.length + 1 }, (_, j) => j);
  for (let i = 1; i <= a.length; i += 1) {
    const cur = [i];
    for (let j = 1; j <= b.length; j += 1) {
      cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
    }
    prev = cur;
  }
  return prev[b.length];
}

/**
 * Propose une adresse corrigée, ou null.
 *
 * Miroir exact de `_180c_email_domain_suggestion()` (inc/email-typo.php).
 *
 * @param {string} email   Adresse saisie.
 * @param {{reference: string[], legit: string[], maxDistance: number}} domains
 * @returns {{email: string, domain: string}|null}
 */
export function suggestEmail(email, domains) {
  const value = String(email || '').trim();
  const at = value.lastIndexOf('@');
  if (at <= 0 || !domains) return null;

  const local = value.slice(0, at);
  const domain = value.slice(at + 1).toLowerCase();
  if (!domain || domains.reference.includes(domain) || domains.legit.includes(domain)) return null;

  let best = '';
  let bestDist = domains.maxDistance + 1;
  domains.reference.forEach((ref) => {
    const dist = levenshtein(domain, ref);
    if (dist < bestDist) {
      best = ref;
      bestDist = dist;
    }
  });

  return bestDist <= domains.maxDistance ? { email: `${local}@${best}`, domain: best } : null;
}

/**
 * Retourne (en la créant vide au besoin) la zone de suggestion d'un champ.
 *
 * @param {HTMLInputElement} input
 * @returns {HTMLElement}
 */
function getHint(input) {
  const next = input.nextElementSibling;
  if (next && next.classList.contains('email-suggest')) return next;

  const hint = document.createElement('p');
  hint.className = 'email-suggest';
  hint.setAttribute('aria-live', 'polite');
  input.insertAdjacentElement('afterend', hint);
  return hint;
}

/**
 * Vide la zone de suggestion d'un champ.
 *
 * @param {HTMLInputElement} input
 */
function clearHint(input) {
  const next = input.nextElementSibling;
  if (next && next.classList.contains('email-suggest')) {
    next.replaceChildren();
    next.classList.remove('email-suggest--visible');
  }
}

/**
 * Affiche la suggestion sous le champ.
 *
 * @param {HTMLInputElement} input
 * @param {{email: string, domain: string}} suggestion
 */
function showHint(input, suggestion) {
  const hint = getHint(input);

  // Même suggestion déjà affichée : ne rien reconstruire. Un clic sur le bouton
  // fait d'abord perdre le focus au champ ; remplacer le bouton entre mousedown
  // et mouseup annulerait le clic.
  const current = hint.querySelector('.email-suggest__button');
  if (current && current.dataset.emailSuggestion === suggestion.email) return;

  const local = suggestion.email.slice(0, suggestion.email.length - suggestion.domain.length);

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'email-suggest__button';
  button.dataset.emailSuggestion = suggestion.email;
  const strong = document.createElement('strong');
  strong.textContent = suggestion.domain;
  button.append(local, strong);

  hint.replaceChildren('Vouliez-vous dire ', button, ' ?');
  hint.classList.add('email-suggest--visible');
}

/**
 * Évalue le champ et affiche ou retire la suggestion.
 *
 * @param {HTMLInputElement} input
 */
function evaluate(input) {
  const suggestion = suggestEmail(input.value, window._180c?.emailDomains);
  if (suggestion) {
    showHint(input, suggestion);
  } else {
    clearHint(input);
  }
}

/**
 * Pose les écouteurs délégués (champs présents ou injectés plus tard).
 */
export function init() {
  if (!window._180c?.emailDomains) return;

  // Région live créée vide dès le premier focus, avant tout contenu.
  document.addEventListener('focusin', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches(SELECTOR)) {
      getHint(event.target);
    }
  });

  document.addEventListener('focusout', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches(SELECTOR)) {
      evaluate(event.target);
    }
  });

  // La saisie reprend : l'ancienne suggestion n'a plus de sens.
  document.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches(SELECTOR)) {
      clearHint(event.target);
    }
  });

  document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('.email-suggest__button') : null;
    if (!button) return;

    const input = button.closest('.email-suggest')?.previousElementSibling;
    if (!(input instanceof HTMLInputElement)) return;

    input.value = button.dataset.emailSuggestion || input.value;
    // `change` : le checkout WooCommerce et les validations écoutent cet événement.
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    clearHint(input);
    input.focus();
  });
}
