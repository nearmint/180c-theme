<?php
/**
 * Statut du dispositif de relance impayé, lisible sans ouvrir l'administration.
 *
 * Endpoint : GET /wp-json/180c/v1/unpaid-tag/status
 * Auth     : en-tête « X-180C-Ops-Key: <_180C_OPS_KEY> ».
 *
 * POURQUOI UNE SECONDE CLÉ
 * ------------------------
 * `_180C_EXPORT_API_KEY` existe déjà et fonctionne de la même façon, mais elle
 * ouvre l'export CRM : 40 colonnes de données personnelles. Cette route-ci ne
 * renvoie que des compteurs et des identifiants WordPress. Les deux ne méritent
 * pas la même clé — partager celle de l'export ferait payer à une lecture
 * anodine le prix de la plus sensible.
 *
 * POURQUOI PAS « Authorization: Bearer »
 * --------------------------------------
 * Simple JWT Login (actif) intercepte tout en-tête `Authorization: Bearer` sur
 * l'ensemble des routes REST du site et répond 400 « Wrong number of segments »
 * dès que la valeur n'est pas un JWT à trois segments — avant même l'exécution
 * du permission_callback. Constaté lors de la mise en place de
 * `inc/rest/subscribers-export.php`, dont cette route reprend le mécanisme.
 *
 * La clé ne transite QUE par en-tête, jamais en query string : une URL se
 * retrouve dans les journaux d'accès du serveur, pas un en-tête.
 *
 * LECTURE SEULE
 * -------------
 * Aucune action n'est déclenchable ici, pas même une simulation. Une clé
 * d'exploitation qui circule dans un cron externe ou un tableau de bord ne doit
 * ouvrir aucun effet de bord ; l'exécution manuelle reste derrière un compte
 * administrateur, un nonce et un POST (`inc/admin/unpaid-tag-screen.php`).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/** Nombre d'exécutions renvoyées par la route. */
const _180C_UNPAID_TAG_STATUS_RUNS = 10;

/**
 * Récupère la clé d'exploitation depuis les en-têtes de la requête.
 *
 * `getallheaders()` en repli : selon la SAPI, un en-tête à tirets peut ne pas
 * atterrir tel quel dans `$_SERVER`.
 *
 * @return string Clé transmise, ou chaîne vide.
 */
function _180c_unpaid_tag_request_key(): string {
	if ( ! empty( $_SERVER['HTTP_X_180C_OPS_KEY'] ) ) {
		return trim( wp_unslash( $_SERVER['HTTP_X_180C_OPS_KEY'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	if ( function_exists( 'getallheaders' ) ) {
		foreach ( (array) getallheaders() as $name => $value ) {
			if ( 0 === strcasecmp( (string) $name, 'X-180C-Ops-Key' ) ) {
				return trim( (string) $value );
			}
		}
	}

	return '';
}

/**
 * Permission callback : clé d'exploitation valide, et rien d'autre.
 *
 * Constante absente et clé fausse répondent le même 403 sans détail : la
 * réponse ne doit pas apprendre à un visiteur si la clé est configurée.
 *
 * @return true|WP_Error
 */
function _180c_unpaid_tag_status_permission() {
	$expected = defined( '_180C_OPS_KEY' ) ? (string) _180C_OPS_KEY : '';
	$provided = _180c_unpaid_tag_request_key();

	if ( '' === $expected || '' === $provided || ! hash_equals( $expected, $provided ) ) {
		return new WP_Error(
			'unpaid_tag_forbidden',
			__( 'Non autorisé.', '180c' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Masque toute adresse e-mail qui se serait glissée dans un message d'erreur.
 *
 * Les codes d'erreur du module n'en contiennent aucune aujourd'hui, mais ils
 * proviennent en partie du client Mailchimp : la garantie « aucune donnée
 * personnelle » ne doit pas dépendre du libellé que renverra une API tierce.
 *
 * @param string $message Message brut.
 * @return string
 */
function _180c_unpaid_tag_status_scrub( string $message ): string {
	return (string) preg_replace(
		'/[^\s<>()\[\]:;,"]+@[^\s<>()\[\]:;,"]+/',
		'[adresse masquée]',
		$message
	);
}

/**
 * Normalise une entrée de journal pour la réponse.
 *
 * @param array<string, mixed> $run Entrée brute.
 * @return array<string, mixed>
 */
function _180c_unpaid_tag_status_run( array $run ): array {
	$details = isset( $run['erreurs_detail'] ) && is_array( $run['erreurs_detail'] ) ? $run['erreurs_detail'] : array();

	return array(
		'date'           => (string) ( $run['date'] ?? '' ),
		'timestamp'      => (int) ( $run['timestamp'] ?? 0 ),
		'declencheur'    => (string) ( $run['trigger'] ?? '' ),
		'mode'           => (string) ( $run['mode'] ?? '' ),
		'evalues'        => (int) ( $run['evalues'] ?? 0 ),
		'candidats'      => (int) ( $run['candidats'] ?? 0 ),
		'tagues'         => (int) ( $run['tagues'] ?? 0 ),
		'retires'        => (int) ( $run['retires'] ?? 0 ),
		'erreurs'        => (int) ( $run['erreurs'] ?? 0 ),
		'user_ids'       => array_map( 'intval', (array) ( $run['ids'] ?? array() ) ),
		'user_ids_total' => (int) ( $run['ids_total'] ?? 0 ),
		'erreurs_detail' => array_map(
			static function ( $detail ) {
				return array(
					'user_id' => (int) ( $detail['user_id'] ?? 0 ),
					'code'    => (string) ( $detail['code'] ?? '' ),
					'message' => _180c_unpaid_tag_status_scrub( (string) ( $detail['message'] ?? '' ) ),
				);
			},
			$details
		),
		'note'           => _180c_unpaid_tag_status_scrub( (string) ( $run['note'] ?? '' ) ),
	);
}

/**
 * Construit la réponse de statut.
 *
 * @return array<string, mixed>
 */
function _180c_unpaid_tag_status_payload(): array {
	$runs = _180c_unpaid_tag_runs();
	$last = empty( $runs ) ? null : $runs[0];
	$next = wp_next_scheduled( _180C_UNPAID_TAG_HOOK );

	$age = null === $last ? null : max( 0, time() - (int) $last['timestamp'] );

	$etat = array(
		'since'                     => defined( '_180C_UNPAID_TAG_SINCE' ) ? (string) _180C_UNPAID_TAG_SINCE : null,
		'since_definie'             => null !== _180c_unpaid_tag_since(),
		'dry_run_definie'           => defined( '_180C_UNPAID_TAG_DRY_RUN' ),
		'mode'                      => _180c_unpaid_tag_is_dry_run() ? 'simulation' : 'reel',
		'delai_jours'               => _180C_UNPAID_TAG_DELAY_DAYS,
		'prochaine_execution'       => $next ? wp_date( 'Y-m-d H:i:s', (int) $next ) : null,
		'age_derniere_execution_s'  => $age,
		'seuil_obsolescence_heures' => _180C_UNPAID_TAG_STALE_HOURS,
		// Le drapeau, et non le calcul, est ce qu'un appelant automatisé doit lire.
		'obsolete'                  => null === $age || $age > ( _180C_UNPAID_TAG_STALE_HOURS * HOUR_IN_SECONDS ),
	);

	$candidates = array_map(
		static function ( $row ) {
			return array(
				'subscription_id' => (int) $row['subscription_id'],
				'user_id'         => (int) $row['user_id'],
				'echec'           => (string) $row['echec'],
				'age_jours'       => (int) $row['age_jours'],
				'bascule'         => (string) $row['bascule'],
				'tague'           => (bool) $row['tague'],
			);
		},
		_180c_unpaid_tag_candidates()
	);

	return array(
		'genere_le'          => current_time( 'mysql' ),
		'etat'               => $etat,
		'derniere_execution' => null === $last ? null : _180c_unpaid_tag_status_run( $last ),
		'executions'         => array_map(
			'_180c_unpaid_tag_status_run',
			array_slice( $runs, 0, _180C_UNPAID_TAG_STATUS_RUNS )
		),
		'candidats'          => $candidates,
		'candidats_total'    => count( $candidates ),
	);
}

/**
 * Sert la réponse.
 *
 * @return WP_REST_Response
 */
function _180c_unpaid_tag_status_handler(): WP_REST_Response {
	$response = rest_ensure_response( _180c_unpaid_tag_status_payload() );

	// Statut d'exploitation : jamais mis en cache, ni par le navigateur ni par le CDN.
	$response->header( 'Cache-Control', 'no-store, max-age=0' );

	return $response;
}

/**
 * Enregistre la route.
 *
 * @return void
 */
function _180c_unpaid_tag_status_register_route(): void {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/unpaid-tag/status',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_unpaid_tag_status_handler',
			'permission_callback' => '_180c_unpaid_tag_status_permission',
		)
	);
}
add_action( 'rest_api_init', '_180c_unpaid_tag_status_register_route' );
