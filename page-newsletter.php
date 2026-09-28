<?php
/**
 * Template Name: Newsletter
 * Template Post Type: page
 *
 * Page d'inscription newsletter — hero immersif éditable + formulaire
 * opt-in branché sur l'endpoint serveur /newsletter/public-subscribe (clé
 * Mailchimp jamais exposée). Sous la ligne de flottaison : bloc auteur,
 * rail des dernières recettes, bloc promo abonnement (non-abonnés).
 *
 * Thème classique (non FSE) : les « patterns » du brief sont rendus en sections
 * directes, sur le modèle de page-contact.php.
 * Données & visibilité : inc/newsletter-page.php. Styles : newsletter.css.
 * Soumission : src/js/modules/newsletter.js (fetch, sans rechargement).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="newsletter" data-component="newsletter">

	<section class="newsletter-hero">
		<div class="newsletter-hero__media">
			<?php
			// Image hero ACF (candidat LCP). Vide → fond de repli CSS.
			echo _180c_newsletter_hero_image_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() échappe déjà sa sortie.
			?>
		</div>

		<div class="newsletter-hero__content">
			<?php
			$_180c_nl_pid       = get_queried_object_id();
			$_180c_nl_title     = (string) _180c_acf( 'hero_title', $_180c_nl_pid, 'Chaque semaine, les recettes de Delphine dans votre boîte mail' );
			$_180c_nl_subtitle  = (string) _180c_acf( 'hero_subtitle', $_180c_nl_pid, 'Un avant-goût offert chaque semaine — l\'intégralité des recettes est réservée aux abonnés.' );
			$_180c_nl_secondary = (string) _180c_acf( 'hero_secondary', $_180c_nl_pid, 'En vous inscrivant, vous acceptez de recevoir la newsletter de 180°C. Désinscription à tout moment via le lien présent dans chaque email ; vos données ne sont jamais cédées à des tiers. Voir notre <a href="/politique-confidentialite/">Politique de confidentialité</a>.' );
			?>
			<h1 class="newsletter-hero__title"><?php echo esc_html( $_180c_nl_title ); ?></h1>
			<?php if ( '' !== $_180c_nl_subtitle ) : ?>
				<p class="newsletter-hero__subtitle"><?php echo esc_html( $_180c_nl_subtitle ); ?></p>
			<?php endif; ?>

			<form class="newsletter-form newsletter-hero__form" data-newsletter-form data-ga-location="page" novalidate>
				<label class="newsletter-form__label" for="nl-email">Votre adresse email</label>

				<div class="newsletter-form__row">
					<input class="newsletter-form__input" id="nl-email" name="email" type="email"
						autocomplete="email" inputmode="email" required
						aria-describedby="nl-feedback" aria-invalid="false" placeholder="vous@exemple.fr">
					<button class="newsletter-form__submit" type="submit" data-newsletter-submit aria-busy="false">Je m'inscris</button>
				</div>

				<?php if ( '' !== trim( $_180c_nl_secondary ) ) : ?>
					<div class="newsletter-form__secondary">
						<?php echo wp_kses_post( $_180c_nl_secondary ); ?>
					</div>
				<?php endif; ?>

				<?php // Honeypot : hors flux, masqué a11y. ?>
				<div class="newsletter-form__gotcha" aria-hidden="true">
					<label for="nl-gotcha">Laissez ce champ vide</label>
					<input id="nl-gotcha" name="_gotcha" type="text" tabindex="-1" autocomplete="off">
				</div>

				<?php
				// Nonce nommé `nl_nonce` (et non `_wpnonce`, réservé par l'API REST WP).
				// Champ `nl_nonce` volontairement VIDE : cette page est servie depuis le cache
				// statique de WP Super Cache, qui survit à l'expiration du nonce. Le JS le
				// remplit avec une valeur fraîche avant le POST (`fetchActionNonce()`,
				// src/js/modules/session.js). Le champ reste dans le DOM car c'est lui que
				// le JS cible ; sans JS il part vide, cas déjà prévu côté serveur
				// (inc/rest/newsletter.php : honeypot + rate-limit + validation).
				?>
				<input type="hidden" name="nl_nonce" value="">

				<p class="newsletter-form__feedback" id="nl-feedback" role="status" aria-live="polite" aria-atomic="true" hidden></p>
			</form>
		</div>
	</section>

	<?php
	// ============================================================
	// Bloc auteur — dynamique, masqué si l'ID n'est pas configuré.
	// Traitement visuel aligné sur la bio auteur du single (cf. single-recipe.php).
	// Photo : champ ACF « Photo de l'auteur » (author_photo) ; repli sur le
	// Gravatar uniquement si le champ n'est pas renseigné.
	// ============================================================
	$_180c_nl_author_id = _180c_newsletter_author_id();
	if ( $_180c_nl_author_id > 0 ) :
		$_180c_nl_first = get_the_author_meta( 'first_name', $_180c_nl_author_id );
		$_180c_nl_last  = get_the_author_meta( 'last_name', $_180c_nl_author_id );
		$_180c_nl_name  = trim( $_180c_nl_first . ' ' . $_180c_nl_last );
		if ( '' === $_180c_nl_name ) {
			$_180c_nl_name = get_the_author_meta( 'display_name', $_180c_nl_author_id );
		}
		$_180c_nl_bio   = get_the_author_meta( 'description', $_180c_nl_author_id );
		$_180c_nl_url   = get_author_posts_url( $_180c_nl_author_id ) . '#recettes';
		$_180c_nl_photo = function_exists( '_180c_author_avatar_url' )
			? _180c_author_avatar_url( $_180c_nl_author_id, 320 )
			: '';
		?>
		<section class="newsletter-author site-container" aria-label="<?php esc_attr_e( 'À propos de l\'auteur', '180c' ); ?>">
			<figure class="newsletter-author__media">
				<?php if ( '' !== $_180c_nl_photo ) : ?>
					<img
						class="newsletter-author__photo"
						src="<?php echo esc_url( $_180c_nl_photo ); ?>"
						alt="<?php echo esc_attr( $_180c_nl_name ); ?>"
						width="160"
						height="160"
						loading="lazy"
						decoding="async"
					/>
				<?php else : ?>
					<?php echo get_avatar( $_180c_nl_author_id, 160, '', $_180c_nl_name, array( 'class' => 'newsletter-author__photo' ) ); ?>
				<?php endif; ?>
			</figure>
			<div class="newsletter-author__text">
				<h2 class="newsletter-author__name"><?php echo esc_html( $_180c_nl_name ); ?></h2>
				<?php if ( $_180c_nl_bio ) : ?>
					<p class="newsletter-author__bio"><?php echo esc_html( $_180c_nl_bio ); ?></p>
				<?php endif; ?>
				<a class="newsletter-author__link" href="<?php echo esc_url( $_180c_nl_url ); ?>">Voir ses recettes</a>
			</div>
		</section>
	<?php endif; ?>

	<?php
	// ============================================================
	// Rail « Un avant-goût » — dernières recettes (rail mobile → grille ≥ md).
	// Réutilise parts/recipe-card.php (variante md). Masqué si aucune recette.
	// ============================================================
	$_180c_nl_recipes = _180c_newsletter_recent_recipes_query();
	if ( $_180c_nl_recipes->have_posts() ) :
		?>
		<section class="newsletter-recipes site-container" aria-label="<?php esc_attr_e( 'Dernières recettes', '180c' ); ?>">
			<h2 class="newsletter-recipes__title">Un avant-goût</h2>
			<ul class="newsletter-recipes__rail" role="list">
				<?php
				while ( $_180c_nl_recipes->have_posts() ) :
					$_180c_nl_recipes->the_post();
					?>
					<li class="newsletter-recipes__item">
						<?php
						get_template_part(
							'parts/recipe-card',
							null,
							array(
								'recipe_id' => get_the_ID(),
								'size'      => 'md',
							)
						);
						?>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php
			// Lien vers la page Recettes éditoriale (Home Builder) — /recettes/ —
			// et non l'archive CPT (/toutes-les-recettes/). Même résolution que le
			// rail recettes de la home.
			$_180c_nl_recettes     = get_page_by_path( 'recettes' );
			$_180c_nl_recettes_url = $_180c_nl_recettes instanceof WP_Post
				? get_permalink( $_180c_nl_recettes )
				: home_url( '/recettes/' );
			?>
			<a class="newsletter-recipes__all" href="<?php echo esc_url( $_180c_nl_recettes_url ); ?>">Voir toutes les recettes</a>
		</section>
		<?php
		wp_reset_postdata();
	endif;
	?>

	<?php
	// ============================================================
	// Bloc abonnement — composant Home Builder `subscription_banner` réutilisé à
	// l'identique (parts/modules/subscription_banner.php), alimenté par les champs
	// ACF de la page. Le partial gère lui-même la visibilité (hide_for_subscribers
	// + _180c_is_recipe_subscriber()).
	//
	// Fallback : si les nouveaux champs `newsletter_sub_*` sont vides, on reprend
	// les anciennes postmeta `newsletter_promo_*` (champs ACF retirés mais meta
	// conservées) — reprise du contenu promo existant sans rien perdre.
	// ============================================================

	/**
	 * Valeur du nouveau champ bannière, sinon repli sur l'ancienne postmeta promo.
	 *
	 * @param string $new_key Champ ACF `newsletter_sub_*`.
	 * @param string $old_key Ancienne meta `newsletter_promo_*` (repli).
	 * @return string
	 */
	$_180c_nl_sub = static function ( $new_key, $old_key ) use ( $_180c_nl_pid ) {
		$value = (string) _180c_acf( $new_key, $_180c_nl_pid, '' );
		return '' !== $value ? $value : (string) get_post_meta( $_180c_nl_pid, $old_key, true );
	};

	get_template_part(
		'parts/modules/subscription_banner',
		null,
		array(
			'title'                => $_180c_nl_sub( 'newsletter_sub_title', 'newsletter_promo_title' ),
			'description'          => $_180c_nl_sub( 'newsletter_sub_description', 'newsletter_promo_subtitle' ),
			'cta_label'            => $_180c_nl_sub( 'newsletter_sub_cta_label', 'newsletter_promo_cta_label' ),
			'cta_url'              => $_180c_nl_sub( 'newsletter_sub_cta_url', 'newsletter_promo_cta_url' ),
			'secondary_link_label' => (string) _180c_acf( 'newsletter_sub_secondary_link_label', $_180c_nl_pid, '' ),
			'secondary_link_url'   => (string) _180c_acf( 'newsletter_sub_secondary_link_url', $_180c_nl_pid, '' ),
			'hide_for_subscribers' => (bool) _180c_acf( 'newsletter_sub_hide_for_subscribers', $_180c_nl_pid, true ),
		)
	);
	?>

</main>

<?php
get_footer();
