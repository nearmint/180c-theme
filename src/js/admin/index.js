/**
 * Entrée JS éditeur Gutenberg — enregistrement des blocs custom 180°C.
 *
 * Le thème n'embarque pas les paquets @wordpress/* (build Vite, pas
 * @wordpress/scripts) : on consomme donc les globales `window.wp.*` exposées
 * par WordPress dans l'éditeur (dépendances déclarées à l'enqueue dans
 * inc/enqueue.php : wp-blocks, wp-element, wp-block-editor, wp-components,
 * wp-i18n, wp-server-side-render).
 *
 * Les blocs sont enregistrés côté serveur (block.json + render.php, glob dans
 * inc/bootstrap.php) ; ce script ajoute uniquement l'expérience d'édition
 * (aperçu serveur live + réglages d'attributs). Pour rester robuste quel que
 * soit l'état d'auto-enregistrement client de WordPress, on désenregistre un
 * éventuel type déjà présent avant de (ré)enregistrer notre définition.
 *
 * Parité éditeur du stylage DS des blocs de contenu (blocs-gutenberg-ds v2) :
 * on importe les tokens + la feuille `content-blocks.css` (double-scopée
 * `.editor-styles-wrapper`) pour que l'aperçu d'édition reflète le rendu front
 * sur le STATIQUE (puces jaunes, citation DS, h2 --text-2xl, radius image,
 * légende non-italique). WP 6.3+ charge les assets `enqueue_block_editor_assets`
 * dans l'iframe de l'éditeur → ces règles s'appliquent au canevas.
 */

import '../../css/tokens.css';
import '../../css/components/content-blocks.css';

( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.components ) {
		return;
	}

	const { registerBlockType, getBlockType, unregisterBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const blockEditor = wp.blockEditor || wp.editor || {};
	const { InspectorControls } = blockEditor;
	const { PanelBody, TextControl, RangeControl } = wp.components;
	const ServerSideRender = wp.serverSideRender || null;
	const __ = ( wp.i18n && wp.i18n.__ ) ? wp.i18n.__ : ( s ) => s;

	/**
	 * Aperçu serveur live du bloc (ou message de repli si SSR indisponible).
	 *
	 * @param {string} name       Nom du bloc (180c/...).
	 * @param {Object} attributes Attributs courants.
	 * @return {Object} Élément React.
	 */
	const preview = ( name, attributes ) =>
		ServerSideRender
			? el( ServerSideRender, { block: name, attributes } )
			: el( 'p', { className: 'block-180c-ssr-fallback' }, __( 'Aperçu disponible côté public.', '180c' ) );

	/**
	 * (Ré)enregistre un bloc de façon idempotente.
	 *
	 * @param {string} name     Nom du bloc.
	 * @param {Object} settings Réglages (title, category, attributes, edit, save…).
	 */
	const define = ( name, settings ) => {
		if ( getBlockType( name ) ) {
			unregisterBlockType( name );
		}
		registerBlockType( name, settings );
	};

	/* ------------------------------------------------------------------ *
	 * 180c/product-highlight — mise en avant produit
	 * ------------------------------------------------------------------ */
	define( '180c/product-highlight', {
		apiVersion: 3,
		title: __( 'Mise en avant produit', '180c' ),
		category: '180c',
		icon: 'star-filled',
		description: __( 'Met en avant un produit WooCommerce (image, titre, prix, CTA).', '180c' ),
		supports: { html: false, anchor: true, align: [ 'wide' ] },
		attributes: {
			product_id: { type: 'number' },
			sku: { type: 'string', default: '' },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Produit', '180c' ), initialOpen: true },
						el( TextControl, {
							label: __( 'ID du produit', '180c' ),
							type: 'number',
							value: attributes.product_id || '',
							onChange: ( v ) =>
								setAttributes( { product_id: v ? parseInt( v, 10 ) : undefined } ),
						} ),
						el( TextControl, {
							label: __( 'SKU (repli si pas d’ID)', '180c' ),
							value: attributes.sku || '',
							onChange: ( v ) => setAttributes( { sku: v } ),
						} )
					)
				),
				preview( '180c/product-highlight', attributes )
			);
		},
		save() {
			return null;
		},
	} );

	/* ------------------------------------------------------------------ *
	 * 180c/products-rail — rail produit horizontal
	 * ------------------------------------------------------------------ */
	define( '180c/products-rail', {
		apiVersion: 3,
		title: __( 'Rail de produits', '180c' ),
		category: '180c',
		icon: 'cart',
		description: __( 'Rail horizontal de produits WooCommerce (IDs ou catégorie).', '180c' ),
		supports: { html: false, anchor: true, align: [ 'wide', 'full' ] },
		attributes: {
			title: { type: 'string', default: '' },
			ids: { type: 'string', default: '' },
			cat: { type: 'string', default: '' },
			limit: { type: 'number', default: 8 },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Rail produit', '180c' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Titre', '180c' ),
							value: attributes.title || '',
							onChange: ( v ) => setAttributes( { title: v } ),
						} ),
						el( TextControl, {
							label: __( 'IDs produits (séparés par des virgules)', '180c' ),
							help: __( 'Prioritaire sur la catégorie. Ordre respecté.', '180c' ),
							value: attributes.ids || '',
							onChange: ( v ) => setAttributes( { ids: v } ),
						} ),
						el( TextControl, {
							label: __( 'Catégorie produit (slug)', '180c' ),
							help: __( 'Utilisée seulement si aucun ID n’est renseigné.', '180c' ),
							value: attributes.cat || '',
							onChange: ( v ) => setAttributes( { cat: v } ),
						} ),
						el( RangeControl, {
							label: __( 'Nombre maximum', '180c' ),
							value: attributes.limit || 8,
							min: 1,
							max: 24,
							onChange: ( v ) => setAttributes( { limit: v || 8 } ),
						} )
					)
				),
				preview( '180c/products-rail', attributes )
			);
		},
		save() {
			return null;
		},
	} );

	/* ------------------------------------------------------------------ *
	 * 180c/recipes-slider — slider des dernières recettes
	 * ------------------------------------------------------------------ */
	define( '180c/recipes-slider', {
		apiVersion: 3,
		title: __( 'Slider de recettes', '180c' ),
		category: '180c',
		icon: 'images-alt2',
		description: __( 'Les dernières recettes publiées, une en vedette à la fois.', '180c' ),
		supports: { html: false, anchor: true, align: [ 'wide', 'full' ] },
		attributes: {
			title: { type: 'string', default: '' },
			count: { type: 'number', default: 6 },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Slider de recettes', '180c' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Titre', '180c' ),
							help: __( 'Vide = « Les dernières recettes publiées ».', '180c' ),
							value: attributes.title || '',
							onChange: ( v ) => setAttributes( { title: v } ),
						} ),
						el( RangeControl, {
							label: __( 'Nombre de recettes', '180c' ),
							help: __( 'Une seule recette est visible à la fois : au-delà de 10 vignettes la frise de pagination devient illisible.', '180c' ),
							value: attributes.count || 6,
							min: 1,
							// Même plafond que le layout ACF et que le renderer PHP.
							max: 10,
							onChange: ( v ) => setAttributes( { count: v || 6 } ),
						} )
					)
				),
				preview( '180c/recipes-slider', attributes )
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
