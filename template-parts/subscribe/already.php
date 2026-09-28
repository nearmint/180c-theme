<?php
/**
 * Page Abonnement — bandeau d'état d'abonnement (sous le H1).
 *
 * Bandeau pleine largeur, AUTO-GARDÉ : rendu selon l'état d'abonnement de
 * l'utilisateur courant. Accès en cours (abonnement actif ou en résiliation) →
 * rappel « déjà abonné » (texte seul). Sinon, invitation contextuelle :
 *   - pending : paiement à finaliser (+ CTA « Finaliser le paiement ») ;
 *   - on_hold : abonnement en pause (+ CTA « Réactiver mon abonnement ») ;
 *   - ended   : abonnement terminé (texte seul ; les offres de la page servent
 *               de CTA).
 * Visiteur sans abonnement → aucun bandeau (return). Écriture neutre (non genrée).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_has_access = function_exists( '_180c_user_has_recipe_access' ) && _180c_user_has_recipe_access();
$_180c_state_data = function_exists( '_180c_get_subscription_display_state' )
	? _180c_get_subscription_display_state()
	: array(
		'state'         => '',
		'payment_url'   => '',
		'reactivate_id' => 0,
	);

// Accès en cours (abonnement actif ou résiliation en cours) → bandeau « déjà
// abonné » ; sinon, l'état réel pilote le message contextuel.
$_180c_banner_state = $_180c_has_access ? 'active' : $_180c_state_data['state'];

// Visiteur sans abonnement : aucun bandeau (page de vente par défaut).
if ( '' === $_180c_banner_state ) {
	return;
}

$_180c_sub_name = function_exists( '_180c_user_active_subscription_name' ) ? _180c_user_active_subscription_name() : '';

// Le nom du produit commence par « Abonnement » : on le rogne pour ne pas
// répéter le mot déjà présent dans la phrase du bandeau « déjà abonné ».
if ( '' !== $_180c_sub_name && function_exists( '_180c_subscription_name_without_prefix' ) ) {
	$_180c_sub_name = _180c_subscription_name_without_prefix( $_180c_sub_name );
}
?>
<aside class="subscribe-banner" role="status">
	<?php if ( 'pending' === $_180c_banner_state ) : ?>
		<p class="subscribe-banner__text">
			<?php esc_html_e( 'Votre abonnement est en attente de paiement. Finalisez le règlement pour activer votre accès à toutes nos recettes.', '180c' ); ?>
		</p>
		<?php if ( '' !== $_180c_state_data['payment_url'] ) : ?>
			<div class="subscribe-banner__action">
				<a class="btn btn--primary" href="<?php echo esc_url( $_180c_state_data['payment_url'] ); ?>">
					<?php esc_html_e( 'Finaliser le paiement', '180c' ); ?>
				</a>
			</div>
		<?php endif; ?>

	<?php elseif ( 'on_hold' === $_180c_banner_state ) : ?>
		<?php if ( '' !== $_180c_state_data['payment_url'] ) : ?>
			<?php /* Pause sur renouvellement impayé → régularisation du paiement. */ ?>
			<p class="subscribe-banner__text">
				<?php esc_html_e( 'Votre abonnement est suspendu : un paiement est en attente. Régularisez-le pour retrouver l\'accès à toutes nos recettes.', '180c' ); ?>
			</p>
			<div class="subscribe-banner__action">
				<a class="btn btn--primary" href="<?php echo esc_url( $_180c_state_data['payment_url'] ); ?>">
					<?php esc_html_e( 'Régler mon paiement', '180c' ); ?>
				</a>
			</div>
		<?php else : ?>
			<?php /* Pause manuelle (rien dû) → réactivation. */ ?>
			<p class="subscribe-banner__text">
				<?php esc_html_e( 'Votre abonnement est actuellement en pause. Réactivez-le pour retrouver l\'accès à toutes nos recettes.', '180c' ); ?>
			</p>
			<?php if ( $_180c_state_data['reactivate_id'] > 0 ) : ?>
				<div class="subscribe-banner__action">
					<button
						type="button"
						class="btn btn--primary"
						data-confirm
						data-confirm-post="subscription/reactivate"
						data-confirm-body="<?php echo esc_attr( wp_json_encode( array( 'subscription_id' => (int) $_180c_state_data['reactivate_id'] ) ) ); ?>"
						data-confirm-title="<?php esc_attr_e( 'Réactiver votre abonnement ?', '180c' ); ?>"
						data-confirm-message="<?php esc_attr_e( 'Souhaitez-vous réactiver votre abonnement ? La facturation reprendra à la prochaine échéance.', '180c' ); ?>"
						data-confirm-confirm-label="<?php esc_attr_e( 'Réactiver', '180c' ); ?>"
					>
						<?php esc_html_e( 'Réactiver mon abonnement', '180c' ); ?>
					</button>
				</div>
			<?php endif; ?>
		<?php endif; ?>

	<?php elseif ( 'ended' === $_180c_banner_state ) : ?>
		<p class="subscribe-banner__text">
			<?php esc_html_e( 'Votre abonnement a pris fin. Réabonnez-vous pour retrouver l\'accès à toutes nos recettes.', '180c' ); ?>
		</p>

	<?php else : // 'active' — accès en cours. ?>
		<p class="subscribe-banner__text">
			<?php
			if ( '' !== $_180c_sub_name ) {
				printf(
					/* translators: %s : nom du produit d'abonnement actif. */
					esc_html__( 'Vous avez déjà l’abonnement %s.', '180c' ),
					'<strong>' . esc_html( $_180c_sub_name ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nom déjà échappé via esc_html().
				);
			} else {
				esc_html_e( 'Vous avez déjà un abonnement actif.', '180c' );
			}
			?>
		</p>
	<?php endif; ?>
</aside>
