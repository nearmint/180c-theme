<?php
/**
 * Page Abonnement — mini-FAQ.
 *
 * Repeater ACF `_180c_abo_faq` (question / réponse) rendu avec l'accordéon
 * réutilisé du Centre d'aide (.centre-aide__* + ARIA disclosure, piloté par
 * src/js/modules/subscribe.js). Bloc masqué si le repeater est vide. Le bouton
 * « Accéder au centre d'aide » est en dur et pointe vers la page Aide.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_faq = function_exists( 'get_field' ) ? (array) get_field( '_180c_abo_faq' ) : array();
$_180c_faq = array_values(
	array_filter(
		$_180c_faq,
		static function ( $row ) {
			return ! empty( $row['faq_question'] ) && ! empty( $row['faq_reponse'] );
		}
	)
);

if ( empty( $_180c_faq ) ) {
	return;
}

$_180c_help_url = function_exists( '_180c_subscribe_help_url' ) ? _180c_subscribe_help_url() : home_url( '/centre-daide/' );
?>
<section class="subscribe-faq" aria-labelledby="subscribe-faq-title">
	<h2 class="subscribe-faq__title" id="subscribe-faq-title"><?php esc_html_e( 'Besoin d’aide ?', '180c' ); ?></h2>

	<ul class="centre-aide__questions subscribe-faq__list" role="list">
		<?php
		foreach ( $_180c_faq as $_180c_i => $_180c_row ) :
			$_180c_trigger_id = 'subscribe-faq-trigger-' . $_180c_i;
			$_180c_answer_id  = 'subscribe-faq-answer-' . $_180c_i;
			?>
			<li class="centre-aide__item">
				<h3 class="centre-aide__item-heading">
					<button type="button"
							class="centre-aide__trigger"
							id="<?php echo esc_attr( $_180c_trigger_id ); ?>"
							aria-expanded="false"
							aria-controls="<?php echo esc_attr( $_180c_answer_id ); ?>">
						<span class="centre-aide__question-text"><?php echo esc_html( $_180c_row['faq_question'] ); ?></span>
						<span class="centre-aide__icon" aria-hidden="true"></span>
					</button>
				</h3>
				<div class="centre-aide__answer"
						id="<?php echo esc_attr( $_180c_answer_id ); ?>"
						role="region"
						aria-labelledby="<?php echo esc_attr( $_180c_trigger_id ); ?>"
						hidden>
					<div class="centre-aide__answer-inner"><?php echo wp_kses_post( $_180c_row['faq_reponse'] ); ?></div>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>

	<a class="subscribe-faq__cta" href="<?php echo esc_url( $_180c_help_url ); ?>">
		<?php esc_html_e( 'Accéder au centre d’aide', '180c' ); ?>
	</a>
</section>
