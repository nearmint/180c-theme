<?php
/**
 * Pages pièces jointes — redirection 301.
 *
 * Les pages d'attachement n'ont aucune valeur éditoriale. Sans ce template,
 * elles retombent sur single.php (le gabarit article) et servent un
 * contenu mince indexable au rendu incohérent. On redirige plutôt vers le post
 * parent, ou à défaut vers le fichier média lui-même.
 *
 * (wp_attachment_pages_enabled = 1 sur cette instance, confirmé en Site Shell.)
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_attachment_id = get_queried_object_id();
$_180c_parent_id     = wp_get_post_parent_id( $_180c_attachment_id );
$_180c_target        = $_180c_parent_id
	? get_permalink( $_180c_parent_id )
	: wp_get_attachment_url( $_180c_attachment_id );

if ( $_180c_target ) {
	wp_safe_redirect( $_180c_target, 301 );
	exit;
}

// Repli improbable (ni parent, ni URL de fichier) : rendu minimal non vide.
get_header();
?>
<main id="main" class="site-main page-shell"></main>
<?php
get_footer();
