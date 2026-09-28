<?php
/**
 * Bloc acf/produit-unique — rendu serveur (template ACF).
 *
 * Affiche une carte produit WooCommerce unique, sélectionnée via un champ
 * post_object, en variante 'sm' (compacte). Le conteneur centre la carte et
 * borne sa largeur (cf. .block-180c-produit-unique dans blocks-produits.css)
 * afin qu'elle ne soit jamais étirée à 100 % de la colonne d'article.
 *
 * Contexte ACF disponible : $block, $content, $is_preview, $post_id.
 *
 * Les variables locales sont préfixées `_180c_` : ce template est inclus en
 * portée de fichier par ACF, donc PHPCS (PrefixAllGlobals) les analyse comme
 * des globales.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/_helpers.php';

$_180c_pu_is_preview = ! empty( $is_preview );
$_180c_pu_product_id = (int) get_field( 'produit' );

$_180c_pu_product = ( $_180c_pu_product_id && function_exists( 'wc_get_product' ) && 'publish' === get_post_status( $_180c_pu_product_id ) )
	? wc_get_product( $_180c_pu_product_id )
	: null;

// Garde : produit absent / non publié / introuvable.
if ( ! $_180c_pu_product instanceof WC_Product ) {
	if ( $_180c_pu_is_preview ) {
		echo '<div class="block-180c-produit-unique block-180c-produit-unique--placeholder"><p>'
			. esc_html__( 'Produit unique : sélectionnez un produit publié dans les réglages du bloc.', '180c' )
			. '</p></div>';
	}
	return;
}

$_180c_pu_card = _180c_block_render_product_card( $_180c_pu_product, array( 'variant' => 'sm' ) );

if ( '' === $_180c_pu_card ) {
	return;
}

// Attributs de wrapper (anchor + className éventuels), alignés sur le modèle
// acf/coordonnees-lieu (les blocs ACF exposent ces valeurs via $block).
$_180c_pu_classes = 'block-180c-produit-unique';
if ( ! empty( $block['className'] ) ) {
	$_180c_pu_classes .= ' ' . $block['className'];
}
$_180c_pu_anchor = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';
?>
<div class="<?php echo esc_attr( $_180c_pu_classes ); ?>"<?php echo $_180c_pu_anchor; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- anchor échappé ci-dessus. ?>>
	<?php echo $_180c_pu_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé par _180c_block_render_product_card(). ?>
</div>
