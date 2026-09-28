<?php
/**
 * Email texte brut — Relance de paiement d'abonnement (override 180°C).
 *
 * Surcharge thème du gabarit plain WooCommerce Subscriptions
 * `emails/plain/customer-payment-retry.php`. Rendu par
 * WCS_Email_Customer_Payment_Retry::get_content_plain().
 *
 * @package 180c
 * @version 1.0.0
 *
 * @var WC_Order        $order              Commande de renouvellement échouée.
 * @var WCS_Retry|object $retry             Objet de relance (get_time()).
 * @var string          $email_heading      Titre de l'email.
 * @var string          $additional_content Contenu additionnel (réglages).
 * @var bool            $sent_to_admin      Faux : e-mail client.
 * @var bool            $plain_text         Vrai : rendu texte brut.
 * @var WC_Email        $email              Instance de l'email.
 */

defined( 'ABSPATH' ) || exit;

$_180c_end_ts = 0;
if ( function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
	$_180c_subs = wcs_get_subscriptions_for_renewal_order( $order );
	if ( ! empty( $_180c_subs ) ) {
		$_180c_sub    = reset( $_180c_subs );
		$_180c_end_ts = (int) $_180c_sub->get_time( 'end' );
		if ( ! $_180c_end_ts ) {
			$_180c_end_ts = (int) $_180c_sub->get_time( 'next_payment' );
		}
	}
}

// Date de la PROCHAINE TENTATIVE automatique. Distincte de la fin d'accès :
// elle ne sert plus de repli à `$_180c_end_ts`, sans quoi la même date serait
// affichée deux fois avec deux sens contradictoires.
$_180c_retry_ts = ( isset( $retry ) && is_object( $retry ) && method_exists( $retry, 'get_time' ) )
	? (int) $retry->get_time()
	: 0;

$_180c_payment_methods_url = wc_get_endpoint_url( 'payment-methods', '', wc_get_page_permalink( 'myaccount' ) );

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

/* translators: %s: prénom du client. */
printf( esc_html__( 'Bonjour %s,', '180c' ), esc_html( $order->get_billing_first_name() ) );
echo "\n\n";

echo esc_html__( 'Nous avons essayé de renouveler votre abonnement aux recettes en ligne, mais le paiement a échoué. Pas de panique : c’est généralement une simple question de carte expirée ou de coordonnées à mettre à jour.', '180c' ) . "\n\n";

if ( $_180c_retry_ts ) {
	printf(
		/* translators: %s : date et heure de la prochaine tentative de paiement. */
		esc_html__( 'Nous réessaierons automatiquement le %s. Vous n’avez rien à faire si votre moyen de paiement est à jour.', '180c' ),
		esc_html( wp_date( 'j F Y à H\\hi', $_180c_retry_ts ) )
	);
	echo "\n\n";
}

if ( $_180c_end_ts ) {
	printf(
		/* translators: %s: date de fin d'accès (FR). */
		esc_html__( 'Votre accès risque d’être interrompu le %s. Pour continuer à cuisiner sans interruption :', '180c' ),
		esc_html( wp_date( 'j F Y', $_180c_end_ts ) )
	);
} else {
	echo esc_html__( 'Pour continuer à cuisiner sans interruption :', '180c' );
}
echo "\n";
echo esc_url( $order->get_checkout_payment_url() ) . "\n\n";

echo esc_html__( 'Gérer vos moyens de paiement :', '180c' ) . ' ' . esc_url( $_180c_payment_methods_url ) . "\n\n";

/**
 * Détail de la commande de renouvellement échouée : article, quantité et
 * montant, en texte brut. `$plain_text` vaut true dans ce contexte, le
 * callback WCS choisit donc son gabarit `emails/plain/email-order-details.php`.
 *
 * @hooked WC_Subscriptions_Email::order_download_details - 10
 * @hooked WC_Subscriptions_Email::order_details - 10
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook de WooCommerce Subscriptions, appelé volontairement depuis sa propre surcharge de gabarit.
do_action( 'woocommerce_subscriptions_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n";

echo esc_html__( 'Ce que vous retrouverez :', '180c' ) . "\n";
echo '- ' . esc_html__( 'Plus de 1 500 recettes testées et approuvées', '180c' ) . "\n";
echo '- ' . esc_html__( 'Les Cahiers de Delphine chaque vendredi', '180c' ) . "\n";
// APP-RELEASE : décommenter à la sortie des apps mobiles.
// echo '- ' . esc_html__( 'L’accès complet aux recettes avec l’app iOS et Android', '180c' ) . "\n";
echo '- ' . esc_html__( 'Les portraits et reportages publiés dans 180°C', '180c' ) . "\n";
echo '- ' . esc_html__( 'Votre carnet personnel de recettes', '180c' ) . "\n\n";

echo esc_html__( 'Besoin d’assistance ? Écrivez-nous :', '180c' ) . ' ' . esc_url( home_url( '/contact/' ) ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo esc_html__( 'À bientôt,', '180c' ) . "\n";
echo esc_html__( 'L’équipe 180°C', '180c' ) . "\n";

echo "\n----------------------------------------\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
