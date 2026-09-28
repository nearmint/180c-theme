<?php
/**
 * Design System — lot « Blocs Gutenberg ».
 *
 * 14 blocs custom namespace 180c/ (server-rendered).
 * Tous en mode snapshot : requièrent champs ACF, posts, ou contexte Woo/user.
 * Seul le separator peut être rendu sans dépendance ; documenté en snapshot
 * pour la cohérence du lot.
 *
 * Classe racine convention : .block-180c-<slug>
 * État éditeur vide : .block-180c-<slug>--placeholder
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ---- 1. app-promo ----
// APP-RELEASE : fiche Design System du bloc app-promo masquée tant que les apps
// ne sont pas publiées. Retirer les marqueurs /* APP-RELEASE */ pour la rétablir.
/* APP-RELEASE
_180c_ds_component(
	array(
		'name'     => __( 'App Promo', '180c' ),
		'bem'      => '.block-180c-app-promo',
		'file'     => 'inc/blocks/app-promo/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--placeholder' ),
		'note'     => __( 'Bloc promotionnel pour les applications mobiles iOS et Android. Titre + description + liste de fonctionnalités (3 max.) + boutons store. Ignoré côté serveur si le cookie dismissed_app_banner=1 est présent. État --placeholder si aucun attribut.', '180c' ),
		'preview'  => '<section class="block-180c-app-promo">'
			. '<div class="block-180c-app-promo__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="block-180c-app-promo__content">'
			. '<h2 class="block-180c-app-promo__title">Cuisinez avec l\'app 180&#176;C</h2>'
			. '<p class="block-180c-app-promo__description">Des centaines de recettes, hors-ligne, sur votre iPhone et Android.</p>'
			. '<ul class="block-180c-app-promo__features" role="list">'
			. '<li class="block-180c-app-promo__feature"><svg class="block-180c-app-promo__feature-icon" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Recettes en mode hors-ligne</span></li>'
			. '<li class="block-180c-app-promo__feature"><svg class="block-180c-app-promo__feature-icon" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Gestion du carnet de recettes</span></li>'
			. '<li class="block-180c-app-promo__feature"><svg class="block-180c-app-promo__feature-icon" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Notifications nouveaux numéros</span></li>'
			. '</ul>'
			. '<div class="block-180c-app-promo__ctas">'
			. '<a class="block-180c-app-promo__store-btn block-180c-app-promo__store-btn--ios" href="#" aria-label="T&#233;l&#233;charger sur l\'App Store">'
			. '<svg class="block-180c-app-promo__store-icon" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>'
			. '<span class="block-180c-app-promo__store-label"><small>Disponible sur</small><strong>App Store</strong></span>'
			. '</a>'
			. '<a class="block-180c-app-promo__store-btn block-180c-app-promo__store-btn--android" href="#" aria-label="T&#233;l&#233;charger sur Google Play">'
			. '<svg class="block-180c-app-promo__store-icon" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="m3 20.5v-17c0-.83 1-.83 1.5-.5l17 8.5-17 8.5c-.5.33-1.5.33-1.5-.5z" opacity=".3"/><path d="M3 20.5V3.5c0-.83 1-.83 1.5-.5L21.5 12 4.5 21c-.5.33-1.5.33-1.5-.5zm2-1.86V5.36L18.04 12 5 18.64z"/></svg>'
			. '<span class="block-180c-app-promo__store-label"><small>Disponible sur</small><strong>Google Play</strong></span>'
			. '</a>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);
APP-RELEASE */

// ---- 2. articles-rail ----
_180c_ds_component(
	array(
		'name'     => __( 'Articles Rail', '180c' ),
		'bem'      => '.block-180c-articles-rail',
		'file'     => 'inc/blocks/articles-rail/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--placeholder', 'mode:recent', 'mode:category', 'mode:tag', 'mode:manual' ),
		'note'     => __( 'Rail horizontal de cartes article. 4 modes : recent (défaut), category (cat ID), tag (tag ID), manual (IDs explicites). Délègue le rendu de chaque carte à _180c_block_render_article_card(). État --placeholder si aucun article trouvé (visible éditeur uniquement).', '180c' ),
		'preview'  => '<section class="block-180c-articles-rail">'
			. '<h2 class="block-180c-articles-rail__title">La Gazette du mois</h2>'
			. '<div class="block-180c-articles-rail__track" role="list">'
			. '<div class="block-180c-articles-rail__item" role="listitem"><div class="card-180c card-180c--article card-180c--md"><div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div><div class="card-180c__body"><span class="card-180c__category">Recettes de saison</span><h3 class="card-180c__title">Le guide complet des champignons d\'automne</h3></div></div></div>'
			. '<div class="block-180c-articles-rail__item" role="listitem"><div class="card-180c card-180c--article card-180c--md"><div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div><div class="card-180c__body"><span class="card-180c__category">Technique</span><h3 class="card-180c__title">Ma&#238;triser la cuisson basse temp&#233;rature</h3></div></div></div>'
			. '<div class="block-180c-articles-rail__item" role="listitem"><div class="card-180c card-180c--article card-180c--md"><div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div><div class="card-180c__body"><span class="card-180c__category">Produits</span><h3 class="card-180c__title">Les meilleures huiles d\'olive de l\'ann&#233;e</h3></div></div></div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 3. complete-collection ----
_180c_ds_component(
	array(
		'name'     => __( 'Collection Compl&#232;te', '180c' ),
		'bem'      => '.block-180c-complete-collection',
		'file'     => 'inc/blocks/complete-collection/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--placeholder', '__badge--owned', '__badge--buy' ),
		'note'     => __( 'Grille de tous les numéros (product_cat=numeros). Affiche un badge « Déjà acheté » / « Acheter » si show_purchased_indicator=true et l\'utilisateur est connecté (cross-référence commandes Woo). État --placeholder si aucun numéro trouvé. Snapshot : dépend WooCommerce + commandes user.', '180c' ),
		'preview'  => '<section class="block-180c-complete-collection">'
			. '<div class="block-180c-complete-collection__header">'
			. '<h2 class="block-180c-complete-collection__title">La collection compl&#232;te</h2>'
			. '<p class="block-180c-complete-collection__description">Retrouvez tous les num&#233;ros depuis 2013.</p>'
			. '</div>'
			. '<div class="block-180c-complete-collection__grid">'
			. '<div class="block-180c-complete-collection__item"><a class="block-180c-complete-collection__item-link" href="#"><div class="block-180c-complete-collection__item-media"><span class="ds-ph" aria-hidden="true"></span><span class="block-180c-complete-collection__badge block-180c-complete-collection__badge--owned">D&#233;j&#224; achet&#233;</span></div><div class="block-180c-complete-collection__item-body"><span class="block-180c-complete-collection__item-title">180&#176;C n&#176;&#8239;42</span><span class="block-180c-complete-collection__item-date">Automne 2024</span></div></a></div>'
			. '<div class="block-180c-complete-collection__item"><a class="block-180c-complete-collection__item-link" href="#"><div class="block-180c-complete-collection__item-media"><span class="ds-ph" aria-hidden="true"></span><span class="block-180c-complete-collection__badge block-180c-complete-collection__badge--buy">Acheter</span></div><div class="block-180c-complete-collection__item-body"><span class="block-180c-complete-collection__item-title">180&#176;C n&#176;&#8239;41</span><span class="block-180c-complete-collection__item-date">&#201;t&#233; 2024</span></div></a></div>'
			. '<div class="block-180c-complete-collection__item"><a class="block-180c-complete-collection__item-link" href="#"><div class="block-180c-complete-collection__item-media"><span class="ds-ph" aria-hidden="true"></span><span class="block-180c-complete-collection__badge block-180c-complete-collection__badge--buy">Acheter</span></div><div class="block-180c-complete-collection__item-body"><span class="block-180c-complete-collection__item-title">180&#176;C n&#176;&#8239;40</span><span class="block-180c-complete-collection__item-date">Printemps 2024</span></div></a></div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 4. hero ----
_180c_ds_component(
	array(
		'name'     => __( 'Hero', '180c' ),
		'bem'      => '.block-180c-hero',
		'file'     => 'inc/blocks/hero/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--full', '--split', '--placeholder' ),
		'note'     => __( 'Hero éditorial : article, recette ou produit sélectionné (selected_post_id). display_style=full (image plein cadre) ou split (image/texte côte à côte). Supertitle auto selon le post_type. CTA : cta_label + cta_url (ou permalink natif). Image hero : thumbnail ou ACF hero_image (recettes). fetchpriority=high, loading=eager sur l\'image. État --placeholder si aucun post sélectionné ou post non publié.', '180c' ),
		'preview'  => '<div class="block-180c-hero block-180c-hero--split">'
			. '<div class="block-180c-hero__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="block-180c-hero__content">'
			. '<span class="block-180c-hero__supertitle">Recettes de saison</span>'
			. '<h1 class="block-180c-hero__title">Tarte Tatin revisit&#233;e aux poires et romarin</h1>'
			. '<p class="block-180c-hero__excerpt">Une version &#233;l&#233;gante du classique fran&#231;ais, avec des poires conf&#233;rence r&#244;ties et une touche de romarin frais&#8239;&#8212;&#8239;d&#233;licieuse en toute saison.</p>'
			. '<a class="btn btn--primary block-180c-hero__cta" href="#">Voir la recette</a>'
			. '</div>'
			. '</div>',
	)
);

// ---- 5. last-publication ----
_180c_ds_component(
	array(
		'name'     => __( 'Derni&#232;re Publication', '180c' ),
		'bem'      => '.block-180c-last-publication',
		'file'     => 'inc/blocks/last-publication/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--placeholder', 'source:auto', 'source:manual' ),
		'note'     => __( 'Mise en avant de la dernière publication de la catégorie produit « livres ». source=auto (WP_Query dernière date) ou source=manual (manual_product_id). Affiche : eyebrow « Dernière publication », titre, description courte, CTA « Acheter » (add_to_cart_url). État --placeholder si aucun produit trouvé. Snapshot : requiert WooCommerce.', '180c' ),
		'preview'  => '<section class="block-180c-last-publication">'
			. '<div class="block-180c-last-publication__media"><span class="ds-ph" aria-hidden="true"></span></div>'
			. '<div class="block-180c-last-publication__content">'
			. '<span class="block-180c-last-publication__eyebrow">Derni&#232;re publication</span>'
			. '<h2 class="block-180c-last-publication__title"><a href="#">Les sauces — le grand livre de r&#233;f&#233;rence</a></h2>'
			. '<div class="block-180c-last-publication__description"><p>Deux cent cinquante recettes de sauces class&#233;es par famille, avec conseils de conservation et accords mets&#8239;&#8212;&#8239;le manuel indispensable de tout cuisinier s&#233;rieux.</p></div>'
			. '<a class="btn btn--primary block-180c-last-publication__cta" href="#">Acheter</a>'
			. '</div>'
			. '</section>',
	)
);

// ---- 6. latest-issues ----
_180c_ds_component(
	array(
		'name'     => __( 'Derniers Num&#233;ros', '180c' ),
		'bem'      => '.block-180c-latest-issues',
		'file'     => 'inc/blocks/latest-issues/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--placeholder' ),
		'note'     => __( 'Rail des N derniers numéros (product_cat=numeros, 2–12 items). Chaque item : couverture (medium) + titre + date de publication (ACF publication_date). Lien vers la fiche produit WooCommerce. État --placeholder si aucun produit trouvé. Snapshot : requiert WooCommerce.', '180c' ),
		'preview'  => '<section class="block-180c-latest-issues">'
			. '<h2 class="block-180c-latest-issues__title">Les derniers num&#233;ros</h2>'
			. '<div class="block-180c-latest-issues__track" role="list" aria-label="Les derniers num&#233;ros">'
			. '<div class="block-180c-latest-issues__item" role="listitem"><a class="block-180c-latest-issues__item-link" href="#"><div class="block-180c-latest-issues__item-media"><span class="ds-ph" aria-hidden="true"></span></div><div class="block-180c-latest-issues__item-body"><span class="block-180c-latest-issues__item-title">180&#176;C n&#176;&#8239;43</span><span class="block-180c-latest-issues__item-date">Hiver 2025</span></div></a></div>'
			. '<div class="block-180c-latest-issues__item" role="listitem"><a class="block-180c-latest-issues__item-link" href="#"><div class="block-180c-latest-issues__item-media"><span class="ds-ph" aria-hidden="true"></span></div><div class="block-180c-latest-issues__item-body"><span class="block-180c-latest-issues__item-title">180&#176;C n&#176;&#8239;42</span><span class="block-180c-latest-issues__item-date">Automne 2024</span></div></a></div>'
			. '<div class="block-180c-latest-issues__item" role="listitem"><a class="block-180c-latest-issues__item-link" href="#"><div class="block-180c-latest-issues__item-media"><span class="ds-ph" aria-hidden="true"></span></div><div class="block-180c-latest-issues__item-body"><span class="block-180c-latest-issues__item-title">180&#176;C n&#176;&#8239;41</span><span class="block-180c-latest-issues__item-date">&#201;t&#233; 2024</span></div></a></div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 7. newsletter-form ----
_180c_ds_component(
	array(
		'name'     => __( 'Newsletter Form', '180c' ),
		'bem'      => '.block-180c-newsletter-form',
		'file'     => 'inc/blocks/newsletter-form/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--with-image', '--placeholder' ),
		'note'     => __( 'Formulaire d\'inscription newsletter. Variante --with-image quand image_url est fourni. Contrat .newsletter-form partagé avec parts/modules/newsletter_form.php. Soumission via REST POST /180c/v1/newsletter/subscribe. Double opt-in Mailchimp. Feedback aria-live dans .newsletter-form__feedback. Snapshot pour la déterminisme (IDs uniques générés par wp_unique_id()).', '180c' ),
		'preview'  => '<section class="block-180c-newsletter-form block-180c-newsletter-form--with-image">'
			. '<div class="block-180c-newsletter-form__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="block-180c-newsletter-form__content">'
			. '<h2 class="block-180c-newsletter-form__title">Restez aux fourneaux</h2>'
			. '<p class="block-180c-newsletter-form__description">Chaque semaine, l\'actualit&#233; culinaire, les recettes de saison et les coulisses de la r&#233;daction.</p>'
			. '<form class="block-180c-newsletter-form__form" action="#" method="POST" novalidate data-form="newsletter">'
			. '<div class="block-180c-newsletter-form__field">'
			. '<label class="block-180c-newsletter-form__label" for="ds-nl-email">Adresse e-mail</label>'
			. '<div class="block-180c-newsletter-form__input-group">'
			. '<input id="ds-nl-email" class="block-180c-newsletter-form__input" type="email" name="email" autocomplete="email" required aria-required="true" placeholder="vous@example.com">'
			. '<button class="btn btn--primary block-180c-newsletter-form__submit" type="submit">S\'inscrire</button>'
			. '</div>'
			. '</div>'
			. '<p class="block-180c-newsletter-form__consent">En vous inscrivant, vous recevez un e-mail de confirmation (double opt-in). D&#233;sabonnement possible &#224; tout moment.</p>'
			. '<p class="newsletter-form__feedback" role="status" aria-live="polite" aria-atomic="true" hidden></p>'
			. '</form>'
			. '</div>'
			. '</section>',
	)
);

// ---- 8. paywall ----
_180c_ds_component(
	array(
		'name'     => __( 'Paywall', '180c' ),
		'bem'      => '.block-180c-paywall-wrapper',
		'file'     => 'inc/blocks/paywall/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '.paywall', '.paywall__gradient', '.paywall__inner', '.paywall__actions', '.paywall__login-link' ),
		'note'     => __( 'Enveloppe bloc .block-180c-paywall-wrapper qui délègue à parts/paywall.php → .paywall. Ne se rend que dans le contexte d\'une recette (is_singular("recipe")) sauf pour les éditeurs. Le partiel paywall.php vérifie _180c_recipe_is_paywalled() avant de rendre. Gradient de fondu .paywall__gradient + icône cadenas + titre + description + actions (abonnement + connexion). Snapshot : requiert recette premium + utilisateur non abonné.', '180c' ),
		'preview'  => '<div class="block-180c-paywall-wrapper">'
			. '<aside class="paywall" role="region" aria-label="Paywall recette">'
			. '<div class="paywall__gradient" aria-hidden="true"></div>'
			. '<div class="paywall__inner">'
			. '<div class="paywall__icon" aria-hidden="true"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>'
			. '<h2 class="paywall__title">Acc&#233;dez &#224; la recette compl&#232;te</h2>'
			. '<p class="paywall__desc">Abonnez-vous aux recettes en ligne pour d&#233;couvrir les ingr&#233;dients et toutes les &#233;tapes.</p>'
			. '<div class="paywall__actions">'
			. '<a class="btn btn--primary" href="#">Je m\'abonne</a>'
			. '<a class="btn btn--ghost" href="#">J\'ai d&#233;j&#224; un compte</a>'
			. '</div>'
			. '<p class="paywall__login-link">D&#233;j&#224; abonn&#233; ? <a href="#">Connexion</a></p>'
			. '</div>'
			. '</aside>'
			. '</div>',
	)
);

// ---- 9. product-card ----
_180c_ds_component(
	array(
		'name'     => __( 'Product Card (bloc)', '180c' ),
		'bem'      => '.block-180c-product-card',
		'file'     => 'inc/blocks/product-card/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--placeholder', '.card-180c--product', '.card-180c--owned' ),
		'note'     => __( 'Enveloppe bloc qui délègue à _180c_block_render_product_card() (inc/blocks/_helpers.php). Nécessite un product_id valide et WooCommerce actif. État --placeholder si product_id absent ou produit non publié. Le rendu interne est .card-180c.card-180c--product. Voir le lot Cards pour la documentation détaillée de la carte elle-même.', '180c' ),
		'preview'  => '<div class="block-180c-product-card">'
			. '<div class="card-180c card-180c--product card-180c--md">'
			. '<div class="card-180c__media"><span class="ds-ph" aria-hidden="true"></span></div>'
			. '<div class="card-180c__body">'
			. '<span class="card-180c__badge">Num&#233;ro</span>'
			. '<h3 class="card-180c__title">180&#176;C n&#176;&#8239;43 — Hiver 2025</h3>'
			. '<p class="card-180c__price">8,90 &#8364;</p>'
			. '<a class="btn btn--primary card-180c__cta" href="#">Acheter</a>'
			. '</div>'
			. '</div>'
			. '</div>',
	)
);

// ---- 10. quote ----
_180c_ds_component(
	array(
		'name'     => __( 'Citation', '180c' ),
		'bem'      => '.block-180c-quote',
		'file'     => 'inc/blocks/quote/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--placeholder' ),
		'note'     => __( 'Bloc citation éditorial (<blockquote>). Texte saisi dans l\'éditeur (balises inline autorisées : em, strong, br, span). Attribution optionnelle dans <cite>. État --placeholder si le champ texte est vide. Snapshot : les attributs proviennent du bloc Gutenberg saisi manuellement.', '180c' ),
		'preview'  => '<blockquote class="block-180c-quote">'
			. '<p class="block-180c-quote__text">La cuisine est l\'art de transformer de simples ingr&#233;dients en un moment inoubliable — c\'est l\'alchimie du quotidien.</p>'
			. '<cite class="block-180c-quote__attribution">Jane Doe, r&#233;dactrice en chef</cite>'
			. '</blockquote>',
	)
);

// ---- 11. recipe-card ----
_180c_ds_component(
	array(
		'name'     => __( 'Recipe Card (bloc)', '180c' ),
		'bem'      => '.block-180c-recipe-card',
		'file'     => 'inc/blocks/recipe-card/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--placeholder', '.card-180c--recipe', '.card-180c--sm', '.card-180c--md', '.card-180c--lg' ),
		'note'     => __( 'Enveloppe bloc qui délègue à _180c_block_render_recipe_card() (inc/blocks/_helpers.php). Nécessite un recipe_id valide (CPT recipe, statut publish). État --placeholder si recipe_id absent ou recette non publiée. Le rendu interne est .card-180c.card-180c--recipe. Voir le lot Cards pour la documentation détaillée.', '180c' ),
		'preview'  => '<div class="block-180c-recipe-card">'
			. '<div class="card-180c card-180c--recipe card-180c--md">'
			. '<div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card-180c__body">'
			. '<span class="card-180c__season">Automne</span>'
			. '<h3 class="card-180c__title">Velouté de potimarron au lait de coco</h3>'
			. '<span class="card-180c__meta">30 min · Facile</span>'
			. '</div>'
			. '</div>'
			. '</div>',
	)
);

// ---- 12. recipes-rail ----
_180c_ds_component(
	array(
		'name'     => __( 'Recipes Rail', '180c' ),
		'bem'      => '.block-180c-recipes-rail',
		'file'     => 'inc/blocks/recipes-rail/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--placeholder', 'mode:recent', 'mode:season', 'mode:type', 'mode:manual' ),
		'note'     => __( 'Rail horizontal de cartes recette (CPT recipe). 4 modes : recent (défaut), season (recipe_season term_id), type (recipe_type term_id), manual (IDs explicites). Délègue à _180c_block_render_recipe_card(). État --placeholder si aucune recette trouvée (éditeur uniquement). Snapshot : requiert CPT recipe peuplé.', '180c' ),
		'preview'  => '<section class="block-180c-recipes-rail">'
			. '<h2 class="block-180c-recipes-rail__title">Recettes d\'automne</h2>'
			. '<div class="block-180c-recipes-rail__track" role="list">'
			. '<div class="block-180c-recipes-rail__item" role="listitem"><div class="card-180c card-180c--recipe card-180c--md"><div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div><div class="card-180c__body"><span class="card-180c__season">Automne</span><h3 class="card-180c__title">Tarte aux cèpes et comté</h3></div></div></div>'
			. '<div class="block-180c-recipes-rail__item" role="listitem"><div class="card-180c card-180c--recipe card-180c--md"><div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div><div class="card-180c__body"><span class="card-180c__season">Automne</span><h3 class="card-180c__title">Confit de canard aux pommes r&#244;ties</h3></div></div></div>'
			. '<div class="block-180c-recipes-rail__item" role="listitem"><div class="card-180c card-180c--recipe card-180c--md"><div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div><div class="card-180c__body"><span class="card-180c__season">Automne</span><h3 class="card-180c__title">Velouté de panais et noisettes torr&#233;fi&#233;es</h3></div></div></div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 13. separator ----
_180c_ds_component(
	array(
		'name'     => __( 'S&#233;parateur', '180c' ),
		'bem'      => '.block-180c-separator',
		'file'     => 'inc/blocks/separator/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--line', '--image' ),
		'note'     => __( 'Séparateur de sections. style=line (défaut) → <hr class="block-180c-separator__line">. style=image → image décorative (role=presentation, alt=""). role=separator aria-hidden=true sur le wrapper. Snapshot pour cohérence du lot (le bloc ne nécessite pas de contenu ACF mais a un attribut image_url optionnel).', '180c' ),
		'preview'  => '<div class="ds-stack">'
			. '<div class="block-180c-separator block-180c-separator--line" role="separator" aria-hidden="true"><hr class="block-180c-separator__line"></div>'
			. '<div class="block-180c-separator block-180c-separator--image" role="separator" aria-hidden="true"><span class="ds-ph ds-ph--wide" aria-hidden="true" style="height: 4rem; display: block;"></span></div>'
			. '</div>',
	)
);

// ---- 14. subscription-banner ----
_180c_ds_component(
	array(
		'name'     => __( 'Bandeau d\'abonnement', '180c' ),
		'bem'      => '.block-180c-subscription-banner',
		'file'     => 'inc/blocks/subscription-banner/render.php',
		'mode'     => 'snapshot',
		'variants' => array( '--with-image', '--placeholder', 'hide_for_subscribers' ),
		'note'     => __( 'Bannière d\'appel à l\'abonnement. Masquée automatiquement si hide_for_subscribers=true et _180c_is_recipe_subscriber() retourne vrai. Variante --with-image si image_url fourni. URL d\'abonnement via filtre 180c/subscription_url (défaut /abonnement/). Snapshot : la visibilité dépend du statut abonné de l\'utilisateur.', '180c' ),
		'preview'  => '<section class="block-180c-subscription-banner block-180c-subscription-banner--with-image" aria-label="Offre d\'abonnement">'
			. '<div class="block-180c-subscription-banner__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="block-180c-subscription-banner__content">'
			. '<h2 class="block-180c-subscription-banner__title">Acc&#233;dez &#224; toutes les recettes</h2>'
			. '<p class="block-180c-subscription-banner__description">Plus de 2&#8239;000 recettes en ligne, nouvelles recettes chaque semaine, acc&#232;s illimit&#233; sur tous vos appareils.</p>'
			. '<a class="btn btn--primary block-180c-subscription-banner__cta" href="#">Je m\'abonne</a>'
			. '</div>'
			. '</section>',
	)
);
