<?php
/**
 * Mon compte — écran S1 : demande de suppression.
 *
 * Le formulaire ne supprime rien : il enregistre une demande et déclenche
 * l'e-mail de confirmation. La suppression n'intervient qu'après le clic sur le
 * lien reçu, puis sur le bouton de l'écran S3.
 *
 * Aucun sondage : ni motif, ni champ libre, ni case newsletters. Le retrait des
 * newsletters est systématique (mode archive Mailchimp), il est annoncé dans le
 * récapitulatif et n'appelle pas de choix.
 *
 * Le champ, les boutons et le texte d'aide utilisent les composants du design
 * system (`.field` / `.label` / `.input`, `.btn--danger`, `.btn--secondary`,
 * `.field__helper`) plutôt que des classes propres au bloc : ce sont des atomes
 * standards, ils n'ont aucune raison d'avoir un style à eux.
 *
 * « Annuler » est un lien, pas un bouton : il ne soumet rien et ramène
 * simplement au tableau de bord. Un <button> ici enverrait le formulaire.
 *
 * Le titre est un <h2> : la page porte déjà un <h1> « Supprimer mon compte »,
 * rendu par singular.php:21 à partir du filtre
 * `woocommerce_endpoint_supprimer-mon-compte_title`.
 *
 * @package 180c
 *
 * @var array $args {
 *     @type string $error Message d'erreur à afficher, ou chaîne vide.
 * }
 */

defined( 'ABSPATH' ) || exit;

$form_error = isset( $args['error'] ) ? (string) $args['error'] : '';
?>

<section class="c-account-deletion">

	<?php if ( '' !== $form_error ) : ?>
		<p class="c-account-deletion__notice c-account-deletion__notice--error" role="alert">
			<?php echo esc_html( $form_error ); ?>
		</p>
	<?php endif; ?>

	<h2 class="c-account-deletion__title">
		<?php esc_html_e( 'Vous êtes sur le point de supprimer votre compte 180°C.', '180c' ); ?>
	</h2>

	<ul class="c-account-deletion__list">
		<li class="c-account-deletion__item">
			<?php esc_html_e( 'Nous effaçons vos informations personnelles et vos favoris.', '180c' ); ?>
		</li>
		<li class="c-account-deletion__item">
			<?php esc_html_e( 'Vous ne recevrez plus nos newsletters.', '180c' ); ?>
		</li>
		<li class="c-account-deletion__item">
			<?php esc_html_e( 'Nous gardons vos anciennes commandes et vos factures. La loi nous y oblige.', '180c' ); ?>
		</li>
		<li class="c-account-deletion__item c-account-deletion__item--strong">
			<?php esc_html_e( 'Cette action est définitive.', '180c' ); ?>
		</li>
	</ul>

	<form class="c-account-deletion__form" method="post" action="">

		<input type="hidden" name="_180c_acctdel_action" value="request">
		<?php wp_nonce_field( '_180c_acctdel_request', '_180c_acctdel_nonce' ); ?>

		<div class="field">
			<label class="label" for="acctdel-password">
				<?php esc_html_e( 'Votre mot de passe', '180c' ); ?>
			</label>
			<input
				class="input"
				type="password"
				id="acctdel-password"
				name="acctdel_password"
				autocomplete="current-password"
				required
			>
		</div>

		<p class="c-account-deletion__actions">
			<button type="submit" class="btn btn--danger">
				<?php esc_html_e( 'Supprimer mon compte', '180c' ); ?>
			</button>
			<a class="btn btn--secondary" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
				<?php esc_html_e( 'Annuler', '180c' ); ?>
			</a>
		</p>

		<p class="field__helper">
			<?php esc_html_e( 'Vous recevrez ensuite un e-mail pour confirmer.', '180c' ); ?>
		</p>

	</form>

</section>
