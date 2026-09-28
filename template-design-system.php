<?php
/**
 * Template Name: Design System
 *
 * Référentiel Design System web de 180c-theme.
 *
 * Une seule page-template, affectée à 3 pages (parent + 2 enfants) ; le partial
 * de section est choisi d'après le slug de la page courante :
 *   - design-system (parent) → welcome
 *   - foundations            → foundations (tokens rendus depuis tokens.css)
 *   - components             → components (composants rendus via leurs partials)
 *
 * Accès : réservé aux administrateurs (manage_options). Indexation : forcée à
 * noindex,nofollow (cf. filtre wp_robots ci-dessous).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// --- Garde d'accès : admins uniquement. ---
if ( ! current_user_can( 'manage_options' ) ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

// --- SEO : forcer noindex,nofollow sur les pages Design System. ---
add_filter(
	'wp_robots',
	function ( $robots ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
		return $robots;
	}
);

// --- Routage par slug de la page courante. ---
$_180c_ds_queried = get_queried_object();
$_180c_ds_slug    = ( $_180c_ds_queried instanceof WP_Post ) ? $_180c_ds_queried->post_name : '';

switch ( $_180c_ds_slug ) {
	case 'foundations':
		$_180c_ds_section = 'foundations';
		break;
	case 'components':
		$_180c_ds_section = 'components';
		break;
	default:
		$_180c_ds_section = 'welcome';
		break;
}

get_header();
?>

<div class="ds">
	<?php get_template_part( 'template-parts/ds/nav', null, array( 'current' => $_180c_ds_section ) ); ?>

	<main id="main" class="ds__content site-main" tabindex="-1">
		<?php get_template_part( 'template-parts/ds/' . $_180c_ds_section ); ?>
	</main>
</div>

<?php
get_footer();
