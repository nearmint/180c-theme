<?php
/**
 * Template part — CMP 180°C (binaire, style Mediapart).
 *
 * Un seul rôle : recueillir le choix initial. Deux boutons de même niveau,
 * Refuser / Accepter, aucun interrupteur — donc aucune case pré-cochée possible.
 *
 * La MODIFICATION après coup ne vit plus ici. Elle se fait sur la page
 * /cookies/, où le shortcode `[180c_consent_toggle]` rend l'interrupteur
 * « Mesure d'audience » et son bouton « Enregistrer ». Le lien « Gestion des
 * cookies » du footer y conduit par une navigation ordinaire.
 *
 * Cette modale a donc cessé de porter un mode « gestion » : le footer étant son
 * unique point d'entrée, le garder aurait laissé du code que rien n'atteint.
 *
 * Une seule catégorie exposée, pas de second niveau. Le cookie porte
 * `{v, id, ts, analytics}` ; les signaux Consent Mode v2 en sont dérivés par le
 * module JS, ils ne sont pas stockés.
 *
 * Script JS associé : src/js/modules/consent.js
 * Constantes et configuration : inc/consent.php
 * Styles : src/css/components/consent.css
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="consent" class="consent" role="dialog" aria-modal="true" aria-labelledby="consent-title" hidden>
	<div class="consent__overlay" aria-hidden="true"></div>

	<div class="consent__modal">
		<header class="consent__header">
			<div class="consent__logo" role="img" aria-label="180&deg;C">
				<?php echo _180c_render_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</header>

		<div class="consent__body">
			<h2 id="consent-title" class="consent__title">
				<?php esc_html_e( 'Votre choix pour vos données', '180c' ); ?>
			</h2>

			<?php // Seule zone scrollable : le logo, le titre et les boutons restent visibles quelle que soit la hauteur de viewport. ?>
			<div class="consent__scroll" tabindex="0">
				<p class="consent__text">
					<?php // APP-RELEASE : rétablir la variante « le site et l'application mobile » à la sortie des apps (ligne commentée ci-dessous). ?>
					<?php // esc_html_e( "Pour mettre le site et l'application mobile de 180°C à votre disposition nous utilisons des cookies ou technologies similaires qui nous permettent de collecter des informations sur votre appareil.", '180c' ); ?>
					<?php esc_html_e( "Pour mettre le site de 180°C à votre disposition nous utilisons des cookies ou technologies similaires qui nous permettent de collecter des informations sur votre appareil.", '180c' ); ?>
				</p>

				<p class="consent__text">
					<?php esc_html_e( "Certaines de ces technologies sont nécessaires pour faire fonctionner nos services correctement : vous ne pouvez pas les refuser. D'autres sont optionnelles mais contribuent à faciliter votre expérience de lecteur ou de lectrice et d'une certaine façon à soutenir 180°C : vous pouvez à tout moment donner ou retirer votre consentement.", '180c' ); ?>
				</p>

				<p class="consent__question">
					<strong><?php esc_html_e( 'Acceptez-vous que 180°C emploie des cookies ou technologies similaires utiles à son fonctionnement ?', '180c' ); ?></strong>
				</p>

				<?php
				/*
				 * Pas de lien vers la politique de cookies ici : retiré à la
				 * demande de l'éditeur. L'information reste portée par les deux
				 * paragraphes ci-dessus, et la page /cookies/ reste accessible
				 * depuis le footer. Pour le rétablir, réinsérer :
				 *
				 *   <p class="consent__policy">
				 *     <a href="<?php echo esc_url( _180c_consent_policy_url() ); ?>">Politique de cookies</a>
				 *   </p>
				 *
				 * Les styles `.consent__policy` sont conservés dans
				 * src/css/components/consent.css pour que ce rétablissement
				 * tienne en une ligne.
				 */
				?>
			</div>
		</div>

		<footer class="consent__actions">
			<button type="button" class="consent__btn consent__btn--decline" data-action="consent-decline">
				<?php esc_html_e( 'Refuser', '180c' ); ?>
			</button>
			<button type="button" class="consent__btn consent__btn--accept" data-action="consent-accept">
				<?php esc_html_e( 'Accepter', '180c' ); ?>
			</button>
		</footer>

		<?php
		/*
		 * État dégradé — révélé par le chien de garde ci-dessous si le module JS
		 * ne prend jamais la main. Les deux boutons de choix sont alors masqués
		 * (ils seraient inertes) et remplacés par ce bloc, qui dit la vérité au
		 * visiteur au lieu de faire disparaître la modale dans son dos.
		 */
		?>
		<footer class="consent__fallback" data-consent-fallback hidden>
			<p class="consent__fallback-text">
				<?php esc_html_e( 'Le module de gestion du consentement n’a pas pu se charger. Aucune mesure d’audience n’est active tant que vous n’avez pas choisi.', '180c' ); ?>
			</p>
			<button type="button" class="consent__btn consent__btn--accept" data-action="consent-retry">
				<?php esc_html_e( 'Réessayer', '180c' ); ?>
			</button>
		</footer>
	</div><!-- .consent__modal -->
</div><!-- #consent.consent -->

<?php
/*
 * Révélation immédiate de la modale — correctif LCP.
 *
 * La modale était jusqu'ici révélée par `openModal()` (consent.js), donc
 * après le chargement ET l'exécution du bundle ES. Or c'est elle qui occupe le
 * plus grand rectangle de la fenêtre : tant qu'elle reste `hidden`, l'élément
 * LCP de la page n'est tout simplement pas peint. Mesure Lighthouse mobile du
 * 2026-08-03 sur la home locale : LCP 3 536 ms, dont 2 899 ms (82 %) de pur
 * render delay, sur `p.consent__text`.
 *
 * Ce script est délibérément inline et synchrone : la décision d'afficher ou non
 * doit être prise pendant l'analyse du document, sinon on ne gagne rien. Il ne
 * duplique aucune logique métier — il ne fait que ce que fait `init()` en
 * l'absence de cookie valide, et le module reprend ensuite la main normalement
 * (`openModal()` / `closeModal()` sont idempotents).
 *
 * Lecture du cookie en JS, jamais en PHP : les pages sont servies par WP Super
 * Cache, un test côté serveur figerait le choix du premier visiteur pour tous
 * les suivants.
 *
 * Chien de garde — si le module ne prend jamais la main (bundle en échec,
 * navigateur qui n'exécute pas les modules), la modale BASCULE EN ÉTAT DÉGRADÉ
 * au bout de huit secondes : les deux boutons de choix, devenus inertes, cèdent
 * la place à un message explicite et à un bouton « Réessayer ».
 *
 * Elle ne se masque plus. Le masquage silencieux escamotait la décision du
 * visiteur : il perdait le bandeau sans avoir choisi, le revoyait à chaque
 * visite, et rien ne permettait de mesurer la panne. Un timeout ne doit jamais
 * tenir lieu de consentement.
 *
 * Aucun cookie n'est écrit et aucun outil de mesure n'est activé dans cet état :
 * ni GA4 ni Umami ne sont chargés tant que `load()` n'a pas été appelée, et
 * seule la CMP l'appelle.
 */
?>
<script>
(function () {
	var modal = document.getElementById('consent');
	if (!modal) { return; }

	// Nom et version viennent de inc/consent.php, jamais d'un littéral : cette
	// comparaison décide de montrer ou non la modale, et elle était jusqu'ici
	// le quatrième exemplaire — codé en dur — d'une version qui vivait déjà en
	// trois endroits. Un oubli ici, et le visiteur cesse d'être resollicité
	// sans que rien ne le signale.
	var COOKIE = <?php echo wp_json_encode( _180C_CONSENT_COOKIE ); ?>;
	var COOKIE_VERSION = <?php echo (int) _180C_CONSENT_VERSION; ?>;

	var parts = document.cookie.split('; ');
	for (var i = 0; i < parts.length; i++) {
		if (parts[i].indexOf(COOKIE + '=') !== 0) { continue; }
		try {
			if (JSON.parse(decodeURIComponent(parts[i].slice(COOKIE.length + 1))).v === COOKIE_VERSION) { return; }
		} catch (e) { /* cookie illisible : on traite comme absent. */ }
		break;
	}

	modal.removeAttribute('hidden');
	document.documentElement.classList.add('is-modal-open');

	window.setTimeout(function () {
		if (document.documentElement.dataset.consentReady === '1') { return; }

		// Le module n'a pas répondu : ses boutons ne feraient rien. On les
		// masque au profit du bloc de repli, sans jamais fermer la modale.
		modal.classList.add('is-degraded');
		var fallback = modal.querySelector('[data-consent-fallback]');
		if (fallback) { fallback.removeAttribute('hidden'); }
	}, 8000);

	// « Réessayer » : rechargement de la page. Le bundle est justement ce qui a
	// échoué — le réinjecter depuis une page dont le JS ne s'exécute pas est
	// moins fiable que de repartir d'un document neuf, et le cookie n'ayant pas
	// été écrit, le visiteur retrouve son choix intact.
	modal.addEventListener('click', function (e) {
		if (e.target.closest('[data-action="consent-retry"]')) {
			e.preventDefault();
			window.location.reload();
		}
	});
}());
</script>
