/**
 * Collage en texte brut dans les éditeurs wysiwyg de l'écran « Recette ».
 *
 * Les champs wysiwyg ACF placés dans un repeater (ici « Contenu » d'une étape,
 * field_180c_step_content) sont initialisés en JavaScript à l'ajout d'une ligne :
 * le filtre PHP tiny_mce_before_init ne couvre que les instances rendues côté
 * serveur. Ce filtre ACF complète la couverture pour toutes les instances,
 * y compris celles créées après le chargement de la page.
 *
 * Effet : TinyMCE dépouille le presse-papiers de tout formatage au collage
 * (comportement natif paste_as_text du plugin « paste »). Les sauts de ligne
 * deviennent des paragraphes ; la barre d'outils reste utilisable ensuite.
 * Aucun contenu déjà enregistré n'est modifié.
 *
 * Le script n'est chargé que sur les écrans du CPT recipe (cf. inc/admin/recipe-editor.php).
 */
( function () {
	'use strict';

	if ( typeof window.acf === 'undefined' || typeof window.acf.addFilter !== 'function' ) {
		return;
	}

	/**
	 * Force paste_as_text sur chaque instance TinyMCE initialisée par ACF.
	 *
	 * @param {Object} init  Réglages TinyMCE de l'instance.
	 * @return {Object} Réglages modifiés.
	 */
	window.acf.addFilter( 'wysiwyg_tinymce_settings', function ( init ) {
		init.paste_as_text = true;

		return init;
	} );
} )();
