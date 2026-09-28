<?php
/**
 * Rendu PHP des onglets de la fiche produit.
 *
 * Compose dynamiquement la liste des onglets (D6) :
 *   1. Description           — toujours (rendu du long descriptif WC) ;
 *   2. Reportages            — si produit éditorial (cat 85/70 + desc.) ET ≥ 1 ;
 *   3. Recettes              — idem, recettes liées ;
 *   4. Informations techniques — si ≥ 1 donnée technique hors prix (D3).
 *
 * Markup ARIA (tablist / tab / tabpanel) + dégradation no-JS : aucun
 * panneau n'est masqué au rendu PHP (tous empilés, titrés). Le masquage
 * et la navigation clavier sont appliqués par src/js/modules/product-tabs.js
 * (qui pose l'attribut `data-tabs-ready` une fois initialisé).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Construit la liste ordonnée des onglets d'un produit.
 *
 * @param WC_Product $product Produit.
 * @return array<int,array{key:string,label:string,content:string}> Onglets non-vides.
 */
function _180c_build_product_tabs( WC_Product $product ): array {
	$product_id = $product->get_id();
	$tabs       = array();

	// 1. Description — toujours présente (D6). Rendu du long descriptif WC.
	ob_start();
	the_content();
	$description = trim( (string) ob_get_clean() );
	$tabs[]      = array(
		'key'     => 'description',
		'label'   => __( 'Description', '180c' ),
		'content' => '<div class="entry-content product-tabs__prose">' . $description . '</div>',
	);

	// Gating éditorial calculé une fois (reportages + recettes).
	$is_editorial = _180c_product_has_editorial_tabs( $product_id );

	// 2. Reportages.
	if ( $is_editorial ) {
		$reportage_ids = _180c_get_product_reportages( $product_id );
		if ( ! empty( $reportage_ids ) ) {
			$cards = '';
			foreach ( $reportage_ids as $reportage_id ) {
				$cards .= _180c_render_article_card( $reportage_id );
			}
			if ( '' !== trim( $cards ) ) {
				$intro  = '<p class="product-tabs__intro">' . esc_html__( 'Retrouvez les reportages publiés dans ce numéro.', '180c' ) . '</p>';
				$tabs[] = array(
					'key'     => 'reportages',
					/* translators: %d: nombre de reportages liés au produit. */
					'label'   => sprintf( __( 'Reportages (%d)', '180c' ), count( $reportage_ids ) ),
					'content' => $intro . '<div class="product-tabs__grid">' . $cards . '</div>',
				);
			}
		}
	}

	// 3. Recettes.
	if ( $is_editorial ) {
		$recipe_ids = _180c_get_product_recipes( $product_id );
		if ( ! empty( $recipe_ids ) ) {
			$cards = '';
			foreach ( $recipe_ids as $recipe_id ) {
				$cards .= _180c_render_recipe_card( $recipe_id );
			}
			if ( '' !== trim( $cards ) ) {
				$intro  = '<p class="product-tabs__intro">' . esc_html__( 'Retrouvez les recettes publiées dans ce numéro.', '180c' ) . '</p>';
				$tabs[] = array(
					'key'     => 'recettes',
					/* translators: %d: nombre de recettes liées au produit. */
					'label'   => sprintf( __( 'Recettes (%d)', '180c' ), count( $recipe_ids ) ),
					'content' => $intro . '<div class="product-tabs__grid">' . $cards . '</div>',
				);
			}
		}
	}

	// 4. Informations techniques (≥ 1 donnée hors prix — D3).
	if ( _180c_product_has_tech_data( $product ) ) {
		$tabs[] = array(
			'key'     => 'informations-techniques',
			'label'   => __( 'Informations techniques', '180c' ),
			'content' => _180c_render_product_tech_table( $product ),
		);
	}

	return $tabs;
}

/**
 * Rend la table 2 colonnes des informations techniques.
 *
 * @param WC_Product $product Produit.
 * @return string HTML de la table (lignes non-vides uniquement).
 */
function _180c_render_product_tech_table( WC_Product $product ): string {
	$specs = _180c_get_product_tech_specs( $product );
	if ( empty( $specs ) ) {
		return '';
	}

	$rows = '';
	foreach ( $specs as $spec ) {
		$value = ! empty( $spec['is_html'] )
			? $spec['value'] // Prix : HTML déjà échappé par WooCommerce.
			: esc_html( (string) $spec['value'] );

		$rows .= sprintf(
			'<tr class="product-tabs__specs-row"><th scope="row" class="product-tabs__specs-label">%1$s</th><td class="product-tabs__specs-value">%2$s</td></tr>',
			esc_html( $spec['label'] ),
			$value // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé ci-dessus (texte) ou HTML WC (prix).
		);
	}

	return '<table class="product-tabs__specs"><tbody>' . $rows . '</tbody></table>';
}

/**
 * Rend le composant onglets de la fiche produit.
 *
 * Affiche au minimum l'onglet Description. IDs préfixés par l'ID produit
 * pour éviter toute collision (plusieurs fiches dans un même DOM, ex. DS).
 *
 * @param WC_Product $product Produit.
 * @return void
 */
function _180c_render_product_tabs( WC_Product $product ): void {
	$tabs = _180c_build_product_tabs( $product );
	if ( empty( $tabs ) ) {
		return;
	}

	$uid = 'p' . $product->get_id();
	?>
	<div class="product-tabs" data-product-tabs>
		<div class="product-tabs__list" role="tablist" aria-label="<?php esc_attr_e( 'Détails du produit', '180c' ); ?>">
			<?php foreach ( $tabs as $index => $tab ) : ?>
				<button
					type="button"
					class="product-tabs__tab"
					role="tab"
					id="ptab-<?php echo esc_attr( $uid . '-' . $tab['key'] ); ?>"
					aria-controls="ppanel-<?php echo esc_attr( $uid . '-' . $tab['key'] ); ?>"
					aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
					<?php echo 0 === $index ? '' : 'tabindex="-1"'; ?>
				><?php echo esc_html( $tab['label'] ); ?></button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $tabs as $tab ) : ?>
			<section
				class="product-tabs__panel"
				role="tabpanel"
				id="ppanel-<?php echo esc_attr( $uid . '-' . $tab['key'] ); ?>"
				aria-labelledby="ptab-<?php echo esc_attr( $uid . '-' . $tab['key'] ); ?>"
				tabindex="0"
			>
				<h2 class="product-tabs__panel-title"><?php echo esc_html( $tab['label'] ); ?></h2>
				<?php
				// Contenu composé/échappé en amont (entry-content, grilles de cartes, table specs).
				echo $tab['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
}
