<?php
/**
 * Template archive recettes.
 *
 * Deux expériences distinctes selon le contexte :
 *
 *  - Archive du post type (`/toutes-les-recettes/`, !is_tax) : page « explore »
 *    avec titre, barre de filtres sticky (recherche plein texte + chips des
 *    taxonomies recipe_category / recipe_season / recipe_publication — étiquettes
 *    exclues) et scroll infini sans pagination. La 1re page est rendue côté
 *    serveur (SEO + LCP) ; le module JS `recipes-archive.js` prend le relais via
 *    l'endpoint REST `/wp-json/180c/v1/recipes`.
 *
 *  - Archives de taxonomie (is_tax sur recipe_category/season/publication) : liste
 *    filtrée classique avec liens de filtre et pagination native.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_tax() ) :
	// ===================================================================
	// ARCHIVE DE TAXONOMIE — liens de filtre + pagination native.
	// ===================================================================
	$queried_term   = get_queried_object();
	$archive_title  = single_term_title( '', false );
	$archive_desc   = term_description();
	$active_term_id = isset( $queried_term->term_id ) ? (int) $queried_term->term_id : 0;

	$season_terms = get_terms(
		array(
			'taxonomy'   => 'recipe_season',
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	$type_terms   = get_terms(
		array(
			'taxonomy'   => 'recipe_category',
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	?>

	<main id="main" class="site-main site-container recipe-archive">

		<header class="archive-header">
			<div class="container-180c">
				<div class="archive-header__bar">
					<h1 class="archive-header__title">
						<?php echo esc_html( $archive_title ); ?>
					</h1>
				</div>
				<?php if ( $archive_desc ) : ?>
					<div class="archive-header__desc">
						<?php echo wp_kses_post( $archive_desc ); ?>
					</div>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( ( ! is_wp_error( $season_terms ) && ! empty( $season_terms ) ) || ( ! is_wp_error( $type_terms ) && ! empty( $type_terms ) ) ) : ?>
			<nav class="recipe-filters" aria-label="<?php esc_attr_e( 'Filtrer les recettes', '180c' ); ?>">
				<div class="container-180c">

					<?php if ( ! is_wp_error( $season_terms ) && ! empty( $season_terms ) ) : ?>
						<div class="recipe-filters__group">
							<span class="recipe-filters__group-label"><?php esc_html_e( 'Saison', '180c' ); ?></span>
							<ul class="recipe-filters__list" role="list">
								<?php foreach ( $season_terms as $season_term ) : ?>
									<li class="recipe-filters__item" role="listitem">
										<a
											class="recipe-filters__link<?php echo ( $active_term_id === (int) $season_term->term_id ) ? ' recipe-filters__link--active' : ''; ?>"
											href="<?php echo esc_url( get_term_link( $season_term ) ); ?>"
											<?php echo ( $active_term_id === (int) $season_term->term_id ) ? 'aria-current="page"' : ''; ?>
										>
											<?php echo esc_html( $season_term->name ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<?php if ( ! is_wp_error( $type_terms ) && ! empty( $type_terms ) ) : ?>
						<div class="recipe-filters__group">
							<span class="recipe-filters__group-label"><?php esc_html_e( 'Type de plat', '180c' ); ?></span>
							<ul class="recipe-filters__list" role="list">
								<?php foreach ( $type_terms as $type_term ) : ?>
									<li class="recipe-filters__item" role="listitem">
										<a
											class="recipe-filters__link<?php echo ( $active_term_id === (int) $type_term->term_id ) ? ' recipe-filters__link--active' : ''; ?>"
											href="<?php echo esc_url( get_term_link( $type_term ) ); ?>"
											<?php echo ( $active_term_id === (int) $type_term->term_id ) ? 'aria-current="page"' : ''; ?>
										>
											<?php echo esc_html( $type_term->name ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<div class="recipe-filters__reset">
						<a
							class="recipe-filters__link recipe-filters__link--reset"
							href="<?php echo esc_url( get_post_type_archive_link( 'recipe' ) ); ?>"
						>
							<?php esc_html_e( 'Toutes les recettes', '180c' ); ?>
						</a>
					</div>

				</div>
			</nav>
		<?php endif; ?>

		<div class="container-180c">
			<?php if ( have_posts() ) : ?>
				<ul class="recipes-grid" role="list">
					<?php
					while ( have_posts() ) :
						the_post();
						$card_html = _180c_render_recipe_card( get_the_ID() );
						if ( ! $card_html ) {
							continue;
						}
						?>
						<li class="recipes-grid__item" role="listitem">
							<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</li>
					<?php endwhile; ?>
				</ul>

				<?php
				the_posts_pagination(
					array(
						'prev_text'          => __( '&larr; Précédent', '180c' ),
						'next_text'          => __( 'Suivant &rarr;', '180c' ),
						'aria_label'         => __( 'Navigation des pages de recettes', '180c' ),
						'screen_reader_text' => __( 'Navigation', '180c' ),
					)
				);
				?>
			<?php else : ?>
				<div class="recipes-empty">
					<p class="recipes-empty__msg">
						<?php esc_html_e( 'Aucune recette trouvée pour ces critères.', '180c' ); ?>
					</p>
					<a class="btn btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'recipe' ) ); ?>">
						<?php esc_html_e( 'Voir toutes les recettes', '180c' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

	</main>

	<?php
	get_footer();
	return;
endif;

// =======================================================================
// ARCHIVE DU POST TYPE — explore : filtres dynamiques + scroll infini.
// =======================================================================
// Titre forcé « Toutes les recettes » (le label du CPT est « Recettes » ; cette
// surface d'exploration s'intitule « Toutes les recettes », cohérent avec son
// slug /toutes-les-recettes/ et le CTA depuis la page « Recettes »).
$archive_title = __( 'Toutes les recettes', '180c' );
$archive_desc  = get_the_post_type_description();

// Groupes de filtres : libellé + termes (étiquettes recipe_tag exclues).
$filter_groups = array(
	'category' => array(
		'taxonomy' => 'recipe_publication',
		'label'    => __( 'Publication', '180c' ),
	),
	'season'   => array(
		'taxonomy' => 'recipe_season',
		'label'    => __( 'Saison', '180c' ),
	),
	'type'     => array(
		'taxonomy' => 'recipe_category',
		'label'    => __( 'Type de plat', '180c' ),
	),
);
foreach ( $filter_groups as $filter_key => $filter_group ) {
	$group_terms                           = get_terms(
		array(
			'taxonomy'   => $filter_group['taxonomy'],
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	$filter_groups[ $filter_key ]['terms'] = ( ! is_wp_error( $group_terms ) ) ? $group_terms : array();
}

// Rendu serveur de la 1re page (aucun filtre actif) — SEO + LCP.
$initial_query = new WP_Query( _180c_recipes_archive_query_args( array(), 1 ) );
$has_more      = (int) $initial_query->max_num_pages > 1;
$total         = (int) $initial_query->found_posts;
?>

<main id="main" class="site-main site-container recipe-archive recipe-archive--explore" data-recipes-archive>

	<header class="archive-header">
		<div class="container-180c">
			<div class="archive-header__bar">
				<h1 class="archive-header__title">
					<?php echo esc_html( $archive_title ); ?>
				</h1>
				<a
					class="btn btn--ghost archive-header__action"
					href="<?php echo esc_url( home_url( '/mon-carnet/' ) ); ?>"
				>
					<?php esc_html_e( 'Mon carnet de recettes', '180c' ); ?>
				</a>
			</div>
			<?php if ( $archive_desc ) : ?>
				<div class="archive-header__desc">
					<?php echo wp_kses_post( $archive_desc ); ?>
				</div>
			<?php endif; ?>
		</div>
	</header>

	<!-- ================================================================
	BARRE DE FILTRES STICKY (recherche + dropdowns multi-select)
	Recherche + filtres tiennent sur une seule ligne (desktop).
	================================================================ -->
	<form
		class="recipe-filters recipe-filters--explore recipe-filters--collapsed"
		data-recipes-filters
		role="search"
		aria-label="<?php esc_attr_e( 'Filtrer les recettes', '180c' ); ?>"
		onsubmit="return false;"
	>
		<div class="container-180c">
			<div class="recipe-filters__bar">

				<div class="recipe-filters__group--search">
					<label class="screen-reader-text" for="recipes-search">
						<?php esc_html_e( 'Rechercher une recette par mot-clé', '180c' ); ?>
					</label>
					<input
						type="search"
						id="recipes-search"
						class="recipe-filters__search"
						data-recipes-search
						placeholder="<?php esc_attr_e( 'Rechercher une recette…', '180c' ); ?>"
						autocomplete="off"
					/>
				</div>

				<?php
				foreach ( $filter_groups as $filter_key => $filter_group ) :
					if ( empty( $filter_group['terms'] ) ) {
						continue;
					}
					$panel_id = 'recipes-dd-panel-' . $filter_key;
					?>
					<div
						class="recipe-filters__dropdown"
						data-recipes-dropdown
						data-filter-group="<?php echo esc_attr( $filter_key ); ?>"
					>
						<button
							type="button"
							class="recipe-filters__dropdown-toggle"
							data-recipes-dropdown-toggle
							aria-expanded="false"
							aria-haspopup="true"
							aria-controls="<?php echo esc_attr( $panel_id ); ?>"
						>
							<span class="recipe-filters__dropdown-label"><?php echo esc_html( $filter_group['label'] ); ?></span>
							<span class="recipe-filters__dropdown-count" data-recipes-dropdown-count hidden></span>
							<svg class="recipe-filters__dropdown-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
								<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
						</button>
						<div
							class="recipe-filters__dropdown-panel"
							id="<?php echo esc_attr( $panel_id ); ?>"
							data-recipes-dropdown-panel
							role="group"
							aria-label="<?php echo esc_attr( $filter_group['label'] ); ?>"
							hidden
						>
							<ul class="recipe-filters__options" role="list">
								<?php foreach ( $filter_group['terms'] as $filter_term ) : ?>
									<li class="recipe-filters__option-item" role="listitem">
										<label class="recipe-filters__option">
											<input
												type="checkbox"
												class="checkbox__input"
												data-recipes-filter
												data-filter-group="<?php echo esc_attr( $filter_key ); ?>"
												data-filter-value="<?php echo esc_attr( (string) $filter_term->term_id ); ?>"
											/>
											<span class="recipe-filters__option-label"><?php echo esc_html( $filter_term->name ); ?></span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				<?php endforeach; ?>

				<button
					type="button"
					class="recipe-filters__link recipe-filters__link--reset"
					data-recipes-reset
					hidden
				>
					<?php esc_html_e( 'Tout effacer', '180c' ); ?>
				</button>

			</div>
		</div>
	</form>

	<!-- ================================================================
	GRILLE + SCROLL INFINI
	================================================================ -->
	<div class="container-180c">

		<p class="recipes-archive__count" data-recipes-count aria-live="polite">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: nombre de recettes. */
					_n( '%s recette', '%s recettes', $total, '180c' ),
					number_format_i18n( $total )
				)
			);
			?>
		</p>

		<ul
			class="recipes-grid"
			role="list"
			data-recipes-grid
			data-page="1"
			data-has-more="<?php echo $has_more ? 'true' : 'false'; ?>"
		>
			<?php
			while ( $initial_query->have_posts() ) :
				$initial_query->the_post();
				$card_html = _180c_render_recipe_card( get_the_ID() );
				if ( ! $card_html ) {
					continue;
				}
				?>
				<li class="recipes-grid__item" role="listitem">
					<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</li>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</ul>

		<p class="recipes-archive__noresults" data-recipes-noresults hidden>
			<?php esc_html_e( 'Aucune recette ne correspond à ces critères.', '180c' ); ?>
		</p>

		<div class="recipes-archive__loader" data-recipes-loader hidden aria-hidden="true">
			<span class="recipes-archive__spinner"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Chargement des recettes…', '180c' ); ?></span>
		</div>

		<div class="recipes-archive__sentinel" data-recipes-sentinel aria-hidden="true"></div>

	</div>

</main>

<?php
get_footer();
