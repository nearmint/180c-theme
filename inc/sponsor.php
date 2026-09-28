<?php
/**
 * Encart de sponsorisation en tête d'article (post).
 *
 * Rendu automatique d'un encart « Contenu sponsorisé » au sommet du corps
 * des articles marqués via le field group ACF `group_180c_sponsor`
 * (champ `est_sponsorise`). Strictement limité au post_type `post` :
 * recettes et produits WooCommerce sont hors périmètre.
 *
 * Le badge « Contenu sponsorisé » est en dur (conformité ARPP / Google :
 * l'identification du caractère publicitaire ne doit pas dépendre d'un
 * champ éditable). La mention, le logo et le lien sortant sont, eux,
 * pilotés par l'auteur.
 *
 * Appelé explicitement dans `single.php` (stratégie template part) plutôt
 * que via un filtre `the_content` : insertion au bon endroit du gabarit
 * d'article (après l'en-tête, avant le corps Gutenberg), sans risque de
 * fuite dans les flux RSS, extraits ou contextes hors boucle.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rend l'encart de sponsorisation d'un article.
 *
 * Ne rend rien (chaîne vide) si :
 *  - le post n'est pas un `post` ;
 *  - le champ ACF `est_sponsorise` est faux / absent ;
 *  - ACF est indisponible.
 *
 * @param int|null $post_id ID de l'article. Défaut : post courant.
 * @return string HTML échappé, ou '' si rien à afficher.
 */
function _180c_render_sponsor_banner( $post_id = null ): string {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return '';
	}
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}
	if ( ! get_field( 'est_sponsorise', $post_id ) ) {
		return '';
	}

	$mention = trim( (string) get_field( 'sponsor_mention', $post_id ) );
	$logo    = get_field( 'sponsor_logo', $post_id );
	$lien    = trim( (string) get_field( 'sponsor_lien', $post_id ) );

	// Logo : tableau ACF (return_format=array) → URL + alt + dimensions.
	// ITEM 9 / CWV : width/height explicites (opportunité `unsized-images`) —
	// ACF expose déjà les dimensions du média, aucune requête supplémentaire.
	$logo_url = '';
	$logo_alt = '';
	$logo_w   = 0;
	$logo_h   = 0;
	if ( is_array( $logo ) && ! empty( $logo['url'] ) ) {
		$logo_url = (string) $logo['url'];
		$logo_alt = ! empty( $logo['alt'] ) ? (string) $logo['alt'] : __( 'Logo du partenaire', '180c' );
		$logo_w   = ! empty( $logo['width'] ) ? (int) $logo['width'] : 0;
		$logo_h   = ! empty( $logo['height'] ) ? (int) $logo['height'] : 0;
	}

	// Libellé « propre » du site (host sans protocole ni www.), réutilise le
	// helper des coordonnées de lieu. Affiché sous la mention.
	$site_label = '';
	if ( '' !== $lien && function_exists( '_180c_cl_site_label' ) ) {
		$site_label = _180c_cl_site_label( $lien );
	}

	ob_start();
	?>
	<aside class="block-180c-encart-sponsor" role="complementary" aria-label="<?php esc_attr_e( 'Contenu sponsorisé', '180c' ); ?>">
		<p class="block-180c-encart-sponsor__label"><?php esc_html_e( 'Contenu sponsorisé', '180c' ); ?></p>
		<div class="block-180c-encart-sponsor__body">
			<?php if ( '' !== $mention || '' !== $lien ) : ?>
				<div class="block-180c-encart-sponsor__text">
					<?php if ( '' !== $mention ) : ?>
						<div class="block-180c-encart-sponsor__mention"><?php echo nl2br( esc_html( $mention ) ); ?></div>
					<?php endif; ?>
					<?php if ( '' !== $lien ) : ?>
						<a class="block-180c-encart-sponsor__site" href="<?php echo esc_url( $lien ); ?>" rel="sponsored noopener" target="_blank">
							<?php echo esc_html( '' !== $site_label ? $site_label : $lien ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php
			if ( '' !== $logo_url ) :
				$logo_img = sprintf(
					'<img class="block-180c-encart-sponsor__logo" src="%1$s" alt="%2$s"%3$s loading="lazy" decoding="async" />',
					esc_url( $logo_url ),
					esc_attr( $logo_alt ),
					( $logo_w && $logo_h ) ? sprintf( ' width="%d" height="%d"', $logo_w, $logo_h ) : ''
				);
				if ( '' !== $lien ) :
					?>
					<a class="block-180c-encart-sponsor__lien" href="<?php echo esc_url( $lien ); ?>" rel="sponsored noopener" target="_blank">
						<?php echo $logo_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup construit avec esc_url/esc_attr ci-dessus. ?>
					</a>
					<?php
				else :
					echo $logo_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup construit avec esc_url/esc_attr ci-dessus.
				endif;
			endif;
			?>
		</div>
	</aside>
	<?php
	return (string) ob_get_clean();
}
