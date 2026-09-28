<?php
/**
 * Template part : état vide d'une archive / liste.
 *
 * Affiché quand une surface archive / taxonomie / recherche ne renvoie aucun
 * résultat. Message clair + CTA optionnel et actionnable (« Voir toutes les
 * recettes », « Retour à la Gazette »…). Le titre est un <p> (pas un heading)
 * pour préserver l'unicité du <h1> porté par parts/section-header.php.
 *
 * Usage :
 *   get_template_part( 'parts/empty-state', null, array(
 *       'title'     => __( 'Aucun résultat', '180c' ),
 *       'message'   => __( 'Essayez une autre recherche.', '180c' ),
 *       'cta_url'   => get_post_type_archive_link( 'recipe' ),
 *       'cta_label' => __( 'Voir toutes les recettes', '180c' ),
 *   ) );
 *
 * @package 180c
 *
 * @var array $args {
 *     @type string $title     Titre de l'état vide. Requis — sinon rien n'est rendu.
 *     @type string $message   Message d'accompagnement. Optionnel.
 *     @type string $cta_url   URL du CTA. Optionnel.
 *     @type string $cta_label Libellé du CTA. Optionnel (le CTA n'apparaît que si url ET label).
 * }
 */

defined( 'ABSPATH' ) || exit;

$es_title     = isset( $args['title'] ) ? (string) $args['title'] : '';
$es_message   = isset( $args['message'] ) ? (string) $args['message'] : '';
$es_cta_url   = isset( $args['cta_url'] ) ? (string) $args['cta_url'] : '';
$es_cta_label = isset( $args['cta_label'] ) ? (string) $args['cta_label'] : '';

if ( '' === $es_title ) {
	return;
}
?>
<div class="empty-state">
	<div class="container">
		<p class="empty-state__title"><?php echo esc_html( $es_title ); ?></p>

		<?php if ( '' !== $es_message ) : ?>
			<p class="empty-state__message"><?php echo esc_html( $es_message ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $es_cta_url && '' !== $es_cta_label ) : ?>
			<a class="empty-state__cta btn btn--ghost" href="<?php echo esc_url( $es_cta_url ); ?>">
				<?php echo esc_html( $es_cta_label ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
