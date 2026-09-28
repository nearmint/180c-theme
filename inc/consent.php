<?php
/**
 * Dispositif de consentement 180°C — source unique de vérité.
 *
 * Ce fichier définit TOUT ce dont dépendent la modale, les trois chargeurs de
 * traceurs et la preuve du consentement : nom du cookie, version du schéma,
 * durée de vie, endpoint REST, table de journal, purge.
 *
 * Pourquoi une source unique
 * --------------------------
 * La version du schéma vivait jusqu'ici en quatre exemplaires — une constante
 * JS, une constante PHP, un littéral en dur dans le script inline de la modale,
 * et une relecture côté Umami. Aucune ne faisait autorité. Or cette valeur est
 * précisément ce qui décide de re-solliciter ou non le visiteur : la désaligner
 * ne provoque aucune erreur, seulement un dispositif qui cesse silencieusement
 * de demander son avis à qui de droit. Tout part désormais d'ici.
 *
 * Règle : TOUTE modification de la liste des traceurs incrémente
 * _180C_CONSENT_VERSION. Les cookies d'une version antérieure sont traités
 * comme absents, donc le choix est redemandé.
 *
 * Le consentement n'est JAMAIS lu en PHP pour décider d'un rendu
 * ----------------------------------------------------------------
 * Les pages sont servies par WP Super Cache, qui ne varie pas sur les cookies
 * custom : un test côté serveur figerait le choix du premier visiteur pour tous
 * les suivants. Le HTML est donc identique pour tout le monde, et c'est le JS
 * qui lit le cookie. Ce fichier n'expose que de la configuration.
 *
 * L'unique exception est `_180c_umami_flag_login()` (inc/analytics/umami.php) :
 * elle s'exécute sur une requête POST de connexion, jamais servie depuis le
 * cache, donc peut lire le cookie sans risque.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// Constantes — la source unique
// ============================================================

/**
 * Nom du cookie de consentement.
 */
const _180C_CONSENT_COOKIE = '_180c_consent';

/**
 * Version du schéma du cookie.
 *
 * 1 → 2 : le payload passe des quatre clés Consent Mode v2
 * (`ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`) à
 * une forme portant la preuve : `{v, id, ts, analytics}`. Les signaux Consent
 * Mode restent émis vers gtag, mais ils sont désormais DÉRIVÉS de `analytics`
 * au lieu d'être stockés — ils n'ont jamais eu qu'une valeur possible chacun.
 *
 * Conséquence assumée : tout visiteur porteur d'un cookie v1 est resollicité.
 * C'est la règle du dispositif, et elle joue ici en faveur du visiteur — les
 * consentements v1 avaient été recueillis sans qu'aucune trace n'en soit
 * conservée.
 */
const _180C_CONSENT_VERSION = 2;

/**
 * Durée de vie du cookie, en jours.
 *
 * Vaut pour l'accord COMME pour le refus : un refus mémorisé moins longtemps
 * qu'un accord revient à redemander plus souvent à ceux qui ont dit non.
 */
const _180C_CONSENT_TTL_DAYS = 182;

/**
 * Durée de conservation par défaut du journal de preuve, en mois.
 *
 * 25 mois : la durée recommandée par la CNIL pour la conservation des preuves
 * de consentement. Surchargeable par le filtre `180c/consent_log_retention_months`.
 */
const _180C_CONSENT_RETENTION_MONTHS = 25;

/**
 * Hook du cron de purge du journal.
 */
const _180C_CONSENT_PURGE_HOOK = '_180c_consent_log_purge';

/**
 * Plafond de l'anti-abus de l'endpoint public, par fenêtre.
 */
const _180C_CONSENT_RATE_LIMIT = 20;

/**
 * Fenêtre de l'anti-abus, en secondes.
 */
const _180C_CONSENT_RATE_WINDOW = 300;

// ============================================================
// Configuration exposée au JS
// ============================================================

/**
 * Imprime la configuration du consentement pour les scripts de la page.
 *
 * Priorité 1 sur `wp_head` : cet objet doit exister avant TOUT script, aussi
 * bien les chargeurs GA4 / Umami (priorité 2) que le bundle principal qui
 * contient le module de consentement.
 *
 * Aucune donnée personnelle, aucun état de consentement : uniquement des
 * constantes de configuration, donc identiques pour tous les visiteurs et
 * parfaitement compatibles avec le cache page.
 *
 * @return void
 */
function _180c_consent_print_config() {
	$config = array(
		'cookie'    => _180C_CONSENT_COOKIE,
		'version'   => (int) _180C_CONSENT_VERSION,
		'ttlDays'   => (int) _180C_CONSENT_TTL_DAYS,
		'endpoint'  => esc_url_raw( rest_url( _180C_API_NAMESPACE . '/consent' ) ),
		'policyUrl' => esc_url_raw( _180c_consent_policy_url() ),
	);

	printf(
		'<script>window._180cConsent=%s;</script>' . "\n",
		wp_json_encode( $config )
	);
}
add_action( 'wp_head', '_180c_consent_print_config', 1 );

/**
 * URL de la page « Politique de cookies ».
 *
 * Le lien « Gestion des cookies » du footer pointait sur `/cookies/`, qui
 * n'existait pas : 404 en production. La page existe désormais, et le lien y
 * conduit par une navigation ordinaire — plus aucun clic n'est intercepté par
 * le JS. Ce helper est le seul point du thème qui connaisse cette adresse.
 *
 * @return string URL absolue.
 */
function _180c_consent_policy_url() {
	/**
	 * Filtre l'URL de la politique de cookies.
	 *
	 * @param string $url URL par défaut.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (string) apply_filters( '180c/consent_policy_url', home_url( '/cookies/' ) );
}

// ============================================================
// Interrupteur de gestion — shortcode [180c_consent_toggle]
// ============================================================

/**
 * Rend l'interrupteur « Mesure d'audience » et son bouton « Enregistrer ».
 *
 * Destiné à la page /cookies/. C'est le SEUL endroit du site où l'on revient
 * sur son choix : la modale, elle, ne recueille que le choix initial.
 *
 * Pourquoi un shortcode plutôt que du HTML collé dans la page
 * ----------------------------------------------------------
 * Le contenu de la page vit en base, saisi une fois à la main. Y coller le
 * markup de l'interrupteur l'aurait figé hors du dépôt : le premier renommage
 * de classe CSS l'aurait privé de ses styles, et le premier changement
 * d'attribut, de son comportement — sans rien casser d'assez visible pour
 * qu'on s'en aperçoive. Un shortcode garde le markup dans le thème, où il
 * évolue avec le CSS et le JS qui le servent.
 *
 * L'état n'est JAMAIS rendu côté serveur : la case part systématiquement
 * décochée, et `src/js/modules/consent.js` la coche depuis le cookie au
 * chargement. Lire le consentement en PHP figerait le choix du premier
 * visiteur dans le cache page pour tous les suivants — c'est l'invariant de
 * tout ce dispositif.
 *
 * @return string Markup de l'interrupteur.
 */
function _180c_consent_toggle_shortcode() {
	ob_start();
	?>
	<div class="consent__manage consent__manage--page">
		<label class="consent__switch">
			<input type="checkbox" data-consent-toggle>
			<span class="consent__switch-track" aria-hidden="true"></span>
			<span class="consent__switch-label">
				<span class="consent__switch-title"><?php esc_html_e( 'Mesure d’audience', '180c' ); ?></span>
				<span class="consent__switch-help"><?php esc_html_e( 'Google Analytics et Umami, pour comprendre quels contenus sont lus. Aucune publicité, aucun reciblage.', '180c' ); ?></span>
			</span>
		</label>

		<div class="consent__manage-actions">
			<button type="button" class="consent__btn consent__btn--accept" data-action="consent-save">
				<?php esc_html_e( 'Enregistrer', '180c' ); ?>
			</button>

			<?php
			/*
			 * Confirmation. Sur la page — contrairement à la modale, qui se
			 * ferme — accepter ne produit aucun changement visible : sans ce
			 * message, le visiteur ne saurait pas si son clic a porté.
			 * `role="status"` l'annonce au lecteur d'écran sans voler le focus.
			 */
			?>
			<p class="consent__manage-status" role="status" data-consent-saved hidden>
				<?php esc_html_e( 'Votre choix a bien été enregistré.', '180c' ); ?>
			</p>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( '180c_consent_toggle', '_180c_consent_toggle_shortcode' );

/**
 * Garantit la présence de l'interrupteur sur la page de politique de cookies.
 *
 * Le droit de retirer son consentement ne doit dépendre de rien qui puisse être
 * oublié. Or le contenu de cette page vit en base, saisi à la main : si le
 * shortcode en est absent — page pas encore mise à jour, bloc supprimé par
 * inadvertance, contenu réécrit — le visiteur n'a plus AUCUN moyen de revenir
 * sur son choix, puisque la modale ne recueille que le choix initial et que le
 * lien du footer mène ici.
 *
 * Ce filet a été ajouté après avoir constaté le cas en production : le lien du
 * footer avait été redirigé vers cette page avant que son contenu ne porte
 * l'interrupteur, laissant une fenêtre sans aucun moyen de retrait.
 *
 * L'ajout n'a lieu QUE si l'interrupteur est absent du rendu : quand le
 * shortcode est en place, ce filtre ne fait rien et la position choisie dans la
 * page est respectée.
 *
 * @param string $content Contenu rendu.
 * @return string
 */
function _180c_consent_ensure_toggle( $content ) {
	if ( is_admin() || ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}

	$policy_path = wp_parse_url( _180c_consent_policy_url(), PHP_URL_PATH );
	$policy_slug = $policy_path ? basename( untrailingslashit( $policy_path ) ) : '';

	if ( '' === $policy_slug || ! is_page( $policy_slug ) ) {
		return $content;
	}

	if ( false !== strpos( $content, 'data-consent-toggle' ) ) {
		return $content;
	}

	return $content . _180c_consent_toggle_shortcode();
}

/*
 * Priorité 20, et c'est la condition de justesse de tout ce filtre.
 *
 * WordPress détend les shortcodes sur `the_content` à la priorité 11. À la
 * priorité par défaut (10), ce filtre s'exécutait AVANT : il ne voyait que le
 * littéral `[180c_consent_toggle]`, jamais son rendu, concluait à l'absence
 * d'interrupteur et en ajoutait un second. La page en affichait deux, tous deux
 * fonctionnels et discordants dès qu'on touchait l'un des deux.
 *
 * Passer après `do_shortcode` fait porter le test sur ce qui compte vraiment :
 * y a-t-il, oui ou non, un interrupteur dans la page telle qu'elle sera servie.
 */
add_filter( 'the_content', '_180c_consent_ensure_toggle', 20 );

// ============================================================
// Journal de preuve — table
// ============================================================

/**
 * Nom complet de la table du journal de consentement.
 *
 * @return string
 */
function _180c_consent_table() {
	global $wpdb;
	return $wpdb->prefix . '180c_consent_log';
}

/**
 * Crée la table du journal via dbDelta si elle est absente.
 *
 * Exécutée à l'activation du thème (`after_switch_theme`). En production,
 * où le thème n'est jamais réactivé, la table se crée avec
 * un script SQL de création (non versionné).
 *
 * Aucune adresse IP n'est stockée — ni en clair, ni hachée. Le journal doit
 * prouver qu'un consentement a été recueilli, pas identifier qui l'a donné :
 * `consent_id` est un UUID tiré côté client, sans lien avec un compte, et
 * `ua_hash` sert uniquement à distinguer deux enregistrements d'un même
 * identifiant.
 *
 * @return void
 */
function _180c_consent_create_table() {
	global $wpdb;

	$table           = _180c_consent_table();
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	/*
	 * `recorded_at` n'a PAS de DEFAULT CURRENT_TIMESTAMP, et c'est délibéré :
	 * ce défaut vaut l'heure locale de la session MySQL, alors que
	 * `consented_at` est écrit en UTC. Les deux colonnes d'une même ligne
	 * auraient porté des fuseaux différents — un écart d'une à deux heures,
	 * silencieux, et exactement du genre à faire conclure à une anomalie
	 * d'horloge client lors d'un contrôle. Les deux sont donc écrites en UTC
	 * par PHP, et la purge compare à UTC_TIMESTAMP().
	 */
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		consent_id CHAR(36) NOT NULL,
		version SMALLINT UNSIGNED NOT NULL,
		analytics TINYINT(1) NOT NULL DEFAULT 0,
		consented_at DATETIME NOT NULL,
		recorded_at DATETIME NOT NULL,
		ua_hash CHAR(64) NOT NULL DEFAULT '',
		KEY idx_consent_id (consent_id),
		KEY idx_recorded_at (recorded_at)
	) {$charset_collate};";

	dbDelta( $sql );

	delete_transient( '_180c_consent_table_ok' );
}
add_action( 'after_switch_theme', '_180c_consent_create_table' );

/**
 * Durée de cache de la présence de la table, quand elle EXISTE.
 *
 * L'endpoint est public : il ne doit pas déclencher un `SHOW TABLES` par requête.
 */
const _180C_CONSENT_TABLE_CACHE_OK = HOUR_IN_SECONDS;

/**
 * Durée de cache quand la table est ABSENTE.
 *
 * Volontairement bien plus courte que le cas positif, et ce n'est pas une
 * symétrie oubliée. En production, la table n'est pas créée par le thème mais à
 * la main en phpMyAdmin (pas de WP-CLI sur cet hébergement) : rien ne vient
 * alors purger le transient. Avec une heure des deux côtés, l'endpoint aurait
 * continué de répondre 503 jusqu'à une heure APRÈS la création de la table, et
 * toutes les preuves de cette fenêtre auraient été perdues — en silence, avec
 * une table pourtant en place et un opérateur convaincu d'avoir fini.
 *
 * Deux minutes bornent la perte à la durée d'un café, sans rendre le
 * `SHOW TABLES` fréquent pour autant : le cas négatif ne dure que le temps du
 * déploiement.
 */
const _180C_CONSENT_TABLE_CACHE_MISSING = 2 * MINUTE_IN_SECONDS;

/**
 * Retourne vrai si la table du journal existe.
 *
 * @return bool
 */
function _180c_consent_table_exists() {
	$cached = get_transient( '_180c_consent_table_ok' );
	if ( false !== $cached ) {
		return (bool) $cached;
	}

	global $wpdb;
	$table = _180c_consent_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

	set_transient(
		'_180c_consent_table_ok',
		$exists ? 1 : 0,
		$exists ? _180C_CONSENT_TABLE_CACHE_OK : _180C_CONSENT_TABLE_CACHE_MISSING
	);

	return $exists;
}

// ============================================================
// Journal de preuve — endpoint REST
// ============================================================

/**
 * Enregistre la route REST de preuve du consentement.
 *
 * @return void
 */
function _180c_consent_register_route() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/consent',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_consent_rest_record',
			// Route délibérément publique : le consentement se recueille avant
			// toute authentification, et une modale qui exigerait un compte
			// n'aurait aucun sens. L'anti-abus tient lieu de garde.
			'permission_callback' => '__return_true',
			'args'                => array(
				'id'        => array(
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => '_180c_consent_is_uuid',
				),
				'v'         => array(
					'required' => true,
					'type'     => 'integer',
					'minimum'  => 1,
				),
				'analytics' => array(
					'required' => true,
					'type'     => 'boolean',
				),
				'ts'        => array(
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => '_180c_consent_is_iso8601',
				),
			),
		)
	);
}
add_action( 'rest_api_init', '_180c_consent_register_route' );

/**
 * Valide un UUID v4 canonique.
 *
 * @param mixed $value Valeur reçue.
 * @return bool
 */
function _180c_consent_is_uuid( $value ) {
	return is_string( $value )
		&& 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
}

/**
 * Valide un horodatage ISO 8601 en UTC (`2026-08-31T06:24:55.000Z`).
 *
 * @param mixed $value Valeur reçue.
 * @return bool
 */
function _180c_consent_is_iso8601( $value ) {
	return is_string( $value )
		&& 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,3})?Z$/', $value );
}

/**
 * Anti-abus de l'endpoint public.
 *
 * Compte les écritures par fenêtre glissante, sur une clé dérivée de l'IP par
 * HMAC. L'adresse elle-même n'est ni stockée ni journalisée : elle ne sert qu'à
 * dériver une clé de transient, qui expire avec la fenêtre.
 *
 * @return bool Vrai si la requête est dans les clous.
 */
function _180c_consent_rate_limit_ok() {
	$ip = function_exists( '_180c_get_client_ip' ) ? _180c_get_client_ip() : '';
	if ( '' === $ip ) {
		return true;
	}

	$key   = '_180c_consent_rl_' . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	$count = (int) get_transient( $key );

	if ( $count >= _180C_CONSENT_RATE_LIMIT ) {
		return false;
	}

	set_transient( $key, $count + 1, _180C_CONSENT_RATE_WINDOW );

	return true;
}

/**
 * Enregistre une preuve de consentement.
 *
 * Le journal est une PREUVE, jamais un verrou : si la table manque ou si
 * l'écriture échoue, le choix du visiteur reste pleinement effectif — il vit
 * dans son cookie, côté client. On répond alors en erreur pour que la panne
 * soit visible, sans jamais rejouer la modale ni annuler le choix.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_consent_rest_record( WP_REST_Request $request ) {
	if ( ! _180c_consent_rate_limit_ok() ) {
		return new WP_Error(
			'180c_consent_rate_limited',
			__( 'Trop de requêtes.', '180c' ),
			array( 'status' => 429 )
		);
	}

	if ( ! _180c_consent_table_exists() ) {
		return new WP_Error(
			'180c_consent_no_table',
			__( 'Journal de consentement indisponible.', '180c' ),
			array( 'status' => 503 )
		);
	}

	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
		? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
		: '';

	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$inserted = $wpdb->insert(
		_180c_consent_table(),
		array(
			'consent_id'   => (string) $request->get_param( 'id' ),
			'version'      => (int) $request->get_param( 'v' ),
			'analytics'    => $request->get_param( 'analytics' ) ? 1 : 0,
			// Horodatage du CLIENT, converti en UTC pour MySQL. C'est le moment
			// du clic ; `recorded_at` (défaut SQL) porte celui de l'écriture.
			// Les deux peuvent diverger — requête différée, horloge décalée — et
			// c'est justement pourquoi on garde les deux.
			'consented_at' => gmdate( 'Y-m-d H:i:s', strtotime( (string) $request->get_param( 'ts' ) ) ),
			'recorded_at'  => gmdate( 'Y-m-d H:i:s' ),
			'ua_hash'      => hash_hmac( 'sha256', $user_agent, wp_salt( 'auth' ) ),
		),
		array( '%s', '%d', '%d', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		return new WP_Error(
			'180c_consent_write_failed',
			__( 'Enregistrement impossible.', '180c' ),
			array( 'status' => 500 )
		);
	}

	return new WP_REST_Response( array( 'recorded' => true ), 201 );
}

// ============================================================
// Journal de preuve — purge
// ============================================================

/**
 * Supprime les preuves plus anciennes que la durée de conservation.
 *
 * @return void
 */
function _180c_consent_purge_log() {
	if ( ! _180c_consent_table_exists() ) {
		return;
	}

	/**
	 * Filtre la durée de conservation du journal de consentement, en mois.
	 *
	 * @param int $months Durée par défaut.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	$months = (int) apply_filters( '180c/consent_log_retention_months', _180C_CONSENT_RETENTION_MONTHS );
	$months = max( 1, $months );

	global $wpdb;
	$table = _180c_consent_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$deleted = $wpdb->query(
		$wpdb->prepare(
			// Le nom de table ne peut pas être un paramètre préparé ; il est
			// construit depuis $wpdb->prefix, jamais depuis une entrée externe.
			// UTC_TIMESTAMP() et non NOW() : `recorded_at` est écrit en UTC,
			// comparer à l'heure locale du serveur MySQL décalerait la purge.
			"DELETE FROM `{$table}` WHERE recorded_at < DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d MONTH )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$months
		)
	);

	if ( function_exists( '_180c_log' ) ) {
		_180c_log( 'Consent log purge done.', array( 'deleted' => absint( $deleted ) ) );
	}
}
add_action( _180C_CONSENT_PURGE_HOOK, '_180c_consent_purge_log' );

/**
 * Planifie la purge mensuelle si elle ne l'est pas déjà.
 *
 * @return void
 */
function _180c_consent_schedule_purge() {
	if ( ! wp_next_scheduled( _180C_CONSENT_PURGE_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'monthly', _180C_CONSENT_PURGE_HOOK );
	}
}
add_action( 'init', '_180c_consent_schedule_purge' );
