<?php
/**
 * 404 personnalisée.
 *
 * Affiche : titre, description, formulaire de recherche,
 * 3 recettes récentes + lien retour accueil.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main site-container error-404">

	<div class="error-404__hero container-180c">
		<h1 class="error-404__title"><?php esc_html_e( 'Page introuvable', '180c' ); ?></h1>
		<p class="error-404__description">
			<?php esc_html_e( 'Cette page n\'existe plus ou a été déplacée.', '180c' ); ?>
		</p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="error-404__back btn btn--primary">
			<?php esc_html_e( 'Retour à l\'accueil', '180c' ); ?>
		</a>
	</div>

	<div class="error-404__search container-180c">
		<h2 class="error-404__section-title"><?php esc_html_e( 'Rechercher sur le site', '180c' ); ?></h2>
		<?php
		// Formulaire de recherche canonique (même partial que le side menu et la
		// page de résultats) — pas le formulaire HTML5 par défaut de WordPress.
		get_template_part( 'parts/search-form' );
		?>
	</div>

	<?php
	// Rail recettes mutualisé avec la home (même shell _180c_render_rail + même
	// renderer de carte). Le shell pose son propre .container-180c et son h2.
	require_once get_template_directory() . '/inc/blocks/_helpers.php';

	$recent_recipes = new WP_Query(
		array(
			'post_type'      => 'recipe',
			'posts_per_page' => 8,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		)
	);

	$items_html = array();
	while ( $recent_recipes->have_posts() ) {
		$recent_recipes->the_post();
		$card = _180c_block_render_recipe_card( get_post(), 'sm' );
		if ( $card ) {
			$items_html[] = $card;
		}
	}
	wp_reset_postdata();

	if ( ! empty( $items_html ) && function_exists( '_180c_render_rail' ) ) {
		// « Voir tout » → page Recettes éditoriale (Home Builder), ancre grille,
		// même cible que le rail recettes de la home.
		$recettes     = get_page_by_path( 'recettes' );
		$base         = $recettes instanceof WP_Post ? get_permalink( $recettes ) : home_url( '/recettes/' );
		$view_all_url = $base . '#grille-recettes';

		_180c_render_rail(
			array(
				'title'        => __( 'Recettes à découvrir', '180c' ),
				'items_html'   => $items_html,
				'view_all_url' => $view_all_url,
				'region_label' => __( 'Recettes à découvrir', '180c' ),
				'modifier'     => 'recipes_rail',
			)
		);
	}
	?>

</main>

<?php
get_footer();
