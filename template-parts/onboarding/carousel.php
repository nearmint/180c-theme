<?php
/**
 * Template part — Onboarding abonné (carousel de bienvenue, 5 écrans).
 *
 * Rendu sur la page « order-received » sous le tableau récap, uniquement pour
 * une commande contenant un abonnement (câblage dans
 * woocommerce/checkout/thankyou.php). Présente la valeur de l'abonnement à une
 * cible âgée : vouvoiement, phrases courtes, gros boutons, fort contraste.
 *
 * Modèle d'affichage :
 *  - SANS JS : le bloc est rendu empilé et visible (les 5 écrans à la suite,
 *    classe `no-js`), contenu 100 % accessible ; le bouton de lancement
 *    (rendu dans thankyou.php) reste masqué.
 *  - AVEC JS : src/js/modules/onboarding.js déplace le bloc en enfant de <body>
 *    (hors #site-shell, dont le `will-change: transform` piégerait le
 *    position:fixed de la modale), le passe en overlay modal (role="dialog",
 *    aria-modal) masqué jusqu'à ouverture, et révèle le bouton de lancement.
 *    Le panneau est centré (~720px) avec une zone scrollable + une barre de
 *    navigation collée en pied de panneau (reprise des styles de
 *    ._180c-unsub__footer).
 *
 * Le QR est rendu côté client à partir des URLs d'app store passées en data-*.
 * Les badges restent de vrais liens (fallback mobile / sans-JS).
 *
 * @package 180c
 *
 * @var array $args {
 *     @type WC_Order $order Commande WooCommerce de la confirmation.
 * }
 */

defined( 'ABSPATH' ) || exit;

$_180c_onb_order = isset( $args['order'] ) && $args['order'] instanceof WC_Order ? $args['order'] : null;
if ( null === $_180c_onb_order ) {
	return;
}

/*
 * APP-RELEASE — L'écran « L'application » (1er écran du carousel) est masqué tant
 * que les apps mobiles ne sont pas publiées. Pour le réactiver : repasser
 * $_180c_onb_show_app à true. Les écrans suivants se renumérotent automatiquement
 * via $_180c_onb_shift, aucune autre modification n'est nécessaire.
 */
$_180c_onb_show_app         = false;
$_180c_onb_shift            = $_180c_onb_show_app ? 0 : 1;
$_180c_onb_total            = 5 - $_180c_onb_shift;
$_180c_onb_app_store        = function_exists( '_180c_app_store_url' ) ? _180c_app_store_url() : '';
$_180c_onb_google_play      = function_exists( '_180c_google_play_url' ) ? _180c_google_play_url() : '';
$_180c_onb_email            = $_180c_onb_order->get_billing_email();
$_180c_onb_recipes_img      = _180c_onboarding_visual_url( 'recettes' );
$_180c_onb_reports_img      = _180c_onboarding_visual_url( 'reportages' );
$_180c_onb_recipes_url      = home_url( '/recettes/' );
$_180c_onb_reports_url      = home_url( '/la-gazette/' );
$_180c_onb_contact_url      = home_url( '/contact/' );
$_180c_onb_account_url      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/mon-compte/' );
$_180c_onb_logo_cahiers     = _180C_THEME_URI . '/assets/img/email/logo-cahiers-delphine-white.png';
$_180c_onb_badge_appstore   = _180C_THEME_URI . '/src/images/badges/app-store-fr-black.svg';
$_180c_onb_badge_googleplay = _180C_THEME_URI . '/src/images/badges/google-play-fr-black.svg';
?>

<section
	class="_180c-onboarding no-js"
	data-onboarding
	data-onboarding-total="<?php echo esc_attr( (string) $_180c_onb_total ); ?>"
	aria-label="<?php esc_attr_e( 'Bienvenue : présentation de votre abonnement', '180c' ); ?>"
>
	<div class="_180c-onboarding__panel" data-onboarding-panel>

		<?php
		/*
		 * Rail d'en-tête (chrome de la modale, JS uniquement) : eyebrow de
		 * l'écran courant aligné à gauche, bouton fermer à droite, sur la même
		 * ligne. L'eyebrow est synchronisé par onboarding.js depuis l'écran
		 * actif. Masqué sans JS (les écrans empilés portent chacun leur eyebrow).
		 */
		?>
		<div class="_180c-onboarding__header" data-onboarding-header hidden>
			<p class="_180c-onboarding__eyebrow _180c-onboarding__eyebrow--rail" data-onboarding-rail-eyebrow></p>
			<button type="button" class="_180c-onboarding__close" data-onboarding-close aria-label="<?php esc_attr_e( 'Fermer', '180c' ); ?>">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
					<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
				</svg>
			</button>
		</div>

		<?php /* Région d'annonce d'étape (remplie par le JS). */ ?>
		<div class="_180c-onboarding__status screen-reader-text" aria-live="polite"></div>

		<div class="_180c-onboarding__viewport">
			<ol class="_180c-onboarding__screens">

				<?php /* ---------- Écran 1 — L'application ---------- */ ?>
				<?php if ( $_180c_onb_show_app ) : // APP-RELEASE : masqué tant que les apps ne sont pas publiées. ?>
				<li
					class="_180c-onboarding__screen"
					data-onboarding-screen="1"
					role="group"
					aria-roledescription="<?php esc_attr_e( 'Écran', '180c' ); ?>"
					aria-labelledby="_180c-onboarding-title-1"
				>
					<p class="_180c-onboarding__eyebrow"><?php esc_html_e( 'L\'application', '180c' ); ?></p>
					<h2 id="_180c-onboarding-title-1" class="_180c-onboarding__title" tabindex="-1">
						<?php esc_html_e( 'Emportez 180°C partout avec vous', '180c' ); ?>
					</h2>
					<p class="_180c-onboarding__lead _180c-onboarding__lead--desktop">
						<?php esc_html_e( 'L\'application mobile est incluse dans votre abonnement. Scannez le code avec l\'appareil photo de votre téléphone pour l\'installer.', '180c' ); ?>
					</p>
					<p class="_180c-onboarding__lead _180c-onboarding__lead--mobile">
						<?php esc_html_e( 'L\'application mobile est incluse dans votre abonnement. Touchez le bouton pour l\'installer.', '180c' ); ?>
					</p>

					<div class="_180c-onboarding__stores">

						<?php if ( '' !== $_180c_onb_app_store ) : ?>
							<div class="_180c-onboarding__store">
								<h3 class="_180c-onboarding__store-title"><?php esc_html_e( 'iPhone & iPad', '180c' ); ?></h3>
								<div
									class="_180c-onboarding__qr"
									data-onboarding-qr
									data-qr-url="<?php echo esc_url( $_180c_onb_app_store ); ?>"
									aria-hidden="true"
								></div>
								<a
									class="_180c-onboarding__badge"
									href="<?php echo esc_url( $_180c_onb_app_store ); ?>"
									target="_blank"
									rel="noopener noreferrer"
									aria-label="<?php esc_attr_e( 'Télécharger sur l\'App Store', '180c' ); ?>"
								>
									<img
										src="<?php echo esc_url( $_180c_onb_badge_appstore ); ?>"
										alt="<?php esc_attr_e( 'Disponible sur · App Store', '180c' ); ?>"
										width="135"
										height="40"
										loading="lazy"
										decoding="async"
									>
								</a>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $_180c_onb_google_play ) : ?>
							<div class="_180c-onboarding__store">
								<h3 class="_180c-onboarding__store-title"><?php esc_html_e( 'Android', '180c' ); ?></h3>
								<div
									class="_180c-onboarding__qr"
									data-onboarding-qr
									data-qr-url="<?php echo esc_url( $_180c_onb_google_play ); ?>"
									aria-hidden="true"
								></div>
								<a
									class="_180c-onboarding__badge"
									href="<?php echo esc_url( $_180c_onb_google_play ); ?>"
									target="_blank"
									rel="noopener noreferrer"
									aria-label="<?php esc_attr_e( 'Disponible sur Google Play', '180c' ); ?>"
								>
									<img
										src="<?php echo esc_url( $_180c_onb_badge_googleplay ); ?>"
										alt="<?php esc_attr_e( 'Disponible sur · Google Play', '180c' ); ?>"
										width="135"
										height="40"
										loading="lazy"
										decoding="async"
									>
								</a>
							</div>
						<?php endif; ?>

					</div>

					<p class="_180c-onboarding__help _180c-onboarding__help--desktop">
						<?php esc_html_e( 'Ouvrez l\'appareil photo, visez le code, puis touchez le lien qui apparaît.', '180c' ); ?>
					</p>
				</li>
				<?php endif; // APP-RELEASE : fin de l'écran « L'application ». ?>

				<?php /* ---------- Écran 2 — Les recettes ---------- */ ?>
				<li
					class="_180c-onboarding__screen"
					data-onboarding-screen="<?php echo (int) ( 2 - $_180c_onb_shift ); ?>"
					role="group"
					aria-roledescription="<?php esc_attr_e( 'Écran', '180c' ); ?>"
					aria-labelledby="_180c-onboarding-title-<?php echo (int) ( 2 - $_180c_onb_shift ); ?>"
				>
					<p class="_180c-onboarding__eyebrow"><?php esc_html_e( 'Les recettes', '180c' ); ?></p>

					<?php if ( '' !== $_180c_onb_recipes_img ) : ?>
						<figure class="_180c-onboarding__visual">
							<img
								class="_180c-onboarding__visual-img"
								src="<?php echo esc_url( $_180c_onb_recipes_img ); ?>"
								alt=""
								width="971"
								height="1024"
								loading="lazy"
								decoding="async"
							>
						</figure>
					<?php endif; ?>

					<h2 id="_180c-onboarding-title-<?php echo (int) ( 2 - $_180c_onb_shift ); ?>" class="_180c-onboarding__title" tabindex="-1">
						<?php esc_html_e( '1 500 recettes, et votre carnet à vous', '180c' ); ?>
					</h2>

					<ul class="_180c-onboarding__list">
						<li class="_180c-onboarding__list-item">
							<?php esc_html_e( 'Plus de 1 500 recettes de saison, sans aucune publicité.', '180c' ); ?>
						</li>
						<li class="_180c-onboarding__list-item">
							<?php
							printf(
								/* translators: %s : libellé du bouton « Ajouter à mon carnet ». */
								esc_html__( 'Une recette vous plaît ? Cliquez sur « %s ». Vous retrouvez votre carnet dans le menu, en haut du site.', '180c' ),
								esc_html__( 'Ajouter à mon carnet', '180c' )
							);
							?>
						</li>
					</ul>

					<div class="_180c-onboarding__cta">
						<a class="_180c-onboarding__action" href="<?php echo esc_url( $_180c_onb_recipes_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Voir les recettes', '180c' ); ?>
						</a>
					</div>
				</li>

				<?php /* ---------- Écran 3 — Les reportages ---------- */ ?>
				<li
					class="_180c-onboarding__screen"
					data-onboarding-screen="<?php echo (int) ( 3 - $_180c_onb_shift ); ?>"
					role="group"
					aria-roledescription="<?php esc_attr_e( 'Écran', '180c' ); ?>"
					aria-labelledby="_180c-onboarding-title-<?php echo (int) ( 3 - $_180c_onb_shift ); ?>"
				>
					<p class="_180c-onboarding__eyebrow"><?php esc_html_e( 'Les reportages', '180c' ); ?></p>

					<?php if ( '' !== $_180c_onb_reports_img ) : ?>
						<figure class="_180c-onboarding__visual">
							<img
								class="_180c-onboarding__visual-img"
								src="<?php echo esc_url( $_180c_onb_reports_img ); ?>"
								alt=""
								width="971"
								height="1024"
								loading="lazy"
								decoding="async"
							>
						</figure>
					<?php endif; ?>

					<h2 id="_180c-onboarding-title-<?php echo (int) ( 3 - $_180c_onb_shift ); ?>" class="_180c-onboarding__title" tabindex="-1">
						<?php esc_html_e( 'Tous les portraits et reportages, en accès illimité', '180c' ); ?>
					</h2>
					<p class="_180c-onboarding__lead">
						<?php esc_html_e( 'Votre abonnement vous donne accès à tous les portraits et reportages parus dans les 30 numéros de la revue 180°C. Les rencontres, les enquêtes, les récits, tout est là, en intégralité.', '180c' ); ?>
					</p>

					<div class="_180c-onboarding__cta">
						<a class="_180c-onboarding__action" href="<?php echo esc_url( $_180c_onb_reports_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Découvrir les reportages', '180c' ); ?>
						</a>
					</div>
				</li>

				<?php /* ---------- Écran 4 — La newsletter (affichage seul) ---------- */ ?>
				<li
					class="_180c-onboarding__screen"
					data-onboarding-screen="<?php echo (int) ( 4 - $_180c_onb_shift ); ?>"
					role="group"
					aria-roledescription="<?php esc_attr_e( 'Écran', '180c' ); ?>"
					aria-labelledby="_180c-onboarding-title-<?php echo (int) ( 4 - $_180c_onb_shift ); ?>"
				>
					<figure class="_180c-onboarding__logo">
						<img
							class="_180c-onboarding__logo-img"
							src="<?php echo esc_url( $_180c_onb_logo_cahiers ); ?>"
							alt="<?php esc_attr_e( 'Les Cahiers de Delphine', '180c' ); ?>"
							width="300"
							height="110"
							loading="lazy"
							decoding="async"
						>
					</figure>

					<p class="_180c-onboarding__eyebrow"><?php esc_html_e( 'La newsletter', '180c' ); ?></p>
					<h2 id="_180c-onboarding-title-<?php echo (int) ( 4 - $_180c_onb_shift ); ?>" class="_180c-onboarding__title" tabindex="-1">
						<?php esc_html_e( 'Les Cahiers de Delphine, chaque vendredi', '180c' ); ?>
					</h2>
					<p class="_180c-onboarding__lead">
						<?php esc_html_e( 'Chaque vendredi, une recette en intégralité, directement dans votre boîte mail.', '180c' ); ?>
					</p>

					<?php if ( '' !== $_180c_onb_email ) : ?>
						<p class="_180c-onboarding__confirm">
							<span class="_180c-onboarding__confirm-icon" aria-hidden="true">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" focusable="false">
									<path d="M5 12.5l4 4 10-10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
								</svg>
							</span>
							<span class="_180c-onboarding__confirm-text">
								<?php
								printf(
									/* translators: %s : adresse e-mail de facturation. */
									esc_html__( 'Vous êtes bien inscrit à l\'adresse %s.', '180c' ),
									'<strong>' . esc_html( $_180c_onb_email ) . '</strong>'
								);
								?>
							</span>
						</p>
					<?php endif; ?>

					<p class="_180c-onboarding__minor-link">
						<a href="<?php echo esc_url( $_180c_onb_account_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Gérer mes préférences', '180c' ); ?>
						</a>
					</p>
				</li>

				<?php /* ---------- Écran 5 — Besoin d'aide ---------- */ ?>
				<li
					class="_180c-onboarding__screen"
					data-onboarding-screen="<?php echo (int) ( 5 - $_180c_onb_shift ); ?>"
					role="group"
					aria-roledescription="<?php esc_attr_e( 'Écran', '180c' ); ?>"
					aria-labelledby="_180c-onboarding-title-<?php echo (int) ( 5 - $_180c_onb_shift ); ?>"
				>
					<p class="_180c-onboarding__eyebrow"><?php esc_html_e( 'Besoin d\'aide', '180c' ); ?></p>
					<h2 id="_180c-onboarding-title-<?php echo (int) ( 5 - $_180c_onb_shift ); ?>" class="_180c-onboarding__title" tabindex="-1">
						<?php esc_html_e( 'Une question ? Nous sommes à votre écoute', '180c' ); ?>
					</h2>
					<p class="_180c-onboarding__lead">
						<?php esc_html_e( 'Selon votre besoin, vous avez trois interlocuteurs :', '180c' ); ?>
					</p>

					<ul class="_180c-onboarding__list _180c-onboarding__routes">
						<li class="_180c-onboarding__list-item">
							<strong><?php esc_html_e( 'La rédaction', '180c' ); ?></strong>
							<?php echo esc_html( ' : ' ); ?>
							<?php esc_html_e( 'Une question sur une recette ou un contenu.', '180c' ); ?>
						</li>
						<li class="_180c-onboarding__list-item">
							<strong><?php esc_html_e( 'Le service abonnement', '180c' ); ?></strong>
							<?php echo esc_html( ' : ' ); ?>
							<?php esc_html_e( 'Votre facturation ou votre résiliation.', '180c' ); ?>
						</li>
						<li class="_180c-onboarding__list-item">
							<strong><?php esc_html_e( 'Le support technique', '180c' ); ?></strong>
							<?php echo esc_html( ' : ' ); ?>
							<?php esc_html_e( 'Un souci de connexion ou d\'affichage.', '180c' ); ?>
						</li>
					</ul>

					<div class="_180c-onboarding__cta">
						<a class="_180c-onboarding__action" href="<?php echo esc_url( $_180c_onb_contact_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Nous contacter', '180c' ); ?>
						</a>
					</div>
				</li>

			</ol>
		</div>

		<?php
		/*
		 * Barre de navigation — reprend les styles de la barre du flux de
		 * désinscription (._180c-unsub__footer) : boutons primaire/ghost aux
		 * mêmes tokens. En mode modale (JS) elle est collée en pied de panneau
		 * (flux flex, pas de position:fixed → aucun piège #site-shell).
		 * Masquée sans JS (le pas-à-pas n'a aucun sens quand les écrans sont
		 * empilés). Libellés et état pilotés par onboarding.js.
		 */
		?>
		<div class="_180c-onboarding__nav" data-onboarding-nav hidden>
			<div class="_180c-onboarding__nav-inner">
				<button type="button" class="_180c-onboarding__btn _180c-onboarding__btn--ghost" data-onboarding-prev>
					<?php esc_html_e( 'Passer', '180c' ); ?>
				</button>

				<div class="_180c-onboarding__progress">
					<p class="_180c-onboarding__progress-label" data-onboarding-step-label aria-hidden="true">
						<?php
						printf(
							/* translators: 1: numéro de l'écran courant, 2: nombre total d'écrans. */
							esc_html__( 'Étape %1$d / %2$d', '180c' ),
							1,
							(int) $_180c_onb_total
						);
						?>
					</p>
					<div class="_180c-onboarding__bar" aria-hidden="true">
						<?php for ( $_180c_onb_s = 1; $_180c_onb_s <= $_180c_onb_total; $_180c_onb_s++ ) : ?>
							<span class="_180c-onboarding__segment<?php echo 1 === $_180c_onb_s ? ' is-active' : ''; ?>" data-onboarding-segment="<?php echo esc_attr( (string) $_180c_onb_s ); ?>"></span>
						<?php endfor; ?>
					</div>
				</div>

				<button type="button" class="_180c-onboarding__btn _180c-onboarding__btn--primary" data-onboarding-next data-onboarding-recipes-url="<?php echo esc_url( $_180c_onb_recipes_url ); ?>">
					<?php esc_html_e( 'Suivant', '180c' ); ?>
				</button>
			</div>
		</div>
	</div>
</section>
