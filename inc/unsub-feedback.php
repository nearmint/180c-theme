<?php
/**
 * Service feedback de désabonnement — couche d'accès aux données.
 *
 * Table custom `{prefix}180c_unsub_feedback` : 1 ligne = 1 motif de
 * désabonnement collecté dans le parcours de rétention (stepper, écran 3).
 * Le motif (`reason`) est un slug validé contre la liste blanche
 * `_180c_unsub_reasons()` ; le commentaire libre (`comment`) est optionnel.
 *
 * Pattern de migration « vivant » calqué sur inc/favorites.php :
 *   - création via dbDelta sur `after_switch_theme` ;
 *   - self-heal versionné sur `init` (_180c_unsub_maybe_upgrade), pour que la
 *     table se crée sans re-switch du thème (dev / shared hosting sans staging).
 * Aucun script one-shot CLI.
 *
 * Ce fichier ne contient QUE la couche données : ni REST, ni admin, ni markup.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Version du schéma de la table feedback désabonnement.
 *
 * Comparée via version_compare() à l'option `_180c_unsub_db_version`.
 *
 * @var string
 */
const _180C_UNSUB_DB_VERSION = '1.0.0';

/**
 * Clé du transient de cache du widget tableau de bord « Désabonnements ».
 *
 * Déclarée ici (couche données, toujours chargée) et non dans
 * inc/admin/dashboard-widget.php, qui n'est inclus qu'en admin : l'invalidation
 * doit rester possible depuis le front et depuis REST.
 *
 * @var string
 */
const _180C_UNSUB_DASHBOARD_CACHE = '_180c_unsub_dashboard_cache';

// ============================================================
// Nom de table
// ============================================================

/**
 * Nom complet (préfixé) de la table feedback désabonnement.
 *
 * @return string
 */
function _180c_unsub_table(): string {
	global $wpdb;
	return $wpdb->prefix . '180c_unsub_feedback';
}

// ============================================================
// Création / disponibilité de la table
// ============================================================

/**
 * Crée (ou met à niveau) la table feedback via dbDelta.
 *
 * Idempotent : dbDelta n'applique que les différences de schéma. Appelé à
 * l'activation du thème (`after_switch_theme`) et par le self-heal versionné.
 *
 * @return void
 */
function _180c_unsub_create_table(): void {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table           = _180c_unsub_table();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		subscription_id BIGINT UNSIGNED NOT NULL,
		reason VARCHAR(40) NOT NULL,
		comment TEXT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY created_at (created_at)
	) {$charset_collate};";

	dbDelta( $sql );

	delete_transient( '_180c_unsub_table_ok' );
}
add_action( 'after_switch_theme', '_180c_unsub_create_table' );

/**
 * Indique si la table feedback existe.
 *
 * Résultat mis en cache dans un transient (évite un SHOW TABLES répété).
 *
 * @return bool
 */
function _180c_unsub_table_exists(): bool {
	$cached = get_transient( '_180c_unsub_table_ok' );
	if ( false !== $cached ) {
		return (bool) $cached;
	}

	global $wpdb;
	$table = _180c_unsub_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

	set_transient( '_180c_unsub_table_ok', $exists ? 1 : 0, HOUR_IN_SECONDS );

	return $exists;
}

/**
 * Self-heal versionné : crée la table sur les installations existantes sans
 * nécessiter un re-switch du thème.
 *
 * `_180c_unsub_create_table()` n'est rejouée que sur `after_switch_theme` ;
 * cette garde versionnée (option `_180c_unsub_db_version`) la déclenche aussi
 * lorsqu'aucune version n'est encore enregistrée. dbDelta restant idempotent,
 * l'opération est sûre à répéter.
 *
 * @return void
 */
function _180c_unsub_maybe_upgrade(): void {
	$installed = (string) get_option( '_180c_unsub_db_version', '0' );

	if ( version_compare( $installed, _180C_UNSUB_DB_VERSION, '>=' ) ) {
		return;
	}

	_180c_unsub_create_table();

	update_option( '_180c_unsub_db_version', _180C_UNSUB_DB_VERSION, false );
}
add_action( 'init', '_180c_unsub_maybe_upgrade' );

// ============================================================
// Motifs de désabonnement (source unique)
// ============================================================

/**
 * Liste blanche des motifs de désabonnement : slug => libellé FR.
 *
 * Source unique réutilisée par le stepper (écran 3), l'écriture en base
 * (validation du slug) et l'export admin.
 *
 * @return array<string, string> Map slug => libellé.
 */
function _180c_unsub_reasons(): array {
	return array(
		'cuisine-moins'        => __( 'Je cuisine moins en ce moment', '180c' ),
		'recettes-inadaptees'  => __( 'Les recettes ne correspondent pas à mes attentes', '180c' ),
		'prix-trop-eleve'      => __( 'Le prix est trop élevé par rapport à mon utilisation', '180c' ),
		'inspiration-ailleurs' => __( 'Je trouve mon inspiration culinaire ailleurs', '180c' ),
		'probleme-technique'   => __( 'J\'ai rencontré des problèmes techniques (accès, paiement, application)', '180c' ),
		'autre'                => __( 'Autre', '180c' ),
	);
}

/**
 * Motifs historiques : slug => libellé FR.
 *
 * Ces slugs ont été collectés par d'anciennes versions du stepper et existent
 * encore en base ; ils ne sont plus proposés à la saisie. Ils sont donc tenus
 * HORS de `_180c_unsub_reasons()`, qui reste la liste blanche stricte de la
 * validation et de l'affichage du stepper — les réintégrer les rouvrirait à
 * l'enregistrement. Ils ne servent qu'à afficher les données historiques avec
 * un libellé lisible plutôt qu'un slug brut.
 *
 * @return array<string, string> Map slug => libellé.
 */
function _180c_unsub_legacy_reasons(): array {
	return array(
		'manque-de-temps' => __( 'Manque de temps', '180c' ),
		'plus-interesse'  => __( 'Plus intéressé·e', '180c' ),
	);
}

/**
 * Résout le libellé affichable d'un motif, actif ou historique.
 *
 * Point d'entrée unique de tous les affichages (widget, liste admin, export
 * CSV). Repli sur le slug brut si le motif n'est connu ni de la liste blanche
 * ni des motifs historiques.
 *
 * @param string $slug Slug du motif tel que stocké en base.
 * @return string Libellé, ou le slug lui-même à défaut.
 */
function _180c_unsub_reason_label( string $slug ): string {
	$labels = _180c_unsub_reasons() + _180c_unsub_legacy_reasons();

	return (string) ( $labels[ $slug ] ?? $slug );
}

// ============================================================
// Écriture
// ============================================================

/**
 * Enregistre un motif de désabonnement en base.
 *
 * Valide le motif contre la liste blanche `_180c_unsub_reasons()`, assainit et
 * tronque le commentaire, puis insère via $wpdb->insert (created_at serveur).
 *
 * @param int    $user_id         ID de l'utilisateur.
 * @param int    $subscription_id ID de l'abonnement WC Subscriptions.
 * @param string $reason          Slug du motif (doit exister dans la liste blanche).
 * @param string $comment         Commentaire libre optionnel.
 * @return bool True si la ligne a été insérée, false sinon (motif invalide,
 *              table absente, paramètres invalides ou échec SQL).
 */
function _180c_unsub_record( int $user_id, int $subscription_id, string $reason, string $comment = '' ): bool {
	if ( $user_id <= 0 || $subscription_id <= 0 ) {
		return false;
	}

	// Liste blanche stricte du motif.
	if ( ! array_key_exists( $reason, _180c_unsub_reasons() ) ) {
		return false;
	}

	if ( ! _180c_unsub_table_exists() ) {
		return false;
	}

	// Assainissement + troncature raisonnable du commentaire (sécurité multioctets).
	$comment    = sanitize_textarea_field( $comment );
	$max_length = 2000;
	if ( mb_strlen( $comment ) > $max_length ) {
		$comment = mb_substr( $comment, 0, $max_length );
	}

	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$inserted = $wpdb->insert(
		_180c_unsub_table(),
		array(
			'user_id'         => $user_id,
			'subscription_id' => $subscription_id,
			'reason'          => $reason,
			'comment'         => '' !== $comment ? $comment : null,
			'created_at'      => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%s', '%s', '%s' )
	);

	$recorded = false !== $inserted && $wpdb->insert_id > 0;

	if ( $recorded ) {
		// Le widget tableau de bord doit refléter la nouvelle réponse sans attendre
		// l'expiration du cache.
		_180c_unsub_flush_dashboard_cache();
	}

	return $recorded;
}

// ============================================================
// Invalidation du cache du widget tableau de bord
// ============================================================

/**
 * Purge le cache agrégé du widget « Désabonnements ».
 *
 * @return void
 */
function _180c_unsub_flush_dashboard_cache(): void {
	delete_transient( _180C_UNSUB_DASHBOARD_CACHE );
}

/**
 * Invalide le cache du widget quand un abonnement bascule en résiliation.
 *
 * Le widget agrège désormais les abonnements `cancelled` / `pending-cancel`
 * (et plus seulement les réponses au sondage) : tout passage vers l'un de ces
 * deux statuts change ses chiffres, quelle que soit l'origine de la résiliation
 * (stepper front, back-office, passerelle de paiement, échéance).
 *
 * @param WC_Subscription|mixed $subscription Abonnement concerné (non utilisé).
 * @param string                $new_status   Nouveau statut, sans préfixe `wc-`.
 * @return void
 */
function _180c_unsub_dashboard_invalidate_on_status( $subscription, $new_status = '' ): void {
	unset( $subscription );

	if ( ! in_array( (string) $new_status, array( 'cancelled', 'pending-cancel' ), true ) ) {
		return;
	}

	_180c_unsub_flush_dashboard_cache();
}
add_action( 'woocommerce_subscription_status_updated', '_180c_unsub_dashboard_invalidate_on_status', 10, 2 );
