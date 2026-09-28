<?php
/**
 * Rate limiting des tentatives de login + intégration Cloudflare Turnstile.
 *
 * Anti-bruteforce basé sur la table custom `{prefix}180c_login_attempts`
 * (colonnes : ip, user_login, timestamp, success). Deux couches :
 *
 *  1. Journal d'audit : chaque issue de login (succès/échec) est consignée en
 *     base via les hooks WordPress (`wp_login_failed`, `wp_login`). Rotation
 *     mensuelle des entrées > 90 jours.
 *  2. Décision de limitation, calculée depuis ce journal :
 *       - Turnstile affiché à partir de 3 échecs (15 min, depuis le dernier
 *         succès de l'IP) ;
 *       - blocage 1 h déclenché à 5 échecs en 15 min (verrou transient).
 *
 * Les clés Cloudflare Turnstile sont stockées en options WordPress
 * (`_180c_turnstile_site_key`, `_180c_turnstile_secret_key`), éditables dans
 * Réglages > Général. Une constante (`TURNSTILE_SITE_KEY` /
 * `TURNSTILE_SECRET_KEY`) reste prioritaire si définie (wp-config).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// Helpers IP
// ============================================================

/**
 * Indique si une adresse fait partie des proxys de confiance.
 *
 * Proxys de confiance : adresses privées ou réservées (reverse proxy sur réseau
 * interne, environnement local) et, en production, la liste optionnelle
 * `_180C_TRUSTED_PROXIES` (wp-config.php) — IP ou plages CIDR séparées par des
 * virgules. Sans cette constante, aucun proxy public n'est cru.
 *
 * @param string $ip Adresse à tester.
 * @return bool
 */
function _180c_is_trusted_proxy( string $ip ): bool {
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return true;
	}

	$list = defined( '_180C_TRUSTED_PROXIES' ) ? (string) _180C_TRUSTED_PROXIES : '';
	foreach ( array_filter( array_map( 'trim', explode( ',', $list ) ) ) as $range ) {
		if ( _180c_ip_in_range( $ip, $range ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Teste l'appartenance d'une IP à une adresse ou une plage CIDR (IPv4 / IPv6).
 *
 * @param string $ip    Adresse testée.
 * @param string $range Adresse seule ou plage `adresse/préfixe`.
 * @return bool
 */
function _180c_ip_in_range( string $ip, string $range ): bool {
	if ( ! str_contains( $range, '/' ) ) {
		return inet_pton( $ip ) !== false && inet_pton( $ip ) === @inet_pton( $range ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- plage mal formée : simple non-correspondance.
	}

	list( $subnet, $bits ) = explode( '/', $range, 2 );

	$ip_bin  = inet_pton( $ip );
	$net_bin = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- idem.
	$bits    = (int) $bits;

	if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) || $bits < 0 || $bits > strlen( $ip_bin ) * 8 ) {
		return false;
	}

	$bytes = intdiv( $bits, 8 );
	$rest  = $bits % 8;

	if ( 0 !== strncmp( $ip_bin, $net_bin, $bytes ) ) {
		return false;
	}

	if ( 0 === $rest ) {
		return true;
	}

	$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;

	return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $net_bin[ $bytes ] ) & $mask );
}

/**
 * Retourne l'IP réelle du visiteur.
 *
 * `REMOTE_ADDR` fait foi. Les en-têtes `CF-Connecting-IP` et `X-Forwarded-For`
 * sont fournis par le client et donc falsifiables : ils ne sont lus que si la
 * connexion vient d'un proxy de confiance (`_180c_is_trusted_proxy()`). Dans ce
 * cas, `X-Forwarded-For` est parcouru de droite à gauche et la première adresse
 * qui n'est pas elle-même un proxy de confiance est retenue — l'entrée la plus à
 * gauche, posée par le client, n'est jamais crue d'office.
 *
 * Vérifié sur la production (derrière le CDN de l'hébergeur) : `REMOTE_ADDR`
 * porte bien l'IP du visiteur, les connexions ne partagent pas une adresse
 * commune.
 *
 * @return string
 */
function _180c_get_client_ip(): string {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( ! filter_var( $remote, FILTER_VALIDATE_IP ) ) {
		return '0.0.0.0';
	}

	if ( ! _180c_is_trusted_proxy( $remote ) ) {
		return $remote;
	}

	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && defined( '_180C_TRUSTED_PROXIES' ) ) {
		$cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
			return $cf;
		}
	}

	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		$hops      = array_reverse( array_map( 'trim', explode( ',', $forwarded ) ) );
		foreach ( $hops as $hop ) {
			if ( ! filter_var( $hop, FILTER_VALIDATE_IP ) ) {
				break;
			}
			if ( ! _180c_is_trusted_proxy( $hop ) ) {
				return $hop;
			}
		}
	}

	return $remote;
}

// ============================================================
// Accès DB — table d'audit
// ============================================================

/**
 * Retourne le nom complet (préfixé) de la table des tentatives de login.
 *
 * @return string
 */
function _180c_login_attempts_table(): string {
	global $wpdb;
	return $wpdb->prefix . '180c_login_attempts';
}

/**
 * Crée la table des tentatives de login via dbDelta si elle est absente.
 *
 * Exécutée à l'activation du thème (`after_switch_theme`). En production,
 * la table peut aussi être créée via le SQL one-shot
 * le script SQL de création des tables (non versionné) — table `{prefix}180c_login_attempts`.
 *
 * @return void
 */
function _180c_create_login_attempts_table(): void {
	global $wpdb;

	$table           = _180c_login_attempts_table();
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// `timestamp` est volontairement conservé comme nom de colonne.
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		ip VARCHAR(45) NOT NULL,
		user_login VARCHAR(191) NOT NULL DEFAULT '',
		`timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		success TINYINT(1) NOT NULL DEFAULT 0,
		KEY idx_ip_time (ip, `timestamp`),
		KEY idx_success (success)
	) {$charset_collate};";

	dbDelta( $sql );

	// Rafraîchit le cache d'existence de la table.
	delete_transient( '_180c_login_attempts_table_ok' );
}

/**
 * Retourne vrai si la table des tentatives existe.
 *
 * Résultat mis en cache dans un transient pour éviter un SHOW TABLES à chaque appel.
 *
 * @return bool
 */
function _180c_login_attempts_table_exists(): bool {
	$cached = get_transient( '_180c_login_attempts_table_ok' );
	if ( false !== $cached ) {
		return (bool) $cached;
	}

	global $wpdb;
	$table = _180c_login_attempts_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	set_transient( '_180c_login_attempts_table_ok', $exists ? 1 : 0, HOUR_IN_SECONDS );
	return $exists;
}

/**
 * Compte les tentatives de login échouées d'une IP sur les N dernières minutes,
 * postérieures au dernier login réussi de cette IP (réinitialisation au succès).
 *
 * @param string $ip      Adresse IP.
 * @param int    $minutes Fenêtre temporelle en minutes.
 * @return int
 */
function _180c_count_recent_failed_attempts( string $ip, int $minutes = 15 ): int {
	if ( ! _180c_login_attempts_table_exists() ) {
		return 0;
	}

	global $wpdb;
	$table = _180c_login_attempts_table();

	// Les échecs comptabilisés sont ceux survenus dans la fenêtre ET après le
	// dernier succès de l'IP : un login réussi remet donc le compteur à zéro.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}`
			WHERE ip = %s
			  AND success = 0
			  AND `timestamp` > DATE_SUB( NOW(), INTERVAL %d MINUTE )
			  AND `timestamp` > COALESCE(
			        ( SELECT MAX( a2.`timestamp` ) FROM `{$table}` a2 WHERE a2.ip = %s AND a2.success = 1 ),
			        '1970-01-01 00:00:00'
			      )",
			$ip,
			$minutes,
			$ip
		)
	);
	// phpcs:enable

	return absint( $count );
}

/**
 * Enregistre une tentative de login dans le journal d'audit.
 *
 * @param string $ip         Adresse IP.
 * @param string $user_login Identifiant saisi.
 * @param bool   $success    Succès (true) ou échec (false).
 * @return void
 */
function _180c_record_login_attempt( string $ip, string $user_login, bool $success ): void {
	if ( ! _180c_login_attempts_table_exists() ) {
		return;
	}

	// La colonne `timestamp` est volontairement omise : elle est renseignée par
	// le DEFAULT CURRENT_TIMESTAMP (heure du serveur MySQL), cohérente avec les
	// NOW() des requêtes de comptage — évite tout décalage WP/MySQL de fuseau.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->insert(
		_180c_login_attempts_table(),
		array(
			'ip'         => $ip,
			'user_login' => mb_substr( $user_login, 0, 191 ),
			'success'    => $success ? 1 : 0,
		),
		array( '%s', '%s', '%d' )
	);
}

// ============================================================
// Verrou de blocage (transient — durée fixe 1 h)
// ============================================================

/**
 * Clé de transient du verrou de blocage pour une IP.
 *
 * @param string $ip Adresse IP.
 * @return string
 */
function _180c_login_block_key( string $ip ): string {
	return '_180c_login_block_' . md5( $ip );
}

/**
 * Déclenche un blocage de 1 h si l'IP a atteint 5 échecs en 15 min.
 *
 * Le verrou a une durée fixe : on ne réinitialise pas son TTL si déjà posé,
 * afin de respecter la durée de blocage de 1 h prévue par la spec.
 *
 * @param string $ip Adresse IP.
 * @return void
 */
function _180c_trip_block_if_needed( string $ip ): void {
	if ( _180c_count_recent_failed_attempts( $ip, 15 ) >= 5 ) {
		$key = _180c_login_block_key( $ip );
		if ( false === get_transient( $key ) ) {
			set_transient( $key, time(), HOUR_IN_SECONDS );
			_180c_log( 'Login IP blocked for 1h (5 fails / 15 min).', array( 'ip' => $ip ), 'warning' );
		}
	}
}

/**
 * Lève le blocage d'une IP (après un login réussi).
 *
 * @param string $ip Adresse IP.
 * @return void
 */
function _180c_clear_ip_block( string $ip ): void {
	delete_transient( _180c_login_block_key( $ip ) );
}

/**
 * Retourne vrai si l'IP est actuellement bloquée.
 *
 * Bloquée si le verrou 1 h est posé, ou (filet de sécurité) si 5 échecs ont
 * eu lieu dans les 15 dernières minutes.
 *
 * @param string $ip Adresse IP.
 * @return bool
 */
function _180c_is_ip_blocked( string $ip ): bool {
	if ( false !== get_transient( _180c_login_block_key( $ip ) ) ) {
		return true;
	}
	return _180c_count_recent_failed_attempts( $ip, 15 ) >= 5;
}

/**
 * Retourne vrai si le widget Turnstile doit être affiché.
 *
 * Conditions : une site key est configurée ET l'IP cumule au moins 3 échecs
 * récents (15 min, depuis le dernier succès).
 *
 * @param string $ip Adresse IP.
 * @return bool
 */
function _180c_should_show_turnstile( string $ip ): bool {
	if ( '' === _180c_turnstile_site_key() ) {
		return false;
	}
	return _180c_count_recent_failed_attempts( $ip, 15 ) >= 3;
}

// ============================================================
// Cloudflare Turnstile — clés + validation
// ============================================================

/**
 * Retourne la site key Turnstile (publique).
 *
 * Constante `TURNSTILE_SITE_KEY` prioritaire, sinon option WordPress.
 *
 * @return string
 */
function _180c_turnstile_site_key(): string {
	if ( defined( 'TURNSTILE_SITE_KEY' ) && '' !== (string) TURNSTILE_SITE_KEY ) {
		return (string) TURNSTILE_SITE_KEY;
	}
	return (string) get_option( '_180c_turnstile_site_key', '' );
}

/**
 * Retourne la secret key Turnstile (validation serveur).
 *
 * Constante `TURNSTILE_SECRET_KEY` prioritaire, sinon option WordPress.
 *
 * @return string
 */
function _180c_turnstile_secret_key(): string {
	if ( defined( 'TURNSTILE_SECRET_KEY' ) && '' !== (string) TURNSTILE_SECRET_KEY ) {
		return (string) TURNSTILE_SECRET_KEY;
	}
	return (string) get_option( '_180c_turnstile_secret_key', '' );
}

/**
 * Valide un token Turnstile auprès de l'API Cloudflare.
 *
 * Si aucune secret key n'est configurée (dev), retourne true (Turnstile désactivé).
 *
 * @param string $token Token soumis par le client (cf-turnstile-response).
 * @return bool
 */
function _180c_validate_turnstile( string $token ): bool {
	$secret = _180c_turnstile_secret_key();

	if ( '' === $secret ) {
		// Dégradation silencieuse : Turnstile non configuré.
		return true;
	}

	if ( '' === $token ) {
		return false;
	}

	$response = wp_remote_post(
		'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		array(
			'timeout' => 5,
			'body'    => array(
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => _180c_get_client_ip(),
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		_180c_log( 'Turnstile validation error.', array( 'error' => $response->get_error_message() ), 'error' );
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	return ! empty( $body['success'] );
}

// ============================================================
// Réglages — clés Turnstile (Réglages > Général)
// ============================================================

/**
 * Enregistre les options Turnstile et leurs champs dans Réglages > Général.
 *
 * @return void
 */
function _180c_register_turnstile_settings(): void {
	register_setting(
		'general',
		'_180c_turnstile_site_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
	register_setting(
		'general',
		'_180c_turnstile_secret_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	add_settings_field(
		'_180c_turnstile_site_key',
		__( 'Cloudflare Turnstile — Site Key', '180c' ),
		'_180c_render_turnstile_site_key_field',
		'general'
	);
	add_settings_field(
		'_180c_turnstile_secret_key',
		__( 'Cloudflare Turnstile — Secret Key', '180c' ),
		'_180c_render_turnstile_secret_key_field',
		'general'
	);
}
add_action( 'admin_init', '_180c_register_turnstile_settings' );

/**
 * Rend le champ Site Key.
 *
 * @return void
 */
function _180c_render_turnstile_site_key_field(): void {
	$constant = defined( 'TURNSTILE_SITE_KEY' ) && '' !== (string) TURNSTILE_SITE_KEY;
	printf(
		'<input type="text" id="_180c_turnstile_site_key" name="_180c_turnstile_site_key" value="%s" class="regular-text" %s>',
		esc_attr( (string) get_option( '_180c_turnstile_site_key', '' ) ),
		$constant ? 'disabled' : ''
	);
	echo '<p class="description">' . esc_html__( 'Clé publique du widget anti-bruteforce sur les pages de connexion / inscription.', '180c' ) . '</p>';
	if ( $constant ) {
		echo '<p class="description"><em>' . esc_html__( 'Définie via la constante TURNSTILE_SITE_KEY (wp-config).', '180c' ) . '</em></p>';
	}
}

/**
 * Rend le champ Secret Key.
 *
 * @return void
 */
function _180c_render_turnstile_secret_key_field(): void {
	$constant = defined( 'TURNSTILE_SECRET_KEY' ) && '' !== (string) TURNSTILE_SECRET_KEY;
	printf(
		'<input type="password" id="_180c_turnstile_secret_key" name="_180c_turnstile_secret_key" value="%s" class="regular-text" autocomplete="off" %s>',
		esc_attr( (string) get_option( '_180c_turnstile_secret_key', '' ) ),
		$constant ? 'disabled' : ''
	);
	echo '<p class="description">' . esc_html__( 'Clé secrète (validation serveur). Idéalement définie via la constante TURNSTILE_SECRET_KEY.', '180c' ) . '</p>';
	if ( $constant ) {
		echo '<p class="description"><em>' . esc_html__( 'Définie via la constante TURNSTILE_SECRET_KEY (wp-config).', '180c' ) . '</em></p>';
	}
}

// ============================================================
// Hooks WordPress — blocage + journalisation
// ============================================================

/**
 * Bloque l'authentification si l'IP est bannie (avant la vérification du mot de passe).
 *
 * Hookée sur `wp_authenticate_user` : couvre wp-login.php comme le formulaire
 * custom (via wp_signon). Message distinct du message générique d'identifiants.
 *
 * @param \WP_User|\WP_Error $user     Utilisateur résolu ou erreur en amont.
 * @param string             $password Mot de passe soumis (non utilisé).
 * @return \WP_User|\WP_Error
 */
function _180c_block_authentication( $user, $password ) {
	unset( $password );

	if ( is_wp_error( $user ) ) {
		return $user;
	}

	if ( _180c_is_ip_blocked( _180c_get_client_ip() ) ) {
		return new WP_Error(
			'_180c_too_many_attempts',
			__( 'Trop de tentatives de connexion. Veuillez réessayer dans 1 heure.', '180c' )
		);
	}

	return $user;
}
add_filter( 'wp_authenticate_user', '_180c_block_authentication', 30, 2 );

/**
 * Journalise un échec de login et déclenche le blocage si le seuil est atteint.
 *
 * Si l'IP est déjà bloquée, on n'enregistre plus rien (anti-flood du journal).
 *
 * @param string $username Identifiant qui a échoué.
 * @return void
 */
function _180c_on_login_failed( $username ): void {
	$ip = _180c_get_client_ip();

	if ( _180c_is_ip_blocked( $ip ) ) {
		return;
	}

	_180c_record_login_attempt( $ip, (string) $username, false );
	_180c_trip_block_if_needed( $ip );
}
add_action( 'wp_login_failed', '_180c_on_login_failed' );

/**
 * Journalise un login réussi et lève le blocage de l'IP.
 *
 * @param string   $user_login Identifiant connecté.
 * @param \WP_User $user       Objet utilisateur.
 * @return void
 */
function _180c_on_login_success( string $user_login, \WP_User $user ): void {
	unset( $user );
	$ip = _180c_get_client_ip();
	_180c_record_login_attempt( $ip, $user_login, true );
	_180c_clear_ip_block( $ip );
}
add_action( 'wp_login', '_180c_on_login_success', 10, 2 );

// ============================================================
// Cron mensuel de purge (rotation 90 jours)
// ============================================================

/**
 * Ajoute une récurrence cron « monthly » (30 jours), absente du cœur WP.
 *
 * @param array $schedules Récurrences existantes.
 * @return array
 */
function _180c_add_monthly_cron_schedule( array $schedules ): array {
	if ( ! isset( $schedules['monthly'] ) ) {
		$schedules['monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Une fois par mois', '180c' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', '_180c_add_monthly_cron_schedule' ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected

/**
 * Supprime les tentatives de login vieilles de plus de 90 jours.
 *
 * @return void
 */
function _180c_login_attempts_cleanup(): void {
	if ( ! _180c_login_attempts_table_exists() ) {
		return;
	}

	global $wpdb;
	$table = _180c_login_attempts_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$deleted = $wpdb->query( "DELETE FROM `{$table}` WHERE `timestamp` < DATE_SUB( NOW(), INTERVAL 90 DAY )" );

	_180c_log( 'Login attempts cleanup done.', array( 'deleted' => absint( $deleted ) ) );
}
add_action( '_180c_login_attempts_cleanup', '_180c_login_attempts_cleanup' );

/**
 * Planifie le cron quotidien de purge si nécessaire.
 *
 * La purge tournait tous les 30 jours. À 90 jours de rétention, la table
 * pouvait donc retenir jusqu'à 120 jours de tentatives entre deux passages.
 * En quotidien, le plafond retombe à 91 jours et la table cesse de gonfler
 * par à-coups.
 *
 * Replanification obligatoire : `wp_next_scheduled()` ne renvoie que la date
 * du prochain passage, il ne dit rien de la récurrence. Un événement déjà
 * planifié en « monthly » conserverait ce rythme indéfiniment — changer
 * l'argument de `wp_schedule_event()` ne suffit pas, il faut désinscrire
 * l'ancien. Idempotent : une fois le cron passé en « daily », les appels
 * suivants ne font plus rien.
 *
 * @return void
 */
function _180c_schedule_login_cleanup(): void {
	$next = wp_next_scheduled( '_180c_login_attempts_cleanup' );

	if ( $next && 'daily' !== wp_get_schedule( '_180c_login_attempts_cleanup' ) ) {
		wp_clear_scheduled_hook( '_180c_login_attempts_cleanup' );
		$next = false;
	}

	if ( ! $next ) {
		wp_schedule_event( time(), 'daily', '_180c_login_attempts_cleanup' );
	}
}
add_action( 'init', '_180c_schedule_login_cleanup' );
