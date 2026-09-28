<?php
/**
 * Recherche native 180°C — requêtes, rendu des résultats, author-card.
 *
 * Moteur natif WP_Query / WP_User_Query (rechargement classique, pas d'instant
 * search). Page de résultats à onglets couvrant trois types, dans l'ordre
 * éditorial recette → article → auteur :
 *
 *  - recettes (CPT `recipe`)        via _180c_render_recipe_card()
 *  - articles (`post` natif)        via _180c_render_article_card()
 *  - auteurs  (publiants ≥ 1 post)  via _180c_render_author_card() (ci-dessous)
 *
 * Trois modes de rendu, orchestrés par _180c_render_search_results() :
 *  1. requête vide      → invite + formulaire.
 *  2. résultats         → onglets (liens, rechargement serveur, un seul
 *                         panneau actif paginé 12/page). Onglets vides
 *                         masqués ; défaut = premier onglet non vide.
 *  3. aucun résultat    → état vide (message + formulaire).
 *
 * Les onglets sont des liens (`<a href="?s=…&tab=…">`) : la bascule est une
 * navigation serveur, d'où une sémantique `<nav>` + `aria-current` (jamais
 * `role="tablist"/"tab"`, réservé aux widgets sans rechargement).
 *
 * Pas de meta_query ACF en v1 : pertinence native `s=` (titre + contenu).
 * Le formulaire vit dans parts/search-form.php ; le déclencheur du menu
 * latéral (parts/site-side-menu.php) soumet déjà vers /?s=.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nombre de résultats par page dans le panneau actif.
 */
const _180C_SEARCH_PER_PAGE = 12;

/**
 * Enregistre la query var `tab` (filtre d'onglet de la page de résultats).
 *
 * `paged` reste géré nativement par WP.
 */
add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'tab';
		return $vars;
	}
);

/*
 * ---------------------------------------------------------------------------
 * 1. Requêtes par type
 * ---------------------------------------------------------------------------
 */

/**
 * Requête recettes (CPT `recipe`) pour la recherche.
 *
 * @param string $query           Terme recherché (brut, échappé à l'affichage).
 * @param int    $posts_per_page  Nombre de résultats (jamais -1).
 * @param int    $paged           Page courante (1+).
 * @return WP_Query Requête exécutée — `found_posts` porte le total.
 */
function _180c_search_recipes( $query, $posts_per_page, $paged = 1 ) {
	return new WP_Query(
		array(
			'post_type'           => 'recipe',
			'post_status'         => 'publish',
			's'                   => $query,
			'posts_per_page'      => max( 1, (int) $posts_per_page ),
			'paged'               => max( 1, (int) $paged ),
			'no_found_rows'       => false,
			'ignore_sticky_posts' => true,
		)
	);
}

/**
 * Requête articles (`post` natif) pour la recherche.
 *
 * @param string $query           Terme recherché.
 * @param int    $posts_per_page  Nombre de résultats (jamais -1).
 * @param int    $paged           Page courante (1+).
 * @return WP_Query Requête exécutée — `found_posts` porte le total.
 */
function _180c_search_articles( $query, $posts_per_page, $paged = 1 ) {
	return new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			's'                   => $query,
			'posts_per_page'      => max( 1, (int) $posts_per_page ),
			'paged'               => max( 1, (int) $paged ),
			'no_found_rows'       => false,
			'ignore_sticky_posts' => true,
		)
	);
}

/**
 * Requête auteurs publiants pour la recherche.
 *
 * Recherche sur display_name / user_nicename / user_login, restreinte aux
 * comptes ayant publié au moins un article ou une recette (jamais les
 * abonnés). Total exposé via get_total().
 *
 * @param string $query  Terme recherché.
 * @param int    $number Nombre de résultats par page (jamais -1).
 * @param int    $paged  Page courante (1+).
 * @return WP_User_Query Requête exécutée.
 */
function _180c_search_authors( $query, $number, $paged = 1 ) {
	$number = max( 1, (int) $number );
	$paged  = max( 1, (int) $paged );

	return new WP_User_Query(
		array(
			'search'              => '*' . $query . '*',
			'search_columns'      => array( 'display_name', 'user_nicename', 'user_login' ),
			'has_published_posts' => array( 'post', 'recipe' ),
			'number'              => $number,
			'offset'              => ( $paged - 1 ) * $number,
			'count_total'         => true,
			'orderby'             => 'display_name',
			'order'               => 'ASC',
		)
	);
}

/**
 * Compte les résultats des trois types pour une requête donnée.
 *
 * Trois requêtes légères (1 résultat, `fields=ids` côté posts) suffisantes
 * pour alimenter les compteurs d'onglets et décider de leur visibilité.
 *
 * @param string $query Terme recherché.
 * @return array{recipe:int,article:int,author:int} Totaux par type.
 */
function _180c_search_counts( $query ) {
	$recipe = new WP_Query(
		array(
			'post_type'           => 'recipe',
			'post_status'         => 'publish',
			's'                   => $query,
			'posts_per_page'      => 1,
			'fields'              => 'ids',
			'no_found_rows'       => false,
			'ignore_sticky_posts' => true,
		)
	);

	$article = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			's'                   => $query,
			'posts_per_page'      => 1,
			'fields'              => 'ids',
			'no_found_rows'       => false,
			'ignore_sticky_posts' => true,
		)
	);

	$authors = _180c_search_authors( $query, 1 );

	wp_reset_postdata();

	return array(
		'recipe'  => (int) $recipe->found_posts,
		'article' => (int) $article->found_posts,
		'author'  => (int) $authors->get_total(),
	);
}

/*
 * ---------------------------------------------------------------------------
 * 2. Carte auteur (composant partagé)
 * ---------------------------------------------------------------------------
 */

/**
 * Rend la carte HTML d'un auteur à partir de son ID.
 *
 * Composant partagé (pendant de _180c_render_recipe_card / _180c_render_article_card)
 * consommé par la page de recherche et réutilisable ailleurs via
 * get_template_part( 'parts/author-card', null, array( 'author_id' => $id ) ).
 *
 * Photo : avatar éditorial ACF prioritaire (srcset natif), sinon
 * monogramme — jamais de silhouette Gravatar grise, par cohérence avec le
 * rail « Découvrez d'autres signatures » et le header auteur.
 *
 * @param int $author_id ID de l'auteur.
 * @return string HTML échappé, ou chaîne vide si l'auteur est introuvable.
 */
function _180c_render_author_card( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return '';
	}

	$name = _180c_author_display_name( $author_id );
	if ( '' === $name ) {
		return '';
	}

	$url       = get_author_posts_url( $author_id );
	$role      = _180c_author_role( $author_id );
	$avatar_id = _180c_author_has_real_avatar( $author_id ) ? _180c_author_avatar_id( $author_id ) : 0;
	$monogram  = ( 0 === $avatar_id ) ? _180c_author_monogram( $author_id ) : '';

	ob_start();
	?>
	<article class="author-card">
		<a class="author-card__link" href="<?php echo esc_url( $url ); ?>">
			<figure class="author-card__media">
				<?php
				if ( $avatar_id > 0 ) {
					echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image échappe ses attributs.
						$avatar_id,
						'thumbnail',
						false,
						array(
							'class'    => 'author-card__photo',
							'alt'      => $name,
							'loading'  => 'lazy',
							'decoding' => 'async',
						)
					);
				} else {
					?>
					<span class="author-card__monogram" aria-hidden="true"><?php echo esc_html( $monogram ); ?></span>
					<?php
				}
				?>
			</figure>
			<span class="author-card__text">
				<span class="author-card__name"><?php echo esc_html( $name ); ?></span>
				<?php if ( '' !== $role ) : ?>
					<span class="author-card__role"><?php echo esc_html( $role ); ?></span>
				<?php endif; ?>
			</span>
		</a>
	</article>
	<?php
	return (string) ob_get_clean();
}

/*
 * ---------------------------------------------------------------------------
 * 3. Orchestration du rendu
 * ---------------------------------------------------------------------------
 */

/**
 * Orchestre le rendu de la page de résultats de recherche.
 *
 * @param string $query Terme recherché (issu de get_search_query()).
 * @return string HTML complet de la zone de résultats.
 */
function _180c_render_search_results( $query ) {
	$query = trim( (string) $query );

	// Requête vide → invite + formulaire.
	if ( '' === $query ) {
		return _180c_search_render_prompt();
	}

	$counts = _180c_search_counts( $query );
	$labels = _180c_search_tab_labels();

	// Onglets visibles (count > 0), dans l'ordre recette → article → auteur.
	$visible = array();
	foreach ( $labels as $slug => $label ) {
		if ( $counts[ $slug ] > 0 ) {
			$visible[] = $slug;
		}
	}

	ob_start();

	echo _180c_search_render_header( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans le helper.
		sprintf(
			/* translators: %s: terme recherché. */
			__( 'Résultats pour « %s »', '180c' ),
			$query
		)
	);

	// Aucun onglet visible → aucun résultat, tous types confondus.
	if ( empty( $visible ) ) {
		echo _180c_search_render_empty( $query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans le helper.
		return (string) ob_get_clean();
	}

	// Onglet actif : `tab` demandé s'il est visible, sinon premier visible.
	$requested = sanitize_key( (string) get_query_var( 'tab' ) );
	$active    = in_array( $requested, $visible, true ) ? $requested : $visible[0];

	echo _180c_search_render_tabs( $query, $visible, $labels, $counts, $active ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans le helper.
	echo _180c_search_render_panel( $query, $active, $labels[ $active ], $counts[ $active ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans le helper.

	return (string) ob_get_clean();
}

/**
 * Libellés des onglets, dans l'ordre éditorial recette → article → auteur.
 *
 * Les clés (`recipe`/`article`/`author`) servent aussi de valeur `tab=` dans
 * l'URL ; `article` cible le CPT natif `post`.
 *
 * @return array<string,string> Slug d'onglet → libellé.
 */
function _180c_search_tab_labels() {
	return array(
		'recipe'  => __( 'Recettes', '180c' ),
		'article' => __( 'Articles', '180c' ),
		'author'  => __( 'Auteurs', '180c' ),
	);
}

/**
 * En-tête de page (h1) commun à tous les modes.
 *
 * @param string $title Titre déjà constitué (sera échappé ici).
 * @return string HTML du <header>.
 */
function _180c_search_render_header( $title ) {
	return sprintf(
		'<header class="search-results__header"><h1 class="search-results__heading">%s</h1></header>',
		esc_html( $title )
	);
}

/**
 * Invite de recherche (requête vide).
 *
 * @return string HTML.
 */
function _180c_search_render_prompt() {
	ob_start();

	echo _180c_search_render_header( __( 'Rechercher sur 180°C', '180c' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans le helper.
	?>
	<div class="search-results__intro">
		<p class="search-results__lead">
			<?php esc_html_e( 'Recettes, articles, signatures : saisissez un mot-clé pour explorer 180°C.', '180c' ); ?>
		</p>
		<?php get_template_part( 'parts/search-form' ); ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Barre d'onglets (navigation par liens, rechargement serveur).
 *
 * Sémantique de navigation : `<nav>` + `aria-current="page"` sur l'onglet
 * actif (jamais `role="tab"`, réservé aux bascules sans rechargement).
 *
 * @param string               $query   Terme recherché.
 * @param string[]             $visible Slugs d'onglets visibles, ordonnés.
 * @param array<string,string> $labels  Slug → libellé.
 * @param array<string,int>    $counts  Slug → total.
 * @param string               $active  Slug de l'onglet actif.
 * @return string HTML du <nav>.
 */
function _180c_search_render_tabs( $query, $visible, $labels, $counts, $active ) {
	ob_start();
	?>
	<nav id="resultats" class="search-tabs" aria-label="<?php esc_attr_e( 'Filtrer les résultats par type', '180c' ); ?>">
		<ul class="search-tabs__list" role="list">
			<?php
			foreach ( $visible as $slug ) :
				$is_active  = ( $slug === $active );
				$link_class = 'search-tabs__link' . ( $is_active ? ' is-active' : '' );
				$url        = add_query_arg(
					array(
						's'   => $query,
						'tab' => $slug,
					),
					home_url( '/' )
				) . '#resultats';
				?>
				<li class="search-tabs__item">
					<a
						class="<?php echo esc_attr( $link_class ); ?>"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $is_active ? 'aria-current="page"' : ''; ?>
					>
						<span class="search-tabs__label"><?php echo esc_html( $labels[ $slug ] ); ?></span>
						<span class="search-tabs__count">(<?php echo esc_html( number_format_i18n( (int) $counts[ $slug ] ) ); ?>)</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
	return (string) ob_get_clean();
}

/**
 * Panneau de résultats du type actif (paginé 12/page).
 *
 * @param string $query  Terme recherché.
 * @param string $active Slug de l'onglet actif (recipe|article|author).
 * @param string $label  Libellé du type actif.
 * @param int    $total  Total de résultats du type actif.
 * @return string HTML de la <section>.
 */
function _180c_search_render_panel( $query, $active, $label, $total ) {
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$total = (int) $total;
	$max   = max( 1, (int) ceil( $total / _180C_SEARCH_PER_PAGE ) );

	ob_start();
	?>
	<section
		class="search-results__panel"
		aria-label="<?php echo esc_attr( sprintf( /* translators: %s: type de résultat actif. */ __( 'Résultats : %s', '180c' ), $label ) ); ?>"
	>
		<h2 class="sr-only">
			<?php
			printf(
				'%1$s : %2$s',
				esc_html( $label ),
				esc_html( _180c_search_count_label( $total ) )
			);
			?>
		</h2>

		<?php if ( 'author' === $active ) : ?>
			<?php $authors = _180c_search_authors( $query, _180C_SEARCH_PER_PAGE, $paged ); ?>
			<ul class="search-results__grid search-results__grid--authors" role="list">
				<?php
				foreach ( $authors->get_results() as $user ) {
					printf(
						'<li class="search-results__item">%s</li>',
						_180c_render_author_card( (int) $user->ID ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le helper.
					);
				}
				?>
			</ul>
		<?php else : ?>
			<?php
			$wp_query    = ( 'recipe' === $active )
				? _180c_search_recipes( $query, _180C_SEARCH_PER_PAGE, $paged )
				: _180c_search_articles( $query, _180C_SEARCH_PER_PAGE, $paged );
			$render_card = ( 'recipe' === $active ) ? '_180c_render_recipe_card' : '_180c_render_article_card';
			?>
			<ul class="search-results__grid" role="list">
				<?php
				while ( $wp_query->have_posts() ) :
					$wp_query->the_post();
					printf(
						'<li class="search-results__item">%s</li>',
						call_user_func( $render_card, get_the_ID(), 'md' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le helper de carte.
					);
				endwhile;
				wp_reset_postdata();
				?>
			</ul>
		<?php endif; ?>

		<?php
		echo _180c_render_pagination( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit par le helper avec esc_attr en amont.
			array(
				'base'         => home_url( '/' ) . '%_%',
				'format'       => '?paged=%#%',
				'current'      => $paged,
				'total'        => $max,
				'add_args'     => array(
					's'   => $query,
					'tab' => $active,
				),
				'add_fragment' => '#resultats',
				'aria_label'   => __( 'Pagination des résultats', '180c' ),
			)
		);
		?>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Libellé de compteur accessible (« 1 résultat » / « N résultats »).
 *
 * @param int $total Nombre total.
 * @return string Libellé localisé.
 */
function _180c_search_count_label( $total ) {
	$total = (int) $total;
	return sprintf(
		/* translators: %s: nombre de résultats. */
		_n( '%s résultat', '%s résultats', $total, '180c' ),
		number_format_i18n( $total )
	);
}

/**
 * État vide partagé : aucun résultat + rappel du formulaire.
 *
 * @param string $query Terme recherché.
 * @return string HTML.
 */
function _180c_search_render_empty( $query ) {
	ob_start();
	?>
	<div class="search-results__empty">
		<p class="search-results__empty-title">
			<?php
			printf(
				/* translators: %s: terme recherché. */
				esc_html__( 'Aucun résultat pour « %s »', '180c' ),
				esc_html( $query )
			);
			?>
		</p>
		<p class="search-results__empty-help">
			<?php esc_html_e( 'Vérifiez l’orthographe ou essayez d’autres mots-clés.', '180c' ); ?>
		</p>
		<?php get_template_part( 'parts/search-form' ); ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
