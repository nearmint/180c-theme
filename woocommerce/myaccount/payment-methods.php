<?php
/**
 * Payment methods — surcharge 180°C (DS).
 *
 * Rend DANS le shell .my-account via my-account.php. Le tableau WC et ses
 * hooks/actions (jeton par défaut, suppression…) sont conservés à l'identique ;
 * mise au DS via .my-account-table (my-account.css). Bouton « Ajouter » en
 * style DS.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package 180c
 * @version 8.9.0
 */

defined( 'ABSPATH' ) || exit;

$saved_methods = wc_get_customer_saved_methods_list( get_current_user_id() );
$has_methods   = (bool) $saved_methods;
$types         = wc_get_account_payment_methods_types();

do_action( 'woocommerce_before_account_payment_methods', $has_methods );
?>

<a class="account-back-link" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) . '#abonnement' ); ?>">
	<span class="account-back-link__icon" aria-hidden="true">&larr;</span>
	<span class="account-back-link__label"><?php esc_html_e( 'Retour à mon compte', '180c' ); ?></span>
</a>

<section class="my-account-section" aria-labelledby="payment-methods-title">
	<header class="my-account-section__header">
		<h2 id="payment-methods-title" class="my-account-section__title"><?php esc_html_e( 'Moyens de paiement', '180c' ); ?></h2>
	</header>

	<?php if ( $has_methods ) : ?>

		<table class="woocommerce-MyAccount-paymentMethods shop_table shop_table_responsive account-payment-methods-table my-account-table">
			<thead class="my-account-table__head">
				<tr>
					<?php foreach ( wc_get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
						<th class="woocommerce-PaymentMethod woocommerce-PaymentMethod--<?php echo esc_attr( $column_id ); ?> payment-method-<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<?php foreach ( $saved_methods as $type => $methods ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
				<?php foreach ( $methods as $method ) : ?>
					<tr class="payment-method my-account-table__row<?php echo ! empty( $method['is_default'] ) ? ' default-payment-method' : ''; ?>">
						<?php foreach ( wc_get_account_payment_methods_columns() as $column_id => $column_name ) : ?>
							<td class="woocommerce-PaymentMethod woocommerce-PaymentMethod--<?php echo esc_attr( $column_id ); ?> payment-method-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
								<?php
								if ( has_action( 'woocommerce_account_payment_methods_column_' . $column_id ) ) {
									do_action( 'woocommerce_account_payment_methods_column_' . $column_id, $method );
								} elseif ( 'method' === $column_id ) {
									if ( ! empty( $method['method']['last4'] ) ) {
										/* translators: 1: credit card type 2: last 4 digits */
										echo sprintf( esc_html__( '%1$s se terminant par %2$s', '180c' ), esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) ), esc_html( $method['method']['last4'] ) );
									} else {
										echo esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) );
									}
								} elseif ( 'expires' === $column_id ) {
									echo esc_html( $method['expires'] );
								} elseif ( 'actions' === $column_id ) {
									foreach ( $method['actions'] as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
										echo '<a href="' . esc_url( $action['url'] ) . '" class="my-account-actions__btn ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>&nbsp;';
									}
								}
								?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</table>

	<?php else : ?>

		<p class="my-account-empty"><?php esc_html_e( 'Aucun moyen de paiement enregistré.', '180c' ); ?></p>

	<?php endif; ?>

	<?php do_action( 'woocommerce_after_account_payment_methods', $has_methods ); ?>

	<?php if ( WC()->payment_gateways->get_available_payment_gateways() ) : ?>
		<p class="my-account-form__actions">
			<a class="my-account-actions__btn my-account-actions__btn--primary" href="<?php echo esc_url( wc_get_endpoint_url( 'add-payment-method' ) ); ?>"><?php esc_html_e( 'Ajouter un moyen de paiement', '180c' ); ?></a>
		</p>
	<?php endif; ?>
</section>
