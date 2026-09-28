<?php
/**
 * Fiche produit — composition 180°C.
 *
 * Hooks de la single product :
 *   - retrait des onglets WC (la description longue + FAQ est rendue inline
 *     dans woocommerce/content-single-product.php) ;
 *   - bloc réassurance sous le bouton d'ajout au panier ;
 *   - cap du nombre d'upsells (jamais -1) ;
 *   - raccourci « Passer commande » dans la notice d'ajout au panier ;
 *   - CTA des produits externes (« Trouver en librairie » / bouton désactivé),
 *     source unique consommée par woocommerce/single-product/add-to-cart/external.php
 *     et par le rappel _180c_render_external_cta().
 *
 * Les avis et le support des commentaires « product » sont gérés ailleurs
 * (inc/woo/overrides.php). La galerie reste la galerie native WooCommerce.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retire les onglets produit (Description / Infos complémentaires).
 *
 * Les hooks de template WooCommerce sont enregistrés au chargement du plugin,
 * donc avant le bootstrap du thème : ce remove_action de premier niveau est
 * effectif.
 */
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );

/**
 * Masque le bloc `.product_meta` de la fiche produit (UGS/SKU, catégories,
 * étiquettes). La référence interne (« UGS : … ») n'a aucune valeur en façade
 * et les catégories/étiquettes sont déjà couvertes par le fil d'Ariane et le
 * schema Product (product_cat → `category`). Le retrait du hook supprime la
 * div entière (aucun markup vide). Les données restent dans le @graph JSON-LD.
 */
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

/*
 * Bloc réassurance (« Paiement sécurisé » / « Expédition soignée » / « Service
 * client à l'écoute ») retiré de la fiche produit (itération 2026-06). Le
 * partial parts/product-reassurance.php reste sur disque pour la fiche du
 * Design System (template-parts/ds/components/misc.php, aperçu autonome) mais
 * n'est plus câblé sur woocommerce_single_product_summary.
 */

/**
 * Cap le nombre d'upsells affichés sur la fiche produit.
 *
 * Par défaut WooCommerce passe `posts_per_page = -1` (tous les upsells).
 * On borne à 8 pour rester cohérent avec le rail et éviter une requête non
 * bornée.
 *
 * @param array $args Arguments d'affichage des upsells.
 * @return array
 */
function _180c_upsell_display_args( $args ): array {
	$args['posts_per_page'] = 8;
	$args['columns']        = 4;
	return $args;
}
add_filter( 'woocommerce_upsell_display_args', '_180c_upsell_display_args' );

/**
 * Porte le nombre de produits « Vous aimerez aussi » (related) à 10.
 *
 * Défaut WooCommerce : 4. Le rendu passe par le rail Home Builder
 * (woocommerce/single-product/related.php) qui affiche tous les produits
 * renvoyés ; on borne donc la requête amont à 10.
 *
 * @param array $args Arguments d'affichage des related.
 * @return array
 */
function _180c_related_products_args( $args ): array {
	$args['posts_per_page'] = 10;
	$args['columns']        = 10;
	return $args;
}
add_filter( 'woocommerce_output_related_products_args', '_180c_related_products_args' );

/**
 * Ajoute un raccourci « Passer commande » à la notice d'ajout au panier.
 *
 * Complète le bouton natif « Voir le panier » par un lien direct vers le
 * checkout (chemin de conversion court). S'applique partout où la notice
 * d'ajout est rendue (fiche produit + archives).
 *
 * @param string $message HTML de la notice.
 * @return string
 */
function _180c_add_to_cart_checkout_link( $message ): string {
	$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
	if ( ! $checkout_url ) {
		return $message;
	}

	$message .= sprintf(
		' <a href="%s" class="button wc-forward wc-forward--checkout">%s</a>',
		esc_url( $checkout_url ),
		esc_html__( 'Passer commande', '180c' )
	);

	return $message;
}
add_filter( 'wc_add_to_cart_message_html', '_180c_add_to_cart_checkout_link' );

/**
 * Retourne l'hôte d'une URL, en minuscules et sans le préfixe « www. ».
 *
 * Sert exclusivement à décider si une URL sort du site : la comparaison doit
 * ignorer la casse et le `www.`, sans quoi `https://180c.fr/...` et
 * `https://www.180c.fr/...` passeraient pour deux domaines distincts.
 *
 * @param string $url URL à analyser.
 * @return string Hôte normalisé, ou chaîne vide si l'URL n'en contient pas.
 */
function _180c_url_host_without_www( string $url ): string {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );

	if ( '' === $host ) {
		return '';
	}

	return (string) preg_replace( '/^www\./i', '', strtolower( $host ) );
}

/**
 * Libellé du CTA d'un produit externe.
 *
 * Le champ WooCommerce « Texte du bouton » (`_button_text`) vaut aujourd'hui
 * « Disponible uniquement en librairie » sur les treize produits externes du
 * catalogue : cette valeur décrit une indisponibilité, pas une action, et ne
 * peut pas servir de libellé de lien. On la neutralise par une comparaison
 * insensible à la casse et aux accents, et on retombe sur le libellé d'action
 * du thème. Toute autre valeur saisie par la rédaction est, elle, respectée.
 *
 * @param WC_Product $product Produit concerné.
 * @return string Libellé, non échappé.
 */
function _180c_product_external_cta_label( WC_Product $product ): string {
	$button_text = trim( (string) $product->get_button_text() );

	if ( '' === $button_text ) {
		return __( 'Trouver en librairie', '180c' );
	}

	$normalize = static function ( string $value ): string {
		return strtolower( trim( remove_accents( $value ) ) );
	};

	if ( $normalize( $button_text ) === $normalize( __( 'Disponible uniquement en librairie', '180c' ) ) ) {
		return __( 'Trouver en librairie', '180c' );
	}

	return $button_text;
}

/**
 * Rend le CTA d'achat d'un produit externe (livre / revue vendu en librairie).
 *
 * Deux états, décidés par la seule URL du produit (`_product_url`) :
 *
 *  1. URL pointant vers un AUTRE domaine → lien d'action vers le marchand,
 *     ouvert dans un nouvel onglet, accompagné d'une note qui nomme le
 *     domaine de destination (rien ne doit surprendre au clic).
 *  2. URL vide, ou pointant sur le site lui-même → bouton désactivé portant
 *     la mention d'indisponibilité, sans note.
 *
 * Le second cas n'est pas théorique : les treize produits externes du
 * catalogue portent aujourd'hui, dans `_product_url`, leur PROPRE permalien.
 * Traiter cette valeur comme une URL marchande produirait un lien « Trouver
 * en librairie » renvoyant sur la page déjà affichée, en nouvelle fenêtre.
 *
 * @param WC_Product $product Produit concerné.
 * @param string     $context Contexte de rendu : 'summary' (colonne d'achat,
 *                            défaut) ou 'sticky', qui ajoute le modificateur
 *                            de layout `product__external-cta--sticky`. Ne
 *                            modifie ni les textes, ni les attributs, ni la
 *                            destination. La barre d'achat collante étant
 *                            construite côté client (src/js/modules/product.js),
 *                            aucun appel 'sticky' n'existe aujourd'hui.
 * @return string HTML échappé, ou chaîne vide si le produit n'est pas externe.
 */
function _180c_product_external_cta( WC_Product $product, string $context = 'summary' ): string {
	if ( ! $product->is_type( 'external' ) ) {
		return '';
	}

	$url   = trim( (string) $product->get_product_url() );
	$host  = _180c_url_host_without_www( $url );
	$label = _180c_product_external_cta_label( $product );

	// Modificateur de layout, et rien d'autre : le contexte ne touche jamais
	// aux textes, aux attributs ni à la destination du lien.
	$classes = 'product__external-cta btn btn--primary';
	if ( 'summary' !== $context && in_array( $context, array( 'sticky' ), true ) ) {
		$classes .= ' product__external-cta--' . $context;
	}

	// Sort-elle vraiment du site ? Une URL vide, relative, ou portant l'hôte
	// du site n'est pas une adresse marchande : on rend l'état indisponible.
	if ( '' === $host || _180c_url_host_without_www( home_url() ) === $host ) {
		return sprintf(
			'<button type="button" disabled aria-disabled="true" class="%1$s is-disabled">%2$s</button>',
			esc_attr( $classes ),
			esc_html__( 'Disponible uniquement en librairie', '180c' )
		);
	}

	$hint_id = 'product-external-hint';

	return sprintf(
		'<a class="%1$s" href="%2$s" target="_blank" rel="noopener" data-sticky-label="%3$s" aria-describedby="%4$s">%5$s<span class="sr-only"> %6$s</span>%7$s</a>',
		esc_attr( $classes ),
		esc_url( $url ),
		esc_attr( $label ),
		esc_attr( $hint_id ),
		esc_html( $label ),
		esc_html__( '(nouvelle fenêtre)', '180c' ),
		_180c_render_svg_icon( 'external-link' )
	) . sprintf(
		'<p id="%1$s" class="product__external-hint">%2$s</p>',
		esc_attr( $hint_id ),
		esc_html(
			sprintf(
				/* translators: %s: nom de domaine du marchand (ex. placedeslibraires.fr). */
				__( 'Vente en ligne indisponible sur 180c.fr — commande via %s', '180c' ),
				$host
			)
		)
	);
}

/**
 * Rend le CTA externe dans le seul cas que WooCommerce court-circuite.
 *
 * `woocommerce_external_add_to_cart()` sort sans rien rendre quand
 * `add_to_cart_url()` est vide (wc-template-functions.php, « if ( ! $product->
 * add_to_cart_url() ) { return; } ») : le template `add-to-cart/external.php`
 * n'est alors jamais chargé, et la fiche reste SANS aucun contrôle d'achat —
 * ni bouton, ni mention. Ce rappel comble exactement ce trou, avec la
 * condition inverse de celle du cœur, pour qu'aucun état ne puisse rendre
 * deux CTA ni zéro.
 *
 * Enregistré en priorité 30, comme le hook du cœur : l'ordre d'exécution est
 * alors l'ordre d'enregistrement, et le thème est chargé après le plugin.
 *
 * @return void
 */
function _180c_render_external_cta(): void {
	global $product;

	if ( ! $product instanceof WC_Product || ! $product->is_type( 'external' ) ) {
		return;
	}

	if ( $product->add_to_cart_url() ) {
		return;
	}

	echo _180c_product_external_cta( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML entièrement échappé dans le helper.
}
add_action( 'woocommerce_single_product_summary', '_180c_render_external_cta', 30 );
