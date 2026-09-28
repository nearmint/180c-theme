<?php
/**
 * Module partagé — entry-header (en-tête éditorial réutilisable).
 *
 * Renderer **data-agnostic** consommé par single-recipe.php (catégorie ·
 * saison + titre + intro + byline) et single.php (catégorie · date +
 * titre + chapô + byline). Source unique de la typographie de l'en-tête
 * éditorial : changer un token ici met à jour les deux pages.
 *
 * Pas de microdata Schema.org : la couverture sémantique passe par le
 * graphe JSON-LD (inc/seo/schema.php + recipe-schema.php / article-schema.php).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rend l'en-tête éditorial (eyebrow, titre, chapô, byline).
 *
 * Markup BEM :
 *   <header class="entry-header">
 *     <div class="entry-header__eyebrow">
 *       <a class="entry-header__eyebrow-link">…</a>
 *       <span class="entry-header__sep" aria-hidden="true">·</span>
 *       <span class="entry-header__eyebrow-text">…</span>   (label sans URL)
 *     </div>
 *     <h1 class="entry-header__title">…</h1>
 *     <p class="entry-header__standfirst">…</p>
 *     <p class="entry-header__byline">Par <a rel="author">…</a></p>
 *   </header>
 *
 * @param array $args {
 *     Arguments de rendu de l'en-tête.
 *
 *     @type array  $eyebrow    Liste de termes joints par « · » — chaque
 *                              item = ['label' => string, 'url' => string|null,
 *                              'datetime' => string|null]. Si `datetime` est
 *                              fourni (et pas d'`url`), le label est rendu en
 *                              <time datetime="…"> sémantique.
 *     @type string $title      Titre principal — rendu en <h1>, esc_html.
 *     @type string $standfirst Chapô optionnel — esc_html. Vide = pas de rendu.
 *     @type array  $byline     Optionnel : ['name' => string, 'url' => string].
 *                              `url` peut contenir un fragment (#recettes).
 * }
 * @return string HTML échappé.
 */
function _180c_render_entry_header( array $args ) {
	$eyebrow    = isset( $args['eyebrow'] ) && is_array( $args['eyebrow'] ) ? $args['eyebrow'] : array();
	$title      = isset( $args['title'] ) ? (string) $args['title'] : '';
	$standfirst = isset( $args['standfirst'] ) ? (string) $args['standfirst'] : '';
	$byline     = isset( $args['byline'] ) && is_array( $args['byline'] ) ? $args['byline'] : null;

	if ( '' === $title ) {
		return '';
	}

	ob_start();
	?>
	<header class="entry-header">

		<?php if ( ! empty( $eyebrow ) ) : ?>
			<div class="entry-header__eyebrow">
				<?php
				$entry_header_first = true;
				foreach ( $eyebrow as $entry_header_item ) {
					if ( ! is_array( $entry_header_item ) || empty( $entry_header_item['label'] ) ) {
						continue;
					}
					if ( ! $entry_header_first ) {
						echo '<span class="entry-header__sep" aria-hidden="true">·</span>';
					}
					$entry_header_first = false;

					$entry_header_url      = isset( $entry_header_item['url'] ) ? (string) $entry_header_item['url'] : '';
					$entry_header_datetime = isset( $entry_header_item['datetime'] ) ? (string) $entry_header_item['datetime'] : '';
					if ( '' !== $entry_header_url ) {
						printf(
							'<a class="entry-header__eyebrow-link" href="%s">%s</a>',
							esc_url( $entry_header_url ),
							esc_html( $entry_header_item['label'] )
						);
					} elseif ( '' !== $entry_header_datetime ) {
						printf(
							'<time class="entry-header__eyebrow-text" datetime="%s">%s</time>',
							esc_attr( $entry_header_datetime ),
							esc_html( $entry_header_item['label'] )
						);
					} else {
						printf(
							'<span class="entry-header__eyebrow-text">%s</span>',
							esc_html( $entry_header_item['label'] )
						);
					}
				}
				?>
			</div>
		<?php endif; ?>

		<h1 class="entry-header__title"><?php echo esc_html( $title ); ?></h1>

		<?php if ( '' !== $standfirst ) : ?>
			<p class="entry-header__standfirst"><?php echo esc_html( $standfirst ); ?></p>
		<?php endif; ?>

		<?php if ( $byline && ! empty( $byline['name'] ) ) : ?>
			<p class="entry-header__byline">
				<?php
				$byline_name = (string) $byline['name'];
				$byline_url  = isset( $byline['url'] ) ? (string) $byline['url'] : '';

				if ( '' !== $byline_url ) {
					printf(
						/* translators: %s: lien vers l'auteur. */
						esc_html__( 'Par %s', '180c' ),
						sprintf(
							'<a href="%s" rel="author">%s</a>',
							esc_url( $byline_url ),
							esc_html( $byline_name )
						)
					);
				} else {
					printf(
						/* translators: %s: nom de l'auteur. */
						esc_html__( 'Par %s', '180c' ),
						esc_html( $byline_name )
					);
				}
				?>
			</p>
		<?php endif; ?>

	</header>
	<?php
	return (string) ob_get_clean();
}
