<?php
/**
 * Template front_page — Home du site.
 *
 * Délègue au rendu mutualisé du builder de modules (Flexible Content
 * `home_modules`). Le même builder alimente la page « La Gazette » via
 * template-home.php.
 *
 * Le <h1> de la page est porté par ce template, jamais par un module : la
 * composition de la home est administrable, donc un <h1> délégué à un module
 * disparaît (ou se duplique) au gré des ajouts/retraits en back-office.
 * Constaté avant correctif : 1 <h1> en local (module hero_editorial présent),
 * 0 en production (module absent). Même modèle que template-home.php, où le
 * <h1> est rendu par gazette-header et les modules restent en <h2>.
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
	/*
	 * <h1> unique de l'accueil, masqué visuellement (`.screen-reader-text` :
	 * clip + 1×1px, cf. src/css/main.css — jamais `display:none`, qui ferait
	 * ignorer le titre par les moteurs). Le design de la home n'a pas de titre
	 * de page visible : décrire le site en clair ici est le seul moyen de
	 * donner un h1 substantiel sans toucher à la maquette.
	 */
	?>
	<h1 class="screen-reader-text">
		<?php esc_html_e( '180°C, la revue culture food : recettes de saison, reportages et portraits de producteurs', '180c' ); ?>
	</h1>

	<div class="home-modules">
		<?php _180c_render_home_modules(); ?>
	</div>
</main>

<?php
get_footer();
