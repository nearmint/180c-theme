<?php
/**
 * Email Header — surcharge 180°C (châssis brandé, phase 2B).
 *
 * Ouvre le document e-mail : <head> (polices, color-scheme), page claire, carte
 * logo 180°C cliquable (swap clair/sombre, sans tagline) puis bande accent
 * #FFAE3A portant $email_heading (Playfair). Ouvre la carte de corps.
 * Fermé par email-footer.php (paire indissociable).
 *
 * Compat : tables role=presentation, 600px, CSS inline, VML/MSO, dark @media
 * (via email-styles.php + classes), aucune dépendance webfont.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.7.0
 */

defined( 'ABSPATH' ) || exit;

$store_name = get_bloginfo( 'name', 'display' );

/** Filtre WC : URL cible du logo d'en-tête. */
$header_image_url = apply_filters( 'woocommerce_email_header_image_url', home_url( '/' ) );

// Logos e-mail dédiés : variante foncée (#0E0E0E) sur fond clair, variante grise
// (#A0A0A0) sur fond sombre. TODO prod : fournir des PNG (Gmail/Outlook ne rendent
// pas le SVG → repli sur le alt « 180°C »).
$logo_light = get_theme_file_uri( 'src/images/email/logo-180c-dark.svg' );
$logo_dark  = get_theme_file_uri( 'src/images/email/logo-180c.svg' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title><?php echo esc_html( $store_name ); ?></title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
</head>
<body class="page-bg" bgcolor="#F5F5F5" style="margin:0; padding:0; background-color:#F5F5F5; color:#0E0E0E;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="page-bg" bgcolor="#F5F5F5" style="background-color:#F5F5F5;">
<tr><td align="center" style="padding:24px 12px;">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="container" style="width:600px; max-width:600px;">

	<!-- Carte 1 — logo 180°C cliquable (swap clair/sombre) -->
	<tr><td style="padding-bottom:24px;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="card" bgcolor="#FFFFFF" style="background-color:#FFFFFF; border-radius:8px;">
			<tr><td class="px" align="center" style="padding:30px 40px 28px 40px;">
				<a href="<?php echo esc_url( $header_image_url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $store_name ); ?>" style="display:inline-block; text-decoration:none; line-height:0;">
					<img src="<?php echo esc_url( $logo_light ); ?>" alt="<?php echo esc_attr( $store_name ); ?>" width="300" height="119" class="logo-light" style="display:block; width:300px; max-width:88%; height:auto; margin:0 auto;">
					<!--[if !mso]><!-->
					<span class="logo-dark"><img src="<?php echo esc_url( $logo_dark ); ?>" alt="<?php echo esc_attr( $store_name ); ?>" width="300" height="119" style="display:block; width:300px; max-width:88%; height:auto; margin:0 auto;"></span>
					<!--<![endif]-->
				</a>
			</td></tr>
		</table>
	</td></tr>

	<!-- Carte 2 — bande accent (titre) + corps -->
	<tr><td style="padding-bottom:24px;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="body-card" bgcolor="#FFFFFF" style="background-color:#FFFFFF; border-radius:8px;">
			<?php
			// Surtitre contextuel (Oswald, sur la bande accent) — mappé par id d'e-mail.
			// Couleur foncée atténuée (le « gris clair » échouerait le contraste sur #FFAE3A).
			$_180c_surtitres = array(
				'180c_gift_donor'                                  => __( 'Cadeau', '180c' ),
				'180c_gift_recipient'                              => __( 'Cadeau', '180c' ),
				'180c_subscription_cancelled'                      => __( 'Abonnement', '180c' ),
				'WC_Memberships_User_Membership_Ending_Soon_Email' => __( 'Abonnement', '180c' ),
				'WC_Memberships_User_Membership_Ended_Email'       => __( 'Abonnement', '180c' ),
				'customer_new_account'                             => __( 'Compte', '180c' ),
				'customer_reset_password'                          => __( 'Compte', '180c' ),
				'failed_renewal_authentication'                    => __( 'Paiement', '180c' ),
				'customer_payment_retry'                           => __( 'Paiement', '180c' ),
			);
			$_180c_email_id = ( isset( $email ) && is_object( $email ) && isset( $email->id ) ) ? (string) $email->id : '';
			$_180c_surtitre = isset( $_180c_surtitres[ $_180c_email_id ] ) ? $_180c_surtitres[ $_180c_email_id ] : '';
			if ( '' === $_180c_surtitre && '' !== $_180c_email_id ) {
				if ( false !== strpos( $_180c_email_id, 'subscription' ) || false !== strpos( $_180c_email_id, 'renewal' ) || false !== strpos( $_180c_email_id, 'notification' ) ) {
					$_180c_surtitre = __( 'Abonnement', '180c' );
				} elseif ( false !== strpos( $_180c_email_id, 'order' ) || false !== strpos( $_180c_email_id, 'invoice' ) ) {
					$_180c_surtitre = __( 'Commande', '180c' );
				}
			}
			?>
			<?php if ( ! empty( $email_heading ) ) : ?>
			<tr><td bgcolor="#FFAE3A" style="background-color:#FFAE3A; border-radius:8px 8px 0 0; padding:26px 32px;">
				<?php if ( '' !== $_180c_surtitre ) : ?>
				<p style="font-family:'Oswald','Arial Narrow',Arial,Helvetica,sans-serif; font-size:12px; font-weight:600; letter-spacing:0.10em; text-transform:uppercase; color:#0E0E0E; opacity:0.7; margin:0 0 6px 0; mso-line-height-rule:exactly; line-height:16px;"><?php echo esc_html( $_180c_surtitre ); ?></p>
				<?php endif; ?>
				<h1 class="h1" style="font-family:'Playfair Display',Georgia,'Times New Roman',serif; font-weight:700; font-size:28px; color:#0E0E0E; margin:0; mso-line-height-rule:exactly; line-height:34px;"><?php echo esc_html( $email_heading ); ?></h1>
			</td></tr>
			<?php endif; ?>
			<tr><td class="px" style="padding:30px 32px 32px 32px;">
				<div id="body_content_inner" style="font-family:'Playfair Display',Georgia,'Times New Roman',serif; font-size:16px; line-height:1.65; color:#0E0E0E;">
