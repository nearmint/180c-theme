<?php
/**
 * Template Name: Page À propos
 * Template Post Type: page
 *
 * Page éditoriale « Qui sommes-nous » — entièrement administrable en
 * ACF : manifeste (hero + WYSIWYG), sections libres (Flexible Content) et grille
 * de contributeurs reliée aux fiches auteurs `/author/`.
 *
 * Le Template Name permet d'affecter ce template à la page existante au slug
 * `/qui-sommes-nous/` sans en changer l'URL (préservation SEO / liens entrants).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="about">
	<?php
	get_template_part( 'template-parts/about/intro' );
	get_template_part( 'template-parts/about/sections' );
	?>
</main>

<?php get_footer(); ?>
