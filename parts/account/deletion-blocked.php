<?php
/**
 * Mon compte — écran S0 : suppression impossible pour l'instant.
 *
 * Une ligne par blocage métier (abonnement, adhésion, commande en cours).
 * Aucun formulaire n'est rendu tant qu'un blocage subsiste.
 *
 * @package 180c
 *
 * @var array $args {
 *     @type array  $blockers      Blocages `{ key, message, url }`.
 *     @type string $contact_email Adresse de contact affichée en pied.
 * }
 */

defined( 'ABSPATH' ) || exit;

$blockers      = isset( $args['blockers'] ) && is_array( $args['blockers'] ) ? $args['blockers'] : array();
$contact_email = isset( $args['contact_email'] ) ? (string) $args['contact_email'] : '';
?>

<section class="c-account-deletion">

	<p class="c-account-deletion__intro">
		<?php esc_html_e( 'Vous ne pouvez pas encore supprimer votre compte.', '180c' ); ?>
	</p>

	<?php if ( ! empty( $blockers ) ) : ?>
		<ul class="c-account-deletion__list">
			<?php foreach ( $blockers as $blocker ) : ?>
				<li class="c-account-deletion__item">
					<?php echo esc_html( (string) $blocker['message'] ); ?>
					<?php if ( ! empty( $blocker['url'] ) ) : ?>
						<a class="c-account-deletion__link" href="<?php echo esc_url( (string) $blocker['url'] ); ?>">
							<?php esc_html_e( 'Gérer mon abonnement', '180c' ); ?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( '' !== $contact_email ) : ?>
		<p class="c-account-deletion__footnote">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: lien mailto vers l'adresse de contact. */
					__( 'Une question ? Écrivez-nous à %s.', '180c' ),
					'<a href="mailto:' . esc_attr( $contact_email ) . '">' . esc_html( $contact_email ) . '</a>'
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
		</p>
	<?php endif; ?>

</section>
