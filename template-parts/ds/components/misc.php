<?php
/**
 * Design System — lot « Pages / shells & divers ».
 *
 * Composants de surface : page Mon carnet, grille d'archive, modale de consentement,
 * sticky app bar, smart banner (stub), réassurance produit, cross-promo
 * numérique, paywall recette et lightbox.
 *
 * Les composants dont le rendu réel requiert un contexte utilisateur connecté,
 * une session WooCommerce ou un appel à une librairie externe sont documentés
 * en mode « snapshot » (HTML statique fidèle), conformément à la règle
 * d'inventaire (justification explicite dans chaque note).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ---- Mon carnet (favoris) ----
_180c_ds_component(
	array(
		'name'     => __( 'Mon carnet (favoris)', '180c' ),
		'bem'      => '.mon-carnet / .recipes-grid',
		'file'     => '_180c_render_mon_carnet() (inc/favorites.php) ; template-mon-carnet.php',
		'mode'     => 'snapshot',
		'variants' => array( '.recipes-grid__item', '.archive-header', '.archive-header__title' ),
		'note'     => __( 'Snapshot : requiert un utilisateur connecté avec des favoris en BDD (tables user_favorites). La grille .recipes-grid reçoit des .card-180c--recipe via _180c_render_recipe_card(). En état vide le template rend parts/empty-state.php.', '180c' ),
		'preview'  => '<main class="site-main site-container mon-carnet" style="background:var(--paper);padding-block:var(--space-lg);">'
			. '<header class="archive-header" style="padding-block:var(--space-md);border-bottom:1px solid var(--rule);margin-bottom:var(--space-lg);">'
			. '<div class="container-180c">'
			. '<h1 class="archive-header__title" style="font-family:var(--font-display);font-size:var(--text-2xl);font-weight:700;color:var(--ink);margin:0;">Mon carnet de recettes</h1>'
			. '</div>'
			. '</header>'
			. '<div class="container-180c">'
			. '<ul class="recipes-grid" role="list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:var(--space-md);list-style:none;padding:0;margin:0;">'
			. '<li class="recipes-grid__item" role="listitem"><span class="ds-ph" aria-hidden="true"></span></li>'
			. '<li class="recipes-grid__item" role="listitem"><span class="ds-ph" aria-hidden="true"></span></li>'
			. '<li class="recipes-grid__item" role="listitem"><span class="ds-ph" aria-hidden="true"></span></li>'
			. '<li class="recipes-grid__item" role="listitem"><span class="ds-ph" aria-hidden="true"></span></li>'
			. '</ul>'
			. '</div>'
			. '</main>',
	)
);

// ---- Archive grid ----
_180c_ds_component(
	array(
		'name'     => __( 'Archive grid', '180c' ),
		'bem'      => '.archive-grid',
		'file'     => 'src/css/components/archive-grid.css ; _180c_archives_render_card() (inc/archives.php)',
		'mode'     => 'snapshot',
		'variants' => array( '.archive-grid__item' ),
		'note'     => __( 'Snapshot : requiert une WP_Query active (contexte archive). Grille responsive 1 col / 2 cols (640px) / 4 cols (1024px). Enfants : .card-180c--article, --recipe ou --product via _180c_archives_render_card(). Pas de variante 3 cols (decision DS).', '180c' ),
		'preview'  => '<ul class="archive-grid" role="list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:var(--space-5);list-style:none;padding:0;margin:0;">'
			. '<li class="archive-grid__item"><span class="ds-ph" aria-hidden="true"></span></li>'
			. '<li class="archive-grid__item"><span class="ds-ph" aria-hidden="true"></span></li>'
			. '<li class="archive-grid__item"><span class="ds-ph" aria-hidden="true"></span></li>'
			. '<li class="archive-grid__item"><span class="ds-ph" aria-hidden="true"></span></li>'
			. '</ul>',
	)
);

// ---- CMP consentement ----
_180c_ds_component(
	array(
		'name'     => __( 'Modale de consentement', '180c' ),
		'bem'      => '.consent / .consent__modal',
		'file'     => 'parts/consent-modal.php ; src/css/components/consent.css',
		'mode'     => 'snapshot',
		'variants' => array( '.consent__overlay', '.consent__header', '.consent__body', '.consent__scroll', '.consent__actions', '.consent__btn--decline', '.consent__btn--accept' ),
		'note'     => __( 'Snapshot : modale maison, aucune bibliotheque tierce (src/js/modules/consent.js), masquee par defaut (hidden). Le markup PHP (parts/consent-modal.php) est rendu fidelement ici en statique. Choix binaire Refuser / Accepter, sans ecran granulaire. Cookie Consent Mode v2 (4 cles GA4). Seul .consent__scroll deborde : logo, titre et boutons restent visibles quelle que soit la hauteur de viewport.', '180c' ),
		'preview'  => '<div style="position:relative;background:var(--paper);border:1px solid var(--rule);border-radius:var(--radius-lg);max-width:480px;overflow:hidden;">'
			. '<div class="consent__modal" style="padding:var(--space-lg);">'
			. '<header class="consent__header" style="margin-bottom:var(--space-md);display:flex;align-items:center;gap:var(--space-sm);">'
			. '<div class="consent__logo" role="img" aria-label="180 C" style="font-family:var(--font-display);font-weight:700;font-size:var(--text-xl);color:var(--color-accent);">180&#176;C</div>'
			. '</header>'
			. '<div class="consent__body">'
			. '<h2 id="consent-title-ds" class="consent__title" style="font-family:var(--font-display);font-size:var(--text-lg);font-weight:700;color:var(--ink);margin:0 0 var(--space-sm);">Votre choix pour vos donn&#233;es</h2>'
			. '<p class="consent__text" style="font-size:var(--text-sm);color:var(--muted);margin:0 0 var(--space-sm);">Pour mettre le site et l&#8217;application mobile de 180&#176;C &#224; votre disposition nous utilisons des cookies ou technologies similaires.</p>'
			. '<p class="consent__text" style="font-size:var(--text-sm);color:var(--muted);margin:0 0 var(--space-sm);">Certaines de ces technologies sont n&#233;cessaires pour faire fonctionner nos services correctement. D&#8217;autres sont optionnelles mais contribuent &#224; faciliter votre exp&#233;rience.</p>'
			. '<p class="consent__question" style="font-size:var(--text-sm);color:var(--ink);margin:0;"><strong>Acceptez-vous que 180&#176;C emploie des cookies ou technologies similaires utiles &#224; son fonctionnement ?</strong></p>'
			. '</div>'
			. '<footer class="consent__actions" style="display:flex;gap:var(--space-sm);margin-top:var(--space-md);">'
			. '<button type="button" class="consent__btn consent__btn--decline btn btn--ghost" style="flex:1;" aria-disabled="true">Refuser</button>'
			. '<button type="button" class="consent__btn consent__btn--accept btn btn--primary" style="flex:1;" aria-disabled="true">Accepter</button>'
			. '</footer>'
			. '</div>'
			. '</div>',
	)
);

// APP-RELEASE : fiche Design System du smart banner masquée tant que les apps
// ne sont pas publiées. Retirer les marqueurs /* APP-RELEASE */ pour la rétablir.
/* APP-RELEASE
// ---- Smart banner (stub — non implemente) ----
_180c_ds_component(
	array(
		'name'    => __( 'Smart banner (Android web)', '180c' ),
		'bem'     => '',
		'file'    => 'parts/smart-banner.php',
		'mode'    => 'snapshot',
		'note'    => __( 'Prevu — non implemente en v1 (stub Phase 8 ; iOS via meta tag apple-itunes-app dans wp_head). Le fichier parts/smart-banner.php existe mais ne contient aucun markup. Le smart banner Android (detection User-Agent, cookie dismissed_app_banner, tracking GA4) est reporte a la Phase 8.', '180c' ),
		'preview' => '',
	)
);
APP-RELEASE */

// ---- Product reassurance ----
_180c_ds_component(
	array(
		'name'     => __( 'Reassurance produit', '180c' ),
		'bem'      => '.product-reassurance',
		'file'     => 'parts/product-reassurance.php',
		'mode'     => 'snapshot',
		'variants' => array( '__item', '__icon', '__label' ),
		'note'     => __( 'Snapshot : le template conditionne les badges au contexte WooCommerce ($product instanceof WC_Product, is_purchasable(), needs_shipping()). Specimen : configuration produit physique achetable (3 badges). Rendu via hook woocommerce_single_product_summary priorite 35.', '180c' ),
		'preview'  => '<ul class="product-reassurance" role="list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:var(--space-sm);">'
			. '<li class="product-reassurance__item" style="display:flex;align-items:center;gap:var(--space-sm);">'
			. '<svg class="product-reassurance__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3 4 6v5c0 5 3.4 8.3 8 10 4.6-1.7 8-5 8-10V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>'
			. '<span class="product-reassurance__label" style="font-size:var(--text-sm);color:var(--ink);">Paiement s&#233;curis&#233;</span>'
			. '</li>'
			. '<li class="product-reassurance__item" style="display:flex;align-items:center;gap:var(--space-sm);">'
			. '<svg class="product-reassurance__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg>'
			. '<span class="product-reassurance__label" style="font-size:var(--text-sm);color:var(--ink);">Exp&#233;dition soign&#233;e</span>'
			. '</li>'
			. '<li class="product-reassurance__item" style="display:flex;align-items:center;gap:var(--space-sm);">'
			. '<svg class="product-reassurance__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 11.5a8.5 8.5 0 0 1-12.2 7.7L3 21l1.8-5.8A8.5 8.5 0 1 1 21 11.5Z"/></svg>'
			. '<span class="product-reassurance__label" style="font-size:var(--text-sm);color:var(--ink);">Service client &#224; l&#8217;&#233;coute</span>'
			. '</li>'
			. '</ul>',
	)
);

// ---- Product digital cross-promo ----
_180c_ds_component(
	array(
		'name'     => __( 'Cross-promo abonnement numerique', '180c' ),
		'bem'      => '.product-crosspromo',
		'file'     => 'parts/product-digital-crosspromo.php',
		'mode'     => 'snapshot',
		'variants' => array( '__body', '__kicker', '__title', '__text', '__cta' ),
		'note'     => __( 'Snapshot : conditionne a un contexte WooCommerce ($product instanceof WC_Product, !is_virtual()). Visible uniquement sur produits physiques (masque pour epub, abonnements, produits virtuels). CTA vers /abonnement/ (filtrable via _180c_subscription_url).', '180c' ),
		'preview'  => '<aside class="product-crosspromo" style="border:1px solid var(--rule);border-radius:var(--radius-lg);padding:var(--space-lg);background:var(--cream);display:flex;flex-direction:column;gap:var(--space-md);">'
			. '<div class="product-crosspromo__body">'
			. '<p class="product-crosspromo__kicker" style="font-family:var(--font-display);font-size:var(--text-xs);text-transform:uppercase;letter-spacing:0.08em;color:var(--color-accent);margin:0 0 var(--space-2xs);">Abonnement num&#233;rique</p>'
			. '<h2 class="product-crosspromo__title" style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;color:var(--ink);margin:0 0 var(--space-sm);">Acc&#233;dez &#224; toutes les recettes en ligne</h2>'
			. '<p class="product-crosspromo__text" style="font-size:var(--text-sm);color:var(--muted);margin:0;">L&#8217;int&#233;gralit&#233; des recettes 180&#176;C, en illimit&#233; et 100&#160;% num&#233;rique, sur le site et les applications.</p>'
			. '</div>'
			. '<a class="btn btn--secondary product-crosspromo__cta" href="#" aria-disabled="true">D&#233;couvrir l&#8217;abonnement</a>'
			. '</aside>',
	)
);

// ---- Recipe paywall (variante) ----
_180c_ds_component(
	array(
		'name'     => __( 'Paywall recette (variante)', '180c' ),
		'bem'      => '.paywall.recipe-paywall',
		'file'     => 'parts/recipe-paywall.php',
		'mode'     => 'snapshot',
		'variants' => array( '.recipe-paywall__preview', '.recipe-paywall__preview-list', '.paywall__inner', '.paywall__icon', '.paywall__title', '.paywall__desc', '.paywall__actions', '.paywall__login-link', '.paywall__benefits', '.paywall__benefit', '.paywall__benefit-check', '.recipe-sticky-cta' ),
		'note'     => __( 'Snapshot : requiert le contexte recette (get_the_ID(), get_permalink(), is_user_logged_in()). Variante du composant .paywall (lot Etats) : ajoute .recipe-paywall__preview (lignes factices floutees aria-hidden) et .recipe-sticky-cta (mini-banniere sticky revelee par recipe.js au scroll). Inclus depuis single-recipe.php quand _180c_user_has_recipe_access() retourne false.', '180c' ),
		'preview'  => '<aside class="paywall recipe-paywall" role="region" aria-label="Acces reserve aux abonnes" style="background:var(--cream);border:1px solid var(--rule);border-radius:var(--radius-lg);overflow:hidden;">'
			. '<div class="recipe-paywall__preview" aria-hidden="true" style="padding:var(--space-md) var(--space-lg);opacity:.35;filter:blur(3px);pointer-events:none;border-bottom:1px solid var(--rule);">'
			. '<ul class="recipe-paywall__preview-list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:var(--space-xs);">'
			. '<li style="height:.9em;background:var(--rule);border-radius:var(--radius-sm);width:60%;">&nbsp;</li>'
			. '<li style="height:.9em;background:var(--rule);border-radius:var(--radius-sm);width:45%;">&nbsp;</li>'
			. '<li style="height:.9em;background:var(--rule);border-radius:var(--radius-sm);width:52%;">&nbsp;</li>'
			. '</ul>'
			. '</div>'
			. '<div class="paywall__inner" style="padding:var(--space-lg);text-align:center;">'
			. '<div class="paywall__icon" aria-hidden="true" style="display:flex;justify-content:center;margin-bottom:var(--space-sm);color:var(--muted);">'
			. '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
			. '</div>'
			. '<h2 class="paywall__title" style="font-family:var(--font-display);font-size:var(--text-2xl);font-weight:700;color:var(--ink);margin:0 0 var(--space-sm);">Envie de lire la suite ?</h2>'
			. '<p class="paywall__desc" style="font-size:var(--text-base);color:var(--muted);margin:0 0 var(--space-md);">Les recettes de 180&#176;C en int&#233;gralit&#233; &#224; partir de 2,99 &#8364; / mois.</p>'
			. '<div class="paywall__actions" style="margin-bottom:var(--space-sm);">'
			. '<a class="btn btn--primary btn--lg" href="#" aria-disabled="true">Je m&#8217;abonne</a>'
			. '</div>'
			. '<p class="paywall__login-link" style="font-size:var(--text-sm);color:var(--muted);margin:0 0 var(--space-md);">D&#233;j&#224; abonn&#233; ? <a href="#" aria-disabled="true">Se connecter</a></p>'
			. '<ul class="paywall__benefits" role="list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:var(--space-xs);text-align:left;">'
			. '<li class="paywall__benefit" style="display:flex;align-items:center;gap:var(--space-xs);font-size:var(--text-sm);color:var(--ink);">'
			. '<svg class="paywall__benefit-check" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
			. 'Acc&#232;s &#224; 2&#160;500+ recettes 100% saison'
			. '</li>'
			. '<li class="paywall__benefit" style="display:flex;align-items:center;gap:var(--space-xs);font-size:var(--text-sm);color:var(--ink);">'
			. '<svg class="paywall__benefit-check" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
			. 'Tous les anciens num&#233;ros en version digitale'
			. '</li>'
			. '<li class="paywall__benefit" style="display:flex;align-items:center;gap:var(--space-xs);font-size:var(--text-sm);color:var(--ink);">'
			. '<svg class="paywall__benefit-check" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
			. 'Sans engagement, r&#233;siliable &#224; tout moment'
			. '</li>'
			. '</ul>'
			. '</div>'
			. '</aside>',
	)
);

// ---- Lightbox ----
_180c_ds_component(
	array(
		'name'     => __( 'Lightbox', '180c' ),
		'bem'      => '.lightbox',
		'file'     => 'src/css/components/lightbox.css + src/js/modules/lightbox.js',
		'mode'     => 'snapshot',
		'variants' => array( '.lightbox[hidden]', '.lightbox__image', '.lightbox__caption', '.lightbox__close', '.lightbox__close-icon', 'html.lightbox-lock' ),
		'note'     => __( 'Snapshot : composant pilote entierement par JS (ouverture, fermeture, chargement src). La lightbox reelle est position:fixed inset:0 z-index:var(--z-modal). Apercu rendu en position relative dans le flux DS. .lightbox__close herite de .overlay-icon-btn (media.css). html.lightbox-lock pose overflow:hidden pendant l\'ouverture.', '180c' ),
		'preview'  => '<div style="position:relative;background:rgb(0 0 0 / 0.7);border-radius:var(--radius-lg);padding:var(--space-xl) var(--space-lg);display:flex;flex-direction:column;align-items:center;gap:var(--space-md);min-height:260px;">'
			. '<button type="button" class="lightbox__close" aria-label="Fermer la lightbox" style="position:absolute;top:var(--space-sm);right:var(--space-sm);background:rgb(255 255 255 / 0.12);border:none;border-radius:var(--radius-pill);width:2.75rem;height:2.75rem;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;" aria-disabled="true">'
			. '<span class="lightbox__close-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></span>'
			. '</button>'
			. '<span class="ds-ph ds-ph--wide" aria-hidden="true" style="border-radius:var(--radius-md);max-height:200px;width:100%;max-width:360px;display:block;"></span>'
			. '<p class="lightbox__caption media-caption" style="font-size:var(--text-sm);color:rgba(255,255,255,.75);text-align:center;margin:0;max-width:min(80ch,100%);">Tarte fine aux tomates anciennes et basilic frais &#8212; num&#233;ro 18</p>'
			. '</div>',
	)
);

// ---- CDD optin (Cahiers de Delphine) ----
_180c_ds_component(
	array(
		'name'     => __( 'CDD optin (Cahiers de Delphine)', '180c' ),
		'bem'      => '.cdd-optin',
		'file'     => 'src/css/components/cdd-optin.css (gabarit supprimé)',
		'mode'     => 'snapshot',
		'variants' => array( '__hero', '__hero-title', '__hero-subtitle', '__signup', '__rail', '__rail-card-title' ),
		'note'     => __( 'RETIRÉ — le gabarit page-cahiers-de-delphine.php a été supprimé, la page /newsletter/ le remplace et /cahiers-de-delphine/ redirige en 301. Plus aucune page du site ne rend ce composant ; seule la feuille cdd-optin.css subsiste, en attente d’une purge dédiée (nécessite un rebuild Vite). Snapshot conservé pour mémoire de l’inventaire.', '180c' ),
		'preview'  => '<div class="cdd-optin" style="background:var(--paper);border-radius:var(--radius-md);overflow:hidden;">'
			. '<section class="cdd-optin__hero" style="padding:var(--space-lg);text-align:center;">'
			. '<div class="cdd-optin__hero-wrap">'
			. '<h1 class="cdd-optin__hero-title" style="font-family:var(--font-display);font-size:var(--text-2xl);color:var(--ink);margin:0 0 var(--space-2xs);">Les Cahiers de Delphine</h1>'
			. '<p class="cdd-optin__hero-subtitle" style="color:var(--muted);margin:0 auto;max-width:48ch;">Recevez chaque mois les carnets de recettes exclusifs des Cahiers de Delphine.</p>'
			. '<span class="ds-ph ds-ph--wide" aria-hidden="true" style="display:block;max-width:360px;margin:var(--space-md) auto 0;"></span>'
			. '</div>'
			. '</section>'
			. '<section class="cdd-optin__signup" style="padding:0 var(--space-lg) var(--space-lg);">'
			. '<div class="cdd-optin__signup-wrap" style="max-width:420px;margin:0 auto;display:flex;gap:var(--space-2xs);">'
			. '<input class="input" type="email" placeholder="vous@example.com" style="flex:1;">'
			. '<button type="button" class="btn btn--primary">Je m&#8217;inscris</button>'
			. '</div>'
			. '</section>'
			. '</div>',
	)
);
