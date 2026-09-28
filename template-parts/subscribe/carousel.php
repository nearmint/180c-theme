<?php
/**
 * Page Abonnement — carousel d'images de bénéfices.
 *
 * Repeater ACF `_180c_abo_carousel` (images uniquement). Piste à scroll-snap
 * horizontal (fonctionnelle sans JS). Le 1er visuel est candidat LCP (eager +
 * fetchpriority high) ; les suivants en lazy. Images non croppées
 * (object-fit: contain, ratio préservé).
 *
 * Les contrôles prev/next + compteur ne sont plus rendus ici : ils vivent dans
 * l'en-tête de page (template-parts/subscribe/carousel-controls.php), au niveau
 * du titre. subscribe.js les retrouve à la racine [data-component="subscribe"].
 *
 * Les slides peuvent être passées déjà filtrées via $args['slides'] (source
 * unique calculée par page-abonnement.php) ; sinon elles sont lues ici.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( isset( $args['slides'] ) && is_array( $args['slides'] ) ) {
	$_180c_slides = $args['slides'];
} else {
	$_180c_slides = function_exists( 'get_field' ) ? (array) get_field( '_180c_abo_carousel' ) : array();
	$_180c_slides = array_values(
		array_filter(
			$_180c_slides,
			static function ( $slide ) {
				return ! empty( $slide['slide_image'] );
			}
		)
	);
}
$_180c_total = count( $_180c_slides );

if ( 0 === $_180c_total ) {
	return;
}
?>
<div class="subscribe-carousel" data-subscribe-carousel>
	<ul class="subscribe-carousel__track" role="list">
		<?php
		foreach ( $_180c_slides as $_180c_i => $_180c_slide ) :
			$_180c_image    = $_180c_slide['slide_image'];
			$_180c_image_id = is_array( $_180c_image ) ? (int) ( $_180c_image['ID'] ?? 0 ) : (int) $_180c_image;
			if ( ! $_180c_image_id ) {
				continue;
			}
			$_180c_first   = ( 0 === $_180c_i );
			$_180c_caption = isset( $_180c_slide['slide_texte'] ) ? trim( (string) $_180c_slide['slide_texte'] ) : '';
			$_180c_attr    = array(
				'class'    => 'subscribe-carousel__img',
				'loading'  => $_180c_first ? 'eager' : 'lazy',
				'decoding' => $_180c_first ? 'sync' : 'async',
			);
			if ( $_180c_first ) {
				$_180c_attr['fetchpriority'] = 'high';
			}
			?>
			<li class="subscribe-carousel__slide">
				<figure class="subscribe-carousel__figure">
					<?php echo wp_get_attachment_image( $_180c_image_id, 'large', false, $_180c_attr ); ?>
					<?php if ( '' !== $_180c_caption ) : ?>
						<figcaption class="subscribe-carousel__caption"><?php echo esc_html( $_180c_caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php
	// Contrôles en sur-impression bas-droite de l'image (desktop). La légende
	// ACF de chaque slide reste en bas-gauche (cf. .subscribe-carousel__caption).
	// Masqués en mobile, où la pagination passe par les nav-dots ci-dessous.
	get_template_part(
		'template-parts/subscribe/carousel-controls',
		null,
		array( 'total' => $_180c_total )
	);
	?>

	<?php if ( $_180c_total > 1 ) : ?>
		<?php // Pagination à points — mobile uniquement (swipe pour défiler). ?>
		<div class="subscribe-carousel__dots" data-subscribe-dots aria-label="<?php esc_attr_e( 'Pagination du diaporama', '180c' ); ?>">
			<?php for ( $_180c_d = 0; $_180c_d < $_180c_total; $_180c_d++ ) : ?>
				<button
					type="button"
					class="subscribe-carousel__dot<?php echo 0 === $_180c_d ? ' subscribe-carousel__dot--active' : ''; ?>"
					data-subscribe-dot="<?php echo esc_attr( (string) $_180c_d ); ?>"
					<?php echo 0 === $_180c_d ? 'aria-current="true"' : ''; ?>
					aria-label="<?php echo esc_attr( sprintf( /* translators: %d: numéro de diapositive */ __( 'Aller à la diapositive %d', '180c' ), $_180c_d + 1 ) ); ?>"
				></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</div>
