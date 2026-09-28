<?php
/**
 * Design System — lot « Cards ».
 *
 * Documente le système canonique .card-180c (article, recette, produit,
 * auteur, publication XL, collection) et le système legacy .card / .card-recipe
 * encore rendu dans parts/account/favoris.php et inc/search.php.
 *
 * Toutes les cards canoniques et XL sont en mode « snapshot » : leur rendu
 * réel nécessite un contexte post / WC_Product / user inaccessible ici.
 * Les placeholders média utilisent <span class="ds-ph"> (neutre, sans photo
 * recadrée ni overlay) conformément à la règle client.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// 1. Article card — système canonique .card-180c
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Card Article', '180c' ),
		'bem'      => '.card-180c.card-180c--article',
		'file'     => 'inc/blocks/_helpers.php → _180c_block_render_article_card()',
		'mode'     => 'snapshot',
		'variants' => array( '--sm', '--md', '--lg', '__link', '__media', '__image', '__body', '__category', '__title', '__excerpt' ),
		'note'     => __( 'Card article canonique. Trois tailles : --sm (rails denses), --md (grille standard, défaut), --lg (mise en avant). La catégorie principale est calculée par _180c_article_primary_category() avec fallback sur le premier terme. L\'extrait est tronqué à 18 mots. Pas de photo ni overlay : placeholder neutre dans le specimen.', '180c' ),
		'preview'  => '<article class="card-180c card-180c--article card-180c--md" style="max-width:320px;">'
			. '<a class="card-180c__link" href="#" aria-label="La cuisine du monde en douze recettes">'
			. '<div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card-180c__body">'
			. '<span class="card-180c__category">La Gazette</span>'
			. '<h3 class="card-180c__title">La cuisine du monde en douze recettes</h3>'
			. '<p class="card-180c__excerpt">Un tour du monde culinaire à travers les recettes emblématiques de six continents, racontées par notre rédactrice en chef.</p>'
			. '</div>'
			. '</a>'
			. '</article>',
	)
);

// ---------------------------------------------------------------------------
// 2. Recipe card — système canonique .card-180c
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Card Recette', '180c' ),
		'bem'      => '.card-180c.card-180c--recipe',
		'file'     => 'inc/blocks/_helpers.php → _180c_block_render_recipe_card()',
		'mode'     => 'snapshot',
		'variants' => array( '--sm', '--md', '--lg', '__link', '__media', '__image', '__body', '__category', '__title', '__meta', '__season' ),
		'note'     => __( 'Card recette canonique. Même structure de taille que la card article (--sm/--md/--lg). La méta affichée provient des taxonomies recipe_category (prioritaire) et recipe_season. Pas de champs total_time/difficulty (supprimés en). Le bouton favori overlay (.favorite-button) est rendu hors du <a> par _180c_render_favorite_button() — non représenté dans ce specimen.', '180c' ),
		'preview'  => '<article class="card-180c card-180c--recipe card-180c--md" style="max-width:320px;">'
			. '<a class="card-180c__link" href="#" aria-label="Tarte tatin aux figues et romarin">'
			. '<div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card-180c__body">'
			. '<span class="card-180c__category">Pâtisserie</span>'
			. '<h3 class="card-180c__title">Tarte tatin aux figues et romarin</h3>'
			. '<div class="card-180c__meta">'
			. '<span class="card-180c__season">Automne</span>'
			. '</div>'
			. '</div>'
			. '</a>'
			. '</article>',
	)
);

// ---------------------------------------------------------------------------
// 3. Product card — système canonique .card-180c
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Card Produit', '180c' ),
		'bem'      => '.card-180c.card-180c--product',
		'file'     => 'inc/blocks/_helpers.php → _180c_block_render_product_card()',
		'mode'     => 'snapshot',
		'variants' => array( '--sm', '--md', '__link', '__media', '__image', '__badge', '__body', '__title', '__price', '__cta' ),
		'note'     => __( 'Card produit WooCommerce. Variantes --sm (rail/cross-sell) et --md (grille, défaut). Le badge .card-180c__badge s\'affiche sur le média quand le produit est épuisé. Le CTA .card-180c__cta (btn btn--primary) est masqué via $args[\'show_cta\'] = false si besoin. Deux états montrés : normal et épuisé.', '180c' ),
		'preview'  => '<div class="ds-row" style="align-items:flex-start;gap:var(--space-md);">'
			. '<article class="card-180c card-180c--product card-180c--md" style="max-width:240px;">'
			. '<a class="card-180c__link" href="#" aria-label="180°C n°42 — Été 2024">'
			. '<div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card-180c__body">'
			. '<h3 class="card-180c__title">180°C n°42 — Été 2024</h3>'
			. '<div class="card-180c__price">8,90&nbsp;€</div>'
			. '</div>'
			. '</a>'
			. '<a class="btn btn--primary card-180c__cta" href="#">Ajouter au panier</a>'
			. '</article>'
			. '<article class="card-180c card-180c--product card-180c--md" style="max-width:240px;">'
			. '<a class="card-180c__link" href="#" aria-label="180°C n°38 — Épuisé">'
			. '<div class="card-180c__media">'
			. '<span class="ds-ph ds-ph--wide" aria-hidden="true"></span>'
			. '<span class="card-180c__badge">Épuisé</span>'
			. '</div>'
			. '<div class="card-180c__body">'
			. '<h3 class="card-180c__title">180°C n°38 — Hiver 2023</h3>'
			. '<div class="card-180c__price">8,90&nbsp;€</div>'
			. '</div>'
			. '</a>'
			. '<a class="btn btn--primary card-180c__cta" href="#">Ajouter au panier</a>'
			. '</article>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 4. Author card — .author-card
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Card Auteur', '180c' ),
		'bem'      => '.author-card',
		'file'     => 'inc/search.php → _180c_render_author_card()',
		'mode'     => 'snapshot',
		'variants' => array( '__link', '__media', '__photo', '__monogram', '__text', '__name', '__role' ),
		'note'     => __( 'Card auteur partagée (page de recherche + rail signatures). Deux variantes visuelles selon disponibilité de l\'avatar ACF : photo (.author-card__photo, wp_get_attachment_image) ou monogramme (.author-card__monogram, initiales). Jamais de silhouette Gravatar grise. Deux specimens : photo (placeholder avatar) et monogramme.', '180c' ),
		'preview'  => '<div class="ds-row" style="gap:var(--space-md);flex-wrap:wrap;">'
			. '<article class="author-card">'
			. '<a class="author-card__link" href="#">'
			. '<figure class="author-card__media">'
			. '<span class="ds-ph ds-ph--avatar" aria-hidden="true"></span>'
			. '</figure>'
			. '<span class="author-card__text">'
			. '<span class="author-card__name">Marie Fontaine</span>'
			. '<span class="author-card__role">Rédactrice en chef</span>'
			. '</span>'
			. '</a>'
			. '</article>'
			. '<article class="author-card">'
			. '<a class="author-card__link" href="#">'
			. '<figure class="author-card__media">'
			. '<span class="author-card__monogram" aria-hidden="true">PD</span>'
			. '</figure>'
			. '<span class="author-card__text">'
			. '<span class="author-card__name">Pierre Dubois</span>'
			. '</span>'
			. '</a>'
			. '</article>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 5. Publication XL — .publication-xl
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Publication XL', '180c' ),
		'bem'      => '.publication-xl',
		'file'     => 'inc/blocks/_helpers.php → _180c_render_publication_xl()',
		'mode'     => 'snapshot',
		'variants' => array( '__media', '__cover', '__content', '__title', '__excerpt', '__price', '__cta' ),
		'note'     => __( 'Format « dernière publication » utilisé par le module home last_publication et le bloc 180c/last-publication. Disposition côte à côte : couverture (.publication-xl__cover) + zone texte (.publication-xl__content). L\'image de couverture utilise loading="eager" + fetchpriority="high" (au-dessus du fold). Libellé CTA par défaut : « Acheter ».', '180c' ),
		'preview'  => '<div class="publication-xl" style="max-width:640px;">'
			. '<div class="publication-xl__media">'
			. '<a href="#" aria-hidden="true" tabindex="-1">'
			. '<span class="ds-ph" style="display:block;width:180px;height:240px;" aria-hidden="true"></span>'
			. '</a>'
			. '</div>'
			. '<div class="publication-xl__content">'
			. '<h2 class="publication-xl__title"><a href="#">180°C — Hors-série Automne 2024</a></h2>'
			. '<p class="publication-xl__excerpt">Un numéro entièrement consacré aux plats réconfortants de saison, avec quarante recettes inédites signées par nos auteurs.</p>'
			. '<div class="publication-xl__price">12,90&nbsp;€</div>'
			. '<a class="btn btn--primary publication-xl__cta" href="#">Acheter</a>'
			. '</div>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 6. Collection card — .card-180c.card-180c--product (variant --owned)
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Card Collection (déjà acheté)', '180c' ),
		'bem'      => '.card-180c.card-180c--product.card-180c--owned',
		'file'     => 'inc/blocks/_helpers.php → _180c_render_collection_card()',
		'mode'     => 'snapshot',
		'variants' => array( '--owned', '__link', '__media', '__badge', '__badge--owned', '__body', '__title', '__price', '__cta', '__cta--owned' ),
		'note'     => __( 'Partage le BEM .card-180c--product. La variante --owned ($already_bought = true) masque le prix, remplace le CTA (btn--ghost + .card-180c__cta--owned) et ajoute le badge .card-180c__badge--owned sur le média. Utilisé par le bloc 180c/complete-collection.', '180c' ),
		'preview'  => '<div class="ds-row" style="align-items:flex-start;gap:var(--space-md);">'
			. '<article class="card-180c card-180c--product card-180c--md" style="max-width:200px;">'
			. '<a class="card-180c__link" href="#" aria-label="180°C n°40 — Printemps 2024">'
			. '<div class="card-180c__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card-180c__body">'
			. '<h3 class="card-180c__title">180°C n°40 — Printemps 2024</h3>'
			. '<div class="card-180c__price">8,90&nbsp;€</div>'
			. '</div>'
			. '</a>'
			. '<a class="btn btn--primary card-180c__cta" href="#">Ajouter au panier</a>'
			. '</article>'
			. '<article class="card-180c card-180c--product card-180c--owned card-180c--md" style="max-width:200px;">'
			. '<a class="card-180c__link" href="#" aria-label="180°C n°39 — Hiver 2023">'
			. '<div class="card-180c__media">'
			. '<span class="ds-ph ds-ph--wide" aria-hidden="true"></span>'
			. '<span class="card-180c__badge card-180c__badge--owned" aria-label="Déjà acheté">Déjà acheté</span>'
			. '</div>'
			. '<div class="card-180c__body">'
			. '<h3 class="card-180c__title">180°C n°39 — Hiver 2023</h3>'
			. '</div>'
			. '</a>'
			. '<span class="btn btn--ghost card-180c__cta card-180c__cta--owned">Déjà acheté</span>'
			. '</article>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 7. Card (legacy) — .card
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Card (legacy)', '180c' ),
		'bem'      => '.card',
		'file'     => 'src/css/components/cards.css',
		'mode'     => 'snapshot',
		'variants' => array( '.card-article', '.card-product', '.card-recipe', '__media', '__body', '__title', '__excerpt', '__meta', '__price', '.card__price--original', '__badge', '__difficulty' ),
		'note'     => __( 'Système legacy (cards.css). Trois sous-types : .card-article (ratio 16/9), .card-recipe (4/3 + .card__difficulty), .card-product (3/4 portrait + .card__price + .card__price--original). Encore actif dans parts/account/favoris.php, inc/search.php et parts/account/dashboard.php. Candidat à consolidation vers .card-180c (hors-scope).', '180c' ),
		'legacy'   => __( 'Legacy — à migrer vers .card-180c', '180c' ),
		'preview'  => '<div class="ds-row" style="align-items:flex-start;gap:var(--space-md);flex-wrap:wrap;">'
			. '<div class="card card-article" style="max-width:280px;">'
			. '<div class="card__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card__body">'
			. '<span class="card__meta">La Gazette · 3 min</span>'
			. '<h3 class="card__title">Recette de saison : le velouté de butternut</h3>'
			. '<p class="card__excerpt">Simple, rapide et réconfortant pour les soirées d\'automne.</p>'
			. '</div>'
			. '</div>'
			. '<div class="card card-product" style="max-width:180px;">'
			. '<div class="card__media"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card__body">'
			. '<h3 class="card__title">180°C n°36</h3>'
			. '<span class="card__price">8,90&nbsp;€</span>'
			. '<span class="card__price card__price--original">12,00&nbsp;€</span>'
			. '<span class="card__badge">Promo</span>'
			. '</div>'
			. '</div>'
			. '</div>',
	)
);

// ---------------------------------------------------------------------------
// 8. Recipe card (legacy) — .card-recipe (parts/account/favoris.php)
// ---------------------------------------------------------------------------
_180c_ds_component(
	array(
		'name'     => __( 'Card Recette (legacy favoris)', '180c' ),
		'bem'      => '.card-recipe',
		'file'     => 'parts/account/favoris.php',
		'mode'     => 'snapshot',
		'variants' => array( '__link', '__thumb', '__img', '__body', '__title' ),
		'note'     => __( 'Card recette legacy rendue dans la page « Mes recettes favorites » (Mon Compte). Structure minimale : lien englobant, vignette (.card-recipe__thumb > img.card-recipe__img), corps (.card-recipe__body > h2.card-recipe__title). Un bouton .account-favoris__remove (btn--ghost btn--sm) hors du lien permet de retirer la recette des favoris via REST. Cette card doit converger vers .card-180c--recipe (hors-scope).', '180c' ),
		'legacy'   => __( 'Legacy — à migrer vers .card-180c', '180c' ),
		'preview'  => '<li class="account-favoris__item card-recipe" style="list-style:none;max-width:280px;">'
			. '<a href="#" class="card-recipe__link">'
			. '<div class="card-recipe__thumb"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></div>'
			. '<div class="card-recipe__body">'
			. '<h2 class="card-recipe__title">Gratin dauphinois à l\'ancienne</h2>'
			. '</div>'
			. '</a>'
			. '<button type="button" class="btn btn--ghost btn--sm account-favoris__remove" data-recipe-id="0" aria-label="Retirer Gratin dauphinois à l\'ancienne des favoris">Retirer</button>'
			. '</li>',
	)
);
