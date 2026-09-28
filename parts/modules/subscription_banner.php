<?php
/**
 * Template part — Module home : Bannière d'abonnement.
 *
 * Reprend le markup de l'ancien repli CTA du module « Carnet de recettes »
 * (.carnet--cta, stylé par src/css/components/home/carnet.css) : titre,
 * sous-titre et un CTA unique. Masqué pour les abonnés actifs, affiché pour les
 * non-abonnés (et les visiteurs non connectés).
 *
 * Sous-champs ACF (clés HISTORIQUES conservées pour ne pas perdre les saisies
 * existantes ; libellés éditeur ré-affectés) :
 *   - title                (text)              → titre (h2)
 *   - description          (textarea)          → sous-titre
 *   - cta_label            (text)              → libellé du CTA
 *   - cta_url              (url, requis)       → cible du CTA
 *   - secondary_link_label (text)              → lien secondaire (libellé)
 *   - secondary_link_url   (url)               → lien secondaire (cible)
 *   - hide_for_subscribers (true_false, défaut true) → masque pour les abonnés
 *
 * Le lien secondaire (à droite du CTA) n'est rendu que si libellé ET URL sont
 * renseignés.
 *
 * Réutilisable HORS Home Builder : si des valeurs sont passées en argument
 * (`get_template_part( 'parts/modules/subscription_banner', null, $args )`,
 * ex. page-newsletter.php), elles priment ; sinon on lit les sous-champs ACF de
 * la boucle flexible `home_modules` (comportement Home Builder d'origine).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// En contexte Home Builder, get_template_part() n'envoie pas d'args : WordPress
// fournit alors un tableau VIDE (et non `unset`). La résolution se fait donc par
// CLÉ — un champ absent des args retombe sur get_sub_field() — sans quoi tous
// les champs seraient lus à vide en HB (régression sous-titre / lien secondaire).
$_180c_sb_args = ( isset( $args ) && is_array( $args ) ) ? $args : array();

/**
 * Résout un champ : depuis les args si la clé y est présente (réutilisation hors
 * Home Builder, ex. page newsletter), sinon depuis le sous-champ ACF de la
 * boucle flexible `home_modules`.
 *
 * @param string $key Nom du champ.
 * @return mixed
 */
$_180c_sb_get = static function ( $key ) use ( $_180c_sb_args ) {
	if ( array_key_exists( $key, $_180c_sb_args ) ) {
		return $_180c_sb_args[ $key ];
	}
	return get_sub_field( $key );
};

$hide = (bool) $_180c_sb_get( 'hide_for_subscribers' );

// Masquer pour les abonnés actifs ; affiché pour les non-abonnés.
if ( $hide && function_exists( '_180c_is_recipe_subscriber' ) && _180c_is_recipe_subscriber() ) {
	return;
}

/*
 * État d'abonnement (utilisateur connecté) : on personnalise le module pour
 * inviter à finaliser / réactiver / se réabonner. Les abonnés actifs (et en
 * résiliation) sont déjà masqués ci-dessus. state ∈ '' | pending | on_hold | ended.
 */
$sub_state = function_exists( '_180c_get_subscription_display_state' )
	? _180c_get_subscription_display_state()
	: array(
		'state'         => '',
		'payment_url'   => '',
		'reactivate_id' => 0,
	);
$sb_promo  = in_array( $sub_state['state'], array( 'pending', 'on_hold', 'ended' ), true ) ? $sub_state['state'] : '';

if ( 'pending' === $sb_promo ) {
	$title    = __( 'Il ne vous reste qu\'une étape', '180c' );
	$subtitle = __( 'Votre abonnement est en attente de paiement. Finalisez-le pour profiter de toutes nos recettes.', '180c' );
} elseif ( 'on_hold' === $sb_promo && '' !== $sub_state['payment_url'] ) {
	$title    = __( 'Votre abonnement est suspendu', '180c' );
	$subtitle = __( 'Un paiement est en attente. Régularisez-le pour retrouver l\'accès à toutes nos recettes.', '180c' );
} elseif ( 'on_hold' === $sb_promo ) {
	$title    = __( 'Votre abonnement est en pause', '180c' );
	$subtitle = __( 'Réactivez-le en un clic et retrouvez l\'accès à toutes nos recettes.', '180c' );
} elseif ( 'ended' === $sb_promo && 'expired' === $sub_state['status'] ) {
	$title    = __( 'Votre abonnement a expiré', '180c' );
	$subtitle = __( 'Renouvelez-le pour continuer à profiter de toutes nos recettes.', '180c' );
} elseif ( 'ended' === $sb_promo ) {
	$title    = __( 'Votre abonnement a pris fin', '180c' );
	$subtitle = __( 'Réabonnez-vous pour retrouver toutes nos recettes et nos contenus.', '180c' );
} else {
	$title    = $_180c_sb_get( 'title' ) ?: __( 'Accédez à toutes les recettes de 180°C', '180c' );
	$subtitle = $_180c_sb_get( 'description' );
}

// CTA générique (état « sans abonnement ») : URL saisie, sinon URL d'abonnement
// par défaut. Les états promo (pending/on_hold/ended) ont leur propre CTA rendu
// dans le markup.
$cta_label     = $_180c_sb_get( 'cta_label' ) ?: __( "Je m'abonne", '180c' );
$cta_url       = $_180c_sb_get( 'cta_url' );
$cta_url       = $cta_url
	? esc_url( $cta_url )
	: esc_url( apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) ) );
$subscribe_url = esc_url( apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) ) );

// Lien secondaire (« Se connecter »), rendu seulement si libellé ET URL saisis,
// hors états promo, et UNIQUEMENT pour un visiteur déconnecté : un utilisateur
// connecté n'a pas besoin du lien de connexion.
$secondary_label = $_180c_sb_get( 'secondary_link_label' );
$secondary_url   = $_180c_sb_get( 'secondary_link_url' );
$has_secondary   = '' === $sb_promo && ! is_user_logged_in() && $secondary_label && $secondary_url;
?>
<section class="home-module carnet carnet--cta" aria-labelledby="subscription-banner-title">
	<div class="container-180c">
		<div class="carnet__cta-inner">
			<h2 id="subscription-banner-title" class="carnet__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( $subtitle ) : ?>
				<p class="carnet__cta-text"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
			<div class="carnet__actions">
				<?php if ( 'pending' === $sb_promo && '' !== $sub_state['payment_url'] ) : ?>
					<a class="btn btn--primary carnet__subscribe" href="<?php echo esc_url( $sub_state['payment_url'] ); ?>">
						<?php esc_html_e( 'Finaliser mon paiement', '180c' ); ?>
					</a>
				<?php elseif ( 'on_hold' === $sb_promo && '' !== $sub_state['payment_url'] ) : ?>
					<a class="btn btn--primary carnet__subscribe" href="<?php echo esc_url( $sub_state['payment_url'] ); ?>">
						<?php esc_html_e( 'Régler mon paiement', '180c' ); ?>
					</a>
				<?php elseif ( 'on_hold' === $sb_promo && $sub_state['reactivate_id'] > 0 ) : ?>
					<button
						type="button"
						class="btn btn--primary carnet__subscribe"
						data-confirm
						data-confirm-post="subscription/reactivate"
						data-confirm-body="<?php echo esc_attr( wp_json_encode( array( 'subscription_id' => (int) $sub_state['reactivate_id'] ) ) ); ?>"
						data-confirm-title="<?php esc_attr_e( 'Réactiver votre abonnement ?', '180c' ); ?>"
						data-confirm-message="<?php esc_attr_e( 'Souhaitez-vous réactiver votre abonnement ? La facturation reprendra à la prochaine échéance.', '180c' ); ?>"
						data-confirm-confirm-label="<?php esc_attr_e( 'Réactiver', '180c' ); ?>"
					>
						<?php esc_html_e( 'Réactiver mon abonnement', '180c' ); ?>
					</button>
				<?php elseif ( 'ended' === $sb_promo ) : ?>
					<a class="btn btn--primary carnet__subscribe" href="<?php echo $subscribe_url; ?>"
						<?php echo _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'home_banner' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php esc_html_e( 'Reprendre mon abonnement', '180c' ); ?>
					</a>
				<?php else : ?>
					<a class="btn btn--primary carnet__subscribe" href="<?php echo $cta_url; ?>"
						<?php echo _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'home_banner' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php echo esc_html( $cta_label ); ?>
					</a>
					<?php if ( $has_secondary ) : ?>
						<a class="carnet__login" href="<?php echo esc_url( $secondary_url ); ?>">
							<?php echo esc_html( $secondary_label ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
