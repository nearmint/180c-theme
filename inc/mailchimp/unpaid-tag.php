<?php
/**
 * Tag Mailchimp « CB expirée » — relance des abonnements suspendus pour impayé.
 *
 * POURQUOI
 * --------
 * La relance automatique de WooCommerce Subscriptions s'arrête après
 * 5 tentatives réparties sur 7 jours (`class-wcs-retry-rules.php:29`). Passé ce
 * délai, l'abonnement reste `on-hold` et **plus aucun e-mail ne part** : les
 * réglages `email_customer_renewal_invoice` et `email_customer_on_hold_renewal_order`
 * sont désactivés en production. C'est ce silence qu'on comble, en posant un tag
 * sur lequel l'équipe branche une automation Mailchimp (J0 / J+3 / J+10).
 *
 * Le choix du tag plutôt que d'un e-mail transactionnel maison est délibéré :
 * l'envoi reste dans Mailchimp, là où l'équipe mesure la délivrabilité, et un
 * tag se retire — un e-mail parti ne se rattrape pas.
 *
 * DEUX GARDES-FOUS
 * ----------------
 * `_180C_UNPAID_TAG_SINCE` (`Y-m-d`) : seuls les échecs postérieurs à cette date
 * sont pris en compte. Sans elle, le cron ne fait rien. C'est ce qui empêche un
 * déploiement de taguer d'un coup les 934 impayés historiques, dont les plus
 * anciens datent de plus de deux ans.
 *
 * `_180C_UNPAID_TAG_DRY_RUN` : à `true` tant qu'elle n'est pas explicitement
 * définie à `false`. En dry-run, tout est calculé et journalisé, rien n'est
 * envoyé à Mailchimp.
 *
 * OBSERVABILITÉ
 * -------------
 * Chaque exécution est consignée dans l'option `_180c_unpaid_tag_runs`
 * (60 dernières entrées, autoload « no »). `error_log()` est conservé, mais il
 * ne suffit pas : en production, ce fichier n'est pas lisible
 * depuis l'administration. Le journal est exposé par l'écran d'admin
 * (`inc/admin/unpaid-tag-screen.php`) et par la route de statut
 * (`inc/rest/unpaid-tag-status.php`).
 *
 * POURQUOI PAS `_180c_mc_tags_add()`
 * ----------------------------------
 * Ce helper commence par un `PUT /members/{hash}` avec `status_if_new` :
 * sur un contact ABSENT de l'audience, il le **crée** en `subscribed`. Poser un
 * tag de relance ne doit jamais créer de contact ni modifier un statut. On passe
 * donc directement par `POST /members/{hash}/tags`, qui échoue proprement en 404
 * si le contact n'existe pas.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/** Nom du tag Mailchimp. */
const _180C_UNPAID_TAG_NAME = 'CB expirée';

/** Hook du cron quotidien. */
const _180C_UNPAID_TAG_HOOK = '_180c_unpaid_tag_sync';

/** Délai après l'échec avant de taguer : au-delà des 7 jours de relance WCS. */
const _180C_UNPAID_TAG_DELAY_DAYS = 8;

/** Meta utilisateur mémorisant l'état posé, pour rester idempotent. */
const _180C_UNPAID_TAG_META = '_180c_unpaid_tagged';

/**
 * Option portant le journal des exécutions.
 *
 * En base et non dans un fichier : les journaux PHP de la production ne sont
 * pas consultables depuis l'administration. Un `error_log()`
 * y part dans un fichier qu'on ne peut pas lire — ce module était donc muet
 * en pratique. Le journal en option est consultable depuis l'admin, depuis
 * phpMyAdmin et depuis la route de statut.
 */
const _180C_UNPAID_TAG_RUNS_OPTION = '_180c_unpaid_tag_runs';

/** Nombre d'exécutions conservées dans le journal. */
const _180C_UNPAID_TAG_RUNS_MAX = 60;

/** Nombre d'identifiants d'utilisateurs conservés par entrée. */
const _180C_UNPAID_TAG_RUN_IDS_MAX = 50;

/** Nombre d'erreurs détaillées conservées par entrée. */
const _180C_UNPAID_TAG_RUN_ERRORS_MAX = 20;

/**
 * Au-delà de ce délai sans exécution, le cron est considéré en défaut.
 *
 * 36 h et non 24 : le cron est quotidien, et WP-Cron ne se déclenche qu'à la
 * faveur d'une visite. Une journée creuse peut légitimement décaler l'exécution
 * de quelques heures ; deux jours sans rien, non.
 *
 * Ici et non dans l'écran d'admin : la route de statut s'en sert aussi, et elle
 * répond hors contexte d'administration.
 */
const _180C_UNPAID_TAG_STALE_HOURS = 36;

/**
 * Le mode dry-run est actif par défaut.
 *
 * @return bool
 */
function _180c_unpaid_tag_is_dry_run(): bool {
	return ! defined( '_180C_UNPAID_TAG_DRY_RUN' ) || (bool) _180C_UNPAID_TAG_DRY_RUN;
}

/**
 * Date plancher des échecs pris en compte.
 *
 * @return int|null Timestamp, ou null si la constante est absente ou illisible.
 */
function _180c_unpaid_tag_since() {
	if ( ! defined( '_180C_UNPAID_TAG_SINCE' ) || '' === (string) _180C_UNPAID_TAG_SINCE ) {
		return null;
	}
	$ts = strtotime( (string) _180C_UNPAID_TAG_SINCE . ' 00:00:00' );

	return false === $ts ? null : $ts;
}

/**
 * Journalise une ligne préfixée, toujours (le dry-run doit rester lisible).
 *
 * @param string $message Message.
 * @return void
 */
function _180c_unpaid_tag_log( string $message ): void {
	error_log( '[180c-unpaid] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * Journal des exécutions, de la plus récente à la plus ancienne.
 *
 * @return array<int, array<string, mixed>>
 */
function _180c_unpaid_tag_runs(): array {
	$runs = get_option( _180C_UNPAID_TAG_RUNS_OPTION, array() );
	if ( ! is_array( $runs ) ) {
		return array();
	}

	// Une entrée mal formée (édition manuelle en base) ne doit pas casser l'écran.
	$runs = array_values( array_filter( $runs, 'is_array' ) );

	return array_slice( $runs, 0, _180C_UNPAID_TAG_RUNS_MAX );
}

/**
 * Enregistre une exécution dans le journal, en tête de liste.
 *
 * L'entrée est normalisée ici et nulle part ailleurs : les appelants passent
 * ce qu'ils ont, les clés manquantes prennent une valeur neutre. Les listes
 * (identifiants, erreurs) sont tronquées avant écriture — l'option n'est pas
 * un espace de stockage, elle doit rester lisible dans phpMyAdmin.
 *
 * @param array<string, mixed> $run Données de l'exécution.
 * @return array<string, mixed> L'entrée telle qu'écrite.
 */
function _180c_unpaid_tag_record_run( array $run ): array {
	$triggers = array( 'cron', 'hook', 'manuel' );
	$trigger  = isset( $run['trigger'] ) ? (string) $run['trigger'] : 'cron';

	$ids       = isset( $run['ids'] ) && is_array( $run['ids'] ) ? array_values( array_unique( array_map( 'intval', $run['ids'] ) ) ) : array();
	$ids_total = count( $ids );

	$details = isset( $run['erreurs_detail'] ) && is_array( $run['erreurs_detail'] ) ? array_values( $run['erreurs_detail'] ) : array();

	$entry = array(
		// Horodatage du site : c'est celui que l'exploitant lit dans l'admin.
		'date'           => current_time( 'mysql' ),
		'timestamp'      => time(),
		'trigger'        => in_array( $trigger, $triggers, true ) ? $trigger : 'cron',
		'mode'           => ! empty( $run['dry_run'] ) ? 'simulation' : 'reel',
		'evalues'        => isset( $run['evalues'] ) ? (int) $run['evalues'] : 0,
		'candidats'      => isset( $run['candidats'] ) ? (int) $run['candidats'] : 0,
		'tagues'         => isset( $run['tagues'] ) ? (int) $run['tagues'] : 0,
		'retires'        => isset( $run['retires'] ) ? (int) $run['retires'] : 0,
		'erreurs'        => isset( $run['erreurs'] ) ? (int) $run['erreurs'] : 0,
		'ids'            => array_slice( $ids, 0, _180C_UNPAID_TAG_RUN_IDS_MAX ),
		'ids_total'      => $ids_total,
		'erreurs_detail' => array_slice( $details, 0, _180C_UNPAID_TAG_RUN_ERRORS_MAX ),
		'note'           => isset( $run['note'] ) ? (string) $run['note'] : '',
	);

	$runs = _180c_unpaid_tag_runs();
	array_unshift( $runs, $entry );
	$runs = array_slice( $runs, 0, _180C_UNPAID_TAG_RUNS_MAX );

	// autoload « no » : ce journal n'a rien à faire dans chaque requête du front.
	update_option( _180C_UNPAID_TAG_RUNS_OPTION, $runs, false );

	return $entry;
}

/**
 * Dernière exécution enregistrée.
 *
 * @return array<string, mixed>|null
 */
function _180c_unpaid_tag_last_run(): ?array {
	$runs = _180c_unpaid_tag_runs();

	return empty( $runs ) ? null : $runs[0];
}

/**
 * Pose ou retire le tag sur un contact.
 *
 * N'crée jamais le contact : `POST /members/{hash}/tags` renvoie 404 si l'adresse
 * est inconnue de l'audience, ce qui est le comportement voulu.
 *
 * @param string $email  Adresse e-mail.
 * @param bool   $active true pour poser, false pour retirer.
 * @return true|WP_Error
 */
function _180c_unpaid_tag_set( string $email, bool $active ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', __( 'Adresse e-mail invalide.', '180c' ) );
	}
	if ( ! function_exists( '_180c_mc_request' ) || ! function_exists( '_180c_mc_source_audience_id' ) ) {
		return new WP_Error( 'mailchimp_unavailable', __( 'Intégration Mailchimp indisponible.', '180c' ) );
	}

	$aud = _180c_mc_source_audience_id();
	if ( '' === $aud ) {
		return new WP_Error( 'no_audience', __( 'Audience Mailchimp non configurée.', '180c' ) );
	}

	$res = _180c_mc_request(
		'POST',
		'/lists/' . $aud . '/members/' . md5( strtolower( trim( $email ) ) ) . '/tags',
		array(
			'tags' => array(
				array(
					'name'   => _180C_UNPAID_TAG_NAME,
					'status' => $active ? 'active' : 'inactive',
				),
			),
		)
	);

	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( (int) $res['code'] >= 400 ) {
		return new WP_Error( 'mailchimp_rejected', sprintf( 'HTTP %d', (int) $res['code'] ) );
	}

	return true;
}

/**
 * Date de l'échec de paiement retenu pour un abonnement, s'il en a un.
 *
 * `on-hold`, dernière commande de renouvellement `failed` ou `pending`, échec
 * postérieur à `_180C_UNPAID_TAG_SINCE`. **Le seuil des 8 jours n'est PAS
 * appliqué ici** : c'est ce qui permet à la liste des candidats à venir et au
 * cron de partager exactement la même règle de qualification, le premier
 * regardant simplement plus tôt que le second.
 *
 * @param WC_Subscription $subscription Abonnement.
 * @param int             $since        Timestamp plancher.
 * @return int|null Timestamp de l'échec, ou null si l'abonnement ne relève pas
 *                  de la relance impayé.
 */
function _180c_unpaid_tag_failure_ts( $subscription, int $since ): ?int {
	if ( ! $subscription instanceof WC_Subscription || ! $subscription->has_status( 'on-hold' ) ) {
		return null;
	}

	$order = $subscription->get_last_order( 'all', array( 'parent', 'renewal' ) );
	if ( ! $order instanceof WC_Order || ! $order->has_status( array( 'failed', 'pending' ) ) ) {
		return null;
	}

	$created = $order->get_date_created();
	if ( ! $created ) {
		return null;
	}
	$ts = $created->getTimestamp();

	return $ts < $since ? null : $ts;
}

/**
 * Un abonnement remplit-il les conditions de relance impayé ?
 *
 * Qualification de `_180c_unpaid_tag_failure_ts()`, plus le délai de 8 jours
 * qui place la relance au-delà des 7 jours de tentatives de WCS.
 *
 * @param WC_Subscription $subscription Abonnement.
 * @param int             $since        Timestamp plancher.
 * @return bool
 */
function _180c_unpaid_tag_qualifies( $subscription, int $since ): bool {
	$ts = _180c_unpaid_tag_failure_ts( $subscription, $since );
	if ( null === $ts ) {
		return false;
	}

	return ( time() - $ts ) >= ( _180C_UNPAID_TAG_DELAY_DAYS * DAY_IN_SECONDS );
}

/**
 * Parcourt tous les abonnements suspendus, par lots.
 *
 * La pagination se fait par `offset`, JAMAIS par `paged` : wcs_get_subscriptions()
 * accepte l'argument `paged` mais ne le traduit pas — seuls `limit` et `offset`
 * atteignent la requête (`wcs-functions.php`, construction de $query_args).
 * Passer `paged` renvoie donc éternellement la même page. Vérifié : la boucle
 * ne se terminait jamais.
 *
 * Factorisé parce que deux appelants en dépendent (le cron et la liste des
 * candidats) et qu'une seconde copie de cette boucle finirait tôt ou tard par
 * réintroduire le `paged`.
 *
 * @param callable $callback Reçoit chaque WC_Subscription.
 * @return void
 */
function _180c_unpaid_tag_walk_on_hold( callable $callback ): void {
	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		return;
	}

	$batch_size = 200;
	$offset     = 0;
	$max_loops  = 500; // Garde-fou dur : 100 000 abonnements au plus.

	for ( $loop = 0; $loop < $max_loops; $loop++ ) {
		$batch = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'on-hold',
				'subscriptions_per_page' => $batch_size,
				'offset'                 => $offset,
			)
		);
		if ( empty( $batch ) ) {
			return;
		}

		foreach ( $batch as $subscription ) {
			$callback( $subscription );
		}

		if ( count( $batch ) < $batch_size ) {
			return;
		}
		$offset += $batch_size;
	}
}

/**
 * Abonnements en attente de relance : impayés qualifiés, seuil des 8 jours mis
 * à part.
 *
 * Sert à voir venir. Un exploitant qui consulte l'écran doit pouvoir dire
 * « celui-ci basculera jeudi » avant que le tag ne parte, et vérifier après
 * coup que la bascule a bien eu lieu. Sans cette liste, la seule preuve que le
 * dispositif fonctionne serait l'absence d'incident.
 *
 * @return array<int, array<string, mixed>> Triés par date d'échec croissante.
 */
function _180c_unpaid_tag_candidates(): array {
	$since = _180c_unpaid_tag_since();
	if ( null === $since ) {
		return array();
	}

	$rows = array();

	_180c_unpaid_tag_walk_on_hold(
		static function ( $subscription ) use ( $since, &$rows ) {
			$failed_at = _180c_unpaid_tag_failure_ts( $subscription, $since );
			if ( null === $failed_at ) {
				return;
			}

			$uid     = (int) $subscription->get_user_id();
			$due_at  = $failed_at + ( _180C_UNPAID_TAG_DELAY_DAYS * DAY_IN_SECONDS );
			$elapsed = time() - $failed_at;

			$rows[] = array(
				'subscription_id'   => (int) $subscription->get_id(),
				'user_id'           => $uid,
				'echec'             => wp_date( 'Y-m-d H:i', $failed_at ),
				'echec_timestamp'   => $failed_at,
				'age_jours'         => (int) floor( $elapsed / DAY_IN_SECONDS ),
				'bascule'           => wp_date( 'Y-m-d', $due_at ),
				'bascule_timestamp' => $due_at,
				'tague'             => $uid > 0 && (bool) get_user_meta( $uid, _180C_UNPAID_TAG_META, true ),
			);
		}
	);

	usort(
		$rows,
		static function ( $a, $b ) {
			return $a['echec_timestamp'] <=> $b['echec_timestamp'];
		}
	);

	return $rows;
}

/**
 * Applique l'état voulu pour un utilisateur, en une seule écriture au plus.
 *
 * Renvoie un tableau et non une simple chaîne : le journal en base a besoin du
 * code et du message de l'erreur, que `error_log()` était seul à connaître.
 *
 * @param int  $user_id Compte.
 * @param bool $wanted  État souhaité du tag.
 * @param bool $dry_run Mode simulation.
 * @return array{resultat:string, erreur:array{user_id:int, code:string, message:string}|null}
 *         `resultat` vaut 'pose' | 'retrait' | 'inchange' | 'erreur' | 'sans_email'.
 */
function _180c_unpaid_tag_apply( int $user_id, bool $wanted, bool $dry_run ): array {
	$current = (bool) get_user_meta( $user_id, _180C_UNPAID_TAG_META, true );
	if ( $current === $wanted ) {
		return array(
			'resultat' => 'inchange',
			'erreur'   => null,
		);
	}

	$user = get_userdata( $user_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return array(
			'resultat' => 'sans_email',
			'erreur'   => null,
		);
	}

	if ( $dry_run ) {
		return array(
			'resultat' => $wanted ? 'pose' : 'retrait',
			'erreur'   => null,
		);
	}

	$res = _180c_unpaid_tag_set( $user->user_email, $wanted );
	if ( is_wp_error( $res ) ) {
		_180c_unpaid_tag_log(
			sprintf(
				'utilisateur %d : échec %s (%s)',
				$user_id,
				$wanted ? 'pose' : 'retrait',
				$res->get_error_message()
			)
		);

		return array(
			'resultat' => 'erreur',
			'erreur'   => array(
				'user_id' => $user_id,
				'code'    => (string) $res->get_error_code(),
				// Message court : le journal est lu dans un tableau d'admin.
				'message' => mb_substr( (string) $res->get_error_message(), 0, 120 ),
			),
		);
	}

	if ( $wanted ) {
		update_user_meta( $user_id, _180C_UNPAID_TAG_META, '1' );
	} else {
		delete_user_meta( $user_id, _180C_UNPAID_TAG_META );
	}

	return array(
		'resultat' => $wanted ? 'pose' : 'retrait',
		'erreur'   => null,
	);
}

/**
 * Cron quotidien : parcourt les abonnements suspendus et rattrape les retraits.
 *
 * Le rattrapage est indispensable : le hook de statut ne couvre pas les sorties
 * qui n'émettent pas d'événement (import, script, intervention en base).
 *
 * Chaque exécution — y compris celle qui ne fait rien faute de constante —
 * laisse une trace dans le journal en base. Une exécution muette et une
 * exécution absente ne se distinguent pas autrement, et c'est justement la
 * distinction qu'on veut pouvoir faire depuis la production.
 *
 * @param string    $trigger       'cron' | 'hook' | 'manuel'.
 * @param bool|null $force_dry_run true pour forcer la simulation quel que soit
 *                                 `_180C_UNPAID_TAG_DRY_RUN` ; null pour suivre
 *                                 la constante. Jamais false : rien ne doit
 *                                 pouvoir forcer le mode réel.
 * @return array Compteurs, pour les tests.
 */
function _180c_unpaid_tag_sync( string $trigger = 'cron', ?bool $force_dry_run = null ): array {
	$stats = array(
		'evalues'   => 0,
		'candidats' => 0,
		'tagues'    => 0,
		'retires'   => 0,
		'erreurs'   => 0,
	);

	$since = _180c_unpaid_tag_since();
	if ( null === $since ) {
		_180c_unpaid_tag_log( 'constante absente : _180C_UNPAID_TAG_SINCE non définie, aucune action.' );
		_180c_unpaid_tag_record_run(
			array_merge(
				$stats,
				array(
					'trigger' => $trigger,
					'dry_run' => true,
					'note'    => __( 'Constante _180C_UNPAID_TAG_SINCE absente : aucune action.', '180c' ),
				)
			)
		);

		return $stats;
	}
	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		_180c_unpaid_tag_log( 'WooCommerce Subscriptions indisponible, aucune action.' );
		_180c_unpaid_tag_record_run(
			array_merge(
				$stats,
				array(
					'trigger' => $trigger,
					'dry_run' => true,
					'note'    => __( 'WooCommerce Subscriptions indisponible : aucune action.', '180c' ),
				)
			)
		);

		return $stats;
	}

	// `true === $force_dry_run` et non `(bool) $force_dry_run` : passer false ne
	// doit PAS pouvoir désactiver le dry-run imposé par la constante.
	$dry_run = true === $force_dry_run ? true : _180c_unpaid_tag_is_dry_run();

	// Utilisateurs à taguer : au moins un abonnement qui remplit les conditions.
	$wanted = array();
	$seen   = array();

	_180c_unpaid_tag_walk_on_hold(
		static function ( $subscription ) use ( $since, &$wanted, &$seen, &$stats ) {
			$uid = (int) $subscription->get_user_id();
			if ( $uid <= 0 ) {
				return;
			}
			$seen[ $uid ] = true;
			++$stats['evalues'];
			if ( _180c_unpaid_tag_qualifies( $subscription, $since ) ) {
				$wanted[ $uid ] = true;
			}
		}
	);

	// Rattrapage : tout porteur du tag qui ne remplit plus les conditions.
	$tagged = get_users(
		array(
			'meta_key' => _180C_UNPAID_TAG_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'   => '1',                   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'fields'       => 'ID',
		'number'       => -1,
		)
	);
	foreach ( $tagged as $uid ) {
		$seen[ (int) $uid ] = true;
	}

	$stats['candidats'] = count( $wanted );

	$touched = array();
	$details = array();

	foreach ( array_keys( $seen ) as $uid ) {
		$outcome = _180c_unpaid_tag_apply( (int) $uid, isset( $wanted[ $uid ] ), $dry_run );
		$result  = $outcome['resultat'];

		if ( 'pose' === $result ) {
			++$stats['tagues'];
			$touched[] = (int) $uid;
		} elseif ( 'retrait' === $result ) {
			++$stats['retires'];
			$touched[] = (int) $uid;
		} elseif ( 'erreur' === $result ) {
			++$stats['erreurs'];
			$touched[] = (int) $uid;
			if ( $outcome['erreur'] ) {
				$details[] = $outcome['erreur'];
			}
		}
	}

	_180c_unpaid_tag_log(
		sprintf(
			'%s — évalués %d, candidats %d, tagués %d, retirés %d, erreurs %d (seuil %s, délai %d j)',
			$dry_run ? 'DRY-RUN' : 'APPLY',
			$stats['evalues'],
			$stats['candidats'],
			$stats['tagues'],
			$stats['retires'],
			$stats['erreurs'],
			gmdate( 'Y-m-d', $since ),
			_180C_UNPAID_TAG_DELAY_DAYS
		)
	);

	_180c_unpaid_tag_record_run(
		array_merge(
			$stats,
			array(
				'trigger'        => $trigger,
				'dry_run'        => $dry_run,
				'ids'            => $touched,
				'erreurs_detail' => $details,
			)
		)
	);

	return $stats;
}

/**
 * Point d'entrée du cron.
 *
 * Enveloppe dédiée : accrocher `_180c_unpaid_tag_sync()` directement exposerait
 * ses paramètres à tout `do_action()` portant des arguments, et le premier
 * d'entre eux pilote le déclencheur journalisé.
 *
 * @return void
 */
function _180c_unpaid_tag_cron(): void {
	_180c_unpaid_tag_sync( 'cron' );
}
add_action( _180C_UNPAID_TAG_HOOK, '_180c_unpaid_tag_cron' );

/**
 * Retrait immédiat dès qu'un abonnement quitte l'état suspendu.
 *
 * `active` = le client a payé. `cancelled` / `expired` = il bascule dans la
 * logique « ex-abonné », qui relève d'une autre relance : dans les deux cas le
 * tag impayé n'a plus lieu d'être.
 *
 * @param WC_Subscription $subscription Abonnement.
 * @param string          $new_status   Nouveau statut.
 * @return void
 */
function _180c_unpaid_tag_on_status_change( $subscription, $new_status ): void {
	if ( ! in_array( $new_status, array( 'active', 'cancelled', 'expired' ), true ) ) {
		return;
	}
	if ( ! $subscription instanceof WC_Subscription ) {
		return;
	}
	$uid = (int) $subscription->get_user_id();
	if ( $uid <= 0 || ! get_user_meta( $uid, _180C_UNPAID_TAG_META, true ) ) {
		return;
	}

	$since = _180c_unpaid_tag_since();
	if ( null === $since ) {
		return;
	}

	// Un autre abonnement du même compte peut encore être en impayé.
	if ( function_exists( 'wcs_get_users_subscriptions' ) ) {
		foreach ( wcs_get_users_subscriptions( $uid ) as $other ) {
			if ( $other->get_id() !== $subscription->get_id()
				&& _180c_unpaid_tag_qualifies( $other, $since ) ) {
				return;
			}
		}
	}

	$dry_run = _180c_unpaid_tag_is_dry_run();
	$outcome = _180c_unpaid_tag_apply( $uid, false, $dry_run );
	$result  = $outcome['resultat'];

	_180c_unpaid_tag_log(
		sprintf(
			'utilisateur %d : abonnement %d passé en %s -> %s',
			$uid,
			$subscription->get_id(),
			$new_status,
			$result
		)
	);

	_180c_unpaid_tag_record_run(
		array(
			'trigger'        => 'hook',
			'dry_run'        => $dry_run,
			'evalues'        => 1,
			'candidats'      => 0,
			'tagues'         => 0,
			'retires'        => 'retrait' === $result ? 1 : 0,
			'erreurs'        => 'erreur' === $result ? 1 : 0,
			'ids'            => array( $uid ),
			'erreurs_detail' => $outcome['erreur'] ? array( $outcome['erreur'] ) : array(),
			'note'           => sprintf(
				/* translators: 1: identifiant d'abonnement, 2: nouveau statut, 3: résultat de l'application du tag. */
				__( 'Abonnement %1$d passé en « %2$s » → %3$s.', '180c' ),
				$subscription->get_id(),
				$new_status,
				$result
			),
		)
	);
}
add_action( 'woocommerce_subscription_status_updated', '_180c_unpaid_tag_on_status_change', 10, 2 );

/**
 * Planifie le cron quotidien.
 *
 * @return void
 */
function _180c_unpaid_tag_schedule(): void {
	if ( ! wp_next_scheduled( _180C_UNPAID_TAG_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', _180C_UNPAID_TAG_HOOK );
	}
}
add_action( 'init', '_180c_unpaid_tag_schedule' );
