<?php
/**
 * Mon Compte — sidebar mutualisée (shell .my-account).
 *
 * Extraite du dashboard pour être partagée par TOUS les endpoints Mon compte
 * via l'override woocommerce/myaccount/my-account.php (cards profil, nav,
 * support, déconnexion). Les liens de navigation pointent en URL absolue
 * (account_url + #ancre) afin de fonctionner depuis n'importe quel endpoint,
 * pas seulement la single-page dashboard.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() || ! function_exists( 'WC' ) ) {
	return;
}

$user_id      = get_current_user_id();
$current_user = wp_get_current_user();

$is_subscriber = function_exists( '_180c_is_recipe_subscriber' )
	? _180c_is_recipe_subscriber()
	: false;

$display_name = trim( $current_user->first_name . ' ' . $current_user->last_name );
if ( '' === $display_name ) {
	$display_name = $current_user->display_name;
}

$subscriber_number = function_exists( '_180c_get_user_subscriber_number' )
	? _180c_get_user_subscriber_number( $user_id )
	: '';

$account_url     = wc_get_page_permalink( 'myaccount' );
$logout_url      = wp_logout_url( $account_url );
$help_center_url = home_url( '/centre-daide/' );
$contact_url     = home_url( '/contact/' );

/*
 * Lien « Votre avis sur le site » — mène directement au questionnaire.
 *
 * Même rendu que les deux autres touchpoints Typeform : URL brute, sans
 * paramètre ni fragment, ouverte dans un nouvel onglet. L'entrée disparaît si
 * aucun sondage n'est en cours.
 */
$survey_url = function_exists( '_180c_survey_url' ) ? _180c_survey_url() : '';

/*
 * Ancres dashboard en URL absolue (fonctionnent depuis tout endpoint).
 *
 * Plus d'entrée « Gérez votre abonnement » : le module #gerer a été retiré du
 * tableau de bord (cf. parts/account/dashboard.php, section 5).
 */
$nav_items = array(
	'#informations' => __( 'Vos informations', '180c' ),
	'#abonnement'   => __( 'Votre abonnement', '180c' ),
	'#factures'     => __( 'Vos commandes', '180c' ),
);
?>

<aside class="my-account__sidebar" aria-label="<?php esc_attr_e( 'Mon compte — navigation et services', '180c' ); ?>">

	<button
		type="button"
		class="my-account__mobile-trigger js-my-account-toggle"
		aria-expanded="false"
		aria-controls="my-account-mobile-panels"
	>
		<span class="my-account__mobile-trigger-label"><?php esc_html_e( 'Menu compte', '180c' ); ?></span>
	</button>

	<section class="my-account-card my-account-card--profile">
		<p class="my-account-card__eyebrow"><?php esc_html_e( 'Votre compte', '180c' ); ?></p>
		<?php if ( $is_subscriber ) : ?>
			<span class="my-account-card__badge"><?php esc_html_e( 'Abonné', '180c' ); ?></span>
		<?php endif; ?>
		<p class="my-account-card__name"><?php echo esc_html( $display_name ); ?></p>
		<?php if ( '' !== $subscriber_number ) : ?>
			<p class="my-account-card__subscriber-number">
				<?php
				printf(
					/* translators: %s: numéro d'abonnement WC Subscriptions (ex. 13125427). */
					esc_html__( 'N° d\'abonnement : %s', '180c' ),
					esc_html( $subscriber_number )
				);
				?>
			</p>
		<?php endif; ?>
	</section>

	<div class="my-account__mobile-panels" id="my-account-mobile-panels">

		<nav class="my-account-card my-account-card--nav" aria-label="<?php esc_attr_e( 'Sections Mon compte', '180c' ); ?>">
			<ul class="my-account-card__list">
				<?php foreach ( $nav_items as $anchor => $label ) : ?>
					<li>
						<a class="my-account-card__link" href="<?php echo esc_url( $account_url . $anchor ); ?>"><?php echo esc_html( $label ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<section class="my-account-card my-account-card--support" aria-label="<?php esc_attr_e( 'Aide', '180c' ); ?>">
			<ul class="my-account-card__list">
				<?php if ( '' !== $survey_url ) : ?>
					<li>
						<a class="my-account-card__link" href="<?php echo esc_url( $survey_url ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'Votre avis sur le site', '180c' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(nouvel onglet)', '180c' ); ?></span>
						</a>
					</li>
				<?php endif; ?>
				<li>
					<a class="my-account-card__link" href="<?php echo esc_url( $help_center_url ); ?>">
						<?php esc_html_e( "Centre d'aide", '180c' ); ?>
					</a>
				</li>
				<li>
					<a class="my-account-card__link" href="<?php echo esc_url( add_query_arg( 'objet', 'support-client', $contact_url ) ); ?>">
						<?php esc_html_e( 'Service client', '180c' ); ?>
					</a>
				</li>
			</ul>
		</section>

		<section class="my-account-card my-account-card--logout" aria-label="<?php esc_attr_e( 'Deconnexion', '180c' ); ?>">
			<ul class="my-account-card__list">
				<li>
					<a class="my-account-card__link" href="<?php echo esc_url( $logout_url ); ?>"
						data-confirm
						data-confirm-title="<?php esc_attr_e( 'Se déconnecter ?', '180c' ); ?>"
						data-confirm-message="<?php esc_attr_e( 'Vous allez être déconnecté de votre compte.', '180c' ); ?>"
						data-confirm-confirm-label="<?php esc_attr_e( 'Se déconnecter', '180c' ); ?>">
						<?php esc_html_e( 'Se déconnecter', '180c' ); ?>
					</a>
				</li>
			</ul>
		</section>

	</div><!-- .my-account__mobile-panels -->

</aside>
