/**
 * Smart App Banner — bannière top Android / navigateurs non-Safari.
 *
 * iOS Safari gère sa propre bannière via le tag HTML :
 *   <meta name="apple-itunes-app" content="app-id=XXXXXXX">
 * Ce module ne gère PAS iOS Safari (éviter la double bannière).
 *
 * TODO Phase 8 — implémenter la logique Android / non-Safari :
 *  - Détecter Android (navigator.userAgentData.platform ou regex)
 *  - Détecter non-Safari (exclure Mobile Safari)
 *  - Vérifier cookie `dismissed_smart_banner` absent ou ≠ '1'
 *  - Injecter / afficher .smart-banner en haut du viewport
 *  - Bouton .js-dismiss-smart-banner → cookie 7j + masque la bannière
 *  - GA4 : déclencher app_promo_click(source=smart-banner, store=android)
 *    sur clic du CTA (via gtag dans analytics/ga4.js)
 *
 * Pour l'instant ce module est un stub commenté afin d'éviter
 * tout régression de bundle ou de comportement en Phase 1.
 */

// stub — aucune logique active en Phase 1
