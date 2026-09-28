<?php
/**
 * Single Product Price — surcharge 180°C.
 *
 * Ajoute la classe `product__price` (stylée dans components/woo.css) tout en
 * conservant `.price` attendue par WooCommerce.
 *
 * Produits externes / affiliés : un badge de statut « Disponible uniquement en
 * librairie » est rendu juste après le prix, pour tout produit externe, que
 * `_product_url` pointe ou non vers un marchand. Le texte est fixe : il décrit
 * le mode de distribution, il ne reprend PAS le champ WooCommerce « Texte du
 * bouton » (`_button_text`), qui est une saisie libre et sert, lui, à étiqueter
 * le CTA (voir _180c_product_external_cta(), inc/woo/single-product.php).
 *
 * Le badge remplace l'ancienne parenthèse `.product__external-note`, qui
 * reprenait `_button_text` à droite du prix et que la CSS masquait : elle
 * partait dans le HTML de chaque fiche sans jamais s'afficher.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

?>
<p class="<?php echo esc_attr( apply_filters( 'woocommerce_product_price_class', 'price product__price' ) ); ?>"><?php
	echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?></p>
<?php if ( $product->is_type( 'external' ) ) : ?>
	<p class="product__status"><?php esc_html_e( 'Disponible uniquement en librairie', '180c' ); ?></p>
<?php endif; ?>
