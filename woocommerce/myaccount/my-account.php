<?php
/**
 * My Account — surcharge 180°C (shell mutualisé).
 *
 * Enveloppe TOUS les endpoints Mon compte dans le shell `.my-account`
 * (sidebar mutualisée à gauche + colonne contenu à droite). La nav WC native
 * est retirée en amont (inc/woo/account-menu.php) ; on rend notre propre
 * sidebar via parts/account/sidebar.php. Le contenu de chaque endpoint est
 * émis par `woocommerce_account_content` :
 *   - dashboard        → parts/account/dashboard.php (sections, sans wrapper) ;
 *   - edit-account / payment-methods / view-order → overrides DS dédiés.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package 180c
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * My Account navigation (retirée par la refonte single-page, conservée pour
 * la compatibilité plugins).
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_account_navigation' );
?>

<div class="my-account">

	<?php get_template_part( 'parts/account/sidebar' ); ?>

	<div class="my-account__content woocommerce-MyAccount-content">
		<?php
		/**
		 * My Account content.
		 *
		 * @since 2.6.0
		 */
		do_action( 'woocommerce_account_content' );
		?>
	</div>

</div>
