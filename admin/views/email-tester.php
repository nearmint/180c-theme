<?php
/**
 * Vue — Page « Test e-mails 180°C ».
 *
 * Variables fournies par _180c_email_tester_render_page() :
 *
 * @var array $all_emails  id => {title,description,class,customer,enabled,source}
 * @var array $test_data   Données de test (user, orders, subscriptions, memberships).
 * @var array $admin_users Administrateurs sélectionnables.
 * @var int   $tested_user ID utilisateur testé.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$grouped = array();
foreach ( $all_emails as $mail_id => $info ) {
	$grouped[ $info['source'] ][ $mail_id ] = $info;
}
?>
<div class="wrap email-tester">
	<h1><?php esc_html_e( 'Test e-mails 180°C', '180c' ); ?></h1>

	<p class="email-tester__intro">
		<?php
		printf(
			/* translators: %d = nombre d'e-mails */
			esc_html__( 'Prévisualisation en lecture seule des %d e-mails WooCommerce activés, avec les données réelles du compte testé. La prévisualisation s’ouvre dans un nouvel onglet. Aucun e-mail n’est envoyé.', '180c' ),
			count( $all_emails )
		);
		?>
	</p>

	<form method="get" class="email-tester__userbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( _180C_EMAIL_TESTER_SLUG ); ?>" />
		<label for="180c-tested-user"><strong><?php esc_html_e( 'Compte de test :', '180c' ); ?></strong></label>
		<select name="uid" id="180c-tested-user" onchange="this.form.submit()">
			<?php foreach ( $admin_users as $u ) : ?>
				<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( (int) $u->ID, $tested_user ); ?>>
					<?php echo esc_html( $u->display_name . ' — ' . $u->user_email ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<span class="email-tester__counts">
			<?php
			printf(
				/* translators: 1: commandes, 2: abonnements, 3: adhésions */
				esc_html__( '%1$d commandes · %2$d abonnements · %3$d adhésions', '180c' ),
				count( $test_data['orders'] ?? array() ),
				count( $test_data['subscriptions'] ?? array() ),
				count( $test_data['memberships'] ?? array() )
			);
			?>
		</span>
	</form>

	<?php foreach ( $grouped as $source => $emails ) : ?>
		<h2 class="email-tester__group"><?php echo esc_html( $source ); ?> <span>(<?php echo count( $emails ); ?>)</span></h2>
		<table class="widefat striped email-tester__table">
			<thead>
				<tr>
					<th class="col-id"><?php esc_html_e( 'ID', '180c' ); ?></th>
					<th><?php esc_html_e( 'Titre & description', '180c' ); ?></th>
					<th class="col-dest"><?php esc_html_e( 'Destinataire', '180c' ); ?></th>
					<th class="col-action"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $emails as $mail_id => $info ) : ?>
					<tr>
						<td class="col-id"><code><?php echo esc_html( $mail_id ); ?></code></td>
						<td>
							<strong><?php echo esc_html( $info['title'] ); ?></strong>
							<?php if ( $info['description'] ) : ?>
								<br /><small><?php echo esc_html( wp_strip_all_tags( $info['description'] ) ); ?></small>
							<?php endif; ?>
						</td>
						<td class="col-dest"><?php echo $info['customer'] ? esc_html__( 'Client', '180c' ) : esc_html__( 'Admin', '180c' ); ?></td>
						<td class="col-action">
							<a href="<?php echo esc_url( _180c_email_tester_preview_url( $mail_id, $tested_user ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-small">
								<?php esc_html_e( 'Prévisualiser ↗', '180c' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>
</div>
