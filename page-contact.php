<?php
/**
 * Template Name: Contact
 * Template Post Type: page
 *
 * Page Contact — hero + formulaire routé côté serveur par objet.
 *
 * Théorie de fonctionnement :
 *  - Soumission `fetch` vers l'endpoint natif POST /180c/v1/contact
 *    (inc/rest/contact.php), qui achemine le message par `wp_mail()`.
 *  - Le routage est porté par la clé de l'objet : chaque <option> expose sa clé
 *    en `data-objet`, que le JS recopie dans le champ caché `objet` — c'est ce
 *    champ, et lui seul, qui décide du destinataire côté serveur.
 *  - `cf_nonce` est rendu ici en secours ; le JS en récupère un frais via
 *    GET /180c/v1/contact/nonce juste avant l'envoi (robustesse cache).
 *  - Honeypot `_gotcha`, vérifié côté client ET côté serveur.
 *  - Sans JavaScript, la soumission native affiche la réponse JSON de l'API :
 *    limite assumée (cf. audit migration Formspree, juillet 2026).
 *
 * Auto-appliqué par WordPress à la page de slug `contact` (page-{slug}.php).
 * Le Template Name permet aussi de l'affecter manuellement à une page au slug
 * différent.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_recipients = function_exists( '_180c_contact_recipients' ) ? _180c_contact_recipients() : array();

get_header();
?>

<main id="main" class="contact site-container" data-component="contact">

	<header class="contact__hero">
		<h1 class="contact__title">Nous écrire</h1>
		<p class="contact__intro">Une question, une remarque, une idée ? Avant de nous écrire, jetez un œil au <a href="/centre-daide/">centre d'aide</a>, vous y trouverez peut-être déjà la réponse. Sinon, ce formulaire est fait pour ça.</p>
	</header>

	<div class="contact__body">

		<form class="contact-form" id="contact-form" method="POST" action="<?php echo esc_url( rest_url( _180C_API_NAMESPACE . '/contact' ) ); ?>" novalidate>

			<input type="text" name="_gotcha" class="contact-form__gotcha" tabindex="-1" autocomplete="off" aria-hidden="true">

			<?php
			// Champ `cf_nonce` volontairement VIDE, comme les champs `nl_nonce`
			// (LOT 1) : cette page est servie depuis le cache statique de WP Super
			// Cache, qui survit à l'expiration du nonce. Le champ reste dans le DOM
			// car c'est lui que le JS cible pour y écrire une valeur fraîche —
			// `applySession()` au premier signe d'intention, puis `fetchActionNonce()`
			// juste avant le POST (src/js/modules/session.js).
			//
			// La vérification serveur reste STRICTE ici, contrairement à celle de la
			// newsletter qui tolère un nonce vide : inc/rest/contact.php rejette un
			// `cf_nonce` absent. C'est délibéré et sans perte — sans JavaScript, ce
			// formulaire POSTe directement vers l'URL REST de son `action` et le
			// visiteur reçoit du JSON brut. Ce chemin est déjà inexploitable, il n'y
			// a donc aucune soumission fonctionnelle à préserver, et rien ne
			// justifierait d'affaiblir la garde CSRF pour lui.
			?>
			<input type="hidden" name="cf_nonce" value="">

			<?php // Clé de routage, renseignée par le JS depuis data-objet de l'option choisie. ?>
			<input type="hidden" name="objet" value="">

			<div class="contact-form__field">
				<label class="contact-form__label" for="cf-name">Prénom et nom</label>
				<input class="contact-form__input" type="text" id="cf-name" name="name"
					placeholder="Prénom et nom" autocomplete="name" required aria-required="true"
					aria-describedby="cf-name-error">
				<p class="contact-form__error" id="cf-name-error" aria-live="polite"></p>
			</div>

			<div class="contact-form__field">
				<label class="contact-form__label" for="cf-email">Adresse e-mail</label>
				<input class="contact-form__input" type="email" id="cf-email" name="email"
					placeholder="vous@mail.com" autocomplete="email" required aria-required="true"
					aria-describedby="cf-email-helper cf-email-error">
				<p class="contact-form__helper" id="cf-email-helper">Nous vous répondrons à cette adresse.</p>
				<p class="contact-form__error" id="cf-email-error" aria-live="polite"></p>
			</div>

			<div class="contact-form__field">
				<label class="contact-form__label" for="cf-subject">Objet de votre message</label>
				<select class="contact-form__select" id="cf-subject" name="_subject" required aria-required="true"
					aria-describedby="cf-subject-error">
					<option value="" data-objet="" selected disabled>Choisissez l'objet de votre message</option>
					<?php foreach ( $_180c_recipients as $_180c_key => $_180c_recipient ) : ?>
						<option value="<?php echo esc_attr( $_180c_recipient['label'] ); ?>"
							data-objet="<?php echo esc_attr( $_180c_key ); ?>">
							<?php echo esc_html( $_180c_recipient['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="contact-form__error" id="cf-subject-error" aria-live="polite"></p>
			</div>

			<div class="contact-form__field">
				<label class="contact-form__label" for="cf-message">Votre message</label>
				<textarea class="contact-form__textarea" id="cf-message" name="message" rows="6"
					placeholder="Décrivez votre demande en quelques lignes." required aria-required="true"
					minlength="20" aria-describedby="cf-message-counter cf-message-error"></textarea>
				<p class="contact-form__counter" id="cf-message-counter" aria-live="polite">0 / 20 caractères minimum</p>
				<p class="contact-form__error" id="cf-message-error" aria-live="polite"></p>
			</div>

			<button class="contact-form__submit" type="submit">Envoyer le message</button>

			<p class="contact-form__rgpd">
				Vos données sont utilisées uniquement pour traiter votre demande. Elles ne sont ni cédées, ni utilisées à des fins commerciales.
				<a href="/politique-confidentialite/">Politique de confidentialité</a>.
			</p>

			<p class="contact-form__status" id="cf-status" role="status" aria-live="polite" hidden></p>

		</form>

	</div>

</main>

<?php
get_footer();
