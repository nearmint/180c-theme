<?php
/**
 * Portée du sous-champ « Visibilité » des modules home.
 *
 * Le groupe ACF « Page - Home modules » s'affiche sur plusieurs pages (accueil
 * du site, page Recettes, page Gazette…) via ses deux location rules
 * `page_type == front_page` et `page_template == template-home.php`. Chaque
 * layout du Flexible Content `home_modules` porte un sous-champ `display`
 * (« Visibilité » : Web uniquement / App uniquement / Web et App).
 *
 * Ce sous-champ n'a de sens que sur la page où se compose l'écran d'accueil des
 * apps, servi par la route REST `/180c/v1/home-recettes` : la page
 * `/recettes/`. Partout ailleurs il n'a aucun consommateur, et la seule valeur
 * qui y change quelque chose est `app_only` — qui masquerait le module sur le
 * web sans le rendre visible nulle part. On le retire donc de l'écran d'édition
 * de toutes les autres pages.
 *
 * Masquage par `acf/prepare_field` renvoyant `false` : le champ n'est pas rendu
 * du tout, donc pas soumis. Les valeurs déjà en base sont préservées —
 * `ACF_Field_Flexible_Content::update_row()` ignore explicitement (`continue`)
 * les sous-champs absents de la ligne postée, il ne les écrase pas.
 *
 * Le critère est le couple `_name` + `parent` plutôt qu'une liste figée de
 * clés : tout nouveau layout doté d'un sous-champ `display` est couvert
 * d'office, sans quoi il rouvrirait discrètement le champ sur toutes les pages.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pages sur lesquelles le sous-champ « Visibilité » reste administrable.
 *
 * La page `/recettes/`, et elle seule : c'est là que se compose l'écran
 * d'accueil des apps.
 *
 * Volontairement résolue ici, et non déduite de la page source de la route
 * `/180c/v1/home-recettes` : cette source a déjà changé une fois (fdb93f1, août
 * 2026) et l'écran d'édition ne doit pas se réorganiser dans le dos de la
 * rédaction à chaque déploiement de la route. Si la source REST redevient un
 * jour l'accueil du site, c'est ce fichier qu'il faut mettre à jour — ou le
 * filtre ci-dessous, sans toucher au thème.
 *
 * @return int[] IDs de pages, éventuellement vide si la page est introuvable.
 */
function _180c_home_modules_visibility_page_ids() {
	$ids  = array();
	$page = get_page_by_path( 'recettes' );

	if ( $page instanceof WP_Post ) {
		$ids[] = (int) $page->ID;
	}

	/**
	 * Filtre les pages où le sous-champ `display` des modules home est éditable.
	 *
	 * @param int[] $ids IDs résolus par défaut (page `/recettes/`).
	 */
	$ids = (array) apply_filters( '180c/home_modules_visibility_pages', $ids );

	return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
}

/**
 * Post en cours d'édition dans le formulaire ACF courant.
 *
 * `acf_get_form_data( 'post_id' )` est posé par `acf_form_data()` au hook
 * `edit_form_after_title`, donc avant le rendu des metaboxes : c'est la source
 * la plus fiable sur un écran d'édition. Repli sur le post global pour les
 * contextes où le formulaire n'a pas été initialisé (rendu AJAX isolé, etc.).
 * La valeur peut être non numérique (`options`, `user_1`…) : elle est alors
 * ignorée.
 *
 * @return int ID de post, ou 0 si indéterminé.
 */
function _180c_acf_edited_post_id() {
	if ( function_exists( 'acf_get_form_data' ) ) {
		$form_post_id = acf_get_form_data( 'post_id' );
		if ( is_numeric( $form_post_id ) ) {
			return (int) $form_post_id;
		}
	}

	$post = get_post();

	return $post instanceof WP_Post ? (int) $post->ID : 0;
}

/**
 * Retire le sous-champ « Visibilité » hors des pages qui l'exploitent.
 *
 * Hook : `acf/prepare_field` (renvoyer `false` annule le rendu du champ).
 * Volontairement « fail-open » : si le post édité n'est pas identifiable, ou si
 * la page `/recettes/` est introuvable, le champ reste affiché — mieux vaut un
 * champ inutile de trop que la perte de l'unique réglage qui pilote l'accueil
 * des apps.
 *
 * @param array|false $field Réglages du champ ACF courant.
 * @return array|false Champ inchangé, ou `false` pour ne pas le rendre.
 */
function _180c_hide_home_module_visibility_field( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}

	$name   = isset( $field['_name'] ) ? $field['_name'] : '';
	$parent = isset( $field['parent'] ) ? $field['parent'] : '';

	if ( 'display' !== $name || 'field_home_modules' !== $parent ) {
		return $field;
	}

	$allowed = _180c_home_modules_visibility_page_ids();
	if ( empty( $allowed ) ) {
		return $field;
	}

	$post_id = _180c_acf_edited_post_id();
	if ( $post_id <= 0 || in_array( $post_id, $allowed, true ) ) {
		return $field;
	}

	return false;
}
add_filter( 'acf/prepare_field', '_180c_hide_home_module_visibility_field' );
