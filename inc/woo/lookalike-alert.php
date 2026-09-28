<?php
/**
 * Alerte admin : première commande payante d'un compte qui ressemble à un
 * compte existant.
 *
 * Origine : doublons de comptes abonnés (support, septembre 2026). Deux
 * clientes ont payé avec une adresse mal saisie (`wandoo.fr`, `virupier`) :
 * WooCommerce a créé un second compte au checkout, à côté de leur compte
 * historique, et leur newsletter premium est partie vers une adresse morte.
 * Personne ne l'a vu avant leur réclamation.
 *
 * Déclencheur : passage en `processing` ou `completed` de la PREMIÈRE commande
 * payante d'un compte créé au checkout (inscrit au plus 2 h avant la commande).
 * Condition : un autre compte porte le même prénom et le même nom (normalisés :
 * minuscules, sans accents), ou une adresse dont la partie locale est à une
 * distance de Levenshtein ≤ 2.
 * Action : note PRIVÉE sur la commande et e-mail à l'adresse d'administration.
 * Rien n'est montré au client ; rien n'est corrigé automatiquement.
 *
 * Pertinence mesurée avant livraison (2026-09-28) : rejouée sur les 548
 * commandes payantes de prod des 90 derniers jours, la règle alerte sur 7
 * commandes (1,3 %), dont le cas réel qui l'a motivée.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta de commande : la vérification a déjà eu lieu (une seule alerte).
 */
const _180C_LOOKALIKE_CHECKED_META = '_180c_lookalike_checked';

/**
 * Écart maximal entre l'inscription et la commande pour « compte créé au checkout ».
 */
const _180C_LOOKALIKE_SIGNUP_WINDOW = 2 * HOUR_IN_SECONDS;

/**
 * Distance maximale entre deux parties locales d'adresse.
 */
const _180C_LOOKALIKE_MAX_LOCAL_DISTANCE = 2;

/**
 * Longueur minimale d'une partie locale pour le critère « adresse ».
 *
 * En dessous, deux caractères d'écart ne disent plus rien : rejouée sur les
 * 548 commandes payantes de prod des 90 derniers jours, la règle alertait sur
 * une partie locale de 3 caractères qui « ressemblait » à 6 comptes sans aucun
 * rapport. Le critère « nom » n'est pas concerné.
 */
const _180C_LOOKALIKE_MIN_LOCAL_LENGTH = 5;

/**
 * Normalise un prénom ou un nom pour comparaison.
 *
 * @param string $value Valeur brute.
 * @return string Minuscules, sans accents ni espaces superflus.
 */
function _180c_lookalike_normalize( string $value ): string {
	return strtolower( trim( preg_replace( '/\s+/', ' ', remove_accents( $value ) ) ) );
}

/**
 * La commande est-elle la première commande payante d'un compte créé au checkout ?
 *
 * @param WC_Order $order Commande.
 * @return bool
 */
function _180c_lookalike_is_eligible( $order ): bool {
	if ( ! $order instanceof WC_Order || (float) $order->get_total() <= 0 ) {
		return false;
	}

	$user_id = (int) $order->get_customer_id();
	$user    = $user_id ? get_userdata( $user_id ) : false;
	// `store-api` : checkout en blocs et paiements express (Apple Pay, Google Pay).
	if ( ! $user || ! in_array( $order->get_created_via(), array( 'checkout', 'store-api' ), true ) || ! $order->get_date_created() ) {
		return false;
	}

	$gap = $order->get_date_created()->getTimestamp() - strtotime( $user->user_registered . ' UTC' );
	if ( $gap < -300 || $gap > _180C_LOOKALIKE_SIGNUP_WINDOW ) {
		return false;
	}

	// Aucune AUTRE commande payée de ce client.
	$others = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'status'      => array( 'wc-processing', 'wc-completed' ),
			'type'        => 'shop_order',
			'exclude'     => array( $order->get_id() ),
			'limit'       => 1,
			'return'      => 'ids',
		)
	);

	return empty( $others );
}

/**
 * Comptes qui ressemblent à un compte donné.
 *
 * @param int $user_id ID du compte à comparer.
 * @return array<int, array{user_id:int, email:string, reasons:string[]}> Comptes ressemblants.
 */
function _180c_lookalike_matches( int $user_id ): array {
	global $wpdb;

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return array();
	}

	$matches = array();

	// 1. Même prénom et même nom. La collation de wp_usermeta ignore casse et
	// accents : la requête ramène les candidats, PHP confirme.
	$first = _180c_lookalike_normalize( (string) $user->first_name );
	$last  = _180c_lookalike_normalize( (string) $user->last_name );
	if ( '' !== $first && '' !== $last ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lecture ponctuelle, à la première commande payante d'un compte.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT f.user_id FROM {$wpdb->usermeta} f
				 JOIN {$wpdb->usermeta} l ON l.user_id = f.user_id AND l.meta_key = 'last_name'
				 WHERE f.meta_key = 'first_name' AND f.meta_value = %s AND l.meta_value = %s AND f.user_id <> %d",
				$user->first_name,
				$user->last_name,
				$user_id
			)
		);
		foreach ( $ids as $id ) {
			$other = get_userdata( (int) $id );
			if ( $other
				&& _180c_lookalike_normalize( (string) $other->first_name ) === $first
				&& _180c_lookalike_normalize( (string) $other->last_name ) === $last ) {
				$matches[ (int) $id ] = array( 'nom' );
			}
		}
	}

	// 2. Partie locale d'adresse proche (faute de frappe sur le nom : virupier/vitupier).
	$email = strtolower( (string) $user->user_email );
	$local = substr( $email, 0, (int) strrpos( $email, '@' ) );
	if ( strlen( $local ) >= _180C_LOOKALIKE_MIN_LOCAL_LENGTH ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idem ; une colonne, sans jointure.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT ID, user_email FROM {$wpdb->users} WHERE ID <> %d AND user_email <> ''", $user_id )
		);
		foreach ( $rows as $row ) {
			$other = strtolower( (string) $row->user_email );
			$ol    = substr( $other, 0, (int) strrpos( $other, '@' ) );
			if ( strlen( $ol ) < _180C_LOOKALIKE_MIN_LOCAL_LENGTH || abs( strlen( $ol ) - strlen( $local ) ) > _180C_LOOKALIKE_MAX_LOCAL_DISTANCE ) {
				continue;
			}
			if ( levenshtein( $local, $ol ) <= _180C_LOOKALIKE_MAX_LOCAL_DISTANCE ) {
				$matches[ (int) $row->ID ][] = 'adresse';
			}
		}
	}

	$out = array();
	foreach ( $matches as $id => $reasons ) {
		$other = get_userdata( $id );
		$out[] = array(
			'user_id' => $id,
			'email'   => $other ? (string) $other->user_email : '',
			'reasons' => $reasons,
		);
	}

	return $out;
}

/**
 * Vérifie une commande payée et alerte l'administration en cas de ressemblance.
 *
 * @param int           $order_id ID de la commande.
 * @param WC_Order|null $order    Commande (fournie par WooCommerce).
 * @return void
 */
function _180c_lookalike_check_order( $order_id, $order = null ): void {
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
	if ( ! $order || $order->get_meta( _180C_LOOKALIKE_CHECKED_META ) ) {
		return;
	}
	if ( ! _180c_lookalike_is_eligible( $order ) ) {
		return;
	}

	$order->update_meta_data( _180C_LOOKALIKE_CHECKED_META, time() );
	$order->save_meta_data();

	$user_id = (int) $order->get_customer_id();
	$matches = _180c_lookalike_matches( $user_id );
	if ( ! $matches ) {
		return;
	}

	$user  = get_userdata( $user_id );
	$lines = array();
	foreach ( $matches as $m ) {
		$lines[] = sprintf(
			/* translators: 1: user ID, 2: e-mail address, 3: matching criteria. */
			__( '- compte #%1$d, %2$s (ressemblance : %3$s)', '180c' ),
			$m['user_id'],
			$m['email'],
			implode( ' + ', $m['reasons'] )
		);
	}

	$note = sprintf(
		/* translators: 1: user ID, 2: list of similar accounts. */
		__( 'Doublon possible : le compte #%1$d, créé à cette commande, ressemble à :%2$s', '180c' ),
		$user_id,
		"\n" . implode( "\n", $lines )
	);
	$order->add_order_note( $note, 0, false );

	$body = sprintf(
		/* translators: 1: order number, 2: user ID, 3: e-mail address, 4: list of similar accounts, 5: order edit URL. */
		__( "La commande n°%1\$s vient d'être payée par un compte créé au checkout :\n- compte #%2\$d, %3\$s\n\nIl ressemble à un compte existant :\n%4\$s\n\nFaute de frappe dans l'adresse ? Vérifiez avant que la cliente ou le client ne perde sa newsletter ou son historique.\n\nCommande : %5\$s", '180c' ),
		$order->get_order_number(),
		$user_id,
		$user ? $user->user_email : '',
		implode( "\n", $lines ),
		$order->get_edit_order_url()
	);

	$sent = wp_mail(
		get_option( 'admin_email' ),
		/* translators: %s: order number. */
		sprintf( __( '[180°C] Doublon de compte possible — commande n°%s', '180c' ), $order->get_order_number() ),
		$body
	);
	if ( ! $sent ) {
		_180c_log( 'Alerte doublon : e-mail admin non envoyé', array( 'order_id' => $order->get_id() ), 'error' );
	}

	/**
	 * Déclenché après une alerte « doublon de compte possible ».
	 *
	 * @param WC_Order $order   Commande concernée.
	 * @param array    $matches Comptes ressemblants.
	 */
	do_action( '180c/lookalike_account_alert', $order, $matches ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}
add_action( 'woocommerce_order_status_processing', '_180c_lookalike_check_order', 20, 2 );
add_action( 'woocommerce_order_status_completed', '_180c_lookalike_check_order', 20, 2 );
