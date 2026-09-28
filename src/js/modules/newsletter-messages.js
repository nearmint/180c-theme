/**
 * Messages newsletter — source unique du wording (formulaires + toggles).
 *
 * Centralise les libellés de confirmation et d'erreur pour garantir un wording
 * homogène sur toutes les surfaces d'opt-in (page /newsletter/, module home,
 * bloc Gutenberg, toggles Mon Compte). Les messages d'erreur sont spécifiques au
 * code HTTP renvoyé par les endpoints REST 180c.
 *
 * @module newsletter-messages
 */

/**
 * Libellés traduits injectés côté serveur via wp_localize_script (D6, domaine
 * `180c`). Source de vérité = _180c_newsletter_js_i18n() (inc/rest/newsletter.php).
 * Les chaînes ci-dessous ne servent que de repli si la localisation est absente
 * (ex. page sans le script 180c-data) ; elles doivent rester alignées avec le
 * catalogue serveur.
 */
const I18N = (typeof window !== 'undefined' && window._180c && window._180c.nlMessages) || {};

/**
 * Libellés des formulaires d'inscription (single opt-in : l'inscription est
 * effective immédiatement, sans e-mail de confirmation).
 */
export const FORM_MESSAGES = {
  success: I18N.success ?? 'Merci, votre inscription à la newsletter est confirmée.',
  emptyEmail: I18N.emptyEmail ?? 'Veuillez saisir votre adresse email.',
  invalidEmail: I18N.invalidEmail ?? 'Cette adresse email n’est pas valide.',
  rateLimited: I18N.rateLimited ?? 'Trop de tentatives. Merci de réessayer dans une minute.',
  sessionExpired: I18N.sessionExpired ?? 'Votre session a expiré, merci de recharger la page.',
  rejected: I18N.rejected ?? 'Cette adresse ne peut pas être réinscrite. Veuillez nous contacter.',
  network: I18N.network ?? 'Connexion impossible. Vérifiez votre réseau et réessayez.',
  error: I18N.serverError ?? 'Une erreur est survenue. Merci de réessayer plus tard.',
};

/**
 * Libellés des toggles d'opt-in (Mon compte).
 */
export const TOGGLE_MESSAGES = {
  on: I18N.toggleOn ?? 'Vous êtes inscrit à la newsletter.',
  off: I18N.toggleOff ?? 'Vous êtes désinscrit de la newsletter.',
  subscriberOnly: I18N.subscriberOnly ?? 'Cette option est réservée aux abonnés.',
};

/**
 * Message d'erreur spécifique pour un formulaire d'inscription, selon le statut.
 *
 * @param {number} status Code HTTP de la réponse.
 * @returns {string}
 */
export function formErrorForStatus(status) {
  switch (status) {
    case 400:
      return FORM_MESSAGES.invalidEmail;
    case 403:
      return FORM_MESSAGES.sessionExpired;
    case 422:
      return FORM_MESSAGES.rejected;
    case 429:
      return FORM_MESSAGES.rateLimited;
    default:
      return FORM_MESSAGES.error;
  }
}

/**
 * Message d'erreur spécifique pour un toggle d'opt-in (Mon Compte), selon le
 * statut. Distingue l'auth/permission, la limite de débit et le réseau.
 *
 * @param {number} status Code HTTP de la réponse (0 = échec réseau).
 * @returns {string}
 */
export function toggleErrorForStatus(status) {
  switch (status) {
    case 401:
      return FORM_MESSAGES.sessionExpired;
    case 403:
      return TOGGLE_MESSAGES.subscriberOnly;
    case 429:
      return FORM_MESSAGES.rateLimited;
    case 0:
      return FORM_MESSAGES.network;
    default:
      return FORM_MESSAGES.error;
  }
}
