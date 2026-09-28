<?php
/**
 * Schema.org ProfilePage + Person (JSON-LD) — page auteur.
 *
 * Source de vérité du nœud ProfilePage pour `/author/{nicename}/`. Le nœud
 * est injecté dans le @graph unique de la page par inc/seo/schema.php
 * (wp_head, priorité 5) via le hook `180c/schema_graph`.
 *
 * `_180c_render_author_jsonld()` est fourni pour un rendu autonome
 * éventuel mais N'EST PAS appelé par le template — éviter la duplication.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Construit le nœud Schema.org ProfilePage + Person pour un auteur donné.
 *
 * Conforme aux Rich Results « ProfilePage » de Google :
 * `mainEntity` → Person (name, url, image, description, jobTitle, sameAs).
 * `dateModified` = date du post le plus récent de l'auteur (post ou recette).
 *
 * @param int $author_id ID de l'auteur.
 * @return array|null Tableau prêt pour wp_json_encode, ou null si introuvable.
 */
function _180c_build_author_schema_node( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return null;
	}

	$user = get_userdata( $author_id );
	if ( ! $user ) {
		return null;
	}

	$author_url = get_author_posts_url( $author_id );
	$home       = home_url( '/' );

	// Person — entité principale.
	$person = array(
		'@type' => 'Person',
		'@id'   => $author_url . '#person',
		'name'  => _180c_author_display_name( $author_id ),
		'url'   => $author_url,
	);

	// Image : uniquement si une vraie photo ACF est présente.
	// On n'émet jamais une URL Gravatar par défaut (silhouette grise)
	// dans le JSON-LD : mieux vaut omettre la clé que pousser un avatar
	// vide vers Google.
	if ( _180c_author_has_real_avatar( $author_id ) ) {
		$photo_url = _180c_author_avatar_url( $author_id, 400 );
		if ( '' !== $photo_url ) {
			$person['image'] = $photo_url;
		}
	}

	// Description = bio (description user meta), nettoyée.
	$bio = trim( (string) get_the_author_meta( 'description', $author_id ) );
	if ( '' !== $bio ) {
		$person['description'] = wp_strip_all_tags( $bio );
	}

	// jobTitle = fonction éditoriale (champ ACF).
	$role = _180c_author_role( $author_id );
	if ( '' !== $role ) {
		$person['jobTitle'] = $role;
	}

	// sameAs = URLs des réseaux sociaux.
	$socials = _180c_author_socials( $author_id );
	if ( ! empty( $socials ) ) {
		$person['sameAs'] = array_values( wp_list_pluck( $socials, 'url' ) );
	}

	// ProfilePage — wrapper.
	$node = array(
		'@type'      => 'ProfilePage',
		'@id'        => $author_url . '#profile',
		'url'        => $author_url,
		'mainEntity' => $person,
		'publisher'  => array( '@id' => $home . '#organization' ),
	);

	// dateModified = post le plus récent de l'auteur (post ou recipe).
	$last_modified = _180c_author_last_modified_iso( $author_id );
	if ( '' !== $last_modified ) {
		$node['dateModified'] = $last_modified;
	}

	return $node;
}

/**
 * Retourne la date ISO 8601 du contenu le plus récent de l'auteur.
 *
 * Cherche le post (CPT `post` ou `recipe`) le plus récemment modifié pour
 * cet auteur. Utilisé pour `ProfilePage.dateModified`. Retourne '' si
 * l'auteur n'a aucun contenu publié.
 *
 * @param int $author_id ID de l'auteur.
 * @return string Date ISO 8601 (ex. '2026-05-28T14:32:00+00:00') ou ''.
 */
function _180c_author_last_modified_iso( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return '';
	}

	$last_query = new WP_Query(
		array(
			'post_type'           => array( 'post', 'recipe' ),
			'author'              => $author_id,
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'fields'              => 'ids',
		)
	);

	if ( ! $last_query->have_posts() ) {
		return '';
	}

	$post_id = (int) $last_query->posts[0];
	return (string) get_the_modified_date( 'c', $post_id );
}

/**
 * Rend un bloc <script type="application/ld+json"> autonome pour l'auteur.
 *
 * Fourni par parité avec le cahier des charges. NON appelé par author.php : le
 * nœud ProfilePage est injecté dans le @graph global via inc/seo/schema.php.
 * À n'utiliser que dans un contexte sans graphe global.
 *
 * @param int|null $author_id ID de l'auteur (par défaut : courant via get_queried_object_id).
 * @return string HTML du script JSON-LD, ou chaîne vide.
 */
function _180c_render_author_jsonld( $author_id = null ) {
	$author_id = $author_id ? (int) $author_id : (int) get_queried_object_id();
	$node      = _180c_build_author_schema_node( $author_id );
	if ( ! $node ) {
		return '';
	}

	$payload = array_merge( array( '@context' => 'https://schema.org' ), $node );
	$json    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	if ( false === $json ) {
		return '';
	}

	return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n";
}
