<?php
/**
 * Configuration de base du thème.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		// Title tag.
		add_theme_support( 'title-tag' );

		// Featured images.
		add_theme_support( 'post-thumbnails' );

		// HTML5.
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
		);

		// Custom logo.
		add_theme_support(
			'custom-logo',
			array(
				'flex-width'  => true,
				'flex-height' => true,
			)
		);

		// Editor styles.
		add_theme_support( 'editor-styles' );
		add_theme_support( 'responsive-embeds' );

		// WooCommerce.
		add_theme_support( 'woocommerce' );
		// Zoom (loupe au survol de l'image de la galerie) retiré volontairement
		// (itération 2026-06) : effet indésirable sur la fiche produit. Lightbox
		// (clic pour agrandir) et slider (swipe) conservés.
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );

		// Image sizes.
		add_image_size( 'recipe-hero', 1600, 900, true );
		add_image_size( 'recipe-card', 800, 600, true );
		add_image_size( 'recipe-thumb', 400, 300, true );

		// Menus. Tous les emplacements admin-ables ; chaque template a un
		// fallback hardcodé (cf. inc/menus.php) quand un menu n'est pas affecté.
		register_nav_menus(
			array(
				'primary'         => __( 'Header — Navigation principale', '180c' ),
				'side'            => __( 'Side menu — Navigation principale', '180c' ),
				'footer-discover' => __( 'Footer — Découvrir 180°C', '180c' ),
				'footer-editions' => __( 'Footer — Maison d’édition', '180c' ),
				'footer-help'     => __( 'Footer — Aide & contact', '180c' ),
				'legals'          => __( 'Footer — Mentions légales', '180c' ),
			)
		);

		// Translations.
		load_theme_textdomain( '180c', _180C_THEME_DIR . '/languages' );
	}
);

/**
 * Ajoute des classes CSS au <body>.
 *
 * - `legal` : pages légales (mentions légales, confidentialité, CGV, cookies),
 *   pour un habillage sobre dédié (voir src/css/components/legal.css).
 *   Sans cette classe, `.entry-content` conserve le `margin-inline: auto` de
 *   single.css et le texte paraît centré dans son conteneur — c'est ce qui
 *   distinguait /cookies/ des trois autres pages légales à sa création.
 *
 * La classe `theme-mode-*` a été retirée : dérivée du cookie, elle était figée
 * dans le HTML par WP Super Cache exactement comme l'attribut `data-theme` de
 * header.php, et donc fausse pour tout visiteur autre que celui qui avait
 * régénéré le cache. Aucun sélecteur CSS ni aucun module JS ne la consommait
 * (vérifié sur l'ensemble de src/ et des templates) : la supprimer ne change
 * rien au rendu. Le thème est désormais résolu côté client (cf. header.php).
 *
 * @param string[] $classes Classes body existantes.
 * @return string[] Classes body augmentées.
 */
add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_page( array( 'mentions-legales', 'politique-confidentialite', 'cgv', 'cookies' ) ) ) {
			$classes[] = 'legal';
		}

		return $classes;
	}
);
