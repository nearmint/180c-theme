<?php
/**
 * Design System - lot "Etats & feedback".
 *
 * Etats UI : empty-state, section-header, entry-header, toast, favorite-button,
 * share-actions, paywall, search states. Apercus live via _180c_ds_capture()
 * quand le partial est pur (aucune dependance DB/ACF/Woo/user) ; snapshot HTML
 * statique sinon (justifie par contexte runtime).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ---- Empty state ----
// Pure partial (args only, no DB) - mode live.
_180c_ds_component(
	array(
		'name'     => __( 'Etat vide', '180c' ),
		'bem'      => '.empty-state',
		'file'     => 'parts/empty-state.php',
		'mode'     => 'live',
		'variants' => array( '__title', '__message', '__cta' ),
		'note'     => __( 'Affiche quand une archive/liste ne renvoie aucun resultat. Le titre est un &lt;p&gt; (preserve l\'unicite du &lt;h1&gt;). Le CTA n\'apparait que si cta_url ET cta_label sont fournis.', '180c' ),
		'preview'  => _180c_ds_capture(
			'parts/empty-state',
			array(
				'title'     => 'Aucun resultat pour cette recherche',
				'message'   => 'Essayez d\'autres mots-cles ou explorez toutes nos recettes.',
				'cta_url'   => '#',
				'cta_label' => 'Voir toutes les recettes',
			)
		),
	)
);

// ---- Section header ----
// Pure partial (args only, no DB) - mode live.
_180c_ds_component(
	array(
		'name'     => __( 'En-tete de section', '180c' ),
		'bem'      => '.section-header',
		'file'     => 'parts/section-header.php',
		'mode'     => 'live',
		'variants' => array( '__eyebrow', '__title', '__subtitle', '__description' ),
		'note'     => __( 'En-tete standard des surfaces archive/taxonomie/recherche. Porte le seul &lt;h1&gt; de la page. Les sections secondaires utilisent &lt;h2&gt;. La description accepte du HTML via wp_kses_post.', '180c' ),
		'preview'  => _180c_ds_capture(
			'parts/section-header',
			array(
				'eyebrow'     => 'Categorie',
				'title'       => 'Cuisine du monde',
				'subtitle'    => '142 recettes',
				'description' => 'Voyages culinaires a travers les saveurs et les traditions du monde entier.',
			)
		),
	)
);

// ---- Entry header ----
// Needs post context (WP_Query loop) - mode snapshot.
// Markup derived from _180c_render_entry_header() in inc/entry-header.php.
_180c_ds_component(
	array(
		'name'     => __( 'En-tete editorial', '180c' ),
		'bem'      => '.entry-header',
		'file'     => '_180c_render_entry_header() - inc/entry-header.php',
		'mode'     => 'snapshot',
		'variants' => array( '__eyebrow', '__eyebrow-link', '__eyebrow-text', '__sep', '__title', '__standfirst', '__byline' ),
		'note'     => __( 'Renderer data-agnostic partage par single.php (article) et single-recipe.php (recette). Eyebrow = liste de termes joints par "." avec ou sans URL. Snapshot : necessite un contexte post actif.', '180c' ),
		'preview'  => '<header class="entry-header">'
			. '<div class="entry-header__eyebrow">'
			. '<a class="entry-header__eyebrow-link" href="#">Recettes du monde</a>'
			. '<span class="entry-header__sep" aria-hidden="true">&middot;</span>'
			. '<span class="entry-header__eyebrow-text">15 juin 2025</span>'
			. '</div>'
			. '<h1 class="entry-header__title">Tajine d&#8217;agneau aux abricots et aux amandes</h1>'
			. '<p class="entry-header__standfirst">Une recette genereuse et parfumee inspiree des traditions marocaines, ideale pour un repas de fete en famille.</p>'
			. '<p class="entry-header__byline">Par <a href="#" rel="author">Jane Doe</a></p>'
			. '</header>',
	)
);

// ---- Toast ----
// JS-driven - no PHP template, no DB dependency.
// Three static toast variants: success, error, info.
// mode='snapshot' justified: toasts are injected/dismissed by JS at runtime.
_180c_ds_component(
	array(
		'name'     => __( 'Toast', '180c' ),
		'bem'      => '.toast',
		'file'     => 'src/css/components/toast.css (JS runtime - aucun template PHP)',
		'mode'     => 'snapshot',
		'variants' => array( '--success', '--error', '--info', '__message', '__action', '__close' ),
		'note'     => __( 'Notification ephemere ancree en bas-centre (.toast-region). Geree entierement par JS (inject/dismiss) - pas de template PHP. Snapshot HTML statique justifie. Les variantes --success/--error/--info sont des conventions semantiques portees par une data-attribute JS.', '180c' ),
		'preview'  => '<div class="ds-stack ds-stack--sm">'
			. '<div style="display:flex;flex-direction:column;gap:var(--space-2xs);">'
			. '<div class="toast" role="status" aria-live="polite" data-toast-variant="success">'
			. '<p class="toast__message">Recette ajoutee a votre carnet.</p>'
			. '<button type="button" class="toast__close" aria-label="Fermer la notification">&#215;</button>'
			. '</div>'
			. '<div class="toast" role="alert" aria-live="assertive" data-toast-variant="error">'
			. '<p class="toast__message">Une erreur est survenue. Veuillez reessayer.</p>'
			. '<button type="button" class="toast__close" aria-label="Fermer la notification">&#215;</button>'
			. '</div>'
			. '<div class="toast" role="status" aria-live="polite" data-toast-variant="info">'
			. '<p class="toast__message">Lien copie dans le presse-papier.</p>'
			. '<button type="button" class="toast__action">Annuler</button>'
			. '<button type="button" class="toast__close" aria-label="Fermer la notification">&#215;</button>'
			. '</div>'
			. '</div>'
			. '</div>',
	)
);

// ---- Favorite button ----
// Needs user auth state + recipe ID - mode snapshot.
// Markup derived from _180c_render_favorite_button() in inc/favorites.php.
// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
_180c_ds_component(
	array(
		'name'     => __( 'Bouton favori', '180c' ),
		'bem'      => '.favorite-button',
		'file'     => '_180c_render_favorite_button() - inc/favorites.php',
		'mode'     => 'snapshot',
		'variants' => array( '--card', '--single', '__icon', '__heart--outline', '__heart--filled', '__label', '__status' ),
		'note'     => __( 'Deux surfaces : --card (icone seule, sur la vignette) et --single (icone + libelle, sur la page recette). Les deux coeurs (outline/filled) sont toujours dans le DOM - la permutation est geree en CSS via aria-pressed. Snapshot : necessite un user_id + recipe_id.', '180c' ),
		'preview'  => '<div class="ds-row" style="gap:var(--space-md);align-items:flex-start;">'
			. '<div>'
			. '<p style="font-size:var(--text-xs);color:var(--muted);margin-block-end:var(--space-2xs);">Surface --card (aria-pressed="false")</p>'
			. '<button type="button"'
			. ' class="favorite-button favorite-button--card"'
			. ' data-favorite-toggle'
			. ' data-recipe-id="0"'
			. ' data-favorite-surface="card"'
			. ' data-label-add="Ajouter a mon carnet"'
			. ' data-label-remove="Retirer de mon carnet"'
			. ' aria-pressed="false"'
			. ' aria-label="Ajouter a mon carnet">'
			. '<span class="favorite-button__icon" aria-hidden="true">'
			. '<svg class="favorite-button__heart favorite-button__heart--outline"'
			. ' viewBox="0 0 24 24" width="24" height="24"'
			. ' fill="none" stroke="currentColor" stroke-width="2"'
			. ' stroke-linecap="round" stroke-linejoin="round"'
			. ' aria-hidden="true" focusable="false">'
			. '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5'
			. ' 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09'
			. 'C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5'
			. 'c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>'
			. '</svg>'
			. '<svg class="favorite-button__heart favorite-button__heart--filled"'
			. ' viewBox="0 0 24 24" width="24" height="24"'
			. ' fill="currentColor"'
			. ' aria-hidden="true" focusable="false">'
			. '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5'
			. ' 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09'
			. 'C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5'
			. 'c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>'
			. '</svg>'
			. '</span>'
			. '<span class="favorite-button__status" data-favorite-status role="status" aria-live="polite"></span>'
			. '</button>'
			. '</div>'
			. '<div>'
			. '<p style="font-size:var(--text-xs);color:var(--muted);margin-block-end:var(--space-2xs);">Surface --single (aria-pressed="true")</p>'
			. '<button type="button"'
			. ' class="favorite-button favorite-button--single"'
			. ' data-favorite-toggle'
			. ' data-recipe-id="0"'
			. ' data-favorite-surface="single"'
			. ' data-label-add="Ajouter a mon carnet"'
			. ' data-label-remove="Retirer de mon carnet"'
			. ' aria-pressed="true"'
			. ' aria-label="Retirer de mon carnet">'
			. '<span class="favorite-button__icon" aria-hidden="true">'
			. '<svg class="favorite-button__heart favorite-button__heart--outline"'
			. ' viewBox="0 0 24 24" width="24" height="24"'
			. ' fill="none" stroke="currentColor" stroke-width="2"'
			. ' stroke-linecap="round" stroke-linejoin="round"'
			. ' aria-hidden="true" focusable="false">'
			. '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5'
			. ' 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09'
			. 'C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5'
			. 'c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>'
			. '</svg>'
			. '<svg class="favorite-button__heart favorite-button__heart--filled"'
			. ' viewBox="0 0 24 24" width="24" height="24"'
			. ' fill="currentColor"'
			. ' aria-hidden="true" focusable="false">'
			. '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5'
			. ' 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09'
			. 'C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5'
			. 'c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>'
			. '</svg>'
			. '</span>'
			. '<span class="favorite-button__label">Retirer de mon carnet</span>'
			. '<span class="favorite-button__status" data-favorite-status role="status" aria-live="polite"></span>'
			. '</button>'
			. '</div>'
			. '</div>',
	)
);
// phpcs:enable Generic.Strings.UnnecessaryStringConcat.Found

// ---- Share actions ----
// Depends on URL + title args; SVG icons need _180c_render_svg_icon() reads.
// Static snapshot documents the BEM structure accurately.
_180c_ds_component(
	array(
		'name'     => __( 'Barre de partage', '180c' ),
		'bem'      => '.share-actions',
		'file'     => '_180c_render_share_actions() - inc/share-actions.php',
		'mode'     => 'snapshot',
		'variants' => array( '__inner', '__share', '__btn', '__btn--copy', '__icon', '__feedback', '__favorite' ),
		'note'     => __( 'Barre de partage reseaux (X, Facebook, WhatsApp, LinkedIn, e-mail) + bouton copier-lien + favori optionnel. Les data-attributes data-share-bar/data-share-url/data-share-copy/data-share-feedback sont les crochets JS. Snapshot : les icones SVG requierent _180c_render_svg_icon().', '180c' ),
		'preview'  => '<section class="share-actions" aria-label="Partager">'
			. '<div class="share-actions__inner">'
			. '<nav class="share-actions__share" aria-label="Partager" data-share-bar data-recipe-share data-share-url="https://www.180c.fr/recettes/tajine-agneau/">'
			. '<a class="share-actions__btn recipe-share__btn" href="#" target="_blank" rel="noopener noreferrer">'
			. '<span class="share-actions__icon recipe-share__icon" aria-hidden="true">'
			. '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
			. '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.266 5.638 5.898-5.638Zm-1.161 17.52h1.833L7.084 4.126H5.117Z"/>'
			. '</svg>'
			. '</span>'
			. '<span class="screen-reader-text">Partager sur X (nouvelle fenetre)</span>'
			. '</a>'
			. '<a class="share-actions__btn recipe-share__btn" href="#" target="_blank" rel="noopener noreferrer">'
			. '<span class="share-actions__icon recipe-share__icon" aria-hidden="true">'
			. '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
			. '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>'
			. '</svg>'
			. '</span>'
			. '<span class="screen-reader-text">Partager sur Facebook (nouvelle fenetre)</span>'
			. '</a>'
			. '<button type="button" class="share-actions__btn share-actions__btn--copy recipe-share__btn recipe-share__btn--copy" data-share-copy="https://www.180c.fr/recettes/tajine-agneau/">'
			. '<span class="share-actions__icon recipe-share__icon" aria-hidden="true">'
			. '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
			. '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>'
			. '<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>'
			. '</svg>'
			. '</span>'
			. '<span class="screen-reader-text">Copier le lien</span>'
			. '</button>'
			. '<span class="share-actions__feedback recipe-share__feedback" role="status" aria-live="polite" data-share-feedback></span>'
			. '</nav>'
			. '</div>'
			. '</section>',
	)
);

// ---- Paywall ----
// Needs _180c_recipe_is_paywalled() + get_the_ID() + WC user state - snapshot.
// Markup derived from parts/paywall.php (non-logged-in visitor state).
_180c_ds_component(
	array(
		'name'     => __( 'Paywall', '180c' ),
		'bem'      => '.paywall',
		'file'     => 'parts/paywall.php',
		'mode'     => 'snapshot',
		'variants' => array( '__gradient', '__inner', '__icon', '__title', '__desc', '__actions', '__login-link' ),
		'note'     => __( 'Affiche dans single-recipe.php apres les premiers ingredients si la recette est premium et l\'utilisateur n\'est pas abonne. Deux boutons d\'action : "Je m\'abonne" (primary) + "J\'ai deja un compte" (ghost). Le lien __login-link n\'apparait que pour les visiteurs non connectes.', '180c' ),
		'preview'  => '<aside class="paywall" role="region" aria-label="Paywall recette">'
			. '<div class="paywall__gradient" aria-hidden="true"></div>'
			. '<div class="paywall__inner">'
			. '<div class="paywall__icon" aria-hidden="true">'
			. '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
			. '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>'
			. '<path d="M7 11V7a5 5 0 0 1 10 0v4"/>'
			. '</svg>'
			. '</div>'
			. '<h2 class="paywall__title">Accedez a la recette complete</h2>'
			. '<p class="paywall__desc">Abonnez-vous aux recettes en ligne pour decouvrir les ingredients et toutes les etapes.</p>'
			. '<div class="paywall__actions">'
			. '<a class="btn btn--primary" href="#">Je m&#8217;abonne</a>'
			. '<a class="btn btn--ghost" href="#">J&#8217;ai deja un compte</a>'
			. '</div>'
			. '<p class="paywall__login-link">Deja abonne ? <a href="#">Connexion</a></p>'
			. '</div>'
			. '</aside>',
	)
);

// ---- Search states ----
// Needs live WP_Query + s/tab GET params + user context - snapshot.
// Shows: tabs bar (recipe/article/author) + empty state variant.
_180c_ds_component(
	array(
		'name'     => __( 'Etats de recherche', '180c' ),
		'bem'      => '.search-results',
		'file'     => '_180c_search_render_{header,tabs,panel,empty,prompt}() - inc/search.php',
		'mode'     => 'snapshot',
		'variants' => array(
			'.search-results__header',
			'.search-results__heading',
			'.search-tabs',
			'.search-tabs__list',
			'.search-tabs__item',
			'.search-tabs__link',
			'.search-tabs__link.is-active',
			'.search-tabs__label',
			'.search-tabs__count',
			'.search-results__panel',
			'.search-results__grid',
			'.search-results__grid--authors',
			'.search-results__item',
			'.search-results__empty',
			'.search-results__empty-title',
			'.search-results__empty-help',
			'.search-results__intro',
			'.search-results__lead',
		),
		'note'     => __( 'Trois etats : invite (requete vide, _180c_search_render_prompt), resultats avec onglets recettes/articles/auteurs (_180c_search_render_tabs + panel), etat vide (_180c_search_render_empty). Navigation onglets = liens rechargement serveur (pas role="tab"). Snapshot : necessite WP_Query live + parametres GET ?s=&tab=.', '180c' ),
		'preview'  => '<div class="ds-stack ds-stack--sm">'
			. '<p style="font-size:var(--text-xs);color:var(--muted);font-family:var(--font-display);text-transform:uppercase;letter-spacing:.06em;">En-tete + onglets (resultats)</p>'
			. '<div class="search-results">'
			. '<header class="search-results__header"><h1 class="search-results__heading">Resultats pour &#171;&nbsp;tajine&nbsp;&#187;</h1></header>'
			. '<nav id="resultats" class="search-tabs" aria-label="Filtrer les resultats par type">'
			. '<ul class="search-tabs__list" role="list">'
			. '<li class="search-tabs__item"><a class="search-tabs__link is-active" href="#" aria-current="page"><span class="search-tabs__label">Recettes</span><span class="search-tabs__count">(14)</span></a></li>'
			. '<li class="search-tabs__item"><a class="search-tabs__link" href="#"><span class="search-tabs__label">Articles</span><span class="search-tabs__count">(3)</span></a></li>'
			. '<li class="search-tabs__item"><a class="search-tabs__link" href="#"><span class="search-tabs__label">Auteurs</span><span class="search-tabs__count">(1)</span></a></li>'
			. '</ul>'
			. '</nav>'
			. '</div>'
			. '<p style="font-size:var(--text-xs);color:var(--muted);font-family:var(--font-display);text-transform:uppercase;letter-spacing:.06em;margin-block-start:var(--space-sm);">Etat vide</p>'
			. '<div class="search-results">'
			. '<header class="search-results__header"><h1 class="search-results__heading">Resultats pour &#171;&nbsp;brunoise&nbsp;&#187;</h1></header>'
			. '<div class="search-results__empty">'
			. '<p class="search-results__empty-title">Aucun resultat pour &#171;&nbsp;brunoise&nbsp;&#187;</p>'
			. '<p class="search-results__empty-help">Verifiez l&#8217;orthographe ou essayez d&#8217;autres mots-cles.</p>'
			. '</div>'
			. '</div>'
			. '</div>',
	)
);
