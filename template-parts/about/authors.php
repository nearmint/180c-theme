<?php
/**
 * Page À propos — section « Auteurs » (répétable).
 *
 * Reçoit via $args : title, subtitle, authors (tableau d'IDs utilisateurs).
 * Toutes les données affichées (photo, nom, bio, site internet) proviennent de
 * la fiche auteur — aucun champ custom. Le nom est cliquable vers `/author/`
 * uniquement si l'auteur publie (anti-lien-mort).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_title    = isset( $args['title'] ) ? $args['title'] : '';
$_180c_subtitle = isset( $args['subtitle'] ) ? $args['subtitle'] : '';
$_180c_ids      = isset( $args['authors'] ) ? array_filter( array_map( 'intval', (array) $args['authors'] ) ) : array();

if ( empty( $_180c_ids ) ) {
	return;
}

$_180c_heading_id = 'about-authors-' . wp_unique_id();
?>
<section class="about-authors" aria-labelledby="<?php echo esc_attr( $_180c_heading_id ); ?>">
	<div class="container">
		<?php if ( $_180c_title ) : ?>
			<h2 id="<?php echo esc_attr( $_180c_heading_id ); ?>" class="about-authors__title"><?php echo esc_html( $_180c_title ); ?></h2>
		<?php endif; ?>
		<?php if ( $_180c_subtitle ) : ?>
			<p class="about-authors__subtitle"><?php echo esc_html( $_180c_subtitle ); ?></p>
		<?php endif; ?>

		<ul class="about-authors__grid" role="list">
			<?php
			foreach ( $_180c_ids as $_180c_uid ) :
				$_180c_a = _180c_about_author( $_180c_uid );
				if ( ! $_180c_a ) {
					continue;
				}
				?>
				<li class="about-author<?php echo $_180c_a['clickable'] ? ' about-author--linked' : ''; ?>">
					<?php echo _180c_about_portrait( $_180c_a['photo_id'], $_180c_a['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() renvoie un markup déjà échappé. ?>

					<?php if ( $_180c_a['clickable'] ) : ?>
						<a class="about-author__name" href="<?php echo esc_url( $_180c_a['url'] ); ?>"><?php echo esc_html( $_180c_a['name'] ); ?></a>
					<?php else : ?>
						<span class="about-author__name"><?php echo esc_html( $_180c_a['name'] ); ?></span>
					<?php endif; ?>

					<?php if ( $_180c_a['bio'] ) : ?>
						<p class="about-author__bio"><?php echo esc_html( $_180c_a['bio'] ); ?></p>
					<?php endif; ?>

					<?php if ( $_180c_a['website'] ) : ?>
						<a class="about-author__website" href="<?php echo esc_url( $_180c_a['website'] ); ?>" rel="noopener" target="_blank">
							<?php esc_html_e( 'Site internet', '180c' ); ?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
