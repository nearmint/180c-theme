<?php
/**
 * Email HTML — Relance de paiement d'abonnement (override 180°C).
 *
 * Surcharge thème du gabarit WooCommerce Subscriptions
 * `emails/customer-payment-retry.php`. Rendu par
 * WCS_Email_Customer_Payment_Retry::get_content_html().
 *
 * Variables fournies par la classe WCS (PAS de $subscription dans ce contexte) :
 *
 * @package 180c
 * @version 7.3.0
 *
 * @var WC_Order        $order              Commande de renouvellement échouée.
 * @var WCS_Retry|object $retry             Objet de relance (get_time()).
 * @var string          $email_heading      Titre de l'email.
 * @var string          $additional_content Contenu additionnel (réglages).
 * @var bool            $sent_to_admin      Faux : e-mail client.
 * @var bool            $plain_text         Faux : rendu HTML.
 * @var WC_Email        $email              Instance de l'email.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

// Date de fin d'accès : depuis l'abonnement lié à la commande de renouvellement,
// sinon repli sur la date de relance.
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
?>

<p>
	<?php
	/* translators: %s: prénom du client. */
	printf( esc_html__( 'Bonjour %s,', '180c' ), esc_html( $order->get_billing_first_name() ) );
	?>
</p>

<p><?php esc_html_e( 'Nous avons essayé de renouveler votre abonnement aux recettes en ligne, mais le paiement a échoué. Pas de panique : c’est généralement une simple question de carte expirée ou de coordonnées à mettre à jour.', '180c' ); ?></p>

<?php if ( $_180c_retry_ts ) : ?>
<p>
	<?php
	printf(
		/* translators: %s : date et heure de la prochaine tentative de paiement. */
		esc_html__( 'Nous réessaierons automatiquement le %s. Vous n’avez rien à faire si votre moyen de paiement est à jour.', '180c' ),
		esc_html( wp_date( 'j F Y à H\hi', $_180c_retry_ts ) )
	);
	?>
</p>
<?php endif; ?>

<?php if ( $_180c_end_ts ) : ?>
<p>
	<?php
	printf(
		/* translators: %s: date de fin d'accès (FR). */
		esc_html__( 'Votre accès risque d’être interrompu le %s. Pour continuer à cuisiner sans interruption :', '180c' ),
		esc_html( wp_date( 'j F Y', $_180c_end_ts ) )
	);
	?>
</p>
<?php else : ?>
<p><?php esc_html_e( 'Pour continuer à cuisiner sans interruption :', '180c' ); ?></p>
<?php endif; ?>

<p style="margin:24px 0;">
	<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" style="display:inline-block; background-color:#FFAE3A; color:#0E0E0E; text-decoration:none; padding:13px 26px; font-family:'Oswald','Arial Narrow',Arial,Helvetica,sans-serif; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; border-radius:4px;"><?php esc_html_e( 'Mettre à jour mon moyen de paiement', '180c' ); ?></a>
</p>

<p style="margin:-8px 0 24px 0; font-size:13px;">
	<?php
	printf(
		/* translators: %1$s et %2$s : balises d'ouverture et de fermeture du lien. */
		esc_html__( 'Vous pouvez aussi %1$sgérer vos moyens de paiement%2$s depuis votre espace client.', '180c' ),
		'<a href="' . esc_url( $_180c_payment_methods_url ) . '" style="color:#FFAE3A; font-weight:bold; text-decoration:none;">',
		'</a>'
	);
	?>
</p>

<?php
/**
 * Détail de la commande de renouvellement échouée : article, quantité et
 * montant. Rendu par WooCommerce Subscriptions
 * (WC_Subscriptions_Email::order_details), habillé par email-styles.php.
 *
 * @hooked WC_Subscriptions_Email::order_download_details - 10
 * @hooked WC_Subscriptions_Email::order_details - 10
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook de WooCommerce Subscriptions, appelé volontairement depuis sa propre surcharge de gabarit.
do_action( 'woocommerce_subscriptions_email_order_details', $order, $sent_to_admin, $plain_text, $email );
?>

<p><strong><?php esc_html_e( 'Ce que vous retrouverez :', '180c' ); ?></strong></p>

<ul>
	<li><?php esc_html_e( 'Plus de 1 500 recettes testées et approuvées', '180c' ); ?></li>
	<li><?php esc_html_e( 'Les Cahiers de Delphine chaque vendredi', '180c' ); ?></li>
	<?php /* APP-RELEASE : décommenter à la sortie des apps mobiles. ?><li><?php esc_html_e( 'L’accès complet aux recettes avec l’app iOS et Android', '180c' ); ?></li><?php */ ?>
	<li><?php esc_html_e( 'Les portraits et reportages publiés dans 180°C', '180c' ); ?></li>
	<li><?php esc_html_e( 'Votre carnet personnel de recettes', '180c' ); ?></li>
</ul>

<p style="margin-top:20px;">
	<?php esc_html_e( 'Besoin d’assistance ?', '180c' ); ?>
	<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" style="color:#FFAE3A; font-weight:bold; text-decoration:none;"><?php esc_html_e( 'Écrivez-nous', '180c' ); ?></a>.
</p>

<p style="margin-top:20px;">
	<?php esc_html_e( 'À bientôt,', '180c' ); ?><br>
	<?php esc_html_e( 'L’équipe 180°C', '180c' ); ?>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
