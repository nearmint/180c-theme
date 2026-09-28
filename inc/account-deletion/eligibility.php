<?php
/**
 * Suppression de compte — règles d'éligibilité.
 *
 * Deux niveaux, volontairement distincts :
 *
 *  1. Éligibilité de RÔLE — décide si l'onglet existe. Seuls les comptes dont
 *     les rôles sont tous dans {customer, subscriber} y ont droit ; tout autre
 *     rôle (auteur, éditeur, administrateur…) ne voit pas l'entrée de menu et
 *     tombe sur l'écran S0-rôle en accès direct.
 *
 *  2. BLOCAGES métier — décident si le formulaire s'affiche. Un compte encore
 *     lié à un abonnement, une adhésion ou une commande en cours ne peut pas
 *     être supprimé : la suppression détacherait des objets encore vivants.
 *
 * Ces mêmes règles sont rejouées par le pipeline (eraser.php, contrôle 1) :
 * l'éligibilité peut se perdre entre la demande et la confirmation.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rôles autorisés à supprimer leur compte.
 *
 * Audit BDD du 2026-09-01 : des milliers de comptes `customer` et `subscriber`,
 * et au moins un compte cumulant les deux — d'où un test d'inclusion et non d'égalité.
 *
 * @return string[] Slugs de rôles.
 */
function _180c_account_deletion_allowed_roles(): array {
	return array( 'customer', 'subscriber' );
}

/**
 * Statuts d'abonnement WC Subscriptions considérés comme terminés.
 *
 * Tout autre statut (active, pending, on-hold, pending-cancel) bloque la
 * suppression.
 *
 * @return string[] Statuts sans préfixe `wc-`.
 */
function _180c_account_deletion_closed_subscription_statuses(): array {
	return array( 'cancelled', 'expired', 'switched', 'trash' );
}

/**
 * Statuts d'adhésion WC Memberships considérés comme terminés.
 *
 * @return string[] Statuts sans préfixe `wcm-`.
 */
function _180c_account_deletion_closed_membership_statuses(): array {
	return array( 'cancelled', 'expired' );
}

/**
 * Statuts de commande qui bloquent la suppression.
 *
 * @return string[] Statuts sans préfixe `wc-`.
 */
function _180c_account_deletion_open_order_statuses(): array {
	return array( 'pending', 'on-hold', 'processing' );
}

/**
 * Indique si le compte a le bon profil de rôle pour voir l'onglet.
 *
 * @param int $user_id Identifiant WP.
 * @return bool Vrai si tous les rôles du compte sont autorisés.
 */
function _180c_account_deletion_role_eligible( int $user_id ): bool {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return false;
	}

	$roles = array_values( (array) $user->roles );

	if ( empty( $roles ) ) {
		return false;
	}

	return array() === array_diff( $roles, _180c_account_deletion_allowed_roles() );
}

/**
 * Formate un horodatage local en date lisible (« 4 septembre 2026 »).
 *
 * @param int $timestamp Horodatage déjà décalé sur le fuseau du site.
 * @return string Date formatée, vide si l'horodatage est nul.
 */
function _180c_account_deletion_format_date( int $timestamp ): string {
	if ( $timestamp <= 0 ) {
		return '';
	}

	return (string) date_i18n( 'j F Y', $timestamp );
}

/**
 * URL de la page « Mes abonnements » de WooCommerce Subscriptions.
 *
 * Le slug de l'endpoint est une option WooCommerce (`subscriptions` par
 * défaut) : jamais en dur.
 *
 * @return string URL absolue, vide si WooCommerce est indisponible.
 */
function _180c_account_deletion_subscriptions_url(): string {
	if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
		return '';
	}

	$endpoint = (string) get_option( 'woocommerce_myaccount_subscriptions_endpoint', 'subscriptions' );

	if ( '' === $endpoint ) {
		$endpoint = 'subscriptions';
	}

	return (string) wc_get_account_endpoint_url( $endpoint );
}

/**
 * Abonnements du compte, corbeille comprise.
 *
 * `wcs_get_users_subscriptions()` s'appuie sur un cache en user meta
 * (`_wcs_subscription_ids_cache`) : il est rafraîchi ici avant lecture pour ne
 * jamais raisonner sur un état périmé.
 *
 * @param int $user_id Identifiant WP.
 * @return WC_Subscription[] Abonnements indexés par ID.
 */
function _180c_account_deletion_get_subscriptions( int $user_id ): array {
	if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return array();
	}

	_180c_account_deletion_flush_subscription_cache( $user_id );

	$subscriptions = wcs_get_users_subscriptions( $user_id );

	return is_array( $subscriptions ) ? $subscriptions : array();
}

/**
 * Invalide le cache « abonnements du client » de WC Subscriptions.
 *
 * Sans cette purge, `wcs_get_users_subscriptions()` peut renvoyer des IDs
 * détachés depuis (cache en user meta, cf. WCS_Customer_Store_Cached_CPT).
 *
 * @param int $user_id Identifiant WP.
 * @return void
 */
function _180c_account_deletion_flush_subscription_cache( int $user_id ): void {
	if ( ! class_exists( 'WCS_Customer_Store' ) ) {
		return;
	}

	$store = WCS_Customer_Store::instance();

	if ( $store && method_exists( $store, 'delete_cache_for_user' ) ) {
		$store->delete_cache_for_user( $user_id );
	}
}

/**
 * Adhésions du compte, tous statuts.
 *
 * @param int $user_id Identifiant WP.
 * @return array Adhésions WC_Memberships_User_Membership.
 */
function _180c_account_deletion_get_memberships( int $user_id ): array {
	if ( ! function_exists( 'wc_memberships_get_user_memberships' ) ) {
		return array();
	}

	$memberships = wc_memberships_get_user_memberships( $user_id );

	return is_array( $memberships ) ? $memberships : array();
}

/**
 * Adhésions donnant accès aux recettes, à l'exclusion du plan gratuit.
 *
 * Le plan `inscrits` octroie une adhésion ACTIVE à chaque compte au moment
 * de son inscription, soit un exemplaire par compte. Le prendre pour un blocage rendrait la totalité des
 * comptes inéligibles à la suppression, et la fonctionnalité inopérante.
 *
 * Le périmètre retenu est donc celui que le thème utilise déjà partout ailleurs
 * pour décider si un compte a un accès payant : `_180c_recipe_plan_slugs()`
 * (inc/helpers.php), source unique partagée avec le paywall et la synchro du
 * tag premium Mailchimp — et dont `inscrits` est délibérément absent.
 *
 * @param int $user_id Identifiant WP.
 * @return array Adhésions sur un plan « recettes ».
 */
function _180c_account_deletion_get_paid_memberships( int $user_id ): array {
	if ( ! function_exists( '_180c_recipe_plan_slugs' ) ) {
		return _180c_account_deletion_get_memberships( $user_id );
	}

	$slugs = (array) _180c_recipe_plan_slugs();
	$paid  = array();

	foreach ( _180c_account_deletion_get_memberships( $user_id ) as $membership ) {
		if ( ! is_object( $membership ) || ! method_exists( $membership, 'get_plan' ) ) {
			continue;
		}

		$plan = $membership->get_plan();

		if ( $plan && in_array( (string) $plan->get_slug(), $slugs, true ) ) {
			$paid[] = $membership;
		}
	}

	return $paid;
}

/**
 * Commandes du compte, tous statuts, corbeille comprise.
 *
 * @param int $user_id Identifiant WP.
 * @return WC_Order[] Commandes.
 */
function _180c_account_deletion_get_orders( int $user_id ): array {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}

	$statuses = array_merge( array_keys( wc_get_order_statuses() ), array( 'trash' ) );

	$orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'status'      => $statuses,
			'limit'       => -1,
			'type'        => 'shop_order',
		)
	);

	return is_array( $orders ) ? $orders : array();
}

/**
 * Liste les blocages métier qui empêchent la suppression du compte.
 *
 * Chaque entrée est un tableau `{ key, message, url }` — `key` sert à la
 * déduplication (un même motif n'est jamais affiché deux fois), `url` est
 * facultative et pointe vers la page où lever le blocage.
 *
 * @param int $user_id Identifiant WP.
 * @return array<int,array{key:string,message:string,url:string}> Blocages, éventuellement vide.
 */
function _180c_account_deletion_blockers( int $user_id ): array {
	$blockers = array();

	// --- Abonnements ------------------------------------------------------
	$blocking_subscription_ids = array();

	foreach ( _180c_account_deletion_get_subscriptions( $user_id ) as $subscription ) {
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_status' ) ) {
			continue;
		}

		$status = (string) $subscription->get_status();

		if ( in_array( $status, _180c_account_deletion_closed_subscription_statuses(), true ) ) {
			continue;
		}

		$blocking_subscription_ids[] = (int) $subscription->get_id();

		if ( 'pending-cancel' === $status ) {
			$end = _180c_account_deletion_subscription_end_date( $subscription );

			$blockers[] = array(
				'key'     => 'subscription-pending-cancel',
				'message' => sprintf(
					/* translators: %s: date de fin de l'abonnement (ex. « 4 septembre 2026 »). */
					__( 'Votre abonnement est résilié. Il reste actif jusqu\'au %s. Vous pourrez supprimer votre compte après cette date.', '180c' ),
					$end
				),
				'url'     => '',
			);
			continue;
		}

		if ( in_array( $status, array( 'on-hold', 'pending' ), true ) ) {
			$blockers[] = array(
				'key'     => 'subscription-unpaid',
				'message' => __( 'Votre abonnement attend un paiement. Réglez-le ou écrivez-nous.', '180c' ),
				'url'     => '',
			);
			continue;
		}

		$blockers[] = array(
			'key'     => 'subscription-active',
			'message' => __( 'Votre abonnement est en cours. Résiliez-le d\'abord.', '180c' ),
			'url'     => _180c_account_deletion_subscriptions_url(),
		);
	}

	// --- Adhésions --------------------------------------------------------
	foreach ( _180c_account_deletion_get_paid_memberships( $user_id ) as $membership ) {
		if ( ! is_object( $membership ) || ! method_exists( $membership, 'get_status' ) ) {
			continue;
		}

		if ( in_array( (string) $membership->get_status(), _180c_account_deletion_closed_membership_statuses(), true ) ) {
			continue;
		}

		// Adhésion adossée à un abonnement déjà signalé : pas de doublon.
		if ( method_exists( $membership, 'get_subscription_id' ) ) {
			$linked = (int) $membership->get_subscription_id();

			if ( $linked > 0 && in_array( $linked, $blocking_subscription_ids, true ) ) {
				continue;
			}
		}

		$end = _180c_account_deletion_membership_end_date( $membership );

		if ( '' !== $end ) {
			$blockers[] = array(
				'key'     => 'membership-dated',
				'message' => sprintf(
					/* translators: %s: date de fin d'accès (ex. « 4 septembre 2026 »). */
					__( 'Votre accès aux recettes est actif jusqu\'au %s. Vous pourrez supprimer votre compte après cette date.', '180c' ),
					$end
				),
				'url'     => '',
			);
			continue;
		}

		$blockers[] = array(
			'key'     => 'membership-undated',
			'message' => __( 'Votre accès aux recettes est actif. Écrivez-nous pour le fermer.', '180c' ),
			'url'     => '',
		);
	}

	// --- Commandes --------------------------------------------------------
	$open_statuses = _180c_account_deletion_open_order_statuses();

	foreach ( _180c_account_deletion_get_orders( $user_id ) as $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_status' ) ) {
			continue;
		}

		if ( ! in_array( (string) $order->get_status(), $open_statuses, true ) ) {
			continue;
		}

		$blockers[] = array(
			'key'     => 'order-open',
			'message' => __( 'Une commande est en cours. Attendez sa livraison ou son annulation.', '180c' ),
			'url'     => '',
		);
		break;
	}

	return _180c_account_deletion_dedupe_blockers( $blockers );
}

/**
 * Déduplique les blocages sur leur clé, en conservant l'ordre d'apparition.
 *
 * @param array<int,array{key:string,message:string,url:string}> $blockers Blocages bruts.
 * @return array<int,array{key:string,message:string,url:string}> Blocages uniques.
 */
function _180c_account_deletion_dedupe_blockers( array $blockers ): array {
	$seen   = array();
	$unique = array();

	foreach ( $blockers as $blocker ) {
		$key = (string) ( $blocker['key'] ?? '' );

		if ( '' === $key || isset( $seen[ $key ] ) ) {
			continue;
		}

		$seen[ $key ] = true;
		$unique[]     = $blocker;
	}

	return $unique;
}

/**
 * Date de fin d'un abonnement, formatée pour l'affichage.
 *
 * `WC_Subscription::get_date_to_display( 'end' )` n'est PAS utilisée : elle
 * renvoie des libellés relatifs anglais (« In 3 weeks », « Not yet ended »,
 * cf. WC_Subscription::format_date_to_display()), incompatibles avec la phrase
 * « Il reste actif jusqu'au {date} ». On reprend son idiome de conversion de
 * fuseau (timestamp GMT + wc_timezone_offset()) sur la date brute.
 *
 * @param object $subscription Abonnement WC Subscriptions.
 * @return string Date formatée, vide si aucune date de fin.
 */
function _180c_account_deletion_subscription_end_date( $subscription ): string {
	if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_time' ) ) {
		return '';
	}

	$timestamp = (int) $subscription->get_time( 'end', 'gmt' );

	if ( $timestamp <= 0 ) {
		return '';
	}

	return _180c_account_deletion_format_date( $timestamp + (int) wc_timezone_offset() );
}

/**
 * Date de fin d'une adhésion, formatée pour l'affichage.
 *
 * @param object $membership Adhésion WC Memberships.
 * @return string Date formatée, vide si l'adhésion est illimitée.
 */
function _180c_account_deletion_membership_end_date( $membership ): string {
	if ( ! is_object( $membership ) || ! method_exists( $membership, 'get_local_end_date' ) ) {
		return '';
	}

	$timestamp = $membership->get_local_end_date( 'timestamp' );

	if ( ! is_numeric( $timestamp ) ) {
		return '';
	}

	return _180c_account_deletion_format_date( (int) $timestamp );
}

/**
 * Indique si le compte peut être supprimé maintenant.
 *
 * @param int $user_id Identifiant WP.
 * @return bool Vrai si le rôle est autorisé et qu'aucun blocage ne subsiste.
 */
function _180c_account_deletion_is_eligible( int $user_id ): bool {
	if ( ! _180c_account_deletion_role_eligible( $user_id ) ) {
		return false;
	}

	return array() === _180c_account_deletion_blockers( $user_id );
}
