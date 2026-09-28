<?php
/**
 * Réalignement du tag push sur le cycle de vie de l'abonnement.
 *
 * Écoute les mêmes évènements que `inc/mailchimp/membership-sync.php` et
 * `subscription-sync.php` — l'ADHÉSION est la source de vérité de l'accès
 * premium, l'abonnement n'en est qu'un des chemins d'entrée. Une adhésion peut
 * naître sans abonnement (cadeau, octroi manuel) et mourir sans transition
 * d'abonnement (expiration à date, pause sur échec de paiement).
 *
 * POURQUOI UNE TÂCHE DIFFÉRÉE
 * ---------------------------
 * Deux raisons, dont la seconde n'est pas de la prudence mais une correction :
 *
 *  1. L'API OneSignal ne doit jamais être appelée en synchrone dans un hook
 *     WooCommerce. Un timeout de 15 s se paierait sur le tunnel de commande.
 *
 *  2. Au moment où `wc_memberships_user_membership_status_changed` se déclenche,
 *     les caches objets du plugin portent encore l'ANCIEN statut de l'adhésion
 *     en cours de transition. C'est un piège déjà rencontré et documenté dans
 *     `inc/mailchimp/membership-sync.php`, qui a dû lui répondre par une requête
 *     SQL directe. Ici, différer suffit : la tâche relit l'état une fois la
 *     transition écrite et les caches purgés, et n'a donc besoin d'aucune
 *     requête SQL de contournement — y compris pour le cas des comptes portant
 *     deux adhésions recettes actives, qu'une lecture fraîche voit correctement.
 *
 * Aucun pattern de tâche différée n'existait dans ce thème : les cinq crons en
 * place (`inc/consent.php`, `inc/favorites.php`, `inc/auth/rate-limit.php`,
 * `inc/mailchimp/unpaid-tag.php`, `inc/stats/subscriber-stats.php`) sont tous
 * RÉCURRENTS. Celui-ci est un évènement unique par compte, dédoublonné par ses
 * arguments.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nom du hook de la tâche différée de synchronisation.
 */
const _180C_PUSH_SYNC_HOOK = '_180c_push_sync_tag';

/**
 * Délai avant exécution de la synchro, en secondes.
 *
 * Assez long pour que la transition WooCommerce soit écrite et les caches
 * objets purgés ; assez court pour que le tag suive l'état réel. L'utilisateur
 * ne l'attend pas : à l'opt-in, le SDK pose déjà le tag côté client, cette
 * tâche n'est que le filet de sécurité serveur.
 */
const _180C_PUSH_SYNC_DELAY = 30;

/**
 * Planifie une synchronisation du tag pour un compte, sans doublon.
 *
 * Le dédoublonnage vient de `wp_next_scheduled()` interrogé AVEC les arguments :
 * WordPress indexe les évènements par (hook, signature des arguments). Deux
 * transitions rapprochées sur le même compte ne produisent donc qu'une tâche —
 * et comme la tâche relit l'état au moment de son exécution, la seconde
 * n'apporterait rien.
 *
 * @param int $user_id ID du compte.
 * @return void
 */
function _180c_push_schedule_sync( int $user_id ): void {
	if ( $user_id <= 0 ) {
		return;
	}

	$args = array( $user_id );

	if ( wp_next_scheduled( _180C_PUSH_SYNC_HOOK, $args ) ) {
		return;
	}

	wp_schedule_single_event( time() + _180C_PUSH_SYNC_DELAY, _180C_PUSH_SYNC_HOOK, $args );
}

/**
 * Exécute la synchronisation différée.
 *
 * @param int $user_id ID du compte.
 * @return void
 */
function _180c_push_run_scheduled_sync( $user_id ): void {
	_180c_push_sync_user_tag( (int) $user_id );
}
add_action( _180C_PUSH_SYNC_HOOK, '_180c_push_run_scheduled_sync' );

/**
 * Transition de statut d'une adhésion recettes.
 *
 * Contrairement à la synchro Mailchimp, aucun filtrage sur le statut cible ni
 * aucune vérification d'adhésion concurrente n'est fait ici : la tâche différée
 * recalcule l'état complet du compte par `_180c_push_user_is_subscriber()`. Il
 * suffit donc de savoir QUE quelque chose a bougé sur un plan recettes.
 *
 * @param mixed  $user_membership Adhésion concernée (WC_Memberships_User_Membership).
 * @param string $old_status      Ancien statut, sans le préfixe `wcm-`.
 * @param string $new_status      Nouveau statut, sans le préfixe `wcm-`.
 * @return void
 */
function _180c_push_on_membership_status_changed( $user_membership, $old_status, $new_status ): void {
	unset( $old_status, $new_status );

	if ( ! is_object( $user_membership ) || ! method_exists( $user_membership, 'get_plan' ) ) {
		return;
	}

	$plan = $user_membership->get_plan();
	if ( ! $plan || ! method_exists( $plan, 'get_slug' ) ) {
		return;
	}

	// Hors périmètre recettes (plan gratuit « Inscrits au site ») : rien à faire.
	if ( ! in_array( (string) $plan->get_slug(), _180c_recipe_plan_slugs(), true ) ) {
		return;
	}

	_180c_push_schedule_sync( (int) $user_membership->get_user_id() );
}
add_action( 'wc_memberships_user_membership_status_changed', '_180c_push_on_membership_status_changed', 10, 3 );

/**
 * Adhésion nouvellement créée.
 *
 * `wc_memberships_user_membership_status_changed` ne se déclenche que sur une
 * TRANSITION. Une adhésion créée directement en `active` — cadeau, octroi
 * programmé — n'en produit pas toujours. Ce second point d'entrée comble ce
 * trou ; il est idempotent avec le premier, le dédoublonnage du cron absorbant
 * le doublon quand les deux se déclenchent.
 *
 * @param mixed $membership_plan Plan concerné.
 * @param array $args            { user_id, user_membership_id, is_update }.
 * @return void
 */
function _180c_push_on_membership_created( $membership_plan, $args ): void {
	if ( ! is_object( $membership_plan ) || ! method_exists( $membership_plan, 'get_slug' ) ) {
		return;
	}

	if ( ! in_array( (string) $membership_plan->get_slug(), _180c_recipe_plan_slugs(), true ) ) {
		return;
	}

	_180c_push_schedule_sync( isset( $args['user_id'] ) ? (int) $args['user_id'] : 0 );
}
add_action( 'wc_memberships_user_membership_created', '_180c_push_on_membership_created', 10, 2 );

/**
 * Transition de statut d'un abonnement WooCommerce.
 *
 * Doublon volontaire des hooks d'adhésion : les deux convergent vers la même
 * tâche idempotente, le premier arrivé planifie, le second est absorbé par le
 * dédoublonnage. C'est la même stratégie que celle retenue côté Mailchimp.
 *
 * @param mixed $subscription Abonnement concerné (WC_Subscription).
 * @return void
 */
function _180c_push_on_subscription_status( $subscription ): void {
	if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_user_id' ) ) {
		return;
	}

	_180c_push_schedule_sync( (int) $subscription->get_user_id() );
}
add_action( 'woocommerce_subscription_status_active', '_180c_push_on_subscription_status' );
add_action( 'woocommerce_subscription_status_cancelled', '_180c_push_on_subscription_status' );
add_action( 'woocommerce_subscription_status_expired', '_180c_push_on_subscription_status' );
add_action( 'woocommerce_subscription_status_updated', '_180c_push_on_subscription_status' );

/**
 * Connexion d'un compte.
 *
 * Rattrape les divergences qu'aucun hook n'a vues : expiration d'adhésion à
 * date (WooCommerce Memberships la constate parfois en tâche de fond), octroi
 * en base sans passer par l'API du plugin, ou simple échec réseau d'une synchro
 * précédente. La tâche sort sans appel réseau si le compte n'a pas d'opt-in web.
 *
 * @param string $user_login Identifiant de connexion (non utilisé).
 * @param mixed  $user       Objet WP_User.
 * @return void
 */
function _180c_push_on_login( $user_login, $user ): void {
	unset( $user_login );

	if ( ! $user instanceof WP_User ) {
		return;
	}

	_180c_push_schedule_sync( (int) $user->ID );
}
add_action( 'wp_login', '_180c_push_on_login', 10, 2 );
