<?php
/**
 * « Offrir un abonnement » — outils de dépannage côté admin.
 *
 * Ajoute deux actions au menu « Actions de commande » (écran d'édition d'une
 * commande contenant un cadeau) :
 *  - « Cadeau : (re)livrer » — ouvre/prolonge l'accès du bénéficiaire si la
 *    livraison ne s'est jamais faite, puis envoie l'e-mail ;
 *  - « Cadeau : renvoyer l'e-mail au bénéficiaire » — renvoie le message SANS
 *    toucher à la durée d'accès déjà accordée (avec un lien de définition de
 *    mot de passe frais si le compte a été créé par le cadeau).
 *
 * Les deux actions sont idempotentes et n'accordent jamais deux fois les
 * 12 mois : la ligne cadeau porte l'ID du membership créé, et une ligne déjà
 * livrée passe par le simple renvoi d'e-mail.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lignes cadeau rattachées à une commande.
 *
 * @param int $order_id ID de commande.
 * @return array Tableau d'objets ligne.
 */
function _180c_gift_rows_for_order( $order_id ) {
	global $wpdb;
	$table = _180c_gift_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC", (int) $order_id ) );

	return is_array( $rows ) ? $rows : array();
}

/**
 * Ajoute les actions cadeau au sélecteur d'actions de commande.
 *
 * @param array         $actions Actions existantes.
 * @param WC_Order|null $order   Commande éditée (fourni par WooCommerce ; repli
 *                               sur $theorder pour les appels historiques).
 * @return array
 */
function _180c_gift_order_actions( $actions, $order = null ) {
	if ( ! $order instanceof WC_Order ) {
		global $theorder;
		$order = $theorder;
	}

	if ( ! $order instanceof WC_Order ) {
		return $actions;
	}

	if ( ! _180c_gift_rows_for_order( $order->get_id() ) ) {
		return $actions;
	}

	$actions['_180c_gift_redeliver']        = __( 'Cadeau : (re)livrer', '180c' );
	$actions['_180c_gift_resend_recipient'] = __( 'Cadeau : renvoyer l’e-mail au bénéficiaire', '180c' );

	return $actions;
}
add_filter( 'woocommerce_order_actions', '_180c_gift_order_actions', 10, 2 );

/**
 * Action « (re)livrer » : ouvre l'accès si nécessaire, sinon renvoie l'e-mail.
 *
 * @param WC_Order $order Commande.
 * @return void
 */
function _180c_gift_action_redeliver( $order ) {
	foreach ( _180c_gift_rows_for_order( $order->get_id() ) as $row ) {
		$gift_id = (int) $row->id;

		if ( empty( $row->membership_id ) ) {
			// `_180c_gift_deliver()` ne traite qu'une ligne `scheduled` : on
			// remet ce statut pour rejouer une livraison avortée (failed) ou
			// jamais planifiée, sans risque de double octroi puisque aucun
			// membership n'est rattaché à la ligne.
			if ( 'scheduled' !== $row->status ) {
				_180c_gift_set_status( $gift_id, 'scheduled' );
			}

			_180c_gift_deliver( $gift_id );

			$after = _180c_gift_get_row( $gift_id );
			_180c_gift_order_note(
				$order,
				sprintf(
					/* translators: 1: e-mail du bénéficiaire, 2: statut résultant. */
					__( 'Cadeau relivré manuellement pour %1$s — statut : %2$s.', '180c' ),
					$row->recipient_email,
					$after ? $after->status : 'inconnu'
				)
			);
			continue;
		}

		// Accès déjà accordé : on ne rejoue que l'e-mail.
		_180c_gift_resend_recipient_email( $gift_id, $order );
	}
}
add_action( 'woocommerce_order_action__180c_gift_redeliver', '_180c_gift_action_redeliver' );

/**
 * Action « renvoyer l'e-mail au bénéficiaire ».
 *
 * @param WC_Order $order Commande.
 * @return void
 */
function _180c_gift_action_resend_recipient( $order ) {
	foreach ( _180c_gift_rows_for_order( $order->get_id() ) as $row ) {
		_180c_gift_resend_recipient_email( (int) $row->id, $order );
	}
}
add_action( 'woocommerce_order_action__180c_gift_resend_recipient', '_180c_gift_action_resend_recipient' );

/**
 * Renvoie l'e-mail cadeau au bénéficiaire, sans toucher à la durée d'accès.
 *
 * Reconstitue le contexte à partir du membership existant. Un lien de
 * définition de mot de passe n'est joint que si le compte a été créé pour ce
 * cadeau (compte créé après l'enregistrement de la ligne) : pour un compte
 * préexistant, le lien de connexion suffit.
 *
 * @param int           $gift_id ID de la ligne cadeau.
 * @param WC_Order|null $order   Commande, pour la note de suivi.
 * @return bool Vrai si l'e-mail est parti.
 */
function _180c_gift_resend_recipient_email( $gift_id, $order = null ) {
	$row = _180c_gift_get_row( (int) $gift_id );

	if ( ! $row || empty( $row->recipient_email ) ) {
		return false;
	}

	$user = get_user_by( 'email', $row->recipient_email );

	if ( ! $user ) {
		_180c_gift_order_note(
			$order,
			sprintf(
				/* translators: %s: adresse e-mail du bénéficiaire. */
				__( '⚠ Renvoi impossible : aucun compte pour %s. Utiliser « Cadeau : (re)livrer ».', '180c' ),
				$row->recipient_email
			)
		);
		return false;
	}

	$context = array( 'user_id' => (int) $user->ID );

	// Compte créé pour ce cadeau → il n'a jamais eu de mot de passe choisi.
	$created_gift_account = ! empty( $row->created_at )
		&& strtotime( $user->user_registered ) >= ( strtotime( $row->created_at ) - MINUTE_IN_SECONDS );

	if ( $created_gift_account ) {
		$key = get_password_reset_key( $user );

		if ( ! is_wp_error( $key ) ) {
			$context['set_password_url'] = network_site_url(
				'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ),
				'login'
			);
		}
	}

	// Période d'accès : lue sur le membership rattaché, sinon sur le plan.
	if ( function_exists( 'wc_memberships_get_user_membership' ) && ! empty( $row->membership_id ) ) {
		$membership = wc_memberships_get_user_membership( (int) $row->membership_id );

		if ( $membership ) {
			$context['start_ts'] = (int) $membership->get_start_date( 'timestamp' );
			$context['end_ts']   = (int) $membership->get_end_date( 'timestamp' );
		}
	}

	$sent = _180c_gift_send_recipient_email( (int) $gift_id, $context );

	_180c_gift_order_note(
		$order,
		$sent
			? sprintf(
				/* translators: %s: adresse e-mail du bénéficiaire. */
				__( 'E-mail cadeau renvoyé à %s.', '180c' ),
				$row->recipient_email
			)
			: sprintf(
				/* translators: %s: adresse e-mail du bénéficiaire. */
				__( '⚠ Échec du renvoi de l’e-mail cadeau à %s.', '180c' ),
				$row->recipient_email
			)
	);

	return $sent;
}
