<?php
/**
 * Page Abonnement — bandeau de réassurance « moyens de paiement ».
 *
 * Contenu entièrement en dur (aucun champ ACF) : un libellé « Paiement
 * sécurisé » précédé d'un cadenas, puis les moyens de paiement acceptés.
 *
 * Les logos de marque sont des SVG locaux (assets/icons/payment/) inlinés afin
 * d'être neutralisés en gris monochrome adaptatif (fill: currentColor piloté
 * par le CSS, couleur de texte secondaire qui bascule clair/sombre). Chaque
 * SVG porte son propre <title> (nom accessible). Le cadenas reste un SVG
 * générique inline.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="subscribe-payment">
	<p class="subscribe-payment__secure">
		<svg class="subscribe-payment__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
			<rect x="4" y="10" width="16" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
			<path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
		</svg>
		<span><?php esc_html_e( 'Paiement sécurisé', '180c' ); ?></span>
	</p>

	<ul class="subscribe-payment__methods" aria-label="<?php esc_attr_e( 'Moyens de paiement acceptés', '180c' ); ?>">
		<?php
		$_180c_pay_methods = array( 'visa', 'mastercard', 'apple-pay', 'google-pay', 'paypal' );
		$_180c_pay_dir     = get_theme_file_path( 'assets/icons/payment/' );
		foreach ( $_180c_pay_methods as $_180c_pay_slug ) :
			$_180c_pay_file = $_180c_pay_dir . $_180c_pay_slug . '.svg';
			if ( ! is_readable( $_180c_pay_file ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Asset SVG local du thème.
			$_180c_pay_svg = file_get_contents( $_180c_pay_file );
			if ( false === $_180c_pay_svg ) {
				continue;
			}
			?>
			<li class="subscribe-payment__method">
				<span class="subscribe-payment__logo">
					<?php echo $_180c_pay_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG de marque local et de confiance (assets/icons/payment/), porte son propre <title>. ?>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
