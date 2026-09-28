<?php
/**
 * Bootstrap du thème 180°C.
 *
 * Charge tous les modules dans l'ordre.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

define( '_180C_VERSION', '0.1.0' );
define( '_180C_THEME_DIR', get_template_directory() );
define( '_180C_THEME_URI', get_template_directory_uri() );
define( '_180C_API_NAMESPACE', '180c/v1' );
define( '_180C_APP_VERSION_MIN_IOS', '1.0.0' );
define( '_180C_APP_VERSION_MIN_ANDROID', '1.0.0' );


/*
 * Visuel de partage par défaut (og:image / twitter:image) pour les surfaces
 * dépourvues d'image propre : accueil, pages statiques, archives, taxonomies.
 *
 * Servi depuis le thème et non depuis les uploads : le déploiement de
 * production est un miroir SFTP du dépôt, donc un fichier commité ici part
 * automatiquement et reste versionné. Le visuel vivait auparavant dans
 * `wp-content/uploads/2026/08/`, absent des uploads locaux : l'aperçu n'était
 * alors vérifiable qu'en production.
 *
 * URL et non ID de pièce jointe : le fichier n'est pas en médiathèque du tout,
 * `attachment_url_to_postid()` rendrait donc 0 dans les deux environnements.
 *
 * JPEG et non WebP : Facebook lit le WebP, mais LinkedIn et WhatsApp ont un
 * historique documenté d'aperçus vides sur ce format. Le JPEG est le seul
 * format que tous les crawlers sociaux acceptent sans réserve.
 *
 * Dimensions déclarées = dimensions RÉELLES du fichier, relevées par
 * `magick identify` sur le fichier commité (2026-08-05) : 1200×630, le ratio
 * attendu par Facebook/LinkedIn/X. Le JPEG est une conversion 1:1 du WebP
 * d'origine — profil Display P3 transformé en sRGB, EXIF retiré, qualité 82 —
 * sans recadrage ni redimensionnement.
 */
define( '_180C_DEFAULT_SHARE_IMAGE', _180C_THEME_URI . '/assets/social/180-og-image.jpg' );
define( '_180C_DEFAULT_SHARE_IMAGE_WIDTH', 1200 );
define( '_180C_DEFAULT_SHARE_IMAGE_HEIGHT', 630 );
define( '_180C_DEFAULT_SHARE_IMAGE_TYPE', 'image/jpeg' );

/*
 * Logo de marque déclaré dans le nœud Organization (JSON-LD).
 *
 * Dimensions relevées par `getimagesize()` sur le fichier servi (2026-08-04) :
 * 1200×1200. L'ancien visuel (`cropped-Logo180C-CultureFood-Rond.webp`) était
 * déclaré 512×512 ; ces valeurs ne doivent pas être reconduites.
 *
 * Contre-vérifiées le 2026-08-05 par `magick identify` sur le fichier téléchargé
 * depuis l'URL ci-dessous : 1200×1200, 14 Ko, sans canal alpha. Le fichier de
 * production et la copie des uploads locaux ont le même sha256, la mesure vaut
 * donc pour les deux. Google exige au moins 112 px de côté : marge confortable.
 *
 * Les dimensions sont figées ici plutôt que lues à l'exécution : le fichier vit
 * à une URL de production et non sur le disque du thème, un relevé à la volée
 * coûterait une requête HTTP par page. À re-mesurer si l'URL change.
 *
 * Ne pas retoucher le fichier dans les uploads : il sert aussi d'image mise en
 * avant au produit « Abonnement cadeau — 12 mois ».
 */
define( '_180C_ORGANIZATION_LOGO', 'https://www.180c.fr/wp-content/uploads/2026/06/logo-abo.webp' );
define( '_180C_ORGANIZATION_LOGO_WIDTH', 1200 );
define( '_180C_ORGANIZATION_LOGO_HEIGHT', 1200 );

require_once _180C_THEME_DIR . '/inc/helpers.php';
require_once _180C_THEME_DIR . '/inc/favorites.php';
require_once _180C_THEME_DIR . '/inc/unsub-feedback.php';
require_once _180C_THEME_DIR . '/inc/menus.php';
require_once _180C_THEME_DIR . '/inc/theme-setup.php';
require_once _180C_THEME_DIR . '/inc/images.php';
require_once _180C_THEME_DIR . '/inc/media-filename.php';
require_once _180C_THEME_DIR . '/inc/favicon.php';
require_once _180C_THEME_DIR . '/inc/media-alt.php';
require_once _180C_THEME_DIR . '/inc/security.php';
require_once _180C_THEME_DIR . '/inc/security-headers.php';
require_once _180C_THEME_DIR . '/inc/enqueue.php';
require_once _180C_THEME_DIR . '/inc/email-typo.php';
require_once _180C_THEME_DIR . '/inc/perf-assets.php';
require_once _180C_THEME_DIR . '/inc/contact.php';
require_once _180C_THEME_DIR . '/inc/design-system.php';

require_once _180C_THEME_DIR . '/inc/cpts/recipe.php';
require_once _180C_THEME_DIR . '/inc/cpts/recipe-taxonomies.php';
require_once _180C_THEME_DIR . '/inc/recipe-access.php';
require_once _180C_THEME_DIR . '/inc/carnet-deeplink.php';
require_once _180C_THEME_DIR . '/inc/recipe-schema.php';
require_once _180C_THEME_DIR . '/inc/recipe-share.php';

require_once _180C_THEME_DIR . '/inc/mailchimp/api.php';
require_once _180C_THEME_DIR . '/inc/mailchimp/premium-tag.php';
require_once _180C_THEME_DIR . '/inc/mailchimp/source-tags.php';
require_once _180C_THEME_DIR . '/inc/mailchimp/subscription-sync.php';
require_once _180C_THEME_DIR . '/inc/mailchimp/unpaid-tag.php';
require_once _180C_THEME_DIR . '/inc/mailchimp/membership-sync.php';
require_once _180C_THEME_DIR . '/inc/mailchimp/email-change-sync.php';

require_once _180C_THEME_DIR . '/inc/author.php';
require_once _180C_THEME_DIR . '/inc/author-schema.php';

require_once _180C_THEME_DIR . '/inc/article.php';
require_once _180C_THEME_DIR . '/inc/article-access.php';
require_once _180C_THEME_DIR . '/inc/article-schema.php';

require_once _180C_THEME_DIR . '/inc/entry-header.php';
require_once _180C_THEME_DIR . '/inc/share-actions.php';
require_once _180C_THEME_DIR . '/inc/sponsor.php';
require_once _180C_THEME_DIR . '/inc/source-product.php';
require_once _180C_THEME_DIR . '/inc/search.php';
require_once _180C_THEME_DIR . '/inc/archives.php';
require_once _180C_THEME_DIR . '/inc/redirects.php';
require_once _180C_THEME_DIR . '/inc/feed.php';
require_once _180C_THEME_DIR . '/inc/home-modules.php';

require_once _180C_THEME_DIR . '/inc/acf/load-json.php';
require_once _180C_THEME_DIR . '/inc/acf/helpers.php';
require_once _180C_THEME_DIR . '/inc/acf/select-author.php';
require_once _180C_THEME_DIR . '/inc/acf/home-modules-visibility.php';
require_once _180C_THEME_DIR . '/inc/acf/step-title-cleaner.php';

// Sondage — lien Typeform. Le helper est chargé inconditionnellement (les
// touchpoints vivent en front) ; `options-page.php` ne l'est qu'en
// administration, le front lisant les valeurs par `get_field( …, 'option' )`
// sans avoir besoin que la page existe.
require_once _180C_THEME_DIR . '/inc/survey/link.php';

require_once _180C_THEME_DIR . '/inc/rest/routes.php';
require_once _180C_THEME_DIR . '/inc/rest/recipe-fields.php';

// Import des reportages de la revue papier (skill `archives-revue`) : meta
// d'articles inscriptibles par REST et résolution des auteurs.
require_once _180C_THEME_DIR . '/inc/archives/meta.php';
require_once _180C_THEME_DIR . '/inc/archives/author-endpoint.php';

require_once _180C_THEME_DIR . '/inc/woo/overrides.php';
require_once _180C_THEME_DIR . '/inc/woo/cart.php';
require_once _180C_THEME_DIR . '/inc/woo/cart-crosssells.php';
require_once _180C_THEME_DIR . '/inc/woo/order-review.php';
require_once _180C_THEME_DIR . '/inc/checkout.php';
require_once _180C_THEME_DIR . '/inc/onboarding.php';
require_once _180C_THEME_DIR . '/inc/woo/single-product.php';
require_once _180C_THEME_DIR . '/inc/woo/product-tabs-data.php';
require_once _180C_THEME_DIR . '/inc/woo/product-tabs-render.php';
require_once _180C_THEME_DIR . '/inc/woo/account-menu.php';
require_once _180C_THEME_DIR . '/inc/woo/account-helpers.php';
require_once _180C_THEME_DIR . '/inc/woo/subscription-status-labels.php';
require_once _180C_THEME_DIR . '/inc/woo/apple-google-pay.php';
require_once _180C_THEME_DIR . '/inc/woo/emails.php';
require_once _180C_THEME_DIR . '/inc/woo/lookalike-alert.php';
require_once _180C_THEME_DIR . '/inc/woo/memberships-i18n.php';

// ISBN dans les exports de commandes (plugin SkyVerge Customer/Order/Coupon
// Export). Chargé inconditionnellement : le fichier ne pose que des filtres du
// plugin d'export, inertes tant que celui-ci n'est pas actif.
require_once _180C_THEME_DIR . '/inc/woo/export-isbn.php';

require_once _180C_THEME_DIR . '/inc/gift/gift.php';
require_once _180C_THEME_DIR . '/inc/gift/rest.php';
require_once _180C_THEME_DIR . '/inc/gift/emails.php';
require_once _180C_THEME_DIR . '/inc/gift/wcsg-compat.php';

require_once _180C_THEME_DIR . '/inc/emails/wp-core-sender.php';
require_once _180C_THEME_DIR . '/inc/emails/admin-notifications.php';

// Digest hebdomadaire des messages du formulaire de contact. Chargé hors
// `is_admin()` : le cron qui l'exécute ne passe pas par l'administration. La
// collecte lit l'en-tête `X-180C-Origin` posé par inc/rest/contact.php, dont
// les constantes sont chargées inconditionnellement via inc/rest/routes.php.
require_once _180C_THEME_DIR . '/inc/contact-digest/collect.php';
require_once _180C_THEME_DIR . '/inc/contact-digest/digest.php';

require_once _180C_THEME_DIR . '/inc/notifications/config.php';
require_once _180C_THEME_DIR . '/inc/notifications/post-type.php';
require_once _180C_THEME_DIR . '/inc/notifications/onesignal-client.php';
// Après post-type.php : le sanitizer de segment de l'automation réutilise
// `_180c_notif_sanitize_segment()`. Ce fichier ne pose aucun hook de
// publication — le moteur qui les pose vit dans automation.php.
require_once _180C_THEME_DIR . '/inc/notifications/automation-settings.php';
// Après onesignal-client.php : le moteur consomme l'envoi différé et
// l'annulation. Chargé hors `is_admin()` : les hooks de publication doivent
// répondre aussi à une publication planifiée (cron) ou faite en REST.
require_once _180C_THEME_DIR . '/inc/notifications/automation.php';
// Après automation.php : la réconciliation consomme les constantes de meta et
// les fonctions d'annulation et de programmation du moteur.
require_once _180C_THEME_DIR . '/inc/notifications/automation-reconcile.php';

// Web push. APRÈS notifications/config.php : le module réutilise l'App ID, la
// clé REST et les accesseurs de configuration OneSignal, sans en redéfinir.
// Ordre interne imposé : subscriber-status (constantes du tag) avant
// onesignal-user (qui les écrit), lui-même avant sync-hooks (qui l'appelle).
require_once _180C_THEME_DIR . '/inc/push/worker.php';
require_once _180C_THEME_DIR . '/inc/push/subscriber-status.php';
require_once _180C_THEME_DIR . '/inc/push/onesignal-user.php';
require_once _180C_THEME_DIR . '/inc/push/sync-hooks.php';
require_once _180C_THEME_DIR . '/inc/push/config-js.php';

if ( is_admin() ) {
	require_once _180C_THEME_DIR . '/inc/admin/unsub-feedback.php';
	require_once _180C_THEME_DIR . '/inc/admin/unpaid-tag-screen.php';
	require_once _180C_THEME_DIR . '/inc/admin/contact-digest-screen.php';
	require_once _180C_THEME_DIR . '/inc/admin/dashboard-widget.php';
	require_once _180C_THEME_DIR . '/inc/admin/recipe-editor.php';
	require_once _180C_THEME_DIR . '/inc/admin/class-180c-email-tester.php';
	require_once _180C_THEME_DIR . '/inc/admin/email-testing.php';
	require_once _180C_THEME_DIR . '/inc/admin/seo-audit-screen.php';
	require_once _180C_THEME_DIR . '/inc/admin/term-seo-qualifier.php';
	require_once _180C_THEME_DIR . '/inc/stats/subscriber-widget.php';
	require_once _180C_THEME_DIR . '/inc/notifications/admin.php';
	require_once _180C_THEME_DIR . '/inc/notifications/automation-panel.php';
	require_once _180C_THEME_DIR . '/inc/notifications/automation-admin.php';
	require_once _180C_THEME_DIR . '/inc/gift/admin.php';
	require_once _180C_THEME_DIR . '/inc/survey/options-page.php';
	// En dernier : le rangement du menu opère sur les entrées posées par
	// tous les modules ci-dessus (Audit SEO, Test e-mails) et par les
	// extensions. Il ne s'accroche qu'à `admin_menu`, en priorité 9999.
	require_once _180C_THEME_DIR . '/inc/admin-menu.php';
}

require_once _180C_THEME_DIR . '/inc/seo/titles.php';
require_once _180C_THEME_DIR . '/inc/seo/title-map.php';
require_once _180C_THEME_DIR . '/inc/seo/description-map.php';
require_once _180C_THEME_DIR . '/inc/seo/meta-tags.php';
require_once _180C_THEME_DIR . '/inc/seo/title-resolver.php';
require_once _180C_THEME_DIR . '/inc/seo/overrides.php';
require_once _180C_THEME_DIR . '/inc/seo/schema.php';
// Après schema.php : les deux modules se greffent sur `180c/schema_graph`.
require_once _180C_THEME_DIR . '/inc/seo/video-schema.php';
require_once _180C_THEME_DIR . '/inc/seo/faq-schema.php';
require_once _180C_THEME_DIR . '/inc/seo/sitemap.php';
// Après description-map.php : /llms.txt reprend la description de l'accueil.
require_once _180C_THEME_DIR . '/inc/seo/llms-txt.php';
// Après meta-tags.php : le module consomme `_180c_seo_robots_has()`.
require_once _180C_THEME_DIR . '/inc/robots.php';
require_once _180C_THEME_DIR . '/inc/sitemap-page.php';
require_once _180C_THEME_DIR . '/inc/coordonnees-lieu.php';

require_once _180C_THEME_DIR . '/inc/newsletter-page.php';
require_once _180C_THEME_DIR . '/inc/about.php';
require_once _180C_THEME_DIR . '/inc/subscribe.php';
require_once _180C_THEME_DIR . '/inc/info-banner.php';

require_once _180C_THEME_DIR . '/inc/auth/rate-limit.php';
// Après rate-limit.php : un code 2FA erroné alimente le même compteur
// d'échecs (`wp_login_failed`) et le même blocage d'IP. Chargé hors
// `is_admin()` : la vérification s'accroche à `authenticate`, sur wp-login.php
// comme sur /connexion/ et le formulaire WooCommerce.
require_once _180C_THEME_DIR . '/inc/security-2fa.php';
require_once _180C_THEME_DIR . '/inc/auth/activation.php';
require_once _180C_THEME_DIR . '/inc/auth/login.php';
require_once _180C_THEME_DIR . '/inc/auth/register.php';
require_once _180C_THEME_DIR . '/inc/auth/password-reset.php';
require_once _180C_THEME_DIR . '/inc/auth/account-delete.php';

// Suppression de compte (web) : eligibility.php avant endpoint.php, qui
// consomme ses helpers dès le rendu de l'onglet Mon compte.
require_once _180C_THEME_DIR . '/inc/account-deletion/eligibility.php';
require_once _180C_THEME_DIR . '/inc/account-deletion/request.php';
require_once _180C_THEME_DIR . '/inc/account-deletion/externals.php';
require_once _180C_THEME_DIR . '/inc/account-deletion/log.php';
require_once _180C_THEME_DIR . '/inc/account-deletion/emails.php';
require_once _180C_THEME_DIR . '/inc/account-deletion/eraser.php';
require_once _180C_THEME_DIR . '/inc/account-deletion/endpoint.php';

// Consentement : AVANT les trois chargeurs de traceurs, qui dépendent de ses
// constantes (_180C_CONSENT_COOKIE, _180C_CONSENT_VERSION).
require_once _180C_THEME_DIR . '/inc/consent.php';

require_once _180C_THEME_DIR . '/inc/analytics/ga4.php';
require_once _180C_THEME_DIR . '/inc/analytics/umami.php';
require_once _180C_THEME_DIR . '/inc/analytics/order-attribution-consent.php';

// Statistiques abonnés : tracker front-end + moteur de calcul/cron chargés
// inconditionnellement (le cron WP n'est pas un contexte is_admin()). Le widget
// dashboard (admin uniquement) est requis dans le bloc is_admin() ci-dessus.
require_once _180C_THEME_DIR . '/inc/stats/last-seen-tracker.php';
require_once _180C_THEME_DIR . '/inc/stats/subscriber-stats.php';

require_once _180C_THEME_DIR . '/inc/cleanup/disable-comments.php';
require_once _180C_THEME_DIR . '/inc/cleanup/disable-emojis.php';
require_once _180C_THEME_DIR . '/inc/cleanup/remove-default-blocks.php';
require_once _180C_THEME_DIR . '/inc/cleanup/head-discovery.php';
require_once _180C_THEME_DIR . '/inc/cleanup/disable-xmlrpc.php';

// Façade YouTube (filtre render_block front : vignette cliquable + modale,
// iframe différée au clic). Ce n'est pas un bloc enregistré → require direct.
require_once _180C_THEME_DIR . '/inc/blocks/youtube-facade.php';

// Auto-enregistrement des blocs custom.
add_action(
	'init',
	function () {
		foreach ( glob( _180C_THEME_DIR . '/inc/blocks/*/block.json' ) as $block_json ) {
			register_block_type( dirname( $block_json ) );
		}
	}
);

// Catégorie de blocs personnalisée.
add_filter(
	'block_categories_all',
	function ( $categories ) {
		array_unshift(
			$categories,
			array(
				'slug'  => '180c',
				'title' => __( '180°C', '180c' ),
			)
		);
		return $categories;
	}
);
