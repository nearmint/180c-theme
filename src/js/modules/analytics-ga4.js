/**
 * Module Analytics GA4 — Tracking client-side des événements.
 *
 * Gère les événements déclenchés côté JS :
 *  - paywall_view : détection du paywall au chargement
 *  - paywall_cta_click : click sur CTA du paywall
 *  - app_promo_click : click sur les liens App Store/Play Store
 *
 * Tous les événements passent par `window._180c.ga4.event()` (posé par
 * inc/analytics/ga4.php) : tant que le consentement n'est pas donné, l'event est
 * mis en file côté page sans aucun appel réseau. Il est émis si l'utilisateur
 * accepte, abandonné s'il refuse.
 *
 * @package 180c
 */

/**
 * Émet un event GA4 via la file consent-gated.
 *
 * @param {string} name   Nom de l'event GA4.
 * @param {Object} params Paramètres associés.
 */
function track(name, params) {
	window._180c?.ga4?.event?.(name, params);
}

/**
 * Initialisation du module.
 *
 * Attache les listeners et vérifie la présence du paywall au chargement.
 */
function init() {
	// Détecte le paywall s'il est présent avec l'attribut data-event-fire.
	detectPaywallView();

	// Listener global : paywall_cta_click.
	document.addEventListener('click', (e) => {
		const cta = e.target.closest('[data-event="paywall_cta_click"]');
		if (!cta) return;

		const recipeId = cta.dataset.recipeId || '';
		const ctaLabel = cta.textContent.trim() || 'unknown';

		track('paywall_cta_click', {
			'recipe_id': recipeId,
			'cta_label': ctaLabel
		});
	});

	// Listener global : app_promo_click (smart banner, home module, sticky bar).
	document.addEventListener('click', (e) => {
		const link = e.target.closest('.js-app-promo-link');
		if (!link) return;

		const source = link.dataset.source || 'unknown'; // smart_banner, home_module, sticky_bar, app_page
		const store = link.dataset.store || 'unknown'; // ios, android

		track('app_promo_click', {
			'source': source,
			'store': store
		});
	});
}

/**
 * Détecte la présence du paywall et pousse l'event paywall_view.
 *
 * Cherche un élément avec [data-event-fire="paywall_view"].
 */
function detectPaywallView() {
	const paywall = document.querySelector('[data-event-fire="paywall_view"]');
	if (!paywall) return;

	const recipeId = paywall.dataset.recipeId || '';

	track('paywall_view', {
		'recipe_id': recipeId
	});

	// Retire l'attribut pour éviter de repousser à chaque DOMContentLoaded.
	paywall.removeAttribute('data-event-fire');
}

/**
 * Initialisation au DOMContentLoaded.
 */
document.addEventListener('DOMContentLoaded', init);

// Export pour usage externe.
export { detectPaywallView };
