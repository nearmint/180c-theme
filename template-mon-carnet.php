<?php
/**
 * Template Name: Mon carnet
 *
 * Page personnelle « Mon carnet de recettes » (favoris de l'utilisateur
 * connecté). Surface web dédiée du carnet de favoris, distincte de
 * l'onglet « Mes recettes favorites » de Mon Compte.
 *
 * Toute la logique de rendu (requête favoris, grille, pagination, état vide)
 * vit dans `_180c_render_mon_carnet()` (inc/favorites.php) pour rester thin et
 * testable, à l'image de search.php → `_180c_render_search_results()`.
 *
 * - Gating : réservée aux connectés. Un visiteur déconnecté est redirigé vers
 *   /connexion/?redirect_to=/mon-carnet/ avant toute sortie HTML.
 * - SEO : noindex, nofollow (contenu personnel), aucun JSON-LD.
 *
 * La Page WP « Mon carnet » et l'affectation de ce template se font en admin
 * (ou via wp-cli depuis le Site Shell) — non créées par code.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// Gating : réservé aux connectés (avant get_header(), donc avant tout output).
if ( ! is_user_logged_in() ) {
	wp_safe_redirect(
		add_query_arg(
			'redirect_to',
			rawurlencode( home_url( '/mon-carnet/' ) ),
			home_url( '/connexion/' )
		)
	);
	exit;
}

// noindex, nofollow — contenu personnel (enregistré avant wp_head).
add_filter(
	'180c/seo_robots',
	static function () {
		return 'noindex, nofollow';
	}
);

get_header();

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le helper.
echo _180c_render_mon_carnet();

get_footer();
