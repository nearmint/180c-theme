<?php
/**
 * Bloc « produit source » mutualisé (recette + article).
 *
 * Source unique du markup pour le bloc « ce contenu est issu de tel
 * produit » (numéro, livre, cahier…). Historiquement deux rendus
 * divergeaient :
 *  - fiche recette (`single-recipe.php`) : `.recipe-section .recipe-source`
 *    enveloppant une carte produit (`_180c_block_render_product_card()`) ;
 *  - article (`patterns/article-source-issue.php`) : markup
 *    `.article-source` distinct.
 *
 * Ce helper consolide le premier (recipe-source) en source unique : la
 * fiche recette ET l'article appellent désormais `_180c_render_source_product()`.
 * Le markup produit est strictement identique à l'ancien recipe-source
 * (aucune régression visuelle attendue côté recette).
 *
 * Règle photo 180°C : la couverture n'est JAMAIS recadrée — garanti par
 * `.card-180c--product .card-180c__image { object-fit: contain }` (woo.css),
 * hérité tel quel ici (aucun `object-fit: cover`).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rend le bloc « produit source » (mutualisé recette + article).
 *
 * Reproduit 1:1 l'ancien markup `recipe-source` : une `<section>` au
 * gabarit `.recipe-section .recipe-source`, contenant une carte produit
 * (`_180c_block_render_product_card()`) précédée d'un titre, et,
 * optionnellement, d'une mention libre (cas recette : `recipe_source_revue`).
 *
 * Ne rend rien (chaîne vide) si :
 *  - WooCommerce est inactif ET aucune mention libre n'est fournie ;
 *  - le produit est absent / introuvable / non publié ET aucune mention.
 *
 * @param int|WC_Product|WP_Post|null $product Produit source (ID, objet WC ou WP_Post).
 * @param array                       $args {
 *     Options d'affichage (optionnel). Défauts = wording recipe-source.
 *
 *     @type string $heading    Titre `<h2 class="recipe-section__title">`.
 *                              Défaut : « Cette recette a initialement été publiée dans : ».
 *     @type string $mention    Mention libre rendue en `<p class="recipe-source__text">`
 *                              avant le titre (cas recette). Défaut : '' (rien).
 *     @type string $aria_label Libellé `aria-label` de la section. Défaut : « Source ».
 * }
 * @return string HTML échappé, ou '' si rien à afficher.
 */
function _180c_render_source_product( $product, array $args = array() ): string {
	$args = wp_parse_args(
		$args,
		array(
			'heading'    => __( 'Cette recette a initialement été publiée dans :', '180c' ),
			'mention'    => '',
			'aria_label' => __( 'Source', '180c' ),
		)
	);

	$mention = trim( (string) $args['mention'] );

	// --- Normalisation du produit → WC_Product publié, sinon null. ---------
	$product_obj = null;
	if ( $product instanceof WC_Product ) {
		$product_obj = $product;
	} elseif ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ) ) {
		if ( $product instanceof WP_Post ) {
			$product_obj = wc_get_product( $product->ID );
		} elseif ( is_numeric( $product ) && (int) $product > 0 ) {
			$product_obj = wc_get_product( (int) $product );
		}
	}
	if ( ! ( $product_obj instanceof WC_Product ) ) {
		$product_obj = null;
	}
	// Produit publié uniquement (no-op propre sur brouillon / corbeille).
	if ( $product_obj && 'publish' !== get_post_status( $product_obj->get_id() ) ) {
		$product_obj = null;
	}

	// Rien à afficher : ni produit exploitable, ni mention libre.
	if ( ! $product_obj && '' === $mention ) {
		return '';
	}

	// Le rendu de la carte produit vit dans le helper de blocs.
	if ( $product_obj && ! function_exists( '_180c_block_render_product_card' ) ) {
		require_once _180C_THEME_DIR . '/inc/blocks/_helpers.php';
	}

	ob_start();
	?>
	<section class="recipe-section recipe-source" aria-label="<?php echo esc_attr( $args['aria_label'] ); ?>">
		<div class="container-180c">
			<?php if ( '' !== $mention ) : ?>
				<p class="recipe-source__text"><?php echo esc_html( $mention ); ?></p>
			<?php endif; ?>
			<?php if ( $product_obj && function_exists( '_180c_block_render_product_card' ) ) : ?>
				<h2 class="recipe-section__title"><?php echo esc_html( $args['heading'] ); ?></h2>
				<?php
				// Wrapper .block-180c-product-card : parité de largeur / chrome avec
				// la boutique. Le modificateur --source neutralise le crop éventuel
				// (couverture affichée en entier — règle « photos jamais croppées »).
				?>
				<div class="block-180c-product-card block-180c-product-card--source">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le renderer.
					echo _180c_block_render_product_card( $product_obj );
					?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}
