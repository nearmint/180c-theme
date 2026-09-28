<?php
/**
 * Mon Compte — Mes commandes.
 *
 * Tableau des commandes Woo : ID, date, montant, statut, actions.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_orders' ) ) {
	return;
}

$user_id = get_current_user_id();
$orders  = wc_get_orders(
	array(
		'customer_id' => $user_id,
		'limit'       => 20,
		'orderby'     => 'date',
		'order'       => 'DESC',
	)
);
?>

<div class="account-commandes">

	<h1 class="account-commandes__title"><?php esc_html_e( 'Mes commandes', '180c' ); ?></h1>

	<?php if ( ! empty( $orders ) ) : ?>

		<table class="account-commandes__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Commande', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Montant', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Statut', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', '180c' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order ) : ?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Commande', '180c' ); ?>">
							<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
								#<?php echo esc_html( $order->get_order_number() ); ?>
							</a>
						</td>
						<td data-title="<?php esc_attr_e( 'Date', '180c' ); ?>">
							<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Montant', '180c' ); ?>">
							<?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Statut', '180c' ); ?>">
							<span class="account-commandes__status wc-<?php echo esc_attr( $order->get_status() ); ?>">
								<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
							</span>
						</td>
						<td data-title="<?php esc_attr_e( 'Actions', '180c' ); ?>">
							<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="btn btn--secondary btn--sm">
								<?php esc_html_e( 'Voir', '180c' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php else : ?>

		<div class="account-commandes__empty">
			<p><?php esc_html_e( 'Vous n\'avez pas encore passé de commande.', '180c' ); ?></p>
			<a href="<?php echo esc_url( _180c_shop_url() ); ?>" class="btn btn--primary">
				<?php esc_html_e( 'Découvrir la boutique', '180c' ); ?>
			</a>
		</div>

	<?php endif; ?>

</div>
