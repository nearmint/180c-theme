<?php
/**
 * Onboarding abonné — helpers (page de remerciement « order-received »).
 *
 * Mini-carousel de bienvenue affiché sur l'écran de confirmation de commande
 * (woocommerce/checkout/thankyou.php) lorsqu'une commande contient un produit
 * d'abonnement. Ces helpers sont en lecture seule, consommés par le partial
 * template-parts/onboarding/carousel.php : garde-fou d'affichage et résolution
 * d'URL des visuels statiques. Aucun effet de bord, aucun appel réseau.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indique si l'onboarding doit s'afficher pour une commande donnée.
 *
 * Vrai dès que la commande contient un produit d'abonnement (cas mixte
 * abonnement + physique inclus). S'appuie sur le helper canonique défini dans
 * inc/checkout.php pour ne pas dupliquer la détection.
 *
 * @param WC_Order|mixed $order Commande WooCommerce (typé large : la valeur peut
 *                              provenir d'un contexte non garanti).
 * @return bool
 */
function _180c_onboarding_should_display( $order ): bool {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	return function_exists( '_180c_order_has_subscription' )
		&& _180c_order_has_subscription( $order );
}

/**
 * URL absolue d'un visuel statique de l'onboarding.
 *
 * Les visuels (recettes, reportages) sont vendus dans le thème sous
 * assets/img/onboarding/. Renvoie une chaîne vide si le fichier est absent, pour
 * permettre au partial de masquer proprement le visuel concerné.
 *
 * @param string $slug Nom de fichier sans extension (ex. « recettes »).
 * @return string URL absolue, ou '' si le fichier n'existe pas.
 */
function _180c_onboarding_visual_url( string $slug ): string {
	$relative = '/assets/img/onboarding/' . $slug . '.webp';

	if ( ! file_exists( _180C_THEME_DIR . $relative ) ) {
		return '';
	}

	return _180C_THEME_URI . $relative;
}
