<?php
/**
 * Partage social des recettes.
 *
 * Construit les URLs d'intent pour X, Facebook, WhatsApp, LinkedIn,
 * e-mail et un lien canonique « copier le lien ». L'URL partagée est toujours
 * publique (get_permalink), ce qui n'ouvre pas l'accès premium côté
 * destinataire — le paywall reste actif côté serveur.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retourne les liens de partage pour une recette donnée.
 *
 * Wrapper de back-compat (polish) : délègue à
 * `_180c_share_links()` (inc/share-actions.php), source de vérité neutre.
 * Le filtre historique `180c/recipe_share_links` est appliqué APRÈS pour
 * préserver les hooks éventuels déjà branchés.
 *
 * @param int $post_id Identifiant de la recette.
 * @return array<string,string> Clé = réseau (x, facebook, whatsapp, linkedin,
 *                              mail, link), valeur = URL d'intent.
 */
function _180c_recipe_share_links( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return array();
	}

	$url   = get_permalink( $post_id );
	$title = get_the_title( $post_id );

	if ( ! $url || '' === $title ) {
		return array();
	}

	$links = _180c_share_links( $url, $title );

	/**
	 * Permet de filtrer les liens de partage d'une recette.
	 *
	 * @param array<string,string> $links   Liens par réseau.
	 * @param int                  $post_id Identifiant de la recette.
	 */
	return (array) apply_filters( '180c/recipe_share_links', $links, $post_id );
}

/**
 * Libellés humains pour chaque réseau de partage.
 *
 * Wrapper de back-compat — délègue à `_180c_share_labels()` (source unique).
 *
 * @return array<string,string>
 */
function _180c_recipe_share_labels() {
	return _180c_share_labels();
}
