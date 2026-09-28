<?php
/**
 * Normalisation des noms de fichiers médias à l'upload.
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * Les fichiers déposés par la rédaction arrivent avec des espaces, des accents,
 * du CamelCase et des symboles typographiques :
 * `©180°C-LesCahiersDeDelphine-CremeGlaceePetitSuisseFruits-1.webp`.
 *
 * WordPress ne les corrige qu'à moitié (cf. « CE QUE FAIT DÉJÀ LE CORE »), ce
 * qui laisse en production des URL percent-encodées. Conséquences mesurées :
 * rich results dégradés côté Google, et un risque de casse côté apps iOS et
 * Android, qui consomment ces URL via l'API REST.
 *
 * Ce module ramène tout nom de fichier média à `[a-z0-9-]` — silencieusement,
 * sans notice ni message d'admin.
 *
 * CE QUE FAIT DÉJÀ LE CORE (vérifié sur WordPress 7.0.2)
 * -----------------------------------------------------
 * `sanitize_file_name()` (wp-includes/formatting.php:2035) appelle bien
 * `remove_accents()` en première ligne — contrairement à une idée reçue, les
 * accents ne sont donc PAS notre problème à l'upload. Ce que le core laisse
 * passer, en revanche :
 *
 *  - `©`, `®`, `™`, `°` : absents de sa liste `$special_chars` ;
 *  - la casse : aucun passage en minuscules ;
 *  - le CamelCase : aucun découpage ;
 *  - les `_` et les `.` internes : conservés tels quels.
 *
 * POURQUOI LE FILTRE EST RESTREINT AUX EXTENSIONS MÉDIAS
 * -----------------------------------------------------
 * `sanitize_file_name()` n'est pas réservé aux médias : c'est le passage obligé
 * de tout code qui fabrique un nom de fichier. Filtrer globalement casserait au
 * moins ACF Pro, qui nomme ses fichiers Local JSON par ce biais
 * (advanced-custom-fields-pro/includes/local-json.php:490) : `group_180c_about.json`
 * deviendrait `group-180c-about.json`, et `acf-json/` — versionné, et surveillé
 * par une garde de dérive dans le pipeline — partirait en doublons à la première
 * sauvegarde de groupe de champs.
 *
 * Même raisonnement pour les journaux WooCommerce (`woocommerce_payments-….log`,
 * dont le nom sert de clé de lookup), les exports CSV et les archives.
 *
 * D'où une **allow-list** plutôt qu'une deny-list : une extension inconnue
 * ressort intacte, au lieu d'être mutilée par défaut.
 *
 * `svg` en est volontairement absent : `_180c_render_svg_icon()`
 * (inc/helpers.php:176) résout un chemin disque via `sanitize_file_name()`. Les
 * 17 icônes actuelles sont conformes, mais inclure `svg` créerait un mode de
 * panne silencieux le jour où une icône arrive nommée `Mon_Icone.svg`.
 *
 * CE QUI N'EST PAS TOUCHÉ
 * -----------------------
 * `wp_unique_filename()` : la résolution des doublons (`-1`, `-2`) reste native.
 * Elle s'exécute APRÈS ce filtre, sur le nom déjà normalisé.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Longueur maximale de la base du nom de fichier, extension exclue.
 */
const _180C_MEDIA_FILENAME_MAX_LENGTH = 100;

/**
 * Extensions dont le nom de fichier est normalisé à l'upload.
 *
 * Volontairement restreinte aux formats déposés par la rédaction. Voir l'en-tête
 * du fichier pour le raisonnement, et notamment l'exclusion de `svg`.
 *
 * @return string[] Extensions en minuscules, sans point.
 */
function _180c_media_filename_extensions() {
	$extensions = array(
		// Images.
		'jpg',
		'jpeg',
		'png',
		'gif',
		'webp',
		'avif',
		'heic',
		'heif',
		// Vidéo.
		'mp4',
		'mov',
		'm4v',
		'webm',
		// Audio.
		'mp3',
		'wav',
		'm4a',
		'ogg',
		// Documents.
		'pdf',
	);

	/**
	 * Filtre la liste des extensions soumises à la slugification.
	 *
	 * Permet d'élargir ou de restreindre le périmètre sans redéployer de
	 * logique. Toute valeur ajoutée doit être en minuscules et sans point.
	 *
	 * @param string[] $extensions Extensions normalisées.
	 */
	return (array) apply_filters( '_180c_media_filename_extensions', $extensions );
}

/**
 * Réduit un nom de fichier à un slug `[a-z0-9-]` suivi de son extension.
 *
 * Fonction pure : aucune dépendance à l'état WordPress hormis `remove_accents()`,
 * et aucun effet de bord. C'est elle que couvre le harness
 * un test manuel (non versionné).
 *
 * Le traitement est idempotent : `f( f( $x ) ) === f( $x )`.
 *
 * @param string $filename Nom de fichier brut, extension comprise.
 * @return string Nom de fichier normalisé.
 */
function _180c_slugify_filename( $filename ) {
	$filename = (string) $filename;

	// 1. Séparer base et extension — l'extension est le dernier segment.
	$last_dot = strrpos( $filename, '.' );

	if ( false === $last_dot ) {
		$base      = $filename;
		$extension = '';
	} else {
		$base      = substr( $filename, 0, $last_dot );
		$extension = substr( $filename, $last_dot + 1 );
	}

	// 2. Extension : minuscules, conservée telle quelle.
	$extension = strtolower( $extension );

	/*
	 * 3. Caractères SUPPRIMÉS, sans tiret de remplacement : les remplacer
	 * produirait des tirets parasites là où l'œil n'attend aucune séparation
	 * (`Tarte (n°3)` doit donner `tarte-n3`, pas `tarte-n-3-`).
	 *
	 * Les apostrophes `'` et `’` sont ABSENTES de cette liste, à dessein : elles
	 * marquent une frontière de mot et doivent devenir un tiret à l'étape 6
	 * (`L'œuf` → `l-oeuf`). Seuls les guillemets sont supprimés.
	 */
	$a_supprimer = array(
		'©',
		'®',
		'™',
		'°',
		'“',
		'”',
		'«',
		'»',
		'"',
		'!',
		'?',
		':',
		';',
		',',
		'@',
		'#',
		'$',
		'%',
		'^',
		'*',
		'(',
		')',
		'[',
		']',
		'{',
		'}',
		'<',
		'>',
		'|',
		'~',
		'`',
		'=',
	);

	$base = str_replace( $a_supprimer, '', $base );

	/*
	 * 4. Translittération par le core : les lettres accentuées perdent leur
	 * diacritique, les ligatures sont décomposées (oe pour œ, ae pour æ) et la
	 * cédille disparaît.
	 */
	$base = remove_accents( $base );

	/*
	 * 5. Découpage CamelCase, en deux passes et dans cet ordre.
	 *
	 * La première traite les acronymes suivis d'un mot (`PDFSeptembre` →
	 * `PDF-Septembre`), la seconde les frontières ordinaires (`LesCahiers` →
	 * `Les-Cahiers`).
	 *
	 * Aucune des deux ne matche une transition chiffre → lettre : `180C` doit
	 * rester `180c` et non devenir `180-c`.
	 */
	$base = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1-$2', $base );
	$base = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $base );

	/*
	 * 6. Tout ce qui reste hors `[A-Za-z0-9]` devient un tiret : espaces, `_`,
	 * `.`, `+`, `&`, apostrophes, tirets cadratins, et les octets des séquences
	 * UTF-8 résiduelles.
	 *
	 * Motif volontairement sans `/u` : un nom de fichier peut porter un encodage
	 * invalide, sur lequel PCRE en mode Unicode renverrait `null` — donc un nom
	 * vide. Le `+` collapse au passage chaque séquence multi-octets en un seul
	 * tiret.
	 */
	$base = preg_replace( '/[^A-Za-z0-9]+/', '-', $base );

	// 7. Minuscules.
	$base = strtolower( $base );

	// 8. Tirets consécutifs collapsés, tirets de bordure retirés.
	$base = preg_replace( '/-+/', '-', $base );
	$base = trim( $base, '-' );

	// 9. Troncature, sans laisser de tiret final.
	if ( strlen( $base ) > _180C_MEDIA_FILENAME_MAX_LENGTH ) {
		$base = rtrim( substr( $base, 0, _180C_MEDIA_FILENAME_MAX_LENGTH ), '-' );
	}

	// 10. Repli : un nom composé uniquement de symboles se vide entièrement.
	if ( '' === $base ) {
		$base = 'fichier-' . gmdate( 'YmdHis' );
	}

	return '' === $extension ? $base : $base . '.' . $extension;
}

/**
 * Applique la slugification aux seuls noms de fichiers médias.
 *
 * Branchée sur `sanitize_file_name` plutôt que sur `wp_handle_upload_prefilter` :
 * c'est le seul point de passage commun à TOUS les chemins d'entrée —
 * médiathèque, uploader inline, API REST, WP-CLI, et `wp_upload_bits()` (utilisé
 * par les imports WXR), que le prefilter ne voit pas.
 *
 * Le travail se fait sur `$filename_raw` et non sur `$filename` : ce dernier a
 * déjà été amputé par le core (espaces devenus tirets, `..` collapsés), ce qui
 * fait perdre les frontières de mots dont dépend le découpage CamelCase.
 *
 * @param string $filename     Nom de fichier après traitement du core.
 * @param string $filename_raw Nom de fichier d'origine.
 * @return string Nom normalisé si l'extension est un média, `$filename` sinon.
 */
function _180c_filter_sanitize_file_name( $filename, $filename_raw ) {
	$raw      = (string) $filename_raw;
	$last_dot = strrpos( $raw, '.' );

	if ( false === $last_dot ) {
		return $filename;
	}

	$extension = strtolower( substr( $raw, $last_dot + 1 ) );

	if ( ! in_array( $extension, _180c_media_filename_extensions(), true ) ) {
		/*
		 * Hors périmètre média : rendre `$filename`, JAMAIS `$raw`. Rendre le
		 * brut annulerait la sanitization de sécurité du core (séquences de
		 * traversée, extensions intermédiaires suspectes, octet nul) sur les
		 * JSON, CSV, journaux et archives qui passent aussi par ici.
		 */
		return $filename;
	}

	$slug = _180c_slugify_filename( $raw );

	// Filet : ne jamais rendre un nom vide, quoi qu'il arrive en amont.
	return '' === $slug ? $filename : $slug;
}
add_filter( 'sanitize_file_name', '_180c_filter_sanitize_file_name', 10, 2 );
