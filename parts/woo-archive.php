<?php
/**
 * Template part : archive boutique / taxonomies produit.
 *
 * Rendu des surfaces produit servies par WooCommerce via `woocommerce.php` :
 * page boutique (`is_shop()`) et archives de taxonomies produit (`product_cat`,
 * `product_tag`, `product_brand`), p. ex. `/categorie-produit/revues-180c/`.
 *
 * NB : ce part remplace l'appel à `woocommerce_content()` (qui rendrait le
 * markup WooCommerce par défaut). WooCommerce ne charge JAMAIS son
 * `archive-product.php` quand le thème fournit un `woocommerce.php` : tout le
 * rendu d'archive doit donc passer par ici.
 *
 * Aligne ces surfaces sur le gabarit d'archive canonique du thème
 * (`archive.php`) : mêmes composants design system — `section-header`
 * (eyebrow contextuel + <h1> + description de terme), `archive-grid` peuplée de
 * cartes produit `card-180c--product`, pagination `.pagination` et état vide
 * `empty-state`. Le wrapper `<main class="site-main page-shell woo-archive">`
 * et le fil d'Ariane global sont fournis par `woocommerce.php` / `footer.php`.
 *
 * Logique mutualisée via `inc/archives.php` (`_180c_archives_context()`,
 * `_180c_archives_render_card()`).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_ctx = _180c_archives_context();

// CTA d'état vide : retour à la boutique. _180c_shop_url() ignore l'option
// WooCommerce tant qu'elle pointe sur un brouillon (cf. inc/helpers.php).
$_180c_shop_url = _180c_shop_url();
?>

<?php
get_template_part(
	'parts/section-header',
	null,
	array(
		'eyebrow'     => $_180c_ctx['eyebrow'],
		'title'       => $_180c_ctx['title'],
		'description' => $_180c_ctx['description'],
	)
);
?>

<div class="container">

	<?php
	// Notices WooCommerce (confirmation d'ajout au panier, tris, etc.).
	if ( function_exists( 'woocommerce_output_all_notices' ) ) {
		woocommerce_output_all_notices();
	}
	?>

	<?php if ( have_posts() ) : ?>

		<ul class="archive-grid" role="list">
			<?php
			while ( have_posts() ) :
				the_post();
				$_180c_card_html = _180c_archives_render_card( get_the_ID() );
				if ( '' === $_180c_card_html ) {
					continue;
				}
				?>
				<li class="archive-grid__item">
					<?php echo $_180c_card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans les helpers de carte. ?>
				</li>
			<?php endwhile; ?>
		</ul>

		<?php
		echo _180c_render_pagination( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit par le helper (esc_attr en amont).
			array(
				'base'       => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
				'format'     => '?paged=%#%',
				'current'    => max( 1, (int) get_query_var( 'paged' ) ),
				'total'      => (int) $GLOBALS['wp_query']->max_num_pages,
				/* translators: %s: titre de l'archive boutique courante. */
				'aria_label' => sprintf( __( 'Pagination : %s', '180c' ), $_180c_ctx['title'] ),
			)
		);
		?>

	<?php else : ?>

		<?php
		get_template_part(
			'parts/empty-state',
			null,
			array(
				'title'     => __( 'Aucun produit pour le moment', '180c' ),
				'message'   => __( 'Cette catégorie ne contient aucun produit disponible à la vente pour l’instant.', '180c' ),
				'cta_url'   => $_180c_shop_url,
				'cta_label' => __( 'Voir toute la boutique', '180c' ),
			)
		);
		?>

	<?php endif; ?>
</div>
