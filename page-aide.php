<?php
/**
 * Template Name: Centre d'aide
 * Template Post Type: page
 *
 * Affiche la FAQ structurée du Centre d'aide (ACF Repeater group_help_page).
 *
 * Données : champ ACF `help_sections` (repeater) sur la page courante.
 *   - section_id (string)
 *   - section_title (string)
 *   - section_questions (repeater)
 *     - question_slug (string)
 *     - question_title (string)
 *     - question_answer (HTML)
 *
 * @package 180c-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$help_sections = function_exists( 'get_field' ) ? get_field( 'help_sections' ) : array();
$has_sections  = ! empty( $help_sections ) && is_array( $help_sections );

/*
 * Phrase du bloc de contact — champ ACF `help_cta_text`.
 *
 * Trois états, volontairement distingués :
 *   - chaîne non vide  → affichée telle quelle ;
 *   - chaîne VIDE      → l'opérateur a retiré la phrase, on n'affiche rien.
 *     C'est tout l'objet de l'externalisation : l'engagement de délai n'est
 *     adossé à aucun SLA côté code, il doit pouvoir être retiré sans toucher
 *     au thème (audit du Centre d'aide, 2026-08-29) ;
 *   - null / false     → champ non résolu. On retombe sur le libellé
 *     historique plutôt que de faire disparaître le bloc.
 *
 * Ce repli n'est pas une précaution de confort, il porte le rendu réel après
 * déploiement. `get_field()` résout un champ par la meta de référence de clé
 * `_<nom>` posée sur le post à l'enregistrement ; tant que la page n'a pas été
 * sauvegardée depuis l'ajout du champ, `_help_cta_text` est absente,
 * `acf_maybe_get_field()` renvoie null et `get_field()` sort AVANT d'appliquer
 * `default_value` — vérifié sur ACF Pro : `acf_get_value()` rend bien la
 * valeur par défaut, `get_field()` rend `null`. Sans ce repli, la phrase
 * disparaîtrait donc du site à la seconde du déploiement, jusqu'au premier
 * enregistrement de la page en admin.
 */
$help_cta_text = function_exists( 'get_field' ) ? get_field( 'help_cta_text' ) : null;

if ( null === $help_cta_text || false === $help_cta_text ) {
	$help_cta_text = "Notre équipe vous répond sous 48\u{00A0}heures ouvrées.";
}

$help_cta_text = trim( (string) $help_cta_text );
?>

<main id="main" class="centre-aide" data-component="centre-aide">

	<header class="centre-aide__hero">
		<div class="centre-aide__hero-wrap">
			<h1 class="centre-aide__title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="centre-aide__intro"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php else : ?>
				<p class="centre-aide__intro">Toutes les réponses à vos questions sur 180°C, l'abonnement aux recettes, les livres numériques et votre compte.</p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( ! $has_sections ) : ?>

		<div class="centre-aide__empty">
			<p>Le Centre d'aide est en cours de mise à jour. Revenez bientôt.</p>
		</div>

	<?php else : ?>

		<div class="centre-aide__layout">

			<aside class="centre-aide__toc" aria-label="Sommaire">
				<h2 class="centre-aide__toc-title">Sommaire</h2>
				<ol class="centre-aide__toc-list">
					<?php
					foreach ( $help_sections as $section ) :
						$section_id    = sanitize_title( $section['section_id'] );
						$section_title = $section['section_title'];
						?>
						<li class="centre-aide__toc-item">
							<a class="centre-aide__toc-link"
								href="#section-<?php echo esc_attr( $section_id ); ?>"
								data-toc-target="section-<?php echo esc_attr( $section_id ); ?>">
								<?php echo esc_html( $section_title ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ol>

				<?php
				/*
				 * Sondage — bouton pleine largeur sous le sommaire.
				 *
				 * Distinct du CTA « Une question sans réponse ? » du bas de page
				 * (.centre-aide__cta), qui mène au formulaire de contact : celui-ci
				 * sort du site vers le questionnaire. Absent tant qu'aucun sondage
				 * n'est en cours.
				 */
				$survey_url = function_exists( '_180c_survey_url' ) ? _180c_survey_url() : '';
				if ( '' !== $survey_url ) :
					?>
					<a class="btn btn--primary centre-aide__toc-survey"
						href="<?php echo esc_url( $survey_url ); ?>"
						target="_blank"
						rel="noopener">
						<?php esc_html_e( 'Votre avis sur le site', '180c' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( '(nouvel onglet)', '180c' ); ?></span>
					</a>
				<?php endif; ?>
			</aside>

			<div class="centre-aide__content">

				<?php
				foreach ( $help_sections as $section ) :
					$section_id    = sanitize_title( $section['section_id'] );
					$section_title = $section['section_title'];
					$questions     = ! empty( $section['section_questions'] ) ? $section['section_questions'] : array();
					if ( empty( $questions ) ) {
						continue;
					}
					?>
					<section class="centre-aide__section"
							id="section-<?php echo esc_attr( $section_id ); ?>"
							data-section-id="<?php echo esc_attr( $section_id ); ?>">

						<h2 class="centre-aide__section-title">
							<?php echo esc_html( $section_title ); ?>
						</h2>

						<ul class="centre-aide__questions" role="list">
							<?php
							foreach ( $questions as $question ) :
								$slug           = sanitize_title( $question['question_slug'] );
								$question_title = $question['question_title'];
								$answer         = $question['question_answer'];
								$trigger_id     = 'trigger-' . $slug;
								$answer_id      = 'answer-' . $slug;
								?>
								<li class="centre-aide__item"
									id="<?php echo esc_attr( $slug ); ?>"
									data-question-slug="<?php echo esc_attr( $slug ); ?>">
									<h3 class="centre-aide__item-heading">
										<button type="button"
												class="centre-aide__trigger"
												id="<?php echo esc_attr( $trigger_id ); ?>"
												aria-expanded="false"
												aria-controls="<?php echo esc_attr( $answer_id ); ?>">
											<span class="centre-aide__question-text"><?php echo esc_html( $question_title ); ?></span>
											<span class="centre-aide__icon" aria-hidden="true"></span>
										</button>
									</h3>
									<div class="centre-aide__answer"
										id="<?php echo esc_attr( $answer_id ); ?>"
										role="region"
										aria-labelledby="<?php echo esc_attr( $trigger_id ); ?>"
										hidden>
										<div class="centre-aide__answer-inner">
											<?php echo wp_kses_post( $answer ); ?>
										</div>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>

					</section>
				<?php endforeach; ?>

			</div>

		</div>

	<?php endif; ?>

	<aside class="centre-aide__cta" aria-labelledby="cta-help-title">
		<div class="centre-aide__cta-wrap">
			<h2 class="centre-aide__cta-title" id="cta-help-title">Une question sans réponse ?</h2>
			<?php if ( '' !== $help_cta_text ) : ?>
				<p class="centre-aide__cta-text"><?php echo esc_html( $help_cta_text ); ?></p>
			<?php endif; ?>
			<a class="centre-aide__cta-button" href="/contact/">Nous contacter</a>
		</div>
	</aside>

</main>

<?php
get_footer();
