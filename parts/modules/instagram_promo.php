<?php
/**
 * Template part — Module home : promotion du compte Instagram.
 *
 * Sous-champs ACF :
 *   - titre (text)
 *   - display (radio web_only|app_only|web_app, lu par le dispatcher — cf.
 *     _180c_render_home_modules())
 *
 * Trois tuiles sur une rangée : une image de recette, une carte portant
 * l'icône et le handle, une seconde image de recette. Les trois pointent vers
 * la même URL Instagram.
 *
 * Aucune API Instagram, aucun appel HTTP sortant, aucun JS : les visuels sont
 * des images mises en avant de recettes du site, tirées au sort à chaque
 * rendu. C'est une vitrine éditoriale, pas un flux — elle ne prétend pas
 * afficher les publications réelles du compte.
 *
 * Les images ne sont JAMAIS recadrées : chaque tuile reçoit le ratio natif de
 * son image via la variable CSS `--home-instagram-ratio`, et l'image la
 * remplit sans `object-fit`. Une couverture portrait et un plat paysage
 * cohabitent donc à des hauteurs différentes, ce qui est le comportement
 * voulu.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_ig_url    = 'https://www.instagram.com/180c_larevue/';
$_180c_ig_handle = '@180c_larevue';
$_180c_ig_title  = (string) get_sub_field( 'titre' );

/*
 * Deux recettes publiées de moins d'un an, disposant d'une image mise en
 * avant. `orderby => rand` est acceptable ici : la requête est bornée à deux
 * lignes, ne porte que sur des IDs, et le module est purement décoratif.
 */
$_180c_ig_query = new WP_Query(
	array(
		'post_type'           => 'recipe',
		'post_status'         => 'publish',
		'posts_per_page'      => 2,
		'orderby'             => 'rand',
		'fields'              => 'ids',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'date_query'          => array(
			array( 'after' => '1 year ago' ),
		),
		'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Test d'existence sur `_thumbnail_id`, indexé ; borné à 2 résultats.
			array(
				'key'     => '_thumbnail_id',
				'compare' => 'EXISTS',
			),
		),
	)
);

// Garde-fou : moins de 2 recettes éligibles => le module n'est pas rendu du
// tout, plutôt qu'une rangée bancale à une seule image.
if ( count( $_180c_ig_query->posts ) < 2 ) {
	return;
}

$_180c_ig_thumbs = array();
foreach ( $_180c_ig_query->posts as $_180c_ig_post_id ) {
	$_180c_ig_thumb_id = (int) get_post_thumbnail_id( (int) $_180c_ig_post_id );
	if ( $_180c_ig_thumb_id ) {
		$_180c_ig_thumbs[] = $_180c_ig_thumb_id;
	}
}

// `_thumbnail_id` peut exister en pointant sur une pièce jointe supprimée :
// la meta_query ci-dessus ne le détecte pas, ce contrôle si.
if ( count( $_180c_ig_thumbs ) < 2 ) {
	return;
}

/*
 * Libellé accessible unique pour les trois liens : même destination, même
 * intitulé (WCAG 2.4.4). Le `target="_blank"` est annoncé dans le libellé.
 */
$_180c_ig_link_label = __( 'Découvrir le compte Instagram de 180°C (nouvelle fenêtre)', '180c' );

/*
 * `id` dérivé de l'index du module dans le Flexible Content (query var posée
 * par le dispatcher) : deux occurrences du module sur la même page ne
 * produisent pas d'`id` en double, ce qui casserait les deux aria-labelledby.
 */
$_180c_ig_title_id = 'home-instagram-title-' . (int) get_query_var( '_180c_block_index' );

/*
 * Les images sont décoratives : le lien porte son propre nom accessible, et
 * il ne mène pas à la recette dont l'image est issue — annoncer son titre
 * serait un contresens. Le thème pose un repli d'`alt` global
 * (_180c_a11y_fallback_image_alt, inc/media-alt.php) qui remplace tout `alt`
 * vide par la légende ou le titre du post parent ; on le neutralise le temps du
 * rendu via le helper mutualisé posé à une priorité supérieure, puis on le
 * retire.
 */
$_180c_ig_has_alt_filter = function_exists( '_180c_collection_decorative_alt' );
if ( $_180c_ig_has_alt_filter ) {
	add_filter( 'wp_get_attachment_image_attributes', '_180c_collection_decorative_alt', 20 );
}
?>
<section class="home-module home-module--instagram_promo home-instagram" aria-labelledby="<?php echo esc_attr( $_180c_ig_title_id ); ?>">
	<div class="home-instagram__inner container-180c">
		<header class="home-instagram__header">
			<span class="home-instagram__eyebrow"><?php esc_html_e( 'Instagram', '180c' ); ?></span>
			<h2 class="home-instagram__title" id="<?php echo esc_attr( $_180c_ig_title_id ); ?>">
				<?php echo esc_html( '' !== $_180c_ig_title ? $_180c_ig_title : $_180c_ig_handle ); ?>
			</h2>
		</header>

		<div class="home-instagram__grid">
			<?php
			foreach ( $_180c_ig_thumbs as $_180c_ig_index => $_180c_ig_thumb ) :
				$_180c_ig_meta  = wp_get_attachment_metadata( $_180c_ig_thumb );
				$_180c_ig_ratio = ( ! empty( $_180c_ig_meta['width'] ) && ! empty( $_180c_ig_meta['height'] ) )
					? $_180c_ig_meta['width'] . ' / ' . $_180c_ig_meta['height']
					: '1 / 1';
				?>
				<a class="home-instagram__tile home-instagram__tile--media"
					href="<?php echo esc_url( $_180c_ig_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="<?php echo esc_attr( $_180c_ig_link_label ); ?>"
					style="--home-instagram-ratio: <?php echo esc_attr( $_180c_ig_ratio ); ?>;">
					<?php
					/*
					 * `large` : seule taille disponible sans recadrage — les
					 * trois tailles du thème (recipe-hero / recipe-card /
					 * recipe-thumb) sont toutes en hard crop.
					 */
					echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() échappe ses propres attributs.
						$_180c_ig_thumb,
						'large',
						false,
						array(
							'class'    => 'home-instagram__img',
							'alt'      => '',
							'loading'  => 'lazy',
							'decoding' => 'async',
							'sizes'    => '(min-width: 768px) 30vw, 100vw',
						)
					);
					?>
				</a>

				<?php
				// La carte s'intercale entre les deux visuels.
				if ( 0 === $_180c_ig_index ) :
					?>
					<a class="home-instagram__tile home-instagram__tile--card"
						href="<?php echo esc_url( $_180c_ig_url ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						aria-label="<?php echo esc_attr( $_180c_ig_link_label ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="34" height="34" fill="none"
							stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
							aria-hidden="true" focusable="false" class="home-instagram__icon">
							<rect x="3" y="3" width="18" height="18" rx="5"></rect>
							<circle cx="12" cy="12" r="4"></circle>
							<circle cx="17.5" cy="6.5" r="0.8" fill="currentColor" stroke="none"></circle>
						</svg>
						<p class="home-instagram__handle"><?php echo esc_html( $_180c_ig_handle ); ?></p>
					</a>
					<?php
				endif;
			endforeach;
			?>
		</div>
	</div>
</section>
<?php
if ( $_180c_ig_has_alt_filter ) {
	remove_filter( 'wp_get_attachment_image_attributes', '_180c_collection_decorative_alt', 20 );
}
