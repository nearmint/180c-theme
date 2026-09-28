<?php
/**
 * Mot de passe oublié + reset — routes /mot-de-passe-oublie/ et /reinitialiser-mot-de-passe/.
 *
 * Routing via rewrite rules + query var `_180c_auth`.
 *
 * Valeurs de _180c_auth :
 *  - 'forgot'    → /mot-de-passe-oublie/ (saisie email)
 *  - 'reset-pwd' → /reinitialiser-mot-de-passe/?key=…&login=… (nouveau password)
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// Routing
// ============================================================

/**
 * Enregistre les rewrite rules pour les routes mot de passe.
 *
 * @return void
 */
function _180c_password_rewrite(): void {
	add_rewrite_rule( '^mot-de-passe-oublie/?$', 'index.php?_180c_auth=forgot', 'top' );
	add_rewrite_rule( '^reinitialiser-mot-de-passe/?$', 'index.php?_180c_auth=reset-pwd', 'top' );
}
add_action( 'init', '_180c_password_rewrite' );

/**
 * Charge le template custom pour les routes mot de passe.
 *
 * @param string $template Chemin template WordPress.
 * @return string
 */
function _180c_password_template_include( string $template ): string {
	$auth = get_query_var( '_180c_auth' );

	if ( 'forgot' === $auth ) {
		$custom = get_template_directory() . '/inc/auth/views/password-reset.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}

	if ( 'reset-pwd' === $auth ) {
		$custom = get_template_directory() . '/inc/auth/views/password-new.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}

	return $template;
}
add_filter( 'template_include', '_180c_password_template_include' );

/**
 * Interdit la mise en cache des pages mot de passe.
 *
 * Ces pages portent un nonce et une notice de session : servies depuis le cache
 * (WP Super Cache en prod), l'utilisateur verrait un formulaire vierge après sa
 * demande et la resoumettrait — ce qui redéclencherait un envoi d'e-mail.
 *
 * @return void
 */
function _180c_password_pages_nocache(): void {
	$auth = get_query_var( '_180c_auth' );

	if ( 'forgot' !== $auth && 'reset-pwd' !== $auth ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	nocache_headers();
}
add_action( 'template_redirect', '_180c_password_pages_nocache', 1 );

// ============================================================
// Handler POST — formulaire 1 (demande de lien)
// ============================================================

/**
 * Traite la demande d'envoi du lien de réinitialisation.
 *
 * Délègue à `_180c_send_password_reset_link()` (verrou anti-doublon +
 * `retrieve_password()`), puis redirige systématiquement (POST/Redirect/GET) :
 * la page de confirmation n'est jamais la réponse au POST, donc un F5 ou un
 * « renvoyer le formulaire » du navigateur ne peut plus déclencher un second
 * e-mail.
 *
 * @return void
 */
function _180c_handle_forgot_form(): void {
	if ( 'forgot' !== get_query_var( '_180c_auth' ) ) {
		return;
	}
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return;
	}

	$nonce = isset( $_POST['_180c_forgot_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_180c_forgot_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, '_180c_forgot' ) ) {
		_180c_set_auth_error( 'nonce', __( 'Session expirée. Veuillez réessayer.', '180c' ) );
		_180c_forgot_redirect( false );
	}

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( '' !== $email && is_email( $email ) ) {
		_180c_send_password_reset_link( $email );
	}

	// Réponse toujours identique, e-mail existant ou non (anti-énumération).
	_180c_set_password_notice( 'sent', __( 'Si cette adresse est associée à un compte, vous recevrez un e-mail avec un lien de réinitialisation.', '180c' ) );
	_180c_forgot_redirect( true );
}
// Hook sur `template_redirect` (et non `init`) : la query var `_180c_auth`
// n'est peuplée qu'après le parsing de la requête. Voir inc/auth/login.php.
add_action( 'template_redirect', '_180c_handle_forgot_form' );

/**
 * Redirige vers /mot-de-passe-oublie/ après traitement du POST.
 *
 * Le paramètre `envoye=1` porte l'état « demande prise en compte » dans l'URL
 * plutôt que de dépendre uniquement du transient de notice : la page reste
 * correcte même si le transient a expiré ou si la réponse est servie par un
 * cache de page.
 *
 * @param bool $sent Vrai si la demande a été prise en compte.
 * @return void Ne retourne jamais : termine la requête.
 */
function _180c_forgot_redirect( bool $sent ): void {
	$url = home_url( '/mot-de-passe-oublie/' );

	if ( $sent ) {
		$url = add_query_arg( 'envoye', '1', $url );
	}

	wp_safe_redirect( $url );
	exit;
}

/**
 * Envoie le lien de réinitialisation.
 *
 * L'unicité de l'envoi est garantie en amont par
 * `_180c_block_duplicate_password_reset()` (hook `lostpassword_post`), commun à
 * tous les points d'entrée. Ici on se contente de déléguer et de journaliser.
 *
 * @param string $email Adresse e-mail saisie (validée par l'appelant).
 * @return void
 */
function _180c_send_password_reset_link( string $email ): void {
	// `retrieve_password()` lit $_POST['user_login'] sur les versions de WP
	// antérieures au support du paramètre.
	$_POST['user_login'] = $email;

	$result = retrieve_password( $email );

	if ( is_wp_error( $result ) ) {
		_180c_log( 'Password reset request failed.', array( 'code' => $result->get_error_code() ), 'warning' );
	}
}

// ============================================================
// Verrou anti-doublon — un seul e-mail par compte et par fenêtre
// ============================================================

/**
 * Empêche l'envoi d'un second lien de réinitialisation au même compte dans une
 * courte fenêtre de temps.
 *
 * Pourquoi c'est nécessaire : chaque demande génère une NOUVELLE clé qui écrase
 * `user_activation_key`. Une demande répétée (double-clic, resoumission du POST
 * par un F5, impatience) ne produit donc pas seulement un doublon dans la boîte
 * de réception — elle invalide aussi le lien du premier e-mail. C'est le scénario
 * observé en prod le 2026-07-25 (deux e-mails à 117 s d'intervalle, deux clés
 * différentes).
 *
 * Pourquoi `lostpassword_post` : cette action est déclenchée AVANT la génération
 * de la clé, et par tous les points d'entrée — la route custom
 * /mot-de-passe-oublie/, `wp-login.php?action=lostpassword`, l'endpoint REST de
 * Simple JWT Login (apps iOS/Android) et le formulaire WooCommerce
 * /mon-compte/lost-password/. Ajouter une erreur ici interrompt la demande sans
 * toucher à la clé existante : le premier lien reste valide.
 *
 * Le verrou n'est pas posé pour un compte inconnu (aucun e-mail ne part), ce qui
 * évite qu'un tiers puisse verrouiller une adresse au hasard.
 *
 * Note : si l'envoi échoue côté `wp_mail`, le verrou reste posé pour la durée de
 * la fenêtre. C'est assumé — filtrer `180c/password_reset_cooldown` permet de la
 * réduire si besoin.
 *
 * @param \WP_Error      $errors    Erreurs accumulées par retrieve_password().
 * @param \WP_User|false $user_data Utilisateur résolu, ou false si introuvable.
 * @return void
 */
function _180c_block_duplicate_password_reset( $errors, $user_data ): void {
	if ( ! ( $user_data instanceof WP_User ) || ! ( $errors instanceof WP_Error ) ) {
		return;
	}

	// Une erreur est déjà présente : la demande va échouer de toute façon.
	if ( $errors->has_errors() ) {
		return;
	}

	$cooldown = _180c_password_reset_cooldown();
	if ( $cooldown <= 0 ) {
		return;
	}

	$lock = _180c_password_reset_lock_key( $user_data->ID );

	if ( false !== get_transient( $lock ) ) {
		$errors->add(
			'_180c_reset_throttled',
			__( 'Un e-mail de réinitialisation vient de vous être envoyé. Vérifiez votre boîte de réception (et vos indésirables) avant d\'en demander un nouveau.', '180c' )
		);
		_180c_log( 'Password reset duplicate suppressed.', array( 'user_id' => $user_data->ID ), 'info' );
		return;
	}

	// Posé AVANT la génération de la clé et l'envoi : deux requêtes rapprochées
	// ne peuvent pas passer toutes les deux.
	set_transient( $lock, time(), $cooldown );
}
add_action( 'lostpassword_post', '_180c_block_duplicate_password_reset', 20, 2 );

/**
 * Clé du verrou anti-doublon d'envoi, par compte.
 *
 * Indexée sur l'ID utilisateur (et non sur la chaîne saisie) : un même compte
 * peut être visé par son identifiant ou par son adresse e-mail.
 *
 * @param int $user_id ID de l'utilisateur.
 * @return string
 */
function _180c_password_reset_lock_key( int $user_id ): string {
	return '_180c_pwd_sent_' . $user_id;
}

/**
 * Fenêtre pendant laquelle un second lien de réinitialisation n'est pas
 * renvoyé au même compte, en secondes.
 *
 * @return int
 */
function _180c_password_reset_cooldown(): int {
	/**
	 * Filtre la fenêtre anti-doublon des e-mails de réinitialisation.
	 *
	 * @param int $seconds Durée en secondes. 0 désactive le verrou.
	 */
	$seconds = (int) apply_filters( '180c/password_reset_cooldown', 5 * MINUTE_IN_SECONDS );

	return max( 0, $seconds );
}

// ============================================================
// Handler POST — formulaire 2 (nouveau password)
// ============================================================

/**
 * Traite la soumission du nouveau mot de passe.
 *
 * @return void
 */
function _180c_handle_reset_form(): void {
	if ( 'reset-pwd' !== get_query_var( '_180c_auth' ) ) {
		return;
	}
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return;
	}

	$nonce = isset( $_POST['_180c_reset_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_180c_reset_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, '_180c_reset' ) ) {
		_180c_set_auth_error( 'nonce', __( 'Session expirée. Veuillez réessayer.', '180c' ) );
		return;
	}

	$key      = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	$login    = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ) ) : '';
	$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$confirm  = isset( $_POST['password_confirm'] ) ? wp_unslash( $_POST['password_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( '' === $key || '' === $login ) {
		_180c_set_auth_error( 'invalid_token', __( 'Lien de réinitialisation invalide.', '180c' ) );
		return;
	}

	if ( mb_strlen( $password ) < 10 ) {
		_180c_set_auth_error( 'password_weak', __( 'Le mot de passe doit faire au moins 10 caractères.', '180c' ) );
		return;
	}

	if ( $password !== $confirm ) {
		_180c_set_auth_error( 'password_mismatch', __( 'Les mots de passe ne correspondent pas.', '180c' ) );
		return;
	}

	$user = check_password_reset_key( $key, $login );

	if ( is_wp_error( $user ) ) {
		_180c_set_auth_error( 'invalid_key', __( 'Ce lien est invalide ou a expiré. Veuillez en demander un nouveau.', '180c' ) );
		return;
	}

	reset_password( $user, $password );

	_180c_log( 'Password reset success.', array( 'user_id' => $user->ID ), 'info' );

	// Redirige vers /connexion/ avec message de succès.
	$redirect = add_query_arg( 'password_reset', '1', home_url( '/connexion/' ) );
	wp_safe_redirect( $redirect );
	exit;
}
// Hook sur `template_redirect` (et non `init`) : la query var `_180c_auth`
// n'est peuplée qu'après le parsing de la requête. Voir inc/auth/login.php.
add_action( 'template_redirect', '_180c_handle_reset_form' );

// ============================================================
// E-mail de réinitialisation — sujet + lien vers la route custom + template Woo
// ============================================================

/**
 * Personnalise l'e-mail de réinitialisation envoyé par retrieve_password().
 *
 * E-mail canonique = « Réinitialisation de mot de passe » client WooCommerce,
 * réglable dans WooCommerce › Réglages › E-mails (sujet, titre, contenu
 * additionnel). On rend SON gabarit brandé au lieu d'un HTML figé, afin que le
 * mail reçu corresponde exactement à ce que voit l'admin dans le BO. Le bouton
 * pointe vers la page custom /reinitialiser-mot-de-passe/ via le filtre
 * `180c/reset_password_email_url` (voir l'override du gabarit).
 *
 * Fallback HTML autonome si WooCommerce est absent/inactif.
 *
 * @param array          $defaults    { to, subject, message, headers }.
 * @param string         $key         Clé de réinitialisation en clair.
 * @param string         $user_login  Identifiant de l'utilisateur.
 * @param \WP_User|false $user_data Objet utilisateur.
 * @return array
 */
function _180c_reset_password_email( $defaults, $key, $user_login, $user_data ) {
	$rendered = _180c_render_wc_reset_password_email( (string) $key, (string) $user_login, $user_data );

	if ( null !== $rendered ) {
		$defaults['subject'] = $rendered['subject'];
		$defaults['message'] = $rendered['message'];
		$defaults['headers'] = $rendered['headers'];
		return $defaults;
	}

	// --- Fallback autonome (WooCommerce absent/inactif) : HTML brandé minimal. ---
	$reset_url = add_query_arg(
		array(
			'key'   => rawurlencode( $key ),
			'login' => rawurlencode( $user_login ),
		),
		home_url( '/reinitialiser-mot-de-passe/' )
	);

	$first    = ( $user_data instanceof WP_User ) ? $user_data->first_name : '';
	$greeting = '' !== $first
		/* translators: %s: prénom de l'utilisateur. */
		? sprintf( __( 'Bonjour %s,', '180c' ), $first )
		: __( 'Bonjour,', '180c' );

	$intro  = __( 'Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour en choisir un nouveau. Ce lien expire dans 24 heures.', '180c' );
	$cta    = __( 'Réinitialiser mon mot de passe', '180c' );
	$ignore = __( "Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail : votre mot de passe restera inchangé.", '180c' );

	$content  = '<p>' . esc_html( $greeting ) . '</p>';
	$content .= '<p>' . esc_html( $intro ) . '</p>';
	$content .= '<p style="text-align:center;margin:32px 0;">';
	$content .= '<a href="' . esc_url( $reset_url ) . '" style="display:inline-block;background-color:#FFAE3A;color:#0E0E0E;font-weight:700;text-decoration:none;padding:14px 28px;border-radius:8px;">' . esc_html( $cta ) . '</a>';
	$content .= '</p>';
	$content .= '<p style="font-size:13px;color:#6E6E6E;word-break:break-all;">' . esc_html__( 'Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :', '180c' ) . '<br>' . esc_url( $reset_url ) . '</p>';
	$content .= '<p>' . esc_html( $ignore ) . '</p>';

	$heading = __( 'Réinitialisation du mot de passe', '180c' );

	$defaults['subject'] = __( '180°C — Réinitialiser votre mot de passe', '180c' );
	$defaults['message'] = _180c_wrap_email_html( $heading, $content );
	$defaults['headers'] = 'Content-Type: text/html; charset=UTF-8';

	return $defaults;
}
add_filter( 'retrieve_password_notification_email', '_180c_reset_password_email', 10, 4 );

/**
 * Rend l'e-mail « Réinitialisation de mot de passe » client WooCommerce (gabarit
 * + sujet + titre + contenu additionnel réglés dans le BO), avec la vraie clé de
 * réinitialisation. Retourne null si WooCommerce est indisponible.
 *
 * Reproduit la préparation faite par WC_Email_Customer_Reset_Password::trigger()
 * — même idiome que le préviewer d'e-mails admin
 * (inc/admin/class-180c-email-tester.php). On ne teste pas is_enabled() :
 * l'e-mail de réinitialisation est critique et doit toujours partir.
 *
 * @param string         $key        Clé de réinitialisation en clair.
 * @param string         $user_login Identifiant de l'utilisateur.
 * @param \WP_User|false $user_data  Objet utilisateur.
 * @return array{subject:string, message:string, headers:string}|null
 */
function _180c_render_wc_reset_password_email( string $key, string $user_login, $user_data ): ?array {
	if ( ! function_exists( 'WC' ) || ! WC() || ! ( $user_data instanceof WP_User ) ) {
		return null;
	}

	// get_emails() est indexé par nom de classe, pas par id : on matche sur id.
	$email = null;
	foreach ( WC()->mailer()->get_emails() as $candidate ) {
		if ( 'customer_reset_password' === $candidate->id ) {
			$email = $candidate;
			break;
		}
	}

	if ( ! ( $email instanceof WC_Email ) ) {
		return null;
	}

	$email->setup_locale();

	// Propriétés lues par le gabarit (cf. WC_Email_Customer_Reset_Password::get_content_html()).
	$email->object            = $user_data;
	$email->user_id           = $user_data->ID;
	$email->user_login        = $user_login;
	$email->user_email        = stripslashes( $user_data->user_email );
	$email->reset_key         = $key;
	$email->recipient         = $email->user_email;
	$email->user_display_name = '' !== $user_data->first_name ? $user_data->first_name : $user_login;

	$message = $email->style_inline( $email->get_content() );
	$subject = $email->get_subject();
	$headers = $email->get_headers();

	$email->restore_locale();

	return array(
		'subject' => $subject,
		'message' => $message,
		'headers' => $headers,
	);
}

/**
 * Fait pointer le bouton de l'e-mail « Réinitialisation de mot de passe » client
 * WooCommerce vers la page brandée custom /reinitialiser-mot-de-passe/ (au lieu
 * de l'endpoint natif /mon-compte/lost-password/). Le gabarit override expose ce
 * filtre. La clé et l'identifiant sont conservés ; l'ID numérique WC est omis (la
 * vue custom password-new.php lit `key` + `login`).
 *
 * @param string $url        URL par défaut (endpoint WooCommerce).
 * @param string $reset_key  Clé de réinitialisation.
 * @param int    $user_id    ID utilisateur (non utilisé par la route custom).
 * @param string $user_login Identifiant de l'utilisateur.
 * @return string
 */
add_filter(
	'180c/reset_password_email_url',
	function ( $url, $reset_key, $user_id, $user_login ) {
		return add_query_arg(
			array(
				'key'   => rawurlencode( $reset_key ),
				'login' => rawurlencode( $user_login ),
			),
			home_url( '/reinitialiser-mot-de-passe/' )
		);
	},
	10,
	4
);

/**
 * Enveloppe un contenu HTML dans le template e-mail WooCommerce (en-tête + pied
 * de page brandés 180°C). Fallback HTML autonome si WooCommerce est absent.
 *
 * @param string $heading Titre de l'e-mail.
 * @param string $content Corps HTML.
 * @return string HTML complet de l'e-mail.
 */
function _180c_wrap_email_html( string $heading, string $content ): string {
	if ( function_exists( 'WC' ) && WC() && is_callable( array( WC()->mailer(), 'wrap_message' ) ) ) {
		$mailer  = WC()->mailer();
		$wrapped = $mailer->wrap_message( $heading, $content );
		return is_callable( array( $mailer, 'style_inline' ) ) ? $mailer->style_inline( $wrapped ) : $wrapped;
	}

	return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"></head>'
		. '<body style="margin:0;padding:24px;background-color:#F5F5F5;font-family:Georgia,\'Times New Roman\',serif;color:#0E0E0E;">'
		. '<div style="max-width:600px;margin:0 auto;background-color:#FFFFFF;border-radius:8px;padding:32px;">'
		. '<h1 style="font-family:Arial,Helvetica,sans-serif;font-size:22px;color:#0E0E0E;margin-top:0;">' . esc_html( $heading ) . '</h1>'
		. $content
		. '</div></body></html>';
}

// ============================================================
// Helpers — notices
// ============================================================

/**
 * Stocke une notice de mot de passe oublié.
 *
 * @param string $code    Code court.
 * @param string $message Message lisible.
 * @return void
 */
function _180c_set_password_notice( string $code, string $message ): void {
	$key = '_180c_pwd_notice_' . md5( _180c_get_client_ip() );
	set_transient(
		$key,
		array(
			'code'    => $code,
			'message' => $message,
		),
		120
	);
}

/**
 * Récupère et consomme la notice de mot de passe oublié.
 *
 * @return array{code: string, message: string}|null
 */
function _180c_get_password_notice(): ?array {
	$key    = '_180c_pwd_notice_' . md5( _180c_get_client_ip() );
	$notice = get_transient( $key );
	if ( is_array( $notice ) ) {
		delete_transient( $key );
		return $notice;
	}
	return null;
}

/**
 * Redirige tous les liens « mot de passe oublié » (WordPress + WooCommerce)
 * vers la page custom /mot-de-passe-oublie/ au lieu de l'endpoint My Account
 * natif (/mon-compte/lost-password/).
 *
 * Priorité 20 : passe APRÈS le filtre WooCommerce (`wc_lostpassword_url`,
 * prio 10) qui pointe sinon vers l'endpoint My Account. Couvre le lien du
 * formulaire de connexion checkout (global/form-login.php → wp_lostpassword_url()).
 *
 * @return string URL de la page mot de passe oublié custom.
 */
add_filter(
	'lostpassword_url',
	function () {
		return home_url( '/mot-de-passe-oublie/' );
	},
	20
);
