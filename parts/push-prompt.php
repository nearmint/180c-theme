<?php
/**
 * Template part — Invite d'activation des notifications web.
 *
 * Rendue UNIQUEMENT pour un abonné qui n'a pas encore activé les notifications.
 * Rendue MASQUÉE (`hidden`) : trois conditions supplémentaires ne sont connues
 * que du navigateur, et le HTML peut être servi depuis le cache.
 *
 *   - le nombre de recettes distinctes déjà consultées (localStorage) ;
 *   - une fermeture précédente, mémorisée par version (localStorage) ;
 *   - l'état de `Notification.permission`.
 *
 * C'est donc `src/js/modules/push-prompt.js` qui décide de l'affichage.
 *
 * Inclus depuis `footer.php`, HORS de `#site-shell` : ce conteneur porte
 * `will-change: transform` et fait donc office de containing block pour ses
 * descendants `position: fixed`, ce qui casserait l'ancrage au viewport.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// Éligibilité serveur : logué, abonné, et pas déjà opt-in. Sans cela, l'invite
// n'a aucune raison d'exister dans le document, même masquée.
if ( ! function_exists( '_180c_push_user_is_eligible' ) || ! _180c_push_user_is_eligible() ) {
	return;
}

if ( _180c_push_user_has_optin( get_current_user_id() ) ) {
	return;
}

$_180c_push_messages = _180c_push_js_i18n();
?>
<?php
/*
 * Aucun rôle ARIA sur ce conteneur, à dessein. `wp-components`, chargé en front
 * par WooCommerce Blocks / Subscriptions, pose `[role=region]{position:relative}`
 * en règle NON-layered — laquelle bat tout `position: fixed` déclaré dans un
 * `@layer`, quelle que soit la spécificité. Le composant est déjà accessible
 * sans rôle : titre, texte, deux boutons nommés et une zone de retour en
 * `role="status"`.
 */
?>
<div class="push-prompt"
	data-push-prompt
	data-version="<?php echo esc_attr( (string) _180C_PUSH_PROMPT_VERSION ); ?>"
	hidden>
	<p class="push-prompt__title">
		<?php echo esc_html( $_180c_push_messages['promptTitle'] ); ?>
	</p>
	<p class="push-prompt__body">
		<?php echo esc_html( $_180c_push_messages['promptBody'] ); ?>
	</p>
	<div class="push-prompt__actions">
		<button type="button" class="push-prompt__accept" data-push-prompt-accept>
			<?php echo esc_html( $_180c_push_messages['promptAccept'] ); ?>
		</button>
		<button type="button" class="push-prompt__dismiss" data-push-prompt-dismiss
			aria-label="<?php echo esc_attr( $_180c_push_messages['promptClose'] ); ?>">
			<span aria-hidden="true">&times;</span>
		</button>
	</div>
	<p class="push-prompt__feedback" role="status" aria-live="polite" aria-atomic="true" hidden></p>
</div>
