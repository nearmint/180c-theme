<?php
/**
 * Mon Compte — helpers de données.
 *
 * Wrappers de lecture autour de WooCommerce, WC Subscriptions, WC Memberships
 * et WC PDF Invoices. Toutes les fonctions échouent gracieusement (null /
 * array vide) si l'utilisateur n'est pas connecté ou si le plugin requis
 * n'est pas actif.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Résumé d'abonnement WC Subscriptions pour un utilisateur.
 *
 * Renvoie le premier abonnement actif ou pending-cancel s'il existe ; sinon
 * le premier abonnement trouvé (incluant on-hold, cancelled, expired). null
 * si l'utilisateur n'a aucun abonnement ou si le plugin n'est pas actif.
 *
 * @param int $user_id ID utilisateur.
 * @return array{
 *     status: string,
 *     status_label: string,
 *     price_formatted: string,
 *     next_payment_iso: string,
 *     next_payment_label: string,
 *     payment_method: string,
 *     edit_payment_url: string,
 *     view_url: string
 * }|null
 */
function _180c_get_user_subscription_summary( int $user_id ): ?array {
	if ( $user_id <= 0 || ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return null;
	}

	$subscriptions = wcs_get_users_subscriptions( $user_id );
	if ( empty( $subscriptions ) ) {
		return null;
	}

	$preferred = null;
	foreach ( $subscriptions as $subscription ) {
		if ( $subscription instanceof \WC_Subscription
			&& $subscription->has_status( array( 'active', 'pending-cancel' ) )
		) {
			$preferred = $subscription;
			break;
		}
	}

	if ( null === $preferred ) {
		$preferred = reset( $subscriptions );
	}

	if ( ! $preferred instanceof \WC_Subscription ) {
		return null;
	}

	$status           = $preferred->get_status();
	$status_label     = function_exists( 'wcs_get_subscription_status_name' )
		? wcs_get_subscription_status_name( $status )
		: ucfirst( $status );
	$next_payment_iso = (string) $preferred->get_date( 'next_payment' );
	$next_payment_ts  = $next_payment_iso ? strtotime( $next_payment_iso ) : 0;

	return array(
		'status'             => $status,
		'status_label'       => (string) $status_label,
		'price_formatted'    => wp_strip_all_tags( $preferred->get_formatted_order_total() ),
		'next_payment_iso'   => $next_payment_iso,
		'next_payment_label' => $next_payment_ts
			? wp_date( get_option( 'date_format' ), $next_payment_ts )
			: '',
		'payment_method'     => (string) $preferred->get_payment_method_title(),
		'edit_payment_url'   => wc_get_endpoint_url( 'payment-methods', '', wc_get_page_permalink( 'myaccount' ) ),
		'view_url'           => $preferred->get_view_order_url(),
	);
}

/**
 * L'utilisateur a-t-il un abonnement numérique EN COURS ?
 *
 * Source = WC **Subscriptions** (et non Memberships), conformément à la règle
 * projet : « a-t-il accès au contenu ? » → Membership ; « faut-il lui vendre /
 * lui gérer un abonnement ? » → Subscription. Ce helper répond à la seconde
 * question (masquer le cross-sell abo, le garde-fou d'ajout au panier et l'opt-in
 * newsletter au checkout) : on regarde donc l'objet commercial « abonnement »,
 * pas le droit d'accès dérivé.
 *
 * « En cours » = statut ∈ { active, on-hold, pending-cancel } :
 *  - `active`         : abonnement en vigueur ;
 *  - `on-hold`        : suspendu par le client, mais la relation d'abonnement
 *                       existe toujours (réactivable) — lui re-vendre un abo
 *                       n'a pas de sens ;
 *  - `pending-cancel` : résiliation programmée, l'abonnement reste actif jusqu'à
 *                       l'échéance de la période déjà payée.
 * Sont exclus `cancelled`, `expired`, `pending` (pas d'abonnement vivant).
 *
 * Aucune restriction par ID produit : dans cette boutique, TOUS les
 * `shop_subscription` sont des abonnements aux recettes. Les abonnements en cours
 * s'étalent sur 4 produits — les 2 offres actuelles (mensuel #13121518, annuel
 * #13121519) ET 2 offres historiques encore vivantes (#13102512 « illimité aux
 * recettes en ligne », #13045165 « illimité à 180°C »), vérifiées en base. Se
 * restreindre aux seuls IDs présents dans le code raterait la majorité des
 * abonnés. On interroge donc l'existence d'un abonnement, quel que soit le produit.
 *
 * Utilise l'API du plugin (`wcs_user_has_subscription()`), aucun SQL direct.
 * Visiteur non connecté → false.
 *
 * @param int $user_id ID utilisateur (0 = utilisateur courant).
 * @return bool True si l'utilisateur a un abonnement numérique en cours.
 */
function _180c_user_has_ongoing_subscription( int $user_id = 0 ): bool {
	if ( $user_id <= 0 ) {
		$user_id = get_current_user_id();
	}

	$result = false;

	if ( $user_id > 0 && function_exists( 'wcs_user_has_subscription' ) ) {
		// has_status() accepte un tableau : un seul appel pour les 3 statuts,
		// tous produits d'abonnement confondus (product_id vide).
		$result = (bool) wcs_user_has_subscription( $user_id, '', array( 'active', 'on-hold', 'pending-cancel' ) );
	}

	/**
	 * Filtre le statut « abonné en cours » de l'utilisateur.
	 *
	 * @param bool $result  Résultat calculé.
	 * @param int  $user_id ID utilisateur résolu.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (bool) apply_filters( '180c/user_has_ongoing_subscription', $result, $user_id );
}

/**
 * Membership active « canonique » de l'utilisateur.
 *
 * Priorité au plan canonique `abonne-recettes` (puis aux plans historiques,
 * même liste filtrable que _180c_is_recipe_subscriber), et NON au premier
 * résultat brut : un compte peut conserver une membership résiduelle issue de
 * la consolidation 4→1 plans (migration 007). Sans cette priorité, le numéro
 * d'abonné affiché en sidebar pouvait pointer sur l'ancienne membership.
 * Fallback : première membership active trouvée, puis null.
 *
 * @param int $user_id ID utilisateur.
 * @return \WC_Memberships_User_Membership|null
 */
function _180c_get_user_active_membership( int $user_id ): ?\WC_Memberships_User_Membership {
	if ( $user_id <= 0 || ! function_exists( 'wc_memberships_get_user_active_memberships' ) ) {
		return null;
	}

	$memberships = wc_memberships_get_user_active_memberships( $user_id );
	if ( empty( $memberships ) ) {
		return null;
	}

	// Indexe les memberships actives par slug de plan.
	$by_slug = array();
	foreach ( $memberships as $membership ) {
		if ( ! $membership instanceof \WC_Memberships_User_Membership ) {
			continue;
		}
		$plan = $membership->get_plan();
		if ( $plan ) {
			$by_slug[ $plan->get_slug() ] = $membership;
		}
	}

	// Ordre de priorité des plans (source unique partagée avec
	// _180c_is_recipe_subscriber via le même filtre).
	$priority = (array) apply_filters(
		'180c/recipe_subscriber_plan_slugs',
		array(
			'abonne-recettes',                                // cible v2 (post-migration).
			'abonnement-en-ligne',                            // historique.
			'abonnes-recettes-en-ligne',                      // historique.
			'abonnement-aux-recettes-en-ligne-pour-3-mois',   // historique.
			'abonnes-illimite-recettes-en-ligne',             // historique.
		)
	);

	foreach ( $priority as $slug ) {
		if ( isset( $by_slug[ $slug ] ) ) {
			return $by_slug[ $slug ];
		}
	}

	$first = reset( $memberships );

	return $first instanceof \WC_Memberships_User_Membership ? $first : null;
}

/**
 * Numéro d'abonnement affiché en sidebar.
 *
 * Renvoie l'ID de l'abonnement WC Subscriptions de l'utilisateur, quel que soit
 * son statut (c'est le numéro visible côté client, ex. 13125427). L'ID interne
 * de la membership ou l'ID utilisateur n'ont pas de sens ici, on ne les expose
 * donc jamais.
 *
 * Sélection : l'abonnement le plus récent **hors** cancelled/expired ; à défaut
 * (uniquement des abonnements résiliés/expirés), le plus récent tout court.
 * Chaîne vide si l'utilisateur n'a aucun abonnement (la ligne est alors masquée).
 *
 * « Plus récent » = ID de post le plus élevé : les IDs WordPress sont
 * monotones, l'ID le plus haut correspond à l'abonnement créé en dernier.
 *
 * @param int $user_id ID utilisateur.
 * @return string ID de l'abonnement, ou chaîne vide si aucun abonnement.
 */
function _180c_get_user_subscriber_number( int $user_id ): string {
	if ( $user_id <= 0 || ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return '';
	}

	$subscriptions = wcs_get_users_subscriptions( $user_id );
	if ( empty( $subscriptions ) ) {
		return '';
	}

	// Tri par ID décroissant : l'abonnement le plus récent en premier.
	uasort(
		$subscriptions,
		static function ( $a, $b ): int {
			$id_a = $a instanceof \WC_Subscription ? $a->get_id() : 0;
			$id_b = $b instanceof \WC_Subscription ? $b->get_id() : 0;
			return $id_b <=> $id_a;
		}
	);

	$fallback = null;
	foreach ( $subscriptions as $subscription ) {
		if ( ! $subscription instanceof \WC_Subscription ) {
			continue;
		}
		if ( null === $fallback ) {
			$fallback = $subscription; // Plus récent tout court (fallback).
		}
		if ( ! $subscription->has_status( array( 'cancelled', 'expired' ) ) ) {
			return (string) $subscription->get_id(); // Plus récent hors cancelled/expired.
		}
	}

	return $fallback instanceof \WC_Subscription ? (string) $fallback->get_id() : '';
}

/**
 * Commandes récentes formatées pour le tableau « Factures ».
 *
 * Filtre sur completed / processing / refunded pour éviter le bruit des
 * paniers abandonnés. Type d'achat dérivé de la présence de produits
 * d'abonnement ou d'un renouvellement.
 *
 * @param int $user_id ID utilisateur.
 * @param int $limit   Nombre max d'entrées (défaut 10).
 * @return array<int, array{
 *     id: int,
 *     number: string,
 *     date_iso: string,
 *     date_label: string,
 *     type: string,
 *     total: string,
 *     view_url: string,
 *     pdf_url: ?string
 * }>
 */
function _180c_get_user_recent_orders( int $user_id, int $limit = 10 ): array {
	if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}

	$orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'limit'       => max( 1, $limit ),
			'orderby'     => 'date',
			'order'       => 'DESC',
			'status'      => array( 'completed', 'processing', 'refunded' ),
		)
	);

	if ( empty( $orders ) ) {
		return array();
	}

	$rows = array();
	foreach ( $orders as $order ) {
		if ( ! $order instanceof \WC_Order ) {
			continue;
		}

		$date    = $order->get_date_created();
		$date_ts = $date ? $date->getTimestamp() : 0;

		$is_renewal      = function_exists( 'wcs_order_contains_renewal' )
			&& wcs_order_contains_renewal( $order );
		$is_subscription = function_exists( 'wcs_order_contains_subscription' )
			&& wcs_order_contains_subscription( $order, 'any' );

		if ( $is_renewal ) {
			$type = __( 'Renouvellement', '180c' );
		} elseif ( $is_subscription ) {
			$type = __( 'Abonnement', '180c' );
		} else {
			$type = __( 'Boutique', '180c' );
		}

		$rows[] = array(
			'id'         => $order->get_id(),
			'number'     => (string) $order->get_order_number(),
			'date_iso'   => $date ? $date->date( 'c' ) : '',
			'date_label' => $date_ts ? wp_date( get_option( 'date_format' ), $date_ts ) : '',
			'type'       => $type,
			'total'      => wp_strip_all_tags( $order->get_formatted_order_total() ),
			'view_url'   => $order->get_view_order_url(),
			'pdf_url'    => _180c_get_order_invoice_pdf_url( $order ),
		);
	}

	return $rows;
}

/**
 * URL de la facture PDF générée par WC PDF Invoices & Packing Slips.
 *
 * Renvoie null si le plugin n'est pas actif, si le document n'existe pas
 * encore, ou si la lib lance une exception (chargement template, perms…).
 *
 * Note : la classe du document a migré vers le namespace WPO\IPS\Documents\*
 * dans les versions récentes du plugin, qui n'expose plus get_pdf_url() côté
 * document. On essaie la méthode legacy puis le helper global, et en dernier
 * recours on construit l'URL admin-ajax officielle (stable cross-versions).
 *
 * @param \WC_Order $order Commande.
 * @return string|null
 */
function _180c_get_order_invoice_pdf_url( \WC_Order $order ): ?string {
	if ( ! function_exists( 'wcpdf_get_document' ) ) {
		return null;
	}

	try {
		$document = wcpdf_get_document( 'invoice', $order );
	} catch ( \Throwable $e ) {
		return null;
	}

	if ( ! $document || ! method_exists( $document, 'exists' ) || ! $document->exists() ) {
		return null;
	}

	// Plugin legacy (≤ 2.x) — méthode directe sur le document.
	if ( method_exists( $document, 'get_pdf_url' ) ) {
		$url = (string) $document->get_pdf_url();
		if ( '' !== $url ) {
			return $url;
		}
	}

	// Plugin récent (≥ 3.x) — helper global si présent.
	if ( function_exists( 'wpo_wcpdf_get_document_url' ) ) {
		$url = (string) wpo_wcpdf_get_document_url( $document );
		if ( '' !== $url ) {
			return $url;
		}
	}

	// Fallback universel — endpoint admin-ajax du plugin, stable depuis 1.x.
	$url = add_query_arg(
		array(
			'action'        => 'generate_wpo_wcpdf',
			'document_type' => 'invoice',
			'order_ids'     => $order->get_id(),
		),
		admin_url( 'admin-ajax.php' )
	);

	return wp_nonce_url( $url, 'generate_wpo_wcpdf' );
}

/**
 * Indique si l'utilisateur a accès au contenu abonné — membership active
 * OU abonnement WC Subscriptions actif.
 *
 * Différent de _180c_is_recipe_subscriber() (helpers.php) qui ne couvre
 * QUE les memberships. Ici on tient compte aussi des subscriptions en
 * cours (cas où la sync membership ↔ subscription est en retard).
 *
 * @param int $user_id ID utilisateur.
 * @return bool
 */
function _180c_is_user_subscribed( int $user_id ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}

	if ( _180c_get_user_active_membership( $user_id ) instanceof \WC_Memberships_User_Membership ) {
		return true;
	}

	if ( function_exists( 'wcs_user_has_subscription' )
		&& wcs_user_has_subscription( $user_id, '', 'active' )
	) {
		return true;
	}

	return false;
}

/**
 * URL de paiement de la dernière commande à régler d'un abonnement.
 *
 * Couvre les deux cas où un abonné doit payer pour (re)gagner l'accès :
 *  - `pending` : la commande PARENTE (1er paiement) n'est pas réglée ;
 *  - `on-hold` après échec de renouvellement : la dernière commande de
 *    RENOUVELLEMENT reste impayée.
 * `get_last_order()` renvoie la commande la plus récente (renouvellement sinon
 * parent) ; si elle `needs_payment()`, on renvoie son URL de paiement WC.
 *
 * @param \WC_Subscription $subscription Abonnement.
 * @return string URL de paiement, ou '' si rien à régler.
 */
function _180c_get_subscription_payment_url( $subscription ): string {
	if ( ! $subscription instanceof \WC_Subscription ) {
		return '';
	}

	$order = $subscription->get_last_order( 'all', array( 'parent', 'renewal' ) );
	if ( $order instanceof \WC_Order && $order->needs_payment() ) {
		return (string) $order->get_checkout_payment_url();
	}

	return '';
}

/**
 * URL de finalisation du 1er paiement pour un abonnement en attente (pending).
 *
 * Récupère l'abonnement `pending` de l'utilisateur et délègue à
 * _180c_get_subscription_payment_url(). Source unique consommée par les
 * touchpoints qui aiguillent un abonné « en attente de paiement » vers le
 * checkout (bloc Mon compte, paywalls, module home, bandeau /abonnement/).
 *
 * @param int|null $user_id ID utilisateur (null/0 = utilisateur courant).
 * @return string URL de paiement, ou chaîne vide si rien à régler (aucun CTA mort).
 */
function _180c_get_pending_payment_url( $user_id = null ): string {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( $user_id <= 0 || ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return '';
	}

	$subscriptions = wcs_get_users_subscriptions( $user_id );
	foreach ( $subscriptions as $subscription ) {
		if ( $subscription instanceof \WC_Subscription && $subscription->has_status( 'pending' ) ) {
			return _180c_get_subscription_payment_url( $subscription );
		}
	}

	return '';
}

/**
 * État d'abonnement « surface front » de l'utilisateur, pour les wordings et CTA
 * conditionnels partagés (bandeau /abonnement/, paywalls article/recette, module
 * home, bloc à la une Mon compte).
 *
 * Un seul passage sur wcs_get_users_subscriptions(). Priorité d'affichage :
 * active / pending-cancel (accès en cours) > on-hold (en pause) > pending (1er
 * paiement en attente) > cancelled / expired (terminé). pending-cancel est
 * regroupé avec active : l'accès est maintenu jusqu'à la fin de période.
 *
 * @param int $user_id ID utilisateur (0 = utilisateur courant).
 * @return array{state:string,status:string,payment_url:string,reactivate_id:int}
 *     state ∈ '' (aucun abonnement) | 'active' | 'on_hold' | 'pending' | 'ended'.
 *     payment_url : URL de paiement de la commande à régler (state 'pending', ou
 *       'on_hold' si renouvellement impayé), sinon ''.
 *     reactivate_id : ID de l'abonnement à réactiver (state 'on_hold'), sinon 0.
 */
function _180c_get_subscription_display_state( int $user_id = 0 ): array {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	$result  = array(
		'state'         => '',
		'status'        => '',
		'payment_url'   => '',
		'reactivate_id' => 0,
	);

	if ( $user_id <= 0 || ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return $result;
	}

	$subscriptions = wcs_get_users_subscriptions( $user_id );
	if ( empty( $subscriptions ) ) {
		return $result;
	}

	$active  = null;
	$on_hold = null;
	$pending = null;
	$ended   = null;
	foreach ( $subscriptions as $subscription ) {
		if ( ! $subscription instanceof \WC_Subscription ) {
			continue;
		}
		if ( null === $active && $subscription->has_status( array( 'active', 'pending-cancel' ) ) ) {
			$active = $subscription;
		} elseif ( null === $on_hold && $subscription->has_status( 'on-hold' ) ) {
			$on_hold = $subscription;
		} elseif ( null === $pending && $subscription->has_status( 'pending' ) ) {
			$pending = $subscription;
		} elseif ( null === $ended && $subscription->has_status( array( 'cancelled', 'expired' ) ) ) {
			$ended = $subscription;
		}
	}

	if ( $active instanceof \WC_Subscription ) {
		$result['state']  = 'active';
		$result['status'] = $active->get_status();
	} elseif ( $on_hold instanceof \WC_Subscription ) {
		// Deux sous-cas : renouvellement impayé (CB expirée/refusée) → paiement ;
		// sinon pause manuelle → réactivation. Le CTA bascule selon payment_url.
		$result['state']         = 'on_hold';
		$result['status']        = 'on-hold';
		$result['reactivate_id'] = $on_hold->get_id();
		$result['payment_url']   = _180c_get_subscription_payment_url( $on_hold );
	} elseif ( $pending instanceof \WC_Subscription ) {
		$result['state']       = 'pending';
		$result['status']      = 'pending';
		$result['payment_url'] = _180c_get_subscription_payment_url( $pending );
	} elseif ( $ended instanceof \WC_Subscription ) {
		$result['state']  = 'ended';
		$result['status'] = $ended->get_status();
	}

	return $result;
}
