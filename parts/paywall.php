<?php
/**
 * Template part : paywall recette — rendu server-side.
 *
 * Ne rien afficher si l'utilisateur est déjà abonné.
 * Appelé depuis single-recipe.php après les premiers ingrédients.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// Précondition : ne pas rendre si l'utilisateur est abonné.
if ( ! _180c_recipe_is_paywalled() ) {
	return;
}

$recipe_id = get_the_ID();

/**
 * Filtre l'URL de la page d'abonnement affichée dans le paywall.
 *
 * @param string $url URL par défaut.
 */
$subscribe_url = esc_url( apply_filters( '180c/paywall_subscribe_url', home_url( '/abonnement/' ) ) );

$current_url     = esc_url( get_permalink() ?: home_url( '/' ) );
$login_url       = esc_url(
	add_query_arg(
		'redirect_to',
		rawurlencode( get_permalink() ?: home_url( '/' ) ),
		home_url( '/connexion/' )
	)
);
$account_sub_url = esc_url( home_url( '/mon-compte/abonnement/' ) );
?>
<aside
	class="paywall"
	role="region"
	aria-label="<?php esc_attr_e( 'Paywall recette', '180c' ); ?>"
	data-event="paywall_view"
	data-recipe-id="<?php echo esc_attr( $recipe_id ); ?>"
>
	<div class="paywall__gradient" aria-hidden="true"></div>

	<div class="paywall__inner">

		<div class="paywall__icon" aria-hidden="true">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
				<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
				<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
			</svg>
		</div>

		<h2 class="paywall__title">
			<?php esc_html_e( 'Accédez à la recette complète', '180c' ); ?>
		</h2>

		<p class="paywall__desc">
			<?php esc_html_e( 'Abonnez-vous aux recettes en ligne pour découvrir les ingrédients et toutes les étapes.', '180c' ); ?>
		</p>

		<div class="paywall__actions">
			<a
				class="btn btn--primary"
				href="<?php echo $subscribe_url; ?>"
				data-event="paywall_cta_click"
				data-recipe-id="<?php echo esc_attr( $recipe_id ); ?>"
				data-cta-label="subscribe"
				<?php echo _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'paywall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			>
				<?php esc_html_e( 'Je m\'abonne', '180c' ); ?>
			</a>

			<?php if ( is_user_logged_in() ) : ?>
				<a
					class="btn btn--ghost"
					href="<?php echo $account_sub_url; ?>"
					data-event="paywall_cta_click"
					data-recipe-id="<?php echo esc_attr( $recipe_id ); ?>"
					data-cta-label="account_subscription"
				>
					<?php esc_html_e( 'Voir mon abonnement', '180c' ); ?>
				</a>
			<?php else : ?>
				<a
					class="btn btn--ghost"
					href="<?php echo $login_url; ?>"
					data-event="paywall_cta_click"
					data-recipe-id="<?php echo esc_attr( $recipe_id ); ?>"
					data-cta-label="already_subscriber"
				>
					<?php esc_html_e( 'J\'ai déjà un compte', '180c' ); ?>
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
						'<a href="%1$s" data-event="paywall_cta_click" data-recipe-id="%2$s" data-cta-label="login">%3$s</a>',
						$login_url,
						esc_attr( $recipe_id ),
						esc_html__( 'Connexion', '180c' )
					)
				);
				?>
			</p>
		<?php endif; ?>

	</div>
</aside>
