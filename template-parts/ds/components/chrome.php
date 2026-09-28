<?php
/**
 * Design System -- lot "Navigation & chrome".
 *
 * Header, skip link, side menu push, footer, theme toggle, breadcrumb,
 * pagination, formulaire de recherche.
 *
 * Composants snapshot : rendus HTML statiques fideles (contexte menu admin /
 * user / Woo / query requis au runtime). Composants live : les vrais partials /
 * fonctions PHP sont appeles directement.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ---- Header de site ----
_180c_ds_component(
	array(
		'name'     => __( 'Header de site', '180c' ),
		'bem'      => '.site-header',
		'file'     => 'parts/header/main.php',
		'mode'     => 'snapshot',
		'variants' => array(
			'__inner',
			'__left',
			'__right',
			'__menu-trigger',
			'__logo',
			'__nav-primary',
			'__nav-list',
			'__account-wrap',
			'__account',
			'__account-label',
			'__chevron-down',
			'__account-dropdown',
			'__account-dropdown-header',
			'__account-dropdown-header-row',
			'__account-name',
			'__account-pill',
			'__account-email',
			'__account-dropdown-sep',
			'__account-dropdown-item',
			'__login',
			'__signup',
		),
		'note'     => __( 'Chrome principal du site. Snapshot : depend des menus admin assignes et du statut de connexion (anonyme / connecte / abonne). Etat anonyme reproduit ici (logo + nav + login / S\'abonner). L\'etat connecte ajoute .site-header__account-wrap avec un dropdown role=menu.', '180c' ),
		'preview'  => '<header id="site-header" class="site-header" style="position:relative;z-index:1;">'
			. '<div class="site-container">'
			. '<div class="site-header__inner">'
			. '<div class="site-header__left">'
			. '<button type="button" class="site-header__menu-trigger js-side-menu-toggle" aria-label="Menu et recherche" aria-expanded="false" aria-controls="site-side-menu">'
			. '<svg class="site-header__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. '<line x1="3" y1="6" x2="13" y2="6"/>'
			. '<line x1="3" y1="12" x2="13" y2="12"/>'
			. '<line x1="3" y1="18" x2="13" y2="18"/>'
			. '<circle cx="18" cy="17" r="3"/>'
			. '<line x1="20.5" y1="19.5" x2="22" y2="21"/>'
			. '</svg>'
			. '</button>'
			. '<a href="#" class="site-header__logo" rel="home" aria-label="180&deg;C &mdash; Accueil">'
			. '<span class="screen-reader-text">180&deg;C</span>'
			. '</a>'
			. '<nav class="site-header__nav-primary" aria-label="Navigation principale">'
			. '<ul class="site-header__nav-list">'
			. '<li><a href="#">La Gazette</a></li>'
			. '<li><a href="#">Cahiers de Delphine</a></li>'
			. '<li><a href="#">Boutique</a></li>'
			. '<li><a href="#" class="site-header__nav-cta">Premium</a></li>'
			. '</ul>'
			. '</nav>'
			. '</div>'
			. '<div class="site-header__right">'
			. '<a href="#" class="site-header__login">Se connecter</a>'
			. '<a href="#" class="site-header__signup btn btn--primary">S&#8217;abonner</a>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</header>',
	)
);

// ---- Skip link ----
_180c_ds_component(
	array(
		'name'    => __( 'Skip link', '180c' ),
		'bem'     => '.skip-link.screen-reader-text',
		'file'    => 'header.php',
		'mode'    => 'live',
		'note'    => __( 'Lien d\'evitement accessible (WCAG 2.1 AA). Visible uniquement au focus clavier. Pointe vers #main.', '180c' ),
		'preview' => '<a class="skip-link screen-reader-text" href="#main">Aller au contenu</a>'
			. '<p style="margin-top:var(--space-xs);color:var(--muted);font-size:var(--text-sm);">'
			. '<em>Ce lien est invisible par defaut et appara&icirc;t uniquement au focus clavier (Tab).</em>'
			. '</p>',
	)
);

// ---- Side menu push ----
_180c_ds_component(
	array(
		'name'     => __( 'Side menu (push drawer)', '180c' ),
		'bem'      => '.site-side-menu',
		'file'     => 'parts/site-side-menu.php',
		'mode'     => 'snapshot',
		'variants' => array(
			'__inner',
			'__close',
			'__icon',
			'__search',
			'__search-input',
			'__search-submit',
			'__nav',
			'__list',
			'__item',
			'__link',
			'__expand',
			'__chevron',
			'__chevron-icon',
			'__sub',
			'__cta',
			'__login',
			'__bottom',
			'__divider',
			'__social',
			'__social-icon',
		),
		'note'     => __( 'Panneau push lateral gauche. Snapshot : depend des menus admin (location "side") et du statut de connexion. JS : src/js/modules/side-menu.js gere le toggle body.has-side-menu-open et le piege de focus. Les items expand-{taxonomy} sont alimentes par get_terms() au runtime.', '180c' ),
		'preview'  => '<aside class="site-side-menu" aria-hidden="true" aria-label="Menu principal" style="position:relative;transform:none;width:320px;max-width:100%;display:block;">'
			. '<div class="site-side-menu__inner">'
			. '<button type="button" class="site-side-menu__close" aria-label="Fermer le menu">'
			. '<svg class="site-side-menu__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
			. '</button>'
			. '<form class="site-side-menu__search" role="search" action="#" method="get">'
			. '<label for="ds-side-search" class="screen-reader-text">Rechercher</label>'
			. '<input id="ds-side-search" class="site-side-menu__search-input" type="search" name="s" placeholder="Rechercher&hellip;" autocomplete="off">'
			. '<button type="submit" class="site-side-menu__search-submit" aria-label="Lancer la recherche">'
			. '<svg class="site-side-menu__icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
			. '</button>'
			. '</form>'
			. '<nav class="site-side-menu__nav" aria-label="Navigation secondaire">'
			. '<ul class="site-side-menu__list">'
			. '<li class="site-side-menu__item">'
			. '<a class="site-side-menu__link" href="#">La Gazette</a>'
			. '</li>'
			. '<li class="site-side-menu__item">'
			. '<button class="site-side-menu__expand" aria-expanded="false" type="button">'
			. '<span>Recettes</span>'
			. '<span class="site-side-menu__chevron"><svg class="site-side-menu__chevron-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg></span>'
			. '</button>'
			. '<ul class="site-side-menu__sub" hidden>'
			. '<li><a href="#">Ap&eacute;ritifs</a></li>'
			. '<li><a href="#">Desserts</a></li>'
			. '<li><a href="#">Plats</a></li>'
			. '</ul>'
			. '</li>'
			. '<li class="site-side-menu__item">'
			. '<a class="site-side-menu__link" href="#">Boutique</a>'
			. '</li>'
			. '</ul>'
			. '</nav>'
			. '<div class="site-side-menu__cta">'
			. '<a href="#" class="btn btn--primary">S&#8217;abonner</a>'
			. '<a href="#" class="site-side-menu__login">Se connecter</a>'
			. '</div>'
			. '<div class="site-side-menu__bottom">'
			. '<hr class="site-side-menu__divider" aria-hidden="true">'
			. '<ul class="site-side-menu__social" aria-label="R&eacute;seaux sociaux">'
			. '<li><a href="#" aria-label="Suivre 180&deg;C sur Facebook">'
			. '<svg class="site-side-menu__social-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.13 8.44 9.88v-6.99H7.9v-2.89h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.89h-2.34V22c4.78-.75 8.43-4.88 8.43-9.94Z"/></svg>'
			. '</a></li>'
			. '<li><a href="#" aria-label="Suivre 180&deg;C sur Instagram">'
			. '<svg class="site-side-menu__social-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>'
			. '</a></li>'
			. '</ul>'
			. '</div>'
			. '</div>'
			. '</aside>',
	)
);

// ---- Footer ----
_180c_ds_component(
	array(
		'name'     => __( 'Footer de site', '180c' ),
		'bem'      => '.site-footer',
		'file'     => 'parts/footer/main.php',
		'mode'     => 'snapshot',
		'variants' => array(
			'__brand-row',
			'__brand',
			'__logo',
			'__theme-toggle-wrap',
			'__sub-title',
			'__columns',
			'__col',
			'__col-title',
			'__col--tools',
			'__nav',
			'__app',
			'__app-badges',
			'__app-badge',
			'__social-wrap',
			'__social',
			'__bottom',
			'__copyright',
			'__legal',
			'__legal-nav',
		),
		'note'     => __( 'Footer 4 colonnes : Decouvrir / Editions / Aide / Apps & reseaux. Snapshot : les menus sont rendus via wp_nav_menu (locations footer-discover, footer-editions, footer-help) ou un fallback hardcode. L\'annee copyright est dynamique. Le toggle de theme est inline dans la brand-row.', '180c' ),
		'preview'  => '<footer class="site-footer">'
			. '<div class="site-container">'
			. '<div class="site-footer__brand-row">'
			. '<div class="site-footer__brand">'
			. '<a href="#" class="site-footer__logo" rel="home" aria-label="180&deg;C &mdash; Accueil">'
			. '<span class="screen-reader-text">180&deg;C &mdash; La revue culture food</span>'
			. '</a>'
			. '</div>'
			. '<div class="site-footer__theme-toggle-wrap">'
			. '<h3 class="site-footer__sub-title">R&eacute;gler l&#8217;affichage</h3>'
			. '<fieldset class="theme-toggle" data-theme-toggle>'
			. '<legend class="screen-reader-text">Th&egrave;me d&#8217;affichage</legend>'
			. '<label class="theme-toggle__option"><input type="radio" name="ds-footer-theme-mode" value="auto" class="theme-toggle__input" checked><span class="theme-toggle__label">Auto</span></label>'
			. '<label class="theme-toggle__option"><input type="radio" name="ds-footer-theme-mode" value="light" class="theme-toggle__input"><span class="theme-toggle__label">Clair</span></label>'
			. '<label class="theme-toggle__option"><input type="radio" name="ds-footer-theme-mode" value="dark" class="theme-toggle__input"><span class="theme-toggle__label">Sombre</span></label>'
			. '</fieldset>'
			. '</div>'
			. '</div>'
			. '<div class="site-footer__columns">'
			. '<nav class="site-footer__col" aria-labelledby="ds-footer-col-1">'
			. '<h2 id="ds-footer-col-1" class="site-footer__col-title">D&eacute;couvrir 180&deg;C</h2>'
			. '<ul class="site-footer__nav">'
			. '<li><a href="#">La Gazette</a></li>'
			. '<li><a href="#">Cahiers de Delphine</a></li>'
			. '<li><a href="#">Recettes en ligne</a></li>'
			. '<li><a href="#">Boutique</a></li>'
			. '<li><a href="#">S&#8217;abonner</a></li>'
			. '</ul>'
			. '</nav>'
			. '<div class="site-footer__col site-footer__col--tools" aria-labelledby="ds-footer-col-4">'
			. '<h2 id="ds-footer-col-4" class="site-footer__col-title">Apps et r&eacute;seaux</h2>'
			. '<div class="site-footer__app">'
			. '<h3 class="site-footer__sub-title">T&eacute;l&eacute;charger l&#8217;app</h3>'
			. '<ul class="site-footer__app-badges">'
			. '<li><a href="#" class="site-footer__app-badge" data-store="ios" aria-label="T&eacute;l&eacute;charger sur l&#8217;App Store"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></a></li>'
			. '<li><a href="#" class="site-footer__app-badge" data-store="android" aria-label="Disponible sur Google Play"><span class="ds-ph ds-ph--wide" aria-hidden="true"></span></a></li>'
			. '</ul>'
			. '</div>'
			. '<div class="site-footer__social-wrap">'
			. '<h3 class="site-footer__sub-title">R&eacute;seaux sociaux</h3>'
			. '<ul class="site-footer__social" aria-label="R&eacute;seaux sociaux">'
			. '<li><a href="#" aria-label="Suivre 180&deg;C sur Facebook"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.13 8.44 9.88v-6.99H7.9v-2.89h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.89h-2.34V22c4.78-.75 8.43-4.88 8.43-9.94Z"/></svg></a></li>'
			. '<li><a href="#" aria-label="Suivre 180&deg;C sur Instagram"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a></li>'
			. '</ul>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '<div class="site-footer__bottom">'
			. '<p class="site-footer__copyright">&copy; <time datetime="2026">2026</time> 180&deg;C &mdash; Tous droits r&eacute;serv&eacute;s</p>'
			. '<nav class="site-footer__legal" aria-label="Informations l&eacute;gales">'
			. '<ul class="site-footer__legal-nav">'
			. '<li><a href="#">Mentions l&eacute;gales</a></li>'
			. '<li><a href="#">Politique de confidentialit&eacute;</a></li>'
			. '<li><a href="#">CGV</a></li>'
			. '<li><a href="#">Gestion des cookies</a></li>'
			. '</ul>'
			. '</nav>'
			. '</div>'
			. '</div>'
			. '</footer>',
	)
);

// ---- Theme toggle ----
_180c_ds_component(
	array(
		'name'     => __( 'Theme toggle', '180c' ),
		'bem'      => '.theme-toggle',
		'file'     => 'parts/footer/main.php + src/css/components/theme-toggle.css',
		'mode'     => 'snapshot',
		'variants' => array(
			'__option',
			'__input',
			'__label',
		),
		'note'     => __( 'Fieldset a 3 radios (auto / clair / sombre) pilote par src/js/modules/dark-mode.js. Pose data-theme sur <html>. La valeur "auto" suit prefers-color-scheme.', '180c' ),
		'preview'  => '<fieldset class="theme-toggle" data-theme-toggle>'
			. '<legend class="screen-reader-text">Th&egrave;me d&#8217;affichage</legend>'
			. '<label class="theme-toggle__option">'
			. '<input type="radio" name="ds-theme-mode-solo" value="auto" class="theme-toggle__input" checked>'
			. '<span class="theme-toggle__label">Auto</span>'
			. '</label>'
			. '<label class="theme-toggle__option">'
			. '<input type="radio" name="ds-theme-mode-solo" value="light" class="theme-toggle__input">'
			. '<span class="theme-toggle__label">Clair</span>'
			. '</label>'
			. '<label class="theme-toggle__option">'
			. '<input type="radio" name="ds-theme-mode-solo" value="dark" class="theme-toggle__input">'
			. '<span class="theme-toggle__label">Sombre</span>'
			. '</label>'
			. '</fieldset>',
	)
);

// ---- Breadcrumb ----
// _180c_breadcrumb_markup() accepts a plain items array { label, url? } with no
// DB / ACF / Woo dependency -- safe to call directly with a mock dataset. Live.
_180c_ds_component(
	array(
		'name'     => __( 'Fil d\'Ariane (breadcrumb)', '180c' ),
		'bem'      => '.breadcrumb',
		'file'     => '_180c_breadcrumb_markup() (inc/helpers.php)',
		'mode'     => 'live',
		'variants' => array(
			'__inner',
			'__item',
			'__item[aria-current="page"]',
			'__sep',
		),
		'note'     => __( 'Rendu via _180c_breadcrumb_markup() avec un jeu d\'items de demonstration. Le dernier item (sans url) recoit aria-current="page". Separateur &rsaquo; entre chaque item.', '180c' ),
		'preview'  => _180c_breadcrumb_markup(
			array(
				array(
					'label' => __( 'Accueil', '180c' ),
					'url'   => '#',
				),
				array(
					'label' => __( 'Recettes', '180c' ),
					'url'   => '#',
				),
				array(
					'label' => __( 'Desserts', '180c' ),
					'url'   => '#',
				),
				array(
					'label' => __( 'Tarte Tatin aux pommes', '180c' ),
				),
			)
		),
	)
);

// ---- Pagination ----
// _180c_render_pagination() delegates to paginate_links() which needs an active
// WP_Query to build valid URLs -- snapshot with a representative page 3/8 HTML.
_180c_ds_component(
	array(
		'name'     => __( 'Pagination', '180c' ),
		'bem'      => '.pagination',
		'file'     => '_180c_render_pagination() (inc/helpers.php)',
		'mode'     => 'snapshot',
		'variants' => array(
			'__list',
			'__item',
			'__link',
			'__link--current',
			'__link--prev',
			'__link--next',
			'__link--dots',
		),
		'note'     => __( 'Snapshot : _180c_render_pagination() delegue a paginate_links() (depend d\'une WP_Query active pour les URLs). Rendu HTML statique representatif d\'une page 3/8 avec ellipses.', '180c' ),
		'preview'  => '<nav class="pagination" aria-label="Pagination">'
			. '<ul class="pagination__list">'
			. '<li class="pagination__item">'
			. '<a class="pagination__link pagination__link--prev" href="#" rel="prev">'
			. '<span class="sr-only">Page pr&eacute;c&eacute;dente</span>'
			. '<span aria-hidden="true">&larr;</span>'
			. '</a>'
			. '</li>'
			. '<li class="pagination__item"><a class="pagination__link" href="#">1</a></li>'
			. '<li class="pagination__item"><span class="pagination__link pagination__link--dots">&hellip;</span></li>'
			. '<li class="pagination__item"><a class="pagination__link" href="#">2</a></li>'
			. '<li class="pagination__item"><span class="pagination__link pagination__link--current" aria-current="page">3</span></li>'
			. '<li class="pagination__item"><a class="pagination__link" href="#">4</a></li>'
			. '<li class="pagination__item"><span class="pagination__link pagination__link--dots">&hellip;</span></li>'
			. '<li class="pagination__item"><a class="pagination__link" href="#">8</a></li>'
			. '<li class="pagination__item">'
			. '<a class="pagination__link pagination__link--next" href="#" rel="next">'
			. '<span class="sr-only">Page suivante</span>'
			. '<span aria-hidden="true">&rarr;</span>'
			. '</a>'
			. '</li>'
			. '</ul>'
			. '</nav>',
	)
);

// ---- Formulaire de recherche ----
// parts/search-form.php has no critical DB dependency: get_search_query()
// returns empty string outside a search context, home_url() is always available.
// Rendered live via _180c_ds_capture().
_180c_ds_component(
	array(
		'name'     => __( 'Formulaire de recherche', '180c' ),
		'bem'      => '.search-form',
		'file'     => 'parts/search-form.php',
		'mode'     => 'live',
		'variants' => array(
			'__label',
			'__input',
			'__submit',
			'__icon',
		),
		'note'     => __( 'Formulaire GET natif (action=/?s=). Rendu live via le vrai partial parts/search-form.php. Pre-rempli avec get_search_query() (vide hors contexte de resultats). Utilise sur la page de recherche (etats vide / no-result) ; le side menu embarque sa propre variante .site-side-menu__search.', '180c' ),
		'preview'  => _180c_ds_capture( 'parts/search-form' ),
	)
);
