<?php
/**
 * Page À propos (« Qui sommes-nous »).
 *
 * Helpers de la page À propos : résolution des auteurs sélectionnés dans les
 * sections « Auteurs » et nœud Schema.org AboutPage.
 *
 * Le template nommé `template-about.php` lit ces helpers via les template-parts
 * de `template-parts/about/`. Aucune donnée auteur n'est dupliquée : photo, nom,
 * bio et site internet proviennent exclusivement de la fiche auteur (+
 * meta WordPress core).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * L'utilisateur a-t-il au moins un contenu publié (article ou recette) ?
 *
 * Réutilise le helper auteur `_180c_author_has_content()` s'il existe ;
 * sinon calcule localement (articles + recettes) avec mise en cache transient
 * pour éviter deux `count_user_posts()` par auteur à chaque rendu.
 *
 * @param int $user_id ID utilisateur.
 * @return bool
 */
function _180c_about_user_has_content( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}

	if ( function_exists( '_180c_author_has_content' ) ) {
		return (bool) _180c_author_has_content( $user_id );
	}

	$key    = '_180c_about_uc_' . $user_id;
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return (bool) $cached;
	}

	$count = (int) count_user_posts( $user_id, 'post', true )
		+ (int) count_user_posts( $user_id, 'recipe', true );
	$has   = $count > 0;

	set_transient( $key, $has ? 1 : 0, HOUR_IN_SECONDS );

	return $has;
}

/**
 * Données d'affichage d'un auteur, lues exclusivement depuis sa fiche.
 *
 * Le nom n'est cliquable vers `/author/` que si l'auteur a au moins un contenu
 * publié (anti-lien-mort). La photo provient du champ ACF `author_photo`
 * (lu défensivement) ; placeholder si absente — jamais Gravatar.
 *
 * @param int $user_id ID utilisateur.
 * @return array{name:string,bio:string,website:string,photo_id:int,url:string,clickable:bool}|null
 */
function _180c_about_author( $user_id ) {
	$user_id = (int) $user_id;
	$user    = $user_id ? get_userdata( $user_id ) : null;
	if ( ! $user ) {
		return null;
	}

	$first = get_the_author_meta( 'first_name', $user_id );
	$last  = get_the_author_meta( 'last_name', $user_id );
	$name  = trim( $first . ' ' . $last );
	if ( '' === $name ) {
		$name = $user->display_name;
	}

	// Accesseur canonique du champ ACF `author_photo`;
	// renvoie 0 si ACF est absent ou la photo non renseignée.
	$photo_id = function_exists( '_180c_author_avatar_id' )
		? (int) _180c_author_avatar_id( $user_id )
		: 0;

	$has_content = _180c_about_user_has_content( $user_id );

	return array(
		'name'      => $name,
		'bio'       => get_the_author_meta( 'description', $user_id ),
		'website'   => esc_url_raw( get_the_author_meta( 'user_url', $user_id ) ),
		'photo_id'  => $photo_id,
		'url'       => $has_content ? get_author_posts_url( $user_id ) : '',
		'clickable' => $has_content,
	);
}

/**
 * Rend le portrait d'un auteur.
 *
 * `wp_get_attachment_image()` réserve width/height (anti-CLS), sert srcset +
 * AVIF/WebP du thème et n'applique aucun recadrage (ratio natif conservé).
 * Placeholder à ratio réservé si la photo est absente.
 *
 * @param int    $photo_id Attachment ID, ou 0.
 * @param string $alt      Texte alternatif (nom de l'auteur).
 * @return string HTML échappé.
 */
function _180c_about_portrait( $photo_id, $alt ) {
	if ( ! $photo_id ) {
		return '<span class="about-author__photo about-author__photo--placeholder" aria-hidden="true"></span>';
	}

	return wp_get_attachment_image(
		(int) $photo_id,
		'medium',
		false,
		array(
			'class'   => 'about-author__photo',
			'loading' => 'lazy',
			'alt'     => $alt,
		)
	);
}

/**
 * Ajoute un nœud AboutPage au @graph Schema.org de la page À propos.
 *
 * On NE duplique PAS Organization : le module schema.php émet déjà le nœud
 * `#organization` dans chaque @graph. AboutPage le référence via `publisher`,
 * ce qui conserve l'invariant « un seul bloc JSON-LD par page ». Les balises
 * title/description/canonical/robots/OG restent gérées par inc/seo/meta-tags.php.
 *
 * @param array $graph Structure { '@context', '@graph' }.
 * @return array
 */
function _180c_about_schema_graph( $graph ) {
	if ( ! is_page_template( 'template-about.php' ) || empty( $graph['@graph'] ) || ! is_array( $graph['@graph'] ) ) {
		return $graph;
	}

	$url = (string) get_permalink();

	$graph['@graph'][] = array(
		'@type'     => 'AboutPage',
		'@id'       => $url . '#aboutpage',
		'name'      => wp_strip_all_tags( get_the_title() ),
		'url'       => $url,
		'publisher' => array( '@id' => home_url( '/' ) . '#organization' ),
	);

	return $graph;
}

add_filter( '180c/schema_graph', '_180c_about_schema_graph' );
