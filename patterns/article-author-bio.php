<?php
/**
 * Pattern PHP — bio auteur en fin d'article.
 *
 * Consomme strictement les helpers _180c_author_* pour
 * photo (ACF prioritaire, monogramme si pas de photo), nom, fonction,
 * bio. Deux liens vers la page auteur :
 *  - Voir tous ses articles → /author/{nicename}/#articles
 *  - Voir toutes ses recettes → /author/{nicename}/#recettes (masque
 *    si l'auteur n'a aucune recette — _180c_author_has_recipes).
 *
 * Section <section aria-labelledby> pour rester structuré pour les SR.
 * Le visuel reprend les tokens du composant auteur (cf. /106).
 *
 * Inclus depuis single.php via get_template_part( 'patterns/article-author-bio' ).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$post_id = (int) get_the_ID();
if ( ! $post_id ) {
	return;
}

$author_id = (int) get_post_field( 'post_author', $post_id );
if ( ! $author_id ) {
	return;
}

// Si l'auteur est marqué non-public (toggle ACF author_public off), on
// n'expose pas le bloc bio (cohérent avec la redirection 301 sur les
// pages auteur non publiques — cf. inc/author.php).
if ( function_exists( '_180c_author_is_public' ) && ! _180c_author_is_public( $author_id ) ) {
	return;
}

$author_name = _180c_author_display_name( $author_id );
$author_url  = get_author_posts_url( $author_id );
$role        = _180c_author_role( $author_id );
$bio         = trim( (string) get_the_author_meta( 'description', $author_id ) );
$has_recipes = _180c_author_has_recipes( $author_id );
// Auteurs dont la page n'expose pas de section articles (cf. inc/author.php) :
// le lien « Voir tous ses articles » pointerait sur une ancre inexistante.
$hides_articles = _180c_author_hides_articles( $author_id );

$has_avatar = _180c_author_has_real_avatar( $author_id );
$avatar_id  = $has_avatar ? _180c_author_avatar_id( $author_id ) : 0;
$monogram   = ( 0 === $avatar_id ) ? _180c_author_monogram( $author_id ) : '';
?>

<section class="article-author-bio" aria-labelledby="article-author-bio-title">
	<div class="article-author-bio__inner">

		<figure class="article-author-bio__media">
			<?php if ( $avatar_id > 0 ) : ?>
				<?php
				echo wp_get_attachment_image(
					$avatar_id,
					'thumbnail',
					false,
					array(
						'class'    => 'article-author-bio__photo',
						'alt'      => esc_attr( $author_name ),
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
				?>
			<?php else : ?>
				<span class="article-author-bio__monogram" aria-hidden="true">
					<?php echo esc_html( $monogram ); ?>
				</span>
			<?php endif; ?>
		</figure>

		<div class="article-author-bio__text">
			<h2 id="article-author-bio-title" class="article-author-bio__title">
				<a class="article-author-bio__name" href="<?php echo esc_url( $author_url ); ?>" rel="author">
					<?php echo esc_html( $author_name ); ?>
				</a>
			</h2>

			<?php if ( '' !== $role ) : ?>
				<p class="article-author-bio__role"><?php echo esc_html( $role ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $bio ) : ?>
				<div class="article-author-bio__description">
					<?php echo wp_kses_post( wpautop( $bio ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $hides_articles || $has_recipes ) : ?>
			<ul class="article-author-bio__links" role="list">
				<?php if ( ! $hides_articles ) : ?>
					<li>
						<a class="article-author-bio__link" href="<?php echo esc_url( $author_url . '#articles' ); ?>">
							<?php esc_html_e( 'Voir tous ses articles', '180c' ); ?>
						</a>
					</li>
				<?php endif; ?>
				<?php if ( $has_recipes ) : ?>
					<li>
						<a class="article-author-bio__link" href="<?php echo esc_url( $author_url . '#recettes' ); ?>">
							<?php esc_html_e( 'Voir toutes ses recettes', '180c' ); ?>
						</a>
					</li>
				<?php endif; ?>
			</ul>
			<?php endif; ?>
		</div>

	</div>
</section>
