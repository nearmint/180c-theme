<?php
/**
 * « Offrir un abonnement » — formulaire cadeau réutilisable.
 *
 * Utilisé à la fois dans le module accordéon de page-abonnement.php et dans la
 * page autonome page-offrir-un-abonnement.php. La soumission est interceptée par
 * src/js/modules/gift.js (fetch vers 180c/v1/gift/start → redirection checkout).
 *
 * @package 180c
 *
 * @var array $args {
 *     @type string $context Contexte de rendu ('module' | 'page'). Informatif.
 * }
 */

defined( 'ABSPATH' ) || exit;

// Date minimale = aujourd'hui (Europe/Paris).
$_180c_gift_min_date = ( new DateTime( 'now', new DateTimeZone( 'Europe/Paris' ) ) )->format( 'Y-m-d' );

$_180c_gift_maxlen = defined( '_180C_GIFT_MESSAGE_MAXLEN' ) ? _180C_GIFT_MESSAGE_MAXLEN : 500;
?>
<form class="gift__form" data-gift-form novalidate>

	<div class="gift__field">
		<label class="gift__label" for="gift-first-name">
			<?php esc_html_e( 'Prénom du bénéficiaire', '180c' ); ?>
		</label>
		<input
			class="gift__input"
			type="text"
			id="gift-first-name"
			name="recipient_first_name"
			maxlength="120"
			autocomplete="off"
			required
			aria-required="true"
			aria-describedby="gift-first-name-error" />
		<p class="gift__error" id="gift-first-name-error" role="alert" hidden></p>
	</div>

	<div class="gift__field">
		<label class="gift__label" for="gift-email">
			<?php esc_html_e( 'E-mail du bénéficiaire', '180c' ); ?>
		</label>
		<input
			class="gift__input"
			type="email"
			id="gift-email"
			name="recipient_email"
			autocomplete="off"
			required
			aria-required="true"
			aria-describedby="gift-email-error gift-email-hint" />
		<p class="gift__hint" id="gift-email-hint">
			<?php esc_html_e( 'C’est à cette adresse que le cadeau sera envoyé.', '180c' ); ?>
		</p>
		<p class="gift__error" id="gift-email-error" role="alert" hidden></p>
	</div>

	<div class="gift__field">
		<label class="gift__label" for="gift-message">
			<?php esc_html_e( 'Votre message (facultatif)', '180c' ); ?>
		</label>
		<textarea
			class="gift__input gift__textarea"
			id="gift-message"
			name="message"
			rows="3"
			maxlength="<?php echo esc_attr( (string) $_180c_gift_maxlen ); ?>"
			aria-describedby="gift-message-hint"></textarea>
		<p class="gift__hint" id="gift-message-hint">
			<?php
			printf(
				/* translators: %d: nombre maximal de caractères. */
				esc_html__( 'Quelques mots qui accompagneront le cadeau (%d caractères max).', '180c' ),
				(int) $_180c_gift_maxlen
			);
			?>
		</p>
	</div>

	<fieldset class="gift__field gift__field--when">
		<legend class="gift__label"><?php esc_html_e( 'Quand envoyer le cadeau ?', '180c' ); ?></legend>

		<label class="gift__checkbox">
			<input type="checkbox" id="gift-send-now" name="send_now" value="1" checked />
			<span><?php esc_html_e( 'Envoyer maintenant', '180c' ); ?></span>
		</label>

		<div class="gift__date" data-gift-date-wrap hidden>
			<label class="gift__label gift__label--date" for="gift-send-date">
				<?php esc_html_e( 'Date d’envoi', '180c' ); ?>
			</label>
			<input
				class="gift__input"
				type="date"
				lang="fr-FR"
				id="gift-send-date"
				name="send_date"
				min="<?php echo esc_attr( $_180c_gift_min_date ); ?>"
				aria-describedby="gift-date-error" />
			<p class="gift__error" id="gift-date-error" role="alert" hidden></p>
		</div>
	</fieldset>

	<button type="submit" class="gift__submit" data-gift-submit>
		<span class="gift__submit-label">
			<?php esc_html_e( 'Offrir cet abonnement', '180c' ); ?>
		</span>
	</button>

	<p class="gift__rgpd">
		<?php
		printf(
			/* translators: %s: lien vers la politique de confidentialité. */
			esc_html__( 'En validant, vous nous confiez l’adresse e-mail d’un tiers dans le seul but de lui transmettre ce cadeau et d’ouvrir son accès aux recettes. Cette donnée n’est utilisée que pour la livraison du cadeau, conformément à notre %s.', '180c' ),
			'<a href="' . esc_url( home_url( '/politique-confidentialite/' ) ) . '">' . esc_html__( 'politique de confidentialité', '180c' ) . '</a>'
		);
		?>
	</p>

	<p class="gift__status" data-gift-status role="status" aria-live="polite" hidden></p>

	<noscript>
		<p class="gift__hint"><?php esc_html_e( 'L’activation de JavaScript est nécessaire pour offrir un abonnement.', '180c' ); ?></p>
	</noscript>
</form>
