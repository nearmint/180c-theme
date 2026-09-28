<?php
/**
 * Bloc acf/recette-unique — rendu serveur (template ACF).
 *
 * Affiche une carte recette (CPT `recipe`) unique, sélectionnée via un champ
 * post_object, en variante 'md'. Le conteneur centre la carte et borne sa
 * largeur (cf. .block-180c-recette-unique dans blocks-recettes.css) afin
 * qu'elle ne soit jamais étirée à 100 % de la colonne d'article.
 *
 * Pendant recette du bloc acf/produit-unique.
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

$_180c_ru_is_preview = ! empty( $is_preview );
$_180c_ru_recipe_id  = (int) get_field( 'recette' );
$_180c_ru_recipe     = $_180c_ru_recipe_id ? get_post( $_180c_ru_recipe_id ) : null;

// Garde : recette absente / mauvais type / non publiée.
if ( ! $_180c_ru_recipe instanceof WP_Post
	|| 'recipe' !== $_180c_ru_recipe->post_type
	|| 'publish' !== get_post_status( $_180c_ru_recipe )
) {
	if ( $_180c_ru_is_preview ) {
		echo '<div class="block-180c-recette-unique block-180c-recette-unique--placeholder"><p>'
			. esc_html__( 'Recette unique : sélectionnez une recette publiée dans les réglages du bloc.', '180c' )
			. '</p></div>';
	}
	return;
}

$_180c_ru_card = _180c_block_render_recipe_card( $_180c_ru_recipe, 'md' );

if ( '' === $_180c_ru_card ) {
	return;
}

// Attributs de wrapper (anchor + className éventuels), alignés sur le modèle
// acf/produit-unique (les blocs ACF exposent ces valeurs via $block).
$_180c_ru_classes = 'block-180c-recette-unique';
if ( ! empty( $block['className'] ) ) {
	$_180c_ru_classes .= ' ' . $block['className'];
}
$_180c_ru_anchor = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';
?>
<div class="<?php echo esc_attr( $_180c_ru_classes ); ?>"<?php echo $_180c_ru_anchor; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- anchor échappé ci-dessus. ?>>
	<?php echo $_180c_ru_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé par _180c_block_render_recipe_card(). ?>
</div>
