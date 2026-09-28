<?php
/**
 * Suppression de compte — endpoint Mon compte, menu et routage.
 *
 * Surface web unique du parcours : l'onglet « Supprimer mon compte » de
 * /mon-compte/. Aucune route REST n'est exposée — les apps mobiles ne sont pas
 * concernées par ce lot.
 *
 * L'endpoint est déclaré à la fois en règle de réécriture
 * (`add_rewrite_endpoint`) et en query var WooCommerce
 * (`woocommerce_get_query_vars`) : c'est cette seconde déclaration qui donne
 * accès aux mécaniques natives `wc_get_account_endpoint_url()`,
 * `WC()->query->get_current_endpoint()` et `woocommerce_endpoint_{slug}_title`,
 * sans avoir à filtrer `woocommerce_get_endpoint_url` à la main.
 *
 * L'écran affiché est TOUJOURS dérivé de l'état serveur (rôle, blocages,
 * usermeta de demande) — jamais d'un paramètre d'URL, à deux exceptions près :
 * `confirm` (lien de l'e-mail, écran S3) et `compte-supprime` (notice de
 * sortie, écran S4).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slug de l'endpoint Mon compte.
 */
const _180C_ACCOUNT_DELETION_ENDPOINT = 'supprimer-mon-compte';

/**
 * Adresse de contact affichée aux utilisateurs.
 *
 * Priorité à la constante `_180C_CONTACT_EMAIL` (wp-config), repli sur
 * l'expéditeur configuré dans WooCommerce.
 *
 * @return string Adresse e-mail.
 */
function _180c_account_deletion_contact_email(): string {
	if ( defined( '_180C_CONTACT_EMAIL' ) && '' !== (string) _180C_CONTACT_EMAIL ) {
		return (string) _180C_CONTACT_EMAIL;
	}

	return (string) get_option( 'woocommerce_email_from_address', '' );
}

// ============================================================
// 1. Déclaration de l'endpoint
// ============================================================

add_action(
	'init',
	static function (): void {
		add_rewrite_endpoint( _180C_ACCOUNT_DELETION_ENDPOINT, EP_ROOT | EP_PAGES );
	}
);

/**
 * Déclare l'endpoint auprès de WC_Query.
 *
 * @param array $vars Query vars WooCommerce.
 * @return array Query vars enrichies.
 */
function _180c_account_deletion_query_vars( array $vars ): array {
	$vars[ _180C_ACCOUNT_DELETION_ENDPOINT ] = _180C_ACCOUNT_DELETION_ENDPOINT;

	return $vars;
}
add_filter( 'woocommerce_get_query_vars', '_180c_account_deletion_query_vars' );

/**
 * Titre de la page pour l'endpoint (fil d'Ariane, balise title).
 *
 * @param string $title Titre courant.
 * @return string Titre de l'onglet.
 */
function _180c_account_deletion_endpoint_title( string $title ): string {
	unset( $title );

	return __( 'Supprimer mon compte', '180c' );
}
add_filter( 'woocommerce_endpoint_' . _180C_ACCOUNT_DELETION_ENDPOINT . '_title', '_180c_account_deletion_endpoint_title' );

// ============================================================
// 2. Entrée de menu
// ============================================================

/**
 * Insère l'entrée de menu juste avant « Déconnexion ».
 *
 * Priorité 20 : le thème remplace intégralement le menu WooCommerce en
 * priorité 10 (`_180c_account_menu_items`, inc/woo/account-menu.php).
 *
 * Note : la navigation réellement visible est rendue par
 * parts/account/sidebar.php ; ce filtre garde le menu WooCommerce cohérent
 * pour les extensions et les contextes qui le lisent encore.
 *
 * @param array $items Items du menu Mon compte.
 * @return array Items avec l'entrée de suppression.
 */
function _180c_account_deletion_menu_item( array $items ): array {
	if ( ! _180c_account_deletion_menu_visible() ) {
		return $items;
	}

	$label  = __( 'Supprimer mon compte', '180c' );
	$logout = 'customer-logout';

	if ( ! isset( $items[ $logout ] ) ) {
		$items[ _180C_ACCOUNT_DELETION_ENDPOINT ] = $label;

		return $items;
	}

	$reordered = array();

	foreach ( $items as $key => $value ) {
		if ( $logout === $key ) {
			$reordered[ _180C_ACCOUNT_DELETION_ENDPOINT ] = $label;
		}

		$reordered[ $key ] = $value;
	}

	return $reordered;
}
add_filter( 'woocommerce_account_menu_items', '_180c_account_deletion_menu_item', 20 );

/**
 * Indique si l'entrée de menu doit être affichée à l'utilisateur courant.
 *
 * Seule l'éligibilité de RÔLE conditionne l'affichage : un compte bloqué par un
 * abonnement en cours voit l'onglet et l'écran d'explication (S0), c'est le
 * comportement attendu. Un compte à rôle non éligible ne voit rien.
 *
 * @return bool
 */
function _180c_account_deletion_menu_visible(): bool {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	return _180c_account_deletion_role_eligible( get_current_user_id() );
}

// ============================================================
// 3. Rendu de l'endpoint
// ============================================================

/**
 * Rend l'écran correspondant à l'état courant du compte.
 *
 * @return void
 */
function _180c_account_deletion_render(): void {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id = get_current_user_id();

	// S0-rôle — accès direct par un compte non éligible par son rôle.
	if ( ! _180c_account_deletion_role_eligible( $user_id ) ) {
		get_template_part( 'parts/account/deletion', 'role' );

		return;
	}

	// S3 / S3' — retour depuis le lien de l'e-mail.
	$token = _180c_account_deletion_confirm_param();

	if ( '' !== $token ) {
		if ( _180c_account_deletion_verify_token( $user_id, $token ) ) {
			get_template_part(
				'parts/account/deletion',
				'confirm',
				array( 'token' => $token )
			);
		} else {
			get_template_part( 'parts/account/deletion', 'invalid' );
		}

		return;
	}

	// S2 — une demande est en cours : ni formulaire, ni écran de blocage.
	if ( ! empty( _180c_account_deletion_get_request( $user_id ) ) ) {
		$user = get_userdata( $user_id );

		get_template_part(
			'parts/account/deletion',
			'sent',
			array(
				'email'  => $user ? (string) $user->user_email : '',
				'notice' => _180c_account_deletion_take_notice(),
			)
		);

		return;
	}

	$blockers = _180c_account_deletion_blockers( $user_id );

	// S0 — blocages métier : écran explicatif, jamais de formulaire.
	if ( ! empty( $blockers ) ) {
		get_template_part(
			'parts/account/deletion',
			'blocked',
			array(
				'blockers'      => $blockers,
				'contact_email' => _180c_account_deletion_contact_email(),
			)
		);

		return;
	}

	// S1 — formulaire de demande.
	get_template_part(
		'parts/account/deletion',
		'form',
		array( 'error' => _180c_account_deletion_take_notice( 'error' ) )
	);
}
add_action( 'woocommerce_account_' . _180C_ACCOUNT_DELETION_ENDPOINT . '_endpoint', '_180c_account_deletion_render' );


// ============================================================
// 4. Notices — transmises d'une requête POST au rendu (PRG)
// ============================================================

/**
 * Clé de transient portant la notice d'une redirection PRG.
 *
 * @param int $user_id Identifiant WP.
 * @return string Clé de transient.
 */
function _180c_account_deletion_notice_key( int $user_id ): string {
	return '_180c_acctdel_notice_' . $user_id;
}

/**
 * Mémorise une notice à afficher après redirection.
 *
 * @param string $type    Type de notice (`error`, `info`).
 * @param string $message Message déjà traduit.
 * @return void
 */
function _180c_account_deletion_set_notice( string $type, string $message ): void {
	$user_id = get_current_user_id();

	if ( $user_id <= 0 ) {
		return;
	}

	set_transient(
		_180c_account_deletion_notice_key( $user_id ),
		array(
			'type'    => $type,
			'message' => $message,
		),
		MINUTE_IN_SECONDS * 5
	);
}

/**
 * Lit et consomme la notice en attente pour l'utilisateur courant.
 *
 * @param string $type Type attendu ; chaîne vide pour accepter tous les types.
 * @return string Message, ou chaîne vide.
 */
function _180c_account_deletion_take_notice( string $type = '' ): string {
	$user_id = get_current_user_id();

	if ( $user_id <= 0 ) {
		return '';
	}

	$key    = _180c_account_deletion_notice_key( $user_id );
	$notice = get_transient( $key );

	if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
		return '';
	}

	if ( '' !== $type && ( $notice['type'] ?? '' ) !== $type ) {
		return '';
	}

	delete_transient( $key );

	return (string) $notice['message'];
}

// ============================================================
// 5. Routage des actions (POST uniquement, PRG)
// ============================================================

/**
 * Indique si la requête courante porte sur l'endpoint de suppression.
 *
 * @return bool
 */
function _180c_account_deletion_is_endpoint(): bool {
	if ( is_admin() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return false;
	}

	if ( ! isset( WC()->query ) || ! method_exists( WC()->query, 'get_current_endpoint' ) ) {
		return false;
	}

	return _180C_ACCOUNT_DELETION_ENDPOINT === (string) WC()->query->get_current_endpoint();
}

/**
 * Traite les actions du parcours de suppression.
 *
 * Toutes les actions sont en POST, protégées par un nonce dédié, et portent
 * exclusivement sur l'utilisateur courant : aucun identifiant n'est accepté en
 * paramètre. Chaque traitement se termine par une redirection (PRG) afin qu'un
 * rechargement de page ne rejoue jamais l'action.
 *
 * @return void
 */
function _180c_account_deletion_route(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}

	if ( ! is_user_logged_in() || ! _180c_account_deletion_is_endpoint() ) {
		return;
	}

	$action = isset( $_POST['_180c_acctdel_action'] )
		? sanitize_key( wp_unslash( $_POST['_180c_acctdel_action'] ) )
		: '';

	if ( ! in_array( $action, array( 'request', 'resend', 'cancel', 'execute' ), true ) ) {
		return;
	}

	$nonce = isset( $_POST['_180c_acctdel_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['_180c_acctdel_nonce'] ) )
		: '';

	if ( ! wp_verify_nonce( $nonce, '_180c_acctdel_' . $action ) ) {
		wp_die( esc_html__( 'Requête invalide.', '180c' ), 403 );
	}

	$user_id = get_current_user_id();

	if ( ! _180c_account_deletion_role_eligible( $user_id ) ) {
		wp_die( esc_html__( 'Requête invalide.', '180c' ), 403 );
	}

	switch ( $action ) {
		case 'request':
			_180c_account_deletion_handle_request( $user_id );
			break;
		case 'resend':
			_180c_account_deletion_handle_resend( $user_id );
			break;
		case 'cancel':
			_180c_account_deletion_handle_cancel( $user_id );
			break;
		case 'execute':
			_180c_account_deletion_handle_execute( $user_id );
			break;
	}

	_180c_account_deletion_redirect_back();
}
add_action( 'template_redirect', '_180c_account_deletion_route' );

/**
 * Redirige vers l'endpoint (PRG) et interrompt la requête.
 *
 * @return void
 */
function _180c_account_deletion_redirect_back(): void {
	wp_safe_redirect( wc_get_account_endpoint_url( _180C_ACCOUNT_DELETION_ENDPOINT ) );
	exit;
}

/**
 * Action « request » — vérifie le mot de passe puis ouvre une demande.
 *
 * @param int $user_id Identifiant WP.
 * @return void
 */
function _180c_account_deletion_handle_request( int $user_id ): void {
	// Le nonce `_180c_acctdel_request` est vérifié par l'appelant
	// (_180c_account_deletion_route), qui refuse la requête sinon. PHPCS ne
	// suit pas cette vérification d'une fonction à l'autre, d'où l'annotation
	// sur la lecture de $_POST ci-dessous.
	// phpcs:disable WordPress.Security.NonceVerification.Missing

	// Une demande n'a de sens que si le compte est encore éligible.
	if ( ! empty( _180c_account_deletion_blockers( $user_id ) ) ) {
		return;
	}

	$user = get_userdata( $user_id );

	$password = isset( $_POST['acctdel_password'] )
		? (string) wp_unslash( $_POST['acctdel_password'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Mot de passe : vérifié tel quel par wp_check_password(), jamais stocké ni affiché.
		: '';

	if ( ! $user || '' === $password || ! wp_check_password( $password, $user->user_pass, $user_id ) ) {
		_180c_account_deletion_set_notice( 'error', __( 'Mot de passe incorrect.', '180c' ) );

		return;
	}

	// phpcs:enable WordPress.Security.NonceVerification.Missing

	_180c_account_deletion_send_confirmation( $user_id, _180c_account_deletion_create_request( $user_id ) );
}

/**
 * Action « resend » — régénère le jeton et renvoie l'e-mail.
 *
 * @param int $user_id Identifiant WP.
 * @return void
 */
function _180c_account_deletion_handle_resend( int $user_id ): void {
	if ( empty( _180c_account_deletion_get_request( $user_id ) ) ) {
		return;
	}

	if ( ! _180c_account_deletion_can_resend( $user_id ) ) {
		_180c_account_deletion_set_notice( 'info', __( 'Vous pourrez renvoyer l\'e-mail dans quelques minutes.', '180c' ) );

		return;
	}

	$token = _180c_account_deletion_refresh_token( $user_id );

	if ( '' === $token ) {
		return;
	}

	_180c_account_deletion_send_confirmation( $user_id, $token );
	_180c_account_deletion_set_notice( 'info', __( 'E-mail renvoyé.', '180c' ) );
}

/**
 * Action « cancel » — annule la demande, le compte reste actif.
 *
 * @param int $user_id Identifiant WP.
 * @return void
 */
function _180c_account_deletion_handle_cancel( int $user_id ): void {
	_180c_account_deletion_clear_request( $user_id );
	_180c_account_deletion_set_notice( 'info', __( 'Votre demande est annulée. Votre compte reste actif.', '180c' ) );
}

/**
 * Action « execute » — dernier maillon : lance le pipeline de suppression.
 *
 * Le jeton est revérifié ici : l'écran S3 ne prouve rien à lui seul, un POST
 * pouvant être forgé. Un verrou de 60 secondes empêche qu'un double clic ne
 * fasse tourner le pipeline deux fois en parallèle.
 *
 * @param int $user_id Identifiant WP.
 * @return void
 */
function _180c_account_deletion_handle_execute( int $user_id ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce `_180c_acctdel_execute` vérifié par l'appelant (_180c_account_deletion_route).
	$raw = isset( $_POST['acctdel_token'] ) ? (string) wp_unslash( $_POST['acctdel_token'] ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$token = 1 === preg_match( '/^[a-f0-9]{64}$/', $raw ) ? $raw : '';

	if ( ! _180c_account_deletion_verify_token( $user_id, $token ) ) {
		return;
	}

	$lock = _180c_account_deletion_lock_key( $user_id );

	if ( false !== get_transient( $lock ) ) {
		return;
	}

	set_transient( $lock, 1, MINUTE_IN_SECONDS );

	$result = _180c_account_deletion_execute( $user_id );

	if ( empty( $result['ok'] ) ) {
		delete_transient( $lock );
		_180c_account_deletion_set_notice( 'error', _180c_account_deletion_pipeline_error_message() );

		return;
	}

	// Le compte n'existe plus : la session doit tomber avant la redirection.
	wp_logout();

	wp_safe_redirect(
		add_query_arg( 'compte-supprime', '1', wc_get_page_permalink( 'myaccount' ) )
	);
	exit;
}

/**
 * Message affiché quand le pipeline s'interrompt sans rien avoir modifié.
 *
 * @return string Message déjà traduit.
 */
function _180c_account_deletion_pipeline_error_message(): string {
	$contact = _180c_account_deletion_contact_email();

	return sprintf(
		/* translators: %s: adresse e-mail de contact. */
		__( 'Nous n\'avons pas pu supprimer votre compte. Rien n\'a été modifié. Écrivez-nous à %s.', '180c' ),
		$contact
	);
}

// ============================================================
// 7. Sortie — notice sur /mon-compte/ après suppression
// ============================================================

/**
 * Affiche la notice de sortie sous le formulaire de connexion.
 *
 * Le compte n'existe plus et la session est fermée : la seule chose que voit
 * l'utilisateur redirigé est le formulaire de connexion de WooCommerce.
 *
 * @return void
 */
function _180c_account_deletion_farewell_notice(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple drapeau d'affichage, sans effet de bord.
	if ( ! isset( $_GET['compte-supprime'] ) || '1' !== (string) wp_unslash( $_GET['compte-supprime'] ) ) {
		return;
	}

	printf(
		'<p class="c-account-deletion__notice" role="status">%s</p>',
		esc_html__( 'Votre compte est supprimé. Merci pour votre confiance.', '180c' )
	);
}
add_action( 'woocommerce_before_customer_login_form', '_180c_account_deletion_farewell_notice' );

// ============================================================
// 6. Retour sur le lien de confirmation après connexion
// ============================================================

/**
 * Ajoute le champ `redirect` au formulaire de connexion WooCommerce.
 *
 * Un visiteur déconnecté qui suit le lien de l'e-mail voit le formulaire de
 * connexion de WooCommerce. Sans ce champ, `WC_Form_Handler::process_login()`
 * retomberait sur le referer, qui n'est pas garanti : on lui redonne l'URL
 * complète, jeton compris.
 *
 * @return void
 */
function _180c_account_deletion_login_redirect_field(): void {
	if ( is_user_logged_in() || ! _180c_account_deletion_is_endpoint() ) {
		return;
	}

	$token = _180c_account_deletion_confirm_param();

	if ( '' === $token ) {
		return;
	}

	printf(
		'<input type="hidden" name="redirect" value="%s" />',
		esc_url( _180c_account_deletion_confirm_url( $token ) )
	);
}
add_action( 'woocommerce_login_form_end', '_180c_account_deletion_login_redirect_field' );

/**
 * Lit le paramètre `confirm` de l'URL.
 *
 * Seule exception, avec `compte-supprime`, à la règle « l'écran découle de
 * l'état serveur » : le jeton ne peut venir que du lien de l'e-mail.
 *
 * Le format est validé, jamais rapiécé : filtrer les caractères indésirables
 * d'une valeur malformée produirait un AUTRE jeton, et ferait passer une URL
 * trafiquée pour un simple lien expiré. Une valeur non conforme est rejetée.
 *
 * @return string Jeton de 64 caractères hexadécimaux, ou chaîne vide.
 */
function _180c_account_deletion_confirm_param(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'un jeton de confirmation en GET, vérifié ensuite par hash_equals ; aucune mutation ici.
	if ( ! isset( $_GET['confirm'] ) ) {
		return '';
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Idem.
	$raw = (string) wp_unslash( $_GET['confirm'] );

	return 1 === preg_match( '/^[a-f0-9]{64}$/', $raw ) ? $raw : '';
}
