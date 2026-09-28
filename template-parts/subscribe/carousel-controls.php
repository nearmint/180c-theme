<?php
/**
 * Page Abonnement — contrôles du carousel (prev / compteur / next).
 *
 * Rendus dans l'en-tête de page (`.subscribe__header`, au niveau du titre H1)
 * et non plus en overlay sur l'image : ils restent ainsi toujours alignés sur
 * le titre, jamais ancrés aux bords d'un visuel qui rétrécit. En mobile,
 * l'en-tête passe en colonne (titre au-dessus des contrôles).
 *
 * Masqués par défaut, révélés par src/js/modules/subscribe.js, qui pilote le
 * carousel `[data-subscribe-carousel]` (recherche des boutons à la racine
 * `[data-component="subscribe"]`, donc hors du conteneur du carousel).
 *
 * @package 180c-theme
 */

defined( 'ABSPATH' ) || exit;

$_180c_total = isset( $args['total'] ) ? (int) $args['total'] : 0;
if ( $_180c_total < 2 ) {
	return;
}
?>
<div class="subscribe-carousel__controls" data-subscribe-carousel-controls hidden>
	<button type="button" class="subscribe-carousel__arrow subscribe-carousel__arrow--prev" data-subscribe-prev aria-label="<?php esc_attr_e( 'Image précédente', '180c' ); ?>">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
	</button>
	<p class="subscribe-carousel__counter" aria-live="polite">
		<span data-subscribe-current>1</span>
		<span aria-hidden="true">/</span>
		<span class="screen-reader-text"><?php esc_html_e( 'sur', '180c' ); ?></span>
		<span data-subscribe-total><?php echo esc_html( (string) $_180c_total ); ?></span>
	</p>
	<button type="button" class="subscribe-carousel__arrow subscribe-carousel__arrow--next" data-subscribe-next aria-label="<?php esc_attr_e( 'Image suivante', '180c' ); ?>">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
	</button>
</div>
