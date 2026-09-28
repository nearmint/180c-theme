<?php
/**
 * Client REST OneSignal — envoi d'une notification push depuis WordPress.
 *
 * Contrat API vérifié sur https://documentation.onesignal.com/reference/push-notification :
 *   POST https://api.onesignal.com/notifications?c=push
 *   Headers : Authorization: Key {REST_API_KEY}, Content-Type / Accept: application/json
 *   Payload : app_id, target_channel, headings, contents, data,
 *             included_segments, ios_attachments, idempotency_key
 *
 * Le deep link (type/id/url) voyage dans « data » : c'est le seul champ délivré
 * au SDK mobile en « additionalData ». « custom_data » ne sert qu'à la
 * personnalisation de templates (avec template_id) et n'est PAS remonté à l'app.
 *
 * Écarts assumés vs le brief (la doc fait foi) :
 *   - l'endpoint documenté porte le suffixe « ?c=push » ;
 *   - « target_channel » n'est requis qu'avec include_aliases ; on l'envoie
 *     malgré tout par explicitation (valeur « push »), ce qui est valide.
 *
 * Idempotence : un idempotency_key (UUID v4) est généré au premier essai et
 * stocké en meta. Le marqueur définitif d'envoi est _180c_onesignal_id : tant
 * qu'il est vide, rien n'a été envoyé.
 *
 * Envoi différé et annulation (automation « push à la publication ») :
 *   - `_180c_onesignal_send( $post_id, $send_after_ts )` alimente le champ
 *     `send_after`. Contrat vérifié sur le schéma de
 *     https://documentation.onesignal.com/reference/push-notification :
 *     « Schedule delivery for a future date/time (in UTC). The format must be
 *     valid per the ISO 8601 standard and compatible with JavaScript's Date()
 *     parser. Example: 2025-09-24T14:00:00-07:00 ». La mention « in UTC » et
 *     l'exemple à décalage explicite se contredisent en apparence : on lève
 *     l'ambiguïté en émettant un instant UTC suffixé « Z ».
 *   - `_180c_onesignal_cancel( $notification_id )` appelle
 *     DELETE https://api.onesignal.com/notifications/{id}?app_id={app_id}
 *     (https://documentation.onesignal.com/reference/cancel-message).
 *
 * Un envoi DIFFÉRÉ n'écrit ni _180c_onesignal_id ni _180c_notif_sent_at : ces
 * deux metas restent le marqueur d'un envoi effectivement DÉLIVRÉ, et ce sont
 * elles que le feed app interroge (inc/notifications/rest.php). Une
 * notification programmée est donc invisible du centre de notifications tant
 * que la réconciliation ne l'a pas promue.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL de l'endpoint OneSignal de création de notification.
 */
define( '_180C_ONESIGNAL_ENDPOINT', 'https://api.onesignal.com/notifications?c=push' );

/**
 * Envoie la notification vers OneSignal (ou la simule en dry-run).
 *
 * @param int      $post_id        ID de la notification (CPT 180c_notification).
 * @param int|null $send_after_ts  Horodatage Unix de livraison différée. Null
 *                                 (défaut) = envoi immédiat, comportement
 *                                 historique strictement inchangé.
 * @return array|WP_Error Réponse OneSignal décodée, ou WP_Error en cas d'échec.
 */
function _180c_onesignal_send( $post_id, $send_after_ts = null ) {
	$post = get_post( $post_id );

	if ( ! $post || _180C_NOTIF_POST_TYPE !== $post->post_type ) {
		return new WP_Error( 'notif_not_found', __( 'Notification introuvable.', '180c' ) );
	}

	// Idempotence forte : déjà envoyée → on ne renvoie jamais.
	$existing = (string) get_post_meta( $post_id, '_180c_onesignal_id', true );
	if ( '' !== $existing ) {
		return new WP_Error(
			'notif_already_sent',
			__( 'Notification déjà envoyée.', '180c' ),
			array( 'onesignal_id' => $existing )
		);
	}

	if ( ! _180c_onesignal_is_configured() && ! _180c_onesignal_is_dry_run() ) {
		_180c_onesignal_store_error( $post_id, 'OneSignal non configuré (constantes wp-config manquantes).' );
		return new WP_Error( 'notif_not_configured', __( 'OneSignal non configuré.', '180c' ) );
	}

	$scheduled = null !== $send_after_ts;
	$payload   = _180c_onesignal_build_payload( $post, $send_after_ts );

	if ( is_wp_error( $payload ) ) {
		_180c_onesignal_store_error( $post_id, $payload->get_error_message() );
		return $payload;
	}

	// Mode simulation : aucun appel réseau, log du payload, ID factice.
	if ( _180c_onesignal_is_dry_run() ) {
		_180c_log(
			'OneSignal dry-run',
			array(
				'post_id'   => $post_id,
				'scheduled' => $scheduled,
				'payload'   => $payload,
			)
		);

		$fake_id = 'dryrun_' . $payload['idempotency_key'];

		if ( $scheduled ) {
			return array(
				'id'        => $fake_id,
				'dry_run'   => true,
				'scheduled' => true,
			);
		}

		_180c_onesignal_mark_sent( $post_id, $fake_id );

		return array(
			'id'      => $fake_id,
			'dry_run' => true,
		);
	}

	$args = array(
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Key ' . _180c_onesignal_rest_key(),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		),
		'body'    => wp_json_encode( $payload ),
	);

	// Un seul retry, uniquement sur erreur réseau (jamais sur un HTTP 4xx/5xx).
	// L'idempotency_key étant stable, un retour tardif du premier appel ne crée
	// pas de doublon côté OneSignal.
	$response = wp_remote_post( _180C_ONESIGNAL_ENDPOINT, $args );
	if ( is_wp_error( $response ) ) {
		$response = wp_remote_post( _180C_ONESIGNAL_ENDPOINT, $args );
	}

	if ( is_wp_error( $response ) ) {
		_180c_onesignal_store_error( $post_id, 'Erreur réseau : ' . $response->get_error_message() );
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		$detail = '';
		if ( is_array( $body ) && ! empty( $body['errors'] ) ) {
			$detail = is_array( $body['errors'] ) ? implode( ' ; ', array_map( 'strval', (array) $body['errors'] ) ) : (string) $body['errors'];
		}
		_180c_onesignal_store_error( $post_id, sprintf( 'HTTP %d %s', $code, $detail ) );
		return new WP_Error(
			'notif_http_error',
			/* translators: %d: code HTTP renvoyé par OneSignal. */
			sprintf( __( 'OneSignal a répondu HTTP %d.', '180c' ), $code ),
			array( 'status' => $code )
		);
	}

	$onesignal_id = ( is_array( $body ) && ! empty( $body['id'] ) ) ? (string) $body['id'] : '';

	if ( '' === $onesignal_id ) {
		$detail = ( is_array( $body ) && ! empty( $body['errors'] ) )
			? ( is_array( $body['errors'] ) ? implode( ' ; ', array_map( 'strval', (array) $body['errors'] ) ) : (string) $body['errors'] )
			: 'réponse sans identifiant';
		_180c_onesignal_store_error( $post_id, 'OneSignal : ' . $detail );
		return new WP_Error( 'notif_no_id', __( 'OneSignal n\'a pas retourné d\'identifiant.', '180c' ) );
	}

	// Envoi différé : rien n'est encore délivré. On ne marque donc PAS la
	// notification comme envoyée — c'est la réconciliation qui le fera une fois
	// le créneau franchi. Le stockage de l'identifiant programmé revient à
	// l'appelant (inc/notifications/automation.php).
	if ( $scheduled ) {
		_180c_log(
			'Notification programmée',
			array(
				'post_id'      => $post_id,
				'onesignal_id' => $onesignal_id,
				'send_after'   => $payload['send_after'] ?? '',
			)
		);

		return is_array( $body ) ? $body : array( 'id' => $onesignal_id );
	}

	_180c_onesignal_mark_sent( $post_id, $onesignal_id );

	return is_array( $body ) ? $body : array( 'id' => $onesignal_id );
}

/**
 * Construit le payload OneSignal à partir de la notification.
 *
 * @param WP_Post  $post           Notification.
 * @param int|null $send_after_ts  Horodatage Unix de livraison différée, ou null.
 * @return array|WP_Error
 */
function _180c_onesignal_build_payload( $post, $send_after_ts = null ) {
	$post_id = (int) $post->ID;
	$title   = (string) get_the_title( $post );
	$body    = (string) get_post_meta( $post_id, '_180c_notif_body', true );

	if ( '' === trim( $title ) && '' === trim( $body ) ) {
		return new WP_Error( 'notif_empty', __( 'Titre et corps vides : rien à envoyer.', '180c' ) );
	}

	// Contenu : le corps prime, sinon le titre sert de contenu.
	$contents = '' !== trim( $body ) ? $body : $title;

	$segment_key = (string) get_post_meta( $post_id, '_180c_notif_segment', true );
	$segment_key = '' !== $segment_key ? $segment_key : _180c_onesignal_default_segment();

	// Résolution du nom de segment par identifiant, au moment de l'envoi. Un
	// segment supprimé ou en pause renvoie une WP_Error : l'envoi est refusé
	// plutôt que d'être adressé à l'aveugle.
	$segment_name = _180c_onesignal_resolve_segment_name( $segment_key );
	if ( is_wp_error( $segment_name ) ) {
		return $segment_name;
	}

	// Idempotency key stable : générée une seule fois puis réutilisée.
	$idempotency = (string) get_post_meta( $post_id, '_180c_notif_idempotency_key', true );
	if ( '' === $idempotency ) {
		$idempotency = wp_generate_uuid4();
		update_post_meta( $post_id, '_180c_notif_idempotency_key', $idempotency );
	}

	$target = _180c_onesignal_build_target( $post_id );

	$payload = array(
		'app_id'            => _180c_onesignal_app_id(),
		'target_channel'    => 'push',
		'included_segments' => array( $segment_name ),
		'headings'          => array( 'en' => $title ),
		'contents'          => array( 'en' => $contents ),
		// `data` (et NON `custom_data`) : c'est ce champ qui est délivré au SDK
		// mobile en `additionalData` (deep link lu au tap). `custom_data` sert
		// uniquement à la personnalisation de templates (avec `template_id`),
		// non utilisée ici → l'y mettre ne remonterait jamais dans l'app.
		'data'              => $target,
		'idempotency_key'   => $idempotency,
	);

	// Image (rich push) : chaîne de repli meta -> cible -> aucune, en taille
	// proportionnelle `large` (aucun recadrage). Si la cible n'a pas d'image,
	// ios_attachments et big_picture sont simplement omis — jamais d'erreur.
	$image_url = _180c_notif_resolve_image_url( $post_id );
	if ( '' !== $image_url ) {
		$payload['ios_attachments'] = array( '180c_image' => $image_url ); // iOS rich push.
		$payload['big_picture']     = $image_url;                          // Android rich push (même payload).
	}

	// Livraison différée. Format : instant UTC suffixé « Z », valide ISO 8601 et
	// parsable par `Date()` — les deux exigences du schéma OneSignal.
	if ( null !== $send_after_ts ) {
		$payload['send_after'] = _180c_onesignal_format_send_after( (int) $send_after_ts );
	}

	/**
	 * Filtre le payload OneSignal juste avant l'envoi.
	 *
	 * @param array $payload Payload complet.
	 * @param int   $post_id ID de la notification.
	 */
	return (array) apply_filters( '_180c_onesignal_payload', $payload, $post_id );
}

/**
 * Formate un horodatage pour le champ `send_after`.
 *
 * Le schéma OneSignal exige « valid per the ISO 8601 standard and compatible
 * with JavaScript's Date() parser » et documente le champ comme étant « in
 * UTC », tout en donnant un exemple à décalage explicite (`-07:00`). Un instant
 * UTC suffixé « Z » satisfait les trois lectures sans ambiguïté possible.
 *
 * @param int $timestamp Horodatage Unix.
 * @return string Date ISO 8601 UTC (ex. « 2026-09-12T08:30:00Z »).
 */
function _180c_onesignal_format_send_after( $timestamp ) {
	return gmdate( 'Y-m-d\TH:i:s\Z', (int) $timestamp );
}

/**
 * Annule une notification programmée côté OneSignal.
 *
 * Contrat vérifié sur https://documentation.onesignal.com/reference/cancel-message :
 *   DELETE https://api.onesignal.com/notifications/{message_id}?app_id={app_id}
 *   Header  : Authorization: Key {REST_API_KEY}
 *   Succès  : HTTP 200, corps { "success": true }
 *   Échec   : HTTP 400, corps { "errors": [ "Reason for the message not being
 *             canceled. Usually due to the message already being sent to all
 *             recipients." ] }
 *
 * L'API ne distingue pas « déjà délivrée » d'un autre refus : les deux passent
 * par un 400 au message générique. Ces cas sortent donc avec le code d'erreur
 * dédié `notif_cancel_soft`, que `_180c_onesignal_cancel_is_soft_failure()`
 * reconnaît : l'appelant journalise et poursuit au lieu de bloquer.
 *
 * @param string $notification_id Identifiant OneSignal de la notification.
 * @return true|WP_Error Vrai si annulée (ou simulée), WP_Error sinon.
 */
function _180c_onesignal_cancel( string $notification_id ) {
	$notification_id = trim( $notification_id );

	if ( '' === $notification_id ) {
		return new WP_Error( 'notif_cancel_no_id', __( 'Aucun identifiant OneSignal à annuler.', '180c' ) );
	}

	// Mode simulation : aucun appel réseau. Un identifiant factice « dryrun_ »
	// est également intercepté hors dry-run, sans quoi une base ayant servi aux
	// essais enverrait un DELETE sur un identifiant qui n'a jamais existé.
	if ( _180c_onesignal_is_dry_run() || 0 === strpos( $notification_id, 'dryrun_' ) ) {
		_180c_log(
			'OneSignal dry-run : annulation simulée',
			array( 'notification_id' => $notification_id )
		);

		return true;
	}

	if ( ! _180c_onesignal_is_configured() ) {
		return new WP_Error( 'notif_not_configured', __( 'OneSignal non configuré.', '180c' ) );
	}

	$url = sprintf(
		'https://api.onesignal.com/notifications/%s?app_id=%s',
		rawurlencode( $notification_id ),
		rawurlencode( _180c_onesignal_app_id() )
	);

	$args = array(
		'method'  => 'DELETE',
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Key ' . _180c_onesignal_rest_key(),
			'Accept'        => 'application/json',
		),
	);

	// Un seul retry, uniquement sur erreur réseau. Une annulation est
	// naturellement idempotente : rejouer un DELETE ne peut rien détruire de
	// plus.
	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		$response = wp_remote_request( $url, $args );
	}

	if ( is_wp_error( $response ) ) {
		_180c_log(
			'Échec annulation OneSignal (réseau)',
			array(
				'notification_id' => $notification_id,
				'error'           => $response->get_error_message(),
			),
			'error'
		);

		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code >= 200 && $code < 300 ) {
		return true;
	}

	$detail = '';
	if ( is_array( $body ) && ! empty( $body['errors'] ) ) {
		$detail = is_array( $body['errors'] )
			? implode( ' ; ', array_map( 'strval', (array) $body['errors'] ) )
			: (string) $body['errors'];
	}

	// 400 (refus documenté, typiquement « déjà envoyée ») et 404 (identifiant
	// inconnu) décrivent tous deux une notification qu'il n'y a plus lieu
	// d'annuler : échec « doux ».
	$code_slug = in_array( $code, array( 400, 404 ), true ) ? 'notif_cancel_soft' : 'notif_cancel_failed';

	_180c_log(
		'Annulation OneSignal refusée',
		array(
			'notification_id' => $notification_id,
			'status'          => $code,
			'detail'          => $detail,
			'soft'            => 'notif_cancel_soft' === $code_slug,
		),
		'notif_cancel_soft' === $code_slug ? 'warning' : 'error'
	);

	return new WP_Error(
		$code_slug,
		/* translators: 1: code HTTP, 2: détail renvoyé par OneSignal. */
		sprintf( __( 'Annulation refusée par OneSignal (HTTP %1$d) : %2$s', '180c' ), $code, $detail ),
		array( 'status' => $code )
	);
}

/**
 * Indique si un échec d'annulation est « doux », c'est-à-dire sans conséquence.
 *
 * Une notification déjà délivrée ou introuvable ne peut plus être annulée :
 * l'état WordPress doit continuer sa route plutôt que de rester bloqué sur un
 * envoi qui n'existe plus côté OneSignal.
 *
 * @param mixed $result Résultat de `_180c_onesignal_cancel()`.
 * @return bool
 */
function _180c_onesignal_cancel_is_soft_failure( $result ) {
	return is_wp_error( $result )
		&& in_array( $result->get_error_code(), array( 'notif_cancel_soft', 'notif_cancel_no_id' ), true );
}

/**
 * Construit le bloc de données de ciblage (champ « data ») transmis à l'app.
 *
 * Types résolus par identifiant + permalien : `recipe`, `product` (LOT D) et
 * `article` (hérité). `product` ouvre une webview authentifiée côté app : cette
 * sémantique est portée par l'app, aucun champ dédié n'est ajouté au feed. Le
 * permalien est celui du site (https://www.180c.fr en production).
 *
 * @param int $post_id ID de la notification.
 * @return array{type:string,id:int,url:string}
 */
function _180c_onesignal_build_target( $post_id ) {
	$type = (string) get_post_meta( $post_id, '_180c_notif_target_type', true );
	if ( '' === $type ) {
		$type = 'none';
	}

	$id  = 0;
	$url = '';

	if ( in_array( $type, array( 'recipe', 'product', 'article' ), true ) ) {
		$id        = (int) get_post_meta( $post_id, '_180c_notif_target_id', true );
		$permalink = $id ? get_permalink( $id ) : '';
		$url       = $permalink ? (string) $permalink : '';
	} elseif ( 'url' === $type ) {
		$url = (string) get_post_meta( $post_id, '_180c_notif_target_url', true );
	}

	return array(
		'type' => $type,
		'id'   => $id,
		'url'  => $url,
	);
}

/**
 * Retourne l'ID de l'image dérivée d'une cible (sans recadrage).
 *
 * @param string $type      Type de cible (`recipe` | `product`).
 * @param int    $target_id ID du contenu cible.
 * @return int ID d'attachment, ou 0 si aucune image.
 */
function _180c_notif_derive_image_id( $type, $target_id ) {
	$target_id = (int) $target_id;
	if ( ! $target_id ) {
		return 0;
	}

	if ( 'product' === $type ) {
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $target_id );
			if ( $product ) {
				$image_id = (int) $product->get_image_id();
				if ( $image_id ) {
					return $image_id;
				}
			}
		}
		return (int) get_post_thumbnail_id( $target_id );
	}

	// recipe (et repli générique) : image mise en avant.
	return (int) get_post_thumbnail_id( $target_id );
}

/**
 * Résout l'URL d'une image d'attachment en taille proportionnelle.
 *
 * Règle client absolue : aucun recadrage. On sert la taille `large` (1024 px,
 * enregistrée avec crop=false), avec repli sur l'original si `large` n'existe
 * pas. Jamais de taille recadrée, jamais de génération.
 *
 * @param int $attachment_id ID d'attachment.
 * @return string URL, ou chaîne vide.
 */
function _180c_notif_attachment_image_url( $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	if ( ! $attachment_id ) {
		return '';
	}

	$src = wp_get_attachment_image_url( $attachment_id, 'large' );
	if ( ! $src ) {
		$src = wp_get_attachment_image_url( $attachment_id, 'full' );
	}

	return $src ? (string) $src : '';
}

/**
 * Retourne l'URL de l'image dérivée d'une cible, en taille proportionnelle.
 *
 * @param string $type      Type de cible (`recipe` | `product`).
 * @param int    $target_id ID du contenu cible.
 * @return string URL, ou chaîne vide si aucune image.
 */
function _180c_notif_derive_image_url( $type, $target_id ) {
	return _180c_notif_attachment_image_url( _180c_notif_derive_image_id( $type, $target_id ) );
}

/**
 * Résout l'image d'une notification selon la chaîne de repli (LOT D, arbitrage 2) :
 *   1. meta _180c_notif_image_id, si renseignée (anciennes notifications) ;
 *   2. sinon, image dérivée de la cible (recette / produit) ;
 *   3. sinon, chaîne vide.
 *
 * Cette même chaîne alimente le feed REST et le payload OneSignal, afin qu'une
 * notification ancienne portant une image explicite garde son rendu, et qu'une
 * notification récente déduise l'image de sa cible.
 *
 * @param int $post_id ID de la notification.
 * @return string URL en taille proportionnelle, ou chaîne vide.
 */
function _180c_notif_resolve_image_url( $post_id ) {
	$post_id = (int) $post_id;

	$image_id = (int) get_post_meta( $post_id, '_180c_notif_image_id', true );
	if ( $image_id ) {
		$url = _180c_notif_attachment_image_url( $image_id );
		if ( '' !== $url ) {
			return $url;
		}
	}

	$type      = (string) get_post_meta( $post_id, '_180c_notif_target_type', true );
	$target_id = (int) get_post_meta( $post_id, '_180c_notif_target_id', true );

	return _180c_notif_derive_image_url( $type, $target_id );
}

/**
 * Marque la notification comme envoyée et purge le cache du feed.
 *
 * @param int    $post_id      ID de la notification.
 * @param string $onesignal_id Identifiant OneSignal (ou factice en dry-run).
 * @return void
 */
function _180c_onesignal_mark_sent( $post_id, $onesignal_id ) {
	update_post_meta( $post_id, '_180c_onesignal_id', sanitize_text_field( $onesignal_id ) );
	update_post_meta( $post_id, '_180c_notif_sent_at', gmdate( 'Y-m-d H:i:s' ) );
	delete_post_meta( $post_id, '_180c_notif_last_error' );

	if ( function_exists( '_180c_notif_purge_feed_cache' ) ) {
		_180c_notif_purge_feed_cache();
	}

	_180c_log(
		'Notification envoyée',
		array(
			'post_id'      => $post_id,
			'onesignal_id' => $onesignal_id,
		)
	);
}

/**
 * Enregistre le dernier message d'erreur d'envoi (sans jamais la clé REST).
 *
 * @param int    $post_id ID de la notification.
 * @param string $message Message d'erreur.
 * @return void
 */
function _180c_onesignal_store_error( $post_id, $message ) {
	update_post_meta( $post_id, '_180c_notif_last_error', sanitize_text_field( $message ) );

	_180c_log(
		'Échec envoi notification',
		array(
			'post_id' => $post_id,
			'error'   => $message,
		),
		'error'
	);
}
