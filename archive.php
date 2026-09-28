<?php
/**
 * Template d'archive générique — repli de la hiérarchie WP.
 *
 * Sert toutes les archives de taxonomie sans template dédié (category,
 * post_tag, recipe_category, recipe_season, recipe_publication, recipe_tag,
 * product_brand) et les archives de date. Remplace le rendu vide qui résultait
 * du repli sur index.php (parts/content* inexistants) et corrige le bug des
 * filtres de archive-recipe.php qui pointaient vers des pages blanches
 * (/saison/{term}, /publication/{term}).
 *
 * Composants partagés (Phase 1) : section-header, archive-grid, page-shell,
 * empty-state, _180c_render_pagination(). Logique dans inc/archives.php.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ctx = _180c_archives_context();

// CTA d'état vide contextuel : recettes → archive recettes, sinon accueil.
if ( is_tax( array( 'recipe_category', 'recipe_season', 'recipe_publication', 'recipe_tag' ) ) ) {
	$empty_cta_url   = get_post_type_archive_link( 'recipe' );
	$empty_cta_label = __( 'Voir toutes les recettes', '180c' );
} else {
	$empty_cta_url   = home_url( '/' );
	$empty_cta_label = __( 'Retour à l’accueil', '180c' );
}
?>

<main id="main" class="site-main page-shell archive">

	<?php
	get_template_part(
		'parts/section-header',
		null,
		array(
			'eyebrow'     => $ctx['eyebrow'],
			'title'       => $ctx['title'],
			'description' => $ctx['description'],
		)
	);
	?>

	<div class="container">
		<?php if ( have_posts() ) : ?>

			<ul class="archive-grid" role="list">
				<?php
				while ( have_posts() ) :
					the_post();
					$card_html = _180c_archives_render_card( get_the_ID() );
					if ( '' === $card_html ) {
						continue;
					}
					?>
					<li class="archive-grid__item">
						<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans les helpers de carte. ?>
					</li>
				<?php endwhile; ?>
			</ul>

			<?php
			echo _180c_render_pagination( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit par le helper (esc_attr en amont).
				array(
					'base'       => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
					'format'     => '?paged=%#%',
					'current'    => max( 1, (int) get_query_var( 'paged' ) ),
					'total'      => (int) $GLOBALS['wp_query']->max_num_pages,
					/* translators: %s: titre de l'archive courante. */
					'aria_label' => sprintf( __( 'Pagination : %s', '180c' ), $ctx['title'] ),
				)
			);
			?>

		<?php else : ?>

			<?php
			get_template_part(
				'parts/empty-state',
				null,
				array(
					'title'     => __( 'Rien à afficher pour le moment', '180c' ),
					'message'   => __( 'Cette page ne contient aucun contenu publié pour l’instant.', '180c' ),
					'cta_url'   => $empty_cta_url,
					'cta_label' => $empty_cta_label,
				)
			);
			?>

		<?php endif; ?>
	</div>

</main>

<?php
get_footer();
