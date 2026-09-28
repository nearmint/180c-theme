<?php
/**
 * Template part — Module home : Mon carnet de recettes.
 *
 * Sous-champs ACF :
 *   - carnet_title (text)
 *   - carnet_count (number) — nb max de favoris affichés
 *   - carnet_empty_text (textarea) — état vide (abonné sans favori)
 *
 * Réservé aux abonnés. Deux états, branchés AVANT toute requête favoris
 * (aucune donnée premium exposée à un visiteur sans accès) :
 *   1. Abonné connecté avec favoris → rail mutualisé (_180c_render_rail).
 *   2. Abonné connecté sans favori → état vide gracieux.
 *
 * Visiteur sans accès (non connecté ou non abonné) → le module ne rend rien :
 * l'incitation à l'abonnement est portée par le module « Bannière abonnement »
 * (parts/modules/subscription_banner.php).
 *
 * Les favoris (lecture) viennent de _180c_get_user_favorite_ids();
 * appel gardé par function_exists pour rester sûr si le module favoris n'est
 * pas chargé. Les favoris N'alimentent PAS l'accumulateur de dédoublonnage
 * (_180c_displayed_recipe_ids) : ce sont des données personnelles, pas du
 * contenu éditorial partagé.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks/_helpers.php';

$title      = get_sub_field( 'carnet_title' ) ?: __( 'Mon carnet de recettes', '180c' );
$count      = (int) ( get_sub_field( 'carnet_count' ) ?: 10 );
$empty_text = get_sub_field( 'carnet_empty_text' );

$is_subscriber = function_exists( '_180c_is_recipe_subscriber' ) && _180c_is_recipe_subscriber();

/*
 * Visiteur sans accès (non connecté ou non abonné) : le carnet ne rend RIEN
 * (plus de fallback CTA). L'incitation à l'abonnement est désormais portée par
 * le module dédié « Bannière abonnement » (parts/modules/subscription_banner.php).
 * Aucune requête de favoris n'est exécutée, aucune donnée premium n'est exposée.
 */
if ( ! $is_subscriber ) {
	return;
}

/*
 * État 2 — abonné connecté : rail de ses recettes favorites.
 */
$favorite_ids = array();
if ( function_exists( '_180c_get_user_favorite_ids' ) ) {
	$favorite_ids = _180c_get_user_favorite_ids();
	if ( $count > 0 ) {
		$favorite_ids = array_slice( $favorite_ids, 0, $count );
	}
}

$items_html = array();
foreach ( $favorite_ids as $favorite_id ) {
	$recipe = get_post( (int) $favorite_id );
	if ( $recipe instanceof WP_Post && 'recipe' === $recipe->post_type && 'publish' === $recipe->post_status ) {
		$card = _180c_block_render_recipe_card( $recipe, 'sm' );
		if ( $card ) {
			$items_html[] = $card;
		}
	}
}

if ( ! empty( $items_html ) ) {
	// « Voir tout » → page « Mon carnet » (favoris). Résolue par slug, avec
	// repli filtrable sur /mon-carnet/.
	$carnet_page = get_page_by_path( 'mon-carnet' );
	$carnet_url  = $carnet_page instanceof WP_Post ? get_permalink( $carnet_page ) : home_url( '/mon-carnet/' );
	$carnet_url  = apply_filters( '180c/carnet_url', $carnet_url );

	_180c_render_rail(
		array(
			'title'          => $title,
			'items_html'     => $items_html,
			'view_all_url'   => $carnet_url,
			'view_all_label' => __( 'Voir mon carnet', '180c' ),
			'region_label'   => $title,
			'modifier'       => 'carnet',
		)
	);
	return;
}

/*
 * État 3 — abonné connecté sans favori : état vide gracieux (jamais piégeant).
 */
$archive_url = get_post_type_archive_link( 'recipe' );
?>
<section class="home-module carnet carnet--empty" aria-labelledby="carnet-empty-title">
	<div class="container-180c">
		<h2 id="carnet-empty-title" class="carnet__title"><?php echo esc_html( $title ); ?></h2>
		<?php if ( $empty_text ) : ?>
			<p class="carnet__empty-text"><?php echo esc_html( wp_strip_all_tags( $empty_text ) ); ?></p>
		<?php endif; ?>
		<?php if ( $archive_url ) : ?>
			<a class="btn btn--secondary carnet__browse" href="<?php echo esc_url( $archive_url ); ?>">
				<?php esc_html_e( 'Parcourir les recettes', '180c' ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
