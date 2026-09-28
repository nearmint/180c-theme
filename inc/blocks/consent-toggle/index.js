/**
 * Bloc one80c/consent-toggle — enregistrement côté éditeur.
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * Un bloc déclaré uniquement en PHP (block.json + render.php) se rend
 * parfaitement côté public, mais l'éditeur ne le connaît pas : son registre est
 * en JavaScript, et sans `editorScript` il n'y trouve rien. Le bloc devient
 * alors un `core/missing` — « ce bloc contient du contenu inattendu » — non
 * insérable, non déplaçable, et signalé comme cassé à chaque ouverture.
 *
 * Constaté le 2026-08-31 dans l'éditeur réel : les 18 blocs du thème sont dans
 * ce cas, aucun n'est exposé au registre JS.
 *
 * PAS DE JSX, PAS D'ÉTAPE DE BUILD
 * --------------------------------
 * Le thème n'a pas de chaîne @wordpress/scripts et src/js/blocks est vide. Ce
 * fichier est donc du JS lisible tel quel par le navigateur, via les globales
 * `wp.*` déclarées en dépendances dans index.asset.php. Ajouter une chaîne de
 * build pour un bloc sans attribut serait payer cher une commodité d'écriture.
 *
 * APERÇU STATIQUE PLUTÔT QUE ServerSideRender
 * -------------------------------------------
 * Le rendu réel s'appuie sur les classes `.consent__*` de
 * src/css/components/consent.css, qui n'est pas chargée dans l'éditeur. Un
 * ServerSideRender afficherait donc un aperçu dénudé, plus trompeur qu'utile —
 * et le styler supposerait de dupliquer ces règles dans une feuille d'éditeur,
 * c'est-à-dire d'en créer une seconde source. On affiche à la place ce que le
 * bloc EST, sans prétendre montrer ce qu'il DONNERA.
 *
 * @package 180c
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	const el = element.createElement;
	const __ = i18n.__;

	blocks.registerBlockType( 'one80c/consent-toggle', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el(
					components.Placeholder,
					{
						icon: 'privacy',
						label: __( 'Gestion des cookies', '180c' ),
						instructions: __(
							'Affiche l’interrupteur « Mesure d’audience » et le bouton « Enregistrer ». C’est le seul endroit du site où le visiteur revient sur son choix — le bandeau, lui, ne recueille que le choix initial.',
							'180c'
						),
					}
				)
			);
		},

		// Bloc dynamique : le markup est produit par render.php à l'affichage,
		// jamais sérialisé dans le contenu. Renvoyer autre chose que null y
		// figerait une copie qui divergerait au premier correctif.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
