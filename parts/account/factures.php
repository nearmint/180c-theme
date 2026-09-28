<?php
/**
 * Mon Compte — Mes factures.
 *
 * Liste des PDF de factures via le plugin "PDF Invoices & Packing Slips
 * for WooCommerce". Utilise la meta _wpo_wcpdf_invoice_number si elle existe.
 * Fallback "Bientôt disponible" si le plugin n'est pas actif.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_orders' ) ) {
	return;
}

$user_id       = get_current_user_id();
$plugin_active = class_exists( 'WPO_WCPDF' ) || function_exists( 'wcpdf_get_invoice' );

$orders = wc_get_orders(
	array(
		'customer_id' => $user_id,
		'limit'       => 30,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'status'      => array( 'wc-completed', 'wc-processing' ),
	)
);
?>

<div class="account-factures">

	<h1 class="account-factures__title"><?php esc_html_e( 'Mes factures', '180c' ); ?></h1>

	<?php if ( ! $plugin_active ) : ?>

		<div class="account-factures__unavailable">
			<p><?php esc_html_e( 'La génération automatique de factures sera disponible très prochainement.', '180c' ); ?></p>
			<p>
				<?php
				echo wp_kses(
					__( 'Pour obtenir une facture dès maintenant, contactez-nous à <a href="mailto:contact@180c.fr">contact@180c.fr</a>.', '180c' ),
					array( 'a' => array( 'href' => array() ) )
				);
				?>
			</p>
		</div>

	<?php elseif ( empty( $orders ) ) : ?>

		<div class="account-factures__empty">
			<p><?php esc_html_e( 'Aucune facture disponible pour le moment.', '180c' ); ?></p>
		</div>

	<?php else : ?>

		<table class="account-factures__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'N° facture', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Commande', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Montant', '180c' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Télécharger', '180c' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $orders as $order ) :
					$order_id       = $order->get_id();
					$invoice_number = $order->get_meta( '_wpo_wcpdf_invoice_number' );

					// Ignore les commandes sans facture générée.
					if ( ! $invoice_number ) {
						continue;
					}

					// URL de téléchargement via WPO WCPDF si disponible.
					$download_url = '';
					if ( function_exists( 'wcpdf_get_document' ) ) {
						$document = wcpdf_get_document( 'invoice', $order );
						if ( $document && $document->is_allowed() ) {
							$download_url = wp_nonce_url(
								add_query_arg(
									array(
										'action'        => 'generate_wpo_wcpdf',
										'template_type' => 'invoice',
										'order_ids'     => $order_id,
									),
									admin_url( 'admin-ajax.php' )
								),
								'generate_wpo_wcpdf'
							);
						}
					}
					?>
					<tr>
						<td data-title="<?php esc_attr_e( 'N° facture', '180c' ); ?>">
							<?php echo esc_html( $invoice_number ); ?>
						</td>
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
						<td data-title="<?php esc_attr_e( 'Télécharger', '180c' ); ?>">
							<?php if ( $download_url ) : ?>
								<a href="<?php echo esc_url( $download_url ); ?>" class="btn btn--secondary btn--sm" target="_blank" rel="noopener">
									<?php esc_html_e( 'PDF', '180c' ); ?>
								</a>
							<?php else : ?>
								<span class="account-factures__unavailable-badge">
									<?php esc_html_e( 'Indisponible', '180c' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php endif; ?>

</div>
