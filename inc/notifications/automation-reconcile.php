<?php
/**
 * Réconciliation de l'automation « push à la publication d'une recette ».
 *
 * Ce cron n'envoie RIEN : la livraison est tenue par OneSignal via `send_after`.
 * Son rôle est de remettre l'état WordPress en phase avec ce qui s'est
 * réellement passé côté OneSignal, ce que WordPress ne peut pas savoir seul :
 *
 *   1. créneau franchi          -> promotion en envoi délivré (post publié,
 *                                  _180c_onesignal_id + _180c_notif_sent_at
 *                                  écrites, cache du feed purgé) ;
 *   2. recette cible dépubliée  -> annulation côté OneSignal ;
 *   3. auto-réparation          -> reprogrammation quand l'automation est
 *                                  active, qu'aucune notification n'est
 *                                  programmée et qu'une recette a été publiée
 *                                  depuis le dernier créneau passé. Couvre les
 *                                  échecs API transitoires au moment de la
 *                                  publication.
 *
 * Les étapes 1 et 2 s'exécutent même automation désactivée : couper
 * l'interrupteur ne doit ni faire disparaître une notification déjà délivrée,
 * ni laisser partir un envoi programmé sur une recette retirée. Seule
 * l'auto-réparation, qui CRÉE de l'envoi, est conditionnée à l'interrupteur.
 *
 * Fréquence horaire : la granularité suffit, l'heure d'envoi étant tenue par
 * OneSignal. En hébergement mutualisé, WP-Cron se déclenche au trafic — un retard de
 * quelques heures ne décale donc que l'apparition dans le feed app, jamais la
 * réception du push.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook du cron de réconciliation.
 */
define( '_180C_NOTIF_RECONCILE_HOOK', '_180c_notif_automation_reconcile' );

/**
 * Programme le cron horaire s'il ne l'est pas déjà.
 *
 * Accroché à l'activation du thème ET à `init` : en production le thème n'est jamais
 * « réactivé », donc `after_switch_theme` seul ne suffirait pas à installer le
 * cron sur un site déjà en production.
 *
 * @return void
 */
function _180c_notif_reconcile_schedule(): void {
	if ( wp_next_scheduled( _180C_NOTIF_RECONCILE_HOOK ) ) {
		return;
	}

	$scheduled = wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', _180C_NOTIF_RECONCILE_HOOK );

	if ( false !== $scheduled && ! is_wp_error( $scheduled ) ) {
		return;
	}

	/*
	 * Sans ce cron, plus rien n'est jamais promu : les notifications partent
	 * bien (OneSignal tient le créneau) mais n'apparaissent jamais dans le feed
	 * de l'app. Une panne aussi silencieuse doit être visible dans le panneau.
	 * Une seule entrée par heure : cette fonction tourne à chaque `init`.
	 */
	if ( ! get_transient( '_180c_notif_reconcile_schedule_failed' ) ) {
		set_transient( '_180c_notif_reconcile_schedule_failed', 1, HOUR_IN_SECONDS );
		_180c_notif_automation_log(
			'api_error',
			array(
				'reason' => __( 'Le cron de réconciliation n\'a pas pu être programmé : les notifications programmées partiront, mais ne seront jamais promues ni visibles dans le feed de l\'app.', '180c' ),
			)
		);
	}
}
add_action( 'after_switch_theme', '_180c_notif_reconcile_schedule' );
add_action( 'init', '_180c_notif_reconcile_schedule', 20 );

/**
 * Déprogramme le cron à la désactivation du thème.
 *
 * @return void
 */
function _180c_notif_reconcile_unschedule(): void {
	wp_clear_scheduled_hook( _180C_NOTIF_RECONCILE_HOOK );
}
add_action( 'switch_theme', '_180c_notif_reconcile_unschedule' );

/**
 * Retourne les notifications automatiques en attente de livraison.
 *
 * @return WP_Post[]
 */
function _180c_notif_pending_scheduled_posts(): array {
	return get_posts(
		array(
			'post_type'      => _180C_NOTIF_POST_TYPE,
			'post_status'    => array( 'draft', 'pending', 'private', 'publish' ),
			'posts_per_page' => 50,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cron d'administration, volume de l'ordre de l'unité.
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => _180C_NOTIF_META_ORIGIN,
					'value' => 'auto',
				),
				array(
					'key'     => _180C_NOTIF_META_SCHEDULED_ID,
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => _180C_NOTIF_META_CANCELLED_AT,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_180c_onesignal_id',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
}

/**
 * Promeut une notification programmée en envoi délivré.
 *
 * C'est l'unique endroit où une notification d'origine `auto` devient visible
 * du feed app : le statut passe à `publish` et les deux metas interrogées par
 * `inc/notifications/rest.php` sont écrites au même moment.
 *
 * @param WP_Post $notif Notification.
 * @return void
 */
function _180c_notif_promote_scheduled( WP_Post $notif ): void {
	$notif_id      = (int) $notif->ID;
	$onesignal_id  = (string) get_post_meta( $notif_id, _180C_NOTIF_META_SCHEDULED_ID, true );
	$scheduled_for = (string) get_post_meta( $notif_id, _180C_NOTIF_META_SCHEDULED_FOR, true );

	/*
	 * La PUBLICATION d'abord, les metas ensuite. L'invariant du feed est
	 * « metas d'envoi écrites => notification visible » : l'inverse écrirait des
	 * metas affirmant un envoi délivré sur un post resté en brouillon, donc
	 * invisible à jamais. En cas d'échec on ne touche à rien : la passe suivante
	 * réessaiera dans l'heure.
	 */
	if ( 'publish' !== $notif->post_status ) {
		$published = wp_update_post(
			array(
				'ID'          => $notif_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $published ) ) {
			_180c_notif_automation_log(
				'api_error',
				array(
					'notif_id'  => $notif_id,
					'recipe_id' => (int) get_post_meta( $notif_id, '_180c_notif_target_id', true ),
					'reason'    => sprintf(
						/* translators: %s: message d'erreur. */
						__( 'Promotion impossible (publication refusée) : %s. Nouvelle tentative à la passe suivante.', '180c' ),
						$published->get_error_message()
					),
				)
			);

			return;
		}
	}

	update_post_meta( $notif_id, '_180c_onesignal_id', sanitize_text_field( $onesignal_id ) );
	update_post_meta( $notif_id, '_180c_notif_sent_at', '' !== $scheduled_for ? $scheduled_for : gmdate( 'Y-m-d H:i:s' ) );
	delete_post_meta( $notif_id, '_180c_notif_last_error' );

	// Appel direct : `inc/rest/routes.php` (donc `inc/notifications/rest.php`)
	// est requis sans condition à `inc/bootstrap.php:127`, avant ce fichier.
	// Un `function_exists()` ici serait une garde qui ne peut pas se déclencher.
	_180c_notif_purge_feed_cache();

	_180c_notif_automation_log(
		'sent',
		array(
			'notif_id'  => $notif_id,
			'recipe_id' => (int) get_post_meta( $notif_id, '_180c_notif_target_id', true ),
			'reason'    => sprintf(
				/* translators: %s: date GMT du créneau franchi. */
				__( 'Créneau franchi (%s) : notification promue et publiée dans le feed.', '180c' ),
				$scheduled_for
			),
		)
	);
}

/**
 * Retourne l'horodatage du dernier créneau passé.
 *
 * Symétrique de `_180c_notif_next_slot()` : même raisonnement dans le fuseau du
 * site, même reconstruction de l'heure après chaque saut de semaine pour rester
 * juste de part et d'autre d'un changement d'heure.
 *
 * @param int|null $from_timestamp Horodatage de référence (défaut : maintenant).
 * @return int
 */
function _180c_notif_previous_slot( ?int $from_timestamp = null ): int {
	$settings = _180c_notif_automation_settings();
	$from     = null !== $from_timestamp ? $from_timestamp : time();
	$day      = (int) $settings['day'];

	$parts  = explode( ':', (string) $settings['time'] );
	$hour   = isset( $parts[0] ) ? (int) $parts[0] : 0;
	$minute = isset( $parts[1] ) ? (int) $parts[1] : 0;

	$tz   = wp_timezone();
	$slot = ( new DateTimeImmutable( '@' . $from ) )->setTimezone( $tz )->setTime( $hour, $minute );

	$delta = ( (int) $slot->format( 'N' ) - $day + 7 ) % 7;
	if ( $delta > 0 ) {
		$slot = $slot->modify( '-' . $delta . ' days' )->setTime( $hour, $minute );
	}

	$guard = 0;
	while ( $slot->getTimestamp() >= $from && $guard < 3 ) {
		$slot = $slot->modify( '-7 days' )->setTime( $hour, $minute );
		++$guard;
	}

	return $slot->getTimestamp();
}

/**
 * Indique si une recette a déjà fait l'objet d'une notification délivrée.
 *
 * Garde-fou de l'auto-réparation : mieux vaut ne rien envoyer qu'envoyer deux
 * fois la même recette.
 *
 * @param int $recipe_id ID de la recette.
 * @return bool
 */
function _180c_notif_recipe_already_notified( int $recipe_id ): bool {
	$posts = get_posts(
		array(
			'post_type'      => _180C_NOTIF_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cron d'administration, requête ponctuelle.
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => '_180c_notif_target_id',
					'value' => $recipe_id,
				),
				array(
					'key'     => '_180c_onesignal_id',
					'value'   => '',
					'compare' => '!=',
				),
			),
		)
	);

	return ! empty( $posts );
}

/**
 * Auto-réparation : reprogramme si un créneau a été manqué.
 *
 * @return void
 */
function _180c_notif_reconcile_repair(): void {
	if ( ! _180c_notif_automation_is_enabled() ) {
		return;
	}

	if ( _180c_notif_scheduled_post() instanceof WP_Post ) {
		return;
	}

	$since = _180c_notif_previous_slot();

	$candidates = get_posts(
		array(
			'post_type'      => 'recipe',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'after'     => gmdate( 'Y-m-d H:i:s', $since ),
					'column'    => 'post_date_gmt',
					'inclusive' => false,
				),
			),
		)
	);

	if ( empty( $candidates ) ) {
		return;
	}

	$recipe_id = (int) $candidates[0];

	if ( _180c_notif_recipe_already_notified( $recipe_id ) ) {
		return;
	}

	_180c_notif_automation_log(
		'repaired',
		array(
			'recipe_id' => $recipe_id,
			'reason'    => __( 'Aucune notification programmée alors qu\'une recette a été publiée depuis le dernier créneau : reprogrammation.', '180c' ),
		)
	);

	_180c_notif_schedule_for_recipe( $recipe_id, __( 'auto-réparation', '180c' ) );
}

/**
 * Passe de réconciliation.
 *
 * @return void
 */
function _180c_notif_automation_reconcile(): void {
	$now = time();

	foreach ( _180c_notif_pending_scheduled_posts() as $notif ) {
		$notif_id      = (int) $notif->ID;
		$scheduled_for = (string) get_post_meta( $notif_id, _180C_NOTIF_META_SCHEDULED_FOR, true );
		$slot_ts       = '' !== $scheduled_for ? (int) strtotime( $scheduled_for . ' UTC' ) : 0;

		// Créneau franchi : OneSignal a livré. Le test passe AVANT celui du
		// statut de la recette — une notification déjà partie ne s'annule plus,
		// et la masquer du feed reviendrait à mentir sur ce que les abonnés ont
		// reçu.
		if ( $slot_ts > 0 && $slot_ts <= $now ) {
			_180c_notif_promote_scheduled( $notif );
			continue;
		}

		$recipe_id = (int) get_post_meta( $notif_id, '_180c_notif_target_id', true );
		$recipe    = $recipe_id ? get_post( $recipe_id ) : null;

		if ( ! $recipe instanceof WP_Post || 'publish' !== $recipe->post_status ) {
			_180c_notif_cancel_scheduled(
				$notif_id,
				__( 'Recette cible absente ou dépubliée (constaté à la réconciliation)', '180c' )
			);
		}
	}

	_180c_notif_reconcile_repair();
}
add_action( _180C_NOTIF_RECONCILE_HOOK, '_180c_notif_automation_reconcile' );
