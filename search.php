<?php
/**
 * Page de résultats de recherche (moteur natif WP).
 *
 * Vue unifiée recette → article → auteur. Toute la logique de rendu (modes
 * unifié / mono-type / état vide) vit dans _180c_render_search_results()
 * (inc/search.php) pour rester testable et réutilisable.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main site-container search-results">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le helper.
	echo _180c_render_search_results( get_search_query() );
	?>
</main>

<?php
get_footer();
