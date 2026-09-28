<?php
/**
 * Configuration JavaScript du web push.
 *
 * Expose sur `window._180c` le strict nécessaire au module front : l'App ID
 * OneSignal, l'identifiant de l'utilisateur, son statut d'abonnement et le
 * catalogue de libellés traduits.
 *
 * RÈGLE D'ÉLIGIBILITÉ, APPLIQUÉE ICI ET PAS SEULEMENT DANS LE JS
 * -------------------------------------------------------------
 * Rien n'est imprimé pour un visiteur anonyme ni pour un compte non abonné.
 * L'éligibilité n'est donc pas une condition d'affichage que le JS pourrait
 * contourner : sans App ID, le module front ne peut rien initialiser du tout.
 *
 * Aucune clé secrète n'est exposée : l'App ID est public par nature (il voyage
 * dans chaque requête du SDK côté navigateur). La clé REST, elle, ne quitte
 * jamais le serveur.
 *
 * Priorité 6 sur `wp_enqueue_scripts` : après le handle `180c-data`
 * (priorité 5), dont ce script dépend pour que `window._180c` existe déjà.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catalogue des libellés du module web push.
 *
 * Traduits côté serveur puis consommés par le JS, comme le fait déjà la
 * newsletter (`_180c_newsletter_js_i18n()`) : aucune chaîne visible n'est
 * codée en dur dans un module JS.
 *
 * @return array<string,string>
 */
function _180c_push_js_i18n(): array {
	return array(
		'on'           => __( 'Les notifications sont activées.', '180c' ),
		'off'          => __( 'Les notifications sont désactivées.', '180c' ),
		'denied'       => __( 'Votre navigateur bloque les notifications. Autorisez-les dans ses réglages, puis réessayez.', '180c' ),
		'dismissed'    => __( 'Vous n’avez pas autorisé les notifications.', '180c' ),
		'unsupported'  => __( 'Votre navigateur ne gère pas les notifications.', '180c' ),
		'network'      => __( 'Connexion impossible. Vérifiez votre réseau et réessayez.', '180c' ),
		'serverError'  => __( 'Une erreur est survenue. Merci de réessayer plus tard.', '180c' ),
		'promptTitle'  => __( 'Ne manquez plus une recette', '180c' ),
		'promptBody'   => __( 'Recevez une alerte à chaque nouvelle publication.', '180c' ),
		'promptAccept' => __( 'Activer les notifications', '180c' ),
		'promptClose'  => __( 'Fermer', '180c' ),
	);
}

/**
 * Version du module d'invite (module sticky).
 *
 * Incrémenter cette valeur réaffiche l'invite aux visiteurs qui l'avaient
 * fermée : la clé de fermeture est comparée à cette version, sur le modèle du
 * bandeau d'information (`inc/info-banner.php`). Fermer une version ne masque
 * donc pas la suivante.
 */
const _180C_PUSH_PROMPT_VERSION = 1;

/**
 * Indique si l'utilisateur courant est éligible au web push.
 *
 * Logué ET abonné. Passe par la fonction canonique : on est en requête front,
 * avec un utilisateur courant établi.
 *
 * @return bool
 */
function _180c_push_user_is_eligible(): bool {
	return is_user_logged_in() && _180c_user_is_subscriber( get_current_user_id() );
}

/**
 * Imprime la configuration web push sur `window._180c`.
 *
 * @return void
 */
function _180c_push_print_js_config(): void {
	if ( ! _180c_push_user_is_eligible() ) {
		return;
	}

	$app_id = _180c_onesignal_app_id();

	// Sans App ID, le module front n'a rien à faire : on n'imprime rien plutôt
	// que d'exposer une configuration inutilisable.
	if ( '' === $app_id ) {
		return;
	}

	$user_id = get_current_user_id();

	wp_add_inline_script(
		'180c-data',
		sprintf(
			// `Object.assign` et NON affectation directe : `inc/analytics/ga4.php`
			// et `inc/enqueue.php` posent déjà des clés sur `window._180c`, qu'un
			// remplacement effacerait.
			'window._180c = Object.assign(window._180c || {}, { push: %1$s });',
			wp_json_encode(
				array(
					'appId'         => $app_id,
					// En CHAÎNE : `OneSignal.login()` attend une chaîne, et
					// l'app iOS envoie déjà `String(id)`. Un entier créerait un
					// second utilisateur OneSignal pour le même compte.
					'userId'        => (string) $user_id,
					'status'        => _180c_push_user_subscription_status( $user_id ),
					'workerPath'    => _180c_push_worker_filename(),
					'optin'         => _180c_push_user_has_optin( $user_id ),
					'promptVersion' => (string) _180C_PUSH_PROMPT_VERSION,
					'messages'      => _180c_push_js_i18n(),
				)
			)
		)
	);
}
add_action( 'wp_enqueue_scripts', '_180c_push_print_js_config', 6 );
