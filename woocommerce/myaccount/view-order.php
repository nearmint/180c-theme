<?php
/**
 * View Order — surcharge 180°C (DS).
 *
 * Rend DANS le shell .my-account via my-account.php. Le détail de commande
 * (tableau articles + totaux + téléchargements + adresses) reste produit par
 * le hook natif `woocommerce_view_order` (templates order-details*.php non
 * surchargés) ; on enveloppe en .my-account-section et on style les tables WC
 * via my-account.css (.my-account__content .woocommerce-table…).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package 180c
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$notes = $order->get_customer_order_notes();
?>

<a class="account-back-link" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
	<span class="account-back-link__icon" aria-hidden="true">&larr;</span>
	<span class="account-back-link__label"><?php esc_html_e( 'Retour à mon compte', '180c' ); ?></span>
</a>

<section class="my-account-section my-account-section--order" aria-labelledby="view-order-title">
	<header class="my-account-section__header">
		<h2 id="view-order-title" class="my-account-section__title">
			<?php
			printf(
				/* translators: %s: numéro de commande. */
				esc_html__( 'Commande #%s', '180c' ),
				esc_html( $order->get_order_number() )
			);
			?>
		</h2>
		<p class="my-account-section__lead">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: 1: order date 2: order status */
					esc_html__( 'Passée le %1$s — statut : %2$s.', '180c' ),
					'<time datetime="' . esc_attr( $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '' ) . '">' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . '</time>',
					'<span class="my-account-status my-account-status--' . esc_attr( $order->get_status() ) . '">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span>'
				)
			);
			?>
		</p>
	</header>

	<?php if ( $notes ) : ?>
		<h3 class="my-account-section__subtitle"><?php esc_html_e( 'Suivi de la commande', '180c' ); ?></h3>
		<ol class="woocommerce-OrderUpdates commentlist notes my-account-order-notes">
			<?php foreach ( $notes as $note ) : ?>
			<li class="woocommerce-OrderUpdate comment note">
				<div class="woocommerce-OrderUpdate-inner comment_container">
					<div class="woocommerce-OrderUpdate-text comment-text">
						<p class="woocommerce-OrderUpdate-meta meta"><?php echo date_i18n( esc_html__( 'l j F Y, H\hi', '180c' ), strtotime( $note->comment_date ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<div class="woocommerce-OrderUpdate-description description">
							<?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
						</div>
						<div class="clear"></div>
					</div>
					<div class="clear"></div>
				</div>
			</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<?php
	/**
	 * Détail de commande natif WC (tableau articles + totaux + downloads +
	 * adresses facturation/livraison). Hooks préservés.
	 *
	 * @since 2.6.0
	 */
	do_action( 'woocommerce_view_order', $order_id );
	?>
</section>
