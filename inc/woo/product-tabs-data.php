<?php
/**
 * Helpers de données pour les onglets de la fiche produit.
 *
 * Fournit la logique métier (gating éditorial, requêtes des contenus
 * liés, agrégation des specs techniques) consommée par le rendu des
 * onglets (inc/woo/product-tabs-render.php) :
 *   - Reportages : `post` liés au produit via la méta `related_product`.
 *   - Recettes   : CPT `recipe` liés au produit via la méta `source_issue`.
 *   - Infos techniques : Date de parution & Pagination (attributs LOCAUX
 *     `date-de-parution` / `pagination`, affichés tels que saisis), ISBN
 *     (champ natif `global_unique_id`, repli méta legacy `_isbn_field`),
 *     dimensions & poids natifs WooCommerce (convertis en cm / g) + le prix
 *     (affiché mais exclu du test de vacuité).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catégories produit déclenchant les onglets éditoriaux (reportages / recettes).
 *
 * Base = `[85, 70]` (filtrable via `_180c_product_editorial_tab_categories`).
 * Les descendants de chaque terme sont ajoutés automatiquement : le gating
 * reste correct que 85/70 soient des feuilles ou des parents de sous-catégories.
 *
 * @return int[] IDs de termes `product_cat` (base + descendants), dédupliqués.
 */
function _180c_product_editorial_tab_categories(): array {
	/**
	 * Filtre la liste de base des catégories éditoriales.
	 *
	 * @param int[] $base IDs de termes `product_cat` (défaut `[85, 70]`).
	 */
	$base = apply_filters( '_180c_product_editorial_tab_categories', array( 85, 70 ) );

	$all = array();
	foreach ( (array) $base as $term_id ) {
		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			continue;
		}
		$all[] = $term_id;

		$children = get_term_children( $term_id, 'product_cat' );
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child_id ) {
				$all[] = (int) $child_id;
			}
		}
	}

	return array_values( array_unique( $all ) );
}

/**
 * Indique si un produit appartient à une catégorie éditoriale (reportages / recettes).
 *
 * @param int $product_id ID du produit.
 * @return bool
 */
function _180c_product_has_editorial_tabs( int $product_id ): bool {
	if ( $product_id <= 0 ) {
		return false;
	}

	$cats = _180c_product_editorial_tab_categories();
	if ( empty( $cats ) ) {
		return false;
	}

	return (bool) has_term( $cats, 'product_cat', $product_id );
}

/**
 * Retourne les IDs des reportages (`post`) liés à un produit.
 *
 * Relation : méta ACF `related_product` (post_object, return_format `id`
 * → ID brut), donc comparaison stricte `=` sur la valeur du produit.
 *
 * @param int $product_id ID du produit.
 * @return int[] IDs d'articles publiés (peut être vide).
 */
function _180c_get_product_reportages( int $product_id ): array {
	if ( $product_id <= 0 ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => -1,
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- relation 1:1 bornée, page produit.
				array(
					'key'     => 'related_product',
					'value'   => $product_id,
					'compare' => '=',
				),
			),
		)
	);

	return array_map( 'intval', $query->posts );
}

/**
 * Retourne les IDs des recettes (`recipe`) liées à un produit.
 *
 * Relation inverse : méta ACF `source_issue` (post_object, return_format
 * `id` → ID brut) côté recette pointant vers le produit (numéro source).
 *
 * @param int $product_id ID du produit.
 * @return int[] IDs de recettes publiées (peut être vide).
 */
function _180c_get_product_recipes( int $product_id ): array {
	if ( $product_id <= 0 ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'           => 'recipe',
			'post_status'         => 'publish',
			'posts_per_page'      => -1,
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- relation 1:1 bornée, page produit.
				array(
					'key'     => 'source_issue',
					'value'   => $product_id,
					'compare' => '=',
				),
			),
		)
	);

	return array_map( 'intval', $query->posts );
}

/**
 * Formate une mesure numérique (cm / g) en chaîne localisée sans zéros inutiles.
 *
 * @param float $value Valeur convertie.
 * @return string Ex. « 21 » ou « 21,5 ».
 */
function _180c_format_measure_value( float $value ): string {
	$rounded  = round( $value, 1 );
	$decimals = ( floor( $rounded ) === $rounded ) ? 0 : 1;
	return number_format_i18n( $rounded, $decimals );
}

/**
 * Agrège les lignes de la fiche technique d'un produit.
 *
 * Renvoie un tableau **ordonné** (D6) de lignes non-vides. Chaque ligne :
 *   - `key`      : identifiant interne ;
 *   - `label`    : libellé affiché ;
 *   - `value`    : valeur (HTML pour le prix, texte sinon) ;
 *   - `is_price` : true pour la ligne prix (exclue du test de vacuité, D3) ;
 *   - `is_html`  : true si `value` est déjà du HTML échappé (prix).
 *
 * Dimensions converties en cm et poids en g via les unités de la boutique.
 *
 * @param WC_Product $product Produit.
 * @return array<int,array<string,mixed>>
 */
function _180c_get_product_tech_specs( WC_Product $product ): array {
	$rows = array();

	// 1. Date de parution — attribut LOCAL, affiché tel que saisi (pas de reformat).
	$pubdate = trim( (string) $product->get_attribute( 'date-de-parution' ) );
	if ( '' !== $pubdate ) {
		$rows[] = array(
			'key'      => 'publication_date',
			'label'    => __( 'Date de parution', '180c' ),
			'value'    => $pubdate,
			'is_price' => false,
		);
	}

	// 2. Pagination — attribut LOCAL, affiché tel que saisi (pas d'ajout d'unité).
	$pagination = trim( (string) $product->get_attribute( 'pagination' ) );
	if ( '' !== $pagination ) {
		$rows[] = array(
			'key'      => 'pagination',
			'label'    => __( 'Pagination', '180c' ),
			'value'    => $pagination,
			'is_price' => false,
		);
	}

	// 3. ISBN — champ natif WooCommerce « global_unique_id » (source unique),
	// avec repli sur la méta legacy `_isbn_field` (filet avant replay migration).
	$isbn = trim( (string) $product->get_global_unique_id() );
	if ( '' === $isbn ) {
		$isbn = trim( (string) $product->get_meta( '_isbn_field' ) );
	}
	if ( '' !== $isbn ) {
		$rows[] = array(
			'key'      => 'isbn',
			'label'    => __( 'ISBN', '180c' ),
			'value'    => $isbn,
			'is_price' => false,
		);
	}

	// 4. Prix (affiché mais exclu du test de vacuité — D3).
	$price_html = $product->get_price_html();
	if ( ! empty( $price_html ) ) {
		$rows[] = array(
			'key'      => 'price',
			'label'    => __( 'Prix', '180c' ),
			'value'    => $price_html,
			'is_price' => true,
			'is_html'  => true,
		);
	}

	// 5. Dimensions (L × l × H), converties en cm, valeurs présentes uniquement.
	$dimension_unit = get_option( 'woocommerce_dimension_unit' );
	$dimensions     = array();
	foreach ( array( 'get_length', 'get_width', 'get_height' ) as $getter ) {
		$raw = $product->$getter();
		if ( '' !== $raw && null !== $raw && (float) $raw > 0 ) {
			$cm           = (float) wc_get_dimension( (float) $raw, 'cm', $dimension_unit );
			$dimensions[] = _180c_format_measure_value( $cm );
		}
	}
	if ( ! empty( $dimensions ) ) {
		$rows[] = array(
			'key'      => 'dimensions',
			'label'    => __( 'Dimensions', '180c' ),
			/* translators: %s: dimensions jointes (ex. « 21 × 15 × 2 »). */
			'value'    => sprintf( __( '%s cm', '180c' ), implode( ' × ', $dimensions ) ),
			'is_price' => false,
		);
	}

	// 6. Poids, converti en g.
	$weight_raw = $product->get_weight();
	if ( '' !== $weight_raw && null !== $weight_raw && (float) $weight_raw > 0 ) {
		$weight_unit = get_option( 'woocommerce_weight_unit' );
		$grams       = (float) wc_get_weight( (float) $weight_raw, 'g', $weight_unit );
		$rows[]      = array(
			'key'      => 'weight',
			'label'    => __( 'Poids', '180c' ),
			/* translators: %s: poids en grammes. */
			'value'    => sprintf( __( '%s g', '180c' ), _180c_format_measure_value( $grams ) ),
			'is_price' => false,
		);
	}

	return $rows;
}

/**
 * Indique si un produit possède au moins une donnée technique (hors prix).
 *
 * Conformément à D3, le prix est exclu du test : l'onglet « Informations
 * techniques » n'apparaît que si ≥ 1 champ parmi {parution, pagination, ISBN,
 * dimensions, poids} est renseigné.
 *
 * @param WC_Product $product Produit.
 * @return bool
 */
function _180c_product_has_tech_data( WC_Product $product ): bool {
	foreach ( _180c_get_product_tech_specs( $product ) as $row ) {
		if ( empty( $row['is_price'] ) ) {
			return true;
		}
	}
	return false;
}
