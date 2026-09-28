<?php
/**
 * Checkout Form — surcharge 180°C.
 *
 * Layout empilé pleine largeur, dans l'ordre du tunnel :
 *  1. « Votre commande »   → récap produits + totaux (`#order_review`) ;
 *  2. « Déjà client ? »    → formulaire de connexion natif (lien repliable) ;
 *  3. « Vos informations » → facturation + livraison conditionnelle ;
 *  4. « Paiement »         → méthodes de paiement natives + CGV/RGPD + bouton.
 *
 * Particularités structurelles :
 *  - La card « Votre commande » (`#order_review`) est rendue HORS du
 *    `<form class="checkout">`. L'update AJAX de WooCommerce remplace les
 *    fragments par sélecteur CSS (`.woocommerce-checkout-review-order-table`,
 *    `.woocommerce-checkout-payment`), indépendamment de la position DOM : le
 *    récap continue donc de se rafraîchir. La sortir du form permet d'insérer
 *    le formulaire de connexion natif (un `<form>` distinct) juste au-dessus de
 *    « Vos informations » SANS imbriquer deux `<form>` (HTML invalide).
 *  - Le login natif est détaché du hook `woocommerce_before_checkout_form`
 *    (cf. `inc/checkout.php`) puis re-rendu ici via
 *    `woocommerce_checkout_login_form()`. Les notices, elles, restent en haut
 *    (toujours accrochées à `woocommerce_before_checkout_form`).
 *  - Le bloc paiement (`woocommerce_checkout_payment`) est détaché de
 *    `woocommerce_checkout_order_review` (cf. `inc/checkout.php`) et re-rendu
 *    dans la card « Paiement ».
 *
 * Tous les `do_action('woocommerce_checkout_*')` natifs sont préservés, en
 * particulier `woocommerce_checkout_before_customer_details` (Express Checkout
 * Apple/Google Pay via Stripe, prio 5). Classes WC requises par l'update AJAX
 * conservées (`form.checkout`, `#order_review`, `.woocommerce-checkout-review-order`).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="checkout-page">

	<?php /* Notices uniquement (login détaché → inc/checkout.php ; coupon masqué). */ ?>
	<?php do_action( 'woocommerce_before_checkout_form', $checkout ); ?>

	<?php
	// Si l'inscription est désactivée et l'utilisateur non connecté : commande
	// impossible. On affiche tout de même le formulaire de connexion (seul
	// moyen de continuer), puisqu'il n'est plus rendu par le hook du haut.
	if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
		woocommerce_checkout_login_form();
		echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'Vous devez être connecté pour finaliser votre commande.', '180c' ) ) );
		echo '</div>';
		return;
	}
	?>

	<?php /* Card 1 — Votre commande : récap produits + totaux. HORS <form> (cf. en-tête). */ ?>
	<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

	<section class="checkout__section checkout__section--summary" aria-labelledby="order_review_heading">
		<h2 id="order_review_heading" class="checkout__section-title">
			<?php esc_html_e( 'Votre commande', '180c' ); ?>
		</h2>

		<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

		<div id="order_review" class="woocommerce-checkout-review-order">
			<?php do_action( 'woocommerce_checkout_order_review' ); ?>
		</div>

		<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
	</section>

	<?php /* « Déjà client ? Cliquez ici pour vous connecter » — juste au-dessus de
	   « Vos informations », en frère du <form> de commande (jamais imbriqué). */ ?>
	<?php woocommerce_checkout_login_form(); ?>

	<form
		name="checkout"
		method="post"
		class="checkout woocommerce-checkout"
		action="<?php echo esc_url( wc_get_checkout_url() ); ?>"
		enctype="multipart/form-data"
		aria-label="<?php esc_attr_e( 'Finaliser la commande', '180c' ); ?>"
	>

		<?php /* Card 2 — Vos informations : facturation + livraison conditionnelle. */ ?>
		<?php if ( $checkout->get_checkout_fields() ) : ?>

			<?php /* Express Checkout (Apple/Google Pay via Stripe) — prio 5. */ ?>
			<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

			<div id="customer_details" class="checkout__customer">

				<section class="checkout__section" aria-labelledby="checkout-billing-heading">
					<h2 id="checkout-billing-heading" class="checkout__section-title">
						<?php esc_html_e( 'Vos informations', '180c' ); ?>
					</h2>
					<?php do_action( 'woocommerce_checkout_billing' ); ?>
				</section>

				<?php /* Livraison : uniquement si le panier contient des produits physiques. */ ?>
				<?php if ( WC()->cart->needs_shipping_address() ) : ?>
					<section class="checkout__section" aria-labelledby="checkout-shipping-heading">
						<h2 id="checkout-shipping-heading" class="checkout__section-title">
							<?php esc_html_e( 'Adresse de livraison', '180c' ); ?>
						</h2>
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</section>
				<?php endif; ?>

			</div>

			<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

		<?php endif; ?>

		<?php /* Card 3 — Paiement : woocommerce_checkout_payment() détaché de order_review (cf. en-tête + inc/checkout.php) ; wrapper .woocommerce-checkout-payment = cible du fragment AJAX, rafraîchissement intact. */ ?>
		<section class="checkout__section checkout__section--payment" aria-labelledby="checkout-payment-heading">
			<h2 id="checkout-payment-heading" class="checkout__section-title">
				<?php esc_html_e( 'Paiement', '180c' ); ?>
			</h2>
			<?php woocommerce_checkout_payment(); ?>
		</section>

	</form>

	<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

</div><?php /* .checkout-page */ ?>
