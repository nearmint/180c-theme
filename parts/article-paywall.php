<?php
/**
 * Template part : paywall article (premium éditorial).
 *
 * Partial DÉDIÉ aux articles (distinct de parts/recipe-paywall.php) mais
 * partageant le composant CSS mutualisé `.paywall` (paywall.css). Pas d'aperçu
 * flouté de lignes factices : c'est le 1er paragraphe réel de l'article qui
 * sert d'amorce (rendu juste au-dessus par _180c_gate_article_content()).
 *
 * Rendu 100% server-side : aucun contenu premium au-delà du 1er paragraphe
 * n'est envoyé au client non abonné. Inclus via get_template_part() depuis le
 * filtre the_content quand _180c_user_has_article_access() renvoie false.
 *
 * Émet data-event-fire="paywall_view" (consommé par analytics-ga4.js).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$post_id     = (int) get_the_ID();
$post_slug   = get_post_field( 'post_name', $post_id );
$current_url = get_permalink( $post_id ) ?: home_url( '/' );

/** Même filtre que le paywall recette (cohérence URL d'abonnement). */
$subscribe_base = apply_filters( '180c/paywall_subscribe_url', home_url( '/abonnement/' ) );
$subscribe_url  = esc_url(
	add_query_arg(
		array(
			'utm_source'   => 'paywall',
			'utm_medium'   => 'article',
			'utm_campaign' => 'single_article',
			'utm_content'  => $post_slug,
		),
		$subscribe_base
	)
);

$login_url = esc_url(
	add_query_arg(
		'redirect_to',
		rawurlencode( $current_url ),
		home_url( '/connexion/' )
	)
);

$account_sub_url = esc_url( home_url( '/mon-compte/abonnement/' ) );

$benefits = array(
	__( '1 500+ recettes de saison, sans publicité', '180c' ),
	__( 'Les Cahiers de Delphine, chaque vendredi', '180c' ),
	__( 'Portraits & reportages publiés dans 180°C', '180c' ),
	// APP-RELEASE : décommenter à la sortie des apps mobiles.
	// __( "L'app mobile incluse", '180c' ),
	__( 'Sans engagement, résiliable à tout moment', '180c' ),
);

/*
 * État d'abonnement de l'utilisateur (le paywall ne s'affiche qu'en l'absence
 * d'accès → state ∈ '' | pending | on_hold | ended). Pilote titre, texte et CTA.
 */
$sub_state = function_exists( '_180c_get_subscription_display_state' )
	? _180c_get_subscription_display_state()
	: array(
		'state'         => '',
		'payment_url'   => '',
		'reactivate_id' => 0,
	);
$pw_state  = $sub_state['state'];

switch ( $pw_state ) {
	case 'pending':
		$pw_title = __( 'Finalisez votre paiement pour lire cet article', '180c' );
		$pw_desc  = __( 'Votre abonnement est en attente de paiement. Réglez-le pour débloquer cet article et l\'ensemble de nos contenus.', '180c' );
		break;
	case 'on_hold':
		if ( '' !== $sub_state['payment_url'] ) {
			$pw_title = __( 'Régularisez votre paiement pour lire cet article', '180c' );
			$pw_desc  = __( 'Votre abonnement est suspendu : un paiement est en attente. Réglez-le pour retrouver l\'accès à cet article et à tous nos contenus.', '180c' );
		} else {
			$pw_title = __( 'Réactivez votre abonnement pour lire cet article', '180c' );
			$pw_desc  = __( 'Votre abonnement est en pause. Réactivez-le pour retrouver l\'accès à cet article et à tous nos contenus.', '180c' );
		}
		break;
	case 'ended':
		$pw_title = __( 'Cet article est réservé à l\'abonnement', '180c' );
		$pw_desc  = __( 'Votre abonnement a pris fin. Réabonnez-vous pour accéder à cet article et à toutes nos recettes.', '180c' );
		break;
	default:
		$pw_title = __( 'La suite est réservée aux abonnés', '180c' );
		$pw_desc  = __( 'Soutenez la rédaction de 180°C et lisez tous nos articles à partir de 2,99 € / mois.', '180c' );
		break;
}
?>
<aside
	class="paywall paywall--article"
	role="region"
	aria-label="<?php esc_attr_e( 'Accès réservé aux abonnés', '180c' ); ?>"
	data-event-fire="paywall_view"
	data-post-id="<?php echo esc_attr( $post_id ); ?>"
>
	<div class="paywall__inner">

		<div class="paywall__icon" aria-hidden="true">
			<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
				<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
				<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
			</svg>
		</div>

		<h2 class="paywall__title">
			<?php echo esc_html( $pw_title ); ?>
		</h2>

		<p class="paywall__desc">
			<?php echo esc_html( $pw_desc ); ?>
		</p>

		<div class="paywall__actions">
			<?php if ( 'pending' === $pw_state && '' !== $sub_state['payment_url'] ) : ?>
				<a
					class="btn btn--primary btn--lg"
					href="<?php echo esc_url( $sub_state['payment_url'] ); ?>"
					data-event="paywall_cta_click"
					data-post-id="<?php echo esc_attr( $post_id ); ?>"
					data-cta-label="finalize_payment"
				>
					<?php esc_html_e( 'Finaliser mon abonnement', '180c' ); ?>
				</a>
			<?php elseif ( 'on_hold' === $pw_state && '' !== $sub_state['payment_url'] ) : ?>
				<a
					class="btn btn--primary btn--lg"
					href="<?php echo esc_url( $sub_state['payment_url'] ); ?>"
					data-event="paywall_cta_click"
					data-post-id="<?php echo esc_attr( $post_id ); ?>"
					data-cta-label="settle_payment"
				>
					<?php esc_html_e( 'Régler mon paiement', '180c' ); ?>
				</a>
			<?php elseif ( 'on_hold' === $pw_state && $sub_state['reactivate_id'] > 0 ) : ?>
				<button
					type="button"
					class="btn btn--primary btn--lg"
					data-confirm
					data-confirm-post="subscription/reactivate"
					data-confirm-body="<?php echo esc_attr( wp_json_encode( array( 'subscription_id' => (int) $sub_state['reactivate_id'] ) ) ); ?>"
					data-confirm-title="<?php esc_attr_e( 'Réactiver votre abonnement ?', '180c' ); ?>"
					data-confirm-message="<?php esc_attr_e( 'Souhaitez-vous réactiver votre abonnement ? La facturation reprendra à la prochaine échéance.', '180c' ); ?>"
					data-confirm-confirm-label="<?php esc_attr_e( 'Réactiver', '180c' ); ?>"
					data-event="paywall_cta_click"
					data-post-id="<?php echo esc_attr( $post_id ); ?>"
					data-cta-label="reactivate"
				>
					<?php esc_html_e( 'Réactiver mon abonnement', '180c' ); ?>
				</button>
			<?php elseif ( 'ended' === $pw_state ) : ?>
				<a
					class="btn btn--primary btn--lg"
					href="<?php echo $subscribe_url; ?>"
					data-event="paywall_cta_click"
					data-post-id="<?php echo esc_attr( $post_id ); ?>"
					data-cta-label="resubscribe"
					<?php echo _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'paywall_article' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				>
					<?php esc_html_e( 'Reprendre mon abonnement', '180c' ); ?>
				</a>
			<?php else : ?>
				<a
					class="btn btn--primary btn--lg"
					href="<?php echo $subscribe_url; ?>"
					data-event="paywall_cta_click"
					data-post-id="<?php echo esc_attr( $post_id ); ?>"
					data-cta-label="subscribe"
					<?php echo _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'paywall_article' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				>
					<?php esc_html_e( 'Je m\'abonne', '180c' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( ! is_user_logged_in() ) : ?>
			<p class="paywall__login-link">
				<?php
				printf(
					/* translators: %s: lien de connexion */
					esc_html__( 'Déjà abonné ? %s', '180c' ),
					sprintf(
						'<a href="%1$s" data-event="paywall_cta_click" data-post-id="%2$s" data-cta-label="login">%3$s</a>',
						$login_url,
						esc_attr( $post_id ),
						esc_html__( 'Se connecter', '180c' )
					)
				);
				?>
			</p>
		<?php elseif ( 'ended' === $pw_state && 'expired' !== $sub_state['status'] ) : ?>
			<?php /* Abonnement résilié (cancelled) → lien d'accès à la gestion du compte. Masqué pour sans-abonnement, pause (CTA = Réactiver), pending (CTA = Finaliser) et expiré. */ ?>
			<p class="paywall__login-link">
				<a
					href="<?php echo $account_sub_url; ?>"
					data-event="paywall_cta_click"
					data-post-id="<?php echo esc_attr( $post_id ); ?>"
					data-cta-label="account_subscription"
				>
					<?php esc_html_e( 'Gérer mon abonnement', '180c' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<ul class="paywall__benefits" role="list">
			<?php foreach ( $benefits as $benefit ) : ?>
				<li class="paywall__benefit">
					<svg class="paywall__benefit-check" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="20 6 9 17 4 12"/>
					</svg>
					<?php echo esc_html( $benefit ); ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php get_template_part( 'template-parts/subscribe/payment-badges' ); ?>

	</div>
</aside>
