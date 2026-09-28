<?php
/**
 * Restriction du champ ACF « Auteurs » (Select Author) par rôle.
 *
 * Le champ user `field_180c_authors_list` (groupe « À propos », layout
 * flexible `authors`) ne doit proposer que les comptes susceptibles d'être
 * auteurs éditoriaux : administrateurs, éditeurs, auteurs et contributeurs.
 * Le filtre est scopé à la key du champ (jamais global) afin de ne pas
 * affecter les autres champs user du thème (ex. WooCommerce).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Limite la requête du champ user « Auteurs » aux rôles rédactionnels.
 *
 * Hook : `acf/fields/user/query` (filtre les arguments de WP_User_Query
 * employés pour peupler le select). Renvoie les arguments inchangés pour
 * tout autre champ.
 *
 * @param array      $args    Arguments WP_User_Query préparés par ACF.
 * @param array      $field   Réglages du champ ACF courant.
 * @param int|string $post_id Contexte d'édition (non utilisé).
 * @return array Arguments éventuellement filtrés par rôle.
 */
function _180c_restrict_select_author_roles( $args, $field, $post_id ) {
	unset( $post_id );

	if ( empty( $field['key'] ) || 'field_180c_authors_list' !== $field['key'] ) {
		return $args;
	}

	$args['role__in'] = array( 'administrator', 'editor', 'author', 'contributor' );

	return $args;
}
add_filter( 'acf/fields/user/query', '_180c_restrict_select_author_roles', 10, 3 );
