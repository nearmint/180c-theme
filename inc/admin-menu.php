<?php
/**
 * Rangement du menu d'administration.
 *
 * Intervention PUREMENT VISUELLE. Ce module ne modifie aucune capability, ne
 * désactive aucune extension et ne supprime aucun code métier : il déplace ou
 * masque des entrées dans les globales `$menu` / `$submenu` construites par
 * `wp-admin/menu.php`. Toutes les pages concernées restent joignables à leur
 * URL actuelle, y compris celles retirées du menu.
 *
 * Opérations, dans l'ordre : « Toutes les notifications » Notifications →
 * Marketing puis renommée « Notifications » ; « Envoyer une notification » retirée du menu ;
 * masquage des top-levels « Notifications », « Paiements » et « Statistiques » ;
 * « WP Mail Logging » et « Simple JWT Login » descendus sous « Outils » ;
 * « Audit SEO (ACF) » et « Test e-mails » retirés d'« Outils » ;
 * « Vue d'ensemble » retirée de « Marketing » ; « Bandeau Boutique » Apparence
 * → « Marketing », en dernière position.
 *
 * Réversibilité — l'intégralité du comportement est conditionnée au filtre
 * `_180c_admin_menu_tidy_enabled` (défaut `true`) :
 *
 *     add_filter( '_180c_admin_menu_tidy_enabled', '__return_false' );
 *
 * Priorité 9999 et non 999 : WooCommerce PDF Invoices occupe déjà 999
 * (`WPO\IPS\Settings::menu`). À priorité égale l'ordre dépendrait de l'ordre
 * d'enregistrement des callbacks ; 9999 garantit de passer après tout le monde.
 * Relevé des priorités effectué sur l'environnement Local le 2026-09-01.
 *
 * @package 180c
 */

/*
 * GlobalVariablesOverride : réécrire `$menu` et `$submenu` EST la mission de
 * ce fichier. C'est l'API que le cœur expose pour réordonner le menu — il n'y
 * a pas de fonction dédiée au déplacement d'une entrée. Le sniff protège
 * contre l'écrasement accidentel d'une globale du cœur ; ici l'écriture est
 * l'intention, et elle reste cantonnée aux deux helpers de manipulation.
 */
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited

defined( 'ABSPATH' ) || exit;

/**
 * Indique si le rangement du menu doit s'appliquer.
 *
 * @return bool
 */
function _180c_admin_menu_tidy_enabled(): bool {
	/**
	 * Active ou désactive le rangement du menu d'administration.
	 *
	 * @param bool $enabled Actif par défaut.
	 */
	return (bool) apply_filters( '_180c_admin_menu_tidy_enabled', true );
}

/**
 * Table des entrées déplacées : slug de page => slug du nouveau parent.
 *
 * Sert à la fois aux déplacements et aux filtres de surlignage.
 *
 * @return array<string, string>
 */
function _180c_admin_menu_moved_pages(): array {
	return array(
		'180c-info-banner'                  => 'woocommerce-marketing',
		'180c-survey'                       => 'woocommerce-marketing',
		'main-page-simple-jwt-login-plugin' => 'tools.php',
		'wpml_plugin_log'                   => 'tools.php',
	);
}

/**
 * Déplace une entrée de sous-menu vers un autre parent, en fin de liste.
 *
 * Conserve le tableau d'origine tel quel (libellé, capability, slug, callback
 * déjà accroché) : seule sa position dans `$submenu` change.
 *
 * @param string $from_parent Slug du parent actuel.
 * @param string $to_parent   Slug du parent cible.
 * @param string $slug        Slug de l'entrée à déplacer.
 * @return bool Vrai si l'entrée a été trouvée et déplacée.
 */
function _180c_admin_menu_move_submenu_entry( string $from_parent, string $to_parent, string $slug ): bool {
	global $submenu;

	if ( ! isset( $submenu[ $from_parent ] ) || ! is_array( $submenu[ $from_parent ] ) ) {
		return false;
	}

	foreach ( $submenu[ $from_parent ] as $key => $entry ) {
		if ( ! isset( $entry[2] ) || $slug !== $entry[2] ) {
			continue;
		}

		unset( $submenu[ $from_parent ][ $key ] );

		if ( ! isset( $submenu[ $to_parent ] ) || ! is_array( $submenu[ $to_parent ] ) ) {
			$submenu[ $to_parent ] = array();
		}
		$submenu[ $to_parent ][] = $entry;

		if ( empty( $submenu[ $from_parent ] ) ) {
			unset( $submenu[ $from_parent ] );
		}

		return true;
	}

	return false;
}

/**
 * Renomme l'intitulé d'une entrée de sous-menu.
 *
 * Met aussi à jour le titre de page (index 3) quand il est renseigné : c'est lui
 * que `get_admin_page_title()` privilégie pour le `<title>` de l'écran, qui
 * sinon divergerait de l'intitulé affiché au menu.
 *
 * @param string $parent_slug Slug du parent.
 * @param string $slug        Slug de l'entrée.
 * @param string $label       Nouvel intitulé.
 * @return bool Vrai si l'entrée a été trouvée et renommée.
 */
function _180c_admin_menu_rename_submenu_entry( string $parent_slug, string $slug, string $label ): bool {
	global $submenu;

	if ( ! isset( $submenu[ $parent_slug ] ) || ! is_array( $submenu[ $parent_slug ] ) ) {
		return false;
	}

	foreach ( $submenu[ $parent_slug ] as $key => $entry ) {
		if ( ! isset( $entry[2] ) || $slug !== $entry[2] ) {
			continue;
		}

		$submenu[ $parent_slug ][ $key ][0] = $label;
		if ( isset( $submenu[ $parent_slug ][ $key ][3] ) ) {
			$submenu[ $parent_slug ][ $key ][3] = $label;
		}

		return true;
	}

	return false;
}

/**
 * Rétablit le rendu d'une page de plugin déplacée sous un autre parent.
 *
 * `wp-admin/admin.php:189` résout le hook de rendu via
 * `get_plugin_page_hookname()`, qui appelle `get_admin_page_parent()` — laquelle
 * parcourt `$submenu` en direct. `wp-admin/menu.php` étant chargé ligne 163,
 * donc AVANT cette résolution, le déplacement change le hookname calculé :
 * `has_action()` devient faux et `admin.php:274` répond
 * `wp_die( 'Cannot load …' )`.
 *
 * On réémet donc les hooks d'origine sous le nouveau nom. Le callback de la
 * page n'est pas ré-enregistré : c'est bien l'ancien hook qui est déclenché.
 *
 * Sans effet quand le hookname ne change pas — cas des anciens menus de premier
 * niveau, pour lesquels `$admin_page_hooks[ $slug ]` reste défini et impose
 * `toplevel_page_…` quel que soit le parent.
 *
 * @param string $slug       Slug de la page déplacée.
 * @param string $old_parent Parent d'origine, tel qu'utilisé à l'enregistrement.
 * @param string $new_parent Nouveau parent.
 * @return void
 */
function _180c_admin_menu_alias_page_hook( string $slug, string $old_parent, string $new_parent ): void {
	global $_registered_pages;

	if ( ! function_exists( 'get_plugin_page_hookname' ) ) {
		return;
	}

	$old_hook = get_plugin_page_hookname( $slug, $old_parent );
	$new_hook = get_plugin_page_hookname( $slug, $new_parent );

	if ( ! $old_hook || ! $new_hook || $old_hook === $new_hook ) {
		return;
	}

	// Rien à réémettre si la page n'était pas rendue par un hook (page inexistante).
	if ( ! has_action( $old_hook ) ) {
		return;
	}

	if ( ! is_array( $_registered_pages ) ) {
		$_registered_pages = array();
	}
	$_registered_pages[ $new_hook ] = true;

	/*
	 * Les hooks réémis appartiennent au CŒUR : leur nom est calculé par
	 * get_plugin_page_hookname() (« appearance_page_180c-info-banner »). Les préfixer
	 * en _180c_ ne réémettrait plus rien — c'est justement le nom d'origine qu'il
	 * faut déclencher pour que le callback déjà accroché s'exécute.
	 */
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	add_action(
		$new_hook,
		static function () use ( $old_hook ) {
			do_action( $old_hook );
		}
	);
	add_action(
		'load-' . $new_hook,
		static function () use ( $old_hook ) {
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Le préfixe « load- » est imposé par wp-admin/admin.php:242.
			do_action( 'load-' . $old_hook );
		}
	);
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
}

/**
 * Transforme un menu de premier niveau en entrée de sous-menu, en fin de liste.
 *
 * Le sous-menu propre à l'ancien menu de premier niveau est retiré : sans
 * parent affiché il ne serait plus rendu, et ses entrées fausseraient
 * `get_admin_page_parent()`. Les pages correspondantes restent joignables à leur
 * URL.
 *
 * @param string $slug      Slug du menu de premier niveau.
 * @param string $to_parent Slug du parent cible.
 * @return bool Vrai si le menu a été trouvé et déplacé.
 */
function _180c_admin_menu_demote_top_level( string $slug, string $to_parent ): bool {
	global $menu, $submenu;

	if ( ! is_array( $menu ) ) {
		return false;
	}

	foreach ( $menu as $key => $entry ) {
		if ( ! isset( $entry[2] ) || $slug !== $entry[2] ) {
			continue;
		}

		if ( ! isset( $submenu[ $to_parent ] ) || ! is_array( $submenu[ $to_parent ] ) ) {
			$submenu[ $to_parent ] = array();
		}

		// Libellé, capability et slug d'origine conservés à l'identique.
		$submenu[ $to_parent ][] = array( $entry[0], $entry[1], $entry[2] );

		unset( $menu[ $key ], $submenu[ $slug ] );

		return true;
	}

	return false;
}

/**
 * 1. « Toutes les notifications » : « Notifications » → « Marketing ».
 *
 * Lien de fichier (`edit.php?post_type=…`) et non page de plugin : aucun
 * hookname en jeu.
 *
 * @return void
 */
function _180c_admin_menu_move_notifications_list(): void {
	_180c_admin_menu_move_submenu_entry(
		'edit.php?post_type=180c_notification',
		'woocommerce-marketing',
		'edit.php?post_type=180c_notification'
	);
}

/**
 * 2 bis. Renomme « Toutes les notifications » en « Notifications ».
 *
 * L'entrée n'a plus de voisine sous Marketing depuis le retrait d'« Envoyer une
 * notification » : le « Toutes les » ne distingue plus rien.
 *
 * @return void
 */
function _180c_admin_menu_rename_notifications(): void {
	_180c_admin_menu_rename_submenu_entry(
		'woocommerce-marketing',
		'edit.php?post_type=180c_notification',
		__( 'Notifications', '180c' )
	);
}

/**
 * 2. Retire « Envoyer une notification » du menu.
 *
 * La création reste joignable à son URL
 * (`post-new.php?post_type=180c_notification`) et par le bouton « Ajouter »
 * de la liste des notifications.
 *
 * @return void
 */
function _180c_admin_menu_hide_notifications_new(): void {
	remove_submenu_page(
		'edit.php?post_type=180c_notification',
		'post-new.php?post_type=180c_notification'
	);
}

/**
 * 3. Masque le menu de premier niveau « Notifications », désormais vide.
 *
 * @return void
 */
function _180c_admin_menu_hide_notifications(): void {
	remove_menu_page( 'edit.php?post_type=180c_notification' );
}

/**
 * 4. Masque « Paiements » (WooCommerce).
 *
 * @return void
 */
function _180c_admin_menu_hide_payments(): void {
	remove_menu_page( 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM' );
}

/**
 * 5. Masque « Statistiques » (WooCommerce Analytics).
 *
 * Le slug enregistré est bien `wc-admin&path=/analytics/overview`, sans préfixe
 * `admin.php?page=` — relevé sur `$menu` le 2026-09-01.
 *
 * @return void
 */
function _180c_admin_menu_hide_analytics(): void {
	remove_menu_page( 'wc-admin&path=/analytics/overview' );
}

/**
 * 6. « WP Mail Logging » : menu de premier niveau → « Outils ».
 *
 * Les onglets « Email log », « Réglages » et « SMTP » sont des onglets internes
 * à la page (`&tab=…`) : ils restent atteignables depuis la page elle-même.
 *
 * @return void
 */
function _180c_admin_menu_move_mail_logging(): void {
	_180c_admin_menu_demote_top_level( 'wpml_plugin_log', 'tools.php' );
}

/**
 * 7. « Simple JWT Login » : menu de premier niveau → « Outils ».
 *
 * @return void
 */
function _180c_admin_menu_move_jwt(): void {
	_180c_admin_menu_demote_top_level( 'main-page-simple-jwt-login-plugin', 'tools.php' );
}

/**
 * 8. Retire « Audit SEO (ACF) » du menu « Outils ».
 *
 * La page reste enregistrée et joignable : `tools.php?page=180c-seo-audit`.
 *
 * @return void
 */
function _180c_admin_menu_hide_seo_audit(): void {
	remove_submenu_page( 'tools.php', '180c-seo-audit' );
}

/**
 * 9. Retire « Test e-mails » du menu « Outils ».
 *
 * La page reste enregistrée et joignable : `tools.php?page=180c-email-tester`.
 *
 * @return void
 */
function _180c_admin_menu_hide_email_tester(): void {
	remove_submenu_page( 'tools.php', '180c-email-tester' );
}

/**
 * 10. Retire « Vue d'ensemble » du menu « Marketing ».
 *
 * Page WooCommerce Admin (`Internal\Admin\Marketing::register_overview_page`).
 * Le slug porté par `$submenu` est bien préfixé `admin.php?page=` : la classe le
 * réécrit elle-même juste après l'enregistrement, `register_page()` posant
 * sinon un chemin faux. C'est le seul cas de figure du module — « Statistiques »
 * est enregistrée sans ce préfixe.
 *
 * La page reste joignable :
 * `admin.php?page=wc-admin&path=/marketing`.
 *
 * @return void
 */
function _180c_admin_menu_hide_marketing_overview(): void {
	remove_submenu_page( 'woocommerce-marketing', 'admin.php?page=wc-admin&path=/marketing' );
}

/**
 * 11. « Bandeau Boutique » : « Apparence » → « Marketing », en dernière position.
 *
 * Sous-page d'options ACF déclarée par le thème (`inc/info-banner.php:33`),
 * posée dans le menu par ACF en priorité 99. Son hookname dépend du parent
 * (`appearance_page_180c-info-banner` → `marketing_page_180c-info-banner`),
 * l'alias est donc indispensable.
 *
 * Appelée en dernier : `_180c_admin_menu_move_submenu_entry()` empile en fin de
 * `$submenu['woocommerce-marketing']`, et `Marketing::reorder_marketing_submenu`
 * (priorité 99) a déjà trié le sous-menu bien avant notre priorité 9999.
 *
 * La capability de l'entrée reste `edit_theme_options`, mais le parent
 * « Marketing » exige `manage_woocommerce` : un profil qui aurait l'une sans
 * l'autre perdrait l'accès par le menu. Sans objet ici, le bandeau n'étant
 * administré que par des administrateurs.
 *
 * @return void
 */
function _180c_admin_menu_move_info_banner(): void {
	$moved = _180c_admin_menu_move_submenu_entry(
		'themes.php',
		'woocommerce-marketing',
		'180c-info-banner'
	);

	if ( $moved ) {
		_180c_admin_menu_alias_page_hook(
			'180c-info-banner',
			'themes.php',
			'woocommerce-marketing'
		);
	}
}

/**
 * 12. « Sondage » : « Apparence » → « Marketing », en dernière position.
 *
 * Sous-page d'options ACF déclarée par le thème
 * (`inc/survey/options-page.php`), posée dans le menu par ACF en priorité 99.
 * Même forme que « Bandeau Boutique » : son hookname dépend du parent
 * (`appearance_page_180c-survey` → `marketing_page_180c-survey`), l'alias est
 * donc indispensable.
 *
 * Appelée après « Bandeau Boutique » : `_180c_admin_menu_move_submenu_entry()`
 * empile en fin de `$submenu['woocommerce-marketing']`, l'ordre des appels est
 * donc l'ordre d'affichage. « Sondage » ferme la marche.
 *
 * @return void
 */
function _180c_admin_menu_move_survey(): void {
	$moved = _180c_admin_menu_move_submenu_entry(
		'themes.php',
		'woocommerce-marketing',
		'180c-survey'
	);

	if ( $moved ) {
		_180c_admin_menu_alias_page_hook(
			'180c-survey',
			'themes.php',
			'woocommerce-marketing'
		);
	}
}

/**
 * Point d'entrée unique du rangement.
 *
 * @return void
 */
function _180c_admin_menu_tidy(): void {
	if ( ! _180c_admin_menu_tidy_enabled() ) {
		return;
	}

	_180c_admin_menu_move_notifications_list();
	_180c_admin_menu_rename_notifications();
	_180c_admin_menu_hide_notifications_new();
	_180c_admin_menu_hide_notifications();
	_180c_admin_menu_hide_payments();
	_180c_admin_menu_hide_analytics();
	_180c_admin_menu_move_mail_logging();
	_180c_admin_menu_move_jwt();
	_180c_admin_menu_hide_seo_audit();
	_180c_admin_menu_hide_email_tester();
	_180c_admin_menu_hide_marketing_overview();
	_180c_admin_menu_move_info_banner();
	_180c_admin_menu_move_survey();
}
add_action( 'admin_menu', '_180c_admin_menu_tidy', 9999 );

/**
 * Surligne le nouveau parent des entrées déplacées.
 *
 * @param string $parent_file Parent calculé par le cœur.
 * @return string
 */
function _180c_admin_menu_parent_file( $parent_file ) {
	global $plugin_page, $typenow;

	if ( ! _180c_admin_menu_tidy_enabled() ) {
		return $parent_file;
	}

	$moved = _180c_admin_menu_moved_pages();

	if ( is_string( $plugin_page ) && isset( $moved[ $plugin_page ] ) ) {
		return $moved[ $plugin_page ];
	}

	// Liste, création et édition d'une notification.
	if ( '180c_notification' === $typenow ) {
		return 'woocommerce-marketing';
	}

	return $parent_file;
}
add_filter( 'parent_file', '_180c_admin_menu_parent_file' );

/**
 * Surligne la bonne entrée de sous-menu pour les pages déplacées.
 *
 * @param string $submenu_file Entrée calculée par le cœur.
 * @return string
 */
function _180c_admin_menu_submenu_file( $submenu_file ) {
	global $plugin_page, $typenow, $pagenow;

	if ( ! _180c_admin_menu_tidy_enabled() ) {
		return $submenu_file;
	}

	$moved = _180c_admin_menu_moved_pages();

	if ( is_string( $plugin_page ) && isset( $moved[ $plugin_page ] ) ) {
		return $plugin_page;
	}

	/*
	 * « Envoyer une notification » ne figure plus au menu : l'écran de création
	 * surligne « Notifications », comme le cœur le fait pour les autres CPT.
	 */
	if ( '180c_notification' === $typenow ) {
		return 'edit.php?post_type=180c_notification';
	}

	return $submenu_file;
}
add_filter( 'submenu_file', '_180c_admin_menu_submenu_file' );
