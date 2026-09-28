<?php
/**
 * Import des archives de la revue papier — meta d'articles inscriptibles par REST.
 *
 * Le skill `archives-revue` (workspace `~/dev/180/import-reportages/`) crée les
 * reportages de la revue en brouillons `post` par l'API REST native, avec un
 * compte Éditeur et un mot de passe d'application. Ce fichier ouvre à l'écriture
 * REST les seules meta dont il a besoin, et UNIQUEMENT sur le type `post`.
 *
 * POURQUOI PAS `show_in_rest` SUR LES GROUPES ACF
 * -----------------------------------------------
 * Le groupe SEO (`group_content_seo`) est aussi rattaché à `recipe`, `page` et
 * `product`. L'activer en REST ferait passer la clé `acf` de `[]` à un objet
 * dans les réponses `wp/v2/recipe` que consomment les apps : un décodeur iOS
 * typé tableau casserait. `register_post_meta()` sur `post` seul ne touche à
 * aucune autre réponse.
 *
 * CLÉS DE RÉFÉRENCE ACF
 * ---------------------
 * ACF lit une valeur par sa clé de champ, stockée dans la meta sœur préfixée
 * d'un tiret bas (`_seo_title` → `field_seo_title`). Une valeur écrite sans
 * cette référence rend NULL à `get_field()`. Elle est donc posée côté serveur
 * après chaque écriture REST de l'une de ces meta.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/** Meta d'idempotence : « <n° de revue>/<nom du PDF> ». */
const _180C_ARCHIVE_SOURCE_META = '_180c_archive_source';

/**
 * Champs ACF des articles ouverts à l'écriture REST, avec leur clé de champ.
 *
 * @return array<string,string> Nom de meta => clé de champ ACF.
 */
function _180c_archives_acf_field_keys(): array {
	return array(
		'seo_title'       => 'field_seo_title',
		'seo_description' => 'field_seo_description',
		'related_product' => 'field_article_related_product',
	);
}

/**
 * Autorisation d'écriture des meta d'import : comptes `edit_others_posts`.
 *
 * Signature du filtre `auth_post_meta_{$meta_key}_for_post`. La valeur par
 * défaut `$allowed` est ignorée : elle vaut `false` pour une meta protégée
 * (préfixe `_`), ce qui fermerait `_180c_archive_source` à tout le monde.
 *
 * @param bool   $allowed   Autorisation par défaut (ignorée).
 * @param string $meta_key  Clé de meta.
 * @param int    $object_id ID du post.
 * @param int    $user_id   ID de l'utilisateur.
 * @return bool
 */
function _180c_archives_meta_auth( $allowed, $meta_key, $object_id, $user_id ): bool {
	unset( $allowed, $meta_key );

	return user_can( (int) $user_id, 'edit_others_posts' )
		&& user_can( (int) $user_id, 'edit_post', (int) $object_id );
}

/**
 * Enregistre les meta d'import sur le type `post`.
 *
 * @return void
 */
function _180c_archives_register_meta(): void {
	$common = array(
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => '_180c_archives_meta_auth',
	);

	register_post_meta(
		'post',
		_180C_ARCHIVE_SOURCE_META,
		$common + array(
			'type'              => 'string',
			'description'       => __( 'Source d\'import des archives de la revue (n° + PDF).', '180c' ),
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_post_meta(
		'post',
		'seo_title',
		$common + array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_post_meta(
		'post',
		'seo_description',
		$common + array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
		)
	);

	register_post_meta(
		'post',
		'related_product',
		$common + array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		)
	);
}
add_action( 'init', '_180c_archives_register_meta', 11 );

/**
 * Pose les clés de référence ACF des champs écrits par REST.
 *
 * `rest_after_insert_post` passe après l'écriture des meta par le contrôleur :
 * seules les meta présentes dans la requête sont traitées.
 *
 * @param WP_Post         $post    Article créé ou mis à jour.
 * @param WP_REST_Request $request Requête REST.
 * @return void
 */
function _180c_archives_sync_acf_references( $post, $request ): void {
	$meta = $request->get_param( 'meta' );

	if ( ! is_array( $meta ) ) {
		return;
	}

	foreach ( _180c_archives_acf_field_keys() as $name => $field_key ) {
		if ( array_key_exists( $name, $meta ) ) {
			update_post_meta( $post->ID, '_' . $name, $field_key );
		}
	}
}
add_action( 'rest_after_insert_post', '_180c_archives_sync_acf_references', 10, 2 );
