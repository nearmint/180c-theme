<?php
/**
 * Design System — lot « Home modules ».
 *
 * Modules ACF Flexible Content (`home_modules`) orchestrés par
 * _180c_render_home_modules() (inc/home-modules.php). Wrapper commun
 * `.home-module` + classe spécifique par layout. Tous en mode snapshot
 * (contexte ACF / WP_Query / user requis).
 *
 * Aperçus : placeholder neutre `.ds-ph` pour les médias (aucune photo
 * recadrée). Les cartes internes sont documentées dans le lot « Cards ».
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ---- 1. Rail (.rail-180c) — composant réutilisable mutualisé ----
_180c_ds_component(
	array(
		'name'     => __( 'Rail (composant mutualisé)', '180c' ),
		'bem'      => '.rail-180c',
		'file'     => 'inc/home-modules.php (_180c_render_rail()) + src/css/components/home/_rails.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'__head',
			'__title',
			'__view-all',
			'__track',
			'__item',
			'__nav',
			'__arrow --prev',
			'__arrow --next',
			'--has-nav',
		),
		'note'     => __( 'Shell HTML mutualisé par articles_rail, recipes_rail et products_rail. Mobile : scroll-snap horizontal. Desktop : grille auto-fit. Les flèches __nav sont révélées par rail.js uniquement quand le track déborde. Le modifier home-module--{slug} est ajouté par _180c_render_rail() via le paramètre modifier. Les cartes internes (.card-180c) sont documentées dans le lot Cards.', '180c' ),
		'preview'  => '<section class="home-module rail-180c home-module--articles_rail" role="region" aria-label="À la une">'
			. '<div class="container-180c">'
			. '<div class="rail-180c__head">'
			. '<h2 class="rail-180c__title">La Gazette</h2>'
			. '<a class="rail-180c__view-all" href="#">Voir tout <span class="screen-reader-text">La Gazette</span></a>'
			. '</div>'
			. '<ul class="rail-180c__track" role="list" tabindex="0">'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-base);margin:.5rem 0 0;">Risotto aux cèpes et parmesan</p></li>'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-base);margin:.5rem 0 0;">Le grand tour des fromages affinés</p></li>'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-base);margin:.5rem 0 0;">Tartare de bœuf à la truffe noire</p></li>'
			. '</ul>'
			. '<div class="rail-180c__nav" aria-hidden="true">'
			. '<button type="button" class="rail-180c__arrow rail-180c__arrow--prev" tabindex="-1" aria-label="Précédent">'
			. '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '</button>'
			. '<button type="button" class="rail-180c__arrow rail-180c__arrow--next" tabindex="-1" aria-label="Suivant">'
			. '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '</button>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 2. Hero éditorial ----
_180c_ds_component(
	array(
		'name'     => __( 'Hero éditorial', '180c' ),
		'bem'      => '.home-module.home-module--hero_editorial + .hero-180c',
		'file'     => 'parts/modules/hero_editorial.php + src/css/components/home/hero-editorial.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'--split',
			'--full',
			'__media',
			'__image',
			'__content',
			'__title',
			'__excerpt',
		),
		'note'     => __( 'Couverture magazine pleine largeur (break-out 100vw). Rendu par _180c_render_hero_post(). Accepte post, recipe ou product. Overlay dégradé pour la lisibilité. Le display_style ACF (full|split) ajoute le modifier BEM. Nécessite un post_object ACF valide.', '180c' ),
		'preview'  => '<section class="home-module home-module--hero_editorial" aria-label="À la une">'
			. '<div class="container-180c" style="padding-inline:0;max-width:none;">'
			. '<div class="hero-180c" style="min-height:18rem;background-color:var(--cream);display:flex;align-items:flex-end;position:relative;overflow:hidden;">'
			. '<span class="ds-ph" aria-hidden="true" style="position:absolute;inset:0;width:100%;height:100%;display:block;"></span>'
			. '<div class="hero-180c__content" style="position:relative;width:100%;padding:2rem var(--space-md);">'
			. '<h2 class="hero-180c__title" style="font-family:var(--font-display);font-size:var(--text-3xl);font-weight:700;color:#fff;margin:0 0 .5rem;text-shadow:0 1px 4px rgba(0,0,0,.4);">Cabillaud en croûte de noisettes, sauce vierge aux agrumes</h2>'
			. '<p class="hero-180c__excerpt" style="font-family:var(--font-body);color:rgba(255,255,255,.9);margin:0 0 1rem;">Une recette fraîche et généreuse signée Jane Doe, idéale pour le déjeuner dominical.</p>'
			. '<a class="btn btn--primary" href="#">Lire la recette</a>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 3. Hero abonnement ----
_180c_ds_component(
	array(
		'name'     => __( 'Hero abonnement', '180c' ),
		'bem'      => '.home-module.home-module--hero_subscribe + .hero-subscribe-180c',
		'file'     => 'parts/modules/hero_subscribe.php + src/css/components/home/hero-subscribe.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'__inner',
			'__media',
			'__image',
			'__content',
			'__eyebrow',
			'__title',
			'__text',
			'__benefits',
			'__benefit',
			'__cta',
		),
		'note'     => __( "Porte l'unique <h1> de la page Boutique (candidat LCP). Split deux colonnes ≥1024px uniquement si une image est présente (:has()). L'image n'est jamais recadrée (height:auto). CTA cible la page /abonnement/ (filtre 180c/subscription_url).", '180c' ),
		'preview'  => '<section class="home-module home-module--hero_subscribe hero-subscribe-180c" aria-labelledby="ds-hero-sub-title">'
			. '<div class="hero-subscribe-180c__inner container-180c">'
			. '<div class="hero-subscribe-180c__media"><span class="ds-ph" aria-hidden="true" style="display:block;width:100%;aspect-ratio:4/3;border-radius:var(--radius-lg);"></span></div>'
			. '<div class="hero-subscribe-180c__content">'
			. '<p class="hero-subscribe-180c__eyebrow">Abonnement numérique</p>'
			. '<h1 id="ds-hero-sub-title" class="hero-subscribe-180c__title">Toutes les recettes 180°C, sans limite</h1>'
			. '<p class="hero-subscribe-180c__text">Accédez à plus de 2 000 recettes en ligne, nouvelles chaque semaine, sur tous vos appareils.</p>'
			. '<ul class="hero-subscribe-180c__benefits">'
			. '<li class="hero-subscribe-180c__benefit">Recettes exclusives chaque semaine</li>'
			/* APP-RELEASE : décommenter à la sortie des apps mobiles.
			. '<li class="hero-subscribe-180c__benefit">Applications iOS et Android incluses</li>' */
			. '<li class="hero-subscribe-180c__benefit">Sans engagement, résiliation à tout moment</li>'
			. '</ul>'
			. '<a class="btn btn--primary hero-subscribe-180c__cta" href="/abonnement/">Je m\'abonne</a>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 4. Rail d'articles ----
_180c_ds_component(
	array(
		'name'     => __( 'Rail d\'articles', '180c' ),
		'bem'      => '.home-module.rail-180c.home-module--articles_rail',
		'file'     => 'parts/modules/articles_rail.php + src/css/components/home/articles-rail.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'mode : recent | category | tag | manual',
		),
		'note'     => __( 'Délègue le rendu HTML à _180c_render_rail(). Requête WP_Query (post). Mode recent mis en cache (transient 5 min). Voir tout pointe vers le terme en mode category/tag. Les cartes internes (.card-180c--article) sont documentées dans le lot Cards.', '180c' ),
		'preview'  => '<section class="home-module rail-180c home-module--articles_rail" role="region" aria-label="Derniers articles">'
			. '<div class="container-180c">'
			. '<div class="rail-180c__head">'
			. '<h2 class="rail-180c__title">Derniers articles</h2>'
			. '<a class="rail-180c__view-all" href="#">Voir tout <span class="screen-reader-text">Derniers articles</span></a>'
			. '</div>'
			. '<ul class="rail-180c__track" role="list" tabindex="0">'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Article · carte documentée dans le lot Cards</p></li>'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Article · carte documentée dans le lot Cards</p></li>'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Article · carte documentée dans le lot Cards</p></li>'
			. '</ul>'
			. '<div class="rail-180c__nav" aria-hidden="true">'
			. '<button type="button" class="rail-180c__arrow rail-180c__arrow--prev" tabindex="-1" aria-label="Précédent"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
			. '<button type="button" class="rail-180c__arrow rail-180c__arrow--next" tabindex="-1" aria-label="Suivant"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 5. Rail de recettes ----
_180c_ds_component(
	array(
		'name'     => __( 'Rail de recettes', '180c' ),
		'bem'      => '.home-module.rail-180c.home-module--recipes_rail',
		'file'     => 'parts/modules/recipes_rail.php + src/css/components/home/recipes-rail.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'mode : recent | category | season | current_season | type | manual',
			'exclude_displayed (dédoublonnage inter-modules)',
		),
		'note'     => __( 'Même shell rail-180c que articles_rail. Spécificités : CPT recipe, modes de filtrage par saison/type, dédoublonnage inter-modules via _180c_displayed_recipe_ids(). Les cartes internes (.card-180c--recipe, ratio 4/5) sont documentées dans le lot Cards.', '180c' ),
		'preview'  => '<section class="home-module rail-180c home-module--recipes_rail" role="region" aria-label="Recettes de saison">'
			. '<div class="container-180c">'
			. '<div class="rail-180c__head">'
			. '<h2 class="rail-180c__title">Recettes de saison</h2>'
			. '<a class="rail-180c__view-all" href="#">Voir tout <span class="screen-reader-text">Recettes de saison</span></a>'
			. '</div>'
			. '<ul class="rail-180c__track" role="list" tabindex="0">'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph" aria-hidden="true" style="aspect-ratio:4/5;"></div><p style="font-family:var(--font-body);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Recette · carte documentée dans le lot Cards</p></li>'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph" aria-hidden="true" style="aspect-ratio:4/5;"></div><p style="font-family:var(--font-body);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Recette · carte documentée dans le lot Cards</p></li>'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph" aria-hidden="true" style="aspect-ratio:4/5;"></div><p style="font-family:var(--font-body);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Recette · carte documentée dans le lot Cards</p></li>'
			. '</ul>'
			. '<div class="rail-180c__nav" aria-hidden="true">'
			. '<button type="button" class="rail-180c__arrow rail-180c__arrow--prev" tabindex="-1" aria-label="Précédent"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
			. '<button type="button" class="rail-180c__arrow rail-180c__arrow--next" tabindex="-1" aria-label="Suivant"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 5 bis. Slider de recettes ----
// Numéroté « 5 bis » et non 6 : renuméroter les dix fiches suivantes pour
// insérer celle-ci gonflerait le diff sans rien apprendre à personne.
_180c_ds_component(
	array(
		'name'     => __( 'Slider de recettes', '180c' ),
		'bem'      => '.home-module.recipes-slider',
		'file'     => 'parts/modules/recipes_slider.php + inc/blocks/recipes-slider/ + src/css/components/home/recipes-slider.css + src/js/modules/recipes-slider.js',
		'mode'     => 'snapshot',
		'variants' => array(
			'count : 1 à 10 (plafond dur, une vignette de frise par recette)',
			'is-enhanced (JS actif) : révèle flèches et frise',
			'__overlay-link : rend le slide entier cliquable',
			'< 768px : carte plein cadre, titre incrusté, frise réduite à des barres',
			'exclude_displayed (dédoublonnage inter-modules)',
		),
		'note'     => __( 'Format « une recette en vedette à la fois », distinct du rail dense : il ne le remplace pas, les deux peuvent cohabiter. Markup mutualisé par _180c_render_recipes_slider() entre le module home et le bloc Gutenberg 180c/recipes-slider. Le viewport est un scroller natif à scroll-snap : sans JS le premier slide reste lisible et les suivants atteignables au doigt, et les contrôles restent masqués tant qu\'ils ne pilotent rien (classe is-enhanced). Navigation circulaire — les flèches ne sont jamais désactivées. Le slide entier est cliquable via un LIEN DE RECOUVREMENT (.recipes-slider__overlay-link, aria-hidden + tabindex=-1), et non via le ::after d\'un bouton : mesuré en production, le pseudo couvrait bien la carte mais le clic n\'atteignait jamais le lien. Le titre n\'est donc pas un lien et la rangée d\'actions repasse au-dessus du recouvrement. L\'image est en position absolue : sans cela son ratio intrinsèque dictait la hauteur de tout le module. Le chapô vient du champ ACF `recipe_intro` (« Introduction ») et non de l\'extrait natif, jamais tronqué. Sous 768px le slide bascule en carte plein cadre à texte incrusté (motif du hero éditorial) pour tenir dans le viewport. L\'aperçu ci-dessous force is-enhanced pour montrer les contrôles.', '180c' ),
		'preview'  => '<section class="home-module recipes-slider is-enhanced" role="region" aria-label="Les dernières recettes publiées">'
			. '<div class="container-180c">'
			. '<div class="recipes-slider__head">'
			. '<h2 class="recipes-slider__title">Les dernières recettes publiées</h2>'
			. '<div class="recipes-slider__controls">'
			. '<div class="recipes-slider__arrows">'
			. '<button type="button" class="recipes-slider__arrow recipes-slider__arrow--prev" tabindex="-1" aria-label="Recette précédente"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6"/></svg></button>'
			. '<button type="button" class="recipes-slider__arrow recipes-slider__arrow--next" tabindex="-1" aria-label="Recette suivante"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg></button>'
			. '</div>'
			. '<a class="recipes-slider__view-all" href="#">Toutes les recettes</a>'
			. '</div>'
			. '</div>'
			. '<div class="recipes-slider__viewport" role="group" aria-roledescription="carrousel" aria-label="Les dernières recettes publiées" tabindex="-1">'
			. '<div class="recipes-slider__track">'
			. '<article class="recipes-slider__slide">'
			. '<a class="recipes-slider__overlay-link" href="#" aria-hidden="true" tabindex="-1"></a>'
			. '<div class="recipes-slider__media"><div class="ds-ph" aria-hidden="true" style="width:100%;height:100%;"></div></div>'
			. '<div class="recipes-slider__panel">'
			. '<span class="recipes-slider__eyebrow">Pâtisserie · Automne</span>'
			. '<h3 class="recipes-slider__slide-title">Tarte tatin aux figues et romarin</h3>'
			. '<p class="recipes-slider__intro">Des figues juste éclatées, du romarin frais et un caramel à peine ambré sur une pâte feuilletée maison.</p>'
			. '<div class="recipes-slider__actions">'
			. '<a class="btn btn--primary recipes-slider__cta" href="#">Voir la recette</a>'
			. '</div>'
			. '</div>'
			. '</article>'
			. '</div>'
			. '</div>'
			. '<ol class="recipes-slider__thumbs">'
			. '<li class="recipes-slider__thumb"><button type="button" class="recipes-slider__thumb-button" aria-current="true"><span class="recipes-slider__thumb-rail" aria-hidden="true"><span class="recipes-slider__thumb-bar"></span></span><span class="recipes-slider__thumb-num" aria-hidden="true">01</span><span class="recipes-slider__thumb-label">Tarte tatin aux figues et romarin</span></button></li>'
			. '<li class="recipes-slider__thumb"><button type="button" class="recipes-slider__thumb-button" aria-current="false"><span class="recipes-slider__thumb-rail" aria-hidden="true"><span class="recipes-slider__thumb-bar"></span></span><span class="recipes-slider__thumb-num" aria-hidden="true">02</span><span class="recipes-slider__thumb-label">Velouté de butternut au lait de coco</span></button></li>'
			. '<li class="recipes-slider__thumb"><button type="button" class="recipes-slider__thumb-button" aria-current="false"><span class="recipes-slider__thumb-rail" aria-hidden="true"><span class="recipes-slider__thumb-bar"></span></span><span class="recipes-slider__thumb-num" aria-hidden="true">03</span><span class="recipes-slider__thumb-label">Poireaux grillés, vinaigrette au miso</span></button></li>'
			. '</ol>'
			. '</div>'
			. '</section>',
	)
);

// ---- 6. Rail de produits ----
_180c_ds_component(
	array(
		'name'     => __( 'Rail de produits', '180c' ),
		'bem'      => '.home-module.rail-180c.home-module--products_rail',
		'file'     => 'parts/modules/products_rail.php + src/css/components/home/articles-rail.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'mode : recent | category | manual',
		),
		'note'     => __( 'Même shell rail-180c. CPT product (WooCommerce). Mode recent mis en cache (transient 5 min) : aucune donnée propre à l\'utilisateur dans la carte. Les cartes internes (.card-180c--product) sont documentées dans le lot Cards.', '180c' ),
		'preview'  => '<section class="home-module rail-180c home-module--products_rail" role="region" aria-label="Notre boutique">'
			. '<div class="container-180c">'
			. '<div class="rail-180c__head">'
			. '<h2 class="rail-180c__title">Notre boutique</h2>'
			. '<a class="rail-180c__view-all" href="#">Voir tout <span class="screen-reader-text">Notre boutique</span></a>'
			. '</div>'
			. '<ul class="rail-180c__track" role="list" tabindex="0">'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Produit · carte documentée dans le lot Cards</p></li>'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Produit · carte documentée dans le lot Cards</p></li>'
			. '<li class="rail-180c__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Produit · carte documentée dans le lot Cards</p></li>'
			. '</ul>'
			. '<div class="rail-180c__nav" aria-hidden="true">'
			. '<button type="button" class="rail-180c__arrow rail-180c__arrow--prev" tabindex="-1" aria-label="Précédent"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
			. '<button type="button" class="rail-180c__arrow rail-180c__arrow--next" tabindex="-1" aria-label="Suivant"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 7. Carnet de recettes ----
_180c_ds_component(
	array(
		'name'     => __( 'Carnet de recettes', '180c' ),
		'bem'      => '.home-module.carnet',
		'file'     => 'parts/modules/carnet.php + src/css/components/home/carnet.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'--cta (visiteur non abonné)',
			'--empty (abonné sans favori)',
			'__cta-inner',
			'__title',
			'__cta-text',
			'__actions',
			'__subscribe',
			'__login',
			'__empty-text',
			'__browse',
		),
		'note'     => __( 'Trois états selon le contexte utilisateur. État abonné avec favoris : rail mutualisé (modifier carnet). État CTA : break-out pleine largeur, fond accent jaune, aucune donnée premium exposée. État vide : gracieux, lien vers /recettes/. Aucune requête favoris exécutée si l\'utilisateur n\'est pas abonné.', '180c' ),
		'preview'  => '<section class="home-module carnet carnet--cta" aria-labelledby="ds-carnet-cta-title">'
			. '<div class="container-180c">'
			. '<div class="carnet__cta-inner">'
			. '<h2 id="ds-carnet-cta-title" class="carnet__title">Accédez à toutes les recettes de 180°C</h2>'
			. '<p class="carnet__cta-text">Abonnez-vous pour retrouver ici vos recettes favorites, disponibles à tout moment.</p>'
			. '<div class="carnet__actions">'
			. '<a class="btn carnet__subscribe" href="/abonnement/">Je m\'abonne</a>'
			. '<a class="carnet__login" href="/mon-compte/">J\'ai déjà un compte</a>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</section>'
			. '<section class="home-module carnet carnet--empty" aria-labelledby="ds-carnet-empty-title" style="margin-block-start:1rem;">'
			. '<div class="container-180c">'
			. '<h2 id="ds-carnet-empty-title" class="carnet__title">Mon carnet de recettes</h2>'
			. '<p class="carnet__empty-text">Vous n\'avez pas encore sauvegardé de recette. Parcourez notre catalogue et ajoutez vos coups de cœur.</p>'
			. '<a class="btn btn--secondary carnet__browse" href="/recettes/">Parcourir les recettes</a>'
			. '</div>'
			. '</section>',
	)
);

// ---- 8. Tuiles de catégories ----
_180c_ds_component(
	array(
		'name'     => __( 'Tuiles de catégories', '180c' ),
		'bem'      => '.home-module.home-module--category_tiles',
		'file'     => 'parts/modules/category_tiles.php + src/css/components/home/category-tiles.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'taxonomy : category | recipe_category | product_cat',
			'--category-tiles-columns (prop inline, 2-6)',
			'__grid',
			'__item',
			'__tile',
			'__media',
			'__image',
			'__name',
			'__count',
		),
		'note'     => __( 'Grid de termes de taxonomie. Le nombre de colonnes est piloté par la prop inline --category-tiles-columns (champ ACF columns). Mobile : scroll horizontal avec snap, cohérent avec les rails. Image de tuile : term meta thumbnail_id (convention WooCommerce pour product_cat ; fond token pour category/recipe_category). Animation skeleton shimmer sur __media via rail.js.', '180c' ),
		'preview'  => '<section class="home-module home-module--category_tiles" role="region" aria-label="Catégories">'
			. '<div class="container-180c category-tiles">'
			. '<h2 class="home-module__title">Nos catégories</h2>'
			. '<ul class="category-tiles__grid" role="list" style="--category-tiles-columns:4;">'
			. '<li class="category-tiles__item" role="listitem">'
			. '<a class="category-tiles__tile" href="#" style="display:flex;flex-direction:column;border:1px solid var(--rule);border-radius:var(--radius-md);overflow:hidden;text-decoration:none;">'
			. '<span class="category-tiles__media ds-ph" aria-hidden="true" style="aspect-ratio:4/3;display:block;"></span>'
			. '<span class="category-tiles__name" style="font-family:var(--font-display);padding:var(--space-md) var(--space-md) 0;">Recettes du marché</span>'
			. '<span class="category-tiles__count" style="font-family:var(--font-display);font-size:var(--text-xs);text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:4px var(--space-md) var(--space-md);">142 recettes</span>'
			. '</a>'
			. '</li>'
			. '<li class="category-tiles__item" role="listitem">'
			. '<a class="category-tiles__tile" href="#" style="display:flex;flex-direction:column;border:1px solid var(--rule);border-radius:var(--radius-md);overflow:hidden;text-decoration:none;">'
			. '<span class="category-tiles__media ds-ph" aria-hidden="true" style="aspect-ratio:4/3;display:block;"></span>'
			. '<span class="category-tiles__name" style="font-family:var(--font-display);padding:var(--space-md) var(--space-md) 0;">Pâtisseries & desserts</span>'
			. '<span class="category-tiles__count" style="font-family:var(--font-display);font-size:var(--text-xs);text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:4px var(--space-md) var(--space-md);">98 recettes</span>'
			. '</a>'
			. '</li>'
			. '<li class="category-tiles__item" role="listitem">'
			. '<a class="category-tiles__tile" href="#" style="display:flex;flex-direction:column;border:1px solid var(--rule);border-radius:var(--radius-md);overflow:hidden;text-decoration:none;">'
			. '<span class="category-tiles__media ds-ph" aria-hidden="true" style="aspect-ratio:4/3;display:block;"></span>'
			. '<span class="category-tiles__name" style="font-family:var(--font-display);padding:var(--space-md) var(--space-md) 0;">Entrées & salades</span>'
			. '<span class="category-tiles__count" style="font-family:var(--font-display);font-size:var(--text-xs);text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:4px var(--space-md) var(--space-md);">61 recettes</span>'
			. '</a>'
			. '</li>'
			. '<li class="category-tiles__item" role="listitem">'
			. '<a class="category-tiles__tile" href="#" style="display:flex;flex-direction:column;border:1px solid var(--rule);border-radius:var(--radius-md);overflow:hidden;text-decoration:none;">'
			. '<span class="category-tiles__media ds-ph" aria-hidden="true" style="aspect-ratio:4/3;display:block;"></span>'
			. '<span class="category-tiles__name" style="font-family:var(--font-display);padding:var(--space-md) var(--space-md) 0;">Plats végétariens</span>'
			. '<span class="category-tiles__count" style="font-family:var(--font-display);font-size:var(--text-xs);text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:4px var(--space-md) var(--space-md);">75 recettes</span>'
			. '</a>'
			. '</li>'
			. '</ul>'
			. '</div>'
			. '</section>',
	)
);

// ---- 9. Grille paginée ----
_180c_ds_component(
	array(
		'name'     => __( 'Grille paginée', '180c' ),
		'bem'      => '.home-module.home-module--grid_paginated',
		'file'     => 'parts/modules/grid_paginated.php + src/css/components/home/grid-paginated.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'source : post | recipe | product',
			'--grid-paginated-columns (prop inline, 2-4)',
			'__grid',
			'__item',
			'__pager',
			'exclude_displayed (dédoublonnage recettes)',
		),
		'note'     => __( 'Grille de navigation (pas de scroll horizontal : une grille de browsing). Pagination réelle via paginate_links(). La query var de pagination est suffixée par l\'index du module (gp_{index}) pour que deux grilles coexistent sans collision. Mobile : une seule colonne. Les cartes internes sont documentées dans le lot Cards.', '180c' ),
		'preview'  => '<section class="home-module home-module--grid_paginated" role="region" aria-label="Tous les articles">'
			. '<div class="container-180c">'
			. '<h2 class="home-module__title">Tous les articles</h2>'
			. '<ul class="grid-paginated__grid" role="list" style="--grid-paginated-columns:3;">'
			. '<li class="grid-paginated__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Carte documentée dans le lot Cards</p></li>'
			. '<li class="grid-paginated__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Carte documentée dans le lot Cards</p></li>'
			. '<li class="grid-paginated__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Carte documentée dans le lot Cards</p></li>'
			. '<li class="grid-paginated__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Carte documentée dans le lot Cards</p></li>'
			. '<li class="grid-paginated__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Carte documentée dans le lot Cards</p></li>'
			. '<li class="grid-paginated__item" role="listitem"><div class="ds-ph ds-ph--wide" aria-hidden="true"></div><p style="font-family:var(--font-display);font-size:var(--text-sm);margin:.5rem 0 0;color:var(--muted);">Carte documentée dans le lot Cards</p></li>'
			. '</ul>'
			. '<nav class="grid-paginated__pager" aria-label="Pagination : Tous les articles">'
			. '<a class="page-numbers" href="#">1</a>'
			. '<span class="page-numbers current">2</span>'
			. '<a class="page-numbers" href="#">3</a>'
			. '<a class="page-numbers next" href="#">Suivant</a>'
			. '</nav>'
			. '</div>'
			. '</section>',
	)
);

// ---- 10. Dernière publication ----
_180c_ds_component(
	array(
		'name'     => __( 'Dernière publication', '180c' ),
		'bem'      => '.home-module.home-module--last_publication + .publication-xl',
		'file'     => 'parts/modules/last_publication.php + src/css/components/home/last-publication.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'source : auto (catégorie livres) | manual',
			'__media',
			'__cover',
			'__content',
			'__title',
			'__excerpt',
			'__price',
			'__cta',
		),
		'note'     => __( 'Rendu délégué à _180c_render_publication_xl() (inc/blocks/_helpers.php). Deux colonnes ≥768px (couverture 2/3 portrait | contenu). Mode auto : requête wc_get_products() catégorie livres, 1 résultat le plus récent. Nécessite WooCommerce actif.', '180c' ),
		'preview'  => '<section class="home-module home-module--last_publication" aria-label="Dernière publication">'
			. '<div class="container-180c">'
			. '<div class="publication-xl" style="display:grid;grid-template-columns:minmax(0,280px) minmax(0,1fr);gap:3rem;align-items:center;">'
			. '<div class="publication-xl__media">'
			. '<span class="ds-ph" aria-hidden="true" style="display:block;width:100%;aspect-ratio:2/3;max-height:420px;box-shadow:var(--shadow-lg);"></span>'
			. '</div>'
			. '<div class="publication-xl__content" style="display:flex;flex-direction:column;gap:var(--space-md);">'
			. '<h2 class="publication-xl__title" style="font-family:var(--font-display);font-size:var(--text-4xl);font-weight:700;margin:0;">Cahier de recettes — Printemps 2025</h2>'
			. '<p class="publication-xl__excerpt" style="font-family:var(--font-body);line-height:var(--line-height-relaxed);margin:0;">Un tour du marché en 48 recettes : légumes primeurs, herbes fraîches et saveurs douces de la nouvelle saison.</p>'
			. '<p class="publication-xl__price" style="font-family:var(--font-display);font-size:var(--text-2xl);font-weight:600;">19,90&nbsp;€</p>'
			. '<a class="btn btn--primary publication-xl__cta" href="#" style="align-self:flex-start;">Acheter</a>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 11. Formulaire newsletter (module) ----
_180c_ds_component(
	array(
		'name'     => __( 'Formulaire newsletter (module home)', '180c' ),
		'bem'      => '.home-module.home-module--newsletter_form + .newsletter-180c',
		'file'     => 'parts/modules/newsletter_form.php + src/css/components/home/newsletter-form.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'--with-image (2 colonnes ≥768px)',
			'home-module--newsletter-with-image',
			'__media',
			'__image',
			'__content',
			'__title',
			'__description',
			'__form (.newsletter-form contrat partagé)',
		),
		'note'     => __( 'Soumet en AJAX vers POST /wp-json/180c/v1/newsletter/subscribe via [data-form="newsletter"] (src/js/modules/newsletter-form.js). Double opt-in côté serveur. Le composant .newsletter-form (input + submit + consent + feedback) est le contrat partagé avec le bloc Gutenberg newsletter-form. Ce module ajoute uniquement le wrapper section et la colonne image optionnelle.', '180c' ),
		'preview'  => '<section class="home-module home-module--newsletter_form">'
			. '<div class="container-180c">'
			. '<div class="newsletter-180c newsletter-180c--with-image" style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;align-items:center;">'
			. '<div class="newsletter-180c__media">'
			. '<span class="ds-ph" aria-hidden="true" style="display:block;width:100%;min-height:240px;border-radius:var(--radius-lg);"></span>'
			. '</div>'
			. '<div class="newsletter-180c__content">'
			. '<h2 class="newsletter-180c__title">La Gazette dans votre boîte</h2>'
			. '<p class="newsletter-180c__description">Recevez chaque semaine une sélection de recettes et les coulisses de la rédaction.</p>'
			. '<form class="newsletter-form newsletter-180c__form" action="#" method="post" data-form="newsletter" data-newsletter-list="free" novalidate>'
			. '<div class="newsletter-form__group">'
			. '<label for="ds-nl-email" class="screen-reader-text">Votre adresse e-mail</label>'
			. '<input type="email" id="ds-nl-email" name="email" class="newsletter-form__input" autocomplete="email" required placeholder="nom@exemple.fr" aria-required="true">'
			. '<button type="submit" class="newsletter-form__submit">S\'inscrire</button>'
			. '</div>'
			. '<p class="newsletter-form__consent">En vous inscrivant, vous recevez un e-mail de confirmation (double opt-in). Désabonnement possible à tout moment.</p>'
			. '</form>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 13. Séparateur ----
_180c_ds_component(
	array(
		'name'    => __( 'Séparateur', '180c' ),
		'bem'     => '.home-module.home-module--separator + .module-separator',
		'file'    => 'parts/modules/separator.php + src/css/components/home/separator.css',
		'mode'    => 'snapshot',
		'note'    => __( 'Règle horizontale fine (clamp 6-12rem) centrée. Rendu minimal : section aria-hidden + div role=separator. Le rythme vertical inter-module est géré globalement par .site-main > * + * (main.css). Ce module ne porte aucun sous-champ ACF.', '180c' ),
		'preview' => '<section class="home-module home-module--separator" aria-hidden="true">'
			. '<div class="module-separator" role="separator" aria-hidden="true" style="width:clamp(6rem,30vw,12rem);height:1px;margin-inline:auto;background-color:var(--rule);"></div>'
			. '</section>',
	)
);

// ---- 14. Bannière d'abonnement ----
_180c_ds_component(
	array(
		'name'     => __( 'Bannière d\'abonnement', '180c' ),
		'bem'      => '.home-module.carnet.carnet--cta',
		'file'     => 'parts/modules/subscription_banner.php + src/css/components/home/carnet.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'__cta-inner',
			'__title',
			'__cta-text',
			'__actions',
			'__subscribe',
			'__login',
		),
		'note'     => __( 'Break-out pleine largeur (100vw), fond accent jaune. Le module ne porte aucun style propre : il réutilise la surface .carnet--cta (carnet.css). Masquée pour les abonnés actifs (hide_for_subscribers ACF) ; pour un connecté sans abonnement actif, titre/sous-titre/CTA sont remplacés par l\'état de son abonnement (pending / on_hold / ended). Lien secondaire rendu seulement si libellé ET URL, et seulement pour un visiteur déconnecté. URL cible : filtre 180c/subscription_url.', '180c' ),
		'preview'  => '<section class="home-module carnet carnet--cta" aria-labelledby="ds-subbanner-title">'
			. '<div class="container-180c">'
			. '<div class="carnet__cta-inner">'
			. '<h2 id="ds-subbanner-title" class="carnet__title">Accédez à toutes les recettes de 180°C</h2>'
			. '<p class="carnet__cta-text">Plus de 2 000 recettes, nouvelles chaque semaine, sur tous vos appareils.</p>'
			. '<div class="carnet__actions">'
			. '<a class="btn btn--primary carnet__subscribe" href="/abonnement/">Je m\'abonne</a>'
			. '<a class="carnet__login" href="/mon-compte/">Se connecter</a>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 15. Bannière « Offrir un abonnement » ----
_180c_ds_component(
	array(
		'name'     => __( 'Bannière « Offrir un abonnement »', '180c' ),
		'bem'      => '.home-module.carnet.carnet--cta.carnet--gift',
		'file'     => 'parts/modules/gift_banner.php + src/css/components/home/carnet.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'--gift',
		),
		'note'     => __( 'Jumeau visuel exact de la bannière d\'abonnement : même surface .carnet--cta, aucun style propre. Le modifier --gift n\'a volontairement aucune déclaration CSS (point d\'accroche QA). Visibilité inverse et non éditorialisable : rendu UNIQUEMENT pour un utilisateur connecté ET abonné actif. Sous-champs ACF identiques à la bannière d\'abonnement, moins hide_for_subscribers. URL cible par défaut : filtre 180c/mc/gift_url (/offrir-un-abonnement/).', '180c' ),
		'preview'  => '<section class="home-module carnet carnet--cta carnet--gift" aria-labelledby="ds-giftbanner-title">'
			. '<div class="container-180c">'
			. '<div class="carnet__cta-inner">'
			. '<h2 id="ds-giftbanner-title" class="carnet__title">Offrez 180°C à un gourmand</h2>'
			. '<p class="carnet__cta-text">Douze mois de recettes, sans reconduction, activés à la date de votre choix.</p>'
			. '<div class="carnet__actions">'
			. '<a class="btn btn--primary carnet__subscribe" href="/offrir-un-abonnement/">Offrir un abonnement</a>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</section>',
	)
);
