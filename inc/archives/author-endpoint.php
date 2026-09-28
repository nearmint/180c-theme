<?php
/**
 * Import des archives de la revue papier — résolution des auteurs.
 *
 * Endpoint : POST /wp-json/180c/v1/archives/author
 * Entrée   : `name` — nom affiché tel que signé dans le PDF (« Michel Smith »).
 * Auth     : compte `edit_others_posts` (Éditeur d'import, mot de passe
 *            d'application).
 *
 * Réponses :
 *   200 { user_id, created: false, display_name, roles }  compte existant ;
 *   201 { user_id, created: true,  display_name, roles }  compte créé ;
 *   409 { code: archives_author_ambiguous, data: { candidates: [...] } }.
 *
 * POURQUOI UN ENDPOINT
 * --------------------
 * Un Éditeur n'a pas `list_users` : `wp/v2/users` ne lui montre que les auteurs
 * ayant déjà publié, et la plupart des contributeurs de la revue n'ont rien
 * publié sur le site. La recherche se fait donc côté serveur, parmi les seuls
 * comptes qui ont la capacité `edit_posts` — un client ou un abonné homonyme
 * est ignoré. Le rôle d'un compte trouvé n'est jamais modifié.
 *
 * CRÉATION
 * --------
 * Rôle `author` forcé, mot de passe aléatoire jamais communiqué, e-mail
 * `<slug>@auteurs.180c.invalid` (TLD réservé : aucun envoi ne peut aboutir).
 * `wp_insert_user()` n'envoie aucune notification par lui-même. L'adhésion
 * gratuite que WooCommerce Memberships accorde sur `user_register` est
 * suspendue le temps de l'insertion : un auteur n'est pas un client.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/** Domaine des e-mails des auteurs créés par l'import. */
const _180C_ARCHIVES_AUTHOR_EMAIL_DOMAIN = 'auteurs.180c.invalid';

/** Meta de traçabilité posée sur les comptes créés par l'import. */
const _180C_ARCHIVES_AUTHOR_META = '_180c_archive_author';

/**
 * Normalise un nom pour comparaison : accents, casse, apostrophes, espaces.
 *
 * @param string $name Nom brut.
 * @return string Nom normalisé, ou chaîne vide.
 */
function _180c_archives_normalize_name( string $name ): string {
	$name = str_replace( array( '’', '‘', 'ʼ', '`' ), "'", $name );
	$name = remove_accents( wp_strip_all_tags( $name ) );
	$name = strtolower( $name );
	$name = preg_replace( '/\s+/u', ' ', $name );

	return trim( (string) $name );
}

/**
 * Cherche les comptes rédactionnels dont le nom correspond.
 *
 * Seuls les comptes dotés de `edit_posts` sont candidats : les rôles qui
 * portent cette capacité servent de pré-filtre SQL, puis chaque compte est
 * revérifié par `user_can()` (une capacité retirée individuellement l'exclut).
 * Correspondance sur `display_name` normalisé, ou sur `user_nicename` égal au
 * slug du nom. La comparaison se fait en PHP : elle ne peut pas dépendre de la
 * collation MySQL de chaque environnement.
 *
 * @param string $name Nom affiché recherché.
 * @return int[] IDs des comptes correspondants, triés.
 */
function _180c_archives_find_authors( string $name ): array {
	$roles = array();
	foreach ( wp_roles()->roles as $role_key => $role ) {
		if ( ! empty( $role['capabilities']['edit_posts'] ) ) {
			$roles[] = $role_key;
		}
	}

	if ( empty( $roles ) ) {
		return array();
	}

	$needle = _180c_archives_normalize_name( $name );
	$slug   = sanitize_title( $name );
	$users  = get_users(
		array(
			'role__in' => $roles,
			'fields'   => array( 'ID', 'display_name', 'user_nicename' ),
		)
	);

	$ids = array();
	foreach ( $users as $user ) {
		$matches = _180c_archives_normalize_name( (string) $user->display_name ) === $needle
			|| ( '' !== $slug && $user->user_nicename === $slug );

		if ( $matches && user_can( (int) $user->ID, 'edit_posts' ) ) {
			$ids[] = (int) $user->ID;
		}
	}

	sort( $ids );

	return array_values( array_unique( $ids ) );
}

/**
 * Résumé public d'un compte pour la réponse REST.
 *
 * @param int $user_id ID du compte.
 * @return array{user_id:int,display_name:string,roles:string[]}
 */
function _180c_archives_author_summary( int $user_id ): array {
	$user = get_userdata( $user_id );

	return array(
		'user_id'      => $user_id,
		'display_name' => $user ? (string) $user->display_name : '',
		'roles'        => $user ? array_values( (array) $user->roles ) : array(),
	);
}

/**
 * Crée un compte auteur pour une signature de la revue.
 *
 * @param string $name Nom affiché.
 * @return int|WP_Error ID du compte créé.
 */
function _180c_archives_create_author( string $name ) {
	$slug = sanitize_title( $name );
	if ( '' === $slug ) {
		return new WP_Error( 'archives_author_invalid', __( 'Nom d\'auteur inexploitable.', '180c' ), array( 'status' => 400 ) );
	}

	$login  = $slug;
	$suffix = 2;
	while ( username_exists( $login ) || email_exists( $login . '@' . _180C_ARCHIVES_AUTHOR_EMAIL_DOMAIN ) ) {
		$login = $slug . '-' . $suffix;
		++$suffix;
	}

	$parts = explode( ' ', $name, 2 );

	$memberships = function_exists( 'wc_memberships' ) ? wc_memberships()->get_plans_instance() : null;
	$priority    = $memberships ? has_action( 'user_register', array( $memberships, 'grant_access_to_free_membership' ) ) : false;
	if ( false !== $priority ) {
		remove_action( 'user_register', array( $memberships, 'grant_access_to_free_membership' ), $priority );
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $login . '@' . _180C_ARCHIVES_AUTHOR_EMAIL_DOMAIN,
			'user_pass'    => wp_generate_password( 32, true, true ),
			'display_name' => $name,
			'nickname'     => $name,
			'first_name'   => $parts[0],
			'last_name'    => $parts[1] ?? '',
			'role'         => 'author',
		)
	);

	if ( false !== $priority ) {
		add_action( 'user_register', array( $memberships, 'grant_access_to_free_membership' ), $priority, 2 );
	}

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	update_user_meta( $user_id, _180C_ARCHIVES_AUTHOR_META, 1 );

	return (int) $user_id;
}

/**
 * Callback REST : résout ou crée l'auteur d'un reportage.
 *
 * @param WP_REST_Request $request Requête.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_archives_author( WP_REST_Request $request ) {
	$name = trim( (string) preg_replace( '/\s+/u', ' ', (string) $request->get_param( 'name' ) ) );

	if ( '' === _180c_archives_normalize_name( $name ) ) {
		return new WP_Error( 'archives_author_invalid', __( 'Nom d\'auteur vide.', '180c' ), array( 'status' => 400 ) );
	}

	$ids = _180c_archives_find_authors( $name );

	if ( count( $ids ) > 1 ) {
		return new WP_Error(
			'archives_author_ambiguous',
			__( 'Plusieurs comptes correspondent à ce nom.', '180c' ),
			array(
				'status'     => 409,
				'candidates' => array_map( '_180c_archives_author_summary', $ids ),
			)
		);
	}

	if ( 1 === count( $ids ) ) {
		return new WP_REST_Response( array( 'created' => false ) + _180c_archives_author_summary( $ids[0] ), 200 );
	}

	$user_id = _180c_archives_create_author( $name );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	return new WP_REST_Response( array( 'created' => true ) + _180c_archives_author_summary( $user_id ), 201 );
}

/**
 * Enregistre la route `180c/v1/archives/author`.
 *
 * @return void
 */
function _180c_rest_register_archives_author(): void {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/archives/author',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_rest_archives_author',
			'permission_callback' => static function () {
				return current_user_can( 'edit_others_posts' );
			},
			'args'                => array(
				'name' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', '_180c_rest_register_archives_author' );
