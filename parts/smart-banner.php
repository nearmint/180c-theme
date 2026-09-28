<?php
/**
 * Template part — Smart Banner (Android web).
 *
 * iOS Smart App Banner : géré via la meta `apple-itunes-app` injectée dans wp_head
 * depuis inc/theme-setup.php (Phase 1.2).
 *
 * Ce template gère le smart banner web pour Android (navigateurs ne supportant
 * pas la meta apple-itunes-app). Il est rendu uniquement en mobile, détection
 * User-Agent via JS (Phase 1.3).
 *
 * Spec :
 * - Affiché uniquement sur mobile Android
 * - Caché si cookie `dismissed_app_banner=1`
 * - Lien Play Store : constante `_180C_APP_ANDROID_URL` (définie dans wp-config ou bootstrap)
 * - Bouton fermer : dispatch event `180c:dismiss-app-banner` (JS Phase 1.3)
 *
 * TODO : Phase 8 — implémenter l'affichage conditionnel via JS, détecter Android,
 *        lire le cookie dismissed_app_banner, gérer le tracking GA4 `app_promo_click`.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;
