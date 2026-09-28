<?php
/**
 * Mini-panier 180°C — icône header, drawer off-canvas, mutations AJAX.
 *
 * Pièces :
 *  - helpers de rendu du corps du drawer (fragment rafraîchi) ;
 *  - fragments WooCommerce (badge header + corps du drawer) ;
 *  - handlers wc_ajax (set qty / remove) pour invités ET connectés ;
 *  - enqueue du module JS + exposition des endpoints/nonce.
 *
 * Slugs : on s'appuie sur wc_get_cart_url() / wc_get_checkout_url()
 * (slug-agnostiques) plutôt que sur /panier/ et /commande/ en dur.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Picto SVG inline pour le drawer (Feather-style, currentColor).
 *
 * Mutualise plus / minus / trash sans dépendre d'un fichier dans
 * src/images/icons/ (cohérent avec les SVG inline du header).
 *
 * @param string $name Nom du picto : 'plus' | 'minus' | 'trash'.
 * @return string Markup SVG inline, ou chaîne vide si inconnu.
 */
function _180c_cart_icon( $name ) {
	$open = '<svg class="cart-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';

	switch ( $name ) {
		case 'plus':
			$path = '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>';
			break;
		case 'minus':
			$path = '<line x1="5" y1="12" x2="19" y2="12"/>';
			break;
		case 'trash':
			$path = '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>';
			break;
		default:
			return '';
	}

	return $open . $path . '</svg>';
}

/**
 * Nombre d'articles dans le panier (0 si WooCommerce indisponible).
 *
 * @return int
 */
function _180c_cart_count() {
	return ( function_exists( 'WC' ) && WC() && WC()->cart )
		? (int) WC()->cart->get_cart_contents_count()
		: 0;
}

/**
 * Rend le wrapper de l'icône panier du header (bouton conditionnel + badge).
 *
 * Source unique réutilisée par le template (parts/header/main.php) ET par le
 * fragment WooCommerce, ciblé via `[data-cart-icon-wrap]`. Le wrapper reste
 * toujours présent ; seul le bouton interne est rendu à partir de 1 article,
 * ce qui permet au fragment de le faire apparaître / disparaître.
 *
 * @return string HTML du wrapper.
 */
function _180c_cart_header_button_html() {
	$count = _180c_cart_count();

	$icon = '<svg class="site-header__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. '<circle cx="9" cy="20" r="1"/>'
		. '<circle cx="18" cy="20" r="1"/>'
		. '<path d="M2 3h2.2l1.9 11.1a1.8 1.8 0 0 0 1.8 1.5h8.7a1.8 1.8 0 0 0 1.8-1.4L21 7H5.4"/>'
		. '</svg>';

	ob_start();
	?>
	<div class="site-header__cart-wrap" data-cart-icon-wrap>
		<?php if ( $count > 0 ) : ?>
			<button
				type="button"
				class="site-header__cart js-cart-toggle"
				aria-haspopup="dialog"
				aria-controls="cart-drawer"
				aria-expanded="false"
				aria-label="<?php esc_attr_e( 'Panier', '180c' ); ?>"
			>
				<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="site-header__cart-badge" data-cart-count><?php echo esc_html( (string) $count ); ?></span>
			</button>
		<?php endif; ?>
	</div><!-- .site-header__cart-wrap -->
	<?php

	return (string) ob_get_clean();
}

/**
 * Rend le corps du drawer (lignes + sous-total + CTA, ou état vide).
 *
 * Ce wrapper porte le sélecteur stable `.js-cart-drawer-body` : il est
 * remplacé tel quel (outerHTML) par le fragment WooCommerce après chaque
 * mutation, ce qui garde le drawer synchronisé sans rechargement.
 *
 * Règle photo : miniature en `object-fit: contain` (CSS) sur une source
 * non recadrée (taille `medium`, bornée et non hard-cropped) → l'image
 * n'est jamais recadrée ni recouverte.
 *
 * @return string HTML du corps du drawer.
 */
function _180c_cart_drawer_body() {
	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
		return '<div class="cart-drawer__body js-cart-drawer-body" data-cart-drawer-body></div>';
	}

	$cart = WC()->cart;

	ob_start();
	?>
	<div class="cart-drawer__body js-cart-drawer-body" data-cart-drawer-body>
		<?php if ( $cart->is_empty() ) : ?>

			<div class="cart-drawer__empty">
				<p class="cart-drawer__empty-text"><?php esc_html_e( 'Votre panier est vide.', '180c' ); ?></p>
				<a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', _180c_shop_url() ) ); ?>" class="btn btn--primary cart-drawer__empty-cta">
					<?php esc_html_e( 'Découvrir la boutique', '180c' ); ?>
				</a>
			</div>

		<?php else : ?>

			<ul class="cart-drawer__items" role="list">
				<?php
				foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
					$product  = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
					$quantity = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;

					if ( ! $product instanceof WC_Product || ! $product->exists() || $quantity <= 0 ) {
						continue;
					}

					/** Filtre WC standard : permet aux extensions de masquer une ligne. */
					if ( ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
						continue;
					}

					$product_name      = apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key );
					$product_permalink = $product->is_visible() ? $product->get_permalink( $cart_item ) : '';
					$line_subtotal     = apply_filters(
						'woocommerce_cart_item_subtotal',
						$cart->get_product_subtotal( $product, $quantity ),
						$cart_item,
						$cart_item_key
					);
					$thumbnail         = apply_filters(
						'woocommerce_cart_item_thumbnail',
						$product->get_image( 'medium', array( 'class' => 'cart-line__thumb-img' ) ),
						$cart_item,
						$cart_item_key
					);
					$item_data         = wc_get_formatted_cart_item_data( $cart_item, true );
					?>
					<li class="cart-line" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>">

						<div class="cart-line__thumb">
							<?php if ( $product_permalink ) : ?>
								<a href="<?php echo esc_url( $product_permalink ); ?>" tabindex="-1" aria-hidden="true">
									<?php echo wp_kses_post( $thumbnail ); ?>
								</a>
							<?php else : ?>
								<?php echo wp_kses_post( $thumbnail ); ?>
							<?php endif; ?>
						</div>

						<div class="cart-line__detail">
							<p class="cart-line__name">
								<?php if ( $product_permalink ) : ?>
									<a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo wp_kses_post( $product_name ); ?></a>
								<?php else : ?>
									<?php echo wp_kses_post( $product_name ); ?>
								<?php endif; ?>
							</p>

							<?php if ( $item_data ) : ?>
								<div class="cart-line__variation"><?php echo wp_kses_post( $item_data ); ?></div>
							<?php endif; ?>

							<div class="cart-line__bottom">
								<div class="cart-line__stepper" data-cart-stepper>
									<button
										type="button"
										class="cart-line__qty-btn js-cart-qty-dec"
										aria-label="<?php esc_attr_e( 'Diminuer la quantité', '180c' ); ?>"
										<?php disabled( $quantity, 1 ); ?>
									><?php echo _180c_cart_icon( 'minus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>

									<span class="cart-line__qty" data-cart-qty aria-live="polite"><?php echo esc_html( (string) $quantity ); ?></span>

									<button
										type="button"
										class="cart-line__qty-btn js-cart-qty-inc"
										aria-label="<?php esc_attr_e( 'Augmenter la quantité', '180c' ); ?>"
									><?php echo _180c_cart_icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								</div>

								<span class="cart-line__price"><?php echo wp_kses_post( $line_subtotal ); ?></span>
							</div>
						</div>

						<button
							type="button"
							class="cart-line__remove js-cart-remove"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %s: nom du produit */ __( 'Retirer %s du panier', '180c' ), wp_strip_all_tags( $product_name ) ) ); ?>"
						><?php echo _180c_cart_icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>

					</li>
					<?php
				}
				?>
			</ul>

			<?php
			// Encart abonnement (cross-sell) — juste au-dessus du séparateur du
			// pied. Rendu mutualisé avec la page panier ; ne s'affiche que si
			// l'abonnement est un cross-sell actif du panier. Inclus dans le
			// fragment `.js-cart-drawer-body` → apparaît / disparaît au fil des
			// mutations du panier.
			if ( function_exists( '_180c_crosssell_module_html' ) ) {
				echo _180c_crosssell_module_html( 'drawer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le renderer.
			}
			?>

			<div class="cart-drawer__footer">
				<div class="cart-drawer__subtotal">
					<span class="cart-drawer__subtotal-label"><?php esc_html_e( 'Sous-total', '180c' ); ?></span>
					<span class="cart-drawer__subtotal-amount" data-cart-subtotal><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></span>
				</div>

				<div class="cart-drawer__actions">
					<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="btn btn--ghost cart-drawer__action">
						<?php esc_html_e( 'Voir le panier', '180c' ); ?>
					</a>
					<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="btn btn--primary cart-drawer__action">
						<?php esc_html_e( 'Commander', '180c' ); ?>
					</a>
				</div>
			</div>

		<?php endif; ?>
	</div><!-- .cart-drawer__body -->
	<?php

	return (string) ob_get_clean();
}

/**
 * Fragments rafraîchis après chaque ajout / mutation panier.
 *
 * WooCommerce remplace l'élément ciblé par chaque sélecteur (outerHTML) par
 * la valeur retournée. On rafraîchit :
 *  - le wrapper de l'icône header (apparition / disparition + compteur) ;
 *  - le corps du drawer (lignes + sous-total + CTA, ou état vide).
 *
 * @param array $fragments Fragments existants.
 * @return array
 */
function _180c_cart_fragments( $fragments ) {
	$fragments['[data-cart-icon-wrap]'] = _180c_cart_header_button_html();
	$fragments['.js-cart-drawer-body']  = _180c_cart_drawer_body();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', '_180c_cart_fragments' );

/**
 * Émet la réponse JSON des mutations panier (fragments rafraîchis).
 *
 * Même forme que la réponse native `?wc-ajax=add_to_cart` (clé `fragments`
 * au premier niveau) → le JS applique un unique remplacement de fragments
 * quelle que soit la mutation. Termine la requête.
 *
 * @param array $extra Données additionnelles fusionnées dans la réponse JSON
 *                     (ex. payload `ga4_add` à l'ajout). Optionnel.
 * @return void
 */
function _180c_cart_send_fragments( $extra = array() ) {
	WC()->cart->calculate_totals();

	wp_send_json(
		array_merge(
			array(
				'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
				'cart_hash' => WC()->cart->get_cart_hash(),
			),
			(array) $extra
		)
	);
}

/**
 * Construit le payload GA4 `add_to_cart` d'un produit ajouté (L3, tracking).
 *
 * Renvoie un objet prêt pour `gtag('event', 'add_to_cart', …)` consommé par
 * src/js/modules/cart.js : currency + value + items[] au format canonique
 * (item_id, item_name, item_variant, price, quantity). cf TRACKING_PLAN.md §4.1.
 *
 * @param int $product_id   ID produit (parent).
 * @param int $variation_id ID variation (0 si simple).
 * @param int $quantity     Quantité ajoutée.
 * @return array|null Payload gtag, ou null si produit introuvable.
 */
function _180c_cart_ga4_add_payload( $product_id, $variation_id, $quantity ) {
	$product = wc_get_product( $variation_id ? $variation_id : $product_id );
	if ( ! $product ) {
		return null;
	}

	$price = (float) wc_get_price_to_display( $product );
	$item  = array(
		'item_id'   => (string) $product->get_id(),
		'item_name' => $product->get_name(),
		'price'     => $price,
		'quantity'  => (int) $quantity,
	);

	if ( $variation_id && $product->is_type( 'variation' ) ) {
		$item['item_variant'] = wp_strip_all_tags( wc_get_formatted_variation( $product, true, false ) );
	}

	return array(
		'currency' => get_woocommerce_currency(),
		'value'    => $price * (int) $quantity,
		'items'    => array( $item ),
	);
}

/**
 * Rend le bloc des totaux du panier (`.cart_totals`) en chaîne.
 *
 * Capture la sortie de `woocommerce_cart_totals()` (template core
 * cart/cart-totals.php) pour la renvoyer en fragment à la page panier.
 *
 * @return string HTML du bloc `.cart_totals`, ou chaîne vide.
 */
function _180c_cart_totals_html() {
	if ( ! function_exists( 'woocommerce_cart_totals' ) ) {
		return '';
	}
	ob_start();
	woocommerce_cart_totals();
	return (string) ob_get_clean();
}

/**
 * Émet la réponse JSON d'une mutation depuis la page panier (`/panier/`).
 *
 * En plus des fragments header + drawer (partagés avec le drawer), renvoie le
 * bloc `.cart_totals` rafraîchi (fragment), le sous-total de la ligne mutée
 * (ou null si retirée) et un drapeau panier vide → le JS met à jour la page
 * sans rechargement (et recharge si le panier devient vide pour afficher le
 * template panier vide). Termine la requête.
 *
 * @param string|null $persisted_key Clé encore présente après mutation, ou null.
 * @return void
 */
function _180c_cart_send_page_response( $persisted_key ) {
	WC()->cart->calculate_totals();
	$cart = WC()->cart;

	$line_subtotal = null;
	if ( $persisted_key ) {
		$item = $cart->get_cart_item( $persisted_key );
		if ( $item && isset( $item['data'] ) ) {
			$line_subtotal = apply_filters(
				'woocommerce_cart_item_subtotal',
				$cart->get_product_subtotal( $item['data'], $item['quantity'] ),
				$item,
				$persisted_key
			);
		}
	}

	$fragments                 = apply_filters( 'woocommerce_add_to_cart_fragments', array() );
	$fragments['.cart_totals'] = _180c_cart_totals_html();

	wp_send_json(
		array(
			'fragments'     => $fragments,
			'line_subtotal' => $line_subtotal,
			'cart_empty'    => $cart->is_empty(),
			'cart_hash'     => $cart->get_cart_hash(),
		)
	);
}

/**
 * Contexte de la requête de mutation ('cart_page' depuis la page panier).
 *
 * @return string
 */
function _180c_cart_request_context() {
	// Nonce vérifié dans _180c_cart_ajax_guard() avant tout appel.
	return isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

/**
 * Vérifie le nonce + la disponibilité du panier ; sinon termine en erreur JSON.
 *
 * @return void
 */
function _180c_cart_ajax_guard() {
	if ( ! check_ajax_referer( '180c_cart', 'nonce', false ) ) {
		wp_send_json( array( 'error' => __( 'Session expirée, rechargez la page.', '180c' ) ), 403 );
	}

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json( array( 'error' => __( 'Panier indisponible.', '180c' ) ), 500 );
	}
}

/**
 * Handler wc_ajax : met à jour la quantité d'une ligne du panier.
 *
 * POST : nonce, cart_item_key, quantity. Une quantité ≤ 0 retire la ligne.
 * Fonctionne pour les invités comme les connectés (endpoint wc-ajax).
 *
 * @return void
 */
function _180c_cart_ajax_set_qty() {
	_180c_cart_ajax_guard();

	// Nonce vérifié dans _180c_cart_ajax_guard() ci-dessus ; wc_clean() + (int) assurent la sanitisation.
	$cart_item_key = isset( $_POST['cart_item_key'] ) ? wc_clean( wp_unslash( $_POST['cart_item_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$quantity      = isset( $_POST['quantity'] ) ? (int) wp_unslash( $_POST['quantity'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( '' === $cart_item_key || ! WC()->cart->get_cart_item( $cart_item_key ) ) {
		wp_send_json( array( 'error' => __( 'Article introuvable dans le panier.', '180c' ) ), 404 );
	}

	if ( $quantity <= 0 ) {
		WC()->cart->remove_cart_item( $cart_item_key );
		$persisted_key = null;
	} else {
		WC()->cart->set_quantity( $cart_item_key, $quantity, true );
		$persisted_key = $cart_item_key;
	}

	if ( 'cart_page' === _180c_cart_request_context() ) {
		_180c_cart_send_page_response( $persisted_key );
	}

	_180c_cart_send_fragments();
}
add_action( 'wc_ajax_180c_cart_set_qty', '_180c_cart_ajax_set_qty' );

/**
 * Handler wc_ajax : retire une ligne du panier.
 *
 * POST : nonce, cart_item_key. Invités et connectés (endpoint wc-ajax).
 *
 * @return void
 */
function _180c_cart_ajax_remove() {
	_180c_cart_ajax_guard();

	// Nonce vérifié dans _180c_cart_ajax_guard() ci-dessus ; wc_clean() sanitise la clé.
	$cart_item_key = isset( $_POST['cart_item_key'] ) ? wc_clean( wp_unslash( $_POST['cart_item_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( '' === $cart_item_key || ! WC()->cart->get_cart_item( $cart_item_key ) ) {
		wp_send_json( array( 'error' => __( 'Article introuvable dans le panier.', '180c' ) ), 404 );
	}

	WC()->cart->remove_cart_item( $cart_item_key );

	if ( 'cart_page' === _180c_cart_request_context() ) {
		_180c_cart_send_page_response( null );
	}

	_180c_cart_send_fragments();
}
add_action( 'wc_ajax_180c_cart_remove', '_180c_cart_ajax_remove' );

/**
 * Handler wc_ajax : ajoute un produit au panier (variations incluses).
 *
 * Le endpoint natif `?wc-ajax=add_to_cart` n'ajoute que `product_id` +
 * `quantity` (pas de variation). On lit ici aussi `variation_id` et les
 * attributs `attribute_*` pour gérer les produits variables depuis la fiche.
 * Invités et connectés (endpoint wc-ajax).
 *
 * POST : nonce, product_id (ou add-to-cart), quantity, variation_id, attribute_*.
 *
 * @return void
 */
function _180c_cart_ajax_add() {
	_180c_cart_ajax_guard();

	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce vérifié dans _180c_cart_ajax_guard() ; wc_clean()/wc_stock_amount()/absint() sanitisent.
	$product_id = 0;
	if ( isset( $_POST['product_id'] ) ) {
		$product_id = absint( wp_unslash( $_POST['product_id'] ) );
	} elseif ( isset( $_POST['add-to-cart'] ) ) {
		$product_id = absint( wp_unslash( $_POST['add-to-cart'] ) );
	}
	$product_id   = apply_filters( 'woocommerce_add_to_cart_product_id', $product_id );
	$quantity     = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $_POST['quantity'] ) );
	$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;

	$variations = array();
	foreach ( $_POST as $key => $value ) {
		if ( 0 === strpos( (string) $key, 'attribute_' ) ) {
			$variations[ sanitize_text_field( wp_unslash( $key ) ) ] = wc_clean( wp_unslash( $value ) );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$passed    = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
	$added_key = false;

	if ( $passed && $product_id > 0 ) {
		$added_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
	}

	if ( $added_key ) {
		do_action( 'woocommerce_ajax_added_to_cart', $product_id );
		$ga4_add = _180c_cart_ga4_add_payload( $product_id, $variation_id, $quantity );
		_180c_cart_send_fragments( $ga4_add ? array( 'ga4_add' => $ga4_add ) : array() );
	}

	// Échec : remonte les notices d'erreur WooCommerce (stock, validation…).
	$messages = array();
	if ( function_exists( 'wc_get_notices' ) ) {
		foreach ( wc_get_notices( 'error' ) as $notice ) {
			$messages[] = is_array( $notice ) ? $notice['notice'] : $notice;
		}
		wc_clear_notices();
	}

	wp_send_json(
		array(
			'error'   => true,
			'message' => $messages
				? wp_strip_all_tags( implode( ' ', $messages ) )
				: __( "Impossible d'ajouter ce produit au panier.", '180c' ),
		),
		400
	);
}
add_action( 'wc_ajax_180c_add_to_cart', '_180c_cart_ajax_add' );

/**
 * Exception abonnement : ne JAMAIS passer un produit d'abonnement en AJAX.
 *
 * La redirection abonnement → checkout est portée par
 * `_180c_subscribe_redirect_to_checkout()` (woocommerce_add_to_cart_redirect,
 * prio 20, inc/subscribe.php). Ce filtre-là ne se déclenche que sur le flux
 * GET natif `?add-to-cart=ID` — pas sur l'endpoint `?wc-ajax=add_to_cart`.
 *
 * Pour que l'exception « /abonnement/ → /commande/ » tienne aussi sur les
 * boutons en boucle (rails produits), on marque ici les liens d'abonnement
 * `data-no-ajax-cart` et on retire la classe `ajax_add_to_cart` : le module
 * JS (src/js/modules/cart.js) les ignore et laisse la navigation native
 * déclencher le redirect existant. Les liens de page-abonnement.php pointent
 * déjà nativement sur le checkout (cf. _180c_subscribe_add_to_cart_url()).
 *
 * @param array      $args    Arguments du lien add-to-cart en boucle.
 * @param WC_Product $product Produit courant.
 * @return array
 */
function _180c_cart_mark_subscription_loop( $args, $product ) {
	if ( function_exists( '_180c_subscribe_is_subscription_product' )
		&& _180c_subscribe_is_subscription_product( $product )
	) {
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}
		$args['attributes']['data-no-ajax-cart'] = 'true';

		if ( isset( $args['class'] ) ) {
			$args['class'] = trim( str_replace( 'ajax_add_to_cart', '', $args['class'] ) );
		}
	}

	return $args;
}
add_filter( 'woocommerce_loop_add_to_cart_args', '_180c_cart_mark_subscription_loop', 10, 2 );

/**
 * Expose les endpoints wc-ajax au module JS panier.
 *
 * Le JS (src/js/modules/cart.js) et le CSS du drawer voyagent dans le bundle
 * principal (`180c-main`, importés par main.js / main.css) : pas d'enqueue
 * séparé. On rattache ici un objet `window._180cCart` en inline AVANT le
 * handle `180c-data` (prio 5, déjà chargé avant le bundle module).
 *
 * Le nonce `180c_cart` n'est PLUS inliné : cette page est servie depuis le
 * cache statique de WP Super Cache, qui lui survit. `cart.js` le récupère à la
 * demande sur `GET /180c/v1/session` avant son premier envoi. `endpoints` reste
 * en revanche ici — c'est la clé que `cart.js` teste pour savoir si le panier
 * est actif sur la page (getConfig(), src/js/modules/cart.js).
 *
 * @return void
 */
function _180c_cart_enqueue_data() {
	if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_AJAX' ) ) {
		return;
	}

	$data = array(
		'endpoints' => array(
			'add'     => WC_AJAX::get_endpoint( '180c_add_to_cart' ),
			'set_qty' => WC_AJAX::get_endpoint( '180c_cart_set_qty' ),
			'remove'  => WC_AJAX::get_endpoint( '180c_cart_remove' ),
		),
	);

	// Rattaché à 180c-data si dispo (chargé avant le bundle), sinon au bundle.
	$handle = wp_script_is( '180c-data', 'registered' ) ? '180c-data' : '180c-main';

	wp_add_inline_script(
		$handle,
		'window._180cCart = ' . wp_json_encode( $data ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', '_180c_cart_enqueue_data', 20 );

/**
 * Désenregistre `wc-add-to-cart` côté front.
 *
 * Décision (Phase 0) : tout l'add-to-cart est géré en vanilla par
 * src/js/modules/cart.js (interception form + boucles, fetch ?wc-ajax=…,
 * remplacement de fragments, ouverture du drawer). On retire le script WC
 * natif pour éviter le double-binding sur `.ajax_add_to_cart`.
 *
 * Priorité 30 : passe APRÈS le filet de sécurité home (inc/enqueue.php, prio
 * 20) qui ré-enqueue ce handle, afin de gagner la course de désinscription.
 *
 * @return void
 */
function _180c_cart_dequeue_wc_add_to_cart() {
	if ( is_admin() ) {
		return;
	}
	wp_dequeue_script( 'wc-add-to-cart' );
}
add_action( 'wp_enqueue_scripts', '_180c_cart_dequeue_wc_add_to_cart', 30 );
