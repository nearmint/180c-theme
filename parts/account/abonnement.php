<?php
/**
 * Mon Compte — Mon abonnement.
 *
 * Détails de l'abonnement actif (plan, dates, statut, moyen de paiement),
 * boutons pause/résiliation via WC Subscriptions natif.
 * Si pas d'abonnement : CTA "Découvrir l'abonnement".
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
	return;
}

$user_id         = get_current_user_id();
$has_membership  = false;
$membership      = null;
$membership_plan = 'abonne-recettes';

if ( function_exists( 'wc_memberships_get_user_active_memberships' ) ) {
	$memberships = wc_memberships_get_user_active_memberships( $user_id );
	foreach ( $memberships as $m ) {
		if ( $m->get_plan()->get_slug() === $membership_plan ) {
			$has_membership = true;
			$membership     = $m;
			break;
		}
	}
}

// Récupère la souscription WC Subscriptions liée.
$subscriptions = array();
if ( function_exists( 'wcs_get_subscriptions' ) ) {
	$subscriptions = wcs_get_subscriptions(
		array(
			'customer_id'            => $user_id,
			'subscriptions_per_page' => 10,
		)
	);
}
?>

<div class="account-abonnement">

	<h1 class="account-abonnement__title"><?php esc_html_e( 'Mon abonnement', '180c' ); ?></h1>

	<?php if ( $has_membership && $membership ) : ?>

		<?php /* ---- Détails de l'abonnement ---- */ ?>
		<section class="account-abonnement__details" aria-labelledby="abo-details-title">
			<h2 id="abo-details-title" class="account-abonnement__section-title">
				<?php esc_html_e( 'Abonnement actif', '180c' ); ?>
			</h2>

			<dl class="account-abonnement__meta">
				<div class="account-abonnement__meta-row">
					<dt><?php esc_html_e( 'Plan', '180c' ); ?></dt>
					<dd><?php echo esc_html( $membership->get_plan()->get_name() ); ?></dd>
				</div>
				<div class="account-abonnement__meta-row">
					<dt><?php esc_html_e( 'Statut', '180c' ); ?></dt>
					<dd class="account-abonnement__status account-abonnement__status--<?php echo esc_attr( $membership->get_status() ); ?>">
						<?php echo esc_html( wc_memberships_get_user_membership_status_name( $membership->get_status() ) ); ?>
					</dd>
				</div>
				<?php
				$start_date = $membership->get_start_date();
				if ( $start_date ) :
					?>
					<div class="account-abonnement__meta-row">
						<dt><?php esc_html_e( 'Depuis le', '180c' ); ?></dt>
						<dd><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $start_date ) ) ); ?></dd>
					</div>
				<?php endif; ?>
				<?php
				$end_date = $membership->get_end_date();
				if ( $end_date ) :
					?>
					<div class="account-abonnement__meta-row">
						<dt><?php esc_html_e( 'Renouvellement le', '180c' ); ?></dt>
						<dd><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $end_date ) ) ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
		</section>

		<?php /* ---- Souscriptions WC Subscriptions ---- */ ?>
		<?php if ( ! empty( $subscriptions ) ) : ?>
			<section class="account-abonnement__subscriptions" aria-labelledby="abo-subs-title">
				<h2 id="abo-subs-title" class="account-abonnement__section-title">
					<?php esc_html_e( 'Détails de paiement', '180c' ); ?>
				</h2>

				<?php foreach ( $subscriptions as $subscription ) : ?>
					<div class="account-abonnement__sub-item">
						<dl class="account-abonnement__meta">
							<div class="account-abonnement__meta-row">
								<dt><?php esc_html_e( 'Moyen de paiement', '180c' ); ?></dt>
								<dd><?php echo esc_html( $subscription->get_payment_method_title() ?: __( 'Non renseigné', '180c' ) ); ?></dd>
							</div>
							<div class="account-abonnement__meta-row">
								<dt><?php esc_html_e( 'Montant', '180c' ); ?></dt>
								<dd><?php echo $subscription->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
							</div>
						</dl>

						<?php /* Actions WC Subscriptions natives */ ?>
						<div class="account-abonnement__actions">
							<?php
							// Récupère les actions disponibles pour cette souscription.
							$actions = wcs_get_all_user_actions_for_subscription( $subscription, wp_get_current_user() );
							foreach ( $actions as $key => $action ) :
								?>
								<a
									href="<?php echo esc_url( $action['url'] ); ?>"
									class="btn btn--secondary btn--sm"
								>
									<?php echo esc_html( $action['name'] ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</section>

			<?php /* ---- Historique des renouvellements ---- */ ?>
			<section class="account-abonnement__history" aria-labelledby="abo-history-title">
				<h2 id="abo-history-title" class="account-abonnement__section-title">
					<?php esc_html_e( 'Historique des renouvellements', '180c' ); ?>
				</h2>

				<?php foreach ( $subscriptions as $subscription ) : ?>
					<?php
					$related_orders = $subscription->get_related_orders( 'all', 'renewal' );
					if ( empty( $related_orders ) ) {
						continue;
					}
					?>
					<table class="account-abonnement__history-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( '#', '180c' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Date', '180c' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Montant', '180c' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Statut', '180c' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $related_orders as $order_id ) : ?>
								<?php $renewal = wc_get_order( $order_id ); ?>
								<?php
								if ( ! $renewal ) {
									continue; }
								?>
								<tr>
									<td><?php echo esc_html( $renewal->get_order_number() ); ?></td>
									<td><?php echo esc_html( wc_format_datetime( $renewal->get_date_created() ) ); ?></td>
									<td><?php echo $renewal->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><?php echo esc_html( wc_get_order_status_name( $renewal->get_status() ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

	<?php else : ?>

		<?php /* ---- Pas d'abonnement actif ---- */ ?>
		<div class="account-abonnement__empty">
			<p><?php esc_html_e( 'Vous n\'avez pas d\'abonnement actif pour le moment.', '180c' ); ?></p>
			<p><?php esc_html_e( 'Abonnez-vous pour accéder à plus de 2 000 recettes exclusives.', '180c' ); ?></p>
			<a href="<?php echo esc_url( _180c_shop_url() ); ?>" class="btn btn--primary">
				<?php esc_html_e( 'Découvrir l\'abonnement', '180c' ); ?>
			</a>
		</div>

	<?php endif; ?>

</div>
