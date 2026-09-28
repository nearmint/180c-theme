<?php
/**
 * Cross-sells du panier — module de mise en avant de l'abonnement mensuel.
 *
 * Le mécanisme natif WooCommerce sur la page panier est le « cross-sell »
 * (l'upsell, lui, vit sur la fiche produit). On s'appuie dessus pour proposer
 * l'abonnement mensuel `100% bien manger` (#13121518) en complément des
 * produits physiques du panier. L'attribution produit↔abonnement est une
 * donnée (postmeta `_crosssell_ids`, script `crosssell-abo.php`) ; ce fichier
 * cadre l'AFFICHAGE :
 *
 *  - garantit la présence de l'abonnement dans les cross-sells agrégés du
 *    panier (remontée en tête + tri figé + cap d'affichage) ;
 *  - fournit un rendu mutualisé `_180c_crosssell_module_html()` : un encart
 *    pleine largeur fond accent (titre + sous-titre + CTA), décliné en deux
 *    variantes — `full` (page panier) et `drawer` (mini-panier latéral).
 *
 * Le module ne s'affiche que si l'abonnement est un cross-sell ACTIF du panier
 * (attribué à un produit présent ET pas déjà au panier) → il disparaît une fois
 * l'abonnement ajouté. Le CTA ajoute l'abonnement au panier mixte (Mixed
 * Checkout requis) via le module vanilla src/js/modules/cart.js
 * (`.ajax_add_to_cart`), sans recharger la page.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * ID du produit d'abonnement mensuel mis en avant en cross-sell du panier.
 *
 * Filtrable pour rester indépendant d'un ID figé (réplication prod, évolution
 * du catalogue d'abonnements).
 *
 * @return int ID du produit d'abonnement, ou 0 si non défini.
 */
function _180c_crosssell_subscription_id(): int {
	return (int) apply_filters( '180c/crosssell_subscription_id', 13121518 );
}

/**
 * Remonte l'abonnement mensuel en tête des cross-sells du panier.
 *
 * N'INJECTE rien : l'abonnement n'apparaît que s'il est déjà attribué en
 * cross-sell d'un produit présent dans le panier (respect de l'attribution
 * produit par produit). On se contente de le réordonner en première position
 * pour qu'il survive au `array_slice` de la limite d'affichage.
 *
 * @param int[] $ids IDs des cross-sells agrégés du panier.
 * @return int[] IDs réordonnés (abonnement en tête si présent).
 */
function _180c_crosssell_feature_subscription( $ids ) {
	$abo_id = _180c_crosssell_subscription_id();

	if ( $abo_id <= 0 || empty( $ids ) ) {
		return $ids;
	}

	$ids = array_map( 'intval', (array) $ids );

	if ( ! in_array( $abo_id, $ids, true ) ) {
		return $ids;
	}

	$ids = array_values( array_diff( $ids, array( $abo_id ) ) );
	array_unshift( $ids, $abo_id );

	return $ids;
}
add_filter( 'woocommerce_cart_crosssell_ids', '_180c_crosssell_feature_subscription' );

/**
 * Préserve l'ordre des cross-sells (abonnement en tête) en désactivant le tri
 * aléatoire natif.
 *
 * @return string Toujours `none`.
 */
function _180c_crosssell_orderby() {
	return 'none';
}
add_filter( 'woocommerce_cross_sells_orderby', '_180c_crosssell_orderby' );

/**
 * Borne le nombre de cross-sells pris en compte par WooCommerce.
 *
 * On garde l'abonnement (en tête) sans laisser WooCommerce considérer une
 * longue liste : le rendu, lui, n'affiche qu'un seul encart de toute façon.
 *
 * @return int Nombre maximal de cross-sells considérés.
 */
function _180c_crosssell_total() {
	return 3;
}
add_filter( 'woocommerce_cross_sells_total', '_180c_crosssell_total' );

/**
 * L'abonnement est-il un cross-sell ACTIF du panier ?
 *
 * Vrai uniquement si l'abonnement est attribué en cross-sell d'un produit
 * présent dans le panier ET n'est pas déjà au panier (WooCommerce retire les
 * articles déjà présents de `get_cross_sells()`). C'est la condition unique
 * d'affichage du module, partagée par la page panier et le mini-panier.
 *
 * @return bool
 */
function _180c_crosssell_is_offered(): bool {
	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
		return false;
	}

	// Abonné en cours : on ne lui propose pas de (re)prendre un abonnement. Ce
	// seul point de garde masque l'encart sur la page panier ET dans le
	// mini-panier latéral (les deux surfaces passent par ici).
	if ( function_exists( '_180c_user_has_ongoing_subscription' ) && _180c_user_has_ongoing_subscription() ) {
		return false;
	}

	$abo_id = _180c_crosssell_subscription_id();
	if ( $abo_id <= 0 ) {
		return false;
	}

	$cross = array_map( 'intval', (array) WC()->cart->get_cross_sells() );
	if ( ! in_array( $abo_id, $cross, true ) ) {
		return false;
	}

	$abo = wc_get_product( $abo_id );
	return ( $abo && $abo->is_purchasable() && $abo->is_in_stock() );
}

/**
 * Rend l'encart de mise en avant de l'abonnement (titre + sous-titre + CTA).
 *
 * Markup BEM `.cart-crosssells`, fond accent, décliné par modificateur :
 *  - `full`   : page panier, pleine largeur (texte + CTA côte à côte ≥ tablette) ;
 *  - `drawer` : mini-panier latéral, compact (empilé).
 *
 * Retourne une chaîne vide si l'abonnement n'est pas un cross-sell actif du
 * panier (cf. `_180c_crosssell_is_offered()`).
 *
 * @param string $variant Variante d'affichage : 'full' (défaut) | 'drawer'.
 * @return string HTML échappé, ou chaîne vide.
 */
function _180c_crosssell_module_html( string $variant = 'full' ): string {
	if ( ! _180c_crosssell_is_offered() ) {
		return '';
	}

	$variant = ( 'drawer' === $variant ) ? 'drawer' : 'full';
	$abo_id  = _180c_crosssell_subscription_id();
	$abo     = wc_get_product( $abo_id );

	$price = wp_strip_all_tags( wc_price( (float) $abo->get_price() ) );

	$title = (string) apply_filters(
		'180c/crosssell_title',
		__( 'Complétez avec un abonnement numérique', '180c' ),
		$variant
	);

	$subtitle = (string) apply_filters(
		'180c/crosssell_subtitle',
		sprintf(
			/* translators: %s: prix mensuel de l'abonnement, ex. « 2,99 € ». */
			__( 'Toutes nos recettes en illimité dès %s/mois, sans engagement.', '180c' ),
			$price
		),
		$variant
	);

	$cta_text = (string) apply_filters(
		'180c/crosssell_cta',
		__( "Ajouter l'abonnement", '180c' ),
		$variant
	);

	$link_text = (string) apply_filters(
		'180c/crosssell_link',
		__( 'En savoir plus', '180c' ),
		$variant
	);

	// Lien « En savoir plus » vers la page abonnement (filtrable). Style :
	// même type que le texte, juste souligné (cf. .cart-crosssells__link).
	$link_url  = (string) apply_filters( '180c/crosssell_link_url', home_url( '/abonnement/' ) );
	$link_html = sprintf(
		'<a class="cart-crosssells__link" href="%s">%s</a>',
		esc_url( $link_url ),
		esc_html( $link_text )
	);

	$cta_html = sprintf(
		'<a class="cart-crosssells__cta add_to_cart_button ajax_add_to_cart" href="%s" data-quantity="1" data-product_id="%s" rel="nofollow">%s</a>',
		esc_url( $abo->add_to_cart_url() ),
		esc_attr( (string) $abo_id ),
		esc_html( $cta_text )
	);

	// Titre en <h2> sur la page panier (section de contenu) ; en <p> dans le
	// mini-panier (dialog) pour ne pas perturber la hiérarchie de titres.
	$title_tag = ( 'full' === $variant ) ? 'h2' : 'p';

	ob_start();
	?>
	<section class="cart-crosssells cart-crosssells--<?php echo esc_attr( $variant ); ?>"
		aria-label="<?php esc_attr_e( 'Suggestion : abonnement', '180c' ); ?>">
		<div class="cart-crosssells__text">
			<<?php echo esc_html( $title_tag ); ?> class="cart-crosssells__title"><?php echo esc_html( $title ); ?></<?php echo esc_html( $title_tag ); ?>>
			<?php if ( $subtitle ) : ?>
				<p class="cart-crosssells__subtitle">
					<?php
					echo esc_html( $subtitle );
					// Page panier : le lien suit directement le texte (inline).
					if ( 'full' === $variant ) {
						echo ' ' . $link_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit/échappé ci-dessus.
					}
					?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( 'drawer' === $variant ) : ?>
			<div class="cart-crosssells__actions">
				<?php
				echo $cta_html;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit/échappé ci-dessus.
				echo $link_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit/échappé ci-dessus.
				?>
			</div>
		<?php else : ?>
			<?php echo $cta_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit/échappé ci-dessus. ?>
		<?php endif; ?>
	</section>
	<?php

	return (string) ob_get_clean();
}
