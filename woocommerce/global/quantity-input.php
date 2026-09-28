<?php
/**
 * Product quantity inputs — surcharge 180°C.
 *
 * Design system : stepper avec boutons - / +.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.1.0
 *
 * @var bool   $readonly If the input should be set to readonly mode.
 * @var string $type     The input type attribute.
 */

defined( 'ABSPATH' ) || exit;

/* translators: %s: Quantity. */
$label = ! empty( $args['product_name'] )
	? sprintf( esc_html__( 'Quantité de %s', '180c' ), wp_strip_all_tags( $args['product_name'] ) )
	: esc_html__( 'Quantité', '180c' );
?>
<div class="quantity qty-input">

	<?php do_action( 'woocommerce_before_quantity_input_field' ); ?>

	<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">
		<?php echo esc_attr( $label ); ?>
	</label>

	<?php if ( ! $readonly ) : ?>
		<button
			type="button"
			class="qty-input__btn qty-input__btn--minus"
			aria-label="<?php esc_attr_e( 'Diminuer la quantité', '180c' ); ?>"
			data-qty-minus
		>
			<span aria-hidden="true">−</span>
		</button>
	<?php endif; ?>

	<input
		type="<?php echo esc_attr( $type ); ?>"
		<?php echo $readonly ? 'readonly="readonly"' : ''; ?>
		id="<?php echo esc_attr( $input_id ); ?>"
		class="qty-input__field <?php echo esc_attr( join( ' ', (array) $classes ) ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		value="<?php echo esc_attr( $input_value ); ?>"
		aria-label="<?php esc_attr_e( 'Quantité du produit', '180c' ); ?>"
		<?php if ( in_array( $type, array( 'text', 'search', 'tel', 'url', 'email', 'password' ), true ) ) : ?>
			size="4"
		<?php endif; ?>
		min="<?php echo esc_attr( $min_value ); ?>"
		<?php if ( 0 < $max_value ) : ?>
			max="<?php echo esc_attr( $max_value ); ?>"
		<?php endif; ?>
		<?php if ( ! $readonly ) : ?>
			step="<?php echo esc_attr( $step ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			inputmode="<?php echo esc_attr( $inputmode ); ?>"
			autocomplete="<?php echo esc_attr( isset( $autocomplete ) ? $autocomplete : 'on' ); ?>"
		<?php endif; ?>
	/>

	<?php if ( ! $readonly ) : ?>
		<button
			type="button"
			class="qty-input__btn qty-input__btn--plus"
			aria-label="<?php esc_attr_e( 'Augmenter la quantité', '180c' ); ?>"
			data-qty-plus
		>
			<span aria-hidden="true">+</span>
		</button>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_quantity_input_field' ); ?>

</div>
