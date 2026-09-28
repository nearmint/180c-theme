<?php
/**
 * Template Name: Plan du site
 * Template Post Type: page
 *
 * Plan du site HTML (/plan-du-site/) — rendu serveur pur, zéro JS.
 *
 * Arbre 100 % dynamique fourni par _180c_get_sitemap_data() (inc/sitemap-page.php) :
 * pages indexables, hubs d'archives, termes de taxonomies non vides,
 * contributeurs publics. Maillage interne crawlable (liens dofollow, ancres
 * descriptives). Page elle-même indexable (aucun noindex posé ici).
 *
 * Auto-appliqué par WordPress à la page de slug `plan-du-site`
 * (page-{slug}.php). Le Template Name permet aussi l'affectation manuelle.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_sitemap = function_exists( '_180c_get_sitemap_data' )
	? _180c_get_sitemap_data()
	: array(
		'pages'    => array(),
		'sections' => array(),
		'authors'  => array(),
	);

get_header();
?>

<main id="main" class="sitemap site-container" data-component="sitemap">

	<header class="sitemap__header">
		<h1 class="sitemap__title"><?php the_title(); ?></h1>
		<?php
		$_180c_intro = trim( wp_strip_all_tags( (string) get_the_content() ) );
		if ( '' !== $_180c_intro ) :
			?>
			<div class="sitemap__intro"><?php the_content(); ?></div>
		<?php else : ?>
			<p class="sitemap__intro"><?php esc_html_e( 'Toutes les sections du site 180°C, d’un seul coup d’œil.', '180c' ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( ! empty( $_180c_sitemap['pages'] ) ) : ?>
		<section class="sitemap__section" aria-labelledby="sitemap-pages">
			<h2 class="sitemap__section-title" id="sitemap-pages"><?php esc_html_e( 'Pages principales', '180c' ); ?></h2>
			<ul class="sitemap__list">
				<?php foreach ( $_180c_sitemap['pages'] as $_180c_page ) : ?>
					<li class="sitemap__item">
						<a class="sitemap__link" href="<?php echo esc_url( $_180c_page['url'] ); ?>"><?php echo esc_html( $_180c_page['title'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php
	foreach ( $_180c_sitemap['sections'] as $_180c_i => $_180c_section ) :
		if ( empty( $_180c_section['hub'] ) && empty( $_180c_section['groups'] ) ) {
			continue;
		}
		$_180c_section_id = 'sitemap-section-' . (int) $_180c_i;
		?>
		<section class="sitemap__section" aria-labelledby="<?php echo esc_attr( $_180c_section_id ); ?>">
			<h2 class="sitemap__section-title" id="<?php echo esc_attr( $_180c_section_id ); ?>"><?php echo esc_html( $_180c_section['label'] ); ?></h2>

			<?php if ( ! empty( $_180c_section['hub'] ) ) : ?>
				<p class="sitemap__hub">
					<a class="sitemap__link sitemap__link--hub" href="<?php echo esc_url( $_180c_section['hub']['url'] ); ?>"><?php echo esc_html( $_180c_section['hub']['title'] ); ?></a>
				</p>
			<?php endif; ?>

			<?php foreach ( $_180c_section['groups'] as $_180c_group ) : ?>
				<div class="sitemap__group">
					<h3 class="sitemap__group-title"><?php echo esc_html( $_180c_group['label'] ); ?></h3>
					<ul class="sitemap__list">
						<?php foreach ( $_180c_group['items'] as $_180c_item ) : ?>
							<li class="sitemap__item">
								<a class="sitemap__link" href="<?php echo esc_url( $_180c_item['url'] ); ?>"><?php echo esc_html( $_180c_item['title'] ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endforeach; ?>

	<?php if ( ! empty( $_180c_sitemap['authors'] ) ) : ?>
		<section class="sitemap__section" aria-labelledby="sitemap-authors">
			<h2 class="sitemap__section-title" id="sitemap-authors"><?php esc_html_e( 'Contributeurs', '180c' ); ?></h2>
			<ul class="sitemap__list sitemap__list--authors">
				<?php foreach ( $_180c_sitemap['authors'] as $_180c_author ) : ?>
					<li class="sitemap__item">
						<a class="sitemap__link" href="<?php echo esc_url( $_180c_author['url'] ); ?>"><?php echo esc_html( $_180c_author['title'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

</main>

<?php
get_footer();
