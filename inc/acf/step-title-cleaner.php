<?php
/**
 * Nettoyage de la numérotation parasite dans les titres d'étapes de recette.
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * Les recettes sont saisies par copier-coller depuis Word. La numérotation
 * automatique de Word est collée dans le sous-champ « Titre de l'étape »
 * (`field_180c_step_title`, repeater `steps`) : « 1.Les œufs », « Étape 2 : … ».
 *
 * Or `single-recipe.php` génère déjà le numéro en dur (`.recipe-step__number`,
 * incrémenté à l'affichage). Résultat en front : « 1 » puis « 1.Les œufs ».
 *
 * Ce module retire le préfixe numérique **à l'enregistrement**, côté serveur :
 * c'est la source de vérité, elle couvre toutes les voies d'écriture (écran
 * d'édition, import, `update_field()`, REST). Le nettoyage JS de l'écran
 * d'édition (`assets/js/recipe-step-title.js`) n'est qu'un confort éditeur :
 * il montre le résultat immédiatement, il ne le garantit pas.
 *
 * CE QUI N'EST DÉLIBÉRÉMENT PAS FAIT
 * ----------------------------------
 *  - Le template n'est pas touché : la numérotation d'affichage reste sa
 *    responsabilité.
 *  - Les données déjà en base ne sont pas reprises : c'est l'objet d'une
 *    requête SQL passée à la main en production (motif de meta_key :
 *    `steps_%_step_title`).
 *
 * GARDE-FOU
 * ---------
 * Un titre qui commence par un nombre SANS séparateur est légitime
 * (« 2 façons de monter les blancs », « 180°C au four ») : il n'est jamais
 * touché. Et si le nettoyage vidait la valeur (« 1. » seul), la valeur
 * d'origine est rendue telle quelle — mieux vaut un doublon qu'une perte.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field key du sous-champ « Titre de l'étape ».
 *
 * Ciblage par KEY et non par nom : `step_title` est un nom générique qui
 * pourrait réapparaître dans un autre groupe ACF, alors que la key est unique
 * et stable (cf. acf-json/group_recipe_fields.json).
 */
const _180C_STEP_TITLE_FIELD_KEY = 'field_180c_step_title';

/**
 * Motif du préfixe de numérotation à retirer, ancré en début de chaîne.
 *
 * ⚠ MOTIF PARTAGÉ AVEC LE JAVASCRIPT.
 * Il est rigoureusement identique, caractère pour caractère, à la constante
 * `PREFIX_PATTERN` de `assets/js/recipe-step-title.js` (mêmes drapeaux : `i` +
 * `u`), à la seule exception de la syntaxe d'échappement des insécables, que
 * les deux moteurs n'écrivent pas pareil : `\x{00A0}` en PCRE, ` ` en
 * JavaScript. La section PARITÉ du test manuel (non versionné)
 * normalise cette différence-là et compare tout le reste — toute autre dérive
 * la fait échouer.
 *
 * POURQUOI LES INSÉCABLES SONT DANS LE MOTIF ET NON NORMALISÉES EN AMONT
 * ---------------------------------------------------------------------
 * Word colle des U+00A0 (NO-BREAK SPACE) et des U+202F (NARROW NO-BREAK SPACE)
 * un peu partout, y compris juste après le point de la numérotation
 * (« 1.<U+00A0>Les œufs ») : sans elles dans la classe d'espaces, `\s` ne les
 * voit pas et le préfixe échappe au nettoyage.
 *
 * Une première version les remplaçait par des espaces ordinaires sur TOUTE la
 * chaîne avant analyse. C'était une régression typographique : l'insécable qui
 * précède `:` `;` `!` `?` `»` en français est voulue, elle empêche une coupure
 * de ligne fautive — et elle sautait même sur des titres sans la moindre
 * numérotation à retirer (« Blancs<U+00A0>: mode d'emploi »). Intégrées à la
 * classe d'espaces, seules les insécables effectivement CONSOMMÉES par le
 * préfixe disparaissent ; le reste du titre ressort intact.
 *
 * Lecture du motif, où WS abrège la classe d'espaces `[\s\x{00A0}\x{202F}]` :
 *
 *   ^WS*                       espaces de tête
 *   (?:
 *     [EÉeé]tapeWS*\d{1,2}     « Étape 1 », « Etape 2 », « ÉTAPE 03 »
 *       (?!\d)WS*
 *       [.):°–—-]?             séparateur FACULTATIF : le mot « étape »
 *                              suffit à lever l'ambiguïté
 *   |
 *     \d{1,2}(?!\d)WS*         « 1 », « 01 » — 1 à 2 chiffres…
 *       [.):°–—-]              …suivis d'un séparateur OBLIGATOIRE, seul signe
 *                              qu'il s'agit d'une numérotation
 *   )
 *   WS*                        espaces avant le titre réel
 *
 * La classe est écrite en toutes lettres à chacune de ses six occurrences
 * plutôt qu'assemblée depuis une sous-constante : c'est le prix à payer pour
 * que le motif reste un littéral, donc extractible et comparable au motif JS
 * par le harnais de test.
 *
 * `(?!\d)` est explicite plutôt qu'implicite : il dit noir sur blanc que
 * « 180°C au four » ne doit pas être lu comme « 18 » + « 0 » ni « 1 » + « 8 ».
 *
 * Séparateurs acceptés : point, parenthèse fermante, deux-points, degré, tiret
 * demi-cadratin, tiret cadratin, trait d'union.
 */
const _180C_STEP_TITLE_PREFIX_PATTERN = '/^[\s\x{00A0}\x{202F}]*(?:[EÉeé]tape[\s\x{00A0}\x{202F}]*\d{1,2}(?!\d)[\s\x{00A0}\x{202F}]*[.):°–—-]?|\d{1,2}(?!\d)[\s\x{00A0}\x{202F}]*[.):°–—-])[\s\x{00A0}\x{202F}]*/iu';

/**
 * Retire la numérotation de tête d'un titre d'étape.
 *
 * Fonction pure, sans effet de bord et sans dépendance à WordPress : c'est elle
 * que couvre le test manuel de parité.
 *
 * Deux opérations, dans l'ordre : retrait du préfixe de numérotation (les
 * insécables qu'il contient sont consommées avec lui, cf.
 * `_180C_STEP_TITLE_PREFIX_PATTERN`), puis trim. Hors du préfixe, la chaîne
 * ressort strictement inchangée, insécables comprises.
 *
 * @param string $title Titre brut.
 * @return string Titre nettoyé, ou le titre d'origine si le nettoyage le viderait.
 */
function _180c_clean_step_title( string $title ): string {
	$cleaned = preg_replace( _180C_STEP_TITLE_PREFIX_PATTERN, '', $title, 1 );

	// `preg_replace` rend null en cas d'échec du moteur (UTF-8 malformé).
	// Dans ce cas on ne touche à rien.
	if ( null === $cleaned ) {
		return $title;
	}

	// `trim()` sans liste de caractères : il ne rogne que les blancs ASCII
	// (" \t\n\r\0\x0B") et laisse donc les insécables de bord intactes. Le
	// pendant JavaScript ne peut PAS utiliser String.prototype.trim(), qui lui
	// rognerait les insécables — cf. le commentaire de asciiTrim().
	$cleaned = trim( $cleaned );

	// Garde-fou : le titre n'était QUE de la numérotation (« 1. »). On rend la
	// valeur d'origine — le doublon visuel est préférable à un titre d'étape
	// effacé sans que personne ne s'en aperçoive.
	if ( '' === $cleaned ) {
		return $title;
	}

	return $cleaned;
}

/**
 * Nettoie la valeur du sous-champ juste avant son enregistrement.
 *
 * @param mixed $value Valeur sur le point d'être enregistrée.
 * @return mixed Valeur nettoyée si c'est une chaîne, valeur d'origine sinon.
 */
function _180c_filter_step_title_update_value( $value ) {
	if ( ! is_string( $value ) ) {
		return $value;
	}

	return _180c_clean_step_title( $value );
}
add_filter( 'acf/update_value/key=' . _180C_STEP_TITLE_FIELD_KEY, '_180c_filter_step_title_update_value' );
