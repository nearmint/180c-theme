<?php
/**
 * Template part — Stepper de résiliation (parcours de rétention).
 *
 * <dialog> natif masqué (4 écrans) rendu sous la section « Gérer votre
 * abonnement » du tableau de bord Mon compte, uniquement pour un abonnement
 * résiliable. Markup + styles seulement : AUCUN <script>, aucune ouverture
 * (showModal). Le binding (bascule d'écrans, appels REST, a11y dynamique) est
 * fourni par src/js/modules/unsubscribe-flow.js (Phase 7), qui cible
 * getElementById('_180c-unsub') + les attributs data-unsub-*.
 *
 * Le <dialog> natif gère nativement le piège de focus et la touche Échap.
 *
 * Usage :
 *   get_template_part( 'parts/account/unsubscribe-stepper', null, array(
 *       'subscription_id' => 123,
 *       'suspend_url'     => 'https://…', // '' si la pause n'est pas dispo
 *   ) );
 *
 * @package 180c
 *
 * @var array $args {
 *     @type int    $subscription_id ID de l'abonnement WC Subscriptions.
 *     @type string $suspend_url     URL native de mise en pause (vide si N/A).
 * }
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks/_helpers.php';

$subscription_id = isset( $args['subscription_id'] ) ? (int) $args['subscription_id'] : 0;
$suspend_url     = isset( $args['suspend_url'] ) ? (string) $args['suspend_url'] : '';

if ( $subscription_id <= 0 ) {
	return;
}

// Rail recette de l'écran 1 : 6 recettes récentes publiées.
$unsub_recipes = new WP_Query(
	array(
		'post_type'           => 'recipe',
		'post_status'         => 'publish',
		'posts_per_page'      => 6,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	)
);

$unsub_cards = array();
while ( $unsub_recipes->have_posts() ) {
	$unsub_recipes->the_post();
	$unsub_card = _180c_block_render_recipe_card( get_post(), 'sm' );
	if ( $unsub_card ) {
		$unsub_cards[] = $unsub_card;
	}
}
wp_reset_postdata();

$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/mon-compte/' );
?>

<dialog
	id="_180c-unsub"
	class="_180c-unsub"
	data-subscription-id="<?php echo esc_attr( (string) $subscription_id ); ?>"
	aria-labelledby="_180c-unsub-title-1"
>
	<?php /* Croix de fermeture (plein écran) — ferme sans action serveur. */ ?>
	<button type="button" class="_180c-unsub__close" data-unsub-close aria-label="<?php esc_attr_e( 'Fermer', '180c' ); ?>">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
			<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
		</svg>
	</button>

	<div class="_180c-unsub__inner">

		<?php /* Région d'annonce d'étape (remplie par le JS en Phase 7). */ ?>
		<div class="_180c-unsub__status screen-reader-text" aria-live="polite"></div>

		<?php /* ---------- Écran 1 — Valeur ---------- */ ?>
		<section class="_180c-unsub__step" data-unsub-step="1">
			<h2 id="_180c-unsub-title-1" class="_180c-unsub__title" tabindex="-1">
				<?php esc_html_e( 'Vous nous quittez déjà ?', '180c' ); ?>
			</h2>
			<p class="_180c-unsub__text">
				<?php esc_html_e( 'Votre abonnement soutient un journalisme culinaire exigeant et rigoureux, et vous donne accès à l\'ensemble de nos recettes.', '180c' ); ?>
			</p>

			<?php
			if ( ! empty( $unsub_cards ) ) {
				_180c_render_rail(
					array(
						'title'        => __( 'Nos dernières recettes', '180c' ),
						'items_html'   => $unsub_cards,
						'region_label' => __( 'Nos dernières recettes', '180c' ),
						'modifier'     => 'unsub',
					)
				);
			}
			?>

		</section>

		<?php /* ---------- Écran 2 — Pause ---------- */ ?>
		<section class="_180c-unsub__step" data-unsub-step="2" hidden>
			<h2 id="_180c-unsub-title-2" class="_180c-unsub__title" tabindex="-1">
				<?php esc_html_e( 'Et si vous faisiez simplement une pause ?', '180c' ); ?>
			</h2>
			<p class="_180c-unsub__text">
				<?php esc_html_e( 'Suspendez temporairement votre abonnement : la facturation s\'arrête et vous reprenez quand vous le souhaitez.', '180c' ); ?>
			</p>

		</section>

		<?php /* ---------- Écran 3 — Sondage ---------- */ ?>
		<section class="_180c-unsub__step" data-unsub-step="3" hidden>
			<h2 id="_180c-unsub-title-3" class="_180c-unsub__title" tabindex="-1">
				<?php esc_html_e( 'Dites-nous pourquoi vous souhaitez vous désabonner', '180c' ); ?>
			</h2>

			<fieldset class="_180c-unsub__fieldset">
				<legend class="screen-reader-text"><?php esc_html_e( 'Motif de désabonnement', '180c' ); ?></legend>
				<?php foreach ( _180c_unsub_reasons() as $unsub_slug => $unsub_label ) : ?>
					<label class="_180c-unsub__radio">
						<input type="radio" name="unsub_reason" value="<?php echo esc_attr( $unsub_slug ); ?>">
						<span class="_180c-unsub__radio-label"><?php echo esc_html( $unsub_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<div class="_180c-unsub__field">
				<label class="_180c-unsub__label" for="_180c-unsub-comment">
					<?php esc_html_e( 'Avez-vous d\'autres commentaires ou suggestions à partager ?', '180c' ); ?>
				</label>
				<textarea
					id="_180c-unsub-comment"
					class="_180c-unsub__textarea"
					rows="3"
					data-unsub-comment
				></textarea>
			</div>

		</section>

		<?php /* ---------- Écran 4 — Récap ---------- */ ?>
		<section class="_180c-unsub__step" data-unsub-step="4" hidden>
			<h2 id="_180c-unsub-title-4" class="_180c-unsub__title" tabindex="-1">
				<?php esc_html_e( 'Votre résiliation est enregistrée', '180c' ); ?>
			</h2>
			<p class="_180c-unsub__text">
				<?php
				printf(
					/* translators: %s: date de fin d'accès (insérée par le JS). */
					esc_html__( 'Vous conservez l\'accès à l\'ensemble de nos recettes jusqu\'au %s. Passé cette date, votre abonnement ne sera pas renouvelé.', '180c' ),
					'<span class="_180c-unsub__end-date" data-unsub-end-date></span>'
				);
				?>
			</p>
			<p class="_180c-unsub__mention">
				<?php esc_html_e( 'Un email de confirmation vient de vous être envoyé.', '180c' ); ?>
			</p>

		</section>

	</div>

	<?php
	/*
	 * Barre d'actions sticky (une par écran, basculée en synchro avec les
	 * sections via le même attribut data-unsub-step que showStep() pilote).
	 * Hors de ._180c-unsub__inner pour s'étendre sur 100 % de la largeur de la
	 * page et rester collée en bas (position: fixed, bottom:0). Le contenu est
	 * borné par .site-container — le même conteneur que le header du site :
	 * actions ferrées à gauche, « Contacter le service client » tout à droite.
	 */
	$unsub_contact_url = home_url( '/contact/' );
	?>

	<?php /* ---------- Footer écran 1 ---------- */ ?>
	<div class="_180c-unsub__footer" data-unsub-step="1">
		<div class="site-container _180c-unsub__footer-inner">
			<div class="_180c-unsub__actions">
				<button type="button" class="_180c-unsub__btn _180c-unsub__btn--primary" data-unsub-stay>
					<?php esc_html_e( 'Rester abonné', '180c' ); ?>
				</button>
				<button type="button" class="_180c-unsub__link" data-unsub-next>
					<?php esc_html_e( 'Non merci, se désabonner', '180c' ); ?>
				</button>
			</div>
			<a class="_180c-unsub__btn _180c-unsub__btn--contact" href="<?php echo esc_url( $unsub_contact_url ); ?>">
				<?php esc_html_e( 'Contacter le service client', '180c' ); ?>
			</a>
		</div>
	</div>

	<?php /* ---------- Footer écran 2 ---------- */ ?>
	<div class="_180c-unsub__footer" data-unsub-step="2" hidden>
		<div class="site-container _180c-unsub__footer-inner">
			<div class="_180c-unsub__actions">
				<?php if ( '' !== $suspend_url ) : ?>
					<a class="_180c-unsub__btn _180c-unsub__btn--primary" href="<?php echo esc_url( $suspend_url ); ?>" data-unsub-pause>
						<?php esc_html_e( 'Mettre en pause mon abonnement', '180c' ); ?>
					</a>
				<?php endif; ?>
				<button type="button" class="_180c-unsub__btn" data-unsub-stay>
					<?php esc_html_e( 'Rester abonné', '180c' ); ?>
				</button>
				<button type="button" class="_180c-unsub__link" data-unsub-next>
					<?php esc_html_e( 'Non merci, se désabonner', '180c' ); ?>
				</button>
			</div>
			<a class="_180c-unsub__btn _180c-unsub__btn--contact" href="<?php echo esc_url( $unsub_contact_url ); ?>">
				<?php esc_html_e( 'Contacter le service client', '180c' ); ?>
			</a>
		</div>
	</div>

	<?php /* ---------- Footer écran 3 ---------- */ ?>
	<div class="_180c-unsub__footer" data-unsub-step="3" hidden>
		<div class="site-container _180c-unsub__footer-inner">
			<div class="_180c-unsub__actions">
				<button type="button" class="_180c-unsub__btn" data-unsub-stay>
					<?php esc_html_e( 'Rester abonné', '180c' ); ?>
				</button>
				<button type="button" class="_180c-unsub__btn _180c-unsub__btn--primary" data-unsub-submit>
					<?php esc_html_e( 'Continuer', '180c' ); ?>
				</button>
			</div>
			<a class="_180c-unsub__btn _180c-unsub__btn--contact" href="<?php echo esc_url( $unsub_contact_url ); ?>">
				<?php esc_html_e( 'Contacter le service client', '180c' ); ?>
			</a>
		</div>
	</div>

	<?php /* ---------- Footer écran 4 ---------- */ ?>
	<div class="_180c-unsub__footer" data-unsub-step="4" hidden>
		<div class="site-container _180c-unsub__footer-inner">
			<div class="_180c-unsub__actions">
				<a class="_180c-unsub__btn _180c-unsub__btn--primary" href="<?php echo esc_url( $account_url ); ?>" data-unsub-done>
					<?php esc_html_e( 'Retour à Mon compte', '180c' ); ?>
				</a>
			</div>
			<a class="_180c-unsub__btn _180c-unsub__btn--contact" href="<?php echo esc_url( $unsub_contact_url ); ?>">
				<?php esc_html_e( 'Contacter le service client', '180c' ); ?>
			</a>
		</div>
	</div>
</dialog>
