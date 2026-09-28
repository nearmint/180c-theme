<?php
/**
 * Page Abonnement — accordéon d'une offre.
 *
 * Réutilise la structure d'accordéon accessible du Centre d'aide (classes
 * .centre-aide__* + ARIA disclosure) ; l'enrichissement JS est porté par
 * src/js/modules/subscribe.js (le module help.js ne cible que la page Aide).
 *
 * En-tête : titre + sous-titre + prix (HTML live WooCommerce). Corps :
 * description, CTA d'ajout au panier (vrai lien, fonctionne sans JS) et badges
 * de paiement. L'offre « mise en avant » est ouverte par défaut + badge.
 *
 * @package 180c
 *
 * @var array $args {
 *     @type array $offer Données d'offre (cf. _180c_subscribe_get_offers()).
 *     @type int   $index Index de l'offre dans la liste.
 * }
 */

defined( 'ABSPATH' ) || exit;

$_180c_offer = isset( $args['offer'] ) && is_array( $args['offer'] ) ? $args['offer'] : array();
$_180c_index = isset( $args['index'] ) ? (int) $args['index'] : 0;

if ( empty( $_180c_offer ) || empty( $_180c_offer['product_id'] ) || empty( $_180c_offer['add_to_cart_url'] ) ) {
	return;
}

// Une ouverture par deep-link du module « Offrir » (?offrir) prime : la page
// passe alors suppress_featured pour ne garder qu'un seul panel ouvert au
// chargement, sans JS (l'exclusivité au clic est gérée par le bus d'événement).
$_180c_suppress    = ! empty( $args['suppress_featured'] );
$_180c_featured    = ! empty( $_180c_offer['is_featured'] ) && ! $_180c_suppress;
$_180c_trigger_id  = 'subscribe-offer-trigger-' . $_180c_index;
$_180c_answer_id   = 'subscribe-offer-answer-' . $_180c_index;
$_180c_price_plain = trim( wp_strip_all_tags( (string) $_180c_offer['price_html'] ) );
$_180c_item_class  = 'centre-aide__item subscribe-offer' . ( $_180c_featured ? ' subscribe-offer--featured' : '' );
// Identifiant d'offre pour la mesure (slug produit, jamais de donnée nominative).
//
// Le déclencheur porte un crochet NEUTRE `data-umami-offer` (et non un
// `data-umami-event`) : l'event subscribe_offer_select est émis par
// src/js/modules/umami-events.js APRÈS le toggle, afin de ne compter que les
// ouvertures — un attribut natif compterait aussi les replis.
$_180c_offer_slug = isset( $_180c_offer['slug'] ) ? (string) $_180c_offer['slug'] : '';
?>
<div class="<?php echo esc_attr( $_180c_item_class ); ?>" data-accordion-group="subscribe">
	<h2 class="centre-aide__item-heading subscribe-offer__heading">
		<button type="button"
				class="centre-aide__trigger subscribe-offer__trigger"
				id="<?php echo esc_attr( $_180c_trigger_id ); ?>"
				aria-expanded="<?php echo $_180c_featured ? 'true' : 'false'; ?>"
				aria-controls="<?php echo esc_attr( $_180c_answer_id ); ?>"
				data-umami-offer="<?php echo esc_attr( $_180c_offer_slug ); ?>">
			<span class="centre-aide__question-text subscribe-offer__head">
				<span class="subscribe-offer__titles">
					<span class="subscribe-offer__title"><?php echo esc_html( $_180c_offer['title'] ); ?></span>
					<?php if ( ! empty( $_180c_offer['subtitle'] ) ) : ?>
						<span class="subscribe-offer__subtitle"><?php echo esc_html( $_180c_offer['subtitle'] ); ?></span>
					<?php endif; ?>
				</span>
				<?php if ( '' !== $_180c_price_plain ) : ?>
					<span class="subscribe-offer__price"><?php echo wp_kses_post( $_180c_offer['price_html'] ); ?></span>
				<?php endif; ?>
			</span>
			<span class="subscribe-offer__chevron" aria-hidden="true">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" focusable="false"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
		</button>
	</h2>

	<div class="centre-aide__answer subscribe-offer__answer"
			id="<?php echo esc_attr( $_180c_answer_id ); ?>"
			role="region"
			aria-labelledby="<?php echo esc_attr( $_180c_trigger_id ); ?>"
			<?php echo $_180c_featured ? 'style="height:auto" data-open="true">' : 'hidden>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs littéraux statiques. ?>
		<div class="centre-aide__answer-inner subscribe-offer__body">
			<?php if ( ! empty( $_180c_offer['description'] ) ) : ?>
				<div class="subscribe-offer__desc"><?php echo wp_kses_post( $_180c_offer['description'] ); ?></div>
			<?php endif; ?>

			<a class="subscribe-offer__cta" href="<?php echo esc_url( $_180c_offer['add_to_cart_url'] ); ?>"
				<?php echo _180c_umami_attrs( 'subscribe_payment_start', array( 'offer' => $_180c_offer_slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<span class="subscribe-offer__cta-label"><?php esc_html_e( "S'abonner", '180c' ); ?></span>
			</a>

			<?php get_template_part( 'template-parts/subscribe/payment-badges' ); ?>
		</div>
	</div>
</div>
