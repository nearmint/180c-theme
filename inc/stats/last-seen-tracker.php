<?php
/**
 * Stats — Tracker « dernière visite » des utilisateurs connectés.
 *
 * Mémorise dans la user meta `_180c_last_seen` la dernière fois qu'un
 * utilisateur connecté a consulté le site en front-end. Throttlé à une
 * écriture par jour et par utilisateur : si la meta existe déjà et porte la
 * date du jour (heure du site), on ne réécrit pas.
 *
 * Sert au KPI « Usage site (30 j) » du widget abonnés
 * (cf. inc/stats/subscriber-stats.php). Aucune écriture côté admin, AJAX,
 * cron ou REST : uniquement sur des vues front authentifiées.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Met à jour `_180c_last_seen` au plus une fois par jour et par utilisateur.
 *
 * Lit la meta courante ; si elle est vide ou si sa partie date est antérieure
 * à aujourd'hui (`current_time('Ymd')`, heure du site), écrit l'horodatage
 * complet `current_time('mysql')`. Idempotent sur la journée.
 *
 * @return void
 */
function _180c_update_last_seen() {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return;
	}

	$today = current_time( 'Ymd' );
	$last  = (string) get_user_meta( $user_id, '_180c_last_seen', true );

	// Partie « Ymd » du dernier horodatage stocké (mysql : "Y-m-d H:i:s").
	$last_day = ( '' !== $last ) ? gmdate( 'Ymd', strtotime( $last ) ) : '';

	if ( '' !== $last && $last_day >= $today ) {
		// Déjà vu aujourd'hui : rien à écrire.
		return;
	}

	update_user_meta( $user_id, '_180c_last_seen', current_time( 'mysql' ) );
}

/**
 * Déclenche la mise à jour sur les vues front authentifiées uniquement.
 *
 * Gardes strictes : exclut l'admin, l'AJAX, le cron et les requêtes REST,
 * pour ne tracer que des consultations « humaines » du site public.
 *
 * @return void
 */
function _180c_last_seen_maybe_track() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	_180c_update_last_seen();
}
add_action( 'template_redirect', '_180c_last_seen_maybe_track' );

/**
 * Mémorise la date de déploiement du tracker (première activation).
 *
 * Sert de borne « depuis le … » au KPI « Usage site » tant que la collecte a
 * moins de 30 jours d'historique. Posée une seule fois.
 *
 * @return void
 */
function _180c_last_seen_record_since() {
	if ( false === get_option( '_180c_last_seen_since', false ) ) {
		add_option( '_180c_last_seen_since', current_time( 'mysql' ), '', false );
	}
}
add_action( 'after_setup_theme', '_180c_last_seen_record_since' );
