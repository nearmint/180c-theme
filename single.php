<?php
/**
 * Template article — format éditorial long et immersif.
 *
 * Articles = post natifs. Le template valorise la photo et la signature :
 *  1. Barre de progression de lecture (enhancement, dégrade proprement sans JS).
 *  2. Hero immersif : image pleine largeur + crédit photo repliable (©).
 *  3. En-tête éditorial (eyebrow catégorie · date + H1 + chapô + byline)
 *     dans un container de lecture étroit centré, via le module partagé
 *     `_180c_render_entry_header()`.
 *  4. Actions partage social via `_180c_render_share_actions()` (pas de favoris).
 *  5. Corps Gutenberg via the_content() — largeur de lecture confortable.
 *  6. Bloc « produit source » mutualisé recette + article, conditionnel
 *     (ACF related_product) — rendu via _180c_render_source_product().
 *  7. Bio auteur (réutilise les helpers _180c_author_*).
 *  8. À lire aussi (3 articles même catégorie principale).
 *
 * Le JSON-LD Article est injecté dans le @graph global de la page
 * (inc/seo/schema.php) — pas de second bloc JSON-LD côté template.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main article">
	<?php
	while ( have_posts() ) :
		the_post();

		$single_post_id   = (int) get_the_ID();
		$single_author_id = (int) get_post_field( 'post_author', $single_post_id );

		// En-tête éditorial via module partagé — dans un container de
		// lecture (mêmes contraintes que le corps : ~44rem / 68ch centré).
		$single_eyebrow  = array();
		$single_category = _180c_article_primary_category( $single_post_id );
		if ( $single_category instanceof WP_Term ) {
			$single_eyebrow[] = array(
				'label' => $single_category->name,
				'url'   => (string) get_category_link( $single_category ),
			);
		}
		$single_eyebrow[] = array(
			'label'    => get_the_date( '', $single_post_id ),
			// Pas de lien sur la date — rendue en <time datetime> sémantique.
			'datetime' => get_the_date( 'Y-m-d', $single_post_id ),
		);

		$single_byline = null;
		if ( $single_author_id ) {
			$single_byline = array(
				'name' => _180c_author_display_name( $single_author_id ),
				// Convention thème (cf. inc/author.php) : la page auteur est
				// `get_author_posts_url($id)`. Pas d'helper `_180c_author_url`.
				'url'  => get_author_posts_url( $single_author_id ),
			);
		}
		?>
		<div class="article__header-container">
			<?php
			// Hero (image + crédit repliable + bouton agrandir) : rendu DANS le
			// même container que l'en-tête éditorial pour partager exactement sa
			// largeur et se coller à son bord supérieur (carte continue).
			get_template_part( 'patterns/article-hero' );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML produit par le renderer (échappement interne).
			echo _180c_render_entry_header(
				array(
					'eyebrow'    => $single_eyebrow,
					'title'      => get_the_title( $single_post_id ),
					'standfirst' => _180c_article_chapo( $single_post_id ),
					'byline'     => $single_byline,
				)
			);

			// Actions partage social (sans favoris).
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML produit par le renderer (échappement interne).
			echo _180c_render_share_actions(
				array(
					'url'                  => get_permalink( $single_post_id ),
					'title'                => get_the_title( $single_post_id ),
					'show_favorite'        => false,
					'aria_label'           => __( 'Partager cet article', '180c' ),
					'compat_section_class' => 'article__share',
				)
			);
			?>
		</div>

		<?php
		// Encart de sponsorisation (ACF) — en tête de corps, conditionnel.
		// No-op propre si l'article n'est pas marqué « est_sponsorise ».
		// Placé hors de <article itemprop="articleBody"> : l'encart de
		// transparence publicitaire n'appartient pas au corps sémantique.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML produit par le renderer (échappement interne).
		echo _180c_render_sponsor_banner( $single_post_id );
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'article__body' ); ?> itemprop="articleBody">
			<?php the_content(); ?>
		</article>

		<?php
		// Bloc « produit source » mutualisé recette + article
		// (cf. inc/source-product.php) : même markup que la fiche recette,
		// titre adapté au contexte éditorial (« Cet article… »).
		// No-op propre si l'article n'a pas de related_product publié.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML produit par le renderer (échappement interne).
		echo _180c_render_source_product(
			_180c_article_source_issue( $single_post_id ),
			array( 'heading' => __( 'Cet article a initialement été publié dans :', '180c' ) )
		);

		get_template_part( 'patterns/article-author-bio' );
		get_template_part( 'patterns/article-related' );
	endwhile;
	?>
</main>

<?php
get_footer();
