<?php
/**
 * Template part — Side menu push 180°C.
 *
 * Panneau latéral gauche en effet push : ouvre via .js-side-menu-toggle
 * (dans le header) et translate #site-shell vers la droite (cf. main.css).
 *
 * Structure :
 *   aside#site-side-menu.site-side-menu[aria-hidden]
 *     .site-side-menu__inner
 *       button.site-side-menu__close
 *       form.site-side-menu__search
 *       nav.site-side-menu__nav
 *         ul.site-side-menu__list > li.site-side-menu__item
 *           (lien simple)         a.site-side-menu__link
 *           (expand)              button.site-side-menu__expand + ul.site-side-menu__sub
 *       .site-side-menu__cta      (anonymes seulement)
 *       .site-side-menu__bottom   (wrapper hors-gap pour resserrer le divider)
 *         hr.site-side-menu__divider
 *         ul.site-side-menu__social
 *
 * La liste principale est rendue par _180c_side_menu_render() :
 *  - si la location 'side' a un menu admin assigné, on utilise ses items
 *    (label, url) + classe CSS `expand-{taxonomy}` pour déclencher un expand
 *    alimenté par get_terms() sur la taxonomy ciblée ;
 *  - sinon, fallback hardcodé via _180c_side_menu_fallback().
 *
 * Le JS (src/js/modules/side-menu.js) :
 *  - toggle body.has-side-menu-open au click sur trigger / close / escape
 *  - bloque le scroll, piège le focus, ferme au click hors du shell
 *  - toggle hidden sur les .site-side-menu__sub via aria-expanded
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$is_logged = is_user_logged_in();

// Utilisateur connecté SANS abonnement : CTA d'abonnement direct (ajout au panier
// de l'abonnement mensuel). Vide sinon (anonyme = CTA /abonnement/ ci-dessous ;
// abonné / état non-actif = pas de CTA d'ajout panier, géré ailleurs).
$side_subscribe_cart_url = '';
if ( $is_logged
	&& function_exists( '_180c_get_subscription_display_state' )
	&& function_exists( '_180c_subscribe_monthly_cart_url' )
) {
	$side_sub_state = _180c_get_subscription_display_state();
	if ( '' === $side_sub_state['state'] ) {
		$side_subscribe_cart_url = _180c_subscribe_monthly_cart_url();
	}
}

// -----------------------------------------------------------------------
// Icônes SVG inline.
// -----------------------------------------------------------------------

$icon_close = '<svg class="site-side-menu__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

$icon_search = '<svg class="site-side-menu__icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';

$icon_fb = '<svg class="site-side-menu__social-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.13 8.44 9.88v-6.99H7.9v-2.89h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.89h-2.34V22c4.78-.75 8.43-4.88 8.43-9.94Z"/></svg>';

$icon_instagram = '<svg class="site-side-menu__social-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>';

$icon_youtube = '<svg class="site-side-menu__social-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2C0 8.1 0 12 0 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1c.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.6 15.6V8.4l6.2 3.6-6.2 3.6z"/></svg>';

$icon_rss = '<svg class="site-side-menu__social-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>';
?>

<aside id="site-side-menu" class="site-side-menu" aria-hidden="true" aria-label="<?php esc_attr_e( 'Menu principal', '180c' ); ?>">
	<div class="site-side-menu__inner">

		<button
			type="button"
			class="site-side-menu__close js-side-menu-close"
			aria-label="<?php esc_attr_e( 'Fermer le menu', '180c' ); ?>"
		>
			<?php echo $icon_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>

		<form class="site-side-menu__search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<label for="side-menu-search" class="screen-reader-text"><?php esc_html_e( 'Rechercher', '180c' ); ?></label>
			<input
				id="side-menu-search"
				class="site-side-menu__search-input"
				type="search"
				name="s"
				placeholder="<?php esc_attr_e( 'Rechercher…', '180c' ); ?>"
				autocomplete="off"
			>
			<button
				type="submit"
				class="site-side-menu__search-submit"
				aria-label="<?php esc_attr_e( 'Lancer la recherche', '180c' ); ?>"
			>
				<?php echo $icon_search; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		</form>

		<nav class="site-side-menu__nav" aria-label="<?php esc_attr_e( 'Navigation secondaire', '180c' ); ?>">
			<?php _180c_side_menu_render(); ?>
		</nav>

		<?php if ( ! $is_logged ) : ?>
			<div class="site-side-menu__cta">
				<a href="<?php echo esc_url( home_url( '/abonnement/' ) ); ?>" class="btn btn--primary"
					<?php echo _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'side_menu' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php esc_html_e( "S'abonner", '180c' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/connexion/' ) ); ?>" class="site-side-menu__login">
					<?php esc_html_e( 'Se connecter', '180c' ); ?>
				</a>
			</div>
		<?php elseif ( '' !== $side_subscribe_cart_url ) : ?>
			<?php /* Connecté sans abonnement : abonnement en un clic (ajout au panier). */ ?>
			<div class="site-side-menu__cta">
				<a href="<?php echo esc_url( $side_subscribe_cart_url ); ?>" class="btn btn--primary"
					<?php echo _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'side_menu' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php esc_html_e( "S'abonner", '180c' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<div class="site-side-menu__bottom">
			<hr class="site-side-menu__divider" aria-hidden="true">

			<ul class="site-side-menu__social" aria-label="<?php esc_attr_e( 'Réseaux sociaux', '180c' ); ?>">
				<li>
					<a href="https://www.facebook.com/180C.LaRevue" aria-label="<?php esc_attr_e( 'Suivre 180°C sur Facebook', '180c' ); ?>" target="_blank" rel="noopener me">
						<?php echo $icon_fb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</li>
				<li>
					<a href="https://www.instagram.com/180c_larevue/" aria-label="<?php esc_attr_e( 'Suivre 180°C sur Instagram', '180c' ); ?>" target="_blank" rel="noopener me">
						<?php echo $icon_instagram; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</li>
				<li>
					<a href="https://www.youtube.com/@180clarevueculturefood8" aria-label="<?php esc_attr_e( 'Suivre 180°C sur YouTube', '180c' ); ?>" target="_blank" rel="noopener me">
						<?php echo $icon_youtube; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( get_bloginfo( 'rss2_url' ) ); ?>" aria-label="<?php esc_attr_e( 'S’abonner au flux RSS', '180c' ); ?>" type="application/rss+xml">
						<?php echo $icon_rss; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</li>
			</ul>
		</div><!-- .site-side-menu__bottom -->

	</div><!-- .site-side-menu__inner -->
</aside>
