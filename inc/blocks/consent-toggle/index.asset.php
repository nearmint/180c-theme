<?php
/**
 * Dépendances de inc/blocks/consent-toggle/index.js.
 *
 * Écrit à la main, et non généré par @wordpress/dependency-extraction-webpack-plugin :
 * le thème n'a pas de chaîne de build pour les blocs. WordPress lit ce fichier
 * automatiquement — même nom que le script, suffixe `.asset.php` — pour en
 * déduire les dépendances et la version.
 *
 * Sans lui, le script serait enregistré SANS dépendance : il s'exécuterait avant
 * que `wp.blocks` existe et le bloc ne serait jamais enregistré.
 *
 * `version` : incrémenter à chaque modification de index.js, sinon le navigateur
 * et le cache serviront l'ancien fichier.
 *
 * @package 180c
 */

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-i18n',
	),
	'version'      => '1.0.0',
);
