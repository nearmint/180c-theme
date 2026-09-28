<?php
/**
 * Compatibilité WooCommerce Subscriptions Gifting — destinataires supprimés.
 *
 * PROBLÈME
 * --------
 * Quand le compte destinataire d'un abonnement offert n'existe plus,
 * `WCS_Gifting::get_user_display_name()` lit `->user_email` sur le `false`
 * renvoyé par `get_user_by()` :
 *
 *     class-wcs-gifting.php:408   $user = get_user_by( 'id', $user_id );
 *     class-wcs-gifting.php:414   $name = make_clickable( $user->user_email );
 *
 * Depuis PHP 8.0 c'est un E_WARNING (auparavant un simple E_NOTICE), et la
 * production tourne en PHP 8. Le warning est imprimé au début de la
 * réponse HTTP : les endpoints `wc/v3/orders` et `wc/v3/subscriptions`
 * renvoient alors un JSON invalide, précédé du chemin absolu du serveur.
 *
 * Le chemin d'exécution passe par un filtre, ce qui rend le défaut corrigeable
 * sans toucher au plugin :
 *
 *     class-wcsg-recipient-management.php:45   add_filter( 'woocommerce_order_item_display_meta_value',
 *                                                          __CLASS__ . '::format_recipient_meta_value', 10 )
 *     woocommerce/includes/class-wc-order-item.php:369     apply_filters( … ) dans get_formatted_meta_data()
 *     class-wcsg-recipient-management.php:414-420          substr() puis get_user_display_name()
 *
 * Aucun `apply_filters` n'existe dans `get_user_display_name()` : le plugin
 * n'offre aucun point d'accroche interne. Le seul levier propre est de
 * remplacer son callback sur le filtre ci-dessus.
 *
 * DEUX STOCKAGES, UN SEUL ENTRETENU
 * ---------------------------------
 * Le destinataire est écrit à deux endroits distincts :
 *
 *   1. meta de LIGNE `wcsg_recipient` = « wcsg_recipient_id_<id> »
 *   2. meta d'ABONNEMENT `_recipient_user` = « <id> »
 *      (lue par `WCS_Gifting::get_recipient_user()`)
 *
 * Le plugin nettoie la première sur `delete_user`
 * (`class-wcsg-recipient-management.php:40` puis `:492-508`) mais **jamais la
 * seconde** — sa boucle ne touche que `wc_delete_order_item_meta()` :
 *
 *     :502   if ( ! wcs_is_subscription( $gifted_item['order_id'] ) ) {
 *     :503       wc_update_order_item_meta( …, 'wcsg_deleted_recipient_data', … );
 *     :505   }
 *     :506   wc_delete_order_item_meta( $gifted_item['order_item_id'], 'wcsg_recipient', … );
 *
 * Ce fichier complète donc le nettoyage sans le dupliquer.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Branche les deux correctifs.
 *
 * Sur `init` : le module gifting s'enregistre sur `plugins_loaded`
 * (`class-wc-subscriptions-plugin.php:47` → `:273`), donc bien avant nous.
 *
 * @return void
 */
function _180c_wcsg_compat_init() {
	if ( ! class_exists( 'WCSG_Recipient_Management' ) ) {
		return;
	}

	// La signature du callback remplacé n'accepte qu'un argument : le plugin
	// l'enregistre sans quatrième paramètre, donc accepted_args vaut 1.
	remove_filter(
		'woocommerce_order_item_display_meta_value',
		'WCSG_Recipient_Management::format_recipient_meta_value',
		10
	);
	add_filter(
		'woocommerce_order_item_display_meta_value',
		'_180c_wcsg_safe_recipient_meta_value',
		10,
		1
	);
}
add_action( 'init', '_180c_wcsg_compat_init' );

/**
 * Rend la valeur d'une meta de ligne sans jamais lire un compte inexistant.
 *
 * Délègue au plugin dans tous les cas où il sait répondre. N'intercepte que le
 * seul cas qu'il ne gère pas : un `wcsg_recipient_id_<id>` dont le compte a
 * disparu.
 *
 * @param mixed $value Valeur de la meta de ligne, telle que passée par
 *                     WC_Order_Item::get_formatted_meta_data().
 * @return mixed Valeur affichable.
 */
function _180c_wcsg_safe_recipient_meta_value( $value ) {
	// Une meta peut être un tableau après désérialisation. `strpos()` lèverait
	// alors une TypeError en PHP 8 — le plugin ne s'en garde pas, nous si.
	if ( ! is_string( $value ) ) {
		return $value;
	}

	// Même test que le plugin (`:415`) : sans underscore final, volontairement.
	if ( false === strpos( $value, 'wcsg_recipient_id' ) ) {
		return WCSG_Recipient_Management::format_recipient_meta_value( $value );
	}

	$recipient_id = (int) substr( $value, strlen( 'wcsg_recipient_id_' ) );

	if ( $recipient_id > 0 && false !== get_userdata( $recipient_id ) ) {
		return WCSG_Recipient_Management::format_recipient_meta_value( $value );
	}

	return sprintf(
		/* translators: %d: identifiant du compte destinataire supprimé. */
		__( 'Destinataire supprimé (#%d)', '180c' ),
		$recipient_id
	);
}

/**
 * Nettoie la meta d'abonnement `_recipient_user` avant suppression d'un compte.
 *
 * Priorité 5 : le callback du plugin est branché sur le même hook en priorité
 * 10 et supprime la meta de ligne dont nous avons besoin pour retrouver les
 * abonnements concernés. Nous devons donc passer avant lui.
 *
 * La recherche réutilise `WCS_Gifting::get_recipient_order_items()` — la
 * requête même dont le plugin se sert — pour couvrir exactement le même
 * périmètre, sans réécrire de SQL et sans dépendre de l'état de HPOS.
 *
 * @param int $user_id Compte en cours de suppression.
 * @return void
 */
function _180c_wcsg_detach_recipient_before_delete( $user_id ) {
	$user_id = (int) $user_id;

	if ( $user_id <= 0 || ! class_exists( 'WCS_Gifting' ) || ! function_exists( 'wcs_is_subscription' ) ) {
		return;
	}

	$items = WCS_Gifting::get_recipient_order_items( $user_id );

	if ( empty( $items ) ) {
		return;
	}

	$note = sprintf(
		/* translators: %d: identifiant du compte destinataire supprimé. */
		__( 'Destinataire supprimé (#%d). La meta _recipient_user a été retirée pour éviter une lecture sur un compte inexistant.', '180c' ),
		$user_id
	);

	$done = array();

	foreach ( $items as $item ) {
		$order_id = isset( $item['order_id'] ) ? (int) $item['order_id'] : 0;

		if ( $order_id <= 0 || isset( $done[ $order_id ] ) || ! wcs_is_subscription( $order_id ) ) {
			continue;
		}

		$subscription = wcs_get_subscription( $order_id );

		if ( ! $subscription || ! $subscription->meta_exists( '_recipient_user' ) ) {
			continue;
		}

		$subscription->delete_meta_data( '_recipient_user' );
		$subscription->delete_meta_data( '_recipient_user_email_address' );
		$subscription->save();
		$subscription->add_order_note( $note );

		$done[ $order_id ] = true;
	}

	if ( ! empty( $done ) ) {
		/**
		 * Émis après détachement d'un destinataire supprimé.
		 *
		 * @param int   $user_id          Compte supprimé.
		 * @param int[] $subscription_ids Abonnements nettoyés.
		 */
		do_action( '180c/gift_recipient_detached', $user_id, array_keys( $done ) );
	}
}
add_action( 'delete_user', '_180c_wcsg_detach_recipient_before_delete', 5, 1 );
