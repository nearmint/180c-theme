<?php
/**
 * Statuts d'abonnement — libellés + redirection post-changement (override-safe).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Réécrit certains libellés de statuts d'abonnement (français orienté client).
 *
 * Passe par le filtre dédié de WooCommerce Subscriptions
 * (`wcs_subscription_statuses`), ce qui propage les nouveaux libellés partout où
 * `wcs_get_subscription_statuses()` est consulté : dropdown de statut en admin
 * et `wcs_get_subscription_status_name()`. Seuls `wc-pending` et `wc-on-hold`
 * sont modifiés ; les autres statuts restent inchangés. Aucune modification du
 * plugin.
 *
 * @param array $statuses Statuts indexés par clé `wc-*`.
 * @return array Statuts avec les libellés `wc-pending` / `wc-on-hold` réécrits.
 */
function _180c_relabel_subscription_statuses( $statuses ) {
	if ( ! is_array( $statuses ) ) {
		return $statuses;
	}

	if ( isset( $statuses['wc-pending'] ) ) {
		$statuses['wc-pending'] = _x( 'En attente de paiement', 'Subscription status', '180c' );
	}

	if ( isset( $statuses['wc-on-hold'] ) ) {
		$statuses['wc-on-hold'] = _x( 'En pause', 'Subscription status', '180c' );
	}

	return $statuses;
}
add_filter( 'wcs_subscription_statuses', '_180c_relabel_subscription_statuses' );

/**
 * Redirige vers Mon compte après une mise en pause / réactivation d'abonnement.
 *
 * Par défaut, WooCommerce Subscriptions renvoie vers la fiche `view-subscription`
 * après un changement de statut initié par le client
 * (`WCS_User_Change_Status_Handler::maybe_change_users_subscription()`,
 * redirection en dur, non filtrable). On préfère ramener l'abonné sur la page
 * Mon compte pour les actions « mise en pause » (on-hold) et « réactivation »
 * (active). La résiliation (`cancelled`) n'est PAS concernée (hors périmètre).
 *
 * Mécanisme sans patch plugin : le plugin déclenche l'action
 * `woocommerce_customer_changed_subscription_to_{status}` JUSTE AVANT son propre
 * `wp_safe_redirect()`. En redirigeant + `exit` ici, on court-circuite la
 * redirection view-subscription du plugin.
 *
 * Garde-fous :
 *  - présence des paramètres GET du parcours de changement de statut → ne se
 *    déclenche que sur l'action utilisateur front, jamais sur un changement
 *    admin / programmatique qui émettrait aussi cette action ;
 *  - priorité 999 → tout autre listener de l'action s'exécute d'abord.
 *
 * La notice WooCommerce (« Votre abonnement a été mis en pause / réactivé »),
 * ajoutée par le plugin AVANT cette action, survit et s'affiche sur
 * /mon-compte/ (WC core accroche `woocommerce_output_all_notices` sur
 * `woocommerce_account_content` en priorité 5).
 *
 * @return void
 */
function _180c_redirect_after_subscription_status_change(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture de marqueurs GET du parcours natif WCS ; le nonce est validé par le plugin avant cette action.
	if ( ! isset( $_GET['change_subscription_to'], $_GET['subscription_id'] ) ) {
		return;
	}

	$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
	if ( $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
add_action( 'woocommerce_customer_changed_subscription_to_active', '_180c_redirect_after_subscription_status_change', 999 );
add_action( 'woocommerce_customer_changed_subscription_to_on-hold', '_180c_redirect_after_subscription_status_change', 999 );
