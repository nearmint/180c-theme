<?php
/**
 * Endpoint REST — réactivation d'un abonnement en pause.
 *
 * Route :
 *   POST /180c/v1/subscription/reactivate
 *
 * Repasse un abonnement `on-hold` en `active` (self-service depuis Mon compte).
 * Nécessaire car l'action native WC Subscriptions `reactivate` n'est pas
 * toujours exposée par `wcs_get_all_user_actions_for_subscription()` (selon le
 * moyen de paiement / les conditions du plugin), ce qui laissait le module
 * « Gérer votre abonnement » vide pour un compte en pause.
 *
 * Flux web-only (cookie WP + nonce wp_rest), miroir sécurité de
 * `subscription/cancel` : utilisateur connecté, ownership strict, idempotent.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', '_180c_rest_register_subscription_reactivate' );

/**
 * Enregistre la route REST de réactivation d'abonnement.
 *
 * @return void
 */
function _180c_rest_register_subscription_reactivate() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/subscription/reactivate',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_rest_subscription_reactivate',
			'permission_callback' => '_180c_rest_subscription_reactivate_permission',
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
				 * `reactivate_subscription_not_found`, via le contrôle d'existence du
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
				'description'       => __( 'ID de l\'abonnement WC Subscriptions à réactiver.', '180c' ),
			),
			),
		)
	);
}

/**
 * Permission callback (web-only) : utilisateur connecté avec capacité de
 * lecture. Le nonce wp_rest est validé par WP core via l'en-tête X-WP-Nonce.
 *
 * @return bool
 */
function _180c_rest_subscription_reactivate_permission() {
	return is_user_logged_in() && current_user_can( 'read' );
}

/**
 * POST /subscription/reactivate — repasse l'abonnement en `active`.
 *
 * Idempotent : un abonnement déjà `active` renvoie un succès sans rien changer.
 * Réactivables : `on-hold` (reprise après pause) et `pending-cancel` (annulation
 * de la résiliation en cours) ; tout autre statut renvoie 409.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_subscription_reactivate( WP_REST_Request $request ) {
	if ( ! function_exists( 'wcs_get_subscription' ) ) {
		return new WP_Error(
			'reactivate_unavailable',
			__( 'Service d\'abonnement temporairement indisponible.', '180c' ),
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
			'reactivate_subscription_not_found',
			__( 'Abonnement introuvable.', '180c' ),
			array( 'status' => 404 )
		);
	}

	// Ownership strict.
	if ( (int) $subscription->get_user_id() !== get_current_user_id() ) {
		return new WP_Error(
			'reactivate_forbidden',
			__( 'Cet abonnement ne vous appartient pas.', '180c' ),
			array( 'status' => 403 )
		);
	}

	// Statut avant action : seule une vraie reprise de pause (on-hold → active)
	// déclenche l'e-mail de réactivation. Une annulation de résiliation
	// (pending-cancel → active) n'est pas une reprise de pause → pas d'e-mail.
	$was_on_hold = $subscription->has_status( 'on-hold' );

	// Idempotence : déjà actif → succès sans agir.
	if ( ! $subscription->has_status( 'active' ) ) {
		// Réactivable depuis une pause (on-hold) ou une résiliation en cours
		// (pending-cancel, « annuler l'annulation »).
		if ( ! $subscription->has_status( array( 'on-hold', 'pending-cancel' ) ) ) {
			return new WP_Error(
				'not_reactivatable',
				__( 'Cet abonnement ne peut pas être réactivé dans son état actuel.', '180c' ),
				array( 'status' => 409 )
			);
		}

		$subscription->update_status(
			'active',
			__( 'Réactivation demandée par l\'abonné depuis Mon compte.', '180c' )
		);
	}

	// Notifie le parcours self-service → e-mail de confirmation de réactivation.
	// WC()->mailer() garantit l'instanciation des classes d'email avant que
	// l'action ne se déclenche (même pattern que subscription/cancel). L'anti-
	// rebond est porté par la meta _180c_resume_email_last_sent côté email.
	if ( $was_on_hold && function_exists( 'WC' ) ) {
		WC()->mailer();
		do_action( '_180c_subscription_resumed_via_flow', $subscription->get_id() );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'status'  => $subscription->get_status(),
		)
	);
}
