<?php
/**
 * Single product content — surcharge 180°C.
 *
 * Layout éditorial de la fiche produit. Reprend la structure WooCommerce
 * (hooks natifs préservés : galerie, prix, ajout panier, meta, structured
 * data) mais :
 *   - enveloppe galerie + résumé dans `.product__top` (2 colonnes ≥ md,
 *     colonne d'achat collante — voir components/woo.css `.product-page`) ;
 *   - insère la cross-promo abonnement numérique (produits physiques) ;
 *   - rend la description longue + FAQ en sections empilées (les onglets WC
 *     sont retirés dans inc/woo/single-product.php) ;
 *   - conserve upsells / related (rendus en rails via leurs surcharges).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}
// Le fil d'Ariane est rendu globalement juste au-dessus du footer
// (footer.php → _180c_render_breadcrumb()), pas en tête de fiche.
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>

	<div class="product__top">
		<?php
		/**
		 * Hook: woocommerce_before_single_product_summary.
		 *
		 * @hooked woocommerce_show_product_sale_flash - 10
		 * @hooked woocommerce_show_product_images - 20 (galerie native, non croppée)
		 */
		do_action( 'woocommerce_before_single_product_summary' );
		?>

		<div class="summary entry-summary">
			<?php
			/**
			 * Hook: woocommerce_single_product_summary.
			 *
			 * @hooked woocommerce_template_single_title - 5
			 * @hooked woocommerce_template_single_price - 10
			 * @hooked woocommerce_template_single_excerpt - 20
			 * @hooked woocommerce_template_single_add_to_cart - 30
			 * @hooked _180c_product_reassurance - 35 (inc/woo/single-product.php)
			 * @hooked woocommerce_template_single_meta - 40
			 * @hooked woocommerce_template_single_sharing - 50
			 */
			do_action( 'woocommerce_single_product_summary' );
			?>
		</div>
	</div>

	<?php
	/*
	 * Cross-promo abonnement numérique retirée de la fiche produit (itération
	 * 2026-06). Le partial parts/product-digital-crosspromo.php reste sur disque
	 * pour la fiche du Design System (template-parts/ds/components/misc.php) mais
	 * n'est plus câblé ici.
	 */
	?>

	<?php
	/*
	 * Onglets fiche produit (Description / Reportages / Recettes / Informations
	 * techniques). Remplace le rendu inline de la description longue : la
	 * Description devient le premier onglet (toujours présent), les autres
	 * onglets s'affichent selon le gating métier (inc/woo/product-tabs-data.php).
	 * Wrapper de largeur/centrage géré par .product-page .product-tabs (woo).
	 */
	if ( $product instanceof WC_Product ) {
		_180c_render_product_tabs( $product );
	}
	?>

	<?php
	/**
	 * Hook: woocommerce_after_single_product_summary.
	 *
	 * Onglets WC natifs retirés (inc/woo/single-product.php) ; onglets 180°C rendus ci-dessus.
	 *
	 * @hooked woocommerce_upsell_display - 15 (rail, cappé)
	 * @hooked woocommerce_output_related_products - 20 (rail)
	 */
	do_action( 'woocommerce_after_single_product_summary' );
	?>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
