<?php
/**
 * Nettoyage des balises de découverte WordPress du `<head>`.
 *
 * WordPress émet une poignée de `<link>` destinés à des clients d'édition
 * distante et à l'auto-découverte de son API. Aucun n'est consommé par ce site,
 * deux exposent des identifiants internes, et un troisième pointe vers
 * `xmlrpc.php`. Mesuré en production : 338 octets pour les quatre visés.
 *
 * Le gain de poids est marginal et n'est pas l'objectif : ces balises réduisent
 * la surface d'information publiée sur la stack et évitent d'exposer des URLs
 * concurrentes du canonique.
 *
 * `wp_generator` est déjà désenregistrée ailleurs (`inc/security-headers.php`),
 * de même que `wp_robots` (`inc/seo/meta-tags.php`) : on ne les redouble pas.
 * `wlwmanifest_link` a été retirée du cœur en WordPress 6.7 — vérifié absent de
 * la production, rien à faire.
 *
 * CONSERVÉ VOLONTAIREMENT :
 *  - les liens de découverte oEmbed (`wp_oembed_add_discovery_links`, 331 o).
 *    Ils ne servent pas ce site mais les sites TIERS qui collent une URL
 *    180c.fr dans leur éditeur. Leur absence de nos journaux ne prouve pas
 *    l'absence de consommateur, et une régression se manifesterait chez un
 *    partenaire, donc invisible pour nous. Le rapport bénéfice/risque ne
 *    justifie pas 331 octets ;
 *  - le flux RSS. Le thème n'active pas `automatic-feed-links`, donc aucun lien
 *    de découverte n'est émis, mais `/feed/` reste servi (HTTP 200) et lié
 *    depuis le pied de page (`parts/footer/main.php`). Rien n'est touché ici ;
 *  - le canonique, les `og:*` / `twitter:*`, et tout ce qui sert WooCommerce
 *    ou Simple JWT Login.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Désenregistre les balises de découverte inutilisées.
 *
 * Exécuté sur `init` et non au chargement du fichier : les actions visées sont
 * posées par le cœur dans `default-filters.php`, donc déjà en place, mais un
 * `init` laisse à une extension la possibilité de les réenregistrer après nous
 * si elle en a besoin — et rend le retrait filtrable.
 *
 * @return void
 */
function _180c_remove_head_discovery_links() {
	/**
	 * Filtre la liste des callbacks `wp_head` désenregistrés.
	 *
	 * @param string[] $callbacks Noms de fonctions du cœur WordPress.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	$callbacks = (array) apply_filters(
		'180c/head_discovery_removed',
		array(
			// <link rel="EditURI" …xmlrpc.php?rsd> — Really Simple Discovery,
			// destiné aux clients d'édition distante (Windows Live Writer et
			// consorts). Pointe sur xmlrpc.php.
			'rsd_link',

			// <link rel='shortlink' href='…/?p=16312'> — jamais utilisé par le
			// thème, expose l'ID interne du contenu et publie une URL
			// concurrente du canonique.
			'wp_shortlink_wp_head',

			// <link rel="https://api.w.org/" …> ET
			// <link rel="alternate" type="application/json" …/wp/v2/pages/16312>
			// Les DEUX viennent de cette seule fonction (wp-includes/rest-api.php,
			// rest_output_link_wp_head()). Retirer l'action n'affecte que la
			// DÉCOUVRABILITÉ de l'API : les routes restent servies normalement,
			// ce que le lot vérifie explicitement sur 180c/v1.
			'rest_output_link_wp_head',
		)
	);

	foreach ( $callbacks as $callback ) {
		remove_action( 'wp_head', $callback );
	}

	// Les mêmes informations sont aussi publiées en en-tête HTTP, où retirer la
	// seule balise <link> ne suffit pas — constaté : `Link: <…>; rel=shortlink`
	// survivait au retrait de `wp_shortlink_wp_head`. Les deux actions sont
	// enregistrées par le cœur sur `template_redirect` priorité 11
	// (wp-includes/default-filters.php, lignes 332 et 364) ; la priorité doit
	// être reprise à l'identique pour que le désenregistrement porte.
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
}
add_action( 'init', '_180c_remove_head_discovery_links' );
