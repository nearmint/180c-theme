<?php
/**
 * Pipeline images — sortie next-gen (WebP/AVIF) des sous-tailles générées.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Format de sortie next-gen pour les sous-tailles générées à l'upload.
 *
 * Mappe les sources JPEG/PNG vers AVIF lorsque l'éditeur GD du serveur sait
 * l'encoder (function_exists('imageavif') + WP >= 6.5), sinon WebP. Le test est
 * fait AU RUNTIME : en production, si GD n'a pas l'AVIF, repli WebP automatique — aucune
 * configuration à changer.
 *
 * Portée : seules les SOUS-TAILLES générées passent dans ce format. Les fichiers
 * ORIGINAUX uploadés ne sont pas convertis, et les images déjà en base restent
 * inchangées jusqu'à un `wp media regenerate` (opération opérateur, hors session).
 *
 * WebP/AVIF préservent la transparence → pas de régression sur les PNG à canal
 * alpha. Les visuels d'UI du thème (icônes, badges) sont en SVG, non concernés.
 *
 * @param array $formats Map MIME source => MIME sortie.
 * @return array
 */
function _180c_image_output_format( $formats ) {
	// WebP uniquement. L'encodeur AVIF de GD est instable et produit des stubs
	// 16 octets sur certains cas (noms à caractères spéciaux, JPEG CMYK,
	// dimensions extrêmes). Gain marginal vs WebP pour un risque élevé.
	$formats['image/jpeg'] = 'image/webp';
	$formats['image/png']  = 'image/webp';

	return $formats;
}
add_filter( 'image_editor_output_format', '_180c_image_output_format' );

/**
 * Qualité d'encodage des sous-tailles WebP/AVIF (≈ 82) — compromis poids/rendu.
 *
 * @param int    $quality   Qualité par défaut de l'éditeur.
 * @param string $mime_type MIME de sortie.
 * @return int
 */
function _180c_image_output_quality( $quality, $mime_type ) {
	if ( in_array( $mime_type, array( 'image/webp', 'image/avif' ), true ) ) {
		return 82;
	}
	return $quality;
}
add_filter( 'wp_editor_set_quality', '_180c_image_output_quality', 10, 2 );

/**
 * Nom de fichier d'une URL/chemin, sans la query string ni l'extension.
 *
 * @param string $path URL ou chemin relatif d'un fichier média.
 * @return string Nom de base sans extension ('' si indéterminable).
 */
function _180c_media_basename_no_ext( $path ) {
	$path = strtok( (string) $path, '?' );
	if ( ! is_string( $path ) || '' === $path ) {
		return '';
	}
	$base = wp_basename( $path );
	$dot  = strrpos( $base, '.' );

	return ( false === $dot ) ? $base : substr( $base, 0, $dot );
}

/**
 * Dimensions réelles d'une source image, en tolérant un changement d'extension.
 *
 * Reproduit la logique de `wp_image_src_get_dimensions()` du cœur, à une
 * différence près : la comparaison ignore l'extension du fichier. C'est ce qui
 * permet de retrouver les dimensions quand la métadonnée pointe un `.webp` et
 * le corps de l'article un `.jpg` (voir `_180c_content_add_image_dimensions()`).
 *
 * Aucune approximation : on ne renvoie des dimensions que si le nom de base
 * correspond exactement à la taille pleine ou à l'une des sous-tailles connues.
 * Le suffixe `-800x600` du nom de fichier n'est délibérément pas exploité — il
 * n'est pas une source fiable pour un fichier d'origine.
 *
 * @param string $src           Attribut src de la balise (URL absolue ou relative).
 * @param int    $attachment_id ID du média.
 * @return array{0:int,1:int}|false Couple largeur/hauteur, ou false.
 */
function _180c_media_dimensions_for_src( $src, $attachment_id ) {
	$needle = _180c_media_basename_no_ext( $src );
	if ( '' === $needle ) {
		return false;
	}

	$meta = wp_get_attachment_metadata( (int) $attachment_id );
	if ( ! is_array( $meta ) || empty( $meta['file'] ) ) {
		return false;
	}

	// Taille pleine.
	if ( _180c_media_basename_no_ext( $meta['file'] ) === $needle ) {
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			return array( (int) $meta['width'], (int) $meta['height'] );
		}
		return false;
	}

	// Sous-tailles générées.
	if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size ) {
			if ( empty( $size['file'] ) || _180c_media_basename_no_ext( $size['file'] ) !== $needle ) {
				continue;
			}
			if ( ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
				return array( (int) $size['width'], (int) $size['height'] );
			}
			return false;
		}
	}

	return false;
}

/**
 * Ajoute width/height aux images de contenu que le cœur laisse sans dimensions.
 *
 * ITEM 9 / CWV — traite l'opportunité Lighthouse `unsized-images`, en échec sur
 * le gabarit article (8 images sur 20 le 2026-07-31, mobile ET desktop).
 *
 * CAUSE — `_180c_image_output_format()` ci-dessus encode les sous-tailles en
 * WebP. Sur les médias régénérés depuis, `$meta['file']` pointe désormais un
 * `.webp` alors que le corps de l'article référence toujours le `.jpg` d'origine
 * (conservé dans `$meta['original_image']`). `wp_image_src_get_dimensions()`
 * compare les noms de fichiers **extension comprise** : plus aucune
 * correspondance, donc le cœur n'injecte ni width/height, ni srcset, ni
 * `loading` — `wp_get_loading_optimization_attributes()` exige des dimensions.
 * Résultat : des originaux pleine taille chargés en eager, sans réservation de
 * place. 787 images du corpus sont dans ce cas (mesure locale du 2026-07-31).
 *
 * CORRECTIF — on repasse avant `wp_filter_content_tags()` (priorité 12) pour
 * poser les dimensions manquantes. Le cœur voit ensuite des images dimensionnées
 * et applique normalement son arbitrage `loading` / `fetchpriority`.
 *
 * Le srcset reste absent sur ces images : le rétablir suppose de réécrire la
 * source vers le fichier WebP, ce qui dépasse le périmètre de l'ITEM 9.
 *
 * Prudence : on n'agit que si les DEUX attributs manquent (même règle que le
 * cœur) et si le média est identifiable via sa classe `wp-image-<ID>`.
 *
 * @param string $content Contenu de l'article.
 * @return string
 */
function _180c_content_add_image_dimensions( $content ) {
	if ( ! is_string( $content ) || false === strpos( $content, '<img' ) ) {
		return $content;
	}

	$filtered = preg_replace_callback( '/<img\s[^>]+>/i', '_180c_img_tag_add_dimensions', $content );

	// preg_replace_callback renvoie null en cas d'échec (backtrack limit) :
	// on rend alors le contenu d'origine plutôt qu'une page vide.
	return ( null === $filtered ) ? $content : $filtered;
}
add_filter( 'the_content', '_180c_content_add_image_dimensions', 11 );

/**
 * Callback de `_180c_content_add_image_dimensions()` — une balise `<img>`.
 *
 * @param array $matches Captures de preg_replace_callback ($matches[0] = balise).
 * @return string Balise inchangée, ou enrichie de width/height.
 */
function _180c_img_tag_add_dimensions( $matches ) {
	$tag = $matches[0];

	// Même conservatisme que le cœur : on ne touche qu'aux images sans AUCUNE
	// dimension. Compléter une dimension isolée risquerait de fausser le ratio.
	if ( preg_match( '/\s(?:width|height)\s*=/i', $tag ) ) {
		return $tag;
	}

	if ( ! preg_match( '/\sclass\s*=\s*(["\'])(.*?)\1/is', $tag, $class_match ) ) {
		return $tag;
	}
	if ( ! preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', $class_match[2], $id_match ) ) {
		return $tag;
	}
	if ( ! preg_match( '/\ssrc\s*=\s*(["\'])(.*?)\1/is', $tag, $src_match ) ) {
		return $tag;
	}

	$dimensions = _180c_media_dimensions_for_src( $src_match[2], (int) $id_match[1] );
	if ( ! $dimensions ) {
		return $tag;
	}

	return preg_replace(
		'/^<img/i',
		sprintf( '<img width="%d" height="%d"', $dimensions[0], $dimensions[1] ),
		$tag,
		1
	);
}
