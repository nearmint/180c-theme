<?php
/**
 * Formulaire d'inscription custom — route /inscription/.
 *
 * Routing via rewrite rule + query var `_180c_auth=register`.
 * Le traitement POST est géré dans ce même fichier via l'action `init`.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// Routing
// ============================================================

/**
 * Enregistre la rewrite rule pour /inscription/.
 *
 * @return void
 */
function _180c_register_rewrite(): void {
	add_rewrite_rule( '^inscription/?$', 'index.php?_180c_auth=register', 'top' );
}
add_action( 'init', '_180c_register_rewrite' );

/**
 * Ajoute la valeur de query var pour register.
 *
 * La query var `_180c_auth` est déjà déclarée dans login.php.
 * Cette fonction est un no-op si login.php est chargé en premier ;
 * le filtre est idempotent.
 *
 * @return void
 */
function _180c_register_ensure_query_var(): void {
	// Aucun ajout nécessaire : login.php gère la query var _180c_auth.
	// Ce commentaire documente l'intention.
}

/**
 * Injecte le template custom pour /inscription/.
 *
 * @param string $template Chemin template WordPress.
 * @return string
 */
function _180c_register_template_include( string $template ): string {
	if ( 'register' === get_query_var( '_180c_auth' ) ) {
		$custom = get_template_directory() . '/inc/auth/views/register.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}
add_filter( 'template_include', '_180c_register_template_include' );

// ============================================================
// Redirection si déjà connecté
// ============================================================

/**
 * Redirige vers Mon Compte si l'utilisateur est déjà connecté.
 *
 * @return void
 */
function _180c_register_redirect_if_logged_in(): void {
	if ( 'register' !== get_query_var( '_180c_auth' ) ) {
		return;
	}
	if ( is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/mon-compte/' ) );
		exit;
	}
}
add_action( 'template_redirect', '_180c_register_redirect_if_logged_in' );

// ============================================================
// Handler POST
// ============================================================

/**
 * Traite la soumission du formulaire d'inscription.
 *
 * @return void
 */
function _180c_handle_register_form(): void {
	if ( 'register' !== get_query_var( '_180c_auth' ) ) {
		return;
	}
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return;
	}

	// Vérification nonce.
	$nonce = isset( $_POST['_180c_register_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_180c_register_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, '_180c_register' ) ) {
		_180c_set_auth_error( 'nonce', __( 'Session expirée. Veuillez réessayer.', '180c' ) );
		return;
	}

	$ip = _180c_get_client_ip();

	// Vérification blocage IP.
	if ( _180c_is_ip_blocked( $ip ) ) {
		_180c_set_auth_error( 'blocked', __( 'Trop de tentatives. Réessayez dans 1 heure.', '180c' ) );
		return;
	}

	// Validation Turnstile si nécessaire.
	if ( _180c_should_show_turnstile( $ip ) ) {
		$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
		if ( ! _180c_validate_turnstile( $token ) ) {
			_180c_set_auth_error( 'turnstile', __( 'Vérification de sécurité échouée. Veuillez réessayer.', '180c' ) );
			return;
		}
	}

	// Récupération et sanitisation des champs.
	$firstname  = isset( $_POST['firstname'] ) ? sanitize_text_field( wp_unslash( $_POST['firstname'] ) ) : '';
	$lastname   = isset( $_POST['lastname'] ) ? sanitize_text_field( wp_unslash( $_POST['lastname'] ) ) : '';
	$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$password   = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$cgu        = ! empty( $_POST['cgu'] );
	$newsletter = ! empty( $_POST['newsletter'] );

	// Validation — CGU obligatoires.
	if ( ! $cgu ) {
		_180c_set_auth_error( 'cgu', __( 'Vous devez accepter les Conditions Générales d\'Utilisation.', '180c' ) );
		return;
	}

	// Validation — champs requis.
	if ( '' === $firstname || '' === $lastname ) {
		_180c_set_auth_error( 'name', __( 'Veuillez renseigner votre prénom et votre nom.', '180c' ) );
		return;
	}

	// Validation — email.
	if ( '' === $email || ! is_email( $email ) ) {
		_180c_set_auth_error( 'email_invalid', __( 'Adresse e-mail invalide.', '180c' ) );
		return;
	}

	if ( email_exists( $email ) ) {
		// Message générique anti-énumération légèrement renforcée.
		_180c_set_auth_error( 'email_exists', __( 'Cette adresse e-mail est déjà utilisée.', '180c' ) );
		return;
	}

	// Validation — force du mot de passe (min 10 caractères, cf. cahier des charges).
	if ( mb_strlen( $password ) < 10 ) {
		_180c_set_auth_error( 'password_weak', __( 'Le mot de passe doit faire au moins 10 caractères.', '180c' ) );
		return;
	}

	// Création de l'utilisateur (username = email).
	$user_id = wp_create_user( $email, $password, $email );

	if ( is_wp_error( $user_id ) ) {
		_180c_set_auth_error( 'create_failed', __( 'Impossible de créer votre compte. Veuillez réessayer.', '180c' ) );
		_180c_log(
			'User creation failed.',
			array(
				'email' => $email,
				'error' => $user_id->get_error_message(),
			),
			'error'
		);
		return;
	}

	// Rôle subscriber explicite (indépendant de l'option default_role, que
	// WooCommerce peut modifier).
	$new_user = new WP_User( $user_id );
	$new_user->set_role( 'subscriber' );

	// Mise à jour des meta.
	update_user_meta( $user_id, 'first_name', $firstname );
	update_user_meta( $user_id, 'last_name', $lastname );

	if ( $newsletter ) {
		update_user_meta( $user_id, '_180c_newsletter_free', 1 );
	}

	// Hook custom pour extensions (ex : sync Mailchimp en Phase 6).
	do_action( '180c/user_registered', $user_id );

	// Connexion automatique.
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );

	_180c_log( 'New user registered.', array( 'user_id' => $user_id ), 'info' );

	// Flag GA4 + redirection.
	_180c_set_auth_success( 'register' );
	wp_safe_redirect( home_url( '/mon-compte/' ) );
	exit;
}
// Hook sur `template_redirect` (et non `init`) : la query var `_180c_auth`
// n'est peuplée qu'après le parsing de la requête. Voir inc/auth/login.php.
add_action( 'template_redirect', '_180c_handle_register_form' );
