<?php
/**
 * Suppression de compte — journal anonyme.
 *
 * Une ligne par suppression réussie, SANS aucun identifiant : ni user_id, ni
 * e-mail, ni IP. Le journal ne sert qu'à mesurer le volume et les motifs de
 * départ ; il ne doit jamais permettre de reconstituer qui est parti.
 *
 * La table n'est pas créée par le thème : elle est posée en base par un script
 * SQL de migration (non versionné). Si elle manque, l'écriture
 * est simplement abandonnée — jamais au prix de la suppression elle-même, qui
 * est déjà faite à ce stade.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nom complet (préfixé) de la table du journal.
 *
 * @return string Nom de table.
 */
function _180c_account_deletion_log_table(): string {
	global $wpdb;

	return $wpdb->prefix . '180c_account_deletions';
}

/**
 * Indique si la table du journal existe.
 *
 * @return bool
 */
function _180c_account_deletion_log_table_exists(): bool {
	global $wpdb;

	$table = _180c_account_deletion_log_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
}

/**
 * Enregistre une suppression dans le journal.
 *
 * Les colonnes `reason` et `reason_text` sont RÉSERVÉES : le formulaire ne pose
 * plus de question, elles sont donc écrites vides. Elles restent en base pour
 * ne pas imposer de migration si un motif redevient un jour pertinent.
 *
 * @param array $entry {
 *     Données anonymes de la suppression.
 *
 *     @type bool $had_subscription   Le compte avait-il au moins un abonnement.
 *     @type bool $newsletter_removed Le contact a-t-il été retiré de l'audience.
 * }
 * @return bool Vrai si la ligne a été écrite.
 */
function _180c_account_deletion_log_record( array $entry ): bool {
	global $wpdb;

	if ( ! _180c_account_deletion_log_table_exists() ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'échec non bloquant, sans donnée personnelle.
		error_log( '[180c account-deletion] table de journal absente, écriture abandonnée' );

		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$inserted = $wpdb->insert(
		_180c_account_deletion_log_table(),
		array(
			'deleted_at'         => current_time( 'mysql', true ),
			'reason'             => '',
			'reason_text'        => null,
			'had_subscription'   => ! empty( $entry['had_subscription'] ) ? 1 : 0,
			'newsletter_removed' => ! empty( $entry['newsletter_removed'] ) ? 1 : 0,
		),
		array( '%s', '%s', '%s', '%d', '%d' )
	);

	return false !== $inserted;
}
