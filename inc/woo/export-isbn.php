<?php
/**
 * ISBN dans les exports de commandes — WooCommerce Customer/Order/Coupon Export.
 *
 * Le plugin d'export de commandes n'exporte pas le catalogue produits :
 * l'ISBN doit donc apparaître au niveau des LIGNES ARTICLES des exports de
 * commandes. Deux formats CSV sont couverts, et eux seuls :
 *
 *  - `default_one_row_per_item` (une ligne par article) → colonne `item_isbn`,
 *    insérée juste après `item_sku` ;
 *  - `default` (une ligne par commande) → colonne `line_items_isbn`, insérée
 *    juste après `line_items`. Les ISBN y sont concaténés par `;` dans l'ordre
 *    des lignes articles, les valeurs vides étant omises et les doublons
 *    conservés. Le contenu natif de `line_items` n'est PAS touché.
 *
 * Le format personnalisé (« builder ») est servi de façon purement ADDITIVE :
 * les deux clés sont proposées comme sources de données, et la valeur n'est
 * fournie que si la colonne correspondante figure déjà dans le mapping du
 * format. Aucun format enregistré n'est modifié, aucune option n'est écrite.
 *
 * Hors périmètre, et inatteignable par construction : le XML (générateur et
 * filtres entièrement disjoints, préfixe `wc_customer_order_export_xml_`), les
 * formats `import` et `legacy_*`, les exports clients et coupons.
 *
 * ⚠ Le filtre `wc_customer_order_export_csv_order_line_item` — point d'accroche
 * en apparence naturel pour un ISBN de ligne — est délibérément ÉCARTÉ : en
 * mode « une ligne par commande », son retour est ensuite agrégé par
 * `pipe_delimit_item()` dans la colonne `line_items`, dont le contenu doit
 * rester intact. Les deux filtres de LIGNE sont utilisés à la place.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clé de colonne pour le format « une ligne par article ».
 *
 * La convention du plugin veut que la clé serve aussi de libellé d'en-tête
 * (`item_sku => item_sku`, `line_items => line_items`).
 */
define( '_180C_EXPORT_ISBN_COLUMN_ITEM', 'item_isbn' );

/**
 * Clé de colonne pour le format « une ligne par commande ».
 */
define( '_180C_EXPORT_ISBN_COLUMN_ORDER', 'line_items_isbn' );

/**
 * Séparateur des ISBN agrégés dans la colonne « une ligne par commande ».
 *
 * Identique à celui que le plugin emploie pour `line_items`, `tax_items`,
 * `coupon_items` et consorts en mode `pipe_delimited`.
 */
define( '_180C_EXPORT_ISBN_SEPARATOR', ';' );


/**
 * Résout l'ISBN d'une ligne de commande.
 *
 * Source : le champ WooCommerce natif « GTIN, UPC, EAN ou ISBN »
 * (`_global_unique_id`, WooCommerce ≥ 9.2), lu sur le produit de la ligne.
 *
 * Repli sur le parent : `WC_Product_Variation` surcharge `get_sku()` pour
 * hériter de la valeur du parent, mais PAS `get_global_unique_id()` — une
 * variation sans ISBN propre rendrait donc une chaîne vide alors que son
 * parent en porte un. Le repli est explicite ici pour cette raison.
 *
 * La valeur est rendue BRUTE, au `trim()` près : ni normalisation (tirets,
 * espaces), ni validation de la clé de contrôle ISBN. C'est un arbitrage —
 * l'export doit refléter la donnée saisie, pas la corriger.
 *
 * @param WC_Order_Item_Product $item Ligne article de la commande.
 * @return string ISBN, ou chaîne vide si la ligne n'en porte aucun
 *                (produit sans ISBN, produit supprimé ou introuvable).
 */
function _180c_get_item_isbn( WC_Order_Item_Product $item ): string {

	$product = $item->get_product();

	// `get_product()` rend `false` si le produit a été supprimé depuis la commande.
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	// Garde de version : le champ est natif depuis WooCommerce 9.2 seulement.
	if ( ! method_exists( $product, 'get_global_unique_id' ) ) {
		return '';
	}

	$isbn = trim( (string) $product->get_global_unique_id() );

	if ( '' !== $isbn ) {
		return $isbn;
	}

	$parent_id = $product->get_parent_id();

	if ( $parent_id <= 0 ) {
		return '';
	}

	$parent = wc_get_product( $parent_id );

	if ( ! $parent instanceof WC_Product || ! method_exists( $parent, 'get_global_unique_id' ) ) {
		return '';
	}

	return trim( (string) $parent->get_global_unique_id() );
}


/**
 * Rend le type de ligne du format en cours d'export.
 *
 * @param object $generator Instance du générateur d'export (propriétés publiques).
 * @return string `item` (une ligne par article) ou `order` (une ligne par commande).
 */
function _180c_export_isbn_row_type( $generator ): string {

	if ( ! is_object( $generator ) || ! isset( $generator->format_definition['row_type'] ) ) {
		return 'order';
	}

	return 'item' === $generator->format_definition['row_type'] ? 'item' : 'order';
}


/**
 * Détermine si la colonne ISBN donnée s'applique au format en cours d'export.
 *
 * Les deux formats par défaut la reçoivent d'office. Le format personnalisé ne
 * la reçoit que si l'opérateur a lui-même ajouté la source correspondante dans
 * le builder — la colonne est alors déjà présente dans le mapping. Tout autre
 * format (`import`, `legacy_single_column`, `legacy_one_row_per_item`) est
 * laissé intact.
 *
 * @param object $generator  Instance du générateur d'export.
 * @param string $column_key Clé de colonne testée.
 * @return bool
 */
function _180c_export_isbn_column_applies( $generator, string $column_key ): bool {

	if ( ! is_object( $generator ) || ! isset( $generator->export_format ) ) {
		return false;
	}

	if ( in_array( $generator->export_format, array( 'default', 'default_one_row_per_item' ), true ) ) {
		return true;
	}

	if ( 'custom' !== $generator->export_format ) {
		return false;
	}

	$columns = isset( $generator->format_definition['columns'] ) && is_array( $generator->format_definition['columns'] )
		? $generator->format_definition['columns']
		: array();

	return isset( $columns[ $column_key ] );
}


/**
 * Insère des entrées dans un tableau associatif juste après une clé donnée.
 *
 * Si la clé de référence est absente, les entrées sont ajoutées en fin de
 * tableau : une colonne mal placée reste préférable à une colonne perdue.
 *
 * @param array  $source Tableau d'origine.
 * @param string $after  Clé de référence.
 * @param array  $insert Entrées à insérer.
 * @return array
 */
function _180c_export_isbn_insert_after( array $source, string $after, array $insert ): array {

	if ( ! array_key_exists( $after, $source ) ) {
		return array_merge( $source, $insert );
	}

	$position = array_search( $after, array_keys( $source ), true ) + 1;

	return array_merge(
		array_slice( $source, 0, $position, true ),
		$insert,
		array_slice( $source, $position, null, true )
	);
}


/**
 * Ajoute la colonne ISBN aux en-têtes CSV des exports de commandes.
 *
 * Un seul filtre couvre les deux formats : c'est le type de ligne qui décide
 * de la colonne à poser et de sa position. Le format personnalisé est ignoré —
 * ses en-têtes sont ceux du mapping saisi par l'opérateur, on n'y touche pas.
 *
 * @param array  $headers   En-têtes au format `clé => libellé`.
 * @param object $generator Instance du générateur d'export.
 * @return array
 */
function _180c_export_isbn_order_headers( $headers, $generator ) {

	if ( ! is_array( $headers ) || ! isset( $generator->export_format ) ) {
		return $headers;
	}

	if ( ! in_array( $generator->export_format, array( 'default', 'default_one_row_per_item' ), true ) ) {
		return $headers;
	}

	if ( 'item' === _180c_export_isbn_row_type( $generator ) ) {
		return _180c_export_isbn_insert_after(
			$headers,
			'item_sku',
			array( _180C_EXPORT_ISBN_COLUMN_ITEM => _180C_EXPORT_ISBN_COLUMN_ITEM )
		);
	}

	return _180c_export_isbn_insert_after(
		$headers,
		'line_items',
		array( _180C_EXPORT_ISBN_COLUMN_ORDER => _180C_EXPORT_ISBN_COLUMN_ORDER )
	);
}
add_filter( 'wc_customer_order_export_csv_order_headers', '_180c_export_isbn_order_headers', 10, 2 );


/**
 * Renseigne la colonne ISBN d'une ligne « une ligne par commande ».
 *
 * ⚠ Ce filtre est également appelé en mode « une ligne par article », où
 * `$order_data` est alors un TABLEAU DE LIGNES et non une ligne. D'où la garde
 * sur le type de ligne, sans laquelle on écraserait la structure entière.
 *
 * `get_items()` sans argument ne rend que les lignes `line_item` : frais,
 * livraison et coupons sont donc ignorés sans avoir à les filtrer.
 *
 * @param array    $order_data Données de la ligne, au format `clé => valeur`.
 * @param WC_Order $order      Commande exportée.
 * @param object   $generator  Instance du générateur d'export.
 * @return array
 */
function _180c_export_isbn_order_row( $order_data, $order, $generator ) {

	if ( ! is_array( $order_data ) || ! $order instanceof WC_Order ) {
		return $order_data;
	}

	if ( 'order' !== _180c_export_isbn_row_type( $generator ) ) {
		return $order_data;
	}

	if ( ! _180c_export_isbn_column_applies( $generator, _180C_EXPORT_ISBN_COLUMN_ORDER ) ) {
		return $order_data;
	}

	$isbns = array();

	foreach ( $order->get_items() as $item ) {

		if ( ! $item instanceof WC_Order_Item_Product ) {
			continue;
		}

		$isbn = _180c_get_item_isbn( $item );

		if ( '' !== $isbn ) {
			$isbns[] = $isbn;
		}
	}

	$order_data[ _180C_EXPORT_ISBN_COLUMN_ORDER ] = implode( _180C_EXPORT_ISBN_SEPARATOR, $isbns );

	return $order_data;
}
add_filter( 'wc_customer_order_export_csv_order_row', '_180c_export_isbn_order_row', 10, 3 );


/**
 * Renseigne la colonne ISBN d'une ligne « une ligne par article ».
 *
 * ⚠ Le `$item` transmis par le plugin est un TABLEAU (construit par
 * `CSV_Export_Generator::get_line_items()`), pas un objet de ligne. Sa clé `id`
 * porte l'identifiant de la ligne de commande, seul chemin vers l'objet
 * `WC_Order_Item_Product` dont le helper a besoin.
 *
 * @param array    $order_data Données de la ligne, au format `clé => valeur`.
 * @param array    $item       Données de la ligne article vues par le plugin.
 * @param WC_Order $order      Commande exportée.
 * @param object   $generator  Instance du générateur d'export.
 * @return array
 */
function _180c_export_isbn_item_row( $order_data, $item, $order, $generator ) {

	if ( ! is_array( $order_data ) || ! $order instanceof WC_Order ) {
		return $order_data;
	}

	if ( 'item' !== _180c_export_isbn_row_type( $generator ) ) {
		return $order_data;
	}

	if ( ! _180c_export_isbn_column_applies( $generator, _180C_EXPORT_ISBN_COLUMN_ITEM ) ) {
		return $order_data;
	}

	$order_data[ _180C_EXPORT_ISBN_COLUMN_ITEM ] = '';

	if ( ! is_array( $item ) || empty( $item['id'] ) ) {
		return $order_data;
	}

	$order_item = $order->get_item( $item['id'] );

	if ( $order_item instanceof WC_Order_Item_Product ) {
		$order_data[ _180C_EXPORT_ISBN_COLUMN_ITEM ] = _180c_get_item_isbn( $order_item );
	}

	return $order_data;
}
add_filter( 'wc_customer_order_export_csv_order_row_one_row_per_item', '_180c_export_isbn_item_row', 10, 4 );


/**
 * Expose les deux clés ISBN comme sources de données du builder de formats.
 *
 * Purement additif : aucun format enregistré n'est modifié. L'opérateur reste
 * libre d'ajouter — ou non — la colonne à un format personnalisé depuis
 * l'administration. `item_isbn` n'a de sens qu'avec un format dont la ligne
 * représente un article, `line_items_isbn` seulement avec une ligne par
 * commande ; le builder mélangeant déjà les deux familles dans le même select
 * (`item_sku` y côtoie `line_items`), les deux sont proposées ensemble et
 * placées auprès de leur voisine naturelle.
 *
 * @param array  $sources     Sources de données disponibles (liste plate).
 * @param string $export_type Type d'export : `orders`, `customers` ou `coupons`.
 * @return array
 */
function _180c_export_isbn_data_sources( $sources, $export_type ) {

	if ( ! is_array( $sources ) || 'orders' !== $export_type ) {
		return $sources;
	}

	foreach ( array(
		'item_sku'   => _180C_EXPORT_ISBN_COLUMN_ITEM,
		'line_items' => _180C_EXPORT_ISBN_COLUMN_ORDER,
	) as $after => $column ) {

		if ( in_array( $column, $sources, true ) ) {
			continue;
		}

		$position = array_search( $after, $sources, true );

		if ( false === $position ) {
			$sources[] = $column;
			continue;
		}

		array_splice( $sources, $position + 1, 0, array( $column ) );
	}

	return $sources;
}
add_filter( 'wc_customer_order_export_csv_format_data_sources', '_180c_export_isbn_data_sources', 10, 2 );
