<?php
/**
 * « Offrir un abonnement » — endpoint REST.
 *
 * POST /wp-json/180c/v1/gift/start
 *   Valide les données cadeau, (ré)injecte le produit cadeau dans le panier avec
 *   ces données, et renvoie l'URL de checkout. Accessible aux invités comme aux
 *   connectés (le paiement se fait au checkout standard). Protection CSRF par
 *   nonce `wp_rest` (en-tête X-WP-Nonce posé par le cœur REST).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', '_180c_gift_register_rest' );

/**
 * Enregistre la route REST du tunnel cadeau.
 *
 * @return void
 */
function _180c_gift_register_rest() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/gift/start',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_gift_rest_start',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Handler POST /gift/start.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_gift_rest_start( WP_REST_Request $request ) {
	if ( ! function_exists( 'WC' ) ) {
		return new WP_Error(
			'gift_unavailable',
			__( 'Service indisponible.', '180c' ),
			array( 'status' => 503 )
		);
	}

	$gift_id = _180c_gift_product_id();
	if ( $gift_id <= 0 ) {
		return new WP_Error(
			'gift_product_missing',
			__( "L'offre cadeau n'est pas encore disponible.", '180c' ),
			array( 'status' => 503 )
		);
	}

	// 1. Prénom du bénéficiaire (obligatoire).
	$first_name = sanitize_text_field( (string) $request->get_param( 'recipient_first_name' ) );
	if ( '' === $first_name ) {
		return new WP_Error(
			'gift_first_name_required',
			__( 'Merci d’indiquer le prénom du bénéficiaire.', '180c' ),
			array( 'status' => 422 )
		);
	}

	// 2. E-mail du bénéficiaire (valide).
	$email = sanitize_email( (string) $request->get_param( 'recipient_email' ) );
	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'gift_email_invalid',
			__( 'L’adresse e-mail du bénéficiaire est invalide.', '180c' ),
			array( 'status' => 422 )
		);
	}

	// 3. Le bénéficiaire ne peut pas être le donateur (si connecté).
	if ( is_user_logged_in() ) {
		$current = wp_get_current_user();
		if ( $current && strtolower( $current->user_email ) === strtolower( $email ) ) {
			return new WP_Error(
				'gift_email_is_donor',
				__( 'Vous ne pouvez pas vous offrir l’abonnement à vous-même. Indiquez l’adresse d’un proche.', '180c' ),
				array( 'status' => 422 )
			);
		}
	}

	// 4. Date d'envoi : « maintenant » OU date ≥ aujourd'hui (Europe/Paris).
	$send_now  = rest_sanitize_boolean( $request->get_param( 'send_now' ) );
	$send_date = '';

	if ( ! $send_now ) {
		$raw_date = sanitize_text_field( (string) $request->get_param( 'send_date' ) );
		$tz       = _180c_gift_timezone();
		$dt       = DateTime::createFromFormat( 'Y-m-d', $raw_date, $tz );

		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $raw_date ) {
			return new WP_Error(
				'gift_date_invalid',
				__( 'Merci de choisir une date d’envoi valide.', '180c' ),
				array( 'status' => 422 )
			);
		}

		$today = new DateTime( 'now', $tz );
		$today->setTime( 0, 0, 0 );
		$dt->setTime( 0, 0, 0 );

		if ( $dt < $today ) {
			return new WP_Error(
				'gift_date_past',
				__( 'La date d’envoi ne peut pas être dans le passé.', '180c' ),
				array( 'status' => 422 )
			);
		}

		$send_date = $raw_date;
	}

	// 5. Message personnel (optionnel, borné).
	$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
	if ( function_exists( 'mb_substr' ) ) {
		$message = mb_substr( $message, 0, _180C_GIFT_MESSAGE_MAXLEN );
	} else {
		$message = substr( $message, 0, _180C_GIFT_MESSAGE_MAXLEN );
	}

	$gift_data = array(
		'recipient_email'      => $email,
		'recipient_first_name' => $first_name,
		'message'              => $message,
		'send_date'            => $send_date,
		'send_now'             => $send_now,
	);

	// 6. Charge le panier hors contexte front (REST), puis purge l'éventuel
	// cadeau déjà présent avant d'ajouter le nouveau (un seul cadeau à la fois).
	if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}

	$cart = WC()->cart;
	if ( ! $cart ) {
		return new WP_Error(
			'gift_cart_unavailable',
			__( 'Le panier est indisponible, réessayez.', '180c' ),
			array( 'status' => 503 )
		);
	}

	foreach ( $cart->get_cart() as $cart_key => $cart_item ) {
		if ( ! empty( $cart_item['product_id'] ) && _180c_gift_is_gift_product( (int) $cart_item['product_id'] ) ) {
			$cart->remove_cart_item( $cart_key );
		}
	}

	$added = $cart->add_to_cart( $gift_id, 1, 0, array(), array( _180C_GIFT_META_KEY => $gift_data ) );

	if ( ! $added ) {
		return new WP_Error(
			'gift_add_failed',
			__( 'Impossible d’ajouter le cadeau au panier, réessayez.', '180c' ),
			array( 'status' => 500 )
		);
	}

	return rest_ensure_response(
		array(
			'checkout_url' => wc_get_checkout_url(),
		)
	);
}
