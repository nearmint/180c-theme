<?php
/**
 * Module partagé — share-actions (barre de partage social + favoris).
 *
 * Logique de partage **data-agnostic** consommée par single-recipe.php et
 * single.php. Le helper de réseaux historique `_180c_recipe_share_links()`
 * (inc/recipe-share.php) devient un wrapper qui délègue ici — la source
 * de vérité du markup partagé vit désormais dans ce module.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Construit la map réseau → URL d'intent de partage.
 *
 * Reprend strictement la logique (`_180c_recipe_share_links`) en
 * la rendant neutre (URL + titre + image en arguments — pas de post_id).
 *
 * @param string $url   URL canonique à partager.
 * @param string $title Titre à passer aux intents.
 * @return array<string,string> Clé = réseau, valeur = URL d'intent.
 */
function _180c_share_links( $url, $title ) {
	$url   = (string) $url;
	$title = (string) $title;

	if ( '' === $url || '' === $title ) {
		return array();
	}

	$url_enc   = rawurlencode( $url );
	$title_enc = rawurlencode( $title );

	$links = array(
		'x'         => 'https://twitter.com/intent/tweet?url=' . $url_enc . '&text=' . $title_enc,
		'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $url_enc,
		'whatsapp'  => 'https://api.whatsapp.com/send?text=' . $title_enc . '%20' . $url_enc,
		'linkedin'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url_enc,
		'mail'      => 'mailto:?subject=' . $title_enc . '&body=' . $url_enc,
		'link'      => $url,
	);

	/**
	 * Permet de filtrer les liens de partage neutres.
	 *
	 * @param array<string,string> $links Liens par réseau.
	 * @param string               $url   URL partagée.
	 * @param string               $title Titre partagé.
	 */
	return (array) apply_filters( '180c/share_links', $links, $url, $title );
}

/**
 * Libellés humains pour chaque réseau de partage.
 *
 * @return array<string,string>
 */
function _180c_share_labels() {
	return array(
		'x'         => __( 'Partager sur X', '180c' ),
		'facebook'  => __( 'Partager sur Facebook', '180c' ),
		'whatsapp'  => __( 'Partager sur WhatsApp', '180c' ),
		'linkedin'  => __( 'Partager sur LinkedIn', '180c' ),
		'mail'      => __( 'Partager par e-mail', '180c' ),
		'link'      => __( 'Copier le lien', '180c' ),
	);
}

/**
 * Rend la barre d'actions de partage (réseaux sociaux + copier-lien +
 * favoris optionnel).
 *
 * Markup BEM :
 *   <section class="share-actions" aria-label="…">
 *     <div class="share-actions__inner">
 *       <nav class="share-actions__share" data-share-bar data-share-url="…">
 *         <a|button class="share-actions__btn">…</a|button>  (par réseau)
 *         <span class="share-actions__feedback" role="status" aria-live="polite"></span>
 *       </nav>
 *       {if show_favorite}<button class="share-actions__favorite is-disabled" disabled>…</button>{/if}
 *     </div>
 *   </section>
 *
 * Les data-attributes `data-share-bar` / `data-share-url` /
 * `data-share-copy` / `data-share-feedback` restent neutres pour que le JS
 * recipe.js (qui pilote le bouton « copier le lien ») continue de
 * fonctionner — le sélecteur sera mis à jour avec le selecteur `data-`
 * neutre dans une passe ultérieure (le selecteur `[data-recipe-share]`
 * historique reste compatible : on l'imprime aussi en alias).
 *
 * @param array $args {
 *     Arguments de rendu de la barre d'actions.
 *
 *     @type string $url           URL canonique à partager.
 *     @type string $title         Titre à utiliser dans les intents.
 *     @type bool   $show_favorite Affiche un bouton favoris (placeholder).
 *                                 Défaut : false.
 *     @type string $favorite_label Libellé du bouton favoris.
 *     @type string $aria_label    aria-label de la <section>. Défaut :
 *                                 « Partager ».
 *     @type string $compat_section_class Classes BEM additionnelles sur
 *                                        la <section> (back-compat recette).
 *     @type string $compat_inner_class   Classes BEM additionnelles sur
 *                                        la <div> interne (ex. container-180c).
 *     @type string $compat_nav_class     Classes BEM additionnelles sur
 *                                        la <nav> de partage (back-compat).
 * }
 * @return string HTML échappé.
 */
function _180c_render_share_actions( array $args ) {
	$url            = isset( $args['url'] ) ? (string) $args['url'] : '';
	$title          = isset( $args['title'] ) ? (string) $args['title'] : '';
	$show_favorite  = ! empty( $args['show_favorite'] );
	$favorite_id    = isset( $args['favorite_recipe_id'] ) ? absint( $args['favorite_recipe_id'] ) : 0;
	$favorite_label = isset( $args['favorite_label'] ) ? (string) $args['favorite_label'] : __( 'Ajouter à mes favoris', '180c' );
	$show_print     = ! empty( $args['show_print'] );
	$print_label    = isset( $args['print_label'] ) ? (string) $args['print_label'] : __( 'Imprimer', '180c' );
	// Désactive imprimer + favori (réservés aux abonnés sur recette premium).
	$actions_disabled = ! empty( $args['actions_disabled'] );
	// Désactive UNIQUEMENT le favori (ex. carnet réservé aux utilisateurs
	// connectés) sans toucher au bouton imprimer.
	$favorite_disabled = ! empty( $args['favorite_disabled'] );
	$aria_label       = isset( $args['aria_label'] ) ? (string) $args['aria_label'] : __( 'Partager', '180c' );
	$compat_section   = isset( $args['compat_section_class'] ) ? (string) $args['compat_section_class'] : '';
	$compat_inner     = isset( $args['compat_inner_class'] ) ? (string) $args['compat_inner_class'] : '';
	$compat_nav       = isset( $args['compat_nav_class'] ) ? (string) $args['compat_nav_class'] : '';

	$links = _180c_share_links( $url, $title );
	if ( empty( $links ) ) {
		return '';
	}

	$labels = _180c_share_labels();

	/**
	 * Canaux de partage normalisés pour la mesure Umami.
	 *
	 * Les clés internes techniques sont traduites en libellés de canal stables
	 * côté analytics ; toute clé absente de la table est reprise telle quelle.
	 */
	$umami_channels = array(
		'mail' => 'email',
		'link' => 'copy',
	);

	// Identifiant de contenu partagé/imprimé : l'ID favori quand il est fourni,
	// sinon le post courant. Jamais de donnée nominative.
	$umami_content_id = $favorite_id > 0 ? $favorite_id : (int) get_the_ID();

	$section_classes = trim( 'share-actions ' . $compat_section );
	$inner_classes   = trim( 'share-actions__inner ' . $compat_inner );
	$nav_classes     = trim( 'share-actions__share ' . $compat_nav );

	ob_start();
	?>
	<section class="<?php echo esc_attr( $section_classes ); ?>" aria-label="<?php echo esc_attr( $aria_label ); ?>">
		<div class="<?php echo esc_attr( $inner_classes ); ?>">

			<nav class="<?php echo esc_attr( $nav_classes ); ?>"
				aria-label="<?php echo esc_attr( $aria_label ); ?>"
				data-share-bar
				data-recipe-share
				data-share-url="<?php echo esc_url( $url ); ?>"
			>
				<?php
				foreach ( $links as $share_network => $share_url ) :
					$share_label = isset( $labels[ $share_network ] ) ? $labels[ $share_network ] : ucfirst( $share_network );
					$share_umami = _180c_umami_attrs(
						'recipe_share',
						array(
							'channel' => isset( $umami_channels[ $share_network ] ) ? $umami_channels[ $share_network ] : $share_network,
						)
					);
					if ( 'link' === $share_network ) :
						?>
						<button
							type="button"
							class="share-actions__btn share-actions__btn--copy recipe-share__btn recipe-share__btn--copy"
							data-share-copy="<?php echo esc_attr( $share_url ); ?>"
							<?php echo $share_umami; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs déjà échappés par _180c_umami_attrs(). ?>
						>
							<span class="share-actions__icon recipe-share__icon" aria-hidden="true"><?php echo _180c_render_svg_icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="screen-reader-text"><?php echo esc_html( $share_label ); ?></span>
						</button>
						<?php
					elseif ( 'mail' === $share_network ) :
						?>
						<a
							class="share-actions__btn recipe-share__btn"
							href="<?php echo esc_url( $share_url ); ?>"
							<?php echo $share_umami; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs déjà échappés par _180c_umami_attrs(). ?>
						>
							<span class="share-actions__icon recipe-share__icon" aria-hidden="true"><?php echo _180c_render_svg_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="screen-reader-text"><?php echo esc_html( $share_label ); ?></span>
						</a>
						<?php
					else :
						?>
						<a
							class="share-actions__btn recipe-share__btn"
							href="<?php echo esc_url( $share_url ); ?>"
							target="_blank"
							rel="noopener noreferrer"
							<?php echo $share_umami; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs déjà échappés par _180c_umami_attrs(). ?>
						>
							<span class="share-actions__icon recipe-share__icon" aria-hidden="true"><?php echo _180c_render_svg_icon( $share_network ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="screen-reader-text">
								<?php
								printf(
									/* translators: %s: libellé d'action de partage. */
									esc_html__( '%s (nouvelle fenêtre)', '180c' ),
									esc_html( $share_label )
								);
								?>
							</span>
						</a>
						<?php
					endif;
				endforeach;
				?>

				<span class="share-actions__feedback recipe-share__feedback" role="status" aria-live="polite" data-share-feedback></span>
			</nav>

			<?php
			// Bouton « Imprimer » (desktop only via CSS), placé À GAUCHE du favori.
			if ( $show_print ) :
				$print_class = 'recipe-print-btn js-recipe-print' . ( $actions_disabled ? ' is-disabled' : '' );
				?>
				<button type="button" class="<?php echo esc_attr( $print_class ); ?>" data-recipe-print
					<?php echo _180c_umami_attrs( 'recipe_print', array( 'recipe_id' => $umami_content_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php
					if ( $actions_disabled ) :
						?>
						disabled aria-disabled="true" title="<?php esc_attr_e( 'Réservé aux abonnés', '180c' ); ?>"
						<?php
					endif;
					?>
				>
					<span class="recipe-print-btn__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></span>
					<span class="recipe-print-btn__label"><?php echo esc_html( $print_label ); ?></span>
				</button>
				<?php
			endif;

			if ( $show_favorite ) :
				if ( $favorite_id > 0 && function_exists( '_180c_render_favorite_button' ) ) :
					// Bouton favori actif — surface « single », désactivable.
					// Désactivé si premium non débloqué OU visiteur non connecté ;
					// le tooltip reflète la raison (connexion vs abonnement).
					$favorite_is_disabled = $actions_disabled || $favorite_disabled;
					$favorite_title       = ( $favorite_disabled && ! $actions_disabled )
						? __( 'Connectez-vous pour ajouter à votre carnet', '180c' )
						: '';
					echo _180c_render_favorite_button( $favorite_id, 'single', $favorite_is_disabled, $favorite_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le renderer.
				else :
					// Fallback placeholder désactivé (aucun recipe_id fourni).
					?>
					<button
						type="button"
						class="btn btn--secondary share-actions__favorite recipe-favorite is-disabled"
						disabled
						aria-disabled="true"
						title="<?php esc_attr_e( 'Bientôt disponible', '180c' ); ?>"
					>
						<span><?php echo esc_html( $favorite_label ); ?></span>
					</button>
					<?php
				endif;
			endif;
			?>

		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}
