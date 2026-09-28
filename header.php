<?php
/**
 * Header HTML.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	/*
	 * Résolution du mode d'affichage — côté CLIENT, en tout premier.
	 *
	 * L'attribut data-theme était auparavant posé sur <html> par PHP depuis le
	 * cookie. WP Super Cache sert le HTML anonyme depuis un fichier statique :
	 * la valeur du PREMIER visiteur à régénérer le cache était donc figée dans
	 * la page et servie à tous les suivants. Un visiteur en mode clair recevait
	 * `data-theme="dark"` puis basculait au chargement du bundle JS — un flash
	 * sombre sur chaque navigation. Le toggle du footer était cosmétique : PHP
	 * décidait, le cache figeait.
	 *
	 * Ce script est volontairement inline, synchrone et placé AVANT toute
	 * feuille de style : il s'exécute avant le premier paint, donc sans FOUC, et
	 * n'ajoute aucune requête. Il duplique sciemment la logique de lecture de
	 * src/js/modules/dark-mode.js (~15 lignes) : ce module voyage dans le bundle
	 * principal, chargé bien trop tard pour décider de la couleur de fond.
	 *
	 * Défaut sans choix persisté : `dark` — décision produit, alignée sur
	 * getStoredMode() dans dark-mode.js. `auto` ne pose aucun attribut et laisse
	 * `prefers-color-scheme` trancher via le CSS (cf. src/css/main.css).
	 */
	?>
	<script>
	(function(){var m='';try{var c=document.cookie.match(/(?:^|;\s*)180c-theme-mode=([^;]*)/);if(c){m=decodeURIComponent(c[1]);}if(!m){m=localStorage.getItem('180c-theme-mode')||'';}}catch(e){}if(m!=='auto'&&m!=='light'&&m!=='dark'){m='dark';}if(m!=='auto'){document.documentElement.setAttribute('data-theme',m);}})();
	</script>
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Aller au contenu', '180c' ); ?></a>

<div id="site-shell">

	<header id="site-header" class="site-header">
		<div class="site-container">
			<?php get_template_part( 'parts/header/main' ); ?>
		</div>
		<?php if ( is_singular( 'post' ) ) : ?>
			<?php /* Barre de progression de lecture (articles) : ancrée DANS le header
			pour suivre sa hauteur dans les deux états et rester visible. Décoratif,
			dégrade proprement sans JS (reste à 0). */ ?>
			<div class="reading-progress" aria-hidden="true">
				<div class="reading-progress__bar"></div>
			</div>
		<?php endif; ?>
	</header>
