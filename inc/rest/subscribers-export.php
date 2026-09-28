<?php
/**
 * Export CRM des utilisateurs vers Google Sheets.
 *
 * Endpoint : GET /wp-json/180c/v1/subscribers-export?page=1&per_page=500
 * Auth     : header « X-180C-Export-Key: <_180C_EXPORT_API_KEY> ».
 *
 * IMPORTANT — pourquoi pas « Authorization: Bearer » :
 * le plugin Simple JWT Login installe un middleware qui
 * intercepte TOUT en-tête « Authorization: Bearer <x> » sur l'ensemble des
 * routes REST du site et répond 400 « Wrong number of segments » dès que la
 * valeur n'est pas un JWT à trois segments — avant même l'exécution du
 * permission_callback. Une clé opaque ne peut donc pas transiter par Bearer
 * ici (vérifié en Phase 2, y compris sur wp/v2/types). X-180C-Export-Key est
 * le canal principal ; Bearer reste géré par _180c_export_request_key() au cas
 * où le middleware disparaîtrait, mais ne doit pas être utilisé.
 *
 * Dans les deux cas la clé ne transite QUE par en-tête, jamais en query
 * string : l'exigence de sécurité est préservée.
 *
 * Sens unique WordPress -> Sheets, lecture seule. Une ligne par utilisateur,
 * 40 colonnes agrégées (compte, facturation, WooCommerce, abonnement,
 * engagement, marketing).
 *
 * Contrat de colonnes : la constante _180C_EXPORT_COLUMNS fait foi et est
 * renvoyée dans « meta.columns ». Toute évolution impose de bumper
 * _180C_EXPORT_SCHEMA_VERSION (l'Apps Script refuse une version inattendue,
 * ce qui évite un désalignement silencieux des colonnes du Sheet).
 *
 * Sécurité : whitelist stricte. Aucune meta hors _180C_EXPORT_META_KEYS n'est
 * lue ; user_pass, user_activation_key et session_tokens ne sortent jamais.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Version du schéma de colonnes. À bumper à chaque changement de colonnes.
 */
define( '_180C_EXPORT_SCHEMA_VERSION', '1.0.0' );

/**
 * Statuts de commande considérés comme « payés » pour les agrégats LTV.
 *
 * Constaté en Phase 0 : wc-completed (l'essentiel des commandes) + wc-processing (marginal).
 * Les remboursements (wc-refunded) sont exclus.
 */
define( '_180C_EXPORT_PAID_STATUSES', 'wc-completed,wc-processing' );

/**
 * Plans de membership « payants » (le plan gratuit 27805 « Inscrits au site »
 * est exclu : il signifie seulement « compte créé » et n'a aucune valeur CRM).
 */
define( '_180C_EXPORT_MEMBERSHIP_PLANS', '13115885,17543,13102513,13072688' );

/**
 * Ordre canonique des colonnes exportées. Contrat avec l'Apps Script.
 *
 * @return string[]
 */
function _180c_export_columns() {
	return array(
		// Identité (8).
		'user_id',
		'user_login',
		'user_email',
		'display_name',
		'first_name',
		'last_name',
		'user_registered',
		'roles',
		// Facturation (8).
		'billing_company',
		'billing_address_1',
		'billing_address_2',
		'billing_postcode',
		'billing_city',
		'billing_country',
		'billing_phone',
		'billing_email',
		// WooCommerce (7).
		'orders_count',
		'orders_total_ltv',
		'orders_avg',
		'first_order_date',
		'last_order_date',
		'last_order_status',
		'paying_customer',
		// Abonnement (9).
		'subscription_id',
		'subscription_status',
		'on_hold_reason',
		'subscription_plan',
		'subscription_start',
		'next_payment_date',
		'subscription_end',
		'membership_status',
		'membership_since',
		// Engagement (3).
		'last_seen_at',
		'wc_last_active',
		'favorites_count',
		// Marketing (4).
		'newsletter_optin_free',
		'newsletter_optin_premium',
		'mailchimp_is_subscribed',
		'mailchimp_status',
		// Technique (1).
		'profile_updated_at',
	);
}

/**
 * Whitelist stricte des meta_keys lues dans usermeta.
 *
 * Rien d'autre ne sort de la base. Toute clé absente de cette liste est
 * ignorée, y compris si elle est ajoutée un jour par un plugin.
 *
 * @return string[]
 */
function _180c_export_meta_keys() {
	global $wpdb;

	return array(
		'first_name',
		'last_name',
		'billing_company',
		'billing_address_1',
		'billing_address_2',
		'billing_postcode',
		'billing_city',
		'billing_country',
		'billing_phone',
		'billing_email',
		'paying_customer',
		'wc_last_active',
		'last_update',
		'_180c_last_seen',
		'_180c_newsletter_free',
		'_180c_newsletter_premium',
		'mailchimp_woocommerce_is_subscribed',
		'_mailchimp_sync_status',
		$wpdb->prefix . 'capabilities',
	);
}

/**
 * Mapping produit -> libellé de plan d'abonnement (constaté en Phase 0).
 *
 * @return array<int,string>
 */
function _180c_export_plan_map() {
	return array(
		13121518 => 'mensuel',
		13121519 => 'annuel',
		13142916 => 'gift',
		13102512 => 'illimite_recettes',
		13045165 => 'illimite_180c',
	);
}

/**
 * Récupère la clé d'authentification de l'export depuis les en-têtes.
 *
 * Priorité à X-180C-Export-Key (canal principal, cf. docblock du fichier :
 * Authorization est monopolisé par le middleware Simple JWT Login). Le repli
 * Bearer couvre le cas où ce middleware ne serait plus là, en gérant les
 * variantes Apache/CGI (HTTP_AUTHORIZATION, REDIRECT_HTTP_AUTHORIZATION).
 *
 * La clé n'est jamais lue depuis la query string.
 *
 * @return string Clé extraite, ou chaîne vide.
 */
function _180c_export_request_key() {
	$auth = '';

	// 1. En-tête dédié (canal principal).
	if ( ! empty( $_SERVER['HTTP_X_180C_EXPORT_KEY'] ) ) {
		return trim( wp_unslash( $_SERVER['HTTP_X_180C_EXPORT_KEY'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	if ( function_exists( 'getallheaders' ) ) {
		foreach ( (array) getallheaders() as $name => $value ) {
			if ( 0 === strcasecmp( $name, 'X-180C-Export-Key' ) ) {
				return trim( (string) $value );
			}
			if ( 0 === strcasecmp( $name, 'Authorization' ) ) {
				$auth = trim( (string) $value );
			}
		}
	}

	// 2. Repli Bearer. Réutilise le helper JWT du thème : gère
	// HTTP_AUTHORIZATION et REDIRECT_HTTP_AUTHORIZATION (cf. inc/rest/auth.php).
	if ( '' === $auth && function_exists( '_180c_jwt_server_auth_header' ) ) {
		$auth = _180c_jwt_server_auth_header();
	}

	if ( '' === $auth ) {
		return '';
	}

	if ( preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ) {
		return trim( $m[1] );
	}

	return '';
}

/**
 * Permission callback de l'endpoint d'export.
 *
 * La clé n'est jamais acceptée en query string : uniquement via en-tête.
 *
 * @return true|WP_Error
 */
function _180c_export_check_auth() {
	if ( ! defined( '_180C_EXPORT_API_KEY' ) || '' === (string) _180C_EXPORT_API_KEY ) {
		return new WP_Error(
			'export_not_configured',
			__( 'Export non configuré : la constante _180C_EXPORT_API_KEY est absente.', '180c' ),
			array( 'status' => 503 )
		);
	}

	$provided = _180c_export_request_key();

	if ( '' === $provided || ! hash_equals( (string) _180C_EXPORT_API_KEY, $provided ) ) {
		// 401 sans détail : ne pas indiquer si la clé est absente ou fausse.
		return new WP_Error(
			'export_unauthorized',
			__( 'Non autorisé.', '180c' ),
			array( 'status' => 401 )
		);
	}

	return true;
}

/**
 * Enregistre la route REST d'export.
 *
 * @return void
 */
function _180c_export_register_route() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/subscribers-export',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_export_handle',
			'permission_callback' => '_180c_export_check_auth',
			'args'                => array(
				'page'     => array(
					'default'           => 1,
					'sanitize_callback' => 'absint',
					'validate_callback' => static function ( $v ) {
						return absint( $v ) >= 1;
					},
				),
				'per_page' => array(
					'default'           => 200,
					'sanitize_callback' => 'absint',
					'validate_callback' => static function ( $v ) {
						$v = absint( $v );
						return $v >= 1 && $v <= 500;
					},
				),
			),
		)
	);
}
add_action( 'rest_api_init', '_180c_export_register_route' );

/**
 * Construit une liste de placeholders %d pour une clause IN.
 *
 * @param array $ids Identifiants.
 * @return string Ex. « %d,%d,%d ».
 */
function _180c_export_placeholders( array $ids ) {
	return implode( ',', array_fill( 0, count( $ids ), '%d' ) );
}

/**
 * Normalise une valeur de sortie : jamais null, jamais d'objet.
 *
 * @param mixed $value Valeur.
 * @return string
 */
function _180c_export_str( $value ) {
	if ( is_null( $value ) || false === $value ) {
		return '';
	}
	return (string) $value;
}

/**
 * Convertit une date MySQL UTC en heure du site, en gérant le « 0 » de
 * WooCommerce Subscriptions (valeur sentinelle des _schedule_* non définis).
 *
 * @param string $gmt_date Date UTC « Y-m-d H:i:s », ou « 0 ».
 * @return string Date en heure du site, ou chaîne vide.
 */
function _180c_export_date_from_gmt( $gmt_date ) {
	$gmt_date = trim( (string) $gmt_date );

	if ( '' === $gmt_date || '0' === $gmt_date || '0000-00-00 00:00:00' === $gmt_date ) {
		return '';
	}

	return get_date_from_gmt( $gmt_date, 'Y-m-d H:i:s' );
}

/**
 * Convertit un timestamp UNIX en date « Y-m-d H:i:s » en heure du site.
 *
 * @param mixed $ts Timestamp UNIX.
 * @return string Date, ou chaîne vide.
 */
function _180c_export_date_from_ts( $ts ) {
	$ts = (int) $ts;
	if ( $ts <= 0 ) {
		return '';
	}
	return wp_date( 'Y-m-d H:i:s', $ts );
}

/**
 * Handler de l'endpoint : construit la page demandée.
 *
 * Zéro N+1 : une requête par source, batchée sur les IDs de la page.
 *
 * @param WP_REST_Request $request Requête.
 * @return WP_REST_Response
 */
function _180c_export_handle( WP_REST_Request $request ) {
	global $wpdb;

	nocache_headers();

	$page     = max( 1, absint( $request->get_param( 'page' ) ) );
	$per_page = min( 500, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
	$offset   = ( $page - 1 ) * $per_page;

	$total_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
	$total_pages = $per_page > 0 ? (int) ceil( $total_users / $per_page ) : 0;

	$columns = _180c_export_columns();

	$meta = array(
		'schema_version' => _180C_EXPORT_SCHEMA_VERSION,
		'generated_at'   => wp_date( 'Y-m-d H:i:s' ),
		'page'           => $page,
		'per_page'       => $per_page,
		'total_users'    => $total_users,
		'total_pages'    => $total_pages,
		'columns'        => $columns,
	);

	// 1. IDs de la page (tri stable ID ASC).
	$user_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->users} ORDER BY ID ASC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		)
	);
	$user_ids = array_map( 'intval', (array) $user_ids );

	// Page hors bornes : 200 avec users vide (pas d'erreur).
	if ( empty( $user_ids ) ) {
		return rest_ensure_response(
			array(
				'meta'  => $meta,
				'users' => array(),
			)
		);
	}

	$rows = _180c_export_build_rows( $user_ids, $columns );

	return rest_ensure_response(
		array(
			'meta'  => $meta,
			'users' => $rows,
		)
	);
}

/**
 * Construit les lignes d'export pour un lot d'utilisateurs.
 *
 * @param int[]    $user_ids IDs des utilisateurs de la page.
 * @param string[] $columns  Ordre canonique des colonnes.
 * @return array[] Lignes, dans l'ordre de $user_ids.
 */
function _180c_export_build_rows( array $user_ids, array $columns ) {
	global $wpdb;

	$ph = _180c_export_placeholders( $user_ids );

	// Les clauses IN() ont une cardinalité variable : les placeholders sont
	// générés par _180c_export_placeholders() / array_fill() et ne contiennent
	// que des littéraux %d ou %s ; toutes les valeurs passent par prepare().
	// WPCS ne sait pas suivre ce motif (faux positif documenté).
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	// -- Base users.
	$users = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, user_login, user_email, display_name, user_registered
			   FROM {$wpdb->users}
			  WHERE ID IN ({$ph})",
			$user_ids
		),
		OBJECT_K
	);

	// -- Usermeta whitelistée, une seule passe, pivot en PHP.
	$meta_keys = _180c_export_meta_keys();
	$meta_ph   = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
	$meta_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, meta_key, meta_value
			   FROM {$wpdb->usermeta}
			  WHERE user_id IN ({$ph})
			    AND meta_key IN ({$meta_ph})",
			array_merge( $user_ids, $meta_keys )
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$um = array();
	foreach ( $meta_rows as $r ) {
		$um[ (int) $r['user_id'] ][ $r['meta_key'] ] = $r['meta_value'];
	}

	$orders      = _180c_export_orders( $user_ids );
	$subs        = _180c_export_subscriptions( $user_ids );
	$memberships = _180c_export_memberships( $user_ids );
	$favorites   = _180c_export_favorites( $user_ids );

	$cap_key  = $wpdb->prefix . 'capabilities';
	$plan_map = _180c_export_plan_map();
	$out      = array();

	foreach ( $user_ids as $uid ) {
		$u   = $users[ $uid ] ?? null;
		$m   = $um[ $uid ] ?? array();
		$o   = $orders[ $uid ] ?? array();
		$s   = $subs[ $uid ] ?? array();
		$mem = $memberships[ $uid ] ?? array();

		// Rôles : parse de la meta capabilities.
		$roles = '';
		if ( isset( $m[ $cap_key ] ) ) {
			$caps = maybe_unserialize( $m[ $cap_key ] );
			if ( is_array( $caps ) ) {
				$roles = implode( ',', array_keys( array_filter( $caps ) ) );
			}
		}

		// Statut Mailchimp : meta sérialisée { list_id, status, timestamp }.
		$mc_status = '';
		if ( isset( $m['_mailchimp_sync_status'] ) ) {
			$mc = maybe_unserialize( $m['_mailchimp_sync_status'] );
			if ( is_array( $mc ) && isset( $mc['status'] ) ) {
				$mc_status = (string) $mc['status'];
			}
		}

		$row = array(
			// Identité.
			'user_id'                  => (int) $uid,
			'user_login'               => _180c_export_str( $u->user_login ?? '' ),
			'user_email'               => _180c_export_str( $u->user_email ?? '' ),
			'display_name'             => _180c_export_str( $u->display_name ?? '' ),
			'first_name'               => _180c_export_str( $m['first_name'] ?? '' ),
			'last_name'                => _180c_export_str( $m['last_name'] ?? '' ),
			'user_registered'          => _180c_export_str( $u->user_registered ?? '' ),
			'roles'                    => $roles,
			// Facturation.
			'billing_company'          => _180c_export_str( $m['billing_company'] ?? '' ),
			'billing_address_1'        => _180c_export_str( $m['billing_address_1'] ?? '' ),
			'billing_address_2'        => _180c_export_str( $m['billing_address_2'] ?? '' ),
			'billing_postcode'         => _180c_export_str( $m['billing_postcode'] ?? '' ),
			'billing_city'             => _180c_export_str( $m['billing_city'] ?? '' ),
			'billing_country'          => _180c_export_str( $m['billing_country'] ?? '' ),
			'billing_phone'            => _180c_export_str( $m['billing_phone'] ?? '' ),
			'billing_email'            => _180c_export_str( $m['billing_email'] ?? '' ),
			// WooCommerce.
			'orders_count'             => (int) ( $o['count'] ?? 0 ),
			'orders_total_ltv'         => (float) round( (float) ( $o['total'] ?? 0 ), 2 ),
			'orders_avg'               => (float) round( (float) ( $o['avg'] ?? 0 ), 2 ),
			'first_order_date'         => _180c_export_str( $o['first_date'] ?? '' ),
			'last_order_date'          => _180c_export_str( $o['last_date'] ?? '' ),
			'last_order_status'        => _180c_export_str( $o['last_status'] ?? '' ),
			'paying_customer'          => ( '1' === (string) ( $m['paying_customer'] ?? '' ) ) ? '1' : '0',
			// Abonnement.
			'subscription_id'          => isset( $s['id'] ) ? (int) $s['id'] : '',
			'subscription_status'      => _180c_export_str( $s['status'] ?? '' ),
			'on_hold_reason'           => _180c_export_str( $s['on_hold_reason'] ?? '' ),
			'subscription_plan'        => _180c_export_str( $s['plan'] ?? '' ),
			'subscription_start'       => _180c_export_str( $s['start'] ?? '' ),
			'next_payment_date'        => _180c_export_str( $s['next_payment'] ?? '' ),
			'subscription_end'         => _180c_export_str( $s['end'] ?? '' ),
			'membership_status'        => _180c_export_str( $mem['status'] ?? '' ),
			'membership_since'         => _180c_export_str( $mem['since'] ?? '' ),
			// Engagement.
			'last_seen_at'             => _180c_export_str( $m['_180c_last_seen'] ?? '' ),
			'wc_last_active'           => _180c_export_date_from_ts( $m['wc_last_active'] ?? 0 ),
			'favorites_count'          => (int) ( $favorites[ $uid ] ?? 0 ),
			// Marketing.
			'newsletter_optin_free'    => ( '1' === (string) ( $m['_180c_newsletter_free'] ?? '' ) ) ? '1' : '0',
			'newsletter_optin_premium' => ( '1' === (string) ( $m['_180c_newsletter_premium'] ?? '' ) ) ? '1' : '0',
			'mailchimp_is_subscribed'  => _180c_export_str( $m['mailchimp_woocommerce_is_subscribed'] ?? '' ),
			'mailchimp_status'         => $mc_status,
			// Technique.
			'profile_updated_at'       => _180c_export_date_from_ts( $m['last_update'] ?? 0 ),
		);

		// Garantit l'ordre canonique et interdit toute clé hors contrat.
		$ordered = array();
		foreach ( $columns as $col ) {
			$ordered[ $col ] = $row[ $col ] ?? '';
		}

		$out[] = $ordered;
	}

	return $out;
}

/**
 * Agrégats commandes par utilisateur (CPT shop_order, HPOS OFF).
 *
 * Les agrégats monétaires portent sur les statuts payés
 * (_180C_EXPORT_PAID_STATUSES) ; last_order_status renvoie en revanche le
 * statut de la commande la plus récente TOUS statuts confondus, afin de rendre
 * visible un dernier paiement échoué.
 *
 * @param int[] $user_ids IDs.
 * @return array<int,array>
 */
function _180c_export_orders( array $user_ids ) {
	global $wpdb;

	$ph      = _180c_export_placeholders( $user_ids );
	$paid    = explode( ',', _180C_EXPORT_PAID_STATUSES );
	$paid_ph = implode( ',', array_fill( 0, count( $paid ), '%s' ) );

	// Cf. _180c_export_build_rows() : IN() à cardinalité variable, placeholders
	// littéraux générés, valeurs passées à prepare(). Faux positif WPCS.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT cu.meta_value AS uid,
			        COUNT(DISTINCT p.ID) AS n,
			        COALESCE(SUM(CAST(tot.meta_value AS DECIMAL(12,2))), 0) AS total,
			        MIN(p.post_date) AS first_date,
			        MAX(p.post_date) AS last_date
			   FROM {$wpdb->postmeta} cu
			   INNER JOIN {$wpdb->posts} p
			           ON p.ID = cu.post_id
			          AND p.post_type = 'shop_order'
			          AND p.post_status IN ({$paid_ph})
			   LEFT JOIN {$wpdb->postmeta} tot
			          ON tot.post_id = p.ID AND tot.meta_key = '_order_total'
			  WHERE cu.meta_key = '_customer_user'
			    AND cu.meta_value IN ({$ph})
			  GROUP BY cu.meta_value",
			array_merge( $paid, $user_ids )
		),
		ARRAY_A
	);

	$out = array();
	foreach ( $rows as $r ) {
		$uid   = (int) $r['uid'];
		$count = (int) $r['n'];
		$total = (float) $r['total'];

		$out[ $uid ] = array(
			'count'      => $count,
			'total'      => $total,
			'avg'        => $count > 0 ? $total / $count : 0.0,
			'first_date' => $r['first_date'],
			'last_date'  => $r['last_date'],
		);
	}

	// Dernier statut, tous statuts confondus.
	$last = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT cu.meta_value AS uid, p.post_status, p.post_date
			   FROM {$wpdb->postmeta} cu
			   INNER JOIN {$wpdb->posts} p
			           ON p.ID = cu.post_id AND p.post_type = 'shop_order'
			  WHERE cu.meta_key = '_customer_user'
			    AND cu.meta_value IN ({$ph})
			  ORDER BY p.post_date ASC",
			$user_ids
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	// Parcours ASC : la dernière écriture par uid est la plus récente.
	foreach ( $last as $r ) {
		$uid                        = (int) $r['uid'];
		$out[ $uid ]['last_status'] = $r['post_status'];
	}

	return $out;
}

/**
 * Abonnement retenu par utilisateur.
 *
 * Règle : la subscription active la plus récente ; à défaut, la plus récente
 * tous statuts confondus. « Récente » = post_date décroissant.
 *
 * @param int[] $user_ids IDs.
 * @return array<int,array>
 */
function _180c_export_subscriptions( array $user_ids ) {
	global $wpdb;

	$ph = _180c_export_placeholders( $user_ids );

	// IN() à cardinalité variable : cf. _180c_export_build_rows(). Faux positif WPCS.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT cu.meta_value AS uid, p.ID, p.post_status, p.post_date
			   FROM {$wpdb->postmeta} cu
			   INNER JOIN {$wpdb->posts} p
			           ON p.ID = cu.post_id AND p.post_type = 'shop_subscription'
			  WHERE cu.meta_key = '_customer_user'
			    AND cu.meta_value IN ({$ph})
			  ORDER BY p.post_date DESC",
			$user_ids
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	if ( empty( $rows ) ) {
		return array();
	}

	// Sélection : première active rencontrée (ordre DESC), sinon première tout court.
	$chosen = array();
	foreach ( $rows as $r ) {
		$uid = (int) $r['uid'];

		if ( ! isset( $chosen[ $uid ] ) ) {
			$chosen[ $uid ] = $r;
			continue;
		}
		if ( 'wc-active' === $r['post_status'] && 'wc-active' !== $chosen[ $uid ]['post_status'] ) {
			$chosen[ $uid ] = $r;
		}
	}

	$sub_ids = array_map( static fn( $r ) => (int) $r['ID'], $chosen );

	$schedules = _180c_export_sub_schedules( $sub_ids );
	$plans     = _180c_export_sub_plans( $sub_ids );
	$reasons   = _180c_export_on_hold_reasons( $chosen );

	$out = array();
	foreach ( $chosen as $uid => $r ) {
		$sid = (int) $r['ID'];
		$sch = $schedules[ $sid ] ?? array();

		$out[ $uid ] = array(
			'id'             => $sid,
			'status'         => $r['post_status'],
			'plan'           => $plans[ $sid ] ?? '',
			'start'          => _180c_export_date_from_gmt( $sch['_schedule_start'] ?? '' ),
			'next_payment'   => _180c_export_date_from_gmt( $sch['_schedule_next_payment'] ?? '' ),
			'end'            => _180c_export_date_from_gmt( $sch['_schedule_end'] ?? '' ),
			'on_hold_reason' => $reasons[ $sid ] ?? '',
		);
	}

	return $out;
}

/**
 * Metas _schedule_* des subscriptions retenues (une requête).
 *
 * @param int[] $sub_ids IDs de subscriptions.
 * @return array<int,array<string,string>>
 */
function _180c_export_sub_schedules( array $sub_ids ) {
	global $wpdb;

	if ( empty( $sub_ids ) ) {
		return array();
	}

	$ph   = _180c_export_placeholders( $sub_ids );
	$keys = array( '_schedule_start', '_schedule_next_payment', '_schedule_end' );
	$kph  = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

	// IN() à cardinalité variable : cf. _180c_export_build_rows(). Faux positif WPCS.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_key, meta_value
			   FROM {$wpdb->postmeta}
			  WHERE post_id IN ({$ph}) AND meta_key IN ({$kph})",
			array_merge( $sub_ids, $keys )
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$out = array();
	foreach ( $rows as $r ) {
		$out[ (int) $r['post_id'] ][ $r['meta_key'] ] = $r['meta_value'];
	}

	return $out;
}

/**
 * Plan (libellé) des subscriptions retenues, via order_items/_product_id.
 *
 * @param int[] $sub_ids IDs de subscriptions.
 * @return array<int,string>
 */
function _180c_export_sub_plans( array $sub_ids ) {
	global $wpdb;

	if ( empty( $sub_ids ) ) {
		return array();
	}

	$ph  = _180c_export_placeholders( $sub_ids );
	$map = _180c_export_plan_map();

	// IN() à cardinalité variable : cf. _180c_export_build_rows(). Faux positif WPCS.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT oi.order_id, im.meta_value AS product_id
			   FROM {$wpdb->prefix}woocommerce_order_items oi
			   INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta im
			           ON im.order_item_id = oi.order_item_id
			          AND im.meta_key = '_product_id'
			  WHERE oi.order_id IN ({$ph})",
			$sub_ids
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$out = array();
	foreach ( $rows as $r ) {
		$pid = (int) $r['product_id'];
		if ( isset( $map[ $pid ] ) ) {
			$out[ (int) $r['order_id'] ] = $map[ $pid ];
		}
	}

	return $out;
}

/**
 * Motif de mise en attente des subscriptions on-hold retenues.
 *
 * Critère validé en Phase 0 : une subscription wc-on-hold liée à au moins une
 * commande de renouvellement (_subscription_renewal) en wc-pending ou
 * wc-failed est un impayé ; sinon c'est une pause volontaire.
 * Mesuré sur la base locale : les impayés sont majoritaires.
 *
 * @param array $chosen Subscriptions retenues, indexées par user_id.
 * @return array<int,string> Motif par subscription_id.
 */
function _180c_export_on_hold_reasons( array $chosen ) {
	global $wpdb;

	$on_hold = array();
	foreach ( $chosen as $r ) {
		if ( 'wc-on-hold' === $r['post_status'] ) {
			$on_hold[] = (int) $r['ID'];
		}
	}

	if ( empty( $on_hold ) ) {
		return array();
	}

	$ph = _180c_export_placeholders( $on_hold );

	// IN() à cardinalité variable : cf. _180c_export_build_rows(). Faux positif WPCS.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT pm.meta_value
			   FROM {$wpdb->postmeta} pm
			   INNER JOIN {$wpdb->posts} p
			           ON p.ID = pm.post_id
			          AND p.post_type = 'shop_order'
			          AND p.post_status IN ('wc-pending', 'wc-failed')
			  WHERE pm.meta_key = '_subscription_renewal'
			    AND pm.meta_value IN ({$ph})",
			$on_hold
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$impayes = array_map( 'intval', (array) $rows );

	$out = array();
	foreach ( $on_hold as $sid ) {
		$out[ $sid ] = in_array( $sid, $impayes, true ) ? 'impaye' : 'pause';
	}

	return $out;
}

/**
 * Membership retenu par utilisateur (plans payants uniquement).
 *
 * Règle : le membership le plus récent parmi _180C_EXPORT_MEMBERSHIP_PLANS.
 * Le plan gratuit « Inscrits au site » est exclu (un par compte, aucune
 * valeur CRM : il signifie seulement « compte créé »).
 *
 * @param int[] $user_ids IDs.
 * @return array<int,array>
 */
function _180c_export_memberships( array $user_ids ) {
	global $wpdb;

	$ph       = _180c_export_placeholders( $user_ids );
	$plans    = array_map( 'intval', explode( ',', _180C_EXPORT_MEMBERSHIP_PLANS ) );
	$plans_ph = _180c_export_placeholders( $plans );

	// IN() à cardinalité variable : cf. _180c_export_build_rows(). Faux positif WPCS.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_author AS uid, post_status, post_date
			   FROM {$wpdb->posts}
			  WHERE post_type = 'wc_user_membership'
			    AND post_author IN ({$ph})
			    AND post_parent IN ({$plans_ph})
			  ORDER BY post_date DESC",
			array_merge( $user_ids, $plans )
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$out = array();
	foreach ( $rows as $r ) {
		$uid = (int) $r['uid'];
		// Ordre DESC : la première occurrence est la plus récente.
		if ( ! isset( $out[ $uid ] ) ) {
			$out[ $uid ] = array(
				'status' => $r['post_status'],
				'since'  => $r['post_date'],
			);
		}
	}

	return $out;
}

/**
 * Nombre de favoris par utilisateur.
 *
 * @param int[] $user_ids IDs.
 * @return array<int,int>
 */
function _180c_export_favorites( array $user_ids ) {
	global $wpdb;

	$table = $wpdb->prefix . 'user_favorites';
	$ph    = _180c_export_placeholders( $user_ids );

	// $table vient de $wpdb->prefix (jamais d'une entrée utilisateur) ; le IN()
	// suit le motif décrit dans _180c_export_build_rows(). Faux positif WPCS.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, COUNT(*) AS n
			   FROM `{$table}`
			  WHERE user_id IN ({$ph})
			  GROUP BY user_id",
			$user_ids
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$out = array();
	foreach ( $rows as $r ) {
		$out[ (int) $r['user_id'] ] = (int) $r['n'];
	}

	return $out;
}
