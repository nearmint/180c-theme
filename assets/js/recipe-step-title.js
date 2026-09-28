/**
 * Nettoyage à la volée de la numérotation dans « Titre de l'étape ».
 *
 * Les recettes sont saisies par copier-coller depuis Word : la numérotation
 * automatique de Word atterrit dans le sous-champ « Titre de l'étape »
 * (field_180c_step_title, repeater `steps`), alors que single-recipe.php génère
 * déjà le numéro en dur à l'affichage.
 *
 * Ce script est un CONFORT ÉDITEUR, pas une garantie : la source de vérité est
 * le filtre serveur `acf/update_value` (inc/acf/step-title-cleaner.php),
 * qui nettoie de toute façon à l'enregistrement. Ici on se contente de montrer
 * tout de suite le résultat, silencieusement — aucune alerte, aucun blocage.
 *
 * Le script n'est chargé que sur les écrans d'édition du CPT recipe
 * (cf. inc/admin/recipe-editor.php).
 */
( function () {
	'use strict';

	/**
	 * Field key du sous-champ ciblé (acf-json/group_recipe_fields.json).
	 *
	 * @type {string}
	 */
	const FIELD_KEY = 'field_180c_step_title';

	/**
	 * Motif du préfixe de numérotation à retirer, ancré en début de chaîne.
	 *
	 * ⚠ MOTIF PARTAGÉ AVEC LE PHP.
	 * Il est rigoureusement identique, caractère pour caractère, à la constante
	 * `_180C_STEP_TITLE_PREFIX_PATTERN` de `inc/acf/step-title-cleaner.php`
	 * (mêmes drapeaux : `i` + `u`), à la seule exception de la syntaxe
	 * d'échappement des insécables, que les deux moteurs n'écrivent pas pareil :
	 * `\u00A0` ici, `\x{00A0}` en PCRE. Toute retouche doit être reportée de
	 * l'autre côté — sinon l'aperçu de l'éditeur et la valeur réellement
	 * enregistrée divergent silencieusement. Le commentaire détaillé du motif,
	 * insécables comprises, vit du côté PHP, qui fait foi.
	 *
	 * @type {RegExp}
	 */
	const PREFIX_PATTERN = /^[\s\u00A0\u202F]*(?:[EÉeé]tape[\s\u00A0\u202F]*\d{1,2}(?!\d)[\s\u00A0\u202F]*[.):°–—-]?|\d{1,2}(?!\d)[\s\u00A0\u202F]*[.):°–—-])[\s\u00A0\u202F]*/iu;

	/**
	 * Marqueur de liaison, pour ne pas brancher deux fois le même champ.
	 *
	 * Plusieurs actions ACF (`ready_field`, `append_field`, `new_field`) peuvent
	 * viser la même instance selon la version d'ACF et le mode d'insertion.
	 *
	 * @type {string}
	 */
	const BOUND_FLAG = 'oneEightyCStepTitleBound';

	/**
	 * Rogne les blancs de bord ASCII, et EUX SEULS.
	 *
	 * `String.prototype.trim()` suit la définition WhiteSpace d'ECMAScript, qui
	 * englobe U+00A0 et U+202F : l'utiliser rognerait les insécables de bord et
	 * ferait diverger le résultat de celui du PHP, dont `trim()` ne connaît que
	 * les blancs ASCII. On réplique donc exactement la liste par défaut de
	 * `trim()` en PHP : espace, tabulation, LF, CR, NUL, tabulation verticale.
	 *
	 * @param {string} value  Chaîne à rogner.
	 * @return {string} Chaîne rognée.
	 */
	function asciiTrim( value ) {
		return value.replace( /^[ \t\n\r\0\v]+|[ \t\n\r\0\v]+$/g, '' );
	}

	/**
	 * Retire la numérotation de tête d'un titre d'étape.
	 *
	 * Réplique exacte de `_180c_clean_step_title()` en PHP, garde-fou compris :
	 * si le nettoyage vide la valeur, la valeur d'origine est rendue telle
	 * quelle. Hors du préfixe, la chaîne ressort strictement inchangée,
	 * insécables comprises.
	 *
	 * @param {string} title  Titre brut.
	 * @return {string} Titre nettoyé.
	 */
	function cleanStepTitle( title ) {
		const cleaned = asciiTrim( String( title ).replace( PREFIX_PATTERN, '' ) );

		return '' === cleaned ? title : cleaned;
	}

	/**
	 * Applique le nettoyage à un `<input>`, si tant est qu'il change quelque chose.
	 *
	 * L'événement `change` est réémis pour qu'ACF prenne acte de la nouvelle
	 * valeur. Pas de boucle possible : le nettoyage est idempotent, donc au
	 * second passage `cleaned === input.value` et on sort avant toute écriture.
	 *
	 * @param {HTMLInputElement} input  Champ texte du titre d'étape.
	 * @return {void}
	 */
	function applyClean( input ) {
		const cleaned = cleanStepTitle( input.value );

		if ( cleaned === input.value ) {
			return;
		}

		input.value = cleaned;
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	/**
	 * Branche les écouteurs sur le champ texte d'une instance du sous-champ.
	 *
	 * `change` (donc à la perte de focus) et `paste` seulement : nettoyer à
	 * chaque frappe empêcherait de taper « 1. » dans un titre légitime. Le
	 * collage est traité au tick suivant, la valeur de l'input n'étant pas
	 * encore à jour au moment où l'événement se déclenche.
	 *
	 * @param {Object} field  Instance de champ ACF.
	 * @return {void}
	 */
	function bindField( field ) {
		if ( ! field || 'function' !== typeof field.$input ) {
			return;
		}

		const $input = field.$input();
		const input = $input && $input.length ? $input[ 0 ] : null;

		if ( ! input || input.dataset[ BOUND_FLAG ] ) {
			return;
		}

		input.dataset[ BOUND_FLAG ] = '1';

		input.addEventListener( 'change', function () {
			applyClean( input );
		} );

		input.addEventListener( 'paste', function () {
			window.setTimeout( function () {
				applyClean( input );
			}, 0 );
		} );
	}

	if ( 'undefined' === typeof window.acf || 'function' !== typeof window.acf.addAction ) {
		return;
	}

	// Les trois actions couvrent les trois moments où une instance du champ
	// apparaît : au chargement de la page (`ready_field`), à l'ajout d'une ligne
	// de repeater (`append_field`), et à l'initialisation générique
	// (`new_field`, ACF 5.7+). `bindField` est idempotent, les recouvrements
	// sont donc sans conséquence.
	[ 'ready_field', 'append_field', 'new_field' ].forEach( function ( action ) {
		window.acf.addAction( action + '/key=' + FIELD_KEY, bindField );
	} );
} )();
