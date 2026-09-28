<?php
/**
 * Design System — lot « WooCommerce ».
 *
 * Overrides WooCommerce : grille produits, prix, add-to-cart, quantité,
 * galerie, fiche produit, related/up-sells, breadcrumb, panier, checkout,
 * page de confirmation et dashboard Mon Compte.
 *
 * Tous les composants sont en mode « snapshot » : leur rendu réel requiert
 * un contexte WooCommerce (produit, panier, commande, utilisateur connecté).
 * Les aperçus reproduisent le markup BEM fidèle avec des placeholders neutres.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ---- 1. Grille produits (loop) ----
_180c_ds_component(
	array(
		'name'     => __( 'Grille produits (loop)', '180c' ),
		'bem'      => 'ul.products.products-grid',
		'file'     => 'woocommerce/loop/loop-start.php · src/css/components/woo.css (.products-grid)',
		'mode'     => 'snapshot',
		'variants' => array( '.columns-2', '.columns-3', '.columns-4', 'li.product' ),
		'note'     => __( 'Surcharge loop-start.php : <ul class="products products-grid columns-{n}">. Grille CSS responsive (2 col mobile, 3-4 col ≥ 768px). Chaque <li class="product"> reçoit la carte produit via le hook loop_content. Classe .card-180c--product appliquée par le thème.', '180c' ),
		'preview'  => '<ul class="products products-grid columns-3" style="list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(3,1fr);gap:var(--spacing-4,1rem);">'
			. '<li class="product">'
			. '<div class="card-180c card-180c--product">'
			. '<div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card-180c__body">'
			. '<h3 class="card-180c__title">Revue 180°C n°34</h3>'
			. '<span class="card-180c__price" style="color:var(--color-accent);">18,00 €</span>'
			. '</div></div></li>'
			. '<li class="product">'
			. '<div class="card-180c card-180c--product">'
			. '<div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card-180c__body">'
			. '<h3 class="card-180c__title">Les Cahiers de Delphine n°12</h3>'
			. '<span class="card-180c__price" style="color:var(--color-accent);">14,00 €</span>'
			. '</div></div></li>'
			. '<li class="product">'
			. '<div class="card-180c card-180c--product">'
			. '<div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card-180c__body">'
			. '<h3 class="card-180c__title">Grand Cahier Bretagne</h3>'
			. '<span class="card-180c__price" style="color:var(--color-accent);">24,00 €</span>'
			. '</div></div></li>'
			. '</ul>',
	)
);

// ---- 2. Prix (loop) ----
_180c_ds_component(
	array(
		'name'     => __( 'Prix — loop', '180c' ),
		'bem'      => 'span.price.product-price',
		'file'     => 'woocommerce/loop/price.php · src/css/components/woo.css (.product-price)',
		'mode'     => 'snapshot',
		'variants' => array( '.price (normal)', '.price ins + del (solde)' ),
		'note'     => __( 'Surcharge loop/price.php : <span class="price product-price">. .product-price utilise font-display, font-weight bold, color accent. En cas de solde, WC injecte <del> (ancien prix barré) et <ins> (nouveau prix).', '180c' ),
		'preview'  => '<div class="ds-stack ds-stack--sm">'
			. '<span class="price product-price" style="font-family:var(--font-display);font-weight:700;color:var(--color-accent);">18,00 €</span>'
			. '<span class="price product-price" style="font-family:var(--font-display);font-weight:700;color:var(--color-accent);">'
			. '<del style="color:var(--color-fg-muted);font-weight:400;text-decoration:line-through;margin-right:.25em;">22,00 €</del>'
			. '<ins style="text-decoration:none;">16,00 €</ins>'
			. '</span>'
			. '</div>',
	)
);

// ---- 3. Add-to-cart (loop) ----
_180c_ds_component(
	array(
		'name'     => __( 'Bouton add-to-cart — loop', '180c' ),
		'bem'      => 'a.button.add_to_cart_button',
		'file'     => 'woocommerce/loop/add-to-cart.php · src/css/components/woo.css (overrides .button.alt)',
		'mode'     => 'snapshot',
		'variants' => array( '.button.btn.btn--primary', '.add_to_cart_button', '.ajax_add_to_cart' ),
		'note'     => __( "Surcharge loop/add-to-cart.php. Le bouton reçoit les classes WC de base (button add_to_cart_button) + btn btn--primary du DS. Le reset CSS hors @layer aligne .button.alt sur l'accent 180°C (cf woo.css, bas de fichier).", '180c' ),
		'preview'  => '<div class="ds-row">'
			. '<a href="#" class="button add_to_cart_button ajax_add_to_cart btn btn--primary" data-quantity="1">Ajouter au panier</a>'
			. '<a href="#" class="button add_to_cart_button btn btn--primary" data-quantity="1" aria-busy="true" style="opacity:.6;">Ajout en cours…</a>'
			. '</div>',
	)
);

// ---- 4. Quantité (stepper) ----
_180c_ds_component(
	array(
		'name'     => __( 'Stepper quantité', '180c' ),
		'bem'      => '.quantity.qty-input',
		'file'     => 'woocommerce/global/quantity-input.php · src/css/components/woo.css (.qty-input)',
		'mode'     => 'snapshot',
		'variants' => array( '.qty-input__btn--minus', '.qty-input__field', '.qty-input__btn--plus' ),
		'note'     => __( 'Surcharge quantity-input.php : stepper − / champ / + en ligne. .qty-input est un flex row avec bordure commune. Boutons min-height 44px (a11y touch). Champ centré 56px, sans spinner natif. Label screen-reader-text uniquement.', '180c' ),
		'preview'  => '<div class="quantity qty-input" style="display:inline-flex;align-items:center;border:1px solid var(--color-border);border-radius:var(--radius-md);overflow:hidden;background:var(--color-bg-elevated);">'
			. '<button type="button" class="qty-input__btn qty-input__btn--minus" aria-label="Diminuer la quantité" style="display:flex;align-items:center;justify-content:center;min-width:36px;min-height:44px;padding:0;background:transparent;border:none;color:var(--color-fg);font-size:18px;cursor:pointer;">'
			. '<span aria-hidden="true">−</span>'
			. '</button>'
			. '<input type="number" class="qty-input__field qty" value="1" min="1" aria-label="Quantité du produit" style="width:56px;min-height:44px;text-align:center;border:none;border-left:1px solid var(--color-border);border-right:1px solid var(--color-border);background:var(--color-bg);color:var(--color-fg);">'
			. '<button type="button" class="qty-input__btn qty-input__btn--plus" aria-label="Augmenter la quantité" style="display:flex;align-items:center;justify-content:center;min-width:36px;min-height:44px;padding:0;background:transparent;border:none;color:var(--color-fg);font-size:18px;cursor:pointer;">'
			. '<span aria-hidden="true">+</span>'
			. '</button>'
			. '</div>',
	)
);

// ---- 5. Prix — fiche produit (single) ----
_180c_ds_component(
	array(
		'name'     => __( 'Prix — fiche produit', '180c' ),
		'bem'      => 'p.price.product__price',
		'file'     => 'woocommerce/single-product/price.php · src/css/components/woo.css (.product-page .price)',
		'mode'     => 'snapshot',
		'variants' => array( '.price', '.product__price', 'ins/del (solde)' ),
		'note'     => __( "Surcharge single-product/price.php : <p class=\"price product__price\">. Dans la colonne d'achat, .product-page .summary .price reçoit font-size 2xl, font-weight bold, color accent. Même structure ins/del que le loop.", '180c' ),
		'preview'  => '<div class="ds-stack ds-stack--sm">'
			. '<p class="price product__price" style="font-family:var(--font-display);font-size:var(--font-size-2xl,1.5rem);font-weight:700;color:var(--color-accent);margin:0;">18,00 €</p>'
			. '<p class="price product__price" style="font-family:var(--font-display);font-size:var(--font-size-2xl,1.5rem);font-weight:700;color:var(--color-accent);margin:0;">'
			. '<del style="color:var(--color-fg-muted);font-size:1rem;font-weight:400;text-decoration:line-through;margin-right:.4em;">22,00 €</del>'
			. '<ins style="text-decoration:none;">16,00 €</ins>'
			. '</p>'
			. '</div>',
	)
);

// ---- 6. Add-to-cart simple — fiche produit ----
_180c_ds_component(
	array(
		'name'     => __( 'Add-to-cart simple — fiche produit', '180c' ),
		'bem'      => 'form.cart · .product__buy-row · button.single_add_to_cart_button',
		'file'     => 'woocommerce/single-product/add-to-cart/simple.php · src/css/components/woo.css (.product__buy-row)',
		'mode'     => 'snapshot',
		'variants' => array( '.btn.btn--primary', '.product__add-to-cart', '.product__buy-row' ),
		'note'     => __( "Surcharge simple.php. .product__buy-row est un flex-wrap row alignant le stepper quantité + le CTA. Le bouton reçoit les classes single_add_to_cart_button btn btn--primary product__add-to-cart. Un bloc Express Checkout (Apple/Google Pay via Stripe) peut s'insérer avant le formulaire via woocommerce_before_add_to_cart_form (prio 5 sur checkout, pas systématique sur fiche produit).", '180c' ),
		'preview'  => '<form class="cart" method="post" style="max-width:420px;">'
			. '<div class="product__buy-row" style="display:flex;flex-wrap:wrap;align-items:stretch;gap:var(--spacing-3,.75rem);margin-block:var(--spacing-4,1rem);">'
			. '<div class="quantity qty-input" style="display:inline-flex;align-items:center;border:1px solid var(--color-border);border-radius:var(--radius-md);overflow:hidden;background:var(--color-bg-elevated);">'
			. '<button type="button" class="qty-input__btn qty-input__btn--minus" aria-label="Diminuer la quantité" style="min-width:36px;min-height:44px;background:transparent;border:none;color:var(--color-fg);font-size:18px;cursor:pointer;"><span aria-hidden="true">−</span></button>'
			. '<input type="number" class="qty-input__field qty" value="1" min="1" aria-label="Quantité du produit" style="width:56px;min-height:44px;text-align:center;border:none;border-left:1px solid var(--color-border);border-right:1px solid var(--color-border);background:var(--color-bg);color:var(--color-fg);">'
			. '<button type="button" class="qty-input__btn qty-input__btn--plus" aria-label="Augmenter la quantité" style="min-width:36px;min-height:44px;background:transparent;border:none;color:var(--color-fg);font-size:18px;cursor:pointer;"><span aria-hidden="true">+</span></button>'
			. '</div>'
			. '<button type="submit" name="add-to-cart" class="single_add_to_cart_button btn btn--primary product__add-to-cart" style="flex:1 1 auto;min-height:44px;">Ajouter au panier</button>'
			. '</div>'
			. '</form>',
	)
);

// ---- 7. Add-to-cart externe (external) ----
_180c_ds_component(
	array(
		'name'     => __( 'Add-to-cart externe', '180c' ),
		'bem'      => '.product__external-cta · .product__external-hint · .product__status',
		'file'     => 'inc/woo/single-product.php (_180c_product_external_cta) · woocommerce/single-product/add-to-cart/external.php · woocommerce/single-product/price.php · src/css/components/woo.css',
		'mode'     => 'snapshot',
		'variants' => array( 'a.product__external-cta + .product__external-hint (URL marchande)', 'button.product__external-cta.is-disabled (URL vide ou auto-lien)', '.product__status (badge, dans les deux cas)' ),
		'note'     => __( "Produits vendus en librairie. Source unique : _180c_product_external_cta(). L'URL n'est traitée comme marchande que si son hôte diffère de celui du site (les treize produits externes portent aujourd'hui leur propre permalien dans _product_url) → sinon bouton désactivé, sans note. Le lien porte target=\"_blank\" rel=\"noopener\" (pas de nofollow), un « (nouvelle fenêtre) » en .sr-only, une icône aria-hidden et un aria-describedby vers la note qui nomme le domaine. Le badge .product__status est rendu par price.php pour tout produit externe, texte fixe — jamais le champ WooCommerce « Texte du bouton ».", '180c' ),
		'preview'  => '<div class="ds-stack ds-stack--sm">'
			. '<p class="product__status" style="display:inline-flex;align-items:center;gap:.5rem;margin:0;padding:.5rem 1rem;border:1px solid var(--color-border);border-radius:999px;background:var(--color-bg-elevated);color:var(--color-fg-muted);font-family:var(--font-display);font-size:var(--font-size-sm,.875rem);font-weight:600;">'
			. 'Disponible uniquement en librairie'
			. '</p>'
			. '<div>'
			. '<a href="#" class="btn btn--primary product__external-cta" target="_blank" rel="noopener" style="min-height:44px;display:inline-flex;align-items:center;">Trouver en librairie'
			. '<span class="sr-only"> (nouvelle fen&#234;tre)</span>'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 3h6v6"/><path d="M10 14L21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>'
			. '</a>'
			. '<p class="product__external-hint" style="margin:.5rem 0 0;color:var(--color-fg-muted);font-size:var(--font-size-xs,.8125rem);">Vente en ligne indisponible sur 180c.fr &#8212; commande via placedeslibraires.fr</p>'
			. '</div>'
			. '<button type="button" disabled aria-disabled="true" class="btn btn--primary product__external-cta is-disabled" style="min-height:44px;">Disponible uniquement en librairie</button>'
			. '</div>',
	)
);

// ---- 8. Galerie produit ----
_180c_ds_component(
	array(
		'name'     => __( 'Galerie produit', '180c' ),
		'bem'      => '.woocommerce-product-gallery.product-page__gallery',
		'file'     => 'woocommerce/single-product/product-image.php · src/css/components/woo.css (.product-page__gallery)',
		'mode'     => 'snapshot',
		'variants' => array( '--with-images', '--without-images', '.woocommerce-product-gallery__wrapper' ),
		'note'     => __( 'Surcharge product-image.php. Règle client : aucun crop ni overlay — galerie WC native conservée intégralement (wc_get_gallery_image_html). Classe theme product-page__gallery ajoutée via filtre woocommerce_single_product_image_gallery_classes. Image principale : loading="eager" fetchpriority="high" sizes optimisés (LCP candidate). Miniatures via woocommerce_product_thumbnails.', '180c' ),
		'preview'  => '<div class="woocommerce-product-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-4 images product-page__gallery" data-columns="4" style="flex:0 0 45%;max-width:45%;">'
			. '<div class="woocommerce-product-gallery__wrapper">'
			. '<div class="woocommerce-product-gallery__image">'
			. '<span class="ds-ph ds-ph--wide" aria-hidden="true" style="display:block;aspect-ratio:3/4;"></span>'
			. '</div>'
			. '</div>'
			. '</div>',
	)
);

// ---- 9. Fiche produit (single product wrapper) ----
_180c_ds_component(
	array(
		'name'     => __( 'Fiche produit — wrapper', '180c' ),
		'bem'      => 'div.product · .product__top · .summary.entry-summary',
		'file'     => 'woocommerce/content-single-product.php · src/css/components/woo.css (.product-page)',
		'mode'     => 'snapshot',
		'variants' => array( '.product-page .product', '.product__top (2 col ≥ md)', '.summary (sticky ≥ md)', '.product__description', '.product__external' ),
		'note'     => __( "Surcharge content-single-product.php. Layout : galerie + colonne d'achat dans .product__top (2 col flex ≥ 768px, colonne summary sticky). Description longue dans .product__description (max-width 56rem). Cross-promo, upsells et related rendus en sections empilées. Onglets WC retirés (inc/woo/single-product.php).", '180c' ),
		'preview'  => '<div class="product-page" style="max-width:900px;">'
			. '<div id="product-demo" class="product type-product">'
			. '<div class="product__top" style="display:flex;flex-direction:row;align-items:flex-start;gap:var(--spacing-8,2rem);">'
			. '<div class="woocommerce-product-gallery product-page__gallery" style="flex:0 0 45%;">'
			. '<span class="ds-ph ds-ph--wide" aria-hidden="true" style="display:block;aspect-ratio:3/4;"></span>'
			. '</div>'
			. '<div class="summary entry-summary" style="flex:1 1 0;min-width:0;">'
			. '<h1 class="product_title entry-title" style="font-family:var(--font-display);font-size:var(--font-size-2xl);margin:0 0 .5rem;">Revue 180°C n°34</h1>'
			. '<p class="price product__price" style="font-family:var(--font-display);font-size:var(--font-size-2xl,1.5rem);font-weight:700;color:var(--color-accent);margin:.5rem 0;">18,00 €</p>'
			. '<div class="product__buy-row" style="display:flex;flex-wrap:wrap;align-items:stretch;gap:.75rem;margin-block:1rem;">'
			. '<button type="button" class="single_add_to_cart_button btn btn--primary product__add-to-cart" style="flex:1 1 auto;min-height:44px;">Ajouter au panier</button>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</div>',
	)
);

// ---- 10. Produits similaires (related) ----
_180c_ds_component(
	array(
		'name'     => __( 'Produits similaires (related)', '180c' ),
		'bem'      => 'section.related.products.product-rail-section · .product-rail',
		'file'     => 'woocommerce/single-product/related.php · src/css/components/woo.css (.product-rail)',
		'mode'     => 'snapshot',
		'variants' => array( '.product-rail__title', '.product-rail (scroll snap mobile / grille 4 col ≥ 768px)', 'card-180c--product --sm' ),
		'note'     => __( 'Surcharge related.php. Rendu en rail horizontal via _180c_block_render_product_card(). Cap configurable (défaut 4) par woocommerce_output_related_products_args. Scroll snap mobile, grille 4 col desktop.', '180c' ),
		'preview'  => '<section class="related products product-rail-section" aria-label="Produits similaires">'
			. '<h2 class="product-rail__title" style="font-family:var(--font-display);font-size:var(--font-size-2xl);font-weight:600;margin-bottom:var(--spacing-6,1.5rem);">Vous aimerez aussi</h2>'
			. '<div class="product-rail" style="display:grid;grid-auto-flow:column;grid-auto-columns:minmax(40%,1fr);gap:var(--spacing-4,1rem);overflow-x:auto;">'
			. '<div class="card-180c card-180c--product card-180c--sm"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span><div style="padding:.75rem;font-family:var(--font-display);">Revue 180°C n°33 — <span style="color:var(--color-accent);">18,00 €</span></div></div>'
			. '<div class="card-180c card-180c--product card-180c--sm"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span><div style="padding:.75rem;font-family:var(--font-display);">Cahier n°11 — <span style="color:var(--color-accent);">14,00 €</span></div></div>'
			. '<div class="card-180c card-180c--product card-180c--sm"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span><div style="padding:.75rem;font-family:var(--font-display);">Grand Cahier Alsace — <span style="color:var(--color-accent);">24,00 €</span></div></div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 11. Ventes incitatives (up-sells) ----
_180c_ds_component(
	array(
		'name'     => __( 'Ventes incitatives (up-sells)', '180c' ),
		'bem'      => 'section.up-sells.upsells.products.product-rail-section · .product-rail',
		'file'     => 'woocommerce/single-product/up-sells.php · src/css/components/woo.css (.product-rail)',
		'mode'     => 'snapshot',
		'variants' => array( '.product-rail__title', '.product-rail', 'card-180c--product --sm' ),
		'note'     => __( 'Surcharge up-sells.php. Même structure rail que related. Cappé à 8 produits par woocommerce_upsell_display_args (jamais -1). Titre « Complétez votre collection ».', '180c' ),
		'preview'  => '<section class="up-sells upsells products product-rail-section" aria-label="Compléments suggérés">'
			. '<h2 class="product-rail__title" style="font-family:var(--font-display);font-size:var(--font-size-2xl);font-weight:600;margin-bottom:var(--spacing-6,1.5rem);">Complétez votre collection</h2>'
			. '<div class="product-rail" style="display:grid;grid-auto-flow:column;grid-auto-columns:minmax(40%,1fr);gap:var(--spacing-4,1rem);overflow-x:auto;">'
			. '<div class="card-180c card-180c--product card-180c--sm"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span><div style="padding:.75rem;font-family:var(--font-display);">Revue 180°C n°35 — <span style="color:var(--color-accent);">18,00 €</span></div></div>'
			. '<div class="card-180c card-180c--product card-180c--sm"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span><div style="padding:.75rem;font-family:var(--font-display);">Abonnement recettes — <span style="color:var(--color-accent);">5,90 €/mois</span></div></div>'
			. '</div>'
			. '</section>',
	)
);

// ---- 12. Breadcrumb WooCommerce ----
_180c_ds_component(
	array(
		'name'     => __( 'Breadcrumb WooCommerce', '180c' ),
		'bem'      => '.breadcrumb · .breadcrumb__item · .breadcrumb__sep',
		'file'     => 'woocommerce/global/breadcrumb.php → _180c_breadcrumb_markup() (inc/helpers.php)',
		'mode'     => 'snapshot',
		'variants' => array( '.breadcrumb__item (lien)', '.breadcrumb__item (courant, aria-current="page")', '.breadcrumb__sep (›)' ),
		'note'     => __( 'Surcharge breadcrumb.php : convertit le fil WC_Breadcrumb vers le composant .breadcrumb du thème via _180c_breadcrumb_markup(). Séparateur chevron (›), dernier item non cliquable avec aria-current="page". Rendu global via _180c_render_breadcrumb() dans footer.php, pas en tête de fiche.', '180c' ),
		'preview'  => '<nav class="breadcrumb" aria-label="Fil d\'Ariane">'
			. '<ol style="list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;align-items:center;gap:.25rem;">'
			. '<li class="breadcrumb__item"><a href="#" style="color:var(--color-fg-muted);text-decoration:none;font-size:var(--font-size-sm);">Accueil</a></li>'
			. '<li class="breadcrumb__sep" aria-hidden="true" style="color:var(--color-fg-muted);font-size:var(--font-size-sm);">›</li>'
			. '<li class="breadcrumb__item"><a href="#" style="color:var(--color-fg-muted);text-decoration:none;font-size:var(--font-size-sm);">Boutique</a></li>'
			. '<li class="breadcrumb__sep" aria-hidden="true" style="color:var(--color-fg-muted);font-size:var(--font-size-sm);">›</li>'
			. '<li class="breadcrumb__item" aria-current="page" style="color:var(--color-fg);font-size:var(--font-size-sm);">Revue 180°C n°34</li>'
			. '</ol>'
			. '</nav>',
	)
);

// ---- 13. Panier ----
_180c_ds_component(
	array(
		'name'     => __( 'Panier', '180c' ),
		'bem'      => 'form.woocommerce-cart-form.cart-table__form · table.shop_table.cart.cart-table',
		'file'     => 'woocommerce/cart/cart.php · src/css/components/woo.css (.cart-table)',
		'mode'     => 'snapshot',
		'variants' => array( '.cart-table__head', '.cart-table__row', '.cart-table__remove', '.cart-table__coupon', '.cart-table__update', '.cart-collaterals' ),
		'note'     => __( 'Surcharge cart.php. Table responsive (entête masquée mobile, lignes en grid 3 col). Classes design system : .cart-table sur le tableau, .cart-table__row sur les lignes, .cart-table__remove (.btn ghost discret). Code promo + mise à jour en bas (.cart-table__actions). Totaux dans .cart-collaterals (hook WC). body.woocommerce-cart élargit .entry-content à 75rem (hors @layer).', '180c' ),
		'preview'  => '<form class="woocommerce-cart-form cart-table__form" method="post">'
			. '<table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents cart-table" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:var(--font-size-sm);">'
			. '<thead class="cart-table__head"><tr>'
			. '<th class="product-remove"><span class="screen-reader-text">Supprimer</span></th>'
			. '<th class="product-thumbnail"><span class="screen-reader-text">Image</span></th>'
			. '<th scope="col" class="product-name" style="text-align:left;padding:.5rem .75rem;font-family:var(--font-display);font-size:var(--font-size-xs);color:var(--color-fg-muted);border-bottom:1px solid var(--color-border);">Produit</th>'
			. '<th scope="col" class="product-price" style="text-align:left;padding:.5rem .75rem;font-family:var(--font-display);font-size:var(--font-size-xs);color:var(--color-fg-muted);border-bottom:1px solid var(--color-border);">Prix</th>'
			. '<th scope="col" class="product-quantity" style="text-align:left;padding:.5rem .75rem;font-family:var(--font-display);font-size:var(--font-size-xs);color:var(--color-fg-muted);border-bottom:1px solid var(--color-border);">Quantité</th>'
			. '<th scope="col" class="product-subtotal" style="text-align:left;padding:.5rem .75rem;font-family:var(--font-display);font-size:var(--font-size-xs);color:var(--color-fg-muted);border-bottom:1px solid var(--color-border);">Sous-total</th>'
			. '</tr></thead>'
			. '<tbody>'
			. '<tr class="woocommerce-cart-form__cart-item cart-table__row cart_item">'
			. '<td class="product-remove" style="padding:.75rem;border-bottom:1px solid var(--color-border);"><a role="button" href="#" class="remove cart-table__remove" aria-label="Supprimer Revue 180°C n°34 du panier" style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:var(--radius-sm);color:var(--color-fg-muted);text-decoration:none;font-size:20px;">&times;</a></td>'
			. '<td class="product-thumbnail" style="padding:.75rem;border-bottom:1px solid var(--color-border);"><span class="ds-ph" aria-hidden="true" style="display:block;width:60px;height:80px;"></span></td>'
			. '<td class="product-name cart-table__name" style="padding:.75rem;border-bottom:1px solid var(--color-border);" data-title="Produit"><a href="#" style="font-family:var(--font-display);font-weight:500;color:var(--color-fg);text-decoration:none;">Revue 180°C n°34</a></td>'
			. '<td class="product-price cart-table__price" style="padding:.75rem;border-bottom:1px solid var(--color-border);" data-title="Prix">18,00 €</td>'
			. '<td class="product-quantity cart-table__quantity" style="padding:.75rem;border-bottom:1px solid var(--color-border);" data-title="Quantité"><div style="display:inline-flex;align-items:center;border:1px solid var(--color-border);border-radius:var(--radius-md);overflow:hidden;"><button type="button" aria-label="Diminuer" style="min-width:32px;min-height:40px;background:transparent;border:none;cursor:pointer;">−</button><input type="number" value="1" style="width:48px;min-height:40px;text-align:center;border:none;border-left:1px solid var(--color-border);border-right:1px solid var(--color-border);"><button type="button" aria-label="Augmenter" style="min-width:32px;min-height:40px;background:transparent;border:none;cursor:pointer;">+</button></div></td>'
			. '<td class="product-subtotal cart-table__subtotal" style="padding:.75rem;border-bottom:1px solid var(--color-border);" data-title="Sous-total"><strong>18,00 €</strong></td>'
			. '</tr>'
			. '<tr class="cart-table__actions-row"><td colspan="6" class="actions cart-table__actions" style="padding:.75rem;">'
			. '<div class="coupon cart-table__coupon" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">'
			. '<input type="text" name="coupon_code" class="input input-text" placeholder="Code promo" style="flex:1 1 16rem;max-width:24rem;padding:.5rem .75rem;border:1px solid var(--color-border);border-radius:var(--radius-md);">'
			. '<button type="submit" name="apply_coupon" class="btn btn--secondary btn--sm">Appliquer</button>'
			. '</div>'
			. '<button type="submit" name="update_cart" class="btn btn--secondary btn--sm cart-table__update" style="margin-top:.5rem;">Mettre à jour</button>'
			. '</td></tr>'
			. '</tbody>'
			. '</table>'
			. '</form>',
	)
);

// ---- 14. Checkout ----
_180c_ds_component(
	array(
		'name'     => __( 'Checkout', '180c' ),
		'bem'      => '.checkout-page · form.checkout.woocommerce-checkout · .checkout__main · .checkout__summary · #order_review',
		'file'     => 'woocommerce/checkout/form-checkout.php · src/css/components/checkout.css',
		'mode'     => 'snapshot',
		'variants' => array( '.checkout-page', '.checkout (grille 1→2 col ≥ 1024px)', '.checkout__main', '.checkout__fields', '.checkout__section', '.checkout__summary (<details> repliable mobile, sticky desktop)', '#customer_details', '#order_review.woocommerce-checkout-review-order' ),
		'note'     => __( 'Surcharge form-checkout.php. Grille 2 colonnes ≥ 1024px (2fr + 340px min). Express Checkout Apple/Google Pay (Stripe) injecté via woocommerce_checkout_before_customer_details prio 5. Le récap <details class="checkout__summary"> est repliable mobile / sticky desktop. Tous les hooks WC natifs préservés (#order_review doit rester dans le <form>). body.woocommerce-checkout élargit .entry-content à 75rem.', '180c' ),
		'preview'  => '<div class="checkout-page" style="max-width:75rem;margin-inline:auto;">'
			. '<form name="checkout" method="post" class="checkout woocommerce-checkout" aria-label="Finaliser la commande" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:var(--spacing-8,2rem);align-items:start;">'
			. '<div class="checkout__main" style="min-width:0;">'
			. '<div id="customer_details" class="checkout__fields">'
			. '<section class="checkout__section" aria-labelledby="ds-checkout-billing">'
			. '<h2 id="ds-checkout-billing" class="checkout__section-title" style="font-size:var(--font-size-xl);margin-bottom:var(--spacing-4,1rem);">Vos informations</h2>'
			. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">'
			. '<div><label style="display:block;font-size:var(--font-size-sm);margin-bottom:.25rem;">Prénom <abbr title="obligatoire">*</abbr></label><input type="text" class="input" placeholder="Marie" style="width:100%;padding:.5rem .75rem;border:1px solid var(--color-border);border-radius:var(--radius-md);"></div>'
			. '<div><label style="display:block;font-size:var(--font-size-sm);margin-bottom:.25rem;">Nom <abbr title="obligatoire">*</abbr></label><input type="text" class="input" placeholder="Dupont" style="width:100%;padding:.5rem .75rem;border:1px solid var(--color-border);border-radius:var(--radius-md);"></div>'
			. '<div style="grid-column:1/-1;"><label style="display:block;font-size:var(--font-size-sm);margin-bottom:.25rem;">Adresse e-mail <abbr title="obligatoire">*</abbr></label><input type="email" class="input" placeholder="marie@example.com" style="width:100%;padding:.5rem .75rem;border:1px solid var(--color-border);border-radius:var(--radius-md);"></div>'
			. '</div>'
			. '</section>'
			. '</div>'
			. '</div>'
			. '<details class="checkout__summary" open style="border:1px solid var(--color-border);border-radius:var(--radius-lg);background:var(--color-bg-elevated);padding:var(--spacing-6,1.5rem);">'
			. '<summary class="checkout__summary-toggle" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;list-style:none;">'
			. '<h2 id="order_review_heading" class="checkout__summary-title" style="font-size:var(--font-size-lg);margin:0;">Votre commande</h2>'
			. '</summary>'
			. '<div class="checkout__summary-body" style="margin-top:var(--spacing-4,1rem);">'
			. '<div id="order_review" class="woocommerce-checkout-review-order">'
			. '<p style="color:var(--color-fg-muted);font-size:var(--font-size-sm);">Revue 180°C n°34 × 1 — <strong>18,00 €</strong></p>'
			. '<p style="font-weight:700;border-top:1px solid var(--color-border);padding-top:.5rem;margin-top:.5rem;">Total : <span style="color:var(--color-accent);">18,00 €</span></p>'
			. '<button type="submit" id="place_order" class="btn btn--primary" style="width:100%;min-height:44px;margin-top:1rem;">Passer la commande</button>'
			. '</div>'
			. '</div>'
			. '</details>'
			. '</form>'
			. '</div>',
	)
);

// ---- 15. Confirmation de commande (thankyou) ----
_180c_ds_component(
	array(
		'name'     => __( 'Confirmation de commande (thankyou)', '180c' ),
		'bem'      => '.woocommerce-order.checkout-thankyou · .checkout-thankyou__header · .checkout-thankyou__recap · .checkout-thankyou__next',
		'file'     => 'woocommerce/checkout/thankyou.php · src/css/components/woo.css + checkout.css (.checkout-thankyou)',
		'mode'     => 'snapshot',
		'variants' => array( '.checkout-thankyou__icon (svg check)', '.checkout-thankyou__details (ul récap)', '.checkout-thankyou__block--subscription', '.checkout-thankyou__block--physical', '.checkout-thankyou__error (commande échouée)' ),
		'note'     => __( "Surcharge thankyou.php. Titre et sous-titre différenciés selon la nature de la commande (abonnement / physique / mixte). Récap dans .checkout-thankyou__details. CTA contextuels (.btn--primary + --secondary). En cas d'échec : .checkout-thankyou__error avec bouton Réessayer.", '180c' ),
		'preview'  => '<div class="woocommerce-order checkout-thankyou" style="max-width:600px;margin:0 auto;padding:2rem 1rem;">'
			. '<div class="checkout-thankyou__header" style="text-align:center;margin-bottom:2rem;">'
			. '<div class="checkout-thankyou__icon" aria-hidden="true" style="color:var(--color-success,#3FA66B);margin-bottom:.75rem;">'
			. '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M7.5 12l3 3 6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '</div>'
			. '<h1 class="checkout-thankyou__title" style="font-family:var(--font-display);font-size:var(--font-size-2xl);margin:0 0 .5rem;">Votre accès est actif</h1>'
			. '<p class="checkout-thankyou__subtitle" style="color:var(--color-fg-muted);margin:0;">Votre abonnement est confirmé. Bonne lecture — et bonne cuisine !</p>'
			. '</div>'
			. '<div class="checkout-thankyou__recap" style="background:var(--color-bg-elevated);border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:1rem;margin-bottom:1.5rem;">'
			. '<ul class="woocommerce-order-overview checkout-thankyou__details" style="list-style:none;margin:0;padding:0;">'
			. '<li style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:var(--font-size-sm);"><span class="checkout-thankyou__detail-label" style="color:var(--color-fg-muted);">Numéro de commande</span><strong class="checkout-thankyou__detail-value">#1042</strong></li>'
			. '<li style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:var(--font-size-sm);"><span class="checkout-thankyou__detail-label" style="color:var(--color-fg-muted);">Date</span><strong>1 juin 2026</strong></li>'
			. '<li style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:var(--font-size-sm);"><span class="checkout-thankyou__detail-label" style="color:var(--color-fg-muted);">Total</span><strong class="checkout-thankyou__total" style="color:var(--color-accent);font-family:var(--font-display);font-size:var(--font-size-lg);">5,90 €/mois</strong></li>'
			. '</ul>'
			. '</div>'
			. '<div class="checkout-thankyou__next">'
			. '<section class="checkout-thankyou__block checkout-thankyou__block--subscription" aria-labelledby="ds-thankyou-sub" style="border:1px solid var(--color-accent);border-radius:var(--radius-lg);padding:1.5rem;text-align:center;background:var(--color-bg-elevated);">'
			. '<h2 id="ds-thankyou-sub" class="checkout-thankyou__block-title" style="font-size:var(--font-size-xl);margin:0 0 .5rem;">Commencez à explorer</h2>'
			. '<p class="checkout-thankyou__block-text" style="color:var(--color-fg-muted);margin:0 0 1rem;">Toutes les recettes en ligne sont accessibles dès maintenant.</p>'
			. '<div class="checkout-thankyou__cta" style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">'
			. '<a href="#" class="btn btn--primary">Découvrez les recettes</a>'
			. '<a href="#" class="btn btn--secondary">Mon compte</a>'
			. '</div>'
			. '</section>'
			. '</div>'
			. '</div>',
	)
);

// ---- 16. Mon Compte — dashboard ----
_180c_ds_component(
	array(
		'name'     => __( 'Mon Compte — dashboard', '180c' ),
		'bem'      => '.my-account · .my-account__sidebar · .my-account__content · .my-account-card · .my-account-section',
		'file'     => 'woocommerce/myaccount/dashboard.php → parts/account/dashboard.php · src/css/components/my-account.css',
		'mode'     => 'snapshot',
		'variants' => array( '.my-account-card--profile', '.my-account-card--nav', '.my-account-card--support', '.my-account-card--logout', '.my-account__mobile-trigger', '.my-account-section (6 ancrées)', '.my-account-status', '.my-account-field', '.my-account-empty' ),
		'note'     => __( 'Surcharge myaccount/dashboard.php : redirige vers parts/account/dashboard.php (custom). Layout grille 300px sidebar + 1fr content ≥ 1024px. Sidebar sticky avec trigger accordéon mobile. 4 cards sidebar (profil / nav / support / déconnexion). 6 sections ancrées dans le content (Informations, Abonnement, Commandes, Livraison, Factures, Gérer). body.woocommerce-account élargit .entry-content à container-max.', '180c' ),
		'preview'  => '<div class="my-account" style="display:grid;grid-template-columns:260px 1fr;gap:var(--space-xl,3rem);align-items:start;max-width:960px;">'
			. '<aside class="my-account__sidebar" style="display:flex;flex-direction:column;gap:var(--space-md,1.5rem);">'
			. '<div class="my-account-card my-account-card--profile" style="background:var(--color-bg-elevated);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:var(--space-md,1.5rem);display:flex;flex-direction:column;align-items:flex-start;gap:.5rem;">'
			. '<p class="my-account-card__eyebrow" style="margin:0;font-family:var(--font-display);font-size:var(--text-sm);font-weight:500;color:var(--color-fg-muted);text-transform:uppercase;letter-spacing:.08em;">Mon Compte</p>'
			. '<span class="my-account-card__badge" style="padding:.25rem .75rem;background:var(--color-accent);border-radius:999px;font-family:var(--font-display);font-size:var(--text-xs);font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#1a1a1a;">Abonné</span>'
			. '<p class="my-account-card__name" style="margin:.25rem 0 0;font-family:var(--font-display);font-size:var(--text-lg);font-weight:600;">Marie Dupont</p>'
			. '</div>'
			. '<div class="my-account-card my-account-card--nav" style="background:var(--color-bg-elevated);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:0;">'
			. '<ul class="my-account-card__list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;">'
			. '<li><a href="#informations" class="my-account-card__link is-active" aria-current="true" style="display:block;padding:var(--space-md) var(--space-sm);border-left:3px solid var(--color-accent);font-family:var(--font-display);font-size:var(--text-lg);font-weight:600;color:var(--color-accent);text-decoration:none;">Informations</a></li>'
			. '<li><a href="#abonnement" class="my-account-card__link" style="display:block;padding:var(--space-md) var(--space-sm);border-left:3px solid transparent;border-bottom:1px solid var(--color-border);font-family:var(--font-display);font-size:var(--text-lg);font-weight:500;color:var(--color-fg);text-decoration:none;">Abonnement</a></li>'
			. '<li><a href="#commandes" class="my-account-card__link" style="display:block;padding:var(--space-md) var(--space-sm);border-left:3px solid transparent;font-family:var(--font-display);font-size:var(--text-lg);font-weight:500;color:var(--color-fg);text-decoration:none;">Commandes</a></li>'
			. '</ul>'
			. '</div>'
			. '</aside>'
			. '<main class="my-account__content" style="display:flex;flex-direction:column;gap:var(--space-md,1.5rem);min-width:0;">'
			. '<section id="informations" class="my-account-section" aria-labelledby="ds-ma-info" style="background:var(--color-bg-elevated);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:var(--space-lg,2rem);">'
			. '<header class="my-account-section__header" style="padding-bottom:var(--space-sm);margin-bottom:var(--space-md);border-bottom:1px solid var(--color-border);">'
			. '<h2 id="ds-ma-info" class="my-account-section__title" style="margin:0;font-family:var(--font-display);font-size:var(--text-2xl);font-weight:700;">Informations</h2>'
			. '</header>'
			. '<dl class="my-account-fields" style="display:flex;flex-direction:column;margin:0;">'
			. '<div class="my-account-field" style="display:grid;grid-template-columns:minmax(0,1fr) auto;column-gap:1.5rem;padding-block:var(--space-md);border-bottom:1px solid var(--color-border);">'
			. '<dt class="my-account-field__label" style="font-family:var(--font-display);font-size:var(--text-xs);font-weight:500;text-transform:uppercase;letter-spacing:.06em;color:var(--color-fg-muted);">Adresse e-mail</dt>'
			. '<dd class="my-account-field__value" style="margin:2px 0 0;font-size:var(--text-base);color:var(--color-fg);">marie.dupont@example.com</dd>'
			. '<a href="#" class="my-account-field__action" style="grid-column:2/3;grid-row:1/span 2;align-self:center;font-family:var(--font-display);font-size:var(--text-sm);font-weight:600;color:var(--color-accent);text-decoration:none;text-transform:uppercase;letter-spacing:.06em;">Modifier</a>'
			. '</div>'
			. '</dl>'
			. '</section>'
			. '</main>'
			. '</div>',
	)
);

// ---- 17. E-mails WooCommerce (hors périmètre) ----
_180c_ds_component(
	array(
		'name'    => __( 'WooCommerce e-mails', '180c' ),
		'bem'     => '—',
		'file'    => 'woocommerce/emails/*.php',
		'mode'    => 'snapshot',
		'note'    => __( 'Hors périmètre v1 — rendu pour client mail (styles inline), pas une surface web.', '180c' ),
		'preview' => '',
	)
);
