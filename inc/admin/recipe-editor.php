<?php
/**
 * Admin — écrans d'édition du CPT « recipe ».
 *
 * Deux conforts de saisie, tous deux motivés par le copier-coller depuis Word :
 * le collage en texte brut (ci-dessous) et le nettoyage de la numérotation des
 * titres d'étapes (assets/js/recipe-step-title.js, doublé côté serveur par
 * inc/acf/step-title-cleaner.php).
 *
 * Collage en texte brut dans les éditeurs wysiwyg : les recettes sont saisies
 * par copier-coller depuis Word, ce qui injectait jusqu'ici tout le balisage
 * Office (spans, styles inline, classes mso-*) dans le champ « Contenu » des
 * étapes (field_180c_step_content, sous-champ du repeater `steps`) et dans
 * l'éditeur natif du contenu. On force donc `paste_as_text` sur ces écrans :
 * TinyMCE dépouille le presse-papiers de tout formatage au moment du collage,
 * les sauts de ligne devenant des paragraphes.
 *
 * Couverture en deux temps, les deux étant nécessaires :
 *   - tiny_mce_before_init (PHP) pour les instances rendues côté serveur ;
 *   - filtre ACF `wysiwyg_tinymce_settings` (JS) pour celles initialisées
 *     dynamiquement, notamment à l'ajout d'une ligne dans le repeater.
 *
 * Périmètre strict : écrans d'édition du CPT recipe. Aucun impact sur les
 * autres types de contenu ni sur le front. Aucune modification du contenu déjà
 * enregistré : le comportement ne s'applique qu'au collage.
 *
 * Chargé uniquement en admin (cf. inc/bootstrap.php).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indique si l'écran admin courant est un écran d'édition de recette.
 *
 * @return bool
 */
function _180c_recipe_editor_is_recipe_screen(): bool {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	$screen = get_current_screen();

	return $screen instanceof WP_Screen && 'recipe' === $screen->post_type;
}

/**
 * Active le collage en texte brut sur les instances TinyMCE des écrans recette.
 *
 * Couvre l'éditeur natif du contenu et les champs wysiwyg ACF déjà présents au
 * rendu de la page. Les instances créées ensuite en JavaScript sont prises en
 * charge par assets/js/recipe-paste-as-text.js.
 *
 * @param array $init Réglages TinyMCE.
 * @return array Réglages modifiés.
 */
function _180c_recipe_editor_paste_as_text( $init ) {
	if ( ! is_array( $init ) || ! _180c_recipe_editor_is_recipe_screen() ) {
		return $init;
	}

	$init['paste_as_text'] = true;

	return $init;
}
add_filter( 'tiny_mce_before_init', '_180c_recipe_editor_paste_as_text' );

/**
 * Enqueue le script de collage brut sur les seuls écrans d'édition de recette.
 *
 * Asset versionné servi tel quel — aucun passage par Vite, aucune dépendance npm
 * (même pattern que inc/notifications/admin.php).
 *
 * @param string $hook Hook de la page admin courante.
 * @return void
 */
function _180c_recipe_editor_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	if ( ! _180c_recipe_editor_is_recipe_screen() ) {
		return;
	}

	// Dépendance `acf-input` : garantit que window.acf existe quand le script
	// s'exécute. Sans ACF, la dépendance manquante annule l'enqueue — et il n'y
	// aurait de toute façon aucun wysiwyg de repeater à couvrir.
	wp_enqueue_script(
		'180c-recipe-paste-as-text',
		get_template_directory_uri() . '/assets/js/recipe-paste-as-text.js',
		array( 'acf-input' ),
		defined( '_180C_VERSION' ) ? _180C_VERSION : null,
		true
	);

	// Nettoyage de la numérotation collée depuis Word dans « Titre de l'étape ».
	// Confort éditeur seulement : le nettoyage qui fait foi est le filtre
	// serveur d'inc/acf/step-title-cleaner.php.
	wp_enqueue_script(
		'180c-recipe-step-title',
		get_template_directory_uri() . '/assets/js/recipe-step-title.js',
		array( 'acf-input' ),
		defined( '_180C_VERSION' ) ? _180C_VERSION : null,
		true
	);
}
add_action( 'admin_enqueue_scripts', '_180c_recipe_editor_assets' );
