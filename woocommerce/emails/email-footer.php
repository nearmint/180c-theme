<?php
/**
 * Email Footer — surcharge 180°C (châssis brandé, phase 2B).
 *
 * Ferme la carte de corps ouverte par email-header.php, puis rend la carte footer
 * reprise du template de campagnes : logo, badges apps (liens via constantes
 * wp-config), réseaux, mention légale (éditeur Thermostat 6), liens utiles —
 * SANS lien de désabonnement (transactionnel). Footer 100 % templé : le filtre
 * `woocommerce_email_footer_text` n'est plus utilisé (cf. inc/woo/emails.php).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

defined( 'ABSPATH' ) || exit;

// Liens stores : source unique (constantes wp-config via helpers). Vide => badge masqué.
$app_store   = function_exists( '_180c_app_store_url' ) ? _180c_app_store_url() : '';
$google_play = function_exists( '_180c_google_play_url' ) ? _180c_google_play_url() : '';

$icon_fb = get_theme_file_uri( 'src/images/email/facebook.svg' );
$icon_ig = get_theme_file_uri( 'src/images/email/instagram.svg' );
$icon_yt = get_theme_file_uri( 'src/images/email/youtube.svg' );

$badge_ios     = get_theme_file_uri( 'src/images/badges/app-store-fr-black.svg' );
$badge_android = get_theme_file_uri( 'src/images/badges/google-play-fr-black.svg' );
?>
				</div>
			</td></tr>
		</table>
	</td></tr>

	<!-- Carte 3 — footer (repris campagne ; sans désabonnement) -->
	<tr><td>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="card" bgcolor="#FFFFFF" style="background-color:#FFFFFF; border-radius:8px;">
			<tr><td class="px" style="padding:34px 40px 32px 40px;">

				<?php // Logo retiré du pied : il faisait doublon avec le logo d'en-tête (email-header.php, carte 1). ?>
				<?php // APP-RELEASE : bloc badges app masqué tant que les apps ne sont pas publiées. Retirer « false && » pour réactiver. ?>
				<?php if ( false && ( '' !== $app_store || '' !== $google_play ) ) : ?>
					<p class="muted" style="font-family:'Oswald','Arial Narrow',Arial,Helvetica,sans-serif; font-size:12px; font-weight:500; letter-spacing:0.1em; text-transform:uppercase; color:#6E6E6E; text-align:center; margin:0;"><?php esc_html_e( 'Toutes nos recettes dans votre poche avec l’app', '180c' ); ?></p>

					<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:14px auto 0 auto;"><tr>
						<?php if ( '' !== $app_store ) : ?>
							<td style="padding:0 6px;"><a href="<?php echo esc_url( $app_store ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr__( 'Télécharger dans l’App Store', '180c' ); ?>" style="line-height:0; display:inline-block;"><img src="<?php echo esc_url( $badge_ios ); ?>" alt="<?php echo esc_attr__( 'App Store', '180c' ); ?>" height="40" style="display:block; height:40px; width:auto;"></a></td>
						<?php endif; ?>
						<?php if ( '' !== $google_play ) : ?>
							<td style="padding:0 6px;"><a href="<?php echo esc_url( $google_play ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr__( 'Disponible sur Google Play', '180c' ); ?>" style="line-height:0; display:inline-block;"><img src="<?php echo esc_url( $badge_android ); ?>" alt="<?php echo esc_attr__( 'Google Play', '180c' ); ?>" height="40" style="display:block; height:40px; width:auto;"></a></td>
						<?php endif; ?>
					</tr></table>
				<?php endif; ?>

				<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:24px auto 0 auto;"><tr>
					<td style="padding:0 9px;"><a href="https://www.facebook.com/180C.LaRevue" target="_blank" rel="noopener me" aria-label="Facebook" style="line-height:0;"><img src="<?php echo esc_url( $icon_fb ); ?>" alt="Facebook" width="22" height="22" style="display:block; width:22px; height:22px;"></a></td>
					<td style="padding:0 9px;"><a href="https://www.instagram.com/180c_larevue/" target="_blank" rel="noopener me" aria-label="Instagram" style="line-height:0;"><img src="<?php echo esc_url( $icon_ig ); ?>" alt="Instagram" width="22" height="22" style="display:block; width:22px; height:22px;"></a></td>
					<td style="padding:0 9px;"><a href="https://www.youtube.com/@180clarevueculturefood8" target="_blank" rel="noopener me" aria-label="YouTube" style="line-height:0;"><img src="<?php echo esc_url( $icon_yt ); ?>" alt="YouTube" width="22" height="22" style="display:block; width:22px; height:22px;"></a></td>
				</tr></table>

				<p class="legal" style="font-family:'Playfair Display',Georgia,'Times New Roman',serif; font-size:13px; line-height:1.6; color:#6E6E6E; text-align:center; margin:20px 0 0 0;"><?php esc_html_e( 'Vous recevez cet e-mail car il concerne votre compte ou votre commande sur 180c.fr.', '180c' ); ?><br><?php esc_html_e( '180°C est édité par Thermostat 6 (SAS), 8 rue des Goncourt, 75011 Paris.', '180c' ); ?></p>

				<p class="muted" style="font-family:'Playfair Display',Georgia,'Times New Roman',serif; font-size:13px; line-height:1.8; text-align:center; color:#6E6E6E; margin:22px 0 0 0;">
					<a class="footer-link" href="<?php echo esc_url( home_url( '/mon-compte/' ) ); ?>" style="color:#6E6E6E; text-decoration:none;"><?php esc_html_e( 'Mon compte', '180c' ); ?></a> ·
					<a class="footer-link" href="<?php echo esc_url( home_url( '/politique-confidentialite/' ) ); ?>" style="color:#6E6E6E; text-decoration:none;"><?php esc_html_e( 'Politique de confidentialité', '180c' ); ?></a> ·
					<a class="footer-link" href="<?php echo esc_url( home_url( '/centre-daide/' ) ); ?>" style="color:#6E6E6E; text-decoration:none;"><?php esc_html_e( 'Centre d’aide', '180c' ); ?></a> ·
					<a class="footer-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" style="color:#6E6E6E; text-decoration:none;"><?php esc_html_e( 'Contact', '180c' ); ?></a>
				</p>

			</td></tr>
		</table>
	</td></tr>

</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
