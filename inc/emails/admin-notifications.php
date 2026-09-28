<?php
/**
 * Notifications e-mail destinées à l'administrateur — désactivations ciblées.
 *
 * Décision produit (juillet 2026) : l'adresse d'administration du site ne doit plus
 * recevoir de notification « Mot de passe changé ». Sans valeur opérationnelle
 * (aucune action n'en découle), elle noyait la boîte de la rédaction à chaque
 * réinitialisation d'abonné.
 *
 * Deux émetteurs, les deux à neutraliser :
 *   - WordPress core : `wp_password_change_notification()` (pluggable, définie
 *     dans wp-includes/pluggable.php) est accrochée à `after_password_reset`
 *     par wp-includes/default-filters.php ;
 *   - WooCommerce : WC_Shortcode_My_Account::reset_password() déclenche
 *     `after_password_reset` PUIS rappelle `wp_password_change_notification()`
 *     en direct (WC 10.9), sous réserve du filtre
 *     `woocommerce_disable_password_change_notification`. Le seul
 *     `remove_action()` laisserait donc l'e-mail partir sur le parcours Woo —
 *     qui est le parcours réel des abonnés (et qui en envoyait deux).
 *
 * Périmètre strict : cette désactivation ne concerne QUE la copie adressée à
 * l'administrateur. L'e-mail de réinitialisation envoyé à l'utilisateur (core
 * comme WooCommerce), les autres notifications admin (nouveau compte…) et les
 * e-mails WooCommerce transactionnels ne sont pas touchés.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coupe la notification admin de changement de mot de passe.
 *
 * Accroché à `init` : `wp-includes/default-filters.php` est chargé bien avant,
 * l'action à retirer est donc forcément déjà en place.
 *
 * @return void
 */
function _180c_disable_password_change_notification(): void {
	// Parcours WordPress core (mot de passe oublié, wp-login.php).
	remove_action( 'after_password_reset', 'wp_password_change_notification' );

	// Parcours WooCommerce (Mon compte), qui appelle la fonction en direct.
	add_filter( 'woocommerce_disable_password_change_notification', '__return_true' );
}
add_action( 'init', '_180c_disable_password_change_notification' );
