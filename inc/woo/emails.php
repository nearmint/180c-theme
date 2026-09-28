<?php
/**
 * Emails transactionnels WooCommerce — 180°C.
 *
 * Configuration des emails : couleurs, en-tête, pied de page.
 * Les templates visuels sont dans templates/woocommerce/emails/.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// 1. Couleurs des emails via les options Woo
// (appliquées au démarrage, avant l'envoi)
// ============================================================

add_filter(
	'woocommerce_email_background_color',
	function () {
		return '#F5F5F5';
	}
);

add_filter(
	'woocommerce_email_body_background_color',
	function () {
		return '#FFFFFF';
	}
);

add_filter(
	'woocommerce_email_base_color',
	function () {
		return '#FFAE3A';
	}
);

add_filter(
	'woocommerce_email_text_color',
	function () {
		return '#0E0E0E';
	}
);

add_filter(
	'woocommerce_email_footer_text_color',
	function () {
		return '#6E6E6E';
	}
);

// ============================================================
// 2. Texte de pied de page des emails
// ============================================================

// Le footer est désormais entièrement templé dans
// woocommerce/emails/email-footer.php (logo, apps, réseaux, mention légale
// éditeur Thermostat 6, liens). Le filtre `woocommerce_email_footer_text` n'est
// plus appelé par le template → aucune surcharge ici (réconciliation phase 2B :
// suppression de l'ancien footer « Éditions Prisma » obsolète).

// ============================================================
// 3. Objet des emails — préfixe [180°C] natif Woo
// ============================================================

// WooCommerce utilise get_bloginfo('name') dans ses emails.
// On ne surchage pas l'objet ici — le nom du site suffit.

// ============================================================
// 4. Expéditeur des emails
// ============================================================

add_filter(
	'woocommerce_email_from_name',
	function ( string $from_name ): string {
		return '180°C';
	}
);

// ============================================================
// 5. Emails custom 180°C
// ============================================================

add_filter(
	'woocommerce_email_classes',
	function ( array $emails ): array {
		require_once _180C_THEME_DIR . '/inc/emails/class-180c-email-subscription-cancelled.php';
		require_once _180C_THEME_DIR . '/inc/emails/class-180c-email-welcome-subscription.php';
		require_once _180C_THEME_DIR . '/inc/emails/class-180c-email-subscription-paused.php';
		require_once _180C_THEME_DIR . '/inc/emails/class-180c-email-subscription-resumed.php';

		if ( class_exists( '_180C_Email_Subscription_Cancelled' ) ) {
			$emails['_180C_Email_Subscription_Cancelled'] = new _180C_Email_Subscription_Cancelled();
		}

		if ( class_exists( '_180C_Email_Welcome_Subscription' ) ) {
			$emails['_180C_Email_Welcome_Subscription'] = new _180C_Email_Welcome_Subscription();
		}

		if ( class_exists( '_180C_Email_Subscription_Paused' ) ) {
			$emails['_180C_Email_Subscription_Paused'] = new _180C_Email_Subscription_Paused();
		}

		if ( class_exists( '_180C_Email_Subscription_Resumed' ) ) {
			$emails['_180C_Email_Subscription_Resumed'] = new _180C_Email_Subscription_Resumed();
		}

		return $emails;
	}
);

// Relance de paiement (WCS) : objet + titre en français (la classe plugin
// renvoie l'anglais par défaut ; on surcharge via ses filtres dédiés).
add_filter(
	'woocommerce_subscriptions_email_subject_customer_retry',
	function (): string {
		return __( 'Renouveler votre abonnement 180°C en 2 clics', '180c' );
	}
);

add_filter(
	'woocommerce_email_heading_customer_retry',
	function (): string {
		return __( 'Renouvellement de votre abonnement 180°C', '180c' );
	}
);

// Bienvenue abonnement : à l'activation de la souscription (paiement complet).
// On passe par WC()->mailer() pour garantir l'init du mailer et récupérer
// l'instance enregistrée ci-dessus ; la garde anti-doublon vit dans trigger().
add_action(
	'woocommerce_subscription_status_active',
	function ( $subscription ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$mailer = WC()->mailer();
		$email  = $mailer->emails['_180C_Email_Welcome_Subscription'] ?? null;

		if ( $email instanceof _180C_Email_Welcome_Subscription ) {
			$email->trigger( $subscription );
		}
	}
);

// Mise en pause d'abonnement : uniquement sur l'action self-service WCS (bouton
// « Mettre en pause » de Mon compte). `woocommerce_customer_changed_subscription_to_on-hold`
// n'est émise que par WCS_User_Change_Status_Handler → jamais sur une pause admin
// ni sur un `on-hold` d'impayé. On force WC()->mailer() car la requête front qui
// traite l'URL de pause n'initialise pas le mailer ; la garde anti-rebond et la
// garde impayé vivent dans trigger().
add_action(
	'woocommerce_customer_changed_subscription_to_on-hold',
	function ( $subscription ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$mailer = WC()->mailer();
		$email  = $mailer->emails['_180C_Email_Subscription_Paused'] ?? null;

		if ( $email instanceof _180C_Email_Subscription_Paused ) {
			$email->trigger( $subscription );
		}
	}
);

// ============================================================
// 6. E-mail « Votre compte » (customer_new_account) — envoi conditionnel
// ============================================================
//
// L'achat d'un abonnement force la création d'un compte (WC Subscriptions rend
// le guest checkout impossible). Deux cas :
// - checkout classique : la cliente choisit son mot de passe → inutile de lui
// envoyer un e-mail de compte ;
// - checkout Apple Pay / Google Pay (Stripe Express) ou tout flux sans
// formulaire : aucun mot de passe n'est saisi, WooCommerce en génère un
// automatiquement → la cliente a besoin du lien de définition de mot de passe
// pour accéder à son compte web.
//
// On ne peut pas distinguer Apple/Google Pay par la passerelle : via Stripe
// Express, `payment_method` vaut toujours `stripe`. Le signal fiable est le
// flag natif WooCommerce `$password_generated`, propagé jusqu'à l'instance
// e-mail (`WC_Email_Customer_New_Account::$password_generated`) via la chaîne
// `woocommerce_created_customer` → `send_transactional_email` →
// `woocommerce_created_customer_notification`.
//
// Le filtre `woocommerce_email_enabled_customer_new_account` reçoit
// ( bool $enabled, WP_User $user, WC_Email $email ). On envoie l'e-mail si, et
// seulement si, le mot de passe a été généré automatiquement — quel que soit
// l'état du réglage on/off natif (désactivé par défaut ici).
add_filter(
	'woocommerce_email_enabled_customer_new_account',
	function ( $enabled, $user = null, $email = null ) {
		if ( $email instanceof WC_Email && ! empty( $email->password_generated ) ) {
			return true;
		}
		return $email instanceof WC_Email ? false : $enabled;
	},
	10,
	3
);

// ============================================================
// 7. Bloc « Informations sur l'abonnement » — sans la mention
// de renouvellement automatique
// ============================================================
//
// WooCommerce Subscriptions accroche `WC_Subscriptions_Order::add_sub_info_email`
// sur `woocommerce_email_after_order_table` pour rendre le template
// `emails/subscription-info.php`. Ce template affiche, sous le tableau de
// l'abonnement, la phrase « Cet abonnement est configuré pour se renouveler
// automatiquement… Vous pouvez gérer ou annuler cet abonnement sur votre page
// Mon compte » — redondante avec le paragraphe 180°C qui suit déjà dans le corps
// de l'e-mail.
//
// Le template conditionne cette phrase (et elle seule — le tableau reste rendu)
// au drapeau `$skip_my_account_link`, quatrième argument de
// `add_sub_info_email()`. Le plugin s'en sert lui-même dans ses e-mails de
// notification de renouvellement, mais le laisse à `false` sur ce hook et
// n'expose aucun filtre pour le changer.
//
// On remplace donc le callback du plugin par un wrapper qui lui repasse la main
// avec le drapeau à `true`. Aucune copie de template dans le thème : la mise en
// page du bloc reste celle du plugin et suit ses mises à jour.

/**
 * Rend le bloc « Informations sur l'abonnement » sans la mention de
 * renouvellement automatique / lien Mon compte.
 *
 * Signature alignée sur `WC_Subscriptions_Order::add_sub_info_email()`.
 *
 * @param WC_Order $order          Commande en cours de rendu.
 * @param bool     $is_admin_email Vrai si l'e-mail part vers l'administration.
 * @param bool     $plaintext      Vrai pour la variante texte brut.
 * @return void
 */
function _180c_email_subscription_info_without_renewal_notice( $order, $is_admin_email, $plaintext = false ): void {
	if ( ! class_exists( 'WC_Subscriptions_Order' ) ) {
		return;
	}

	WC_Subscriptions_Order::add_sub_info_email( $order, $is_admin_email, $plaintext, true );
}

add_action(
	'init',
	function (): void {
		$hook     = 'woocommerce_email_after_order_table';
		$callback = 'WC_Subscriptions_Order::add_sub_info_email';

		// Renvoie la priorité employée par le plugin, ou false si le hook a
		// disparu (plugin désactivé, ou refonte interne lors d'une mise à jour)
		// — dans ce cas on ne touche à rien.
		$priority = has_action( $hook, $callback );

		if ( false === $priority ) {
			return;
		}

		remove_action( $hook, $callback, $priority );
		add_action( $hook, '_180c_email_subscription_info_without_renewal_notice', $priority, 3 );
	},
	20
);
