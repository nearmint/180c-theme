<?php
/**
 * Pattern PHP — section « Ses articles » de la page auteur.
 *
 * Grille paginée (12 par page) des articles (post natifs) signés par
 * l'auteur courant. La pagination utilise un query var dédié
 * `articles_page` pour rester indépendante de la section « recettes »
 * sur la même URL.
 *
 * La section est entièrement masquée si l'auteur n'a aucun article publié.
 *
 * Inclus depuis author.php via get_template_part( 'patterns/author-articles' ).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$author_id = (int) get_queried_object_id();
if ( ! $author_id ) {
	return;
}

// Exception éditoriale : certains auteurs n'exposent que leurs recettes.
if ( _180c_author_hides_articles( $author_id ) ) {
	return;
}

$articles_page = _180c_author_articles_paged();
$recipes_page  = _180c_author_recipes_paged();

$articles = _180c_author_articles_query( $author_id, $articles_page );

if ( ! $articles->have_posts() ) {
	wp_reset_postdata();
	return;
}

$first_name = _180c_author_first_name( $author_id );
$total      = (int) $articles->max_num_pages;

// Préservation : si on est paginé sur les recettes, on garde ce paramètre.
$preserve = array();
if ( $recipes_page > 1 ) {
	$preserve['recipes_page'] = $recipes_page;
}
?>

<section
	id="articles"
	class="author-section author-section--articles"
	aria-labelledby="author-articles-title"
	data-author-section="post"
	data-author-id="<?php echo esc_attr( $author_id ); ?>"
	data-current-page="<?php echo esc_attr( $articles_page ); ?>"
	data-max-pages="<?php echo esc_attr( $total ); ?>"
>
	<div class="container">
		<h2 id="author-articles-title" class="author-section__title">
			<?php
			printf(
				/* translators: %s: prénom de l'auteur */
				esc_html__( 'Les articles de %s', '180c' ),
				esc_html( $first_name )
			);
			?>
		</h2>

		<ul class="author-section__grid" role="list" data-author-section-grid aria-live="polite">
			<?php while ( $articles->have_posts() ) : ?>
				<?php $articles->the_post(); ?>
				<li class="author-section__item">
					<?php
					get_template_part(
						'parts/article-card',
						null,
						array(
							'post_id' => get_the_ID(),
							'size'    => 'md',
						)
					);
					?>
				</li>
			<?php endwhile; ?>
		</ul>

		<?php
		$pagination_html = _180c_author_render_pagination( 'articles', $articles_page, $total, $author_id, $preserve );
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
