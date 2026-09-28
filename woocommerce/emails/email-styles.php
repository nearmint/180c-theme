<?php
/**
 * Email Styles — surcharge 180°C (châssis brandé, phase 2B).
 *
 * Cœur du design e-mail : palette claire (base) + couche sombre `@media`, typo
 * Oswald (UI/corps) + Playfair (titres) avec fallbacks système, bouton
 * bulletproof, tableau de commande WC natif habillé, responsive 600px.
 *
 * Ce CSS est injecté par WooCommerce (`woocommerce_email_styles`) puis « inliné »
 * sur les éléments par l'inliner (Emogrifier) ; les règles non-inlinables
 * (`@media`, `[data-ogsc]`) sont préservées dans un `<style>` du `<head>`. Les
 * éléments du châssis portent donc des classes (page-bg, card, fg, body-fg,
 * muted, legal, logo-light, logo-dark, td…) pour que les surcharges sombres
 * ciblent juste. Pas de `var()` (non supporté en e-mail).
 *
 * Réécriture intégrale : ce châssis ne lit AUCUN réglage de couleur ni de
 * police de WooCommerce (`woocommerce_email_*_color`, `email_improvements`,
 * `block_email_editor`). L'écran « WooCommerce › Réglages › E-mails » est donc
 * sans effet sur le rendu, aperçu compris — c'est voulu, la palette est celle
 * de la marque. Le `@version` suit le cœur pour le rapport d'état, il ne
 * signale pas une parité de contenu.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;

// Palette claire (base) — miroir de cross-platform/tokens.yaml + valeurs thème.
$c_accent    = '#FFAE3A';
$c_accent_fg = '#0E0E0E';
$c_page      = '#F5F5F5';
$c_card      = '#FFFFFF';
$c_fg        = '#0E0E0E';
$c_muted     = '#6E6E6E';
$c_border    = '#E5E5E5';
$c_thead     = '#FAFAFA';

// Typo : titres = Playfair ; tout le reste (corps, UI, tableaux) = Oswald.
$font_title = "'Playfair Display', Georgia, 'Times New Roman', serif";
$font_ui    = "'Oswald', 'Arial Narrow', Arial, Helvetica, sans-serif";
?>
/* ===== Reset & base ===== */
body {
	background-color: <?php echo esc_html( $c_page ); ?>;
	margin: 0;
	padding: 0;
	width: 100% !important;
	font-family: <?php echo $font_ui; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	font-size: 16px;
	color: <?php echo esc_html( $c_fg ); ?>;
	-webkit-text-size-adjust: 100%;
	-ms-text-size-adjust: 100%;
}
img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
p { margin: 0 0 16px; }

/* ===== Corps éditorial (Playfair) ===== */
#body_content_inner {
	font-family: <?php echo $font_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	font-size: 16px;
	line-height: 1.65;
	color: <?php echo esc_html( $c_fg ); ?>;
}
#body_content_inner p { margin: 0 0 16px; }
/* Titres de section dans le corps (n° de commande, libellés d'adresse) = Oswald. */
#body_content_inner h2 {
	font-family: <?php echo $font_ui; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	font-size: 18px;
	font-weight: 600;
	color: <?php echo esc_html( $c_fg ); ?>;
	margin: 26px 0 12px;
}
#body_content_inner a { color: #B26A00; text-decoration: underline; }

/* ===== Tableau de commande WC natif habillé ===== */
.td {
	border: 1px solid <?php echo esc_html( $c_border ); ?>;
	padding: 10px 12px;
	text-align: left;
	vertical-align: middle;
	font-family: <?php echo $font_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	font-size: 14px;
	color: <?php echo esc_html( $c_fg ); ?>;
}
/* En-têtes de colonnes (Produit / Quantité / Prix) = Oswald. */
th.td {
	background-color: <?php echo esc_html( $c_thead ); ?>;
	font-family: <?php echo $font_ui; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	color: <?php echo esc_html( $c_muted ); ?>;
}
.order-totals-last th, .order-totals-last td { font-weight: 700; color: <?php echo esc_html( $c_fg ); ?>; }
address.address {
	font-style: normal;
	font-family: <?php echo $font_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	font-size: 14px;
	line-height: 1.6;
	color: <?php echo esc_html( $c_fg ); ?>;
}

/* ===== Bouton (fallback non-VML / e-mails WC à bouton .button) ===== */
.button, .btn-a {
	background-color: <?php echo esc_html( $c_accent ); ?>;
	border-radius: 8px;
	color: <?php echo esc_html( $c_accent_fg ); ?> !important;
	display: inline-block;
	font-family: <?php echo $font_ui; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
	font-size: 15px;
	font-weight: 600;
	padding: 14px 30px;
	text-decoration: none;
}

/* ===== Helpers chrome (couleurs claires de base) ===== */
.page-bg { background-color: <?php echo esc_html( $c_page ); ?>; }
.card, .body-card { background-color: <?php echo esc_html( $c_card ); ?>; }
.fg { color: <?php echo esc_html( $c_fg ); ?>; }
.body-fg { color: <?php echo esc_html( $c_fg ); ?>; }
.muted, .legal, .footer-link { color: <?php echo esc_html( $c_muted ); ?>; }
/* Variante logo « dark » masquée en clair SANS display:none — sinon l'inliner WC
 * (HtmlPruner) supprime l'élément et casse le swap dark. max-height:0 survit. */
.logo-dark { display: block; max-height: 0; overflow: hidden; mso-hide: all; }

/* ===== DARK : couche par-dessus la base claire (valeurs campagne) ===== */
@media (prefers-color-scheme: dark) {
	body, .page-bg { background-color: #0E0E0E !important; }
	.card, .body-card { background-color: #1A1A1A !important; }
	.fg, #body_content_inner { color: #F5F5F5 !important; }
	#body_content_inner h2 { color: #F5F5F5 !important; }
	.body-fg, #body_content_inner p { color: #E6E6E6 !important; }
	.muted, .footer-link { color: #A0A0A0 !important; }
	.legal { color: #8C8C8C !important; }
	.logo-light { display: none !important; }
	.logo-dark { display: block !important; max-height: none !important; overflow: visible !important; }
	.td, td.td { border-color: #2A2A2A !important; color: #E6E6E6 !important; }
	th.td { background-color: #1A1A1A !important; color: #A0A0A0 !important; }
	.order-totals-last th, .order-totals-last td { color: #F5F5F5 !important; }
	address.address { color: #E6E6E6 !important; }
}

/* ===== Outlook.com (data-ogsc) ===== */
[data-ogsc] body, [data-ogsc] .page-bg { background-color: #0E0E0E !important; }
[data-ogsc] .card, [data-ogsc] .body-card { background-color: #1A1A1A !important; }
[data-ogsc] .fg, [data-ogsc] #body_content_inner, [data-ogsc] #body_content_inner h2 { color: #F5F5F5 !important; }
[data-ogsc] .body-fg { color: #E6E6E6 !important; }
[data-ogsc] .muted, [data-ogsc] .footer-link { color: #A0A0A0 !important; }
[data-ogsc] .legal { color: #8C8C8C !important; }
[data-ogsc] .logo-light { display: none !important; }
[data-ogsc] .logo-dark { display: block !important; max-height: none !important; overflow: visible !important; }
[data-ogsc] .td, [data-ogsc] td.td { border-color: #2A2A2A !important; color: #E6E6E6 !important; }
[data-ogsc] th.td { background-color: #1A1A1A !important; color: #A0A0A0 !important; }

/* ===== Responsive 600px ===== */
@media screen and (max-width: 600px) {
	#wrapper, .container { width: 100% !important; }
	.px { padding-left: 22px !important; padding-right: 22px !important; }
	.h1 { font-size: 26px !important; line-height: 1.2 !important; }
	.btn-a { display: block !important; }
	.addr-col { display: block !important; width: 100% !important; box-sizing: border-box; }
}
