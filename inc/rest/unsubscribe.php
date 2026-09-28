<?php
/**
 * Endpoint REST — feedback de désabonnement (sondage de rétention).
 *
 * Route :
 *   POST /180c/v1/unsubscribe/feedback
 *
 * Collecte le motif (slug validé) + commentaire libre saisis à l'écran 3 du
 * stepper de résiliation. Flux web-only (cookie WP + nonce wp_rest) : on
 * n'utilise PAS le callback hybride JWT/cookie des favoris. La couche données
 * (validation du motif, insert) vit dans inc/unsub-feedback.php ; ce fichier ne
 * fait que valider la requête, authentifier et vérifier l'ownership.
 *
 * La résiliation effective (pending-cancel) est un endpoint distinct (Phase 5).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', '_180c_rest_register_unsubscribe' );

/**
 * Enregistre la route REST du feedback de désabonnement.
 *
 * @return void
 */
function _180c_rest_register_unsubscribe() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/unsubscribe/feedback',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_rest_unsub_feedback',
			'permission_callback' => '_180c_rest_unsub_permission',
			'args'                => array(

				/*
				 * `minimum` RETIRÉ, volontairement.
				 *
				 * Il n'était pas appliqué — déclarer un `sanitize_callback`
				 * prive l'argument du `rest_parse_request_arg` que le cœur
				 * assigne par défaut (class-wp-rest-request.php:858-861), et
				 * c'est lui qui applique le schéma.
				 *
				 * Le rendre opposable changerait la réponse : un
				 * `subscription_id` hors bornes rend depuis toujours 404
				 * `unsub_subscription_not_found`, via le contrôle d'existence du
				 * callback. Un `validate_callback` ne peut pas préserver ce
				 * code — le cœur aplatit toute erreur de validation en un
				 * unique `rest_invalid_param` 400
				 * (class-wp-rest-request.php:961-968).
				 *
				 * On ne déclare donc que ce qu'on applique.
				 */
			'subscription_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( 'ID de l\'abonnement WC Subscriptions concerné.', '180c' ),
			),
				'reason'      => array(
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => static function ( $value ) {
						return array_key_exists( $value, _180c_unsub_reasons() );
					},
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => __( 'Slug du motif de désabonnement (liste blanche).', '180c' ),
				),
				'comment'     => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'description'       => __( 'Commentaire libre optionnel.', '180c' ),
				),
			),
		)
	);

	register_rest_route(
		_180C_API_NAMESPACE,
		'/subscription/cancel',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_rest_subscription_cancel',
			'permission_callback' => '_180c_rest_unsub_permission',
			'args'                => array(

				/*
				 * `minimum` RETIRÉ, volontairement.
				 *
				 * Il n'était pas appliqué — déclarer un `sanitize_callback`
				 * prive l'argument du `rest_parse_request_arg` que le cœur
				 * assigne par défaut (class-wp-rest-request.php:858-861), et
				 * c'est lui qui applique le schéma.
				 *
				 * Le rendre opposable changerait la réponse : un
				 * `subscription_id` hors bornes rend depuis toujours 404
				 * `unsub_subscription_not_found`, via le contrôle d'existence du
				 * callback. Un `validate_callback` ne peut pas préserver ce
				 * code — le cœur aplatit toute erreur de validation en un
				 * unique `rest_invalid_param` 400
				 * (class-wp-rest-request.php:961-968).
				 *
				 * On ne déclare donc que ce qu'on applique.
				 */
			'subscription_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( 'ID de l\'abonnement WC Subscriptions à résilier.', '180c' ),
			),
			),
		)
	);
}

/**
 * Permission callback dédié (web-only) : utilisateur connecté avec capacité de
 * lecture. Le nonce wp_rest est validé par WP core via l'en-tête X-WP-Nonce en
 * authentification cookie ; inutile de le re-vérifier ici.
 *
 * @return bool
 */
function _180c_rest_unsub_permission() {
	return is_user_logged_in() && current_user_can( 'read' );
}

/**
 * POST /unsubscribe/feedback — enregistre le motif + commentaire.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_unsub_feedback( WP_REST_Request $request ) {
	// Table indisponible → fallback gracieux 503 (modèle favorites).
	if ( ! _180c_unsub_table_exists() ) {
		return new WP_Error(
			'unsub_unavailable',
			__( 'Service de désabonnement temporairement indisponible.', '180c' ),
			array( 'status' => 503 )
		);
	}

	if ( ! function_exists( 'wcs_get_subscription' ) ) {
		return new WP_Error(
			'unsub_unavailable',
			__( 'Service de désabonnement temporairement indisponible.', '180c' ),
			array( 'status' => 503 )
		);
	}

	// Ce contrôle n'est PAS un filet : c'est LA validation de
	// `subscription_id` sur cette route — voir le commentaire à
	// l'enregistrement.
	$subscription_id = absint( $request->get_param( 'subscription_id' ) );
	$reason          = sanitize_text_field( $request->get_param( 'reason' ) );
	$comment         = (string) $request->get_param( 'comment' );

	$subscription = wcs_get_subscription( $subscription_id );
	if ( ! $subscription instanceof \WC_Subscription ) {
		return new WP_Error(
			'unsub_subscription_not_found',
			__( 'Abonnement introuvable.', '180c' ),
			array( 'status' => 404 )
		);
	}

	// Ownership strict : l'abonnement doit appartenir à l'utilisateur courant.
	// Pas de contrôle de statut ici : le sondage peut précéder la résiliation.
	$current_user_id = get_current_user_id();
	if ( (int) $subscription->get_user_id() !== $current_user_id ) {
		return new WP_Error(
			'unsub_forbidden',
			__( 'Cet abonnement ne vous appartient pas.', '180c' ),
			array( 'status' => 403 )
		);
	}

	$ok = _180c_unsub_record( $current_user_id, $subscription_id, $reason, $comment );
	if ( ! $ok ) {
		return new WP_Error(
			'unsub_record_failed',
			__( 'Enregistrement du motif impossible.', '180c' ),
			array( 'status' => 500 )
		);
	}

	// Persiste le motif (slug, énumération fermée) pour l'event GA4
	// subscription_cancel émis ensuite sur woocommerce_subscription_status_cancelled
	// (cf inc/analytics/ga4.php — L5). Le sondage précède la résiliation.
	set_transient( '_180c_cancel_reason_' . $subscription_id, $reason, 30 * MINUTE_IN_SECONDS );

	$response = rest_ensure_response( array( 'success' => true ) );
	$response->set_status( 201 );

	return $response;
}

/**
 * POST /subscription/cancel — résiliation en fin de période (pending-cancel).
 *
 * Passe l'abonnement en `pending-cancel` : l'accès est maintenu jusqu'à la fin
 * de la période déjà payée, sans renouvellement ensuite. Si aucune période n'est
 * payée d'avance, WC Subscriptions bascule directement en `cancelled` (pas de
 * date de fin renvoyée). Idempotent : un abo déjà pending-cancel/cancelled
 * renvoie le récap sans re-changer de statut.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_subscription_cancel( WP_REST_Request $request ) {
	if ( ! function_exists( 'wcs_get_subscription' ) ) {
		return new WP_Error(
			'unsub_unavailable',
			__( 'Service de désabonnement temporairement indisponible.', '180c' ),
			array( 'status' => 503 )
		);
	}

	// Ce contrôle n'est PAS un filet : c'est LA validation de
	// `subscription_id` sur cette route — voir le commentaire à
	// l'enregistrement.
	$subscription_id = absint( $request->get_param( 'subscription_id' ) );

	$subscription = wcs_get_subscription( $subscription_id );
	if ( ! $subscription instanceof \WC_Subscription ) {
		return new WP_Error(
			'unsub_subscription_not_found',
			__( 'Abonnement introuvable.', '180c' ),
			array( 'status' => 404 )
		);
	}

	// Ownership strict.
	if ( (int) $subscription->get_user_id() !== get_current_user_id() ) {
		return new WP_Error(
			'unsub_forbidden',
			__( 'Cet abonnement ne vous appartient pas.', '180c' ),
			array( 'status' => 403 )
		);
	}

	// Idempotence : déjà résilié (ou en cours) → renvoyer le récap sans agir.
	if ( ! $subscription->has_status( array( 'pending-cancel', 'cancelled' ) ) ) {
		// Seuls les statuts résiliables sont acceptés.
		if ( ! $subscription->has_status( array( 'active', 'on-hold' ) ) ) {
			return new WP_Error(
				'not_cancellable',
				__( 'Cet abonnement ne peut pas être résilié dans son état actuel.', '180c' ),
				array( 'status' => 409 )
			);
		}

		// Date de fin d'accès capturée avant le changement de statut.
		$end_ts = $subscription->get_time( 'end' ) ?: $subscription->get_time( 'next_payment' );

		$subscription->update_status(
			'pending-cancel',
			__( 'Résiliation demandée par l\'abonné via le parcours de désabonnement.', '180c' )
		);

		// Récap : WCS a pu recalculer la date de fin lors du passage en pending-cancel.
		$end_ts = $subscription->get_time( 'end' ) ?: $end_ts;
	} else {
		$end_ts = $subscription->get_time( 'end' );
	}

	// end_date FR seulement si une période payée d'avance reste (sinon cancelled).
	$end_date = ( $end_ts && $end_ts > time() )
		? wp_date( 'j F Y', $end_ts )
		: null;

	/*
	 * Notifie le parcours self-service → email de confirmation (Phase 8).
	 * WC()->mailer() garantit l'instanciation des classes d'email avant que
	 * l'action ne se déclenche (même pattern que WCS::send_cancelled_email).
	 * L'anti-doublon est porté par la meta _180c_cancel_email_sent côté email.
	 */
	if ( function_exists( 'WC' ) ) {
		WC()->mailer();
		do_action( '_180c_subscription_cancelled_via_flow', $subscription->get_id() );
	}

	return rest_ensure_response(
		array(
			'success'  => true,
			'status'   => $subscription->get_status(),
			'end_date' => $end_date,
		)
	);
}
