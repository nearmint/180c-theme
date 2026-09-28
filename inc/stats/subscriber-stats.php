<?php
/**
 * Stats — Moteur de calcul des KPIs abonnés, cache et planification nocturne.
 *
 * Calcule six indicateurs sur la base d'abonnés numériques (WooCommerce
 * Subscriptions) une fois par nuit via WP-Cron, persiste le résultat complet
 * dans un transient (TTL ~25 h) et archive un instantané quotidien des KPIs
 * « stock » dans une option (pour calculer leurs variations ~J-30).
 *
 * Le widget (inc/stats/subscriber-widget.php) lit le transient ; en cas
 * d'absence il déclenche un calcul à la volée (fallback).
 *
 * Toutes les lectures passent par l'API WC Subscriptions (wcs_get_subscriptions
 * + méthodes WC_Subscription) pour rester agnostique du stockage (CPT ou HPOS).
 * Aucune écriture en base hormis le transient/option de cache.
 *
 * Les 6 KPIs :
 *   1. Abonnés actifs (stock)        — nb `active`, split mensuels/annuels.
 *   2. MRR (stock)                   — somme des récurrents `active` /mois.
 *   3. Nouveaux abonnés (flux)       — créés sur [J-30, J].
 *   4. Résiliations (flux)           — annulation/fin sur [J-30, J].
 *   5. Abonnements en pause (stock)  — nb `on-hold`.
 *   6. Usage site (flux)             — part des abonnés `active` vus < 30 j.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clé du transient portant le payload complet des KPIs.
 *
 * @var string
 */
const _180C_STATS_TRANSIENT = '_180c_subscriber_stats';

/**
 * Clé de l'option archivant les instantanés « stock » quotidiens.
 *
 * @var string
 */
const _180C_STATS_HISTORY_OPTION = '_180c_subscriber_stats_history';

/**
 * Nom du hook WP-Cron de recalcul nocturne.
 *
 * @var string
 */
const _180C_STATS_CRON_HOOK = '_180c_subscriber_stats_recompute';

/**
 * Durée de vie du transient (~25 h : couvre un cycle quotidien + marge).
 *
 * @var int
 */
const _180C_STATS_TTL = 90000; // 25 * HOUR_IN_SECONDS.

/**
 * Nombre maximal d'instantanés conservés dans l'historique (~65 jours).
 *
 * @var int
 */
const _180C_STATS_HISTORY_MAX = 65;

/**
 * Taille de page pour la pagination des abonnements (quelques milliers d'abonnements).
 *
 * @var int
 */
const _180C_STATS_PER_PAGE = 200;

/**
 * Normalise un montant récurrent au mois selon la période de facturation.
 *
 * @param float  $total    Montant récurrent de l'abonnement.
 * @param string $period   Période WCS : day|week|month|year.
 * @param int    $interval Intervalle de facturation (≥ 1).
 * @return float Montant mensualisé (0 si données inexploitables).
 */
function _180c_stats_monthly_amount( float $total, string $period, int $interval ): float {
	if ( $total <= 0 || $interval < 1 ) {
		return 0.0;
	}

	// Nombre de mois couverts par un cycle de facturation.
	$months_per_cycle = array(
		'day'   => 1 / 30,
		'week'  => 7 / 30,
		'month' => 1.0,
		'year'  => 12.0,
	);

	$factor = $months_per_cycle[ $period ] ?? 1.0;
	$months = $factor * $interval;

	return ( $months > 0 ) ? ( $total / $months ) : 0.0;
}

/**
 * Calcule l'intégralité des KPIs, persiste le cache et l'historique.
 *
 * Effectue une seule passe paginée sur tous les abonnements pour les KPIs flux
 * (créations, résiliations) et stock (active, on-hold, MRR), puis croise les
 * abonnés actifs avec leur meta `_180c_last_seen` pour l'usage site.
 *
 * @return array Payload structuré des 6 KPIs (voir _180c_stats_neutral_payload).
 */
function _180c_compute_subscriber_stats(): array {
	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		$payload = _180c_stats_neutral_payload();
		set_transient( _180C_STATS_TRANSIENT, $payload, _180C_STATS_TTL );
		return $payload;
	}

	$now = time();
	$d30 = $now - 30 * DAY_IN_SECONDS;
	$d60 = $now - 60 * DAY_IN_SECONDS;

	// Compteurs stock.
	$active       = 0;
	$active_month = 0;
	$active_year  = 0;
	$on_hold      = 0;
	$mrr          = 0.0;
	$active_users = array(); // IDs uniques des abonnés actifs (pour KPI usage).

	// Compteurs flux (fenêtres courante [J-30,J] et précédente [J-60,J-30[).
	$new_cur     = 0;
	$new_prev    = 0;
	$cancel_cur  = 0;
	$cancel_prev = 0;

	// Pagination par offset : wcs_get_subscriptions() ignore l'argument `paged`
	// (vérifié sur cette install), un ordre stable + offset est donc requis pour
	// ne pas reboucler indéfiniment sur la même page.
	$offset = 0;
	do {
		$batch = wcs_get_subscriptions(
			array(
				'subscriptions_per_page' => _180C_STATS_PER_PAGE,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			)
		);

		foreach ( $batch as $subscription ) {
			if ( ! $subscription instanceof WC_Subscription ) {
				continue;
			}

			$status = $subscription->get_status();

			// --- KPI 1/2 : stock actifs + MRR + split période. ---
			if ( 'active' === $status ) {
				++$active;

				$period = (string) $subscription->get_billing_period();
				if ( 'year' === $period ) {
					++$active_year;
				} elseif ( 'month' === $period ) {
					++$active_month;
				}

				$mrr += _180c_stats_monthly_amount(
					(float) $subscription->get_total(),
					$period,
					(int) $subscription->get_billing_interval()
				);

				$uid = (int) $subscription->get_user_id();
				if ( $uid > 0 ) {
					$active_users[ $uid ] = true;
				}
			}

			// --- KPI 5 : stock en pause. ---
			if ( 'on-hold' === $status ) {
				++$on_hold;
			}

			// --- KPI 3 : nouveaux abonnés (date de création, tous statuts). ---
			$created = (int) $subscription->get_time( 'date_created' );
			if ( $created > 0 ) {
				if ( $created >= $d30 && $created <= $now ) {
					++$new_cur;
				} elseif ( $created >= $d60 && $created < $d30 ) {
					++$new_prev;
				}
			}

			// --- KPI 4 : résiliations (date d'annulation, sinon de fin). ---
			$cancelled_ts = (int) $subscription->get_time( 'cancelled' );
			if ( $cancelled_ts <= 0 ) {
				$cancelled_ts = (int) $subscription->get_time( 'end' );
			}
			if ( $cancelled_ts > 0 ) {
				if ( $cancelled_ts >= $d30 && $cancelled_ts <= $now ) {
					++$cancel_cur;
				} elseif ( $cancelled_ts >= $d60 && $cancelled_ts < $d30 ) {
					++$cancel_prev;
				}
			}
		}

		$count   = count( $batch );
		$offset += _180C_STATS_PER_PAGE;
	} while ( $count >= _180C_STATS_PER_PAGE );

	// --- KPI 6 : usage site sur la base des abonnés actifs. ---
	$usage = _180c_stats_compute_usage( array_keys( $active_users ), $now, $d30, $d60 );

	// Variations stock vis-à-vis de l'instantané ~J-30 (null si indisponible).
	$prev = _180c_stats_history_lookup( $now - 30 * DAY_IN_SECONDS );

	$payload = array(
		'generated_at'    => current_time( 'mysql' ),
		'wcs_available'   => true,
		'last_seen_since' => (string) get_option( '_180c_last_seen_since', '' ),
		'kpis'            => array(
			'active'        => array(
				'value'          => $active,
				'monthly'        => $active_month,
				'yearly'         => $active_year,
				'prev'           => isset( $prev['active'] ) ? (int) $prev['active'] : null,
				'positive_is_up' => true,
			),
			'mrr'           => array(
				'value'          => round( $mrr, 2 ),
				'prev'           => isset( $prev['mrr'] ) ? (float) $prev['mrr'] : null,
				'positive_is_up' => true,
			),
			'new'           => array(
				'value'          => $new_cur,
				'prev'           => $new_prev,
				'positive_is_up' => true,
			),
			'cancellations' => array(
				'value'          => $cancel_cur,
				'prev'           => $cancel_prev,
				'positive_is_up' => false,
			),
			'on_hold'       => array(
				'value'          => $on_hold,
				'prev'           => isset( $prev['on_hold'] ) ? (int) $prev['on_hold'] : null,
				'positive_is_up' => false,
			),
			'usage'         => array(
				'value'          => $usage['current'],
				'total'          => $usage['total'],
				'prev'           => $usage['previous'],
				'prev_total'     => $usage['total'],
				'positive_is_up' => true,
			),
		),
	);

	set_transient( _180C_STATS_TRANSIENT, $payload, _180C_STATS_TTL );
	_180c_stats_history_append(
		array(
			'active'  => $active,
			'mrr'     => round( $mrr, 2 ),
			'on_hold' => $on_hold,
			'monthly' => $active_month,
			'yearly'  => $active_year,
		)
	);

	return $payload;
}

/**
 * Calcule l'usage site : abonnés actifs vus sur la fenêtre courante/précédente.
 *
 * Lit la meta `_180c_last_seen` (heure du site) des abonnés actifs ; amorce le
 * cache meta en une requête pour éviter N lectures unitaires.
 *
 * @param int[] $user_ids IDs des abonnés actifs.
 * @param int   $now      Timestamp courant (UTC).
 * @param int   $d30      Borne J-30 (UTC).
 * @param int   $d60      Borne J-60 (UTC).
 * @return array{current:int,previous:int,total:int}
 */
function _180c_stats_compute_usage( array $user_ids, int $now, int $d30, int $d60 ): array {
	$total = count( $user_ids );
	if ( 0 === $total ) {
		return array(
			'current'  => 0,
			'previous' => 0,
			'total'    => 0,
		);
	}

	// Amorce le cache meta utilisateurs en une passe.
	update_meta_cache( 'user', $user_ids );

	$current  = 0;
	$previous = 0;

	foreach ( $user_ids as $uid ) {
		$last = (string) get_user_meta( $uid, '_180c_last_seen', true );
		if ( '' === $last ) {
			continue;
		}

		$ts = strtotime( $last );
		if ( ! $ts ) {
			continue;
		}

		if ( $ts >= $d30 && $ts <= $now ) {
			++$current;
		} elseif ( $ts >= $d60 && $ts < $d30 ) {
			++$previous;
		}
	}

	return array(
		'current'  => $current,
		'previous' => $previous,
		'total'    => $total,
	);
}

/**
 * Payload neutre renvoyé lorsque WC Subscriptions est indisponible.
 *
 * @return array Structure identique au payload nominal, valeurs à zéro.
 */
function _180c_stats_neutral_payload(): array {
	$stock = array(
		'value'          => 0,
		'prev'           => null,
		'positive_is_up' => true,
	);

	return array(
		'generated_at'    => current_time( 'mysql' ),
		'wcs_available'   => false,
		'last_seen_since' => (string) get_option( '_180c_last_seen_since', '' ),
		'kpis'            => array(
			'active'        => array(
				'value'          => 0,
				'monthly'        => 0,
				'yearly'         => 0,
				'prev'           => null,
				'positive_is_up' => true,
			),
			'mrr'           => $stock,
			'new'           => array(
				'value'          => 0,
				'prev'           => 0,
				'positive_is_up' => true,
			),
			'cancellations' => array(
				'value'          => 0,
				'prev'           => 0,
				'positive_is_up' => false,
			),
			'on_hold'       => array(
				'value'          => 0,
				'prev'           => null,
				'positive_is_up' => false,
			),
			'usage'         => array(
				'value'          => 0,
				'total'          => 0,
				'prev'           => null,
				'prev_total'     => null,
				'positive_is_up' => true,
			),
		),
	);
}

/**
 * Lit le payload des KPIs depuis le cache, avec calcul de secours.
 *
 * @return array Payload des 6 KPIs.
 */
function _180c_get_subscriber_stats(): array {
	$cached = get_transient( _180C_STATS_TRANSIENT );
	if ( is_array( $cached ) && isset( $cached['kpis'] ) ) {
		return $cached;
	}

	return _180c_compute_subscriber_stats();
}

/**
 * Déclenche un recalcul immédiat (vérification manuelle, WP-CLI).
 *
 * @return array Payload fraîchement calculé.
 */
function _180c_subscriber_stats_recompute_now(): array {
	return _180c_compute_subscriber_stats();
}

/**
 * Ajoute l'instantané « stock » du jour à l'historique, élagué à la fenêtre.
 *
 * Indexé par date `Ymd` (heure du site) : un seul instantané par jour, le
 * dernier calcul du jour écrase le précédent. Conserve ~65 entrées.
 *
 * @param array{active:int,mrr:float,on_hold:int,monthly:int,yearly:int} $snapshot Instantané.
 * @return void
 */
function _180c_stats_history_append( array $snapshot ): void {
	$history = get_option( _180C_STATS_HISTORY_OPTION, array() );
	if ( ! is_array( $history ) ) {
		$history = array();
	}

	$history[ current_time( 'Ymd' ) ] = $snapshot;

	// Tri par clé (date) croissante puis élagage des entrées les plus anciennes.
	ksort( $history );
	if ( count( $history ) > _180C_STATS_HISTORY_MAX ) {
		$history = array_slice( $history, -_180C_STATS_HISTORY_MAX, null, true );
	}

	update_option( _180C_STATS_HISTORY_OPTION, $history, false );
}

/**
 * Retrouve l'instantané stock le plus proche d'une date cible.
 *
 * Cherche d'abord une entrée exacte au jour `Ymd` de la cible, sinon l'entrée
 * disponible la plus proche dans une tolérance de ±3 jours (la collecte peut
 * comporter des trous si le cron a été manqué).
 *
 * @param int $target_ts Timestamp de la date cible (~J-30).
 * @return array|null Instantané trouvé, ou null si l'historique est trop court.
 */
function _180c_stats_history_lookup( int $target_ts ): ?array {
	$history = get_option( _180C_STATS_HISTORY_OPTION, array() );
	if ( ! is_array( $history ) || empty( $history ) ) {
		return null;
	}

	$target_day = (int) gmdate( 'Ymd', $target_ts );

	$best      = null;
	$best_diff = PHP_INT_MAX;
	foreach ( $history as $day => $snapshot ) {
		$diff = abs( _180c_stats_days_between( (int) $day, $target_day ) );
		if ( $diff < $best_diff ) {
			$best_diff = $diff;
			$best      = $snapshot;
		}
	}

	return ( $best_diff <= 3 && is_array( $best ) ) ? $best : null;
}

/**
 * Écart en jours entre deux dates au format entier `Ymd`.
 *
 * @param int $a Date A (Ymd).
 * @param int $b Date B (Ymd).
 * @return int Nombre de jours signé (a - b), 0 si parsing impossible.
 */
function _180c_stats_days_between( int $a, int $b ): int {
	$ta = strtotime( (string) $a );
	$tb = strtotime( (string) $b );
	if ( ! $ta || ! $tb ) {
		return 0;
	}

	return (int) round( ( $ta - $tb ) / DAY_IN_SECONDS );
}

/**
 * Hook de recalcul nocturne (callback WP-Cron).
 *
 * @return void
 */
function _180c_subscriber_stats_cron() {
	_180c_compute_subscriber_stats();
}
add_action( _180C_STATS_CRON_HOOK, '_180c_subscriber_stats_cron' );

/**
 * Planifie l'événement quotidien s'il ne l'est pas déjà.
 *
 * @return void
 */
function _180c_subscriber_stats_schedule() {
	if ( ! wp_next_scheduled( _180C_STATS_CRON_HOOK ) ) {
		// Décalé à +1 h pour ne pas concurrencer un calcul à la volée au boot.
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', _180C_STATS_CRON_HOOK );
	}
}
add_action( 'after_setup_theme', '_180c_subscriber_stats_schedule' );

/**
 * Déprogramme l'événement à la désactivation du thème.
 *
 * @return void
 */
function _180c_subscriber_stats_unschedule() {
	$timestamp = wp_next_scheduled( _180C_STATS_CRON_HOOK );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, _180C_STATS_CRON_HOOK );
	}
}
add_action( 'switch_theme', '_180c_subscriber_stats_unschedule' );
