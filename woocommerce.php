<?php
/**
 * Wrapper WooCommerce — 180°C.
 *
 * Gabarit principal appliqué par WooCommerce aux surfaces qu'il contrôle
 * directement : fiche produit (`is_product()`), archive boutique (`is_shop()`)
 * et archives de taxonomies produit (`is_product_taxonomy()`).
 *
 * NB : le panier, la commande et « Mon compte » sont des Pages WordPress
 * classiques (shortcodes/blocs) rendues par `page.php`/`singular` — elles ne
 * passent pas par ce gabarit et ne sont donc pas impactées.
 *
 * Thème PHP classique (pas FSE) : on enveloppe simplement `woocommerce_content()`
 * dans le conteneur standard du thème. Le layout deux colonnes de la fiche
 * produit est porté par la classe `product-page` (voir components/woo.css).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_is_single_product = function_exists( 'is_product' ) && is_product();

/*
 * Fiche produit : conteneur standard `site-container` (layout `product-page`).
 * Archives boutique / taxonomies produit : `page-shell` (rythme bas),
 * SANS `site-container` — la largeur est portée par les `.container` internes
 * des parts DS (section-header, archive-grid…), exactement comme archive.php.
 * Le rendu de l'archive est piloté par parts/woo-archive.php.
 */
$_180c_main_classes = $_180c_is_single_product
	? 'site-main site-container product-page'
	: 'site-main page-shell woo-archive';

get_header();
?>
<main id="main" class="<?php echo esc_attr( $_180c_main_classes ); ?>">
	<?php
	if ( $_180c_is_single_product ) {
		// Fiche produit : rendu WooCommerce standard (content-single-product).
		woocommerce_content();
	} else {
		/*
		 * Archives boutique / taxonomies produit : rendu design system dédié.
		 * On NE passe PAS par woocommerce_content() (markup Woo par défaut) :
		 * WooCommerce ne charge pas son archive-product.php tant que le thème
		 * fournit ce woocommerce.php, donc tout le rendu d'archive vit dans le
		 * part parts/woo-archive.php.
		 */
		get_template_part( 'parts/woo-archive' );
	}
	?>
</main>
<?php
// Le fil d'Ariane est rendu globalement, juste au-dessus du footer, via
// get_footer() (footer.php → _180c_render_breadcrumb()). Plus d'appel local ici.
get_footer();
