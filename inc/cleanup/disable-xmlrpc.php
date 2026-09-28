<?php
/**
 * Désactivation de XML-RPC.
 *
 * Constat de production (2026-08-04) : `POST /xmlrpc.php` répondait 200 et
 * `system.listMethods` exposait **80 méthodes**, dont :
 *  - `pingback.ping` — vecteur d'amplification DDoS classique, le serveur
 *    pouvant être utilisé pour émettre des requêtes vers une cible tierce ;
 *  - `system.multicall` — permet d'empiler des centaines de tentatives
 *    `wp.getUsersBlogs` dans une seule requête HTTP, ce qui rend les
 *    protections anti-force-brute par nombre de requêtes inopérantes ;
 *  - `blogger.*` / `wp.newPost` / `wp.deletePost` — publication distante.
 *
 * Aucun consommateur identifié :
 *  - les applications iOS et Android passent par le namespace REST `180c/v1`
 *    et Simple JWT Login, jamais par XML-RPC ;
 *  - Jetpack n'est pas installé (extensions présentes : ACF Pro,
 *    Simple JWT Login, WooCommerce et ses modules) ;
 *  - aucun plugin de sauvegarde ni d'outil de publication distante.
 *
 * Effet de bord connu et accepté : les **pingbacks entrants** cessent. Les
 * commentaires sont désactivés sur le site (décision actée), l'impact est nul.
 *
 * ANGLE MORT ASSUMÉ : un outil externe utilisé par la rédaction (client de
 * publication de bureau type MarsEdit, service de programmation tiers) ne
 * laisserait aucune trace côté code. Ce point reste à confirmer auprès de
 * l'équipe éditoriale — c'est le seul risque réel de ce changement.
 *
 * ROLLBACK : supprimer ce fichier de `inc/bootstrap.php`, ou sans toucher au
 * dépôt, poser depuis un mu-plugin :
 *     add_filter( 'xmlrpc_enabled', '__return_true', 20 );
 * Aucune donnée n'est modifiée, aucune migration n'est en jeu.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/*
 * Le filtre `xmlrpc_enabled` neutralise toutes les méthodes qui exigent une
 * authentification, ainsi que `system.multicall`. Le fichier `xmlrpc.php`
 * continue de répondre — le supprimer ou le bloquer relève du serveur
 * (`.htaccess`), pas du thème — mais il n'expose plus de surface exploitable.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Retire les méthodes de pingback de l'interface XML-RPC.
 *
 * `xmlrpc_enabled` ne les couvre pas : elles sont publiques par conception et
 * restent donc appelables même filtre posé. C'est précisément la surface
 * d'amplification DDoS.
 *
 * @param array $methods Méthodes XML-RPC exposées.
 * @return array Méthodes sans les entrées de pingback.
 */
function _180c_remove_xmlrpc_pingback_methods( $methods ) {
	unset(
		$methods['pingback.ping'],
		$methods['pingback.extensions.getPingbacks']
	);

	return $methods;
}
add_filter( 'xmlrpc_methods', '_180c_remove_xmlrpc_pingback_methods' );

/*
 * En-tête `X-Pingback: …/xmlrpc.php`, émis sur chaque page. Sans méthode de
 * pingback derrière, il ne fait plus qu'annoncer une porte fermée.
 */
add_filter( 'wp_headers', '_180c_remove_pingback_header' );

/**
 * Retire l'en-tête HTTP `X-Pingback`.
 *
 * @param array $headers En-têtes de la réponse.
 * @return array En-têtes sans X-Pingback.
 */
function _180c_remove_pingback_header( $headers ) {
	unset( $headers['X-Pingback'] );

	return $headers;
}
