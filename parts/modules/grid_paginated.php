<?php
/**
 * Template part — Module home : Grille paginée.
 *
 * Sous-champs ACF :
 *   - title (text)
 *   - source (select : post|recipe|product)
 *   - columns (number 2-4)
 *   - rows (number 1-6)
 *   - exclude_displayed (true_false, source=recipe) — dédoublonnage inter-modules
 *
 * Grille ciblée (posts_per_page = columns × rows) avec pagination réelle.
 * La query var de pagination est suffixée par l'index du module
 * (`gp_{index}`, index fourni par _180c_render_home_modules via
 * `_180c_block_index`) afin que deux grilles coexistent sans collision.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks/_helpers.php';

$title   = get_sub_field( 'title' );
$source  = (string) ( get_sub_field( 'source' ) ?: 'post' );
$columns = max( 2, min( 4, (int) ( get_sub_field( 'columns' ) ?: 3 ) ) );
$rows    = max( 1, min( 6, (int) ( get_sub_field( 'rows' ) ?: 2 ) ) );

$allowed_sources = array( 'post', 'recipe', 'product' );
if ( ! in_array( $source, $allowed_sources, true ) || ! post_type_exists( $source ) ) {
	return;
}

$per_page  = $columns * $rows;
$block_idx = (int) get_query_var( '_180c_block_index' );
$query_var = 'gp_' . $block_idx;

// Pagination idempotente (GET en lecture seule) ; le cast (int) assainit.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged = isset( $_GET[ $query_var ] ) ? max( 1, (int) $_GET[ $query_var ] ) : 1;

$query_args = array(
	'post_type'           => $source,
	'post_status'         => 'publish',
	'posts_per_page'      => $per_page,
	'paged'               => $paged,
	'orderby'             => 'date',
	'order'               => 'DESC',
	'ignore_sticky_posts' => true,
);

// Dédoublonnage opt-in : exclut les recettes déjà rendues par les modules
// situés plus haut (source recette uniquement). Pagination réelle conservée.
if ( 'recipe' === $source && (bool) get_sub_field( 'exclude_displayed' ) ) {
	$already = _180c_displayed_recipe_ids();
	if ( ! empty( $already ) ) {
		$query_args['post__not_in'] = $already;
	}
}

$query = new WP_Query( $query_args );

if ( ! $query->have_posts() ) {
	return;
}

$anchor       = 'grid-paginated-' . $block_idx;
$region_label = $title ? $title : __( 'Grille', '180c' );

// Ancre stable « grille-recettes » : cible du lien « Voir tout » des rails
// recette (recipes_rail) lorsque cette grille recense les recettes sur la
// page Recettes (slug `recettes`). Émise EN PLUS de l'id dynamique (qui reste
// la clé de pagination anti-collision). L'offset header est géré globalement
// par html { scroll-padding-top } (main.css).
$stable_anchor = ( 'recipe' === $source && 'recettes' === get_post_field( 'post_name', get_queried_object_id() ) )
	? 'grille-recettes'
	: '';

$cards        = array();
$rendered_ids = array();
while ( $query->have_posts() ) {
	$query->the_post();
	switch ( $source ) {
		case 'recipe':
			$card = _180c_block_render_recipe_card( get_post(), 'md' );
			break;
		case 'product':
			$card = _180c_block_render_product_card( get_post() );
			break;
		case 'post':
		default:
			$card = _180c_block_render_article_card( get_post(), 'md' );
			break;
	}
	if ( $card ) {
		$cards[]        = $card;
		$rendered_ids[] = get_the_ID();
	}
}
wp_reset_postdata();

// Mémorise les recettes rendues pour le dédoublonnage des modules suivants.
if ( 'recipe' === $source && ! empty( $rendered_ids ) ) {
	_180c_displayed_recipe_ids( $rendered_ids );
}

if ( empty( $cards ) ) {
	return;
}

$pager = paginate_links(
	array(
		'base'      => esc_url_raw( add_query_arg( $query_var, '%#%' ) ) . '#' . $anchor,
		'format'    => '',
		'current'   => $paged,
		'total'     => (int) $query->max_num_pages,
		'add_args'  => false,
		'mid_size'  => 1,
		'prev_text' => __( 'Précédent', '180c' ),
		'next_text' => __( 'Suivant', '180c' ),
	)
);
?>

<section id="<?php echo esc_attr( $anchor ); ?>" class="home-module home-module--grid_paginated" aria-label="<?php echo esc_attr( $region_label ); ?>">
	<?php if ( $stable_anchor ) : ?>
		<span id="<?php echo esc_attr( $stable_anchor ); ?>" class="home-module__anchor" aria-hidden="true"></span>
	<?php endif; ?>
	<div class="container-180c">
		<?php
		// Lien « Voir toutes les recettes » → archive du post type recipe
		// (/toutes-les-recettes/). Pertinent uniquement pour une grille de
		// recettes : aligné à droite, au même niveau que le titre.
		$view_all_url = ( 'recipe' === $source ) ? get_post_type_archive_link( 'recipe' ) : '';
		?>
		<?php if ( $title || $view_all_url ) : ?>
			<div class="home-module__head">
				<?php if ( $title ) : ?>
					<h2 class="home-module__title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( $view_all_url ) : ?>
					<a class="home-module__view-all" href="<?php echo esc_url( $view_all_url ); ?>">
						<?php esc_html_e( 'Voir toutes les recettes', '180c' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php // `role="list"` CONSERVÉ : le <ul> est en display:grid avec list-style:none, combinaison qui fait perdre la sémantique de liste à VoiceOver/Safari. Le rôle explicite la rétablit. Les <li>, eux, restent en display:list-item : leur `role="listitem"` était redondant et a été retiré. ?>
		<ul class="grid-paginated__grid" role="list" style="--grid-paginated-columns: <?php echo esc_attr( (string) $columns ); ?>;">
			<?php foreach ( $cards as $card ) : ?>
				<li class="grid-paginated__item">
					<?php echo $card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML déjà échappé par les helpers de carte. ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $pager ) : ?>
			<nav class="grid-paginated__pager" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: grid title. */ __( 'Pagination : %s', '180c' ), $region_label ) ); ?>">
				<?php echo $pager; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() renvoie du HTML échappé. ?>
			</nav>
		<?php endif; ?>
	</div>
</section>
