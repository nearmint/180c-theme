<?php
/**
 * Footer HTML.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;
?>

<?php
// Fil d'Ariane — point d'insertion global et unique, juste au-dessus du footer.
// La visibilité par contexte est gérée dans _180c_should_show_breadcrumb().
if ( function_exists( '_180c_render_breadcrumb' ) ) {
	_180c_render_breadcrumb();
}
?>

<footer id="site-footer" class="site-footer">
	<div class="site-container">
		<?php get_template_part( 'parts/footer/main' ); ?>
	</div>
</footer>

</div><!-- /#site-shell -->

<?php
/**
 * Overlays / fixed elements — HORS de #site-shell pour conserver leur
 * positionnement fixe (un parent transformé crée un containing block pour
 * les descendants en position:fixed, ce qui casse leur ancrage au viewport).
 */
?>
<?php get_template_part( 'parts/site-side-menu' ); ?>
<?php get_template_part( 'parts/header/cart-drawer' ); ?>
<?php get_template_part( 'parts/smart-banner' ); ?>
<?php get_template_part( 'parts/consent-modal' ); ?>
<?php get_template_part( 'parts/push-prompt' ); ?>

<?php wp_footer(); ?>
</body>
</html>
