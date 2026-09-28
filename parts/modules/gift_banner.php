<?php
/**
 * Template part — Module home : Bannière « Offrir un abonnement ».
 *
 * Jumeau de parts/modules/subscription_banner.php : MÊME markup, MÊMES classes
 * (.carnet.carnet--cta, stylé par src/css/components/home/carnet.css), donc
 * aucun style propre. Seules changent la cible (page cadeau) et — surtout — la
 * règle de visibilité, qui est l'exacte inverse : la bannière d'abonnement
 * s'adresse aux NON-abonnés, celle-ci aux abonnés.
 *
 * Visibilité (règle dure, non éditorialisable) : rendu UNIQUEMENT pour un
 * utilisateur connecté ET abonné actif (_180c_is_recipe_subscriber()). Offrir un
 * abonnement à un tiers reste permis à un abonné — c'est même la cible du
 * module — cf. l'exclusion du produit cadeau dans _180c_subscribe_block_add_for_subscriber().
 *
 * Sous-champs ACF (layout `gift_banner`) :
 *   - title                (text)      → titre (h2)
 *   - description          (textarea)  → sous-titre
 *   - cta_label            (text)      → libellé du CTA
 *   - cta_url              (url)       → cible du CTA (défaut : page cadeau)
 *   - secondary_link_label (text)      → lien secondaire (libellé)
 *   - secondary_link_url   (url)       → lien secondaire (cible)
 *
 * Réutilisable HORS Home Builder, selon le même contrat que la bannière
 * d'abonnement : des valeurs passées en argument
 * (`get_template_part( 'parts/modules/gift_banner', null, $args )`) priment ;
 * sinon on lit les sous-champs ACF de la boucle flexible `home_modules`.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// Garde de visibilité AVANT toute lecture de champ : abonné connecté uniquement.
// Un visiteur déconnecté ne peut pas être abonné, mais le test explicite
// documente l'intention et protège d'un helper qui deviendrait laxiste.
if ( ! is_user_logged_in() || ! function_exists( '_180c_is_recipe_subscriber' ) || ! _180c_is_recipe_subscriber() ) {
	return;
}

// En contexte Home Builder, get_template_part() n'envoie pas d'args : WordPress
// fournit alors un tableau VIDE (et non `unset`). La résolution se fait donc par
// CLÉ — un champ absent des args retombe sur get_sub_field().
$_180c_gb_args = ( isset( $args ) && is_array( $args ) ) ? $args : array();

/**
 * Résout un champ : depuis les args si la clé y est présente (réutilisation hors
 * Home Builder), sinon depuis le sous-champ ACF de la boucle flexible.
 *
 * @param string $key Nom du champ.
 * @return mixed
 */
$_180c_gb_get = static function ( $key ) use ( $_180c_gb_args ) {
	if ( array_key_exists( $key, $_180c_gb_args ) ) {
		return $_180c_gb_args[ $key ];
	}
	return get_sub_field( $key );
};

$title    = $_180c_gb_get( 'title' );
$title    = $title ? $title : __( 'Offrez 180°C à un gourmand', '180c' );
$subtitle = $_180c_gb_get( 'description' );

// CTA : URL saisie, sinon page « Offrir un abonnement » du thème, filtrable
// par `180c/mc/gift_url` (nom hérité de l'ancien générateur de campagnes
// Mailchimp, conservé pour ne pas casser un éventuel `add_filter` existant).
$cta_label = $_180c_gb_get( 'cta_label' );
$cta_label = $cta_label ? $cta_label : __( 'Offrir un abonnement', '180c' );
$cta_url   = $_180c_gb_get( 'cta_url' );
$cta_url   = $cta_url ? $cta_url : apply_filters( '180c/mc/gift_url', home_url( '/offrir-un-abonnement/' ) );

// Lien secondaire, rendu seulement si libellé ET URL saisis.
$secondary_label = $_180c_gb_get( 'secondary_link_label' );
$secondary_url   = $_180c_gb_get( 'secondary_link_url' );
$has_secondary   = $secondary_label && $secondary_url;
?>
<section class="home-module carnet carnet--cta carnet--gift" aria-labelledby="gift-banner-title">
	<div class="container-180c">
		<div class="carnet__cta-inner">
			<h2 id="gift-banner-title" class="carnet__title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( $subtitle ) : ?>
				<p class="carnet__cta-text"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
			<div class="carnet__actions">
				<a class="btn btn--primary carnet__subscribe" href="<?php echo esc_url( $cta_url ); ?>"
					<?php echo _180c_umami_attrs( 'gift_cta_click', array( 'position' => 'home_banner' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php echo esc_html( $cta_label ); ?>
				</a>
				<?php if ( $has_secondary ) : ?>
					<a class="carnet__login" href="<?php echo esc_url( $secondary_url ); ?>">
						<?php echo esc_html( $secondary_label ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
