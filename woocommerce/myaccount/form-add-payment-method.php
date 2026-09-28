<?php
/**
 * Add payment method form — surcharge 180°C (DS).
 *
 * Rend DANS le shell .my-account via my-account.php. La liste des passerelles
 * et leurs champs (Stripe…) sont conservés à l'identique (hooks, nonce) ;
 * enveloppe DS via .my-account-section + .my-account-form. L'`aria-label` de la
 * liste vient du cœur 10.9.0 (a11y), libellé traduit en dur.
 *
 * Écart assumé avec le cœur : le cas « aucune passerelle » rend un paragraphe
 * .my-account-empty dans la section DS, là où le cœur appelle wc_print_notice().
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package 180c
 * @version 10.9.0
 */

defined( 'ABSPATH' ) || exit;

$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
?>

<section class="my-account-section" aria-labelledby="add-payment-title">
	<header class="my-account-section__header">
		<h2 id="add-payment-title" class="my-account-section__title"><?php esc_html_e( 'Ajouter un moyen de paiement', '180c' ); ?></h2>
	</header>

	<?php if ( $available_gateways ) : ?>
		<form id="add_payment_method" method="post" class="my-account-form my-account-form--payment">
			<div id="payment" class="woocommerce-Payment">
				<ul class="woocommerce-PaymentMethods payment_methods methods" aria-label="<?php esc_attr_e( 'Moyens de paiement', '180c' ); ?>">
					<?php
					// Chosen Method.
					if ( count( $available_gateways ) ) {
						current( $available_gateways )->set_current();
					}

					foreach ( $available_gateways as $gateway ) {
						?>
						<li class="woocommerce-PaymentMethod woocommerce-PaymentMethod--<?php echo esc_attr( $gateway->id ); ?> payment_method_<?php echo esc_attr( $gateway->id ); ?>">
							<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> />
							<label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>"><?php echo wp_kses_post( $gateway->get_title() ); ?> <?php echo wp_kses_post( $gateway->get_icon() ); ?></label>
							<?php
							if ( $gateway->has_fields() || $gateway->get_description() ) {
								echo '<div class="woocommerce-PaymentBox woocommerce-PaymentBox--' . esc_attr( $gateway->id ) . ' payment_box payment_method_' . esc_attr( $gateway->id ) . '" style="display: none;">';
								$gateway->payment_fields();
								echo '</div>';
							}
							?>
						</li>
						<?php
					}
					?>
				</ul>

				<?php do_action( 'woocommerce_add_payment_method_form_bottom' ); ?>

				<div class="form-row my-account-form__actions">
					<?php wp_nonce_field( 'woocommerce-add-payment-method', 'woocommerce-add-payment-method-nonce' ); ?>
					<button type="submit" class="woocommerce-Button button alt my-account-actions__btn my-account-actions__btn--primary" id="place_order" value="<?php esc_attr_e( 'Ajouter', '180c' ); ?>"><?php esc_html_e( 'Ajouter', '180c' ); ?></button>
					<input type="hidden" name="woocommerce_add_payment_method" id="woocommerce_add_payment_method" value="1" />
				</div>
			</div>
		</form>
	<?php else : ?>
		<p class="my-account-empty"><?php esc_html_e( "De nouveaux moyens de paiement ne peuvent être ajoutés que lors d'une commande. Contactez-nous si besoin.", '180c' ); ?></p>
	<?php endif; ?>
</section>
