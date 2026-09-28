<?php
/**
 * Permission callbacks REST (JWT) — Simple JWT Login compatible.
 *
 * Deux usages du même Bearer token (HS256, secret SIMPLE_JWT_LOGIN_SECRET) :
 *  1. Permission callbacks sur les routes 180c/v1/* (erreurs 401 explicites).
 *  2. Authentification globale via `determine_current_user` : un Bearer valide
 *     définit l'utilisateur courant pour TOUTES les requêtes (y compris les
 *     routes natives wp/v2/*), ce qui permet le gating serveur des recettes.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Décode un segment base64url en chaîne binaire.
 *
 * @param string $data Segment base64url sans padding.
 * @return string Données binaires décodées.
 */
function _180c_jwt_base64url_decode( $data ) {
	$remainder = strlen( $data ) % 4;
	if ( $remainder ) {
		$data .= str_repeat( '=', 4 - $remainder );
	}
	return base64_decode( strtr( $data, '-_', '+/' ) );
}

/**
 * Valide un Bearer token (signature HS256 + expiration) et renvoie son payload.
 *
 * ┌─ VALIDATEUR JWT UNIQUE DU THÈME ────────────────────────────────────────┐
 * │ Ceci est la SEULE implémentation de vérification de signature JWT du     │
 * │ thème. NE JAMAIS réimplémenter la vérification HS256 ailleurs (hash_hmac,│
 * │ décodage base64url, comparaison de signature) : toute route app-facing   │
 * │ doit passer par les helpers de ce fichier —                              │
 * │ `_180c_rest_jwt_or_cookie[_user]()` (session d'abord, RECOMMANDÉ) ou,     │
 * │ à défaut, `_180c_rest_jwt_user()`. Historique : LOT G — unification de la │
 * │ validation JWT (le pipeline newsletter forçait une revérification contre  │
 * │ la constante et échouait en prod ; cf. commit fix(auth)).                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * Cœur de validation partagé entre le permission callback et l'auth globale.
 * Le secret provient EXCLUSIVEMENT de la constante `SIMPLE_JWT_LOGIN_SECRET`
 * (wp-config), qui DOIT rester byte-identique à la decryption key du plugin
 * Simple JWT Login (sinon désalignement → `jwt_signature`, cf. LOT G).
 *
 * @param string $token Token JWT brut (sans le préfixe « Bearer »).
 * @return array|WP_Error Payload décodé, ou WP_Error explicite.
 */
function _180c_jwt_validate_token( $token ) {
	$token = trim( (string) $token );

	if ( '' === $token ) {
		return new WP_Error( 'jwt_invalid', __( 'Token JWT invalide', '180c' ), array( 'status' => 401 ) );
	}

	if ( ! defined( 'SIMPLE_JWT_LOGIN_SECRET' ) ) {
		_180c_log( 'SIMPLE_JWT_LOGIN_SECRET non défini', array(), 'error' );
		return new WP_Error( 'jwt_not_configured', __( 'JWT non configuré', '180c' ), array( 'status' => 500 ) );
	}

	$parts = explode( '.', $token );
	if ( 3 !== count( $parts ) ) {
		return new WP_Error( 'jwt_invalid', __( 'Token JWT mal formé', '180c' ), array( 'status' => 401 ) );
	}

	list( $header_b64, $payload_b64, $signature_b64 ) = $parts;

	// Vérifie la signature HMAC-SHA256.
	$expected_sig = hash_hmac( 'sha256', $header_b64 . '.' . $payload_b64, SIMPLE_JWT_LOGIN_SECRET, true );
	$provided_sig = _180c_jwt_base64url_decode( $signature_b64 );

	if ( ! hash_equals( $expected_sig, $provided_sig ) ) {
		_180c_log( 'Signature JWT invalide', array( 'token_prefix' => substr( $token, 0, 10 ) ), 'warning' );
		return new WP_Error( 'jwt_signature', __( 'Signature JWT invalide', '180c' ), array( 'status' => 401 ) );
	}

	$payload = json_decode( _180c_jwt_base64url_decode( $payload_b64 ), true );

	if ( ! is_array( $payload ) ) {
		return new WP_Error( 'jwt_invalid', __( 'Payload JWT invalide', '180c' ), array( 'status' => 401 ) );
	}

	// Vérifie l'expiration.
	if ( isset( $payload['exp'] ) && time() > (int) $payload['exp'] ) {
		return new WP_Error( 'jwt_expired', __( 'Token expiré', '180c' ), array( 'status' => 401 ) );
	}

	return $payload;
}

/**
 * Résout l'ID utilisateur depuis les claims standards de Simple JWT Login.
 *
 * @param array $payload Payload JWT validé.
 * @return int ID utilisateur, ou 0 si introuvable.
 */
function _180c_jwt_payload_user_id( array $payload ) {
	$user_id = 0;

	if ( isset( $payload['id'] ) ) {
		$user_id = (int) $payload['id'];
	} elseif ( isset( $payload['email'] ) ) {
		$user    = get_user_by( 'email', sanitize_email( $payload['email'] ) );
		$user_id = $user ? (int) $user->ID : 0;
	} elseif ( isset( $payload['username'] ) ) {
		$user    = get_user_by( 'login', sanitize_user( $payload['username'] ) );
		$user_id = $user ? (int) $user->ID : 0;
	}

	/**
	 * Filtre l'ID utilisateur résolu depuis un JWT validé.
	 *
	 * Point de passage unique des trois consommateurs JWT du thème (routes
	 * REST, `determine_current_user`, `?180c_app_login`). Renvoyer 0 fait
	 * ignorer le jeton. Utilisé par inc/security-2fa.php pour refuser tout
	 * jeton du compte protégé par le 2FA.
	 *
	 * @param int   $user_id ID résolu (0 si introuvable).
	 * @param array $payload Payload JWT validé.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks projet `180c/` imposé par CLAUDE.md.
	return (int) apply_filters( '180c/jwt_payload_user_id', $user_id, $payload );
}

/**
 * Récupère l'en-tête Authorization brut depuis $_SERVER.
 *
 * Gère le cas Apache/CGI où l'en-tête est déplacé dans REDIRECT_HTTP_AUTHORIZATION.
 *
 * @return string En-tête Authorization, ou chaîne vide.
 */
function _180c_jwt_server_auth_header() {
	if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		return trim( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
		return trim( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	return '';
}

/**
 * Permission callback : utilisateur authentifié via JWT (Simple JWT Login).
 *
 * Extrait et valide le Bearer token depuis l'en-tête Authorization. En cas de
 * succès, définit l'utilisateur courant pour la durée de la requête.
 *
 * @param WP_REST_Request $request Requête REST entrante.
 * @return bool|WP_Error True si le token est valide, WP_Error sinon.
 */
function _180c_rest_jwt_user( WP_REST_Request $request ) {
	$auth_header = $request->get_header( 'Authorization' );

	if ( ! $auth_header || stripos( $auth_header, 'Bearer ' ) !== 0 ) {
		return new WP_Error( 'jwt_missing', __( 'Token JWT requis', '180c' ), array( 'status' => 401 ) );
	}

	$payload = _180c_jwt_validate_token( substr( $auth_header, 7 ) );
	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	$user_id = _180c_jwt_payload_user_id( $payload );
	if ( ! $user_id ) {
		return new WP_Error( 'jwt_user_not_found', __( 'Utilisateur introuvable', '180c' ), array( 'status' => 401 ) );
	}

	wp_set_current_user( $user_id );
	return true;
}

/**
 * Permission callback hybride : JWT OU cookie WordPress (logged_in).
 *
 * Permet aux mêmes endpoints d'être appelés depuis l'app mobile (JWT)
 * ou depuis le navigateur web (cookie de session WP).
 *
 * @param WP_REST_Request $request Requête REST entrante.
 * @return bool|WP_Error True si authentifié, WP_Error sinon.
 */
function _180c_rest_jwt_or_cookie( WP_REST_Request $request ) {
	// Utilisateur déjà authentifié via cookie WP (site web) ou auth globale JWT.
	if ( is_user_logged_in() ) {
		return true;
	}

	// Sinon tente la validation du JWT (apps mobiles).
	return _180c_rest_jwt_user( $request );
}

/**
 * Variante « objet utilisateur » du validateur hybride.
 *
 * Même sémantique que {@see _180c_rest_jwt_or_cookie()} — session web (cookie)
 * OU JWT app, la session déjà établie primant sur toute revérification — mais
 * renvoie l'objet WP_User résolu plutôt qu'un booléen. Destinée aux pipelines
 * qui doivent ensuite manipuler l'utilisateur (comparaison d'e-mail, gating…).
 *
 * IMPORTANT : privilégier ce helper (ou `_180c_rest_jwt_or_cookie`) à un appel
 * direct à `_180c_rest_jwt_user()` dans les routes app-facing. `_180c_rest_jwt_user`
 * force la revérification HS256 contre la constante `SIMPLE_JWT_LOGIN_SECRET` et
 * ignore la session que le plugin Simple JWT Login a pu établir globalement : si
 * la constante diverge de la clé du plugin (cf. LOT G — unification JWT), la
 * route échoue en `jwt_signature` là où `/me` réussit. La sémantique
 * « session d'abord » de ce helper immunise contre ce désalignement.
 *
 * Contrat d'erreur strictement identique : mêmes WP_Error `jwt_*` renvoyés
 * lorsqu'aucune session n'est établie et que le JWT est absent/invalide/expiré.
 *
 * @param WP_REST_Request $request Requête REST entrante.
 * @return WP_User|WP_Error Utilisateur courant si authentifié, WP_Error sinon.
 */
function _180c_rest_jwt_or_cookie_user( WP_REST_Request $request ) {
	// Session déjà établie (cookie web, ou auth globale JWT du plugin/thème).
	if ( is_user_logged_in() ) {
		return wp_get_current_user();
	}

	// Sinon tente la validation du JWT (apps mobiles) ; définit l'utilisateur courant.
	$auth = _180c_rest_jwt_user( $request );
	if ( is_wp_error( $auth ) ) {
		return $auth;
	}

	return wp_get_current_user();
}

/**
 * Authentification globale par Bearer JWT (filtre determine_current_user).
 *
 * Quand aucune session n'est déjà établie (cookie, application password…) et
 * qu'un en-tête « Authorization: Bearer <token> » valide est présent, définit
 * l'utilisateur courant. Indispensable au gating serveur des recettes sur les
 * routes natives wp/v2/* (qui n'ont pas de permission_callback custom).
 *
 * Strictement additif : aucun effet en l'absence de Bearer, et ne remplace
 * jamais une authentification déjà résolue.
 *
 * @param int|false $user_id ID utilisateur déjà déterminé (0/false si aucun).
 * @return int|false ID utilisateur résolu.
 */
function _180c_jwt_determine_current_user( $user_id ) {
	// Ne jamais écraser une authentification déjà établie.
	if ( ! empty( $user_id ) ) {
		return $user_id;
	}

	$auth_header = _180c_jwt_server_auth_header();
	if ( '' === $auth_header || stripos( $auth_header, 'Bearer ' ) !== 0 ) {
		return $user_id;
	}

	$payload = _180c_jwt_validate_token( substr( $auth_header, 7 ) );
	if ( is_wp_error( $payload ) ) {
		return $user_id;
	}

	$resolved = _180c_jwt_payload_user_id( $payload );
	return $resolved > 0 ? $resolved : $user_id;
}
add_filter( 'determine_current_user', '_180c_jwt_determine_current_user', 30 );
