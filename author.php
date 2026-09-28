<?php
/**
 * Template — page auteur.
 *
 * URL native WordPress : `/author/{nicename}/`. Affiche l'identité de
 * l'auteur (header) puis ses contenus en deux sections paginées
 * indépendamment : articles (post) + recettes (recipe).
 *
 * Le thème étant classique (pas FSE), on assemble en PHP : header global,
 * 3 patterns inclus via get_template_part(), footer global.
 *
 * 180°C ne porte ni notes / étoiles / commentaires : la page ne contient
 * aucun de ces éléments. Sections sans contenu = masquées (par les
 * patterns eux-mêmes). Auteur sans aucun contenu : seul le header
 * s'affiche.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main author page-shell">
	<?php
	get_template_part( 'patterns/author-header' );
	get_template_part( 'patterns/author-articles' );
	get_template_part( 'patterns/author-recipes' );
	get_template_part( 'patterns/author-related' );
	?>
</main>

<?php
get_footer();
