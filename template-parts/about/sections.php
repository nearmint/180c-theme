<?php
/**
 * Page À propos — sections libres (Flexible Content).
 *
 * Layouts : text / text_image / quote / authors. La section « Auteurs » est
 * répétable et délègue son rendu à template-parts/about/authors.php. Rien n'est
 * rendu si aucune section n'est saisie (la page reste valable avec le seul
 * manifeste).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! have_rows( 'about_sections' ) ) {
	return;
}
?>
<div class="about-sections">
	<?php
	while ( have_rows( 'about_sections' ) ) :
		the_row();
		$_180c_layout = get_row_layout();

		if ( 'text' === $_180c_layout ) :
			?>
			<section class="about-section about-section--text">
				<div class="about-measure about-prose">
					<?php echo wp_kses_post( get_sub_field( 'section_content' ) ); ?>
				</div>
			</section>
			<?php
		elseif ( 'text_image' === $_180c_layout ) :
			$_180c_img_id = (int) get_sub_field( 'section_image' );
			$_180c_pos    = get_sub_field( 'section_image_position' ) ? get_sub_field( 'section_image_position' ) : 'right';
			?>
			<section class="about-section about-section--text-image about-section--img-<?php echo esc_attr( $_180c_pos ); ?>">
				<div class="container about-section__grid">
					<div class="about-section__text about-prose">
						<?php echo wp_kses_post( get_sub_field( 'section_content' ) ); ?>
					</div>
					<?php if ( $_180c_img_id ) : ?>
						<div class="about-section__media">
							<?php
							echo wp_get_attachment_image(
								$_180c_img_id,
								'large',
								false,
								array(
									'class'   => 'about-section__img',
									'loading' => 'lazy',
									'alt'     => '',
								)
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			</section>
			<?php
		elseif ( 'quote' === $_180c_layout ) :
			?>
			<section class="about-section about-section--quote">
				<div class="about-measure">
					<blockquote class="about-quote">
						<p class="about-quote__text"><?php echo esc_html( get_sub_field( 'quote_text' ) ); ?></p>
						<?php $_180c_attr = get_sub_field( 'quote_attribution' ); ?>
						<?php if ( $_180c_attr ) : ?>
							<cite class="about-quote__cite"><?php echo esc_html( $_180c_attr ); ?></cite>
						<?php endif; ?>
					</blockquote>
				</div>
			</section>
			<?php
		elseif ( 'authors' === $_180c_layout ) :
			get_template_part(
				'template-parts/about/authors',
				null,
				array(
					'title'    => get_sub_field( 'authors_title' ),
					'subtitle' => get_sub_field( 'authors_subtitle' ),
					'authors'  => (array) get_sub_field( 'authors_list' ),
				)
			);
		endif;
	endwhile;
	?>
</div>
