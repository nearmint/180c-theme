<?php
/**
 * Design System — section Components.
 *
 * Documentation de chaque composant inventorié (Phase 0), regroupée par lot
 * cohérent. Chaque lot est un partial template-parts/ds/components/<slug>.php.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_ds_lots = function_exists( '_180c_ds_component_lots' ) ? _180c_ds_component_lots() : array();
?>

<header class="ds-section__header">
	<h1 class="ds-section__title"><?php esc_html_e( 'Components', '180c' ); ?></h1>
	<p class="ds-section__lead"><?php esc_html_e( 'Composants du thème : aperçu, classe BEM racine, variantes et note d’usage. Badge « live » = vrai partial ; « snapshot » = HTML statique (contexte ACF/Woo/JS).', '180c' ); ?></p>
</header>

<?php foreach ( $_180c_ds_lots as $_180c_ds_lot_slug => $_180c_ds_lot_title ) : ?>
	<section class="ds-group" id="ds-lot-<?php echo esc_attr( $_180c_ds_lot_slug ); ?>" aria-labelledby="ds-lot-<?php echo esc_attr( $_180c_ds_lot_slug ); ?>-title">
		<h2 class="ds-group__title" id="ds-lot-<?php echo esc_attr( $_180c_ds_lot_slug ); ?>-title"><?php echo esc_html( $_180c_ds_lot_title ); ?></h2>
		<div class="ds-components">
			<?php get_template_part( 'template-parts/ds/components/' . $_180c_ds_lot_slug ); ?>
		</div>
	</section>
<?php endforeach; ?>
