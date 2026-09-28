<?php
/**
 * Statut d'abonnement d'un compte, évaluable HORS requête front.
 *
 * POURQUOI CE FICHIER
 * -------------------
 * `_180c_user_is_subscriber()` (inc/recipe-access.php) fait autorité dans ce
 * thème, et rien ici ne la remplace : elle reste la fonction à appeler pour
 * décider ce que voit l'utilisateur courant.
 *
 * Elle a en revanche une limite documentée par l'audit du 2026-09-04 (risque
 * R3) : son mécanisme canonique — WooCommerce Memberships — est gardé par
 * `get_current_user_id() === $user_id`. Interrogée sur un AUTRE compte que
 * l'utilisateur courant, elle saute donc l'adhésion et ne juge plus que sur
 * l'abonnement et la meta d'octroi.
 *
 * Or la synchro du tag push s'exécute précisément hors requête front : dans un
 * hook WooCommerce, dans une tâche cron différée, en WP-CLI. Elle lirait « non
 * abonné » pour tous les comptes dont l'accès vient d'une adhésion sans
 * abonnement actif — cadeaux (`inc/gift/gift.php`), octrois manuels en admin,
 * `wc_memberships_grant_membership_access_from_purchase`. C'est exactement la
 * population que `inc/mailchimp/membership-sync.php` a dû rattraper côté
 * Mailchimp, mesures à l'appui.
 *
 * Ce fichier applique donc la même cascade, sans la garde sur l'utilisateur
 * courant, et n'est consommé QUE par la synchro push. Aucune autre surface ne
 * doit l'appeler : le paywall, le REST `/me` et le tracking continuent de
 * passer par la fonction canonique.
 *
 * Corriger `_180c_user_is_subscriber()` elle-même serait le vrai geste, mais il
 * touche le paywall et le gating REST : arbitrage hors périmètre de ce lot.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valeur du tag OneSignal pour un compte abonné.
 *
 * Nomenclature imposée par l'app iOS (`PushNotificationService.swift:141`), que
 * le web reprend à l'identique : les deux canaux alimentent les mêmes segments,
 * une divergence de valeur les couperait en deux.
 */
const _180C_PUSH_STATUS_ACTIVE = 'active';

/**
 * Valeur du tag OneSignal pour un compte non abonné.
 */
const _180C_PUSH_STATUS_NONE = 'none';

/**
 * Clé du tag OneSignal portant le statut d'abonnement.
 *
 * `subscription_status`, et non `is_subscriber` : c'est le nom que l'app iOS
 * pose déjà. Introduire un second tag ferait coexister deux vérités et rendrait
 * la définition du segment « Abonnés » ambiguë selon le canal.
 */
const _180C_PUSH_STATUS_TAG = 'subscription_status';

/**
 * Indique si un compte a un accès abonné, quel que soit le contexte d'appel.
 *
 * Même cascade que `_180c_user_is_subscriber()`, SANS la garde sur
 * l'utilisateur courant :
 *   a) adhésion WooCommerce Memberships active sur un plan recettes ;
 *   b) abonnement WooCommerce Subscriptions actif ;
 *   c) meta `access_recipes` valant `granted` (octroi manuel / apps).
 *
 * @param int $user_id ID du compte.
 * @return bool True si le compte a un accès abonné.
 */
function _180c_push_user_is_subscriber( int $user_id ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}

	// a) Adhésion — mécanisme canonique de l'accès premium dans ce thème.
	// `wc_memberships_is_user_active_member()` accepte un user_id explicite et
	// ne prend qu'UN slug à la fois : on itère, comme le fait déjà
	// `_180c_is_recipe_subscriber()` (un tableau y renverrait toujours false).
	if ( function_exists( 'wc_memberships_is_user_active_member' ) ) {
		foreach ( _180c_recipe_plan_slugs() as $slug ) {
			if ( wc_memberships_is_user_active_member( $user_id, $slug ) ) {
				return true;
			}
		}
	}

	// b) Abonnement — repli.
	if ( function_exists( 'wcs_user_has_subscription' )
		&& wcs_user_has_subscription( $user_id, '', 'active' ) ) {
		return true;
	}

	// c) Meta d'octroi — repli.
	if ( 'granted' === get_user_meta( $user_id, 'access_recipes', true ) ) {
		return true;
	}

	/**
	 * Filtre le statut d'abonnement utilisé par la synchro push.
	 *
	 * Distinct de `180c/user_is_subscriber` à dessein : accorder l'accès au
	 * contenu et accorder les notifications d'abonné sont deux décisions.
	 *
	 * @param bool $is_subscriber Statut courant (false à ce stade).
	 * @param int  $user_id       ID du compte.
	 */
	return (bool) apply_filters( '180c/push/user_is_subscriber', false, $user_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Valeur du tag `subscription_status` à poser pour un compte.
 *
 * @param int $user_id ID du compte.
 * @return string `active` ou `none`.
 */
function _180c_push_user_subscription_status( int $user_id ): string {
	return _180c_push_user_is_subscriber( $user_id )
		? _180C_PUSH_STATUS_ACTIVE
		: _180C_PUSH_STATUS_NONE;
}
