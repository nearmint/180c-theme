<?php
/**
 * Template part — Module home : Tuiles de catégories.
 *
 * Sous-champs ACF :
 *   - title (text)
 *   - taxonomy (select : category|recipe_category|recipe_publication|product_cat|recipe_season)
 *   - count (number) — nb de termes, les plus fournis en priorité
 *   - columns (number) — colonnes desktop
 *
 * Affiche les termes d'une taxonomie sous forme de tuiles cliquables.
 * Image de tuile : term meta `thumbnail_id` (convention WooCommerce pour
 * product_cat ; absente pour category/recipe_category → fond token).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$title    = get_sub_field( 'title' );
$taxonomy = (string) ( get_sub_field( 'taxonomy' ) ?: 'category' );
$count    = (int) ( get_sub_field( 'count' ) ?: 8 );
$columns  = max( 2, min( 6, (int) ( get_sub_field( 'columns' ) ?: 4 ) ) );

$allowed_taxonomies = array( 'category', 'recipe_category', 'product_cat', 'recipe_publication', 'recipe_season' );
if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) || ! taxonomy_exists( $taxonomy ) ) {
	return;
}

$terms = get_terms(
	array(
		'taxonomy'   => $taxonomy,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => $count,
		'hide_empty' => true,
	)
);

if ( is_wp_error( $terms ) || empty( $terms ) ) {
	return;
}

/**
 * Libellé pluralisé du compteur de termes selon la taxonomie.
 *
 * @param string $taxo   Taxonomie courante.
 * @param int    $number Nombre de contenus du terme.
 * @return string Libellé traduit prêt à l'affichage.
 */
$count_label = static function ( $taxo, $number ) {
	switch ( $taxo ) {
		case 'recipe_category':
		case 'recipe_publication':
		case 'recipe_season':
			/* translators: %s: number of recipes. */
			return sprintf( _n( '%s recette', '%s recettes', $number, '180c' ), number_format_i18n( $number ) );
		case 'product_cat':
			/* translators: %s: number of products. */
			return sprintf( _n( '%s produit', '%s produits', $number, '180c' ), number_format_i18n( $number ) );
		default:
			/* translators: %s: number of articles. */
			return sprintf( _n( '%s article', '%s articles', $number, '180c' ), number_format_i18n( $number ) );
	}
};

$region_label = $title ? $title : __( 'Catégories', '180c' );
?>

<section class="home-module home-module--category_tiles" aria-label="<?php echo esc_attr( $region_label ); ?>">
	<div class="container-180c category-tiles">
		<?php if ( $title ) : ?>
			<h2 class="home-module__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>

		<?php // `role="list"` CONSERVÉ : le <ul> est en display:flex avec list-style:none, combinaison qui fait perdre la sémantique de liste à VoiceOver/Safari. Le rôle explicite la rétablit. Les <li>, eux, restent en display:list-item : leur `role="listitem"` était redondant et a été retiré. ?>
		<ul class="category-tiles__grid" role="list" style="--category-tiles-columns: <?php echo esc_attr( (string) $columns ); ?>;">
			<?php
			foreach ( $terms as $term ) :
				$term_link = get_term_link( $term );
				if ( is_wp_error( $term_link ) ) {
					continue;
				}
				?>
				<li class="category-tiles__item">
					<a class="category-tiles__tile" href="<?php echo esc_url( $term_link ); ?>">
						<span class="category-tiles__name"><?php echo esc_html( $term->name ); ?></span>
						<span class="category-tiles__count"><?php echo esc_html( $count_label( $taxonomy, (int) $term->count ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
