<?php
/**
 * Pattern PHP — header de la page auteur (v2).
 *
 * Identité publique : photo (si ACF `author_photo` renseigné), nom,
 * fonction, bio, réseaux. Si l'admin n'a pas téléversé de photo, le
 * bloc média est **entièrement masqué** (pas de silhouette grise) et le
 * <header> reçoit le modifier `author-header--no-photo` (CSS reflowable).
 *
 * Photo = candidat LCP : eager + fetchpriority + srcset complet.
 *
 * Inclus depuis author.php via get_template_part( 'patterns/author-header' ).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$author_id = (int) get_queried_object_id();
if ( ! $author_id ) {
	return;
}

$author_name = _180c_author_display_name( $author_id );
$bio         = trim( (string) get_the_author_meta( 'description', $author_id ) );
$role        = _180c_author_role( $author_id );
$socials     = _180c_author_socials( $author_id );

$has_avatar = _180c_author_has_real_avatar( $author_id );
$photo_id   = $has_avatar ? _180c_author_photo_attachment_id( $author_id ) : 0;

$header_classes = array( 'author-header' );
if ( ! $has_avatar ) {
	$header_classes[] = 'author-header--no-photo';
}
?>

<header class="<?php echo esc_attr( implode( ' ', $header_classes ) ); ?>" itemscope itemtype="https://schema.org/Person">
	<div class="container author-header__inner">

		<?php if ( $has_avatar && $photo_id ) : ?>
			<figure class="author-header__media">
				<?php
				echo wp_get_attachment_image(
					$photo_id,
					'medium',
					false,
					array(
						'class'         => 'author-header__photo',
						'alt'           => esc_attr( $author_name ),
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
						'sizes'         => '(max-width: 1024px) 140px, 180px',
						'itemprop'      => 'image',
					)
				);
				?>
			</figure>
		<?php endif; ?>

		<div class="author-header__text">
			<h1 class="author-header__name" itemprop="name"><?php echo esc_html( $author_name ); ?></h1>

			<?php if ( '' !== $role ) : ?>
				<p class="author-header__role" itemprop="jobTitle"><?php echo esc_html( $role ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $bio ) : ?>
				<div class="author-header__bio" itemprop="description">
					<?php echo wp_kses_post( wpautop( $bio ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $socials ) ) : ?>
				<ul
					class="author-header__socials"
					aria-label="<?php
						/* translators: %s: nom de l'auteur */
						printf( esc_attr__( 'Réseaux sociaux de %s', '180c' ), esc_attr( $author_name ) );
					?>"
					role="list"
				>
					<?php foreach ( $socials as $social ) : ?>
						<li class="author-header__social">
							<a
								class="author-header__social-link author-header__social-link--<?php echo esc_attr( $social['network'] ); ?>"
								href="<?php echo esc_url( $social['url'] ); ?>"
								rel="me noopener"
								target="_blank"
								itemprop="sameAs"
							>
								<span class="author-header__social-label"><?php echo esc_html( $social['label'] ); ?></span>
								<span class="sr-only"> <?php esc_html_e( '(nouvelle fenêtre)', '180c' ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

	</div>
</header>
