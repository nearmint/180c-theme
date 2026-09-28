<?php
/**
 * Template part — Footer principal.
 *
 * Refonte 2026-05 (v3) :
 *  - Plus de bloc Newsletter (NL accessible via le module home, /newsletter/, side menu).
 *  - Logo dual-mode dans le brand block (mêmes SVG que le header).
 *  - 4 colonnes : Découvrir / Éditions Thermostat 6 / Aide & contact / Apps et réseaux.
 *  - La col 4 « Apps et réseaux » regroupe theme toggle + app badges + social icons.
 *  - Bloc bas : copyright (gauche) + légaux (droite), séparateur top discret.
 *  - Année du copyright dynamique.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$current_year = gmdate( 'Y' );

/**
 * Rendu fallback pour wp_nav_menu : liste hardcodée si la location n'est pas
 * affectée à un menu dans WP Admin > Apparence > Menus.
 *
 * @param array<int, array{label:string, url:string, external?:bool}> $items
 * @return void
 */
$render_fallback_nav = function ( array $items ) {
	echo '<ul class="site-footer__nav">';
	foreach ( $items as $item ) {
		$is_external = ! empty( $item['external'] );
		$rel         = $is_external ? ' rel="noopener me"' : '';
		$target      = $is_external ? ' target="_blank"' : '';
		// Mesure Umami : le repli hardcodé porte les mêmes attributs que les
		// items du menu administrable (posés par _180c_umami_nav_menu_attrs()).
		$umami = function_exists( '_180c_umami_is_subscribe_url' ) && _180c_umami_is_subscribe_url( $item['url'] )
			? _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'footer' ) )
			: '';
		printf(
			'<li><a href="%1$s"%2$s%3$s%5$s>%4$s</a></li>',
			esc_url( $item['url'] ),
			$rel, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$target, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $item['label'] ),
			$umami // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs déjà échappés par _180c_umami_attrs().
		);
	}
	echo '</ul>';
};

/**
 * Affiche un menu WordPress, ou la liste fallback hardcodée si la location
 * n'est pas affectée à un menu.
 *
 * @param string                                                  $location  Theme location.
 * @param array<int, array{label:string, url:string, external?:bool}> $fallback Items de secours.
 * @return void
 */
$render_footer_nav = function ( $location, array $fallback ) use ( $render_fallback_nav ) {
	if ( has_nav_menu( $location ) ) {
		wp_nav_menu(
			array(
				'theme_location' => $location,
				'menu_class'     => 'site-footer__nav',
				'container'      => false,
				'fallback_cb'    => false,
				'depth'          => 1,
			)
		);
		return;
	}
	$render_fallback_nav( $fallback );
};
?>

<div class="site-footer__brand-row">

	<div class="site-footer__brand">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-footer__logo" rel="home" aria-label="<?php esc_attr_e( '180°C — Accueil', '180c' ); ?>">
			<?php echo _180c_render_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="screen-reader-text"><?php esc_html_e( '180°C — La revue culture food', '180c' ); ?></span>
		</a>
	</div><!-- .site-footer__brand -->

	<div class="site-footer__theme-toggle-wrap">
		<h3 class="site-footer__sub-title"><?php esc_html_e( 'Régler l’affichage', '180c' ); ?></h3>
		<fieldset class="theme-toggle" data-theme-toggle>
			<legend class="screen-reader-text"><?php esc_html_e( 'Thème d’affichage', '180c' ); ?></legend>
			<label class="theme-toggle__option">
				<input type="radio" name="theme-mode" value="auto" class="theme-toggle__input">
				<span class="theme-toggle__label"><?php esc_html_e( 'Auto', '180c' ); ?></span>
			</label>
			<label class="theme-toggle__option">
				<input type="radio" name="theme-mode" value="light" class="theme-toggle__input">
				<span class="theme-toggle__label"><?php esc_html_e( 'Clair', '180c' ); ?></span>
			</label>
			<label class="theme-toggle__option">
				<input type="radio" name="theme-mode" value="dark" class="theme-toggle__input">
				<span class="theme-toggle__label"><?php esc_html_e( 'Sombre', '180c' ); ?></span>
			</label>
		</fieldset>
	</div><!-- .site-footer__theme-toggle-wrap -->

</div><!-- .site-footer__brand-row -->

<div class="site-footer__columns">

	<nav class="site-footer__col" aria-labelledby="footer-col-1">
		<h2 id="footer-col-1" class="site-footer__col-title"><?php esc_html_e( 'Découvrir 180°C', '180c' ); ?></h2>
		<?php
		$render_footer_nav(
			'footer-discover',
			array(
				array(
					'label' => __( 'La Gazette', '180c' ),
					'url'   => home_url( '/la-gazette/' ),
				),
				array(
					'label' => __( 'Newsletter', '180c' ),
					'url'   => home_url( '/newsletter/' ),
				),
				array(
					'label' => __( 'Recettes en ligne', '180c' ),
					'url'   => home_url( '/recettes/' ),
				),
				array(
					'label' => __( 'Boutique en ligne', '180c' ),
					'url'   => home_url( '/boutique/' ),
				),
				array(
					'label' => __( 'S’abonner aux recettes', '180c' ),
					'url'   => home_url( '/abonnement/' ),
				),
			)
		);
		?>
	</nav>

	<nav class="site-footer__col" aria-labelledby="footer-col-2">
		<h2 id="footer-col-2" class="site-footer__col-title"><?php esc_html_e( 'Éditions Thermostat 6', '180c' ); ?></h2>
		<?php
		$render_footer_nav(
			'footer-editions',
			array(
				array(
					'label' => __( '180°C — La revue', '180c' ),
					'url'   => home_url( '/categorie-produit/revues-180c/' ),
				),
				array(
					'label' => __( '12°5 — La revue', '180c' ),
					'url'   => home_url( '/categorie-produit/revue-12-5/' ),
				),
				array(
					'label' => __( 'Les Petits Cahiers', '180c' ),
					'url'   => home_url( '/categorie-produit/les-petits-cahiers/' ),
				),
				array(
					'label' => __( 'Les Grands Cahiers', '180c' ),
					'url'   => home_url( '/categorie-produit/grands-cahiers/' ),
				),
				array(
					'label' => __( 'Les livres 180°C', '180c' ),
					'url'   => home_url( '/categorie-produit/publication/' ),
				),
				array(
					'label' => __( 'Livres numériques (ePub)', '180c' ),
					'url'   => home_url( '/categorie-produit/epub/' ),
				),
			)
		);
		?>
	</nav>

	<nav class="site-footer__col" aria-labelledby="footer-col-3">
		<h2 id="footer-col-3" class="site-footer__col-title"><?php esc_html_e( 'Aide et contact', '180c' ); ?></h2>
		<?php
		$render_footer_nav(
			'footer-help',
			array(
				array(
					'label' => __( 'Centre d’aide', '180c' ),
					'url'   => home_url( '/centre-daide/' ),
				),
				array(
					'label' => __( 'Contacter la rédaction', '180c' ),
					'url'   => home_url( '/contactez-la-redaction/' ),
				),
				array(
					'label' => __( 'Contact presse', '180c' ),
					'url'   => home_url( '/contact-presse/' ),
				),
				array(
					'label' => __( 'Espace pro', '180c' ),
					'url'   => home_url( '/contact-pro/' ),
				),
				array(
					'label' => __( 'Partenariats et annonceurs', '180c' ),
					'url'   => home_url( '/partenariats-et-annonceurs/' ),
				),
				array(
					'label' => __( 'Qui sommes-nous ?', '180c' ),
					'url'   => home_url( '/qui-sommes-nous/' ),
				),
			)
		);
		?>
	</nav>

	<div class="site-footer__col site-footer__col--tools" aria-labelledby="footer-col-4">
		<h2 id="footer-col-4" class="site-footer__col-title"><?php esc_html_e( 'Apps et réseaux', '180c' ); ?></h2>

		<?php
		// Liens stores : source unique (constantes wp-config via helpers). Badge masqué si URL vide.
		$_180c_app_store   = _180c_app_store_url();
		$_180c_google_play = _180c_google_play_url();
		// APP-RELEASE : bloc « Télécharger l'app » masqué tant que les apps mobiles
		// ne sont pas publiées. Retirer « false && » ci-dessous pour le réactiver.
		if ( false && ( '' !== $_180c_app_store || '' !== $_180c_google_play ) ) :
			?>
			<div class="site-footer__app">
				<h3 class="site-footer__sub-title"><?php esc_html_e( 'Télécharger l’app', '180c' ); ?></h3>
				<ul class="site-footer__app-badges">
					<?php if ( '' !== $_180c_app_store ) : ?>
						<li>
							<a href="<?php echo esc_url( $_180c_app_store ); ?>" class="site-footer__app-badge" data-store="ios" aria-label="<?php esc_attr_e( 'Télécharger sur l’App Store', '180c' ); ?>" target="_blank" rel="noopener">
								<img
									src="<?php echo esc_url( _180C_THEME_URI . '/src/images/badges/app-store-fr-black.svg' ); ?>"
									alt="<?php esc_attr_e( 'Télécharger dans l’App Store', '180c' ); ?>"
									width="135"
									height="40"
									loading="lazy"
									decoding="async"
								>
							</a>
						</li>
					<?php endif; ?>
					<?php if ( '' !== $_180c_google_play ) : ?>
						<li>
							<a href="<?php echo esc_url( $_180c_google_play ); ?>" class="site-footer__app-badge" data-store="android" aria-label="<?php esc_attr_e( 'Disponible sur Google Play', '180c' ); ?>" target="_blank" rel="noopener">
								<img
									src="<?php echo esc_url( _180C_THEME_URI . '/src/images/badges/google-play-fr-black.svg' ); ?>"
									alt="<?php esc_attr_e( 'Disponible sur Google Play', '180c' ); ?>"
									width="135"
									height="40"
									loading="lazy"
									decoding="async"
								>
							</a>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="site-footer__social-wrap">
			<h3 class="site-footer__sub-title"><?php esc_html_e( 'Réseaux sociaux', '180c' ); ?></h3>
			<ul class="site-footer__social" aria-label="<?php esc_attr_e( 'Réseaux sociaux', '180c' ); ?>">
				<li>
					<a href="https://www.facebook.com/180C.LaRevue" aria-label="<?php esc_attr_e( 'Suivre 180°C sur Facebook', '180c' ); ?>" target="_blank" rel="noopener me">
						<?php echo _180c_render_svg_icon( 'facebook' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</li>
				<li>
					<a href="https://www.instagram.com/180c_larevue/" aria-label="<?php esc_attr_e( 'Suivre 180°C sur Instagram', '180c' ); ?>" target="_blank" rel="noopener me">
						<?php echo _180c_render_svg_icon( 'instagram' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</li>
				<?php // Icône X (Twitter) masquée sur demande. Retirer le « if ( false ) » / « endif » ci-dessous pour la réafficher. ?>
				<?php if ( false ) : // phpcs:ignore Generic.CodeAnalysis.UnconditionalIfStatement.Found ?>
					<li>
						<a href="https://x.com/180C_LaRevue" aria-label="<?php esc_attr_e( 'Suivre 180°C sur X', '180c' ); ?>" target="_blank" rel="noopener me">
							<?php echo _180c_render_svg_icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					</li>
				<?php endif; ?>
				<li>
					<a href="https://www.youtube.com/@180clarevueculturefood8" aria-label="<?php esc_attr_e( 'Suivre 180°C sur YouTube', '180c' ); ?>" target="_blank" rel="noopener me">
						<?php echo _180c_render_svg_icon( 'youtube' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( home_url( '/feed/' ) ); ?>" aria-label="<?php esc_attr_e( 'S’abonner au flux RSS', '180c' ); ?>" type="application/rss+xml">
						<?php echo _180c_render_svg_icon( 'rss' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</li>
			</ul>
		</div>
	</div><!-- .site-footer__col--tools -->

</div><!-- .site-footer__columns -->

<div class="site-footer__bottom">
	<p class="site-footer__copyright">
		<?php
		printf(
			/* translators: %s: current year (wrapped in <time>). */
			esc_html__( 'Tous droits réservés © 180°C, %s', '180c' ),
			sprintf( '<time datetime="%1$s">%1$s</time>', esc_html( $current_year ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		?>
	</p>

	<nav class="site-footer__legal" aria-label="<?php esc_attr_e( 'Informations légales', '180c' ); ?>">
		<ul class="site-footer__legal-nav">
			<li><a href="<?php echo esc_url( home_url( '/plan-du-site/' ) ); ?>"><?php esc_html_e( 'Plan du site', '180c' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/mentions-legales/' ) ); ?>"><?php esc_html_e( 'Mentions légales', '180c' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/politique-confidentialite/' ) ); ?>"><?php esc_html_e( 'Politique de confidentialité', '180c' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/cgv/' ) ); ?>"><?php esc_html_e( 'Conditions générales de vente', '180c' ); ?></a></li>
			<?php // Lien ordinaire : plus aucun clic intercepté par le JS. La gestion du consentement se fait sur la page, via le shortcode [180c_consent_toggle]. ?>
			<li><a href="<?php echo esc_url( _180c_consent_policy_url() ); ?>"><?php esc_html_e( 'Gestion des cookies', '180c' ); ?></a></li>
		</ul>
	</nav>
</div><!-- .site-footer__bottom -->
