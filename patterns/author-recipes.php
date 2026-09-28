<?php
/**
 * Pattern PHP — section « Ses recettes » de la page auteur.
 *
 * Grille paginée (12 par page) des recettes (CPT recipe) signées par
 * l'auteur courant. La pagination utilise un query var dédié
 * `recipes_page` pour rester indépendante de la section « articles »
 * sur la même URL.
 *
 * La section est entièrement masquée si l'auteur n'a aucune recette publiée.
 *
 * Inclus depuis author.php via get_template_part( 'patterns/author-recipes' ).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$author_id = (int) get_queried_object_id();
if ( ! $author_id ) {
	return;
}

$articles_page = _180c_author_articles_paged();
$recipes_page  = _180c_author_recipes_paged();

$recipes = _180c_author_recipes_query( $author_id, $recipes_page );

if ( ! $recipes->have_posts() ) {
	wp_reset_postdata();
	return;
}

$first_name = _180c_author_first_name( $author_id );
$total      = (int) $recipes->max_num_pages;

// Préservation : si on est paginé sur les articles, on garde ce paramètre.
$preserve = array();
if ( $articles_page > 1 ) {
	$preserve['articles_page'] = $articles_page;
}
?>

<section
	id="recettes"
	class="author-section author-section--recipes"
	aria-labelledby="author-recipes-title"
	data-author-section="recipe"
	data-author-id="<?php echo esc_attr( $author_id ); ?>"
	data-current-page="<?php echo esc_attr( $recipes_page ); ?>"
	data-max-pages="<?php echo esc_attr( $total ); ?>"
>
	<div class="container">
		<h2 id="author-recipes-title" class="author-section__title">
			<?php
			printf(
				/* translators: %s: prénom de l'auteur */
				esc_html__( 'Les recettes de %s', '180c' ),
				esc_html( $first_name )
			);
			?>
		</h2>

		<ul class="author-section__grid" role="list" data-author-section-grid aria-live="polite">
			<?php while ( $recipes->have_posts() ) : ?>
				<?php $recipes->the_post(); ?>
				<li class="author-section__item">
					<?php
					get_template_part(
						'parts/recipe-card',
						null,
						array(
							'recipe_id' => get_the_ID(),
							'size'      => 'md',
						)
					);
					?>
				</li>
			<?php endwhile; ?>
		</ul>

		<?php
		$pagination_html = _180c_author_render_pagination( 'recipes', $recipes_page, $total, $author_id, $preserve );
		if ( '' !== $pagination_html ) {
			// _180c_render_pagination retourne déjà le <nav aria-label="…">
			// stylé via .pagination — pas de wrapper supplémentaire ici.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit par le helper avec esc_attr en amont.
			echo $pagination_html;
		}
		?>
	</div>
</section>

<?php wp_reset_postdata(); ?>
