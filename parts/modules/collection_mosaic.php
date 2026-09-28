<?php
/**
 * Template part — Module home : Collection (mosaïque de couvertures).
 *
 * Sous-champs ACF :
 *   - title (text)
 *   - cta_label (text)
 *   - cta_url (url, optionnel — archive de la catégorie par défaut)
 *
 * Les couvertures ne sont pas saisies : elles proviennent de la catégorie
 * produit « Les revues 180°C », ordonnées par numéro croissant extrait du SKU
 * (cf. _180c_collection_issues() dans inc/home-modules.php).
 *
 * La mosaïque est DÉCORATIVE : aucune couverture n'est cliquable et l'ensemble
 * est retiré de l'arbre d'accessibilité (aria-hidden). L'unique action est le
 * CTA. C'est un choix assumé — des vignettes floutées, inclinées et en
 * défilement permanent font de mauvaises cibles de clic, et le doublage des
 * items pour la boucle aurait produit autant de liens dupliqués.
 *
 * Aucun JS : le défilement, sa désynchronisation et la pause au survol sont
 * intégralement portés par le CSS (cf. components/home/collection-mosaic.css).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$issues = function_exists( '_180c_collection_issues' ) ? _180c_collection_issues() : array();

// Catégorie vide, introuvable, ou aucun numéro exploitable : pas de section
// vide — le module ne s'affiche pas du tout.
if ( empty( $issues ) ) {
	return;
}

/*
 * Densité de la mosaïque. `$per` = nombre de couvertures par colonne AVANT
 * doublage ; il conditionne la continuité de la boucle : la hauteur d'un jeu
 * doit dépasser celle du cadre débordé, sinon un vide traverse la colonne à
 * chaque cycle. Six suffit pour toutes les combinaisons largeur/colonnes
 * gérées par le CSS (calcul détaillé dans collection-mosaic.css).
 *
 * Le nombre de colonnes est celui du desktop ; le CSS masque les dernières aux
 * paliers inférieurs plutôt que de rendre trois DOM différents.
 */
$columns_count = 9;
$per_column    = 6;

$columns = _180c_collection_columns( $issues, $columns_count, $per_column );

if ( empty( $columns ) ) {
	return;
}

$title     = get_sub_field( 'title' );
$cta_label = get_sub_field( 'cta_label' );
$cta_url   = get_sub_field( 'cta_url' );

// CTA sans cible explicite : l'archive de la catégorie source.
if ( ! $cta_url ) {
	$term = _180c_collection_term();
	$link = $term instanceof WP_Term ? get_term_link( $term, 'product_cat' ) : null;
	if ( $link && ! is_wp_error( $link ) ) {
		$cta_url = $link;
	}
}

$region_label = $title ? $title : __( 'La collection 180°C', '180c' );

// Couvertures décoratives : neutralise le repli d'`alt` global du thème le
// temps du rendu (cf. _180c_collection_decorative_alt).
add_filter( 'wp_get_attachment_image_attributes', '_180c_collection_decorative_alt', 20 );
?>
<section class="home-module home-module--collection_mosaic collection-mosaic" aria-label="<?php echo esc_attr( $region_label ); ?>">
	<div class="collection-mosaic__stage">
		<div class="collection-mosaic__grid" aria-hidden="true">
			<?php foreach ( $columns as $column ) : ?>
				<div class="collection-mosaic__column collection-mosaic__column--<?php echo esc_attr( $column['direction'] ); ?>"
					style="--collection-duration: <?php echo esc_attr( $column['duration'] ); ?>s; --collection-offset: <?php echo esc_attr( $column['offset'] ); ?>;">
					<?php
					foreach ( $column['thumbs'] as $thumb_id ) {
						/*
						 * `medium` (225 × 300) et non `medium_large` (768 × 0).
						 *
						 * Les couvertures originales plafonnent à ~596 px de
						 * large : `medium_large` faisait donc servir le fichier
						 * PLEIN, 66,6 Ko pièce, pour un rendu affiché entre 140
						 * et 230 px de large et recouvert d'un voile en
						 * `backdrop-filter: blur(12–16px)`. Le `srcset` rattrapait
						 * partiellement le tir côté navigateur, au prix de
						 * ~37 Ko de HTML (108 balises) ; il est retiré dans
						 * `_180c_collection_decorative_alt()`, cette taille fixe
						 * le remplaçant.
						 *
						 * Ratio préservé : 225/300 = 595/794 = 0,75. C'est un
						 * redimensionnement proportionnel, pas un recadrage —
						 * contrairement à `woocommerce_thumbnail` ou aux tailles
						 * carrées (150×150), dont le hard crop dépend du
						 * Customizer.
						 */
						echo wp_get_attachment_image(
							(int) $thumb_id,
							'medium',
							false,
							array(
								'class'    => 'collection-mosaic__cover',
								'alt'      => '',
								'loading'  => 'lazy',
								'decoding' => 'async',
							)
						);
					}
					?>
				</div>
			<?php endforeach; ?>
			<?php remove_filter( 'wp_get_attachment_image_attributes', '_180c_collection_decorative_alt', 20 ); ?>
		</div>

		<div class="collection-mosaic__veil" aria-hidden="true"></div>
		<div class="collection-mosaic__fade" aria-hidden="true"></div>

		<div class="collection-mosaic__content">
			<?php if ( $title ) : ?>
				<h2 class="collection-mosaic__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( $cta_label && $cta_url ) : ?>
				<a class="btn btn--primary btn--lg collection-mosaic__cta" href="<?php echo esc_url( $cta_url ); ?>">
					<?php echo esc_html( $cta_label ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>
