<?php
/**
 * Template Name: Home Builder
 *
 * Page éditoriale « La Gazette » (home du blog d'articles). Réutilise le
 * builder de modules home (Flexible Content `home_modules`) sur une page
 * dédiée. Le groupe ACF est exposé sur ce template via sa location rule
 * `page_template == template-home.php`.
 *
 * SEO/JSON-LD : title/description/canonical/robots/OG pilotés par les champs
 * ACF `group_content_seo` de la page ; le @graph reçoit un nœud CollectionPage
 * + Blog (cf. inc/seo/schema.php).
 *
 * @see _180c_render_home_modules() dans inc/home-modules.php
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main site-container">
	<?php
	while ( have_posts() ) :
		the_post();

		// Titre de page = unique <h1> de toutes les homes (Recettes, Gazette,
		// Boutique) ; les modules de tête (ex. hero_editorial) portent un
		// <h2>. Cohérence + un seul h1 par page.
		?>
		<header class="gazette-header container-180c">
			<div class="gazette-header__bar">
				<h1 class="gazette-header__title"><?php the_title(); ?></h1>
			</div>
		</header>

		<?php
		// Page « Recettes » : nav sticky révélée au scroll (recherche + accès
		// rapides). Le champ de recherche soumet vers la page de résultats
		// (query var native `s`, pré-filtrée sur le CPT recipe via `tab=recipe`,
		// cf. inc/search.php) — fonctionne sans JS. La révélation au scroll est
		// portée par src/js/modules/recipes-stickynav.js (IntersectionObserver
		// sur le header de page) ; sans JS la barre reste masquée (`hidden`).
		if ( is_page( 'recettes' ) ) :
			$_180c_recipes_archive_url = get_post_type_archive_link( 'recipe' );
			if ( ! $_180c_recipes_archive_url ) {
				$_180c_recipes_archive_url = home_url( '/toutes-les-recettes/' );
			}
			?>
			<nav
				class="recipes-stickynav"
				data-recipes-stickynav
				aria-label="<?php esc_attr_e( 'Rechercher et naviguer dans les recettes', '180c' ); ?>"
				hidden
			>
				<div class="container-180c recipes-stickynav__inner">
					<form
						class="search-form recipes-stickynav__search"
						role="search"
						method="get"
						action="<?php echo esc_url( home_url( '/' ) ); ?>"
						aria-label="<?php esc_attr_e( 'Rechercher une recette', '180c' ); ?>"
					>
						<label for="recipes-stickynav-search" class="search-form__label sr-only">
							<?php esc_html_e( 'Rechercher une recette', '180c' ); ?>
						</label>
						<input
							type="search"
							id="recipes-stickynav-search"
							class="search-form__input"
							name="s"
							value=""
							placeholder="<?php esc_attr_e( 'Rechercher une recette…', '180c' ); ?>"
							autocomplete="off"
						>
						<input type="hidden" name="tab" value="recipe">
						<button type="submit" class="search-form__submit">
							<svg class="search-form__icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
							<span class="sr-only"><?php esc_html_e( 'Lancer la recherche', '180c' ); ?></span>
						</button>
					</form>
					<div class="recipes-stickynav__actions">
						<a
							class="btn btn--ghost recipes-stickynav__action"
							href="<?php echo esc_url( $_180c_recipes_archive_url ); ?>"
						>
							<?php esc_html_e( 'Toutes les recettes', '180c' ); ?>
						</a>
						<a
							class="btn btn--ghost recipes-stickynav__action"
							href="<?php echo esc_url( home_url( '/mon-carnet/' ) ); ?>"
						>
							<?php esc_html_e( 'Mon carnet de recettes', '180c' ); ?>
						</a>
					</div>
				</div>
			</nav>
		<?php endif; ?>

		<div class="home-modules">
			<?php _180c_render_home_modules( get_the_ID() ); ?>
		</div>
		<?php
	endwhile;
	?>
</main>

<?php
get_footer();
