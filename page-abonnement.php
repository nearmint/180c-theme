<?php
/**
 * Template Name: Page Abonnement
 * Template Post Type: page
 *
 * Page de souscription à l'abonnement numérique 180°C.
 *
 * Layout deux colonnes : à gauche les offres en accordéons (réutilisant
 * l'accordéon du Centre d'aide) — ou, si l'utilisateur est déjà abonné, un
 * panel dédié ; à droite un carousel d'images de bénéfices. Suit un mini-module
 * FAQ. Prix lus en direct depuis WooCommerce, jamais saisis en dur.
 *
 * Données : groupe ACF group_180c_page_abonnement + helpers inc/subscribe.php.
 *
 * @package 180c-theme
 */

defined( 'ABSPATH' ) || exit;

// GA4 : vue de l'offre d'abonnement (view_subscription_offer, cf inc/analytics/ga4.php).
if ( function_exists( '_180c_analytics_print_subscription_offer' ) ) {
	add_action( 'wp_footer', '_180c_analytics_print_subscription_offer' );
}

get_header();

$_180c_abo_titre = function_exists( 'get_field' ) ? (string) get_field( '_180c_abo_titre' ) : '';
if ( '' === $_180c_abo_titre ) {
	$_180c_abo_titre = __( "Nos offres d'abonnement", '180c' );
}

$_180c_abo_offers = function_exists( '_180c_subscribe_get_offers' ) ? _180c_subscribe_get_offers() : array();

// Slides du carousel calculées une seule fois (source unique) : le compteur et
// les contrôles vivent dans l'en-tête (au niveau du titre), la piste dans la
// colonne média.
$_180c_abo_slides = function_exists( 'get_field' ) ? (array) get_field( '_180c_abo_carousel' ) : array();
$_180c_abo_slides = array_values(
	array_filter(
		$_180c_abo_slides,
		static function ( $slide ) {
			return ! empty( $slide['slide_image'] );
		}
	)
);
$_180c_abo_total  = count( $_180c_abo_slides );

// Ouverture du module « Offrir » par deep-link (?offrir=1|open). Calculée en
// amont des offres : si vraie, on demande aux accordéons d'offres de rester
// repliés (suppress_featured) afin qu'un seul panel soit ouvert au chargement,
// y compris sans JS. Paramètre d'affichage seul (aucune mutation) → nonce non
// requis.
$_180c_gift_open = isset( $_GET['offrir'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	&& in_array( sanitize_key( wp_unslash( $_GET['offrir'] ) ), array( '1', 'open' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<main id="main" class="subscribe" data-component="subscribe">
	<div class="subscribe__inner container-180c">

		<h1 class="subscribe__title"><?php echo esc_html( $_180c_abo_titre ); ?></h1>

		<?php
		// Bandeau d'état d'abonnement, ajouté sous le H1 sans modifier le reste de
		// la page (offres + carousel + FAQ restent identiques pour tous). Le partial
		// est auto-gardé : « déjà abonné » si accès en cours, invitation
		// contextuelle pour pending/on-hold/terminé, rien pour un visiteur sans
		// abonnement.
		get_template_part( 'template-parts/subscribe/already' );
		?>

		<div class="subscribe__layout">

			<div class="subscribe__offers">
				<?php
				if ( empty( $_180c_abo_offers ) ) {
					?>
					<p class="subscribe__empty">
						<?php esc_html_e( "Nos offres d'abonnement seront bientôt disponibles.", '180c' ); ?>
					</p>
					<?php
				} else {
					foreach ( $_180c_abo_offers as $_180c_abo_index => $_180c_abo_offer ) {
						get_template_part(
							'template-parts/subscribe/offer-accordion',
							null,
							array(
								'offer'             => $_180c_abo_offer,
								'index'             => (int) $_180c_abo_index,
								'suppress_featured' => $_180c_gift_open,
							)
						);
					}
				}
				?>

				<?php
				// 3e module : « Offrir un abonnement ». Statique (hors repeater
				// ACF des offres). Ouvert d'emblée si ?offrir=1|open ($_180c_gift_open,
				// calculé plus haut) ; l'ancre #offrir + le scroll doux sont gérés par
				// src/js/modules/gift.js. data-accordion-group le rattache au bus
				// d'exclusivité partagé avec les offres (cf. subscribe.js / gift.js).
				?>
				<div class="gift__module" id="offrir" data-component="gift">
					<div class="gift__item" data-accordion-group="subscribe">
						<h2 class="gift__heading">
							<button type="button"
									class="gift__trigger"
									id="gift-module-trigger"
									aria-expanded="<?php echo $_180c_gift_open ? 'true' : 'false'; ?>"
									aria-controls="gift-module-answer">
								<span class="gift__head">
									<span class="gift__titles">
										<span class="gift__module-title"><?php esc_html_e( 'Offrir un abonnement', '180c' ); ?></span>
										<span class="subscribe-offer__subtitle"><?php esc_html_e( '12 mois de recettes à offrir', '180c' ); ?></span>
									</span>
									<span class="subscribe-offer__price">30&nbsp;€</span>
								</span>
								<span class="gift__chevron" aria-hidden="true">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" focusable="false"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</span>
							</button>
						</h2>
						<div class="gift__answer"
								id="gift-module-answer"
								role="region"
								aria-labelledby="gift-module-trigger"
								<?php echo $_180c_gift_open ? 'data-open="true">' : 'hidden>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs littéraux statiques. ?>
							<div class="gift__body">
								<ol class="gift__steps">
									<li class="gift__step">
										<span class="gift__step-number">1</span>
										<span class="gift__step-text"><?php esc_html_e( "Renseignez le prénom et l'adresse e-mail du bénéficiaire, ajoutez un message et choisissez la date d'envoi.", '180c' ); ?></span>
									</li>
									<li class="gift__step">
										<span class="gift__step-number">2</span>
										<span class="gift__step-text"><?php esc_html_e( 'Réglez en ligne, en une seule fois. Aucun renouvellement automatique.', '180c' ); ?></span>
									</li>
									<li class="gift__step">
										<span class="gift__step-number">3</span>
										<span class="gift__step-text"><?php esc_html_e( 'Le bénéficiaire reçoit un e-mail le jour choisi et accède immédiatement aux recettes et aux autres avantages.', '180c' ); ?></span>
									</li>
								</ol>

								<a class="gift__cta" href="<?php echo esc_url( home_url( '/offrir-un-abonnement/' ) ); ?>">
									<?php esc_html_e( 'Offrir un abonnement', '180c' ); ?>
								</a>

								<?php get_template_part( 'template-parts/subscribe/payment-badges' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<aside class="subscribe__media" aria-label="<?php esc_attr_e( "Aperçu de l'abonnement 180°C", '180c' ); ?>">
				<?php
				get_template_part(
					'template-parts/subscribe/carousel',
					null,
					array( 'slides' => $_180c_abo_slides )
				);
				?>
			</aside>

		</div>

		<?php get_template_part( 'template-parts/subscribe/faq' ); ?>

	</div>

	<?php
	if ( function_exists( '_180c_subscribe_render_jsonld' ) ) {
		_180c_subscribe_render_jsonld();
	}
	?>
</main>

<?php
get_footer();
