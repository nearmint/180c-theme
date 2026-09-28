<?php
/**
 * Template Name: Téléchargement app (redirection)
 * Template Post Type: page
 *
 * Cible du QR code de téléchargement de l'app 180°C (`/app`).
 *
 * Redirige selon le système du visiteur, détecté côté serveur pour éviter tout
 * flash de page blanche : iOS → App Store, Android → Google Play, tout le reste
 * (desktop, bots, UA absent) → accueil du site.
 *
 * Ce fichier ne porte que les effets de bord (en-têtes + redirection) : la
 * résolution de la cible vit dans `_180c_get_app_download_redirect_url()`
 * (inc/helpers.php), pour rester appelable en test sans déclencher de
 * redirection.
 *
 * **Comportement tant que l'app n'est pas publiée.** Les constantes
 * `_180C_APP_STORE_URL` / `_180C_GOOGLE_PLAY_URL` sont encore commentées dans
 * wp-config : les helpers renvoient alors une chaîne vide et la page retombe
 * silencieusement sur l'accueil, sans jamais mentionner l'app. Même logique de
 * prudence que `inc/blocks/app-promo/render.php` et `parts/sticky-app-bar.php`
 * (grep `APP-RELEASE`). Renseigner les constantes suffit à activer la
 * redirection, sans toucher au thème.
 *
 * Cette page n'est volontairement liée depuis aucun template (footer,
 * app-promo, sticky-app-bar) : elle n'est atteignable que par URL directe, le
 * temps que les points de contact (page remerciement, print, footer) soient
 * arbitrés. Elle est servie en `noindex, nofollow` et exclue des deux plans du
 * site — voir `_180c_seo_noindex_page_slugs()` (inc/seo/meta-tags.php) et
 * `_180c_seo_excluded_page_ids()` (inc/seo/sitemap.php).
 *
 * @package 180c-theme
 */

defined( 'ABSPATH' ) || exit;

/*
 * La réponse dépend du User-Agent : elle ne doit surtout pas être mise en cache
 * page. Sans ça, WP Super Cache servirait la première redirection produite (par
 * exemple vers l'App Store) à tous les visiteurs suivants, quel que soit leur
 * appareil. Même pattern que inc/auth/password-reset.php et inc/seo/llms-txt.php.
 */
if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- constante d'interface imposée par WP Super Cache / W3 Total Cache ; la préfixer la rendrait inopérante.
	define( 'DONOTCACHEPAGE', true );
}
nocache_headers();

/*
 * Seul signal robots qu'un crawler verra réellement sur cette URL : elle ne rend
 * jamais de `<head>`, puisqu'elle se termine toujours par une redirection. Le
 * `noindex` du pipeline meta-tags couvre l'autre cas (aperçu admin, et un
 * éventuel futur variant qui rendrait du contenu). Précédent : le 410 des
 * anciens sitemaps dans inc/redirects.php.
 */
header( 'X-Robots-Tag: noindex, nofollow', true );

/*
 * 302 et non 301 : la cible dépend de l'appareil, et elle changera à la
 * publication de l'app. Une redirection permanente serait mise en cache par les
 * navigateurs sur la mauvaise valeur, sans moyen de la reprendre.
 */
wp_redirect( _180c_get_app_download_redirect_url(), 302, '180c-app-download' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Cible externe volontaire (App Store / Google Play) : wp_safe_redirect() la remplacerait par l'accueil.
exit;
