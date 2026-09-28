<?php
/**
 * Façade YouTube — substitution du rendu front de l'embed YouTube.
 *
 * Filtre `render_block` (FRONT uniquement) : remplace la sortie de l'embed
 * YouTube par une façade légère (vignette cliquable). L'iframe YouTube n'est
 * PAS chargée tant que l'utilisateur ne clique pas (perf + vie privée). Au
 * clic, le module front `src/js/youtube-modal.js` ouvre une modale unique
 * chargeant `youtube-nocookie.com` en grand format.
 *
 * Repli `<noscript>` : iframe nocookie responsive standard (a11y + sans-JS).
 * Le clic vaut action utilisateur explicite → pas de gating par la CMP.
 *
 * Éditeur NON impacté : l'éditeur rend l'embed côté client (oEmbed), pas via
 * ce filtre PHP (garde `is_admin()` + `REST_REQUEST`).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Extrait l'identifiant vidéo (11 caractères) d'une URL YouTube.
 *
 * Couvre watch?v=, youtu.be/, embed/, shorts/, v/.
 *
 * @param string $url URL YouTube.
 * @return string ID vidéo ou chaîne vide.
 */
function _180c_youtube_extract_id( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return '';
	}
	if ( preg_match(
		'~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~',
		$url,
		$matches
	) ) {
		return $matches[1];
	}
	return '';
}

/**
 * Construit le markup de la façade pour un ID donné.
 *
 * @param string $id      ID vidéo YouTube.
 * @param string $caption Légende éventuelle (HTML restreint).
 * @param string $align   Suffixe d'alignement Gutenberg ('', 'wide', 'full'…).
 * @return string Markup HTML de la façade.
 */
function _180c_youtube_facade_markup( $id, $caption = '', $align = '' ) {
	$watch_url = 'https://www.youtube.com/watch?v=' . $id;
	$thumb_max = 'https://img.youtube.com/vi/' . $id . '/maxresdefault.jpg';
	$thumb_hq  = 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg';
	$embed_url = 'https://www.youtube-nocookie.com/embed/' . $id;

	$align_class = '' !== $align ? ' align' . $align : '';

	$play_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 5.14v13.72a1 1 0 0 0 1.54.84l10.79-6.86a1 1 0 0 0 0-1.68L9.54 4.3A1 1 0 0 0 8 5.14Z"/></svg>';

	ob_start();
	?>
	<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube yt-facade<?php echo esc_attr( $align_class ); ?>">
		<div class="wp-block-embed__wrapper">
			<a
				class="yt-facade__btn"
				href="<?php echo esc_url( $watch_url ); ?>"
				data-yt-facade
				data-yt-id="<?php echo esc_attr( $id ); ?>"
				aria-label="<?php esc_attr_e( 'Lire la vidéo', '180c' ); ?>"
			>
				<img
					class="yt-facade__thumb"
					src="<?php echo esc_url( $thumb_max ); ?>"
					width="1280"
					height="720"
					loading="lazy"
					decoding="async"
					alt=""
					onerror="this.onerror=null;this.src='<?php echo esc_url( $thumb_hq ); ?>';"
				/>
				<span class="yt-facade__scrim" aria-hidden="true"></span>
				<span class="yt-facade__play" aria-hidden="true">
					<span class="yt-facade__play-circle"><?php echo $play_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique. ?></span>
					<span class="yt-facade__play-label"><?php esc_html_e( 'Lire la vidéo', '180c' ); ?></span>
				</span>
			</a>
			<noscript>
				<iframe
					class="yt-facade__noscript"
					width="560"
					height="315"
					src="<?php echo esc_url( $embed_url ); ?>"
					title="<?php esc_attr_e( 'Vidéo YouTube', '180c' ); ?>"
					loading="lazy"
					frameborder="0"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
					allowfullscreen
				></iframe>
			</noscript>
		</div>
		<?php if ( '' !== $caption ) : ?>
			<figcaption class="wp-element-caption"><?php echo wp_kses_post( $caption ); ?></figcaption>
		<?php endif; ?>
	</figure>
	<?php
	return (string) ob_get_clean();
}

/**
 * Remplace le rendu de l'embed YouTube par la façade (front uniquement).
 *
 * @param string $block_content Rendu HTML du bloc.
 * @param array  $block         Données du bloc analysé.
 * @return string
 */
function _180c_youtube_facade_render_block( $block_content, $block ) {
	// Garde front-only stricte : jamais en admin ni via REST (block-renderer).
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $block_content;
	}
	if ( empty( $block['blockName'] ) || 'core/embed' !== $block['blockName'] ) {
		return $block_content;
	}

	$provider = isset( $block['attrs']['providerNameSlug'] ) ? (string) $block['attrs']['providerNameSlug'] : '';
	$url      = isset( $block['attrs']['url'] ) ? (string) $block['attrs']['url'] : '';

	// Cible YouTube (par provider ou par URL — robustesse migration).
	if ( 'youtube' !== $provider && false === strpos( $url, 'youtu' ) ) {
		return $block_content;
	}

	$id = _180c_youtube_extract_id( $url );
	if ( '' === $id ) {
		return $block_content;
	}

	$caption = isset( $block['attrs']['caption'] ) ? (string) $block['attrs']['caption'] : '';
	$align   = isset( $block['attrs']['align'] ) ? (string) $block['attrs']['align'] : '';

	return _180c_youtube_facade_markup( $id, $caption, $align );
}
add_filter( 'render_block', '_180c_youtube_facade_render_block', 10, 2 );
