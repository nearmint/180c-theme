<?php
/**
 * Synchronisation du tag premium Mailchimp avec le cycle de vie WooCommerce
 * Subscriptions.
 *
 * À l'activation d'un abonnement → pose le tag premium sur l'audience.
 * À la résiliation / expiration → retire le tag, derrière le filtre
 * `180c/mc/remove_premium_on_cancel` (true par défaut) pour pouvoir désactiver
 * ce retrait automatique.
 *
 * Décision actée : un seul produit d'abonnement (cf. CLAUDE.md), donc tout
 * abonnement vaut accès premium. Le filtre `180c/mc/subscription_grants_premium`
 * permet de restreindre au cas par cas sans toucher au code.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Synchronise le tag premium pour le client d'un abonnement.
 *
 * Best-effort : un échec Mailchimp est journalisé sans interrompre le flux Woo.
 * Miroite l'état dans la meta `_180c_newsletter_premium` qui pilote l'affichage
 * initial du toggle « Mon Compte ».
 *
 * @param WC_Subscription|mixed $subscription Abonnement concerné.
 * @param bool                  $active       True pour poser le tag, false pour le retirer.
 * @return void
 */
function _180c_mc_subscription_sync_tag( $subscription, bool $active ): void {
	if ( ! function_exists( '_180c_mc_premium_set' ) ) {
		return;
	}
	if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_billing_email' ) ) {
		return;
	}

	/**
	 * Cet abonnement accorde-t-il l'accès premium (tag « Abonnés Premium ») ?
	 *
	 * @param bool            $grants       True par défaut (produit d'abonnement unique).
	 * @param WC_Subscription $subscription Abonnement concerné.
	 */
	$grants = (bool) apply_filters( '180c/mc/subscription_grants_premium', true, $subscription ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	if ( ! $grants ) {
		return;
	}

	$user_id = (int) $subscription->get_user_id();

	// Identité du contact = e-mail DU COMPTE, pas l'e-mail de facturation.
	// C'est celui que lisent /newsletter/premium, /newsletter/status et
	// /newsletter/account-optin ($user->user_email) et celui que vise
	// membership-sync.php. Viser l'adresse de facturation faisait diverger les
	// deux : une petite part des abonnements actifs du clone local a une
	// facturation différente du compte — leur tag partait sur un contact que le
	// toggle « Mon compte » et les apps ne consultent jamais.
	// L'e-mail de facturation reste le repli, pour les abonnements sans compte.
	$email = ( $user_id && function_exists( '_180c_mc_member_email_for_user' ) )
		? _180c_mc_member_email_for_user( $user_id )
		: '';

	if ( '' === $email ) {
		$email = sanitize_email( (string) $subscription->get_billing_email() );
	}

	if ( ! is_email( $email ) ) {
		return;
	}

	$result = _180c_mc_premium_set( $email, $active );
	if ( is_wp_error( $result ) ) {
		_180c_log(
			'Sync tag premium abonnement échouée',
			array(
				'active' => $active,
				'error'  => $result->get_error_code(),
			),
			'warning'
		);
		return;
	}

	// Traçabilité de la source : uniquement à l'activation. Une désactivation ne
	// pose aucun tag (les tags de source ne sont jamais retirés). Best-effort,
	// posé ici et non dans _180c_mc_premium_set() qui reste un setter pur.
	if ( $active && function_exists( '_180c_mc_tag_source' ) ) {
		_180c_mc_tag_source( $email, 'web-subscription-sync' );
	}

	// Miroir local : état initial du toggle « Mon Compte ».
	if ( $user_id ) {
		update_user_meta( $user_id, '_180c_newsletter_premium', $active ? 1 : 0 );
	}

	/**
	 * Déclenché après une synchronisation du tag premium depuis un abonnement.
	 *
	 * @param int  $user_id ID du client (0 si inconnu).
	 * @param bool $active  État appliqué (true = tag posé, false = tag retiré).
	 */
	do_action( '180c/mc/subscription_premium_synced', $user_id, $active ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Abonnement actif → pose le tag premium.
 *
 * @param WC_Subscription|mixed $subscription Abonnement passé à l'état « active ».
 * @return void
 */
function _180c_mc_subscription_activated( $subscription ): void {
	_180c_mc_subscription_sync_tag( $subscription, true );
}
add_action( 'woocommerce_subscription_status_active', '_180c_mc_subscription_activated' );

/**
 * Abonnement résilié / expiré → retire le tag premium (si le filtre l'autorise).
 *
 * @param WC_Subscription|mixed $subscription Abonnement terminé.
 * @return void
 */
function _180c_mc_subscription_ended( $subscription ): void {
	/**
	 * Faut-il retirer le tag premium à la résiliation / expiration ?
	 *
	 * @param bool $remove True par défaut. Renvoyer false pour conserver le tag.
	 */
	$remove = (bool) apply_filters( '180c/mc/remove_premium_on_cancel', true ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	if ( ! $remove ) {
		return;
	}
	_180c_mc_subscription_sync_tag( $subscription, false );
}
add_action( 'woocommerce_subscription_status_cancelled', '_180c_mc_subscription_ended' );
add_action( 'woocommerce_subscription_status_expired', '_180c_mc_subscription_ended' );
