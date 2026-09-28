<?php
/**
 * Page À propos — manifeste (hero LCP + WYSIWYG d'intro).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_hero_id = (int) get_field( 'about_hero_image' );
?>
<header class="about-hero">
	<?php
	if ( $_180c_hero_id ) {
		echo wp_get_attachment_image(
			$_180c_hero_id,
			'full',
			false,
			array(
				'class'         => 'about-hero__img',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
				'alt'           => '',
			)
		);
	}
	?>
	<div class="about-hero__head about-measure">
		<h1 class="about-hero__title"><?php the_title(); ?></h1>
	</div>
</header>

<?php $_180c_intro = get_field( 'about_intro' ); ?>
<?php if ( $_180c_intro ) : ?>
	<section class="about-intro">
		<div class="about-measure about-prose">
			<?php echo wp_kses_post( $_180c_intro ); ?>
		</div>
	</section>
<?php endif; ?>
