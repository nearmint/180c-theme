<?php
/**
 * Template fiche recette — rendu server-side.
 *
 * Aligné sur le modèle ACF (group_recipe_fields.json) :
 *   recipe_intro, servings/servings_unit, recipe_is_premium,
 *   ingredients_groups → items → line, steps → step_title/step_content,
 *   source_issue. Taxonomies : recipe_category, recipe_season, recipe_publication,
 *   recipe_tag.
 *
 * Structure :
 *  1. Hero (méta type·saison, titre, intro, byline auteur, image LCP eager)
 *  2. Facts strip (portions + scaling ; temps/difficulté/coût si renseignés)
 *  3a. Accès complet : ingrédients (scalables) + étapes + compléments
 *  3b. Sinon : paywall server-side (parts/recipe-paywall.php)
 *  3c. Source / numéro d'origine (toujours — hors paywall)
 *  4. Tags (toujours)        5. Bio auteur (toujours)
 *  6. Recettes liées (toujours)
 *
 * Le JSON-LD Recipe est injecté dans le @graph global (inc/seo/schema.php).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="site-main site-container">
<?php
while ( have_posts() ) :
	the_post();

	$recipe_id   = get_the_ID();
	$has_access  = _180c_user_has_recipe_access( $recipe_id );
	$access_mode = $has_access ? 'full' : 'preview';

	// --- Champs ACF (modèle). -------------------------------------
	$intro              = function_exists( 'get_field' ) ? get_field( 'recipe_intro', $recipe_id ) : '';
	$servings           = function_exists( 'get_field' ) ? (int) get_field( 'servings', $recipe_id ) : 0;
	$servings_unit      = function_exists( 'get_field' ) ? (string) get_field( 'servings_unit', $recipe_id ) : '';
	$ingredients_groups = function_exists( 'get_field' ) ? get_field( 'ingredients_groups', $recipe_id ) : array();
	$steps              = function_exists( 'get_field' ) ? get_field( 'steps', $recipe_id ) : array();
	$source_issue       = function_exists( 'get_field' ) ? get_field( 'source_issue', $recipe_id ) : null;

	// --- Champs méta optionnels (hors — rendus seulement si alimentés). ---
	// prep/cook/rest/difficulté/coût ne sont plus rendus côté template
	// (la facts-strip a été retirée v3.2). Ils restent lus par
	// le JSON-LD Recipe (inc/recipe-schema.php) directement depuis ACF.
	$chef_notes   = function_exists( 'get_field' ) ? get_field( 'recipe_chef_notes', $recipe_id ) : '';
	$wine_pairing = function_exists( 'get_field' ) ? (string) get_field( 'recipe_wine_pairing', $recipe_id ) : '';
	$source_revue = function_exists( 'get_field' ) ? (string) get_field( 'recipe_source_revue', $recipe_id ) : '';

	// --- Taxonomies pour le hero (type de plat · saison). ------------
	$type_terms   = get_the_terms( $recipe_id, 'recipe_category' );
	$type_term    = ( $type_terms && ! is_wp_error( $type_terms ) ) ? $type_terms[0] : null;
	$season_terms = get_the_terms( $recipe_id, 'recipe_season' );
	$season_term  = ( $season_terms && ! is_wp_error( $season_terms ) ) ? $season_terms[0] : null;

	// --- Auteur. ---------------------------------------------------------
	$author_id    = (int) get_post_field( 'post_author', $recipe_id );
	$author_name  = get_the_author_meta( 'display_name', $author_id );
	$author_first = get_the_author_meta( 'first_name', $author_id );
	$author_url   = get_author_posts_url( $author_id );
	$author_bio   = get_the_author_meta( 'description', $author_id );
	?>

	<article
		class="recipe recipe--<?php echo esc_attr( $access_mode ); ?>"
		id="recipe-<?php echo esc_attr( $recipe_id ); ?>"
		itemscope
		itemtype="https://schema.org/Recipe"
	>

		<!-- =============================================================
		1. HERO
		============================================================= -->
		<header class="recipe-hero">
			<?php
			if ( has_post_thumbnail( $recipe_id ) ) :
				$thumb_id      = (int) get_post_thumbnail_id( $recipe_id );
				$thumb_caption = $thumb_id ? trim( (string) wp_get_attachment_caption( $thumb_id ) ) : '';
				$lightbox_src  = $thumb_id ? (string) wp_get_attachment_image_url( $thumb_id, 'large' ) : '';
				?>
				<figure
					class="recipe-hero__figure"
					data-lightbox-src="<?php echo esc_url( $lightbox_src ); ?>"
					data-caption="<?php echo esc_attr( $thumb_caption ); ?>"
				>
					<?php
					the_post_thumbnail(
						'full',
						array(
							'class'         => 'recipe-hero__image',
							'loading'       => 'eager',
							'decoding'      => 'async',
							'fetchpriority' => 'high',
							'itemprop'      => 'image',
							/*
							Hero = 25vw au desktop (col 1/4), pleine largeur ailleurs.
								Le srcset WP fournira les candidates medium/large adaptées. */
							'sizes'         => '(min-width: 1024px) 25vw, 100vw',
						)
					);
					?>
					<button type="button" class="recipe-hero__zoom overlay-icon-btn" aria-label="<?php esc_attr_e( 'Agrandir l’image', '180c' ); ?>" data-lightbox-trigger>
						<span class="recipe-hero__zoom-icon" aria-hidden="true"><?php echo _180c_render_svg_icon( 'expand' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</button>
				</figure>
			<?php endif; ?>

			<div class="recipe-hero__content">
				<?php
				// En-tête éditorial via module partagé (entry-header). Le markup
				// `.recipe-hero__content` reste la cellule de grille / le wrapper
				// de padding desktop ; seule la typographie est mutualisée.
				$recipe_eyebrow = array();
				if ( $type_term ) {
					$recipe_eyebrow[] = array(
						'label' => $type_term->name,
						'url'   => get_term_link( $type_term ),
					);
				}
				if ( $season_term ) {
					$recipe_eyebrow[] = array(
						'label' => $season_term->name,
						'url'   => get_term_link( $season_term ),
					);
				}

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML produit par le renderer (échappement interne).
				echo _180c_render_entry_header(
					array(
						'eyebrow'    => $recipe_eyebrow,
						'title'      => get_the_title( $recipe_id ),
						'standfirst' => $intro,
						'byline'     => array(
							'name' => $author_name,
							// Conserve l'ancrage #recettes (comportement d'origine).
							'url'  => $author_url . '#recettes',
						),
					)
				);
				?>
			</div>
		</header>

		<!-- =============================================================
		1bis. ACTIONS (partage + favoris) — module partagé share-actions
		============================================================= -->
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML produit par le renderer (échappement interne).
		echo _180c_render_share_actions(
			array(
				'url'                  => get_permalink( $recipe_id ),
				'title'                => get_the_title( $recipe_id ),
				'show_favorite'        => true,
				'favorite_recipe_id'   => $recipe_id,
				'show_print'           => true,
				// Imprimer + carnet réservés aux abonnés (désactivés sinon).
				'actions_disabled'     => ! $has_access,
				// Le carnet exige une session : bouton désactivé hors connexion.
				// Jamais désactivé sur l'état de connexion : un bouton `disabled`
				// n'est pas focusable, ce qui rendait le popover « carnet » et son
				// CTA d'abonnement inatteignables au clavier. Déconnecté, le clic
				// redirige vers /connexion/ (src/js/modules/favorites.js).
				// `actions_disabled` (recette premium verrouillée) reste, lui, en
				// vigueur : c'est un état de contenu, pas d'authentification.
				'favorite_disabled'    => false,
				'aria_label'           => __( 'Partager cette recette', '180c' ),
				// Compose .recipe-section .recipe-actions sur la <section> :
				// - .recipe-section pose le padding-block (cf. recipe.css)
				// - .recipe-actions retire la border-top (override local)
				'compat_section_class' => 'recipe-section recipe-actions',
				// Preserve l'alignement horizontal avec les autres sections
				// recette (.container-180c sur la <div> interne — historique).
				'compat_inner_class'   => 'container-180c recipe-actions__inner',
				'compat_nav_class'     => 'recipe-share',
			)
		);
		?>

		<?php if ( $has_access ) : ?>

			<!-- =========================================================
			3a. INGRÉDIENTS
			========================================================= -->
			<?php if ( ! empty( $ingredients_groups ) ) : ?>
				<?php
				$ingredients_title = __( 'Ingrédients', '180c' );
				if ( $servings > 0 ) {
					$ingredients_title .= ' ' . sprintf(
						/* translators: %1$s: nombre de portions, %2$s: unité (personnes, parts…). */
						__( 'pour %1$s %2$s', '180c' ),
						$servings,
						$servings_unit
					);
				}
				?>
				<section class="recipe-section recipe-ingredients" aria-label="<?php esc_attr_e( 'Ingrédients', '180c' ); ?>">
					<div class="container-180c">
						<h2 class="recipe-section__title"><?php echo esc_html( $ingredients_title ); ?></h2>

						<?php
						foreach ( $ingredients_groups as $group ) :
							$group_label = isset( $group['group_label'] ) ? $group['group_label'] : '';
							$items       = isset( $group['items'] ) ? $group['items'] : array();
							if ( empty( $items ) ) {
								continue;
							}
							?>
							<div class="recipe-ingredients__group">
								<?php if ( $group_label ) : ?>
									<h3 class="recipe-ingredients__group-title"><?php echo esc_html( $group_label ); ?></h3>
								<?php endif; ?>
								<ul class="recipe-ingredients__list" role="list">
									<?php
									foreach ( $items as $item ) :
										$line = isset( $item['line'] ) ? (string) $item['line'] : '';
										if ( '' === trim( $line ) ) {
											continue;
										}
										$parsed = _180c_recipe_parse_ingredient_line( $line );
										?>
										<li class="recipe-ingredients__item" itemprop="recipeIngredient">
											<span class="recipe-ingredients__name">
												<?php if ( '' !== $parsed['qty_label'] ) : ?>
													<strong class="recipe-ingredients__quantity"><?php echo esc_html( $parsed['qty_label'] ); ?></strong>
													<?php echo esc_html( $parsed['rest'] ); ?>
												<?php else : ?>
													<?php echo esc_html( $line ); ?>
												<?php endif; ?>
											</span>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<!-- =========================================================
			4a. ÉTAPES
			========================================================= -->
			<?php if ( ! empty( $steps ) ) : ?>
				<section class="recipe-section recipe-steps" aria-label="<?php esc_attr_e( 'Étapes de préparation', '180c' ); ?>">
					<div class="container-180c">
						<h2 class="recipe-section__title"><?php esc_html_e( 'Préparation', '180c' ); ?></h2>
						<ol class="recipe-steps__list">
							<?php
							$step_index = 0;
							foreach ( $steps as $step ) :
								$step_content = isset( $step['step_content'] ) ? $step['step_content'] : '';
								if ( '' === trim( wp_strip_all_tags( (string) $step_content ) ) ) {
									continue;
								}
								++$step_index;
								$step_title = isset( $step['step_title'] ) ? $step['step_title'] : '';
								$step_timer = isset( $step['step_timer'] ) ? (int) $step['step_timer'] : 0;
								?>
								<li class="recipe-step" itemprop="recipeInstructions" itemscope itemtype="https://schema.org/HowToStep">
									<div class="recipe-step__header">
										<span class="recipe-step__number" aria-hidden="true"><?php echo esc_html( $step_index ); ?></span>
										<span class="sr-only">
											<?php
											printf(
												/* translators: %d: numéro de l'étape */
												esc_html__( 'Étape %d', '180c' ),
												(int) $step_index
											);
											?>
										</span>
									</div>
									<div class="recipe-step__body">
										<?php if ( $step_title ) : ?>
											<h3 class="recipe-step__title" itemprop="name"><?php echo esc_html( $step_title ); ?></h3>
										<?php endif; ?>
										<div class="recipe-step__content" itemprop="text">
											<?php echo wp_kses_post( $step_content ); ?>
										</div>
										<?php if ( $step_timer > 0 ) : ?>
											<button type="button" class="recipe-step__timer" data-action="start-timer" data-timer-minutes="<?php echo esc_attr( $step_timer ); ?>">
												<?php
												printf(
													/* translators: %d: durée en minutes */
													esc_html__( '⏱ Démarrer un timer de %d min', '180c' ),
													(int) $step_timer
												);
												?>
											</button>
										<?php endif; ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ol>
					</div>
				</section>
			<?php endif; ?>

			<!-- =========================================================
			5a. LE MOT DE LA RÉDACTION + ACCORD VIN
			========================================================= -->
			<?php if ( $chef_notes || $wine_pairing ) : ?>
				<section class="recipe-section recipe-extras" aria-label="<?php esc_attr_e( 'Conseils et accords', '180c' ); ?>">
					<div class="container-180c">
						<div class="recipe-extras__grid">
							<?php if ( $chef_notes ) : ?>
								<div class="recipe-extras__card recipe-extras__card--tip">
									<h3 class="recipe-extras__card-title"><?php esc_html_e( 'Le mot de la rédaction', '180c' ); ?></h3>
									<div class="recipe-extras__card-content"><?php echo wp_kses_post( $chef_notes ); ?></div>
								</div>
							<?php endif; ?>
							<?php if ( $wine_pairing ) : ?>
								<div class="recipe-extras__card recipe-extras__card--wine">
									<h3 class="recipe-extras__card-title"><?php esc_html_e( 'À boire avec', '180c' ); ?></h3>
									<p class="recipe-extras__card-content"><?php echo esc_html( $wine_pairing ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

		<?php else : ?>

			<!-- =========================================================
			3b. PAYWALL (server-side, non abonné)
			========================================================= -->
			<?php get_template_part( 'parts/recipe-paywall' ); ?>

		<?php endif; ?>

		<!-- =============================================================
		3c. SOURCE (numéro d'origine + mention revue) — toujours visible
		============================================================= -->
		<?php
		// Bloc « source » mutualisé recette + article (cf.
		// inc/source-product.php). Markup strictement identique à
		// l'ancien rendu inline recipe-source : titre fixe + carte
		// produit, précédés de la mention revue libre si renseignée.
		//
		// Hors du test `$has_access` : la provenance (numéro, cahier, livre)
		// n'est pas du contenu payant, c'est une mise en avant boutique — elle
		// doit rester visible aux non-abonnés comme aux visiteurs non logués.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML produit par le renderer (échappement interne).
		echo _180c_render_source_product(
			$source_issue,
			array( 'mention' => $source_revue )
		);
		?>

		<!-- =============================================================
		4. TAGS (toujours visible)
		============================================================= -->
		<?php
		$chip_terms = array();
		foreach ( array( 'recipe_category', 'recipe_season', 'recipe_tag' ) as $chip_tax ) {
			$chip_tax_terms = get_the_terms( $recipe_id, $chip_tax );
			if ( $chip_tax_terms && ! is_wp_error( $chip_tax_terms ) ) {
				$chip_terms = array_merge( $chip_terms, $chip_tax_terms );
			}
		}
		if ( ! empty( $chip_terms ) ) :
			?>
			<footer class="recipe-section recipe-tags" aria-label="<?php esc_attr_e( 'Mots-clés', '180c' ); ?>">
				<div class="container-180c">
					<ul class="recipe-tags__list" role="list">
						<?php
						foreach ( $chip_terms as $chip_term ) :
							$chip_term_link = get_term_link( $chip_term );
							if ( is_wp_error( $chip_term_link ) ) {
								continue;
							}
							?>
							<li>
								<a class="recipe-tags__chip" href="<?php echo esc_url( $chip_term_link ); ?>">
									<?php echo esc_html( $chip_term->name ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</footer>
		<?php endif; ?>

		<!-- =============================================================
		5. BIO AUTEUR (toujours visible)
		============================================================= -->
		<?php if ( $author_bio ) : ?>
			<?php
			// Avatar éditorial uniquement (champ ACF `author_photo`). Pas de
			// silhouette Gravatar par défaut : si vide, le bloc se rend sans
			// média.
			$bio_avatar_url = function_exists( '_180c_author_avatar_url' )
				? _180c_author_avatar_url( $author_id, 200 )
				: '';
			?>
			<aside class="recipe-section recipe-author-bio<?php echo $bio_avatar_url ? '' : ' recipe-author-bio--no-photo'; ?>" aria-label="<?php esc_attr_e( 'À propos de l\'auteur', '180c' ); ?>">
				<div class="container-180c recipe-author-bio__inner">
					<?php if ( '' !== $bio_avatar_url ) : ?>
						<img
							class="recipe-author-bio__avatar"
							src="<?php echo esc_url( $bio_avatar_url ); ?>"
							alt=""
							width="72"
							height="72"
							loading="lazy"
							decoding="async"
						/>
					<?php endif; ?>
					<div class="recipe-author-bio__body">
						<h2 class="recipe-author-bio__name">
							<?php
							printf(
								/* translators: %s: lien vers l'auteur */
								esc_html__( 'Écrit par %s', '180c' ),
								sprintf( '<a href="%1$s">%2$s</a>', esc_url( $author_url ), esc_html( $author_name ) )
							);
							?>
						</h2>
						<p class="recipe-author-bio__desc"><?php echo esc_html( $author_bio ); ?></p>
						<a class="link-arrow recipe-author-bio__more" href="<?php echo esc_url( $author_url ) . '#recettes'; ?>">
							<?php
							printf(
								/* translators: %s: prénom de l'auteur */
								esc_html__( 'Plus de recettes de %s', '180c' ),
								esc_html( $author_first ? $author_first : $author_name )
							);
							?>
						</a>
					</div>
				</div>
			</aside>
		<?php endif; ?>

		<!-- =============================================================
		6. RECETTES RECOMMANDÉES (même recipe_category, randomisées)
		============================================================= -->
		<?php
		require_once get_template_directory() . '/inc/blocks/_helpers.php';

		/*
		 * Pool : recettes partageant au moins un terme `recipe_category` avec la
		 * recette courante. Exclusion de l'ID courant + des recettes déjà
		 * affichées sur la page (accumulateur Home Builder mutualisé). Tri
		 * aléatoire (orderby rand) pour varier les recommandations à chaque
		 * affichage ; 4 cartes, requête bornée (jamais -1). Repli sur les
		 * recettes récentes si le pool catégoriel est vide.
		 */
		$related_count   = 10;
		$related_exclude = array_merge( array( $recipe_id ), _180c_displayed_recipe_ids() );
		$cat_ids         = wp_get_post_terms( $recipe_id, 'recipe_category', array( 'fields' => 'ids' ) );

		$related_args = array(
			'post_type'           => 'recipe',
			'post_status'         => 'publish',
			'posts_per_page'      => $related_count,
			'post__not_in'        => $related_exclude,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'rand',
		);
		if ( ! is_wp_error( $cat_ids ) && ! empty( $cat_ids ) ) {
			$related_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'recipe_category',
					'field'    => 'term_id',
					'terms'    => $cat_ids,
				),
			);
		}

		$related_query = new WP_Query( $related_args );

		// Repli : recettes récentes si aucune recette ne partage de catégorie.
		if ( ! $related_query->have_posts() ) {
			$related_query = new WP_Query(
				array(
					'post_type'           => 'recipe',
					'post_status'         => 'publish',
					'posts_per_page'      => $related_count,
					'post__not_in'        => $related_exclude,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'orderby'             => 'date',
					'order'               => 'DESC',
				)
			);
		}

		$related_items = array();
		while ( $related_query->have_posts() ) {
			$related_query->the_post();
			$related_card = _180c_block_render_recipe_card( get_post(), 'sm' );
			if ( $related_card ) {
				$related_items[] = $related_card;
			}
		}
		wp_reset_postdata();

		if ( ! empty( $related_items ) ) {
			$recettes_page    = get_page_by_path( 'recettes' );
			$related_view_all = $recettes_page instanceof WP_Post ? get_permalink( $recettes_page ) : home_url( '/recettes/' );

			_180c_render_rail(
				array(
					'title'        => __( 'Vous aimerez aussi', '180c' ),
					'items_html'   => $related_items,
					'view_all_url' => $related_view_all,
					'region_label' => __( 'Vous aimerez aussi', '180c' ),
					'modifier'     => 'recipes_rail',
				)
			);
		}
		?>

	</article>

<?php endwhile; ?>
</main>

<?php
get_footer();
