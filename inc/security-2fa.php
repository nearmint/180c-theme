<?php
/**
 * Authentification à deux facteurs (TOTP) — compte webmaster uniquement.
 *
 * Un seul compte est concerné : celui dont le `user_login` vaut exactement
 * `_180C_2FA_LOGIN`, constante définie dans `wp-config.php`. Sans elle (ou
 * vide), le module échoue FERMÉ : toute connexion par mot de passe d'un
 * compte administrateur (`manage_options`) est refusée, plutôt que de laisser
 * le compte protégé se connecter sans second facteur. Aucun autre utilisateur, quel que soit son rôle, ne voit
 * d'écran ni ne subit de vérification supplémentaire.
 *
 * Portée de la garde. Ce fichier est chargé bien avant que WordPress sache qui
 * se connecte (sur une requête de login, l'utilisateur courant est anonyme) :
 * une sortie en tête de fichier est donc impossible. La garde vit en PREMIÈRE
 * instruction de chaque fonction accrochée — `_180c_2fa_is_target_user()` — et
 * ressort immédiatement, sans rien lire ni écrire, pour tout autre compte.
 *
 * La cible est reconnue sur `WP_User::user_login` tel qu'il est stocké en base,
 * jamais sur l'identifiant saisi : une connexion par e-mail ou avec une autre
 * casse (MySQL compare sans la casse) doit déclencher le 2FA.
 *
 * TOTP RFC 6238 en PHP pur : HMAC-SHA1, 6 chiffres, pas de 30 s, tolérance ±1
 * pas, comparaison `hash_equals()`. Un code accepté ne peut pas être rejoué :
 * le pas consommé est mémorisé dans `_180c_totp_last_step`.
 *
 * Mots de passe d'application : volontairement NON couverts (décision du
 * 2026-09-16). Ils ne passent pas par `authenticate`, donnent un accès REST
 * mais n'ouvrent jamais de session cookie dans l'administration.
 *
 * ---------------------------------------------------------------------------
 * PERTE D'ACCÈS : il n'y a pas de codes de secours. La procédure de
 * déblocage (intervention en base) est documentée hors dépôt.
 * ---------------------------------------------------------------------------
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seul compte protégé par le 2FA — comparaison stricte sur le `user_login` en base.
 * La valeur réelle vit dans `wp-config.php` ; vide = échec fermé (voir
 * `_180c_2fa_fail_closed()`).
 */
if ( ! defined( '_180C_2FA_LOGIN' ) ) {
	define( '_180C_2FA_LOGIN', '' );
}

/** Meta : secret TOTP encodé en Base32 (32 caractères, 160 bits). */
define( '_180C_2FA_META_SECRET', '_180c_totp_secret' );

/** Meta : `1` une fois le premier code valide saisi. */
define( '_180C_2FA_META_ENROLLED', '_180c_totp_enrolled' );

/** Meta : dernier pas TOTP accepté (anti-rejeu). */
define( '_180C_2FA_META_LAST_STEP', '_180c_totp_last_step' );

/** Slug de l'écran d'enrôlement (alphabétique : cf. CLAUDE.md, identifiants CSS). */
define( '_180C_2FA_PAGE', 'one80c-2fa' );

/** Durée d'un pas TOTP, en secondes. */
define( '_180C_2FA_PERIOD', 30 );

/** Libellé de l'émetteur affiché dans l'application d'authentification. */
define( '_180C_2FA_ISSUER', '180°C' );

// ============================================================
// Ciblage
// ============================================================

/**
 * Indique si un utilisateur est le compte protégé par le 2FA.
 *
 * Garde commune à toutes les fonctions accrochées de ce fichier.
 *
 * @param WP_User|int|null $user Utilisateur ou ID.
 * @return bool True seulement pour le compte `_180C_2FA_LOGIN`.
 */
function _180c_2fa_is_target_user( $user ): bool {
	if ( is_numeric( $user ) ) {
		$user = (int) $user > 0 ? get_userdata( (int) $user ) : null;
	}

	return $user instanceof WP_User && _180C_2FA_LOGIN === $user->user_login;
}

/**
 * Indique si l'utilisateur courant peut voir l'écran d'enrôlement.
 *
 * Double condition : capacité d'administration ET comparaison du login. Le
 * rôle seul ne suffit pas (tous les administrateurs l'ont).
 *
 * @return bool
 */
function _180c_2fa_current_user_can_enroll(): bool {
	$user = wp_get_current_user();

	return _180c_2fa_is_target_user( $user ) && user_can( $user, 'manage_options' );
}

/**
 * Lit le secret TOTP d'un utilisateur.
 *
 * @param int $user_id ID utilisateur.
 * @return string Secret Base32, ou '' si absent ou malformé.
 */
function _180c_2fa_get_secret( int $user_id ): string {
	$secret = (string) get_user_meta( $user_id, _180C_2FA_META_SECRET, true );

	return 1 === preg_match( '/^[A-Z2-7]{32}$/', $secret ) ? $secret : '';
}

/**
 * Indique si le 2FA est actif pour un utilisateur.
 *
 * Actif = marqueur d'enrôlement posé ET secret exploitable. Un marqueur sans
 * secret (suppression partielle en base) laisse le compte déverrouillé
 * plutôt que de le rendre inaccessible.
 *
 * @param int $user_id ID utilisateur.
 * @return bool
 */
function _180c_2fa_is_enrolled( int $user_id ): bool {
	return '1' === (string) get_user_meta( $user_id, _180C_2FA_META_ENROLLED, true )
		&& '' !== _180c_2fa_get_secret( $user_id );
}

// ============================================================
// TOTP (RFC 6238) — PHP pur
// ============================================================

/**
 * Encode une chaîne binaire en Base32 (RFC 4648, sans remplissage).
 *
 * @param string $bytes Octets bruts.
 * @return string Chaîne Base32 majuscule.
 */
function _180c_totp_base32_encode( string $bytes ): string {
	$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$bits     = '';

	foreach ( str_split( $bytes ) as $char ) {
		$bits .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
	}

	$encoded = '';
	foreach ( str_split( $bits, 5 ) as $chunk ) {
		$encoded .= $alphabet[ bindec( str_pad( $chunk, 5, '0' ) ) ];
	}

	return $encoded;
}

/**
 * Décode une chaîne Base32 (RFC 4648). Espaces et `=` tolérés.
 *
 * @param string $encoded Chaîne Base32.
 * @return string Octets bruts, ou '' si la chaîne contient un caractère invalide.
 */
function _180c_totp_base32_decode( string $encoded ): string {
	$encoded = strtoupper( (string) preg_replace( '/[\s=]+/', '', $encoded ) );
	if ( '' === $encoded || 1 !== preg_match( '/^[A-Z2-7]+$/', $encoded ) ) {
		return '';
	}

	$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$bits     = '';
	foreach ( str_split( $encoded ) as $char ) {
		$bits .= str_pad( decbin( strpos( $alphabet, $char ) ), 5, '0', STR_PAD_LEFT );
	}

	$bytes = '';
	foreach ( str_split( $bits, 8 ) as $byte ) {
		if ( 8 === strlen( $byte ) ) {
			$bytes .= chr( bindec( $byte ) );
		}
	}

	return $bytes;
}

/**
 * Génère un secret TOTP : 20 octets aléatoires → 32 caractères Base32.
 *
 * @return string Secret Base32.
 */
function _180c_totp_generate_secret(): string {
	return _180c_totp_base32_encode( random_bytes( 20 ) );
}

/**
 * Calcule le code TOTP d'un pas donné (HOTP RFC 4226 sur le compteur temporel).
 *
 * @param string $secret Secret Base32.
 * @param int    $step   Numéro de pas (floor( timestamp / période )).
 * @param int    $digits Nombre de chiffres (6 en usage, 8 pour les vecteurs RFC).
 * @return string Code zéro-rempli, ou '' si le secret est inexploitable.
 */
function _180c_totp_code( string $secret, int $step, int $digits = 6 ): string {
	$key = _180c_totp_base32_decode( $secret );
	if ( '' === $key ) {
		return '';
	}

	// Compteur sur 64 bits big-endian.
	$hash   = hash_hmac( 'sha1', pack( 'J', $step ), $key, true );
	$offset = ord( $hash[19] ) & 0x0F;
	$binary = ( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 )
		| ( ord( $hash[ $offset + 1 ] ) << 16 )
		| ( ord( $hash[ $offset + 2 ] ) << 8 )
		| ord( $hash[ $offset + 3 ] );

	return str_pad( (string) ( $binary % ( 10 ** $digits ) ), $digits, '0', STR_PAD_LEFT );
}

/**
 * Vérifie un code TOTP saisi et le consomme.
 *
 * Tolérance ±1 pas (±30 s). Les trois pas sont toujours calculés et comparés
 * par `hash_equals()`. Un pas déjà consommé (ou antérieur) est refusé : un code
 * intercepté ne peut pas être rejoué dans sa fenêtre de validité.
 *
 * @param int    $user_id ID utilisateur.
 * @param string $code    Code saisi (espaces tolérés).
 * @return bool True si le code est valide et n'avait jamais servi.
 */
function _180c_totp_verify( int $user_id, string $code ): bool {
	$code   = (string) preg_replace( '/\s+/', '', $code );
	$secret = _180c_2fa_get_secret( $user_id );

	if ( '' === $secret || 1 !== preg_match( '/^\d{6}$/', $code ) ) {
		return false;
	}

	$current = (int) floor( time() / _180C_2FA_PERIOD );
	$matched = null;

	for ( $offset = -1; $offset <= 1; $offset++ ) {
		$step = $current + $offset;
		if ( hash_equals( _180c_totp_code( $secret, $step ), $code ) && null === $matched ) {
			$matched = $step;
		}
	}

	$last_step = (int) get_user_meta( $user_id, _180C_2FA_META_LAST_STEP, true );
	if ( null === $matched || $matched <= $last_step ) {
		return false;
	}

	update_user_meta( $user_id, _180C_2FA_META_LAST_STEP, $matched );

	return true;
}

// ============================================================
// Enrôlement (administration)
// ============================================================

/**
 * URL de l'écran d'enrôlement.
 *
 * @param array<string, string> $args Arguments de requête supplémentaires.
 * @return string
 */
function _180c_2fa_enrollment_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => _180C_2FA_PAGE ), $args ), admin_url( 'users.php' ) );
}

/**
 * Déclare l'écran d'enrôlement sous « Comptes », pour le seul compte ciblé.
 *
 * @return void
 */
function _180c_2fa_register_admin_page(): void {
	if ( ! _180c_2fa_current_user_can_enroll() ) {
		return;
	}

	$hook = add_submenu_page(
		'users.php',
		__( 'Double authentification', '180c' ),
		__( 'Double authentification', '180c' ),
		'manage_options',
		_180C_2FA_PAGE,
		'_180c_2fa_render_admin_page'
	);

	if ( $hook ) {
		// L'écran affiche le secret en clair : jamais de mise en cache.
		add_action( "load-{$hook}", 'nocache_headers' );
	}
}
add_action( 'admin_menu', '_180c_2fa_register_admin_page' );

/**
 * Force l'enrôlement : tant qu'il n'est pas fait, toute page d'administration
 * renvoie vers l'écran d'enrôlement.
 *
 * Épargnés : AJAX, `admin-post.php` (le formulaire d'enrôlement y est traité)
 * et l'écran d'enrôlement lui-même.
 *
 * @return void
 */
function _180c_2fa_force_enrollment(): void {
	if ( ! _180c_2fa_current_user_can_enroll() ) {
		return;
	}

	if ( wp_doing_ajax() || _180c_2fa_is_enrolled( get_current_user_id() ) ) {
		return;
	}

	global $pagenow;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture du slug de page pour le routage, aucune action.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( 'admin-post.php' === $pagenow || ( 'users.php' === $pagenow && _180C_2FA_PAGE === $page ) ) {
		return;
	}

	wp_safe_redirect( _180c_2fa_enrollment_url() );
	exit;
}
add_action( 'admin_init', '_180c_2fa_force_enrollment' );

/**
 * Rend l'écran d'enrôlement.
 *
 * Génère le secret s'il est absent. Une fois l'enrôlement validé, le secret
 * n'est plus jamais réaffiché.
 *
 * @return void
 */
function _180c_2fa_render_admin_page(): void {
	if ( ! _180c_2fa_current_user_can_enroll() ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ), '', array( 'response' => 403 ) );
	}

	$user_id = get_current_user_id();
	$user    = wp_get_current_user();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Code de statut d'affichage posé par la redirection du handler.
	$status = isset( $_GET['one80c_2fa'] ) ? sanitize_key( wp_unslash( $_GET['one80c_2fa'] ) ) : '';

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Double authentification', '180c' ) . '</h1>';

	if ( 'enrolled' === $status ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Double authentification activée. Le code sera demandé à chaque connexion.', '180c' ) . '</p></div>';
	} elseif ( 'invalid' === $status ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Code invalide. Réessayez avec le code affiché actuellement.', '180c' ) . '</p></div>';
	}

	if ( _180c_2fa_is_enrolled( $user_id ) ) {
		echo '<p>' . esc_html__( 'La double authentification est active sur ce compte.', '180c' ) . '</p>';
		echo '<p>' . esc_html__( 'En cas de perte d\'accès : supprimer les métadonnées _180c_totp_secret et _180c_totp_enrolled de ce compte dans la table usermeta (procédure détaillée en tête de inc/security-2fa.php).', '180c' ) . '</p>';
		echo '</div>';
		return;
	}

	$secret = _180c_2fa_get_secret( $user_id );
	if ( '' === $secret ) {
		$secret = _180c_totp_generate_secret();
		update_user_meta( $user_id, _180C_2FA_META_SECRET, $secret );
		delete_user_meta( $user_id, _180C_2FA_META_LAST_STEP );
	}

	$otpauth = sprintf(
		'otpauth://totp/%1$s:%2$s?secret=%3$s&issuer=%1$s&algorithm=SHA1&digits=6&period=%4$d',
		rawurlencode( _180C_2FA_ISSUER ),
		rawurlencode( $user->user_login ),
		$secret, // gitleaks:allow — variable, aucun secret en dur.
		_180C_2FA_PERIOD
	);

	?>
	<p><?php esc_html_e( 'La connexion à ce compte exigera un code à 6 chiffres en plus du mot de passe. Ajoutez la clé ci-dessous dans l\'app Mots de passe (fiche du site → « Configurer un code de validation » → « Saisir la clé de configuration »), puis saisissez le code généré pour confirmer.', '180c' ); ?></p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Clé de configuration', '180c' ); ?></th>
			<td>
				<code style="font-size:1.4em;letter-spacing:0.08em;user-select:all;"><?php echo esc_html( implode( ' ', str_split( $secret, 4 ) ) ); ?></code>
				<p class="description"><?php esc_html_e( 'Les espaces ne servent qu\'à la lecture.', '180c' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Paramètres', '180c' ); ?></th>
			<td>
				<?php
				printf(
					/* translators: 1: émetteur, 2: identifiant de connexion. */
					esc_html__( 'Émetteur : %1$s — Compte : %2$s — TOTP, SHA-1, 6 chiffres, 30 secondes.', '180c' ),
					esc_html( _180C_2FA_ISSUER ),
					esc_html( $user->user_login )
				);
				?>
				<p class="description">
					<a href="<?php echo esc_url( $otpauth, array( 'otpauth' ) ); ?>"><?php esc_html_e( 'Ouvrir directement dans Mots de passe', '180c' ); ?></a>
					<?php esc_html_e( '(si le navigateur le propose).', '180c' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="one80c_2fa_enroll">
		<?php wp_nonce_field( 'one80c_2fa_enroll' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="one80c-2fa-code"><?php esc_html_e( 'Code à 6 chiffres', '180c' ); ?></label></th>
				<td>
					<input type="text" id="one80c-2fa-code" name="one80c_totp_code" class="regular-text" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Activer la double authentification', '180c' ) ); ?>
	</form>
	</div>
	<?php
}

/**
 * Traite la confirmation d'enrôlement : un premier code valide active le 2FA.
 *
 * @return void
 */
function _180c_2fa_handle_enroll(): void {
	if ( ! _180c_2fa_current_user_can_enroll() ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'one80c_2fa_enroll' );

	$user_id = get_current_user_id();

	if ( _180c_2fa_is_enrolled( $user_id ) ) {
		wp_safe_redirect( _180c_2fa_enrollment_url() );
		exit;
	}

	$code = isset( $_POST['one80c_totp_code'] ) ? sanitize_text_field( wp_unslash( $_POST['one80c_totp_code'] ) ) : '';

	if ( ! _180c_totp_verify( $user_id, $code ) ) {
		wp_safe_redirect( _180c_2fa_enrollment_url( array( 'one80c_2fa' => 'invalid' ) ) );
		exit;
	}

	update_user_meta( $user_id, _180C_2FA_META_ENROLLED, '1' );
	_180c_log( '2FA enrolled.', array( 'user_id' => $user_id ) );

	wp_safe_redirect( _180c_2fa_enrollment_url( array( 'one80c_2fa' => 'enrolled' ) ) );
	exit;
}
add_action( 'admin_post_one80c_2fa_enroll', '_180c_2fa_handle_enroll' );

// ============================================================
// Vérification à la connexion
// ============================================================

/** Durée de vie d'une vérification en attente (mot de passe validé, code non saisi). */
define( '_180C_2FA_PENDING_TTL', 5 * MINUTE_IN_SECONDS );

/** Nombre de codes erronés tolérés sur une même vérification en attente. */
define( '_180C_2FA_MAX_ATTEMPTS', 5 );

/** Action `wp-login.php?action=…` de l'écran de saisie du code. */
define( '_180C_2FA_LOGIN_ACTION', 'one80c_2fa' );

/**
 * Message unique pour tout échec de la seconde étape.
 *
 * Code faux, pas consommé, vérification expirée ou épuisée, IP bloquée, nonce
 * invalide : la cause n'est jamais détaillée.
 *
 * @return string
 */
function _180c_2fa_generic_error(): string {
	return __( 'Code invalide ou vérification expirée. Si le problème persiste, reconnectez-vous.', '180c' );
}

/**
 * Indique si la requête courante est une navigation humaine, capable de
 * suivre une redirection vers l'écran de saisie du code.
 *
 * @return bool False pour REST, XML-RPC, AJAX, cron, WP-CLI et requêtes JSON.
 */
function _180c_2fa_is_interactive_request(): bool {
	if ( wp_doing_ajax() || wp_doing_cron() || wp_is_json_request() ) {
		return false;
	}

	return ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		&& ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		&& ! ( defined( 'WP_CLI' ) && WP_CLI );
}

/**
 * Clé du transient de vérification en attente, liée à l'ID utilisateur.
 *
 * Une seule vérification en attente par compte : un nouveau login par mot de
 * passe remplace la précédente.
 *
 * @param int $user_id ID utilisateur.
 * @return string
 */
function _180c_2fa_pending_key( int $user_id ): string {
	return '_180c_2fa_pending_' . $user_id;
}

/**
 * Destination après validation du code : l'administration.
 *
 * Le `redirect_to` demandé n'est repris que s'il pointe DANS l'administration
 * (ex. retour sur l'écran d'édition d'où la session avait expiré).
 *
 * @param string $requested URL demandée.
 * @return string URL absolue dans wp-admin.
 */
function _180c_2fa_redirect_target( string $requested ): string {
	$admin     = admin_url();
	$validated = '' !== $requested ? wp_validate_redirect( $requested, '' ) : '';

	return ( '' !== $validated && 0 === strpos( $validated, $admin ) ) ? $validated : $admin;
}

/**
 * Seconde étape : intercepte un login par mot de passe réussi du compte ciblé.
 *
 * Priorité 30, après `wp_authenticate_username_password` / `…_email_password`
 * (20) et après `wp_authenticate_cookie` (30, enregistré avant le thème).
 *
 * Aucun cookie n'est posé : la redirection (ou l'erreur) intervient à
 * l'intérieur de `wp_signon()`, avant `wp_set_auth_cookie()`.
 *
 * @param WP_User|WP_Error|null $user     Résultat des filtres précédents.
 * @param string                $username Identifiant saisi (non utilisé : on lit `$user`).
 * @param string                $password Mot de passe saisi.
 * @return WP_User|WP_Error|null
 */
function _180c_2fa_authenticate( $user, $username, $password ) {
	unset( $username );

	if ( ! $user instanceof WP_User || ! _180c_2fa_is_target_user( $user ) ) {
		return $user;
	}

	// Sans mot de passe, c'est `wp_authenticate_cookie` qui a résolu une
	// session déjà établie (wp-login.php appelle wp_signon() à chaque
	// affichage) : rien à vérifier.
	if ( '' === (string) $password || ! _180c_2fa_is_enrolled( $user->ID ) ) {
		return $user;
	}

	// Hors navigation (REST, AJAX…) aucun écran ne peut être présenté :
	// refus sec, indiscernable d'un mauvais mot de passe.
	if ( ! _180c_2fa_is_interactive_request() ) {
		return new WP_Error( 'incorrect_password', __( 'Identifiants incorrects.', '180c' ) );
	}

	_180c_2fa_start_challenge( $user );

	return new WP_Error( 'incorrect_password', __( 'Identifiants incorrects.', '180c' ) ); // Jamais atteint.
}
add_filter( 'authenticate', '_180c_2fa_authenticate', 30, 3 );

/**
 * Échec fermé quand `_180C_2FA_LOGIN` n'est pas configurée.
 *
 * Sans la constante, le compte à protéger n'est plus identifiable. Plutôt que
 * de le laisser entrer sans second facteur, on refuse toute connexion par mot
 * de passe d'un administrateur, avec le message générique d'échec (aucune
 * indication sur l'existence du compte). Les autres rôles ne sont pas touchés.
 *
 * @param WP_User|WP_Error|null $user     Résultat des filtres précédents.
 * @param string                $username Identifiant saisi (non utilisé).
 * @param string                $password Mot de passe saisi.
 * @return WP_User|WP_Error|null
 */
function _180c_2fa_fail_closed( $user, $username, $password ) {
	unset( $username );

	if ( '' !== (string) _180C_2FA_LOGIN || ! $user instanceof WP_User || '' === (string) $password ) {
		return $user;
	}

	if ( ! user_can( $user, 'manage_options' ) ) {
		return $user;
	}

	if ( function_exists( '_180c_log' ) ) {
		_180c_log( '2FA : _180C_2FA_LOGIN absente, connexion administrateur refusée', array(), 'error' );
	}

	return new WP_Error( 'incorrect_password', __( 'Identifiants incorrects.', '180c' ) );
}
add_filter( 'authenticate', '_180c_2fa_fail_closed', 31, 3 ); // gitleaks:allow — nom de hook, pas un secret.

/**
 * Ouvre une vérification en attente et redirige vers l'écran de saisie.
 *
 * Le transient ne stocke que l'empreinte SHA-256 du jeton ; le jeton clair ne
 * transite que dans l'URL puis dans le formulaire. Il ne vaut rien seul : il
 * faut encore un code TOTP valide, en moins de 5 minutes et 5 essais.
 *
 * @param WP_User $user Compte ciblé, mot de passe validé.
 * @return void
 */
function _180c_2fa_start_challenge( WP_User $user ): void {
	$token = bin2hex( random_bytes( 32 ) );

	// phpcs:disable WordPress.Security.NonceVerification -- Champs du formulaire de login d'origine, déjà traité par wp_signon().
	$requested = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
	$remember  = ! empty( $_POST['rememberme'] );
	$interim   = isset( $_REQUEST['interim-login'] );
	// phpcs:enable

	set_transient(
		_180c_2fa_pending_key( $user->ID ),
		array(
			'token'       => hash( 'sha256', $token ),
			'remember'    => $remember,
			'interim'     => $interim,
			'redirect_to' => _180c_2fa_redirect_target( $requested ),
			'attempts'    => 0,
			'expires'     => time() + _180C_2FA_PENDING_TTL,
		),
		_180C_2FA_PENDING_TTL
	);

	$args = array(
		'action' => _180C_2FA_LOGIN_ACTION,
		'uid'    => $user->ID,
		'token'  => $token,
	);
	if ( $interim ) {
		$args['interim-login'] = '1';
	}

	// wp-login.php en dur (et non wp_login_url()) : l'écran vit sur un hook
	// `login_form_*`, qui n'existe que là.
	wp_safe_redirect( add_query_arg( $args, site_url( 'wp-login.php', 'login' ) ) );
	exit;
}

/**
 * Relit une vérification en attente et contrôle son jeton.
 *
 * @param int    $user_id ID utilisateur.
 * @param string $token   Jeton clair reçu.
 * @return array<string, mixed>|null Données en attente, ou null si absente, expirée ou jeton faux.
 */
function _180c_2fa_get_pending( int $user_id, string $token ): ?array {
	if ( $user_id <= 0 || '' === $token ) {
		return null;
	}

	$pending = get_transient( _180c_2fa_pending_key( $user_id ) );
	if ( ! is_array( $pending ) || empty( $pending['token'] ) || (int) ( $pending['expires'] ?? 0 ) <= time() ) {
		return null;
	}

	return hash_equals( (string) $pending['token'], hash( 'sha256', $token ) ) ? $pending : null;
}

/**
 * Comptabilise un code erroné sur la vérification en attente.
 *
 * L'échéance d'origine est conservée (pas de prolongation à chaque essai) ;
 * au-delà de `_180C_2FA_MAX_ATTEMPTS`, la vérification est détruite.
 *
 * @param int                       $user_id ID utilisateur.
 * @param array<string, mixed>|null $pending Données en attente.
 * @return array<string, mixed>|null Données mises à jour, ou null si détruites.
 */
function _180c_2fa_register_failure( int $user_id, ?array $pending ): ?array {
	if ( null === $pending ) {
		return null;
	}

	$pending['attempts'] = (int) $pending['attempts'] + 1;
	$remaining           = (int) $pending['expires'] - time();

	if ( $pending['attempts'] >= _180C_2FA_MAX_ATTEMPTS || $remaining <= 0 ) {
		delete_transient( _180c_2fa_pending_key( $user_id ) );
		return null;
	}

	set_transient( _180c_2fa_pending_key( $user_id ), $pending, $remaining );

	return $pending;
}

/**
 * Mémorise, pour la requête courante, le compte qui vient de valider son code.
 *
 * Seul laissez-passer du verrou `send_auth_cookies` pour une NOUVELLE session.
 *
 * @param int $user_id ID à marquer (0 = lecture seule).
 * @return int ID validé sur cette requête, 0 sinon.
 */
function _180c_2fa_validated_user_id( int $user_id = 0 ): int {
	static $validated = 0;

	if ( $user_id > 0 ) {
		$validated = $user_id;
	}

	return $validated;
}

/**
 * Écran `wp-login.php?action=one80c_2fa` : saisie et contrôle du code.
 *
 * @return void
 */
function _180c_2fa_login_screen(): void {
	global $interim_login;

	// wp-login.php n'initialise $interim_login qu'APRÈS les hooks login_form_*.
	// phpcs:disable WordPress.Security.NonceVerification -- Lecture du contexte ; le POST est contrôlé par nonce plus bas.
	$interim_login = isset( $_REQUEST['interim-login'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Global de wp-login.php, initialisé par le cœur après les hooks login_form_*.
	$user_id       = isset( $_REQUEST['uid'] ) ? absint( $_REQUEST['uid'] ) : 0;
	$token         = isset( $_REQUEST['token'] ) ? (string) preg_replace( '/[^a-f0-9]/', '', strtolower( sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) ) ) : '';
	// phpcs:enable

	// Le jeton est dans l'URL : il ne doit fuiter nulle part via Referer.
	header( 'Referrer-Policy: no-referrer' );

	$user    = _180c_2fa_is_target_user( $user_id ) ? get_userdata( $user_id ) : null;
	$pending = $user ? _180c_2fa_get_pending( $user_id, $token ) : null;
	$errors  = new WP_Error();

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		$nonce = isset( $_POST['_one80c_2fa_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_one80c_2fa_nonce'] ) ) : '';
		$code  = isset( $_POST['one80c_totp_code'] ) ? sanitize_text_field( wp_unslash( $_POST['one80c_totp_code'] ) ) : '';

		$valid = $user && null !== $pending
			&& wp_verify_nonce( $nonce, 'one80c_2fa_' . $user_id )
			&& ! _180c_is_ip_blocked( _180c_get_client_ip() )
			&& _180c_totp_verify( $user_id, $code );

		if ( $valid ) {
			_180c_2fa_complete_login( $user, $pending );
		}

		// Même compteur d'échecs et même blocage d'IP que le mot de passe.
		do_action( 'wp_login_failed', $user ? $user->user_login : '', new WP_Error( 'one80c_2fa_invalid', _180c_2fa_generic_error() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook du cœur, rejoué volontairement.
		_180c_log( '2FA code rejected.', array( 'user_id' => $user_id ), 'warning' );

		$pending = $user ? _180c_2fa_register_failure( $user_id, $pending ) : null;
		$errors->add( 'one80c_2fa_invalid', _180c_2fa_generic_error() );
	} elseif ( null === $pending ) {
		$errors->add( 'one80c_2fa_invalid', _180c_2fa_generic_error() );
	}

	_180c_2fa_render_login_screen( $user_id, $token, null !== $pending, $errors );
	exit;
}
add_action( 'login_form_' . _180C_2FA_LOGIN_ACTION, '_180c_2fa_login_screen' );

/**
 * Termine la connexion après un code valide.
 *
 * @param WP_User              $user    Compte ciblé.
 * @param array<string, mixed> $pending Vérification en attente validée.
 * @return void
 */
function _180c_2fa_complete_login( WP_User $user, array $pending ): void {
	global $interim_login;

	delete_transient( _180c_2fa_pending_key( $user->ID ) );

	_180c_2fa_validated_user_id( $user->ID );
	wp_set_auth_cookie( $user->ID, ! empty( $pending['remember'] ), is_ssl() );
	wp_set_current_user( $user->ID );

	/** This action is documented in wp-includes/user.php */
	do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook du cœur, rejoué comme wp_signon().

	// Reconnexion dans la modale « session expirée » de l'admin : même
	// réponse que wp-login.php, la classe `interim-login-success` ferme la modale.
	if ( ! empty( $pending['interim'] ) ) {
		$interim_login = 'success'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Même valeur que wp-login.php pour fermer la modale.
		login_header( '' );
		echo '<p class="message">' . esc_html__( 'Connexion réussie.', '180c' ) . '</p></div>';
		/** This action is documented in wp-login.php */
		do_action( 'login_footer' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook du cœur, rejoué comme wp-login.php.
		echo '</body></html>';
		exit;
	}

	wp_safe_redirect( (string) $pending['redirect_to'] );
	exit;
}

/**
 * Rend l'écran de saisie du code dans le gabarit natif de wp-login.php.
 *
 * @param int      $user_id     ID du compte.
 * @param string   $token       Jeton de la vérification en attente.
 * @param bool     $has_pending Vérification exploitable (sinon, lien de retour seul).
 * @param WP_Error $errors      Erreurs à afficher.
 * @return void
 */
function _180c_2fa_render_login_screen( int $user_id, string $token, bool $has_pending, WP_Error $errors ): void {
	global $interim_login;

	// Aucun message passé à login_header() : Simple JWT Login accroche une
	// fonction sans valeur de retour au FILTRE `login_message`, qui vide tout
	// message de wp-login.php. Le texte d'aide vit donc dans le formulaire.
	login_header( __( 'Vérification en deux étapes', '180c' ), '', $errors );

	if ( $has_pending ) {
		?>
		<form name="one80c-2fa-form" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php?action=' . _180C_2FA_LOGIN_ACTION, 'login_post' ) ); ?>" method="post" autocomplete="off">
			<p><?php esc_html_e( 'Saisissez le code à 6 chiffres affiché dans Mots de passe.', '180c' ); ?></p>
			<p>
				<label for="one80c-2fa-code"><?php esc_html_e( 'Code de vérification', '180c' ); ?></label>
				<input type="text" name="one80c_totp_code" id="one80c-2fa-code" class="input" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required>
			</p>
			<input type="hidden" name="uid" value="<?php echo esc_attr( (string) $user_id ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="_one80c_2fa_nonce" value="<?php echo esc_attr( wp_create_nonce( 'one80c_2fa_' . $user_id ) ); ?>">
			<?php if ( $interim_login ) : ?>
				<input type="hidden" name="interim-login" value="1">
			<?php endif; ?>
			<p class="submit">
				<input type="submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Vérifier', '180c' ); ?>">
			</p>
		</form>
		<?php
	} elseif ( ! $interim_login ) {
		?>
		<p id="nav"><a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Retour à la connexion', '180c' ); ?></a></p>
		<?php
	}

	login_footer( $has_pending ? 'one80c-2fa-code' : '' );
}

/**
 * Verrou final : aucun cookie de session NEUVE pour le compte enrôlé sans
 * second facteur validé sur la requête.
 *
 * Couvre tous les chemins qui appellent `wp_set_auth_cookie()` sans passer par
 * `authenticate` : autologin et middleware de Simple JWT Login, connexion app
 * `?180c_app_login=1`, ou toute extension future.
 *
 * Laissez-passer :
 * - le code vient d'être validé sur cette requête ;
 * - RÉÉMISSION d'une session existante : le jeton passé est celui du cookie
 *   `logged_in` présenté ET la session est toujours valide. C'est le cas du
 *   changement de mot de passe depuis le profil (`wp_update_user()`), qui
 *   ré-écrit le cookie de la session courante. Une nouvelle connexion, elle,
 *   crée un jeton neuf qui ne peut pas correspondre au cookie.
 *
 * @param bool   $send       Envoyer les cookies.
 * @param int    $expire     Expiration du cookie (non utilisé).
 * @param int    $expiration Expiration de la session (non utilisé).
 * @param int    $user_id    ID utilisateur.
 * @param string $scheme     Schéma (non utilisé).
 * @param string $token      Jeton de session.
 * @return bool
 */
function _180c_2fa_guard_auth_cookies( $send, $expire, $expiration, $user_id, $scheme, $token ) {
	unset( $expire, $expiration, $scheme );

	$user_id = (int) $user_id;
	if ( ! $send || $user_id <= 0 || ! _180c_2fa_is_target_user( $user_id ) ) {
		return $send;
	}

	if ( ! _180c_2fa_is_enrolled( $user_id ) || _180c_2fa_validated_user_id() === $user_id ) {
		return $send;
	}

	$token   = (string) $token;
	$cookie  = wp_parse_auth_cookie( '', 'logged_in' );
	$manager = WP_Session_Tokens::get_instance( $user_id );

	if ( is_array( $cookie ) && '' !== $token
		&& hash_equals( (string) $cookie['token'], $token )
		&& get_userdata( $user_id )->user_login === $cookie['username']
		&& $manager->verify( $token )
	) {
		return $send;
	}

	// Refus : la session que wp_set_auth_cookie() vient de créer est détruite,
	// l'utilisateur courant est remis à zéro et la requête s'arrête avant tout
	// effet de bord (`wp_login` des appelants, redirection d'autologin…).
	if ( '' !== $token ) {
		$manager->destroy( $token );
	}
	wp_set_current_user( 0 );

	_180c_log(
		'2FA: auth cookie refused (second factor missing).',
		array(
			'user_id' => $user_id,
			'ip'      => _180c_get_client_ip(),
		),
		'warning'
	);

	if ( ! _180c_2fa_is_interactive_request() || isset( $_GET['180c_app_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Détection du point d'entrée app, aucune action.
		wp_send_json_error( array( 'message' => __( 'Accès refusé.', '180c' ) ), 401 );
	}

	wp_die( esc_html__( 'Accès refusé.', '180c' ), '', array( 'response' => 403 ) );
}
add_filter( 'send_auth_cookies', '_180c_2fa_guard_auth_cookies', 10, 6 );

// ============================================================
// Fermeture des accès JWT (décision du 2026-09-16)
// ============================================================

/*
 * Simple JWT Login vérifie le mot de passe lui-même (`wp_check_password`) et ne
 * passe jamais par `authenticate` : sans ce bloc, `/auth` délivrerait un jeton
 * sur le seul mot de passe et `/autologin` en ferait une session admin. Le
 * compte protégé n'utilise pas les apps (un compte dédié les sert) : ses accès
 * JWT sont fermés INCONDITIONNELLEMENT, enrôlé ou non.
 *
 * Trois verrous complémentaires :
 * 1. `/auth`, `/auth/refresh`, `/autologin` refusés pour ce compte
 *    (`rest_pre_dispatch`) ;
 * 2. tout jeton de ce compte ignoré par les consommateurs JWT du thème
 *    (filtre `180c/jwt_payload_user_id`) ;
 * 3. le verrou `send_auth_cookies` ci-dessus, qui couvre aussi le middleware
 *    du plugin et un jeton lu depuis son cookie.
 */

/**
 * Lit la configuration de Simple JWT Login utile au blocage.
 *
 * @return array{namespace: string, url_key: string, header_key: string}
 */
function _180c_2fa_jwt_settings(): array {
	$settings = get_option( 'simple_jwt_login_settings' );
	if ( is_string( $settings ) ) {
		$settings = json_decode( $settings, true );
	}
	$settings = is_array( $settings ) ? $settings : array();
	$keys     = isset( $settings['request_keys'] ) && is_array( $settings['request_keys'] ) ? $settings['request_keys'] : array();

	$namespace = trim( (string) ( $settings['route_namespace'] ?? '' ), ' /\\' );

	return array(
		'namespace'  => '' !== $namespace ? $namespace : 'simple-jwt-login/v1',
		'url_key'    => (string) ( $keys['url'] ?? 'JWT' ),
		'header_key' => (string) ( $keys['header'] ?? 'Authorization' ),
	);
}

/**
 * Indique si un identifiant de connexion (login ou e-mail) désigne le compte protégé.
 *
 * @param mixed $identifier Valeur brute reçue.
 * @return bool
 */
function _180c_2fa_identifier_targets( $identifier ): bool {
	if ( ! is_scalar( $identifier ) || '' === (string) $identifier ) {
		return false;
	}

	$identifier = sanitize_text_field( (string) $identifier );
	$by_login   = get_user_by( 'login', $identifier );
	$by_email   = get_user_by( 'email', $identifier );

	return ( $by_login && _180c_2fa_is_target_user( $by_login ) )
		|| ( $by_email && _180c_2fa_is_target_user( $by_email ) );
}

/**
 * Indique si un JWT, même non vérifié ou expiré, se réclame du compte protégé.
 *
 * La signature n'est volontairement pas contrôlée : la seule conséquence
 * d'un faux positif est un refus, jamais un accès.
 *
 * @param string $jwt Jeton brut.
 * @return bool
 */
function _180c_2fa_jwt_targets( string $jwt ): bool {
	$parts = explode( '.', trim( $jwt ) );
	if ( 3 !== count( $parts ) ) {
		return false;
	}

	$payload = json_decode( (string) _180c_jwt_base64url_decode( $parts[1] ), true );
	if ( ! is_array( $payload ) ) {
		return false;
	}

	return ( isset( $payload['id'] ) && is_numeric( $payload['id'] ) && _180c_2fa_is_target_user( (int) $payload['id'] ) )
		|| _180c_2fa_identifier_targets( $payload['username'] ?? '' )
		|| _180c_2fa_identifier_targets( $payload['email'] ?? '' );
}

/**
 * Refuse `/auth`, `/auth/refresh` et `/autologin` de Simple JWT Login pour le
 * compte protégé.
 *
 * `/auth` répond exactement comme un mauvais mot de passe du plugin
 * (400, errorCode 48) : la route ne révèle pas que le compte est protégé.
 *
 * @param mixed           $result  Réponse pré-calculée.
 * @param WP_REST_Server  $server  Serveur REST (non utilisé).
 * @param WP_REST_Request $request Requête.
 * @return mixed
 */
function _180c_2fa_block_jwt_routes( $result, $server, $request ) {
	unset( $server );

	if ( null !== $result || ! $request instanceof WP_REST_Request ) {
		return $result;
	}

	$settings = _180c_2fa_jwt_settings();
	$base     = '/' . $settings['namespace'] . '/';
	$route    = untrailingslashit( (string) $request->get_route() );

	if ( 0 !== strpos( $route . '/', $base ) ) {
		return $result;
	}

	$endpoint = substr( $route, strlen( $base ) );

	if ( 'auth' === $endpoint ) {
		foreach ( array( 'username', 'email', 'login' ) as $param ) {
			if ( _180c_2fa_identifier_targets( $request->get_param( $param ) ) ) {
				wp_send_json_error(
					array(
						'message'   => __( 'Wrong user credentials.', 'simple-jwt-login' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Message du plugin reproduit à l'identique.
						'errorCode' => 48,
					),
					400
				);
			}
		}
		return $result;
	}

	if ( 'auth/refresh' !== $endpoint && 'autologin' !== $endpoint ) {
		return $result;
	}

	$candidates = array(
		(string) $request->get_param( $settings['url_key'] ),
		(string) $request->get_param( 'JWT' ),
		(string) $request->get_header( $settings['header_key'] ),
		_180c_jwt_server_auth_header(),
	);

	foreach ( $candidates as $candidate ) {
		$candidate = trim( (string) preg_replace( '/^Bearer\s+/i', '', $candidate ) );
		if ( '' !== $candidate && _180c_2fa_jwt_targets( $candidate ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', '180c' ) ), 401 );
		}
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', '_180c_2fa_block_jwt_routes', 5, 3 );

/**
 * Ignore tout JWT du compte protégé dans les consommateurs JWT du thème.
 *
 * @param int $user_id ID résolu depuis le jeton.
 * @return int 0 pour le compte protégé, ID inchangé sinon.
 */
function _180c_2fa_ignore_jwt_user( $user_id ) {
	return _180c_2fa_is_target_user( (int) $user_id ) ? 0 : $user_id;
}
add_filter( '180c/jwt_payload_user_id', '_180c_2fa_ignore_jwt_user' );
