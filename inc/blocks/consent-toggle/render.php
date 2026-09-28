<?php
/**
 * Bloc one80c/consent-toggle — rendu serveur.
 *
 * NAMESPACE `one80c/` ET NON `180c/`, et ce n'est pas un caprice.
 * -------------------------------------------------------------
 * Le parseur de blocs du cœur impose que le namespace commence par une LETTRE :
 * `(?P<namespace>[a-z][a-z0-9_-]*\/)?` (wp-includes/class-wp-block-parser.php).
 * Un nom commençant par un chiffre n'est jamais reconnu — `parse_blocks()`
 * renvoie `blockName: null` et le commentaire `<!-- wp:180c/... -->` traverse
 * la page telle quelle, sans rien rendre.
 *
 * `register_block_type()`, lui, accepte ces noms sans broncher : le bloc
 * apparaît dans l'insérateur, se laisse insérer, et ne s'affiche jamais côté
 * public. Panne silencieuse de bout en bout.
 *
 * Constat du 2026-08-31 : les 18 blocs `180c/*` du thème sont dans ce cas, et
 * aucun n'est utilisé dans le moindre post_content — ce qui explique que
 * personne ne s'en soit aperçu. Ils mériteraient le même renommage ; c'est un
 * chantier distinct, non entrepris ici.
 *
 * Interrupteur « Mesure d'audience » + bouton « Enregistrer ». Seul endroit du
 * site où l'on revient sur son choix : la modale, elle, ne recueille que le
 * choix initial.
 *
 * Le markup vit dans `_180c_consent_toggle_shortcode()` (inc/consent.php) et
 * non ici. Trois points d'entrée le rendent — ce bloc, le shortcode
 * `[180c_consent_toggle]`, et le filet de `the_content` — et ils doivent
 * produire exactement la même chose : trois copies auraient divergé au premier
 * correctif, dont deux sans que personne ne le voie.
 *
 * `"multiple": false` dans block.json : deux interrupteurs sur une même page
 * seraient tous deux fonctionnels et discordants dès qu'on touche l'un des
 * deux, puisque le module JS lit le PREMIER du document.
 *
 * Pas de `style` déclaré dans block.json : les classes `.consent__*` sont
 * servies par `src/css/components/consent.css`, déjà dans le bundle principal.
 * Une feuille de bloc dupliquerait ces déclarations pour rien.
 *
 * Variables disponibles : $attributes, $content, $block.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '_180c_consent_toggle_shortcode' ) ) {
	return;
}

$anchor  = isset( $attributes['anchor'] ) ? (string) $attributes['anchor'] : '';
$wrapper = get_block_wrapper_attributes( '' !== $anchor ? array( 'id' => $anchor ) : array() );

printf(
	'<div %1$s>%2$s</div>',
	$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() échappe déjà.
	_180c_consent_toggle_shortcode() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup construit et échappé dans inc/consent.php.
);
