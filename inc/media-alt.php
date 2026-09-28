<?php
/**
 * Textes alternatifs des médias — helper + filet de sécurité runtime.
 *
 * Remplace `inc/accessibility.php`, dont le repli d'`alt` fabriquait exactement
 * ce qu'il faut éviter : des textes alternatifs dérivés de noms de fichiers.
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * Une majorité des images de la médiathèque n'a aucun `_wp_attachment_image_alt`
 * renseigné (mesure locale du 2026-08-03). Sans repli, autant d'images liées se
 * retrouvent sans nom accessible — violation axe `link-name` sur les vignettes
 * cliquables, et perte sèche pour l'indexation Google Images.
 *
 * CE QUI A CHANGÉ PAR RAPPORT À L'ANCIEN REPLI
 * -------------------------------------------
 * L'ancienne cascade était `post_title` PUIS `post_excerpt`. Or le `post_title`
 * d'une pièce jointe WordPress est **dérivé du nom de fichier à l'upload** : le
 * thème rendait donc, en production, des `alt="Sans titre-6"`,
 * `alt="Photo-oeuf-croustillant-1"` ou `alt="Frame 52"`. Un `alt` de remplissage
 * est pire qu'un `alt` vide : il occupe la place du vrai libellé et il est lu tel
 * quel par un lecteur d'écran.
 *
 * La cascade est désormais :
 *   1. `_wp_attachment_image_alt` — la seule source rédigée pour cet usage ;
 *   2. la légende (`post_excerpt`) — rédigée par un humain, pour l'affichage ;
 *   3. le titre du post parent — décrit le contexte, jamais le fichier ;
 *   4. le repli explicite passé par l'appelant ;
 *   5. chaîne vide.
 *
 * Le `post_title` de la pièce jointe est **volontairement absent** de la cascade
 * runtime. Il n'est exploité que par le script de backfill
 * (script de rattrapage ponctuel, non versionné), qui le soumet à un détecteur de nom de fichier et
 * le classe en confiance `medium`, jamais écrit sans relecture humaine.
 *
 * PORTÉE
 * ------
 * Front-office uniquement : en admin, un `alt` vide doit rester visible pour
 * inciter la rédaction à le renseigner.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indique si une chaîne ressemble à un nom de fichier plutôt qu'à une légende.
 *
 * Sert de garde-fou partout où un `post_title` de pièce jointe est envisagé comme
 * source d'`alt` : le titre auto-généré par WordPress est le nom de fichier privé
 * de son extension et de ses tirets, ce qui donne des chaînes syntaxiquement
 * valides mais sémantiquement vides.
 *
 * Sont rejetés :
 *  - une extension résiduelle (`… .jpg`, `….webp`) ;
 *  - les motifs d'appareil photo et d'export (`IMG_1234`, `DSC0042`, `P1010101`,
 *    `Capture d'écran …`, `Sans titre-6`, `Frame 52`, `Photo 3`) ;
 *  - une suite de 4 chiffres ou plus, qui trahit un horodatage ou un compteur ;
 *  - les chaînes majoritairement composées de chiffres, de tirets et de
 *    soulignés ;
 *  - les chaînes de moins de trois caractères ou sans la moindre voyelle,
 *    qui ne peuvent pas former un mot.
 *
 * @param string $value Chaîne candidate.
 * @return bool True si la chaîne ressemble à un nom de fichier.
 */
function _180c_alt_looks_like_filename( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value || mb_strlen( $value ) < 3 ) {
		return true;
	}

	// Extension résiduelle.
	if ( preg_match( '/\.(jpe?g|png|gif|webp|avif|tiff?|bmp|heic|svg)$/i', $value ) ) {
		return true;
	}

	// Motifs d'appareil / d'export / de placeholder.
	$patterns = array(
		'/^(img|dsc|dscn|dcim|p|pxl|mvimg|photo|image|screenshot|capture)[\s_-]*\d+/i',
		'/^(sans[\s_-]*titre|untitled|no[\s_-]*name|frame|group|rectangle|ellipse|layer|calque)\b/i',
		'/^capture\s+d[’\']écran/iu',
	);
	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $value ) ) {
			return true;
		}
	}

	// Horodatage ou compteur : 4 chiffres consécutifs ou plus.
	if ( preg_match( '/\d{4,}/', $value ) ) {
		return true;
	}

	// Majoritairement non alphabétique (chiffres, tirets, soulignés).
	$letters = preg_match_all( '/\p{L}/u', $value );
	if ( $letters < 3 || $letters < ( mb_strlen( $value ) / 2 ) ) {
		return true;
	}

	// Aucune voyelle : ce n'est pas un mot d'une langue latine.
	if ( ! preg_match( '/[aeiouyàâäéèêëîïôöùûüœ]/iu', $value ) ) {
		return true;
	}

	return false;
}

/**
 * Texte alternatif d'une pièce jointe, selon la cascade documentée en tête.
 *
 * Aucune valeur n'est jamais dérivée du nom de fichier : voir
 * `_180c_alt_looks_like_filename()` et le commentaire de tête.
 *
 * @param int    $attachment_id ID de la pièce jointe.
 * @param string $fallback      Repli explicite de l'appelant (avant-dernier recours).
 * @return string Texte alternatif, éventuellement vide.
 */
function _180c_img_alt( int $attachment_id, string $fallback = '' ): string {
	if ( $attachment_id <= 0 ) {
		return trim( $fallback );
	}

	// 1. Le champ dédié.
	$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	if ( '' !== $alt ) {
		return $alt;
	}

	$attachment = get_post( $attachment_id );
	if ( $attachment instanceof WP_Post ) {
		// 2. La légende — rédigée par un humain pour être lue.
		$caption = trim( (string) $attachment->post_excerpt );
		if ( '' !== $caption ) {
			return $caption;
		}

		// 3. Le titre du post parent : décrit le contexte d'usage de l'image.
		$parent_id = (int) $attachment->post_parent;
		if ( $parent_id > 0 ) {
			$parent_title = trim( (string) get_the_title( $parent_id ) );
			if ( '' !== $parent_title ) {
				return $parent_title;
			}
		}
	}

	// 4. Repli de l'appelant, puis 5. chaîne vide.
	return trim( $fallback );
}

/**
 * Indique qu'un jeu d'attributs marque l'image comme purement décorative.
 *
 * Trois marquages sont reconnus, tous équivalents pour un lecteur d'écran :
 * `role="presentation"` (ou `role="none"`), `aria-hidden="true"` et l'attribut
 * maison `data-decorative="1"`. Une image ainsi marquée conserve son `alt=""` :
 * lui inventer un libellé la réintroduirait dans l'arbre d'accessibilité, à
 * rebours de l'intention du gabarit.
 *
 * @param array $attr Attributs HTML de l'image.
 * @return bool
 */
function _180c_img_attr_is_decorative( array $attr ) {
	$role = isset( $attr['role'] ) ? strtolower( trim( (string) $attr['role'] ) ) : '';
	if ( 'presentation' === $role || 'none' === $role ) {
		return true;
	}

	if ( isset( $attr['aria-hidden'] ) && 'true' === strtolower( trim( (string) $attr['aria-hidden'] ) ) ) {
		return true;
	}

	if ( isset( $attr['data-decorative'] ) && '' !== trim( (string) $attr['data-decorative'] ) && '0' !== trim( (string) $attr['data-decorative'] ) ) {
		return true;
	}

	return false;
}

/**
 * Repli d'`alt` pour les images rendues par `wp_get_attachment_image()`.
 *
 * Filtre conservateur : il ne remplit un `alt` que s'il est vide, si l'image
 * n'est pas marquée décorative, et si la pièce jointe est **rattachée à un
 * post parent**. Cette dernière garde est ce qui distingue une image de contenu
 * (illustration d'un article, visuel produit) d'un média orphelin de la
 * médiathèque, dont on ne sait rien du contexte d'usage.
 *
 * Rien n'est écrit en base : le repli vit le temps du rendu.
 *
 * @param array        $attr       Attributs HTML de l'image.
 * @param WP_Post|null $attachment Pièce jointe source.
 * @return array Attributs, avec `alt` de repli le cas échéant.
 */
function _180c_a11y_fallback_image_alt( $attr, $attachment ) {
	if ( is_admin() ) {
		return $attr;
	}

	if ( isset( $attr['alt'] ) && '' !== trim( (string) $attr['alt'] ) ) {
		return $attr;
	}

	if ( ! ( $attachment instanceof WP_Post ) ) {
		return $attr;
	}

	if ( _180c_img_attr_is_decorative( (array) $attr ) ) {
		return $attr;
	}

	// Média orphelin : aucun contexte exploitable, on n'invente rien.
	if ( (int) $attachment->post_parent <= 0 ) {
		return $attr;
	}

	$fallback = _180c_img_alt( (int) $attachment->ID );
	if ( '' !== $fallback ) {
		$attr['alt'] = $fallback;
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', '_180c_a11y_fallback_image_alt', 10, 2 );
