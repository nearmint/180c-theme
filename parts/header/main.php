<?php
/**
 * Template part — Header principal 180°C.
 *
 * Structure (desktop ≥1024px) :
 *   .site-header__inner
 *     .site-header__left
 *       button.site-header__menu-trigger  (toujours visible)
 *       a.site-header__logo               (toujours visible)
 *       nav.site-header__nav-primary      (visible ≥1024px)
 *     .site-header__right
 *       si logué   : .site-header__account-wrap
 *                      button.site-header__account[aria-haspopup=menu]
 *                      div.site-header__account-dropdown[role=menu]
 *       si anonyme : a.site-header__login (≥1024px) + a.site-header__signup.btn.btn--primary
 *                    + a.site-header__login-icon (icône compte, <1024px)
 *
 * Le bouton menu-trigger ouvre le side-menu push (cf parts/site-side-menu.php +
 * src/js/modules/side-menu.js).
 * Le bouton account ouvre le dropdown (cf src/js/modules/account-dropdown.js).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$home_url  = esc_url( home_url( '/' ) );
$is_logged = is_user_logged_in();

if ( $is_logged ) {
	$current_user  = wp_get_current_user();
	$user_email    = $current_user ? $current_user->user_email : '';
	$is_subscriber = function_exists( '_180c_is_recipe_subscriber' ) && _180c_is_recipe_subscriber();
	// État d'abonnement pour adapter le CTA du menu compte (ex. abonnement en
	// pause → « Gérer mon abonnement » plutôt que « S'abonner »).
	$account_sub_state = function_exists( '_180c_get_subscription_display_state' )
		? _180c_get_subscription_display_state()['state']
		: '';
	$account_url   = function_exists( 'wc_get_page_permalink' )
		? esc_url( wc_get_page_permalink( 'myaccount' ) )
		: esc_url( home_url( '/mon-compte/' ) );
	$logout_url    = esc_url( wp_logout_url( home_url() ) );
}

// -----------------------------------------------------------------------
// Icônes SVG inline (Feather-style, 24px, currentColor).
// -----------------------------------------------------------------------

// Hamburger + loupe combinés : 3 lignes (gauche) + cercle/poignée loupe (droite).
$icon_menu_search = '<svg class="site-header__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
	. '<line x1="3" y1="6" x2="13" y2="6"/>'
	. '<line x1="3" y1="12" x2="13" y2="12"/>'
	. '<line x1="3" y1="18" x2="13" y2="18"/>'
	. '<circle cx="18" cy="17" r="3"/>'
	. '<line x1="20.5" y1="19.5" x2="22" y2="21"/>'
	. '</svg>';

$icon_user = '<svg class="site-header__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
	. '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>'
	. '<circle cx="12" cy="7" r="4"/>'
	. '</svg>';

// Chevron bas (16x16, stroke fin).
$icon_chevron_down = '<svg class="site-header__chevron-down" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
	. '<polyline points="6 9 12 15 18 9"/>'
	. '</svg>';

?>

<div class="site-header__inner">

	<div class="site-header__left">

		<button
			type="button"
			class="site-header__menu-trigger js-side-menu-toggle"
			aria-label="<?php esc_attr_e( 'Menu et recherche', '180c' ); ?>"
			aria-expanded="false"
			aria-controls="site-side-menu"
		>
			<?php echo $icon_menu_search; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>

		<a href="<?php echo $home_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="site-header__logo" rel="home" aria-label="<?php esc_attr_e( '180°C — Accueil', '180c' ); ?>">
			<?php echo _180c_render_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="screen-reader-text"><?php esc_html_e( '180°C', '180c' ); ?></span>
		</a>

		<nav class="site-header__nav-primary" aria-label="<?php esc_attr_e( 'Navigation principale', '180c' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'site-header__nav-list',
					'depth'          => 1,
					'fallback_cb'    => '_180c_header_primary_fallback',
				)
			);
			?>
		</nav>

	</div><!-- .site-header__left -->

	<div class="site-header__right">

		<?php
		// Icône panier — à gauche du bouton compte, même gabarit. Rendue
		// uniquement si le panier contient au moins un article (invités inclus).
		// Source unique (helper) partagée avec le fragment WC, qui fait
		// apparaître / disparaître le bouton après chaque mutation AJAX.
		if ( function_exists( '_180c_cart_header_button_html' ) ) {
			echo _180c_cart_header_button_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>

		<?php if ( $is_logged ) : ?>

			<div class="site-header__account-wrap js-account-dropdown">

				<button
					type="button"
					class="site-header__account js-account-toggle"
					aria-haspopup="menu"
					aria-expanded="false"
					aria-controls="account-dropdown"
				>
					<?php echo $icon_user; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="site-header__account-label"><?php esc_html_e( 'Mon compte', '180c' ); ?></span>
					<?php echo $icon_chevron_down; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>

				<div
					class="site-header__account-dropdown"
					id="account-dropdown"
					role="menu"
					aria-label="<?php esc_attr_e( 'Menu compte', '180c' ); ?>"
				>
					<a
						href="<?php echo $account_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
						class="site-header__account-dropdown-header"
						role="menuitem"
					>
						<span class="site-header__account-dropdown-header-row">
							<span class="site-header__account-name"><?php esc_html_e( 'Mon compte', '180c' ); ?></span>
							<?php if ( $is_subscriber ) : ?>
								<span class="site-header__account-pill"><?php esc_html_e( 'Abonné', '180c' ); ?></span>
							<?php endif; ?>
						</span>
						<?php if ( $user_email ) : ?>
							<span class="site-header__account-email"><?php echo esc_html( $user_email ); ?></span>
						<?php endif; ?>
					</a>

					<hr class="site-header__account-dropdown-sep" aria-hidden="true">

					<?php if ( $is_subscriber ) : ?>
						<a href="<?php echo esc_url( home_url( '/mon-compte/#abonnement' ) ); ?>" class="site-header__account-dropdown-item" role="menuitem">
							<?php esc_html_e( 'Mon abonnement', '180c' ); ?>
						</a>
					<?php elseif ( 'on_hold' === $account_sub_state ) : ?>
						<?php /* Abonnement en pause → inviter à le gérer (réactivation) plutôt qu'à se réabonner. */ ?>
						<a href="<?php echo esc_url( home_url( '/mon-compte/' ) ); ?>" class="site-header__account-dropdown-item site-header__account-dropdown-item--cta" role="menuitem">
							<?php esc_html_e( 'Gérer mon abonnement', '180c' ); ?>
						</a>
					<?php else : ?>
						<a href="<?php echo esc_url( home_url( '/abonnement/' ) ); ?>" class="site-header__account-dropdown-item site-header__account-dropdown-item--cta" role="menuitem">
							<?php esc_html_e( "S'abonner", '180c' ); ?>
						</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( home_url( '/mon-compte/#factures' ) ); ?>" class="site-header__account-dropdown-item" role="menuitem">
						<?php esc_html_e( 'Mes commandes', '180c' ); ?>
					</a>
					<?php if ( $is_subscriber ) : ?>
						<a href="<?php echo esc_url( home_url( '/mon-carnet/' ) ); ?>" class="site-header__account-dropdown-item" role="menuitem">
							<?php esc_html_e( 'Mon carnet de recettes', '180c' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( ! $is_subscriber ) : ?>
						<a href="<?php echo esc_url( home_url( '/newsletter/' ) ); ?>" class="site-header__account-dropdown-item" role="menuitem">
							<?php esc_html_e( 'Newsletter', '180c' ); ?>
						</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( home_url( '/centre-daide/' ) ); ?>" class="site-header__account-dropdown-item" role="menuitem">
						<?php esc_html_e( "Centre d'aide", '180c' ); ?>
					</a>

					<hr class="site-header__account-dropdown-sep" aria-hidden="true">

					<a href="<?php echo $logout_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="site-header__account-dropdown-item" role="menuitem"
						data-confirm
						data-confirm-title="<?php esc_attr_e( 'Se déconnecter ?', '180c' ); ?>"
						data-confirm-message="<?php esc_attr_e( 'Vous allez être déconnecté de votre compte.', '180c' ); ?>"
						data-confirm-confirm-label="<?php esc_attr_e( 'Se déconnecter', '180c' ); ?>">
						<?php esc_html_e( 'Se déconnecter', '180c' ); ?>
					</a>
				</div><!-- .site-header__account-dropdown -->

			</div><!-- .site-header__account-wrap -->

		<?php else : ?>

			<a
				href="<?php echo esc_url( home_url( '/connexion/' ) ); ?>"
				class="site-header__login"
			>
				<?php esc_html_e( 'Se connecter', '180c' ); ?>
			</a>

			<a
				href="<?php echo esc_url( home_url( '/abonnement/' ) ); ?>"
				class="site-header__signup btn btn--primary"
				<?php echo _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'header' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			>
				<?php esc_html_e( "S'abonner", '180c' ); ?>
			</a>

			<?php /* Mobile <1024px : le lien texte « Se connecter » est masqué, on
			expose l'accès au login via une icône compte à droite du CTA d'abonnement. */ ?>
			<a
				href="<?php echo esc_url( home_url( '/connexion/' ) ); ?>"
				class="site-header__login-icon"
				aria-label="<?php esc_attr_e( 'Se connecter', '180c' ); ?>"
			>
				<?php echo $icon_user; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>

		<?php endif; ?>

	</div><!-- .site-header__right -->

</div><!-- .site-header__inner -->
