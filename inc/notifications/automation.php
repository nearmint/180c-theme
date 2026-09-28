<?php
/**
 * Moteur de l'automation « push à la publication d'une recette ».
 *
 * Principe : l'envoi différé est délégué à OneSignal via `send_after`, jamais à
 * WP-Cron. Sur l'hébergement mutualisé, WP-Cron se déclenche au trafic et
 * ne garantit aucune heure ; OneSignal, lui, tient le créneau à la seconde.
 * WP-Cron n'intervient qu'en réconciliation (automation-reconcile.php), pour
 * remettre l'état WordPress en phase avec ce qui a réellement été délivré.
 *
 * Cycle de vie d'une notification d'origine `auto` :
 *
 *   publication d'une recette
 *     -> la notification programmée en cours (autre recette) est annulée
 *     -> un post `180c_notification` est créé en `draft`, origine `auto`
 *     -> POST OneSignal avec `send_after` = prochain créneau
 *     -> _180c_notif_scheduled_os_id + _180c_notif_scheduled_for
 *   modification de la recette cible
 *     -> annulation puis reprogrammation, payload régénéré (liaison tardive)
 *   dépublication / corbeille / suppression de la recette cible
 *     -> annulation sèche, sans reprogrammation
 *   créneau franchi
 *     -> la réconciliation promeut le post en `publish` et écrit
 *        _180c_onesignal_id + _180c_notif_sent_at : la notification n'entre
 *        dans le feed app qu'à ce moment précis.
 *
 * Ce module ne restaure PAS l'ancien `inc/notifications/auto-publish.php`,
 * supprimé au Lot D. La meta `_180c_notif_auto_done` posée à l'époque n'est ni
 * lue, ni écrite, ni supprimée.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Origine de la notification : `auto` ou `manual`.
 */
define( '_180C_NOTIF_META_ORIGIN', '_180c_notif_origin' );

/**
 * Date MySQL GMT du créneau de livraison programmé.
 */
define( '_180C_NOTIF_META_SCHEDULED_FOR', '_180c_notif_scheduled_for' );

/**
 * Identifiant OneSignal de l'envoi programmé (distinct de _180c_onesignal_id,
 * qui ne marque qu'un envoi effectivement délivré).
 */
define( '_180C_NOTIF_META_SCHEDULED_ID', '_180c_notif_scheduled_os_id' );

/**
 * Date MySQL GMT d'annulation.
 */
define( '_180C_NOTIF_META_CANCELLED_AT', '_180c_notif_cancelled_at' );

/**
 * Sous-titre rendu (iOS et macOS uniquement côté OneSignal).
 */
define( '_180C_NOTIF_META_SUBTITLE', '_180c_notif_subtitle' );

/**
 * Empreinte du contenu programmé, pour ne reprogrammer qu'en cas de changement réel.
 */
define( '_180C_NOTIF_META_HASH', '_180c_notif_scheduled_hash' );

/**
 * Enregistre les post meta propres à l'automation.
 *
 * Toutes en `show_in_rest => false`, comme les metas historiques du CPT : le
 * feed app dédié est la seule surface de lecture publique.
 *
 * @return void
 */
function _180c_notif_register_automation_meta(): void {
	$metas = array(
		_180C_NOTIF_META_ORIGIN        => 'sanitize_key',
		_180C_NOTIF_META_SCHEDULED_FOR => 'sanitize_text_field',
		_180C_NOTIF_META_SCHEDULED_ID  => 'sanitize_text_field',
		_180C_NOTIF_META_CANCELLED_AT  => 'sanitize_text_field',
		_180C_NOTIF_META_SUBTITLE      => 'sanitize_text_field',
		_180C_NOTIF_META_HASH          => 'sanitize_text_field',
	);

	foreach ( $metas as $key => $sanitize ) {
		register_post_meta(
			_180C_NOTIF_POST_TYPE,
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => '_180c_notif_meta_auth',
			)
		);
	}
}
add_action( 'init', '_180c_notif_register_automation_meta', 11 );

/**
 * Retourne l'origine d'une notification.
 *
 * Les envois manuels historiques n'ont pas la meta : ils sont `manual`.
 *
 * @param int $notif_id ID de la notification.
 * @return string `auto` ou `manual`.
 */
function _180c_notif_origin( int $notif_id ): string {
	return 'auto' === get_post_meta( $notif_id, _180C_NOTIF_META_ORIGIN, true ) ? 'auto' : 'manual';
}

/**
 * Retourne la notification automatique actuellement programmée, s'il y en a une.
 *
 * Critères : origine `auto`, identifiant d'envoi programmé présent, ni annulée
 * ni déjà promue en envoi délivré.
 *
 * @return WP_Post|null
 */
function _180c_notif_scheduled_post(): ?WP_Post {
	$posts = get_posts(
		array(
			'post_type'      => _180C_NOTIF_POST_TYPE,
			'post_status'    => array( 'draft', 'pending', 'private', 'publish' ),
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Requête d'administration, volume négligeable (une poignée de posts).
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

	return ! empty( $posts ) ? $posts[0] : null;
}

/**
 * Rend les trois textes d'une notification pour une recette.
 *
 * @param int $recipe_id ID de la recette.
 * @return array{heading:string,subtitle:string,content:string}
 */
function _180c_notif_render_auto_texts( int $recipe_id ): array {
	$settings = _180c_notif_automation_settings();

	$heading = _180c_notif_render_template( (string) $settings['heading_template'], $recipe_id );
	if ( '' === $heading ) {
		// Un titre vide ferait échouer l'envoi (« Titre et corps vides »), et
		// une notification sans titre n'a de toute façon aucun sens.
		$heading = _180c_notif_token_value( '{titre}', $recipe_id );
	}

	return array(
		'heading'  => _180c_notif_truncate_words( $heading, 80 ),
		'subtitle' => _180c_notif_truncate_words(
			_180c_notif_render_template( (string) $settings['subtitle_template'], $recipe_id ),
			50
		),
		'content'  => _180c_notif_truncate_words(
			_180c_notif_render_template( (string) $settings['content_template'], $recipe_id ),
			_180C_NOTIF_BODY_MAX
		),
	);
}

/**
 * Écrit le contenu rendu sur le post de notification et retourne son empreinte.
 *
 * @param int $notif_id  ID de la notification.
 * @param int $recipe_id ID de la recette cible.
 * @return string Empreinte du contenu écrit.
 */
function _180c_notif_apply_auto_content( int $notif_id, int $recipe_id ): string {
	$settings = _180c_notif_automation_settings();
	$texts    = _180c_notif_render_auto_texts( $recipe_id );

	$updated = wp_update_post(
		array(
			'ID'         => $notif_id,
			'post_title' => $texts['heading'],
		),
		true
	);

	// Le titre du post EST le `headings` du payload, relu à l'envoi : un échec
	// silencieux ferait partir l'ancien titre.
	if ( is_wp_error( $updated ) ) {
		_180c_log(
			'Automation : titre de notification non enregistré',
			array(
				'notif_id' => $notif_id,
				'error'    => $updated->get_error_message(),
			),
			'error'
		);
	}

	update_post_meta( $notif_id, '_180c_notif_body', $texts['content'] );
	update_post_meta( $notif_id, '_180c_notif_target_type', 'recipe' );
	update_post_meta( $notif_id, '_180c_notif_target_id', $recipe_id );
	update_post_meta( $notif_id, '_180c_notif_segment', _180c_notif_sanitize_segment( (string) $settings['segment'] ) );

	if ( '' !== $texts['subtitle'] ) {
		update_post_meta( $notif_id, _180C_NOTIF_META_SUBTITLE, $texts['subtitle'] );
	} else {
		delete_post_meta( $notif_id, _180C_NOTIF_META_SUBTITLE );
	}

	return md5( (string) wp_json_encode( array( $recipe_id, $texts, $settings['segment'] ) ) );
}

/**
 * Injecte le sous-titre rendu dans le payload OneSignal.
 *
 * Passer par le filtre existant évite de modifier le client pour un champ qui
 * n'existe que sur le chemin automatique. Rappel de la documentation
 * (https://documentation.onesignal.com/docs/en/push) : « Secondary text
 * supported on iOS and macOS only (via APNs). Not available on Android or
 * web. »
 *
 * @param array $payload Payload OneSignal.
 * @param int   $post_id ID de la notification.
 * @return array
 */
function _180c_notif_payload_subtitle( $payload, $post_id ) {
	$subtitle = (string) get_post_meta( (int) $post_id, _180C_NOTIF_META_SUBTITLE, true );

	if ( '' !== trim( $subtitle ) ) {
		$payload['subtitle'] = array( 'en' => $subtitle );
	}

	return $payload;
}
add_filter( '_180c_onesignal_payload', '_180c_notif_payload_subtitle', 10, 2 );

/**
 * Programme (ou reprogramme) l'envoi différé d'une notification.
 *
 * @param int $notif_id ID de la notification.
 * @param int $slot_ts  Horodatage Unix du créneau.
 * @return true|WP_Error
 */
function _180c_notif_schedule_send( int $notif_id, int $slot_ts ) {
	/*
	 * Clé d'idempotence RÉGÉNÉRÉE à chaque programmation. Ne pas « optimiser »
	 * ce delete_post_meta : la documentation OneSignal
	 * (https://documentation.onesignal.com/reference/idempotent-notification-requests)
	 * est explicite — « If you reuse the same idempotency_key for different
	 * messages or events, only the first request will be processed », et le code
	 * d'exemple officiel ajoute « Do not reuse keys across logically distinct
	 * sends ». Les clés sont retenues 30 jours et rien n'indique qu'une
	 * annulation en libère une. Sans ce delete, la reprogrammation recevrait en
	 * réponse l'identifiant de l'envoi qui vient d'être ANNULÉ, et plus rien ne
	 * partirait — sans la moindre erreur visible.
	 */
	delete_post_meta( $notif_id, '_180c_notif_idempotency_key' );

	$result = _180c_onesignal_send( $notif_id, $slot_ts );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$onesignal_id = isset( $result['id'] ) ? (string) $result['id'] : '';

	/*
	 * ASSERTION DE CONTRAT, et non garde active : `_180c_onesignal_send()` ne
	 * retourne aujourd'hui un tableau que si l'identifiant est non vide — il le
	 * vérifie lui-même avant de rendre la main, en dry-run comme en réel
	 * (mesuré). Cette branche est donc INATTEIGNABLE en l'état ; elle ne
	 * protège de rien, elle documente et verrouille le contrat pour le jour où
	 * le client changera.
	 */
	if ( '' === $onesignal_id ) {
		return new WP_Error( 'notif_no_id', __( 'OneSignal n\'a pas retourné d\'identifiant.', '180c' ) );
	}

	update_post_meta( $notif_id, _180C_NOTIF_META_SCHEDULED_ID, $onesignal_id );
	update_post_meta( $notif_id, _180C_NOTIF_META_SCHEDULED_FOR, gmdate( 'Y-m-d H:i:s', $slot_ts ) );
	delete_post_meta( $notif_id, _180C_NOTIF_META_CANCELLED_AT );
	delete_post_meta( $notif_id, '_180c_notif_last_error' );

	return true;
}

/**
 * Annule l'envoi programmé d'une notification.
 *
 * @param int    $notif_id        ID de la notification.
 * @param string $reason          Motif, journalisé.
 * @param bool   $mark_cancelled  Écrire `_180c_notif_cancelled_at`. Faux lors
 *                                d'une reprogrammation, qui n'est pas une
 *                                annulation du point de vue de l'exploitant.
 * @return bool Vrai si l'état WordPress a pu être mis à jour.
 */
function _180c_notif_cancel_scheduled( int $notif_id, string $reason, bool $mark_cancelled = true ): bool {
	$onesignal_id = (string) get_post_meta( $notif_id, _180C_NOTIF_META_SCHEDULED_ID, true );

	if ( '' === $onesignal_id ) {
		return false;
	}

	$result = _180c_onesignal_cancel( $onesignal_id );

	if ( is_wp_error( $result ) && ! _180c_onesignal_cancel_is_soft_failure( $result ) ) {
		update_post_meta( $notif_id, '_180c_notif_last_error', sanitize_text_field( $result->get_error_message() ) );
		_180c_notif_automation_log(
			'api_error',
			array(
				'notif_id'  => $notif_id,
				'recipe_id' => (int) get_post_meta( $notif_id, '_180c_notif_target_id', true ),
				'reason'    => $result->get_error_message(),
			)
		);

		return false;
	}

	if ( is_wp_error( $result ) ) {
		// Échec doux : la notification est déjà partie ou n'existe plus côté
		// OneSignal. On journalise et on poursuit — rester bloqué sur un envoi
		// disparu ne servirait personne.
		_180c_notif_automation_log(
			'cancelled',
			array(
				'notif_id'  => $notif_id,
				'recipe_id' => (int) get_post_meta( $notif_id, '_180c_notif_target_id', true ),
				'reason'    => sprintf(
					/* translators: %s: message renvoyé par OneSignal. */
					__( 'Annulation sans effet côté OneSignal : %s', '180c' ),
					$result->get_error_message()
				),
			)
		);
	}

	delete_post_meta( $notif_id, _180C_NOTIF_META_SCHEDULED_ID );
	delete_post_meta( $notif_id, _180C_NOTIF_META_HASH );

	if ( $mark_cancelled ) {
		update_post_meta( $notif_id, _180C_NOTIF_META_CANCELLED_AT, gmdate( 'Y-m-d H:i:s' ) );
		_180c_notif_automation_log(
			'cancelled',
			array(
				'notif_id'  => $notif_id,
				'recipe_id' => (int) get_post_meta( $notif_id, '_180c_notif_target_id', true ),
				'reason'    => $reason,
			)
		);
	}

	return true;
}

/**
 * Crée un post de notification automatique pour une recette.
 *
 * Créé en `draft` : le statut est une seconde barrière, indépendante des metas,
 * contre une apparition prématurée dans le feed app (qui ne sert que du
 * `publish`). La réconciliation le promeut en `publish` au moment exact où elle
 * écrit `_180c_onesignal_id` et `_180c_notif_sent_at`.
 *
 * @param int $recipe_id ID de la recette.
 * @return int|WP_Error ID du post créé.
 */
function _180c_notif_create_auto_post( int $recipe_id ) {
	$recipe = get_post( $recipe_id );

	$notif_id = wp_insert_post(
		array(
			'post_type'   => _180C_NOTIF_POST_TYPE,
			'post_status' => 'draft',
			'post_title'  => $recipe ? $recipe->post_title : '',
			'post_author' => $recipe ? (int) $recipe->post_author : 0,
		),
		true
	);

	if ( is_wp_error( $notif_id ) ) {
		return $notif_id;
	}

	update_post_meta( $notif_id, _180C_NOTIF_META_ORIGIN, 'auto' );

	return (int) $notif_id;
}

/**
 * Programme la notification de la recette qui vient d'être publiée.
 *
 * @param int    $recipe_id ID de la recette.
 * @param string $trigger   Origine du déclenchement, journalisée.
 * @return void
 */
function _180c_notif_schedule_for_recipe( int $recipe_id, string $trigger = '' ): void {
	if ( ! _180c_notif_automation_is_enabled() ) {
		_180c_notif_automation_log(
			'disabled',
			array(
				'recipe_id' => $recipe_id,
				'reason'    => __( 'Automation désactivée : aucune notification créée.', '180c' ),
			)
		);

		return;
	}

	$slot = _180c_notif_next_slot();

	// Une notification déjà programmée porte forcément une autre recette (le
	// cas « même recette » passe par la reprogrammation). Elle est annulée, pas
	// mise en file : une seule notification part par créneau.
	$current = _180c_notif_scheduled_post();

	if ( $current instanceof WP_Post ) {
		$previous_recipe = (int) get_post_meta( $current->ID, '_180c_notif_target_id', true );

		$cancelled = _180c_notif_cancel_scheduled(
			(int) $current->ID,
			sprintf(
				/* translators: %d: identifiant de la recette qui prend la place. */
				__( 'Remplacée par la recette #%d', '180c' ),
				$recipe_id
			)
		);

		/*
		 * L'annulation a échoué durement : l'envoi précédent est TOUJOURS
		 * programmé côté OneSignal. En programmer un second livrerait deux
		 * notifications au même créneau — exactement ce que la règle métier
		 * interdit. On renonce donc au remplacement, et on le dit.
		 */
		if ( ! $cancelled ) {
			_180c_notif_automation_log(
				'api_error',
				array(
					'notif_id'  => (int) $current->ID,
					'recipe_id' => $recipe_id,
					'reason'    => sprintf(
						/* translators: 1: id de la notification restée programmée, 2: id de la recette non programmée. */
						__( 'Annulation de la notification #%1$d impossible : la recette #%2$d n\'a PAS été programmée, pour ne pas livrer deux notifications au même créneau. Annuler l\'envoi depuis le panneau, puis réenregistrer la recette.', '180c' ),
						(int) $current->ID,
						$recipe_id
					),
				)
			);

			return;
		}

		_180c_notif_automation_log(
			'replaced',
			array(
				'notif_id'  => (int) $current->ID,
				'recipe_id' => $previous_recipe,
				'reason'    => sprintf(
					/* translators: %d: identifiant de la recette retenue. */
					__( 'Recette non retenue, remplacée par #%d', '180c' ),
					$recipe_id
				),
			)
		);

		_180c_notif_automation_log(
			'skipped',
			array(
				'recipe_id' => $previous_recipe,
				'notif_id'  => (int) $current->ID,
				'reason'    => __( 'Une recette plus récente a été publiée avant le créneau.', '180c' ),
			)
		);
	}

	$notif_id = _180c_notif_create_auto_post( $recipe_id );

	if ( is_wp_error( $notif_id ) ) {
		_180c_notif_automation_log(
			'api_error',
			array(
				'recipe_id' => $recipe_id,
				'reason'    => $notif_id->get_error_message(),
			)
		);

		return;
	}

	$hash   = _180c_notif_apply_auto_content( $notif_id, $recipe_id );
	$result = _180c_notif_schedule_send( $notif_id, $slot );

	if ( is_wp_error( $result ) ) {
		_180c_notif_automation_log(
			'api_error',
			array(
				'notif_id'  => $notif_id,
				'recipe_id' => $recipe_id,
				'reason'    => $result->get_error_message(),
			)
		);

		return;
	}

	update_post_meta( $notif_id, _180C_NOTIF_META_HASH, $hash );

	_180c_notif_automation_log(
		'scheduled',
		array(
			'notif_id'  => $notif_id,
			'recipe_id' => $recipe_id,
			'reason'    => sprintf(
				/* translators: 1: créneau en clair, 2: origine du déclenchement. */
				__( 'Créneau %1$s %2$s', '180c' ),
				_180c_notif_format_slot( $slot ),
				'' !== $trigger ? '(' . $trigger . ')' : ''
			),
		)
	);
}

/**
 * Reprogramme la notification en cours après modification de la recette cible.
 *
 * Liaison tardive : le payload est régénéré pour refléter l'état à jour du
 * contenu. L'empreinte évite les allers-retours API inutiles — une simple
 * ouverture-fermeture de l'éditeur ne doit pas consommer deux appels.
 *
 * @param int    $notif_id ID de la notification programmée.
 * @param int    $recipe_id ID de la recette cible.
 * @param string $reason   Motif journalisé.
 * @return void
 */
function _180c_notif_reschedule( int $notif_id, int $recipe_id, string $reason ): void {
	$stored_slot = (string) get_post_meta( $notif_id, _180C_NOTIF_META_SCHEDULED_FOR, true );
	$slot        = '' !== $stored_slot ? (int) strtotime( $stored_slot . ' UTC' ) : 0;
	$lead        = (int) _180c_notif_automation_setting( 'min_lead_minutes' ) * MINUTE_IN_SECONDS;

	// Le créneau enregistré est conservé, sauf s'il est devenu trop proche ou
	// déjà passé — auquel cas on repart sur le suivant.
	if ( $slot <= 0 || ( $slot - time() ) < $lead ) {
		$slot = _180c_notif_next_slot();
	}

	$settings = _180c_notif_automation_settings();
	$texts    = _180c_notif_render_auto_texts( $recipe_id );
	$hash     = md5( (string) wp_json_encode( array( $recipe_id, $texts, $settings['segment'] ) ) );

	$stored_hash = (string) get_post_meta( $notif_id, _180C_NOTIF_META_HASH, true );
	$stored_ts   = '' !== $stored_slot ? (int) strtotime( $stored_slot . ' UTC' ) : 0;

	// Rien n'a bougé (ni le texte, ni le segment, ni le créneau) : on s'abstient.
	// Sans ce garde-fou, une simple ouverture-fermeture de l'éditeur consommerait
	// une annulation et une recréation côté OneSignal.
	if ( $stored_hash === $hash && $stored_ts === $slot ) {
		return;
	}

	if ( ! _180c_notif_cancel_scheduled( $notif_id, $reason, false ) ) {
		return;
	}

	_180c_notif_apply_auto_content( $notif_id, $recipe_id );

	$result = _180c_notif_schedule_send( $notif_id, $slot );

	if ( is_wp_error( $result ) ) {
		_180c_notif_automation_log(
			'api_error',
			array(
				'notif_id'  => $notif_id,
				'recipe_id' => $recipe_id,
				'reason'    => $result->get_error_message(),
			)
		);

		return;
	}

	update_post_meta( $notif_id, _180C_NOTIF_META_HASH, $hash );

	_180c_notif_automation_log(
		'rescheduled',
		array(
			'notif_id'  => $notif_id,
			'recipe_id' => $recipe_id,
			'reason'    => $reason,
		)
	);
}

/**
 * Indique si le contexte de la requête autorise un déclenchement.
 *
 * `wp_is_json_request()` n'est volontairement PAS une exclusion : le CPT
 * `recipe` est en `show_in_rest`, donc édité dans l'éditeur de blocs, et une
 * publication depuis l'éditeur EST une requête REST. L'exclure reviendrait à
 * neutraliser le chemin éditorial principal — exactement l'inverse du but.
 *
 * @param WP_Post $post Post concerné.
 * @return bool
 */
function _180c_notif_automation_context_allows( WP_Post $post ): bool {
	/*
	 * INATTEIGNABLE depuis les deux hooks de ce module, et mesuré comme tel :
	 * une révision comme une autosauvegarde déclenchent `transition_post_status`
	 * avec `post_type = 'revision'`, jamais `'recipe'`. Le filtre de type de
	 * contenu de `_180c_notif_automation_transition()` a donc déjà rendu la
	 * main, et `save_post_recipe` ne se déclenche pas non plus. Ce n'est pas
	 * cette ligne qui a fait passer le scénario « révision et autosauvegarde »,
	 * c'est le test de `post_type`. On la conserve parce que cette fonction est
	 * un point d'entrée réutilisable, pas parce qu'elle protège aujourd'hui.
	 */
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return false;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	// Import en masse : `WP_IMPORTING` est posée par l'importateur WordPress et
	// par les scripts de migration (hors dépôt).
	if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
		return false;
	}

	/**
	 * Autorise ou bloque le déclenchement de l'automation pour ce post.
	 *
	 * Point de sortie pour les traitements en masse maison : poser
	 * `add_filter( '180c/notif_automation_trigger', '__return_false' )` autour
	 * d'un script de migration suffit à le neutraliser.
	 *
	 * @param bool    $allowed Autorisé par défaut.
	 * @param WP_Post $post    Post concerné.
	 */
	return (bool) apply_filters( '180c/notif_automation_trigger', true, $post ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Déclencheur principal : transitions de statut d'une recette.
 *
 * @param string  $new_status Nouveau statut.
 * @param string  $old_status Ancien statut.
 * @param WP_Post $post       Post concerné.
 * @return void
 */
function _180c_notif_automation_transition( $new_status, $old_status, $post ): void {
	if ( ! $post instanceof WP_Post || 'recipe' !== $post->post_type ) {
		return;
	}

	if ( ! _180c_notif_automation_context_allows( $post ) ) {
		return;
	}

	$recipe_id = (int) $post->ID;

	// Publication (y compris `future` -> `publish` d'une publication planifiée).
	if ( 'publish' === $new_status && 'publish' !== $old_status ) {
		_180c_notif_schedule_for_recipe( $recipe_id, $old_status . ' → publish' );

		return;
	}

	// Dépublication, mise en attente, corbeille : annulation sèche. Volontairement
	// NON conditionnée à l'interrupteur maître — désactiver l'automation ne doit
	// pas laisser partir un envoi déjà programmé sur une recette retirée.
	if ( 'publish' === $old_status && 'publish' !== $new_status ) {
		_180c_notif_cancel_for_recipe(
			$recipe_id,
			sprintf(
				/* translators: %s: nouveau statut du post. */
				__( 'Recette cible passée en « %s »', '180c' ),
				$new_status
			)
		);
	}
}
add_action( 'transition_post_status', '_180c_notif_automation_transition', 10, 3 );

/**
 * Annule la notification programmée si elle cible cette recette.
 *
 * @param int    $recipe_id ID de la recette.
 * @param string $reason    Motif journalisé.
 * @return void
 */
function _180c_notif_cancel_for_recipe( int $recipe_id, string $reason ): void {
	$current = _180c_notif_scheduled_post();

	if ( ! $current instanceof WP_Post ) {
		return;
	}

	if ( (int) get_post_meta( $current->ID, '_180c_notif_target_id', true ) !== $recipe_id ) {
		return;
	}

	_180c_notif_cancel_scheduled( (int) $current->ID, $reason );
}

/**
 * Reprogrammation à l'enregistrement de la recette cible.
 *
 * Ce hook n'est pas redondant avec `transition_post_status` : sous l'éditeur de
 * blocs, la publication (requête REST) et l'enregistrement des champs ACF
 * (requête metabox séparée vers post.php) sont DEUX requêtes distinctes. Au
 * moment de la transition, `recipe_intro` n'est pas encore écrit ; c'est cette
 * seconde passe qui régénère le payload avec le contenu réel.
 *
 * @param int     $post_id ID du post enregistré.
 * @param WP_Post $post    Post enregistré.
 * @return void
 */
function _180c_notif_automation_save_post( $post_id, $post ): void {
	if ( ! $post instanceof WP_Post || 'recipe' !== $post->post_type || 'publish' !== $post->post_status ) {
		return;
	}

	if ( ! _180c_notif_automation_context_allows( $post ) ) {
		return;
	}

	$current = _180c_notif_scheduled_post();

	if ( ! $current instanceof WP_Post ) {
		return;
	}

	if ( (int) get_post_meta( $current->ID, '_180c_notif_target_id', true ) !== (int) $post_id ) {
		return;
	}

	_180c_notif_reschedule(
		(int) $current->ID,
		(int) $post_id,
		__( 'Contenu de la recette modifié : payload régénéré', '180c' )
	);
}
add_action( 'save_post_recipe', '_180c_notif_automation_save_post', 20, 2 );

/**
 * Reprogrammation après écriture des champs ACF.
 *
 * ACF écrit ses champs sur `save_post` en priorité 10 ; notre propre `save_post`
 * en priorité 20 passe donc après, sauf sur les chemins où ACF s'exécute plus
 * tard. `acf/save_post` en priorité 20 ferme ce cas de figure. L'empreinte de
 * contenu rend l'appel inoffensif quand rien n'a changé.
 *
 * @param int|string $post_id ID du post ACF (peut être « options », « term_X »…).
 * @return void
 */
function _180c_notif_automation_acf_save( $post_id ): void {
	if ( ! is_numeric( $post_id ) ) {
		return;
	}

	$post = get_post( (int) $post_id );

	if ( $post instanceof WP_Post ) {
		_180c_notif_automation_save_post( (int) $post_id, $post );
	}
}
add_action( 'acf/save_post', '_180c_notif_automation_acf_save', 20 );

/**
 * Annulation à la suppression définitive de la recette cible.
 *
 * @param int $post_id ID du post supprimé.
 * @return void
 */
function _180c_notif_automation_before_delete( $post_id ): void {
	$post = get_post( (int) $post_id );

	if ( ! $post instanceof WP_Post || 'recipe' !== $post->post_type ) {
		return;
	}

	_180c_notif_cancel_for_recipe( (int) $post_id, __( 'Recette cible supprimée définitivement', '180c' ) );
}
add_action( 'before_delete_post', '_180c_notif_automation_before_delete' );
