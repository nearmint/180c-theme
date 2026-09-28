<?php
/**
 * Helper central de construction des balises `<title>`.
 *
 * Règle unique du site :
 *
 *     {Sujet} — {Qualificatif} · 180°C
 *
 * - Suffixe ` · 180°C` (espace, U+00B7, espace) injecté ici et nulle part
 *   ailleurs. Jamais saisi à la main dans un template ou une meta.
 * - Une seule occurrence de la marque : si le sujet la contient déjà, le
 *   suffixe n'est pas ajouté.
 * - Un seul séparateur interne ` — ` (U+2014).
 * - 60 caractères maximum, suffixe compris. Au-delà : on retire d'abord le
 *   qualificatif, puis on tronque le sujet sur la dernière limite de mot
 *   suivie de `…`. La marque n'est jamais tronquée.
 * - Pagination : ` — Page {N}` avant le suffixe, dès la page 2, exempté de
 *   la limite de 60 caractères.
 *
 * Ce module prend la main sur `pre_get_document_title`. Il ne remplace pas
 * `document_title_parts` (laissé en place pour les contextes non couverts),
 * mais le court-circuite dès qu'un contexte est résolu.
 *
 * Aucun échappement n'est fait ici : la sortie est destinée à
 * `wp_get_document_title()`, que WordPress échappe déjà, et à `esc_html()` /
 * `esc_attr()` côté template.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nom de marque, unique source de vérité.
 *
 * @var string
 */
const _180C_TITLE_BRAND = '180°C';

/**
 * Séparateur marque : espace + interponctuation U+00B7 + espace.
 *
 * @var string
 */
const _180C_TITLE_BRAND_SEP = ' · ';

/**
 * Séparateur interne : espace + cadratin U+2014 + espace.
 *
 * @var string
 */
const _180C_TITLE_SEP = ' — ';

/**
 * Longueur maximale du title, suffixe de marque compris.
 *
 * @var int
 */
const _180C_TITLE_MAX_LEN = 60;

/*
 * ---------------------------------------------------------------------------
 * 1. Le helper
 * ---------------------------------------------------------------------------
 */

/**
 * Assemble un `<title>` conforme à la règle unique du site.
 *
 * Ordre des opérations : normalisation → assemblage sujet/qualificatif →
 * mention de pagination → déduplication de la marque → suffixe → troncature.
 *
 * @param string $subject   Sujet principal (titre de page, nom de terme…).
 * @param string $qualifier Qualificatif optionnel, posé à droite du cadratin.
 * @param array  $args      {
 *     Options.
 *
 *     @type int  $paged       Numéro de page ; ` — Page {N}` dès 2. Défaut 0.
 *     @type bool $no_brand    N'ajoute pas le suffixe de marque. Défaut false.
 *     @type bool $no_truncate Désactive la limite de 60 caractères. Défaut false.
 * }
 * @return string Title final, non échappé.
 */
function _180c_build_title( string $subject, string $qualifier = '', array $args = array() ): string {
	$args = wp_parse_args(
		$args,
		array(
			'paged'       => 0,
			'no_brand'    => false,
			'no_truncate' => false,
		)
	);

	$subject   = _180c_title_normalize( $subject );
	$qualifier = _180c_title_normalize( $qualifier );
	$paged     = (int) $args['paged'];

	// Sans sujet exploitable, on ne rend jamais une chaîne vide ni la marque
	// seule : on retombe sur la marque en tant que sujet, sans suffixe.
	if ( '' === $subject ) {
		$subject   = _180C_TITLE_BRAND;
		$qualifier = '';
	}

	// Un qualificatif identique au sujet ne dit rien de plus : on le laisse
	// tomber plutôt que de rendre « La Gazette — La Gazette ». Le cas se
	// produit dès qu'un terme porte le nom de son propre repli de taxonomie.
	if ( '' !== $qualifier && 0 === strcasecmp( $subject, $qualifier ) ) {
		$qualifier = '';
	}

	// Assemblage sujet + qualificatif.
	$title = ( '' !== $qualifier )
		? $subject . _180C_TITLE_SEP . $qualifier
		: $subject;

	// Mention de pagination, avant le suffixe de marque.
	$page_suffix = ( $paged >= 2 )
		/* translators: %d : numéro de page d'une archive paginée. */
		? _180C_TITLE_SEP . sprintf( __( 'Page %d', '180c' ), $paged )
		: '';

	// Déduplication de la marque : si elle est déjà présente dans le sujet ou
	// le qualificatif, on n'ajoute pas le suffixe.
	$brand_present = _180c_title_has_brand( $title );
	$add_brand     = ! $args['no_brand'] && ! $brand_present;
	$brand_suffix  = $add_brand ? _180C_TITLE_BRAND_SEP . _180C_TITLE_BRAND : '';

	// Troncature. La pagination et la marque en sont exemptées : seul le
	// couple sujet/qualificatif est réduit, et uniquement hors pagination.
	if ( ! $args['no_truncate'] && '' === $page_suffix ) {
		$budget = _180C_TITLE_MAX_LEN - mb_strlen( $brand_suffix );

		if ( mb_strlen( $title ) > $budget ) {
			// 1er palier : on retire le qualificatif.
			$title = $subject;
		}
		if ( mb_strlen( $title ) > $budget ) {
			// 2e palier : troncature du sujet sur limite de mot.
			$title = _180c_title_truncate( $title, $budget );
		}
	}

	return $title . $page_suffix . $brand_suffix;
}

/**
 * Normalise une portion de title : tags retirés, entités décodées, espaces
 * (y compris insécables et blancs Unicode) réduits à un espace simple.
 *
 * @param string $text Texte brut.
 * @return string
 */
function _180c_title_normalize( string $text ): string {
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	// Outre les blancs ASCII, la classe couvre les trois espaces Unicode que
	// les rédacteurs saisissent sans le savoir : insécable, insécable fine et
	// fine. Non normalisés, ils faussent le comptage des 60 caractères.
	$text = (string) preg_replace( '/[\s\x{00A0}\x{202F}\x{2009}]+/u', ' ', $text );

	return trim( $text );
}

/**
 * Vrai si la marque est déjà présente dans le texte (insensible à la casse).
 *
 * @param string $text Texte à inspecter.
 * @return bool
 */
function _180c_title_has_brand( string $text ): bool {
	return false !== mb_stripos( $text, _180C_TITLE_BRAND );
}

/**
 * Tronque sur la dernière limite de mot tenant dans la longueur voulue, et
 * suffixe d'une ellipse.
 *
 * L'ellipse est comptée dans le budget. Si aucune limite de mot n'est
 * exploitable (mot unique très long), on coupe au caractère.
 *
 * @param string $text   Texte à tronquer.
 * @param int    $length Longueur maximale, ellipse comprise.
 * @return string
 */
function _180c_title_truncate( string $text, int $length ): string {
	if ( $length < 2 ) {
		return $text;
	}
	if ( mb_strlen( $text ) <= $length ) {
		return $text;
	}

	// -1 pour l'ellipse « … » (1 caractère).
	$cut   = mb_substr( $text, 0, $length - 1 );
	$space = mb_strrpos( $cut, ' ' );

	if ( false !== $space && $space > 0 ) {
		$cut = mb_substr( $cut, 0, $space );
	}

	// Ponctuation traînante avant l'ellipse.
	$cut = rtrim( $cut, ' ,;:—-–' );

	return $cut . '…';
}

/*
 * ---------------------------------------------------------------------------
 * 2. Utilitaires de contexte
 * ---------------------------------------------------------------------------
 */

/**
 * Numéro de page courant, tous contextes confondus.
 *
 * Couvre la pagination d'archive (`paged`) et la pagination interne d'un
 * contenu découpé par `<!--nextpage-->` (`page`).
 *
 * @return int 0 ou 1 si non paginé, N sinon.
 */
function _180c_title_current_page(): int {
	$paged = (int) get_query_var( 'paged' );
	if ( $paged < 2 ) {
		$paged = (int) get_query_var( 'page' );
	}

	return max( 0, $paged );
}

/*
 * `_180c_title_acf_override()` ne revient pas ici, et c'est délibéré.
 *
 * Il lisait l'ACF `seo_title` et le prenait comme **sujet** du gabarit, qui lui
 * rajoutait ensuite qualificatif, pagination et suffixe de marque : le title
 * rendu n'était jamais celui affiché dans le champ. Une valeur fautive passait
 * sans que rien ne le signale (cf. « recette Pudding aux tomates et chorizo »,
 * qui venait de ce champ et non du code).
 *
 * Le champ a été rebranché en 2026-07 (`inc/seo/overrides.php`) pour découpler
 * le `<title>` des titres éditoriaux à jeux de mots, dont l'audit GSC a montré
 * qu'ils annulent le CTR en position 1. Mais il l'a été **hors du gabarit** :
 * l'override est servi tel quel sur `pre_get_document_title` en priorité 4, et
 * ce fichier ne le voit jamais.
 *
 * La conséquence est ce qu'on voulait : deux régimes nets, pas un hybride.
 * Sans saisie, le gabarit décide seul ; avec saisie, ce qui est saisi est ce
 * qui sort. Aucun assemblage silencieux entre les deux.
 *
 * L'autre point d'extension reste le filtre `180c/title_context`
 * (`inc/seo/title-resolver.php`), versionné et relisible.
 */
