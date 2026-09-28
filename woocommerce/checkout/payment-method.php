<?php
/**
 * Single payment method — surcharge 180°C (item d'accordéon design system).
 *
 * Reprend le pattern visuel de l'accordéon DS (/abonnement/, Centre d'aide) :
 * chaque méthode = un item dont l'en-tête cliquable (le `<label>` natif) porte
 * l'intitulé à gauche, le logo à droite et un chevron ; le `.payment_box` est le
 * panneau. L'OUVERTURE/FERMETURE du panneau reste 100 % pilotée par le JS de
 * WooCommerce (slideUp/slideDown sur `div.payment_box.payment_method_<id>` au
 * `change` du radio) — on ne force jamais son `display` en CSS et le JS du thème
 * ne fait que refléter l'état (aria-expanded + chevron).
 *
 * Invariants WooCommerce STRICTEMENT préservés (sinon AJAX/soumission cassés) :
 *   - `li.wc_payment_method.payment_method_<id>`
 *   - `input#payment_method_<id>.input-radio[name="payment_method"][value=<id>]`
 *     + `data-order_button_text` + `checked()`
 *   - `div.payment_box.payment_method_<id>`
 *   - `label[for="payment_method_<id>"]`
 *
 * Adaptations 180°C : classes de style additionnelles (`checkout-payment__*`),
 * réorganisation du contenu du `<label>` (titre / logo / chevron) et liaison
 * ARIA disclosure (aria-controls + aria-expanded) entre l'en-tête et le panneau.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package 180c
 * @version 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$_180c_has_box = $gateway->has_fields() || $gateway->get_description();
$_180c_box_id  = 'wc-payment-box-' . $gateway->id;
?>
<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?> checkout-payment__item">
	<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />

	<label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>" class="checkout-payment__header"
		<?php if ( $_180c_has_box ) : ?>
			aria-controls="<?php echo esc_attr( $_180c_box_id ); ?>" aria-expanded="<?php echo $gateway->chosen ? 'true' : 'false'; ?>"
		<?php endif; ?>
	>
		<span class="checkout-payment__title">
			<?php echo $gateway->get_title(); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_title() échappé par WooCommerce + filtre woocommerce_gateway_title. */ ?>
		</span>
		<?php
		$_180c_icon = $gateway->get_icon();
		if ( $_180c_icon ) :
			?>
			<span class="checkout-payment__logo">
				<?php echo $_180c_icon; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML d'icône fourni par la passerelle / filtre woocommerce_gateway_icon. */ ?>
			</span>
		<?php endif; ?>
		<?php if ( $gateway->has_fields() ) : /* Chevron seulement si le panneau contient des champs à déplier (Stripe). PayPal n'a qu'un panneau informatif → pas de chevron. */ ?>
			<span class="checkout-payment__chevron" aria-hidden="true">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" focusable="false"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
		<?php endif; ?>
	</label>

	<?php if ( $_180c_has_box ) : ?>
		<div id="<?php echo esc_attr( $_180c_box_id ); ?>" class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?> checkout-payment__box" <?php if ( ! $gateway->chosen ) : /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>style="display:none;"<?php endif; /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>>
			<?php $gateway->payment_fields(); ?>
		</div>
	<?php endif; ?>
</li>
