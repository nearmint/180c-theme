<?php
/**
 * Fallback ultime de la hiérarchie WordPress.
 *
 * Théoriquement inatteignable : chaque contexte (front, single, page, archive,
 * taxonomie, auteur, recherche, 404) dispose d'un template plus spécifique
 * existant. Conservé par robustesse — réutilise le rendu de boucle d'archive.php
 * (cartes via _180c_archives_render_card(), grille .archive-grid, pagination
 * mutualisée, empty-state) afin de ne jamais rendre un <main> vide.
 *
 * Pas de section-header : le fallback n'a pas de contexte d'archive précis
 * (eyebrow/titre de terme) à afficher.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main page-shell">

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
					'aria_label' => __( 'Pagination', '180c' ),
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
					'cta_url'   => home_url( '/' ),
					'cta_label' => __( 'Retour à l’accueil', '180c' ),
				)
			);
			?>

		<?php endif; ?>
	</div>

</main>

<?php
get_footer();
