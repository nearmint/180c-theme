<?php
/**
 * Pattern PHP — rail « Découvrez d'autres signatures » (v2 étape 8).
 *
 * Recommande 6 autres signatures classées par volume de contenu publié
 * (articles + recettes), en excluant l'auteur courant.
 *
 * Pattern entièrement masqué s'il ne reste aucun auteur à recommander
 * (site mono-auteur, ou tous les autres auteurs filtrés).
 *
 * Fallback monogramme pour les auteurs sans `author_photo` ACF : un carré
 * arrondi avec initiales (uniformité visuelle du rail). Le header de la
 * page, lui, masque le bloc avatar — pas de monogramme côté hero, c'est
 * délibéré (le header reflowe sans photo, le rail garde un gabarit fixe).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$author_id = (int) get_queried_object_id();
if ( ! $author_id ) {
	return;
}

$related = _180c_author_related_authors( $author_id, 6 );
if ( empty( $related ) ) {
	return;
}
?>

<section class="author-related" aria-labelledby="author-related-title">
	<div class="container">
		<h2 id="author-related-title" class="author-related__title">
			<?php esc_html_e( "Découvrez d'autres signatures", '180c' ); ?>
		</h2>

		<ul class="author-related__grid" role="list">
			<?php foreach ( $related as $user ) :
				$uid       = (int) $user->ID;
				$name      = _180c_author_display_name( $uid );
				$url       = get_author_posts_url( $uid );
				$role      = _180c_author_role( $uid );
				$avatar_id = _180c_author_has_real_avatar( $uid ) ? _180c_author_avatar_id( $uid ) : 0;
				$mono      = ( 0 === $avatar_id ) ? _180c_author_monogram( $uid ) : '';
				?>
				<li class="author-related__item">
					<a class="author-related__link" href="<?php echo esc_url( $url ); ?>">
						<?php
						if ( $avatar_id > 0 ) {
							echo wp_get_attachment_image(
								$avatar_id,
								'thumbnail',
								false,
								array(
									'class'    => 'author-related__avatar',
									'alt'      => '',
									'loading'  => 'lazy',
									'decoding' => 'async',
								)
							);
						} else {
							?>
							<span class="author-related__monogram" aria-hidden="true"><?php echo esc_html( $mono ); ?></span>
							<?php
						}
						?>

						<span class="author-related__name"><?php echo esc_html( $name ); ?></span>
						<?php if ( '' !== $role ) : ?>
							<span class="author-related__role"><?php echo esc_html( $role ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
