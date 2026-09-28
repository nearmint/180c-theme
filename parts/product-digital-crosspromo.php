<?php
/**
 * Cross-promo abonnement numérique — fiche produit.
 *
 * Section visible (conversion secondaire) vers la Page Abonnement,
 * 100 % numérique : aucune mention ni option d'abonnement papier.
 *
 * Visibilité : produits physiques uniquement. Le test `! is_virtual()` couvre
 * proprement le périmètre attendu (masque epub, abonnements et produits
 * « subscription », tous numériques) sans hardcoder de slug de catégorie.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! ( $product instanceof WC_Product ) || $product->is_virtual() ) {
	return;
}

/**
 * URL de la Page Abonnement. Filtrable : par défaut /abonnement/.
 *
 * @param string $url URL cible de la cross-promo.
 */
$_180c_abo_url = apply_filters( '_180c_subscription_url', home_url( '/abonnement/' ) );
?>
<aside class="product-crosspromo">
	<div class="product-crosspromo__body">
		<p class="product-crosspromo__kicker"><?php esc_html_e( 'Abonnement numérique', '180c' ); ?></p>
		<h2 class="product-crosspromo__title"><?php esc_html_e( 'Accédez à toutes les recettes en ligne', '180c' ); ?></h2>
		<p class="product-crosspromo__text"><?php esc_html_e( 'L’intégralité des recettes 180°C, en illimité et 100 % numérique, sur le site et les applications.', '180c' ); ?></p>
	</div>
	<a class="btn btn--secondary product-crosspromo__cta" href="<?php echo esc_url( $_180c_abo_url ); ?>">
		<?php esc_html_e( 'Découvrir l’abonnement', '180c' ); ?>
	</a>
</aside>
