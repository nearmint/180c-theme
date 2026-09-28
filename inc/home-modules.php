<?php
/**
 * Rendu mutualisé du builder de modules home (Flexible Content `home_modules`).
 *
 * Trois helpers publics :
 *  - _180c_render_home_modules() : boucle sur le Flexible Content et délègue
 *    à parts/modules/{layout}.php. Consommé par front-page.php (front_page)
 *    et template-home.php (page « La Gazette »).
 *  - _180c_render_rail() : shell HTML mutualisé pour tous les rails horizontaux
 *    (articles, recettes, produits). Centralise titre, lien « Voir tout »,
 *    sémantique a11y (role=region, track focusable) et flèches desktop.
 *  - _180c_home_module_visibility() : normalise le sous-champ `display` d'un
 *    module. Partagé avec l'endpoint REST /180c/v1/home-recettes, qui applique
 *    la règle symétrique côté apps.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalise la visibilité par plateforme d'un module home.
 *
 * Le sous-champ ACF `display` vaut `web_only` (défaut), `app_only` ou
 * `web_app`. Cette fonction absorbe en plus les deux valeurs de l'ancien
 * modèle binaire, qui peuvent subsister en base tant que la migration
 * la migration de visibilité des modules n'a pas été rejouée sur un
 * environnement — et, plus généralement, toute valeur inattendue :
 *
 *  - `visible`, valeur vide ou inconnue → `web_app`. C'est exactement le
 *    comportement d'avant la bascule (module rendu sur le web ET sérialisé
 *    pour les apps). Le choix est délibérément « fail-open » : un déploiement
 *    de code qui précéderait la migration BDD ne doit pas vider la home.
 *  - `hidden` → `none`, sentinelle interne : aucune plateforme. Elle ne
 *    correspond à aucun choix du champ ACF et n'est jamais sérialisée en REST ;
 *    elle existe pour ne pas ressusciter un module que la rédaction avait
 *    explicitement masqué sous l'ancien modèle.
 *
 * @param mixed $raw Valeur brute du sous-champ `display`.
 * @return string `web_only`, `app_only`, `web_app` ou `none`.
 */
function _180c_home_module_visibility( $raw ) {
	$value = is_string( $raw ) ? $raw : '';

	if ( in_array( $value, array( 'web_only', 'app_only', 'web_app' ), true ) ) {
		return $value;
	}

	if ( 'hidden' === $value ) {
		return 'none';
	}

	return 'web_app';
}

/**
 * Rend les modules home d'une page donnée.
 *
 * Parcourt le Flexible Content `home_modules` et inclut le template part
 * correspondant à chaque layout. Seuls les modules visibles sur le web sont
 * rendus (`web_only` ou `web_app`). L'index ACF de chaque module (position
 * dans la liste, modules non rendus compris) est exposé aux parts via la query
 * var `_180c_block_index` afin que les grilles paginées disposent d'une clé
 * d'instance stable et non collisionnante.
 *
 * @param int|null $page_id ID de la page portant les modules. Défaut : objet
 *                          de la requête courante (front_page ou page Gazette).
 * @return void
 */
function _180c_render_home_modules( $page_id = null ) {
	$page_id = $page_id ? (int) $page_id : (int) get_queried_object_id();

	if ( ! function_exists( 'have_rows' ) || ! have_rows( 'home_modules', $page_id ) ) {
		return;
	}

	$index = -1;
	while ( have_rows( 'home_modules', $page_id ) ) {
		the_row();
		++$index;

		// Masqué : App uniquement (`app_only`), ou legacy `hidden` → `none`.
		if ( ! in_array( _180c_home_module_visibility( get_sub_field( 'display' ) ), array( 'web_only', 'web_app' ), true ) ) {
			continue;
		}

		set_query_var( '_180c_block_index', $index );
		get_template_part( 'parts/modules/' . get_row_layout() );
	}
}

/**
 * Rend le shell HTML d'un rail horizontal de cartes.
 *
 * Markup mutualisé par articles_rail / recipes_rail / products_rail : section
 * `role=region`, en-tête (titre + lien « Voir tout » optionnel), piste
 * focusable au clavier et navigation par flèches (activée par rail.js
 * uniquement en cas d'overflow sur desktop). Ne rend rien si aucune carte.
 *
 * @param array $args Paramètres du rail : `title` (string, titre h2),
 *                    `items_html` (string[], cartes déjà rendues et échappées),
 *                    `view_all_url` (string|null, lien « Voir tout »),
 *                    `view_all_label` (string, libellé du lien),
 *                    `region_label` (string, aria-label, défaut $title),
 *                    `modifier` (string, suffixe BEM home-module--{modifier}).
 * @return void
 */
function _180c_render_rail( array $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'          => '',
			'items_html'     => array(),
			'view_all_url'   => null,
			'view_all_label' => __( 'Voir tout', '180c' ),
			'region_label'   => '',
			'modifier'       => '',
		)
	);

	if ( empty( $args['items_html'] ) ) {
		return;
	}

	$region_label  = $args['region_label'] ? $args['region_label'] : $args['title'];
	$section_class = 'home-module rail-180c';
	if ( $args['modifier'] ) {
		$section_class .= ' home-module--' . sanitize_html_class( $args['modifier'] );
	}
	?>
	<section class="<?php echo esc_attr( $section_class ); ?>"<?php echo $region_label ? ' aria-label="' . esc_attr( $region_label ) . '"' : ''; ?>>
		<div class="container-180c">
			<div class="rail-180c__head">
				<?php if ( $args['title'] ) : ?>
					<h2 class="rail-180c__title"><?php echo esc_html( $args['title'] ); ?></h2>
				<?php endif; ?>
				<?php if ( $args['view_all_url'] ) : ?>
					<a class="rail-180c__view-all" href="<?php echo esc_url( $args['view_all_url'] ); ?>">
						<?php echo esc_html( $args['view_all_label'] ); ?>
						<span class="screen-reader-text"><?php echo esc_html( $args['title'] ? $args['title'] : $region_label ); ?></span>
					</a>
				<?php endif; ?>
			</div>

			<?php // `role="list"` CONSERVÉ : le <ul> est en display:flex avec list-style:none, combinaison qui fait perdre la sémantique de liste à VoiceOver/Safari. Le rôle explicite la rétablit. Les <li>, eux, restent en display:list-item : leur `role="listitem"` était redondant et a été retiré. ?>
			<ul class="rail-180c__track" role="list" tabindex="0">
				<?php foreach ( $args['items_html'] as $item ) : ?>
					<li class="rail-180c__item">
						<?php echo $item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML déjà échappé par les helpers de carte. ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="rail-180c__nav" aria-hidden="true">
				<button type="button" class="rail-180c__arrow rail-180c__arrow--prev" tabindex="-1" aria-label="<?php esc_attr_e( 'Précédent', '180c' ); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
				</button>
				<button type="button" class="rail-180c__arrow rail-180c__arrow--next" tabindex="-1" aria-label="<?php esc_attr_e( 'Suivant', '180c' ); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
				</button>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Plafond dur du nombre de recettes d'un module `recipes_slider`.
 *
 * Le `max` d'un champ number ACF ne contraint que l'UI d'admin : une valeur
 * plus grande déjà stockée (ou posée par script / import) repasse telle quelle
 * dans `get_sub_field()`. Le plafond doit donc exister côté rendu.
 */
const _180C_RECIPES_SLIDER_MAX = 10;

/**
 * Préfixe des clés de transient posées par `_180c_resolve_recipe_rail_ids()`.
 *
 * Une seule famille existe aujourd'hui : `_180c_rail_recipe_ids_recent_{count}`
 * (mode « recent » sans dédoublonnage). Le préfixe s'arrête volontairement AVANT
 * `recent_` pour qu'une future variante de la même famille soit purgée sans
 * retoucher `_180c_flush_recipe_rail_cache()`.
 *
 * Il ne recouvre en revanche PAS `_180c_rail_articles_recent_*`
 * (parts/modules/articles_rail.php) ni `_180c_rail_products_recent_v2_*`
 * (parts/modules/products_rail.php) : ces deux caches ne dépendent pas des
 * recettes, publier une recette n'a aucune raison de les invalider.
 *
 * `inc/seo/schema.php` reconstruit la même clé en littéral pour sa lecture. Il
 * n'a pas besoin de la constante : la purge travaille sur le préfixe en base et
 * le couvre quoi qu'il arrive. En cas de renommage ici, cette lecture-là ne
 * trouverait plus rien et retomberait sur sa propre requête — sortie identique,
 * simplement non mise en cache.
 */
const _180C_RECIPE_RAIL_CACHE_PREFIX = '_180c_rail_recipe_ids_';

/**
 * Rend le module « Slider de recettes » (une recette en vedette à la fois).
 *
 * Shell HTML mutualisé entre le module home (parts/modules/recipes_slider.php)
 * et le bloc Gutenberg `180c/recipes-slider` : les deux surfaces émettent
 * exactement le même markup, donc le même CSS et le même JS s'y appliquent.
 *
 * Enhancement progressif (cf. src/js/modules/recipes-slider.js) : la piste est
 * un conteneur à défilement horizontal natif avec scroll-snap. Sans JS, le
 * premier slide est visible et les suivants restent atteignables au doigt ou à
 * la molette ; les flèches, le compteur et la frise de pagination ne sont
 * révélés qu'une fois la classe `is-enhanced` posée par le JS, pour ne jamais
 * afficher un contrôle inerte.
 *
 * @param array $args Paramètres du slider : `title` (string, titre h2),
 *                    `recipe_ids` (int[], recettes à rendre, déjà ordonnées),
 *                    `view_all_url` (string|null, lien « Toutes les recettes »),
 *                    `view_all_label` (string, libellé du lien),
 *                    `region_label` (string, aria-label, défaut $title),
 *                    `priority_first` (bool, true → 1er visuel eager +
 *                    fetchpriority high, pour un module en haut de page),
 *                    `extra_class` (string, classes ajoutées à la section).
 * @return void
 */
function _180c_render_recipes_slider( array $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'          => '',
			'recipe_ids'     => array(),
			'view_all_url'   => null,
			'view_all_label' => __( 'Toutes les recettes', '180c' ),
			'region_label'   => '',
			'priority_first' => false,
			'extra_class'    => '',
		)
	);

	$recipe_ids = array_slice( array_map( 'intval', (array) $args['recipe_ids'] ), 0, _180C_RECIPES_SLIDER_MAX );
	$recipes    = array();

	foreach ( $recipe_ids as $recipe_id ) {
		$recipe = get_post( $recipe_id );
		if ( $recipe instanceof WP_Post ) {
			$recipes[] = $recipe;
		}
	}

	if ( empty( $recipes ) ) {
		return;
	}

	$title        = $args['title'] ? $args['title'] : __( 'Les dernières recettes publiées', '180c' );
	$region_label = $args['region_label'] ? $args['region_label'] : $title;

	$section_class = 'home-module recipes-slider';
	if ( $args['extra_class'] ) {
		$section_class .= ' ' . $args['extra_class'];
	}
	?>
	<section class="<?php echo esc_attr( $section_class ); ?>" aria-label="<?php echo esc_attr( $region_label ); ?>" data-recipes-slider>
		<div class="container-180c">
			<div class="recipes-slider__head">
				<h2 class="recipes-slider__title"><?php echo esc_html( $title ); ?></h2>

				<div class="recipes-slider__controls">
					<div class="recipes-slider__arrows">
						<button type="button" class="recipes-slider__arrow recipes-slider__arrow--prev" data-slider-prev aria-label="<?php esc_attr_e( 'Recette précédente', '180c' ); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6" /></svg>
						</button>
						<button type="button" class="recipes-slider__arrow recipes-slider__arrow--next" data-slider-next aria-label="<?php esc_attr_e( 'Recette suivante', '180c' ); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" /></svg>
						</button>
					</div>

					<?php if ( $args['view_all_url'] ) : ?>
						<a class="recipes-slider__view-all" href="<?php echo esc_url( $args['view_all_url'] ); ?>">
							<?php echo esc_html( $args['view_all_label'] ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="recipes-slider__viewport" data-slider-viewport role="group" aria-roledescription="<?php esc_attr_e( 'carrousel', '180c' ); ?>" aria-label="<?php echo esc_attr( $region_label ); ?>" tabindex="0">
				<div class="recipes-slider__track" data-slider-track>
					<?php
					foreach ( $recipes as $position => $recipe ) :
						$eyebrow  = _180c_recipe_slider_eyebrow( $recipe );
						$intro    = _180c_recipe_slider_intro( $recipe );
						$thumb_id = (int) get_post_thumbnail_id( $recipe );
						$is_first = ( 0 === $position );
						?>
						<article class="recipes-slider__slide" data-slider-slide>
							<?php
							/*
							 * Lien de recouvrement — c'est LUI qui rend la carte entière
							 * cliquable, et pas un pseudo-élément.
							 *
							 * La version précédente étirait le `::after` du CTA sur tout
							 * le slide (motif « stretched link »). Mesuré en production :
							 * le pseudo couvrait bien la carte (1110 × 651 px, z-index 1,
							 * pointer-events auto) mais un vrai clic sur la photo arrivait
							 * avec `target` = l'<article>, jamais le <a> — la navigation
							 * ne partait pas. Les trois API de hit-testing ne s'accordaient
							 * même pas entre elles sur ce point.
							 *
							 * Un ÉLÉMENT réel ne laisse aucune place à cette ambiguïté.
							 * aria-hidden + tabindex="-1" : le lien accessible reste le
							 * CTA « Voir la recette », celui-ci ne doit être ni annoncé ni
							 * atteint au clavier (sinon doublon sur chaque slide).
							 */
							?>
							<a class="recipes-slider__overlay-link" href="<?php echo esc_url( get_permalink( $recipe ) ); ?>" aria-hidden="true" tabindex="-1"></a>

							<?php if ( $thumb_id ) : ?>
								<div class="recipes-slider__media">
									<?php
									echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé par le core.
										$thumb_id,
										'large',
										false,
										array(
											'class'    => 'recipes-slider__image',
											// Seul le 1er visuel d'un module en haut de page est
											// candidat LCP ; tout le reste est hors écran.
											'loading'  => ( $is_first && $args['priority_first'] ) ? 'eager' : 'lazy',
											'decoding' => 'async',
											'sizes'    => '(min-width: 768px) 33vw, 100vw',
										) + ( ( $is_first && $args['priority_first'] ) ? array( 'fetchpriority' => 'high' ) : array() )
									);
									?>
								</div>
							<?php endif; ?>

							<div class="recipes-slider__panel">
								<?php if ( $eyebrow ) : ?>
									<span class="recipes-slider__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
								<?php endif; ?>

								<h3 class="recipes-slider__slide-title"><?php echo esc_html( get_the_title( $recipe ) ); ?></h3>

								<?php if ( $intro ) : ?>
									<p class="recipes-slider__intro"><?php echo esc_html( $intro ); ?></p>
								<?php endif; ?>

								<div class="recipes-slider__actions">
									<?php // Lien unique du slide : son ::after s'étend sur toute la carte (stretched link, cf. recipes-slider.css). Le titre n'est donc PAS un lien — il serait redondant et masqué par le pseudo. ?>
									<a class="btn btn--primary recipes-slider__cta" href="<?php echo esc_url( get_permalink( $recipe ) ); ?>">
										<?php esc_html_e( 'Voir la recette', '180c' ); ?>
										<span class="screen-reader-text"><?php echo esc_html( get_the_title( $recipe ) ); ?></span>
									</a>
									<?php
									if ( function_exists( '_180c_render_favorite_button' ) ) {
										// Bouton ACTIF même déconnecté, comme partout ailleurs
										// (rails, cartes) : le clic redirige vers /connexion/ via
										// src/js/modules/favorites.js. Un `disabled` n'est pas
										// focusable — il rendait le popover « carnet » et son CTA
										// d'abonnement inatteignables au clavier sur cette seule
										// surface, alors qu'ils l'étaient dans les rails.
										echo _180c_render_favorite_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le renderer.
											(int) $recipe->ID,
											'single'
										);
									}
									?>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>

			<ol class="recipes-slider__thumbs" data-slider-thumbs>
				<?php
				foreach ( $recipes as $position => $recipe ) :
					$thumb_title = get_the_title( $recipe );
					?>
					<li class="recipes-slider__thumb">
						<?php
						/*
						 * `aria-label` porté par le bouton, et non laissé au seul libellé
						 * visible : sous 768 px, le CSS masque en `display: none` À LA FOIS
						 * `__thumb-num` et `__thumb-label`, ne laissant dans le bouton que
						 * `__thumb-rail`, lui-même `aria-hidden`. Le bouton se retrouvait donc
						 * SANS AUCUN nom accessible — un lecteur d'écran n'annonçait que
						 * « bouton ». Relevé par axe-core (règle `button-name`, impact
						 * critique, 5 occurrences).
						 *
						 * La valeur est exactement le libellé visible au-dessus de 768 px :
						 * les deux ne peuvent pas diverger, ils sortent du même
						 * `get_the_title()`. Le critère WCAG 2.5.3 « Label in Name » est donc
						 * satisfait à toutes les largeurs, et pas seulement à celles où le
						 * libellé est masqué.
						 */
						?>
						<button
							type="button"
							class="recipes-slider__thumb-button"
							data-slider-go="<?php echo esc_attr( (string) $position ); ?>"
							aria-current="<?php echo 0 === $position ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( $thumb_title ); ?>"
						>
							<span class="recipes-slider__thumb-rail" aria-hidden="true">
								<span class="recipes-slider__thumb-bar"></span>
							</span>
							<span class="recipes-slider__thumb-num" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $position + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
							<span class="recipes-slider__thumb-label"><?php echo esc_html( $thumb_title ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ol>

			<p class="screen-reader-text" aria-live="polite" data-slider-live></p>
		</div>
	</section>
	<?php
}

/**
 * Chapô d'un slide : le champ ACF `recipe_intro`, en texte brut et JAMAIS tronqué.
 *
 * Source de vérité = l'ACF « Introduction » (group_recipe_fields.json), le texte
 * écrit par la rédaction pour présenter la recette. PAS `get_the_excerpt()` :
 * l'extrait natif est soit vide, soit un repli auto-généré depuis le contenu,
 * soit un copier-coller d'InDesign avec son markup. Relevé sur les 30 dernières
 * recettes en production : `recipe_intro` est renseigné 30 fois sur 30, l'extrait
 * natif seulement 27 — dont trois des cinq recettes alors à l'affiche, qui
 * s'affichaient donc sans chapô.
 *
 * Le nettoyage reste nécessaire malgré un champ textarea : les intros ont été
 * migrées depuis le gras d'ouverture des anciens articles et charrient encore
 * des entités, des balises résiduelles et des insécables.
 *
 * Ordre imposé : décodage des entités D'ABORD, strip ENSUITE. L'inverse
 * laisserait passer un `&lt;strong&gt;` que le décodage rétablirait en balise.
 *
 * @param WP_Post $recipe La recette.
 * @return string Texte brut prêt à échapper (vide si le champ n'est pas rempli).
 */
function _180c_recipe_slider_intro( WP_Post $recipe ) {
	$intro = (string) _180c_acf( 'recipe_intro', $recipe->ID, '' );

	if ( '' === $intro ) {
		return '';
	}

	$intro = html_entity_decode( $intro, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$intro = wp_strip_all_tags( $intro, true );

	// Insécables et retours de ligne hérités de la migration : ils creuseraient
	// des trous dans un chapô rendu en un seul paragraphe.
	$intro = preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $intro ) );

	return trim( (string) $intro );
}

/**
 * Eyebrow d'un slide : catégorie culinaire · saison.
 *
 * Reprend la même source de vérité que la carte recette
 * (_180c_block_render_recipe_card) : premier terme `recipe_category`, puis
 * premier terme `recipe_season`. Les deux sont facultatifs — le séparateur
 * n'apparaît que si les deux sont présents.
 *
 * @param WP_Post $recipe La recette.
 * @return string Libellé prêt à échapper (vide si aucune taxonomie).
 */
function _180c_recipe_slider_eyebrow( WP_Post $recipe ) {
	$parts = array();

	foreach ( array( 'recipe_category', 'recipe_season' ) as $taxonomy ) {
		$terms = get_the_terms( $recipe->ID, $taxonomy );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$parts[] = $terms[0]->name;
		}
	}

	return implode( ' · ', $parts );
}

/**
 * Accumulateur d'IDs de recettes déjà rendues dans la page courante.
 *
 * Permet le dédoublonnage inter-modules (option « Exclure les recettes déjà
 * affichées ») : chaque module recette enregistre les IDs qu'il rend, et les
 * modules situés plus bas peuvent les exclure via `post__not_in`. La portée
 * est la requête courante (static), réinitialisée à chaque chargement de page.
 *
 * @param int[] $add IDs de recettes à mémoriser. Vide pour lire l'accumulateur.
 * @return int[] Liste dédoublonnée des IDs déjà rendus.
 */
function _180c_displayed_recipe_ids( $add = array() ) {
	static $ids = array();

	if ( ! empty( $add ) ) {
		$ids = array_values( array_unique( array_merge( $ids, array_map( 'intval', (array) $add ) ) ) );
	}

	return $ids;
}

/**
 * Terme `recipe_season` correspondant à la date courante (hémisphère nord).
 *
 * Mappe le mois sur l'un des slugs `printemps` | `ete` | `automne` | `hiver`.
 * Les slugs doivent correspondre aux termes saisis en back-office (voir
 * l'étape de vérification dans la doc des composants). Retourne null si le
 * terme n'existe pas encore.
 *
 * @return WP_Term|null Terme de la saison en cours, ou null.
 */
function _180c_current_season_term() {
	$month = (int) wp_date( 'n' );

	if ( $month >= 3 && $month <= 5 ) {
		$slug = 'printemps';
	} elseif ( $month >= 6 && $month <= 8 ) {
		$slug = 'ete';
	} elseif ( $month >= 9 && $month <= 11 ) {
		$slug = 'automne';
	} else {
		$slug = 'hiver';
	}

	/**
	 * Filtre le slug du terme `recipe_season` retenu pour la saison en cours.
	 *
	 * @param string $slug  Slug calculé (printemps|ete|automne|hiver).
	 * @param int    $month Mois courant (1-12).
	 */
	$slug = apply_filters( '180c/current_season_slug', $slug, $month );

	$term = get_term_by( 'slug', $slug, 'recipe_season' );

	return ( $term instanceof WP_Term ) ? $term : null;
}

/**
 * Indique si une page contient au moins un module du layout donné.
 *
 * Sert à discriminer les pages bâties avec le même builder (ex. « La Gazette »
 * vs « Recettes ») sans dépendre du titre : la présence du layout `carnet`
 * caractérise la home Recettes côté SEO (cf. inc/seo/schema.php).
 *
 * @param int    $page_id ID de la page.
 * @param string $layout  Nom machine du layout recherché (ex. 'carnet').
 * @return bool True si le layout est présent au moins une fois.
 */
function _180c_home_modules_has_layout( $page_id, $layout ) {
	$page_id = (int) $page_id;

	if ( ! $page_id || ! function_exists( 'have_rows' ) || ! have_rows( 'home_modules', $page_id ) ) {
		return false;
	}

	$found = false;
	while ( have_rows( 'home_modules', $page_id ) ) {
		the_row();
		if ( get_row_layout() === $layout ) {
			$found = true;
			break;
		}
	}
	reset_rows();

	return $found;
}

/**
 * Normalise une valeur de champ Taxonomy ACF en objet WP_Term.
 *
 * Le champ peut renvoyer un WP_Term (return_format=object) ou un ID de terme.
 *
 * @param mixed  $value    Valeur ACF (WP_Term, ID, ou null).
 * @param string $taxonomy Taxonomie attendue pour résoudre un ID.
 * @return WP_Term|null
 */
function _180c_acf_term_object( $value, $taxonomy ) {
	if ( $value instanceof WP_Term ) {
		return $value;
	}
	if ( is_numeric( $value ) && (int) $value > 0 ) {
		$term = get_term( (int) $value, $taxonomy );
		return ( $term instanceof WP_Term ) ? $term : null;
	}
	return null;
}

/**
 * Résout la taxonomie + le terme cible d'un module `recipes_rail` selon son mode.
 *
 * Mapping (clés taxo finales) : mode=category → recipe_publication ;
 * mode=season / current_season → recipe_season ; mode=type → recipe_category.
 * Les modes `recent` / `manual` ne ciblent aucun terme.
 *
 * @param array $sub Sous-champs du module (mode + sélecteurs de taxo).
 * @return array{taxonomy:string,term:?WP_Term}
 */
function _180c_recipe_rail_term( array $sub ) {
	$mode   = ! empty( $sub['mode'] ) ? $sub['mode'] : 'recent';
	$result = array(
		'taxonomy' => '',
		'term'     => null,
	);

	switch ( $mode ) {
		case 'category':
			$result['taxonomy'] = 'recipe_publication';
			$result['term']     = _180c_acf_term_object( $sub['recipe_category'] ?? null, 'recipe_publication' );
			break;
		case 'season':
			$result['taxonomy'] = 'recipe_season';
			$result['term']     = _180c_acf_term_object( $sub['season'] ?? null, 'recipe_season' );
			break;
		case 'current_season':
			$result['taxonomy'] = 'recipe_season';
			$result['term']     = _180c_current_season_term();
			break;
		case 'type':
			$result['taxonomy'] = 'recipe_category';
			$result['term']     = _180c_acf_term_object( $sub['type'] ?? null, 'recipe_category' );
			break;
	}

	return $result;
}

/**
 * Résout la liste ordonnée des IDs de recettes d'un module `recipes_rail`.
 *
 * Source de vérité unique partagée par le renderer (parts/modules/recipes_rail.php)
 * et l'endpoint REST `180c/v1/home-recettes` — zéro duplication. Honore le mode
 * (recent/category/season/current_season/type/manual), le dédoublonnage opt-in
 * inter-modules (accumulateur `_180c_displayed_recipe_ids`) et un cache court sur
 * le mode « recent » sans exclusion. Alimente l'accumulateur avec les IDs résolus.
 *
 * @param array $sub Sous-champs du module (mode, count, sélecteurs, manual_recipes,
 *                   exclude_displayed).
 * @return int[] IDs de recettes, ordonnés (vide si aucun résultat).
 */
function _180c_resolve_recipe_rail_ids( array $sub ) {
	$mode  = ! empty( $sub['mode'] ) ? $sub['mode'] : 'recent';
	$count = (int) ( $sub['count'] ?? 0 );
	if ( $count < 1 ) {
		$count = 8;
	}
	$exclude = 'manual' !== $mode && ! empty( $sub['exclude_displayed'] );

	$query_args = array(
		'post_type'      => 'recipe',
		'post_status'    => 'publish',
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		'fields'         => 'ids',
	);

	if ( 'manual' === $mode ) {
		$recipe_ids = array();
		foreach ( (array) ( $sub['manual_recipes'] ?? array() ) as $r ) {
			$rid = is_object( $r ) ? (int) $r->ID : (int) $r;
			if ( $rid > 0 ) {
				$recipe_ids[] = $rid;
			}
		}
		if ( empty( $recipe_ids ) ) {
			return array();
		}
		$query_args['post__in']       = $recipe_ids;
		$query_args['orderby']        = 'post__in';
		$query_args['posts_per_page'] = count( $recipe_ids );
	} else {
		$target = _180c_recipe_rail_term( $sub );
		if ( '' !== $target['taxonomy'] && $target['term'] instanceof WP_Term ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => $target['taxonomy'],
					'field'    => 'term_id',
					'terms'    => array( (int) $target['term']->term_id ),
				),
			);
		}
	}

	if ( $exclude ) {
		$already = _180c_displayed_recipe_ids();
		if ( ! empty( $already ) ) {
			$query_args['post__not_in'] = $already;
		}
	}

	// Cache court (IDs) sur le mode « recent » sans dédoublonnage (sortie stable).
	$ids       = null;
	$cache_key = '';
	if ( 'recent' === $mode && ! $exclude ) {
		$cache_key = _180C_RECIPE_RAIL_CACHE_PREFIX . 'recent_' . $count;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$ids = array_map( 'intval', $cached );
		}
	}

	if ( null === $ids ) {
		$query = new WP_Query( $query_args );
		$ids   = array_map( 'intval', $query->posts );
		if ( '' !== $cache_key ) {
			set_transient( $cache_key, $ids, 5 * MINUTE_IN_SECONDS );
		}
	}

	if ( ! empty( $ids ) ) {
		_180c_displayed_recipe_ids( $ids );
	}

	return $ids;
}

/**
 * Purge le cache court des IDs de recettes des rails / sliders.
 *
 * ## Pourquoi le TTL de 5 minutes ne suffit pas
 *
 * `get_transient()` ne contrôle l'expiration que dans deux conditions cumulées
 * (wp-includes/option.php, `get_transient()`) :
 *
 *  - la ligne `_transient_*` ne doit PAS être autoloadée — le contrôle est dans
 *    un `if ( ! isset( $alloptions[ $transient_option ] ) )`, donc entièrement
 *    sauté pour une option autoloadée ;
 *  - la ligne `_transient_timeout_*` doit exister — le test est
 *    `if ( false !== $timeout && $timeout < time() )`, donc inerte si elle manque.
 *
 * Dans l'un ou l'autre de ces états, la valeur périmée est rendue indéfiniment.
 * Et comme `set_transient()` n'est atteint QUE sur un miss
 * (cf. `_180c_resolve_recipe_rail_ids()`), la ligne ne se répare jamais seule :
 * l'interblocage est définitif. Le ramasse-miettes du cœur ne rattrape pas
 * l'orphelin non plus — `delete_expired_transients()` joint la ligne de timeout
 * en jointure interne, or c'est précisément elle qui manque.
 *
 * Constaté en production le 2026-09-01 : la page d'accueil servait une liste
 * figée d'avant le 2026-08-14, amputée des trois recettes les plus récentes,
 * sur le rendu du slider comme dans l'`ItemList` du JSON-LD.
 *
 * `delete_transient()` supprime les deux lignes sans condition : il guérit donc
 * l'état gelé en plus d'invalider le cache.
 *
 * Effet de bord souhaité : la liste devient exacte dès la publication, au lieu
 * d'un décalage nominal pouvant aller jusqu'à 5 minutes.
 *
 * La purge cible le PRÉFIXE en base, pas une clé littérale : elle couvre donc
 * aussi la lecture directe de `inc/seo/schema.php`, qui reconstruit la même clé
 * de son côté sans passer par la constante.
 *
 * @todo Durcir la LECTURE en embarquant l'échéance dans la valeur du transient,
 *       pour qu'une ligne gelée ne puisse plus figer le rendu entre deux
 *       publications. Lot séparé : `inc/seo/schema.php` lit la même clé en
 *       direct et devra suivre la nouvelle forme dans le même commit.
 *
 * @param int          $post_id ID du post à l'origine du déclenchement (0 si inconnu).
 * @param WP_Post|null $post    Objet post, quand le hook le fournit.
 * @return void
 */
function _180c_flush_recipe_rail_cache( $post_id = 0, $post = null ) {
	// L'éditeur de blocs poste un autosave toutes les ~10 s : sans cette garde,
	// chaque frappe déclencherait la requête `LIKE` ci-dessous.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$post_id = (int) $post_id;
	if ( $post_id && ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) ) {
		return;
	}

	/*
	 * `deleted_post` et `transition_post_status` sont GLOBAUX : sans ce bornage,
	 * la moindre commande WooCommerce enregistrée purgerait le cache des recettes.
	 * `save_post_recipe` est déjà borné par son nom — la garde y est alors
	 * simplement redondante. On lit `$post` en priorité : sur `deleted_post` la
	 * ligne n'existe plus, `get_post_type()` ne répondrait pas.
	 */
	if ( $post instanceof WP_Post ) {
		if ( 'recipe' !== $post->post_type ) {
			return;
		}
	} elseif ( $post_id && 'recipe' !== get_post_type( $post_id ) ) {
		return;
	}

	/*
	 * Pas de garde « une fois par requête » : une publication déclenche bien
	 * `transition_post_status` PUIS `save_post_recipe`, mais la seconde purge ne
	 * coûte qu'un SELECT qui ne ramène plus rien. Un drapeau statique casserait
	 * en revanche les scripts de migration (hors dépôt), qui créent
	 * des centaines de recettes dans un seul processus : seule la première
	 * publication purgerait.
	 */
	global $wpdb;

	$like = $wpdb->esc_like( '_transient_' . _180C_RECIPE_RAIL_CACHE_PREFIX ) . '%';

	/*
	 * Requête directe assumée, et non contournée :
	 *
	 *  - il n'existe aucune API cœur pour énumérer les transients d'un préfixe ;
	 *  - le nombre de clés n'est pas connu à l'avance (`count` va de 1 à 20 selon
	 *    la composition ACF, cf. acf-json/group_page_home_modules.json), donc
	 *    balayer les 20 clés à l'aveugle coûterait 40 lectures d'option pour n'en
	 *    trouver qu'une ou deux ;
	 *  - la mise en cache du SELECT (`NoCaching`) serait absurde : on lit ici
	 *    précisément pour invalider un cache. L'appel n'a lieu qu'à l'écriture
	 *    d'une recette, jamais sur une requête de front.
	 *
	 * La suppression, elle, passe bien par `delete_transient()` : c'est lui qui
	 * nettoie aussi le cache d'objets et la ligne de timeout.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like
		)
	);

	foreach ( (array) $names as $name ) {
		delete_transient( substr( $name, strlen( '_transient_' ) ) );
	}
}
add_action( 'save_post_recipe', '_180c_flush_recipe_rail_cache', 10, 2 );
add_action( 'deleted_post', '_180c_flush_recipe_rail_cache', 10, 2 );

/**
 * Adaptateur `transition_post_status` vers `_180c_flush_recipe_rail_cache()`.
 *
 * Signature incompatible (statuts d'abord, post en 3e), d'où ce relais plutôt
 * qu'un branchement direct. Couvre la mise en ligne, la dépublication, la mise
 * à la corbeille et la restauration — autant de cas où `save_post_recipe` ne
 * suffit pas ou n'est pas émis.
 *
 * @param string  $new_status Nouveau statut.
 * @param string  $old_status Ancien statut.
 * @param WP_Post $post       Post concerné.
 * @return void
 */
function _180c_flush_recipe_rail_cache_on_transition( $new_status, $old_status, $post ) {
	if ( $new_status === $old_status ) {
		return;
	}

	_180c_flush_recipe_rail_cache( $post instanceof WP_Post ? $post->ID : 0, $post );
}
add_action( 'transition_post_status', '_180c_flush_recipe_rail_cache_on_transition', 10, 3 );

/**
 * URL « Voir tout » d'un module `recipes_rail`.
 *
 * Lien du terme ciblé pour les modes filtrés (category/season/current_season/type),
 * sinon la page Recettes (sans ancre) pour les modes non filtrés (ex. « Dernières
 * recettes »).
 *
 * @param array $sub Sous-champs du module.
 * @return string|null
 */
function _180c_recipe_rail_view_all_url( array $sub ) {
	$target = _180c_recipe_rail_term( $sub );
	if ( '' !== $target['taxonomy'] && $target['term'] instanceof WP_Term ) {
		$link = get_term_link( $target['term'], $target['taxonomy'] );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}

	$recettes = get_page_by_path( 'recettes' );
	return $recettes instanceof WP_Post ? get_permalink( $recettes ) : home_url( '/recettes/' );
}

/**
 * Terme `product_cat` alimentant le module `collection_mosaic`.
 *
 * Résolu par SLUG et non par ID : l'ID du terme n'est pas garanti identique
 * entre les environnements. `85` n'est qu'un filet de sécurité documenté —
 * c'est l'ID observé en production ET en local au 2026-08-03, mais il ne doit
 * jamais être la source primaire.
 *
 * @return WP_Term|null Terme des revues 180°C, ou null s'il est introuvable.
 */
function _180c_collection_term() {
	$term = get_term_by( 'slug', 'revues-180c', 'product_cat' );

	if ( ! $term instanceof WP_Term ) {
		$fallback = get_term( 85, 'product_cat' );
		$term     = ( $fallback instanceof WP_Term ) ? $fallback : null;
	}

	/**
	 * Filtre le terme `product_cat` source du module « Collection ».
	 *
	 * Permet de cibler une autre catégorie (ou de neutraliser le module en
	 * renvoyant null) sans toucher au template.
	 *
	 * @param WP_Term|null $term Terme résolu par slug, puis par ID de repli.
	 */
	$term = apply_filters( '180c/collection_term', $term );

	return ( $term instanceof WP_Term ) ? $term : null;
}

/**
 * Numéros de revue du module « Collection », ordonnés par n° croissant.
 *
 * Le tri s'appuie sur le SKU, seule donnée qui porte le numéro de façon fiable
 * (le titre et la date de publication ne suivent pas l'ordre des parutions).
 * Format attendu : `REV-180-042`.
 *
 * Deux écueils que le motif adresse explicitement :
 *  1. Une extraction naïve des chiffres (`preg_replace('/\D/', '', $sku)`)
 *     ramasse aussi le « 180 » du préfixe : `REV-180-001` et `REV-180-HS001`
 *     donnent tous deux « 180001 » — collision silencieuse.
 *  2. Les hors-séries (`REV-180-HS001`) et les numéros spéciaux datés
 *     (`REV-180-2021-09`) n'ont pas de numéro de collection : ils sont exclus,
 *     par décision produit, et non triés en fin de liste.
 *
 * Les produits sans image mise en avant sont écartés : le module est purement
 * visuel, un numéro sans couverture n'a rien à y afficher.
 *
 * @return array[] Liste ordonnée de `array{id:int, thumb:int, sku:string, number:int}`.
 */
function _180c_collection_issues() {
	$term = _180c_collection_term();

	if ( ! $term instanceof WP_Term ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			// Volume borné (~30 numéros, catalogue clos et à croissance lente).
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => array( (int) $term->term_id ),
				),
			),
		)
	);

	$ids = array_map( 'intval', $query->posts );

	if ( empty( $ids ) ) {
		return array();
	}

	// `fields => ids` n'amorce pas le cache de meta : on le fait en une requête
	// plutôt qu'une par `get_post_meta()`.
	update_meta_cache( 'post', $ids );

	/**
	 * Filtre le motif d'extraction du numéro de revue depuis le SKU.
	 *
	 * Le motif DOIT exposer un premier groupe capturant contenant le numéro et
	 * être ancré en fin de chaîne, sans quoi les SKU hors-série et datés
	 * repassent dans la sélection.
	 *
	 * @param string $pattern Motif PCRE appliqué au SKU.
	 */
	$pattern = apply_filters( '180c/collection_sku_pattern', '/^REV-180-(\d{3})$/' );

	$issues = array();

	foreach ( $ids as $id ) {
		$sku = (string) get_post_meta( $id, '_sku', true );

		$matches = array();
		if ( ! preg_match( $pattern, trim( $sku ), $matches ) ) {
			continue;
		}

		$thumb = (int) get_post_thumbnail_id( $id );
		if ( ! $thumb ) {
			continue;
		}

		$issues[] = array(
			'id'     => $id,
			'thumb'  => $thumb,
			'sku'    => $sku,
			'number' => (int) $matches[1],
		);
	}

	usort(
		$issues,
		static function ( $a, $b ) {
			return $a['number'] <=> $b['number'];
		}
	);

	/**
	 * Filtre la liste ordonnée des numéros affichés par le module « Collection ».
	 *
	 * @param array[] $issues Numéros retenus, triés par n° croissant.
	 * @param WP_Term $term   Terme `product_cat` source.
	 */
	return apply_filters( '180c/collection_issues', $issues, $term );
}

/**
 * Force un `alt` vide sur les couvertures décoratives de la mosaïque.
 *
 * Le thème pose un repli d'`alt` global (_180c_a11y_fallback_image_alt) : dès
 * qu'un `alt` est vide, il retombe sur le titre puis la légende de la pièce
 * jointe, pour donner un nom accessible aux images portées par un lien. Les
 * couvertures de la mosaïque sont l'exact cas inverse : décoratives, sans lien,
 * répétées cent fois. Ce filtre est donc posé ponctuellement AUTOUR de leur
 * rendu, à une priorité supérieure, puis retiré.
 *
 * @param array $attr Attributs HTML de l'image.
 * @return array Attributs, `alt` forcé à vide.
 */
function _180c_collection_decorative_alt( $attr ) {
	$attr['alt'] = '';

	/*
	 * Une seule taille, pas de `srcset`. Le module rend 108 balises pour 26
	 * couvertures uniques ; chaque `srcset` de quatre candidats plus son `sizes`
	 * pesait ~350 octets, soit ~37 Ko de HTML pour un défilé décoratif intégral-
	 * ement recouvert d'un voile en `backdrop-filter: blur(12–16px)`.
	 *
	 * La taille retenue en amont est `medium` (225 × 300) : c'est déjà celle que
	 * le navigateur choisissait sur desktop via `sizes: 16vw`, donc aucun octet
	 * d'image supplémentaire de ce côté ; sur mobile il piochait `300x400`
	 * (25,0 Ko), la valeur fixe est donc plus légère. Ratio 0,75 identique à
	 * l'original 595 × 794 : redimensionnement proportionnel, aucun recadrage.
	 *
	 * Ce filtre s'exécute APRÈS l'ajout de srcset/sizes par
	 * `wp_get_attachment_image()` — c'est le seul point où on peut les retirer
	 * sans réécrire la balise à la main.
	 */
	unset( $attr['srcset'], $attr['sizes'] );

	// Décoratif : ne doit jamais concurrencer le LCP pour la bande passante.
	$attr['fetchpriority'] = 'low';

	return $attr;
}

/**
 * Répartit les couvertures en colonnes pour la mosaïque « Collection ».
 *
 * Chaque colonne reçoit son propre ordre, dérivé d'un hash déterministe de
 * l'index de colonne : le rendu est stable d'un chargement à l'autre (donc
 * compatible avec le cache de page) tout en évitant que les colonnes défilent
 * la même séquence côte à côte.
 *
 * Les items d'une colonne sont ensuite DOUBLÉS : l'animation translate la
 * colonne de -50 %, si bien que la seconde moitié vient exactement remplacer la
 * première — la boucle est sans couture visible.
 *
 * @param array[] $issues  Numéros retournés par _180c_collection_issues().
 * @param int     $columns Nombre de colonnes.
 * @param int     $per     Nombre de couvertures par colonne avant doublage.
 * @return array[] Une entrée par colonne : `array{thumbs:int[], duration:int, offset:string, direction:string}`.
 */
function _180c_collection_columns( array $issues, $columns, $per ) {
	$total = count( $issues );

	if ( ! $total ) {
		return array();
	}

	$columns = max( 1, (int) $columns );
	$per     = max( 1, (int) $per );
	$thumbs  = wp_list_pluck( $issues, 'thumb' );
	$offsets = array( '0%', '-6%', '-13%' );
	$result  = array();

	for ( $i = 0; $i < $columns; $i++ ) {
		$order = range( 0, $total - 1 );

		// Hash multiplicatif (constante de Knuth) : ordre pseudo-aléatoire mais
		// reproductible, propre à chaque colonne.
		$seed = ( $i * 37 ) + 17;
		usort(
			$order,
			static function ( $a, $b ) use ( $seed ) {
				$ha = ( ( $a + 1 ) * $seed * 2654435761 ) % 1000003;
				$hb = ( ( $b + 1 ) * $seed * 2654435761 ) % 1000003;
				return $ha <=> $hb;
			}
		);

		$items = array();
		for ( $j = 0; $j < $per; $j++ ) {
			$items[] = $thumbs[ $order[ $j % $total ] ];
		}

		// Couture de boucle : éviter deux fois la même couverture à la jointure.
		if ( $per > 1 && $items[ $per - 1 ] === $items[0] ) {
			$items[ $per - 1 ] = $thumbs[ $order[ ( $per + 1 ) % $total ] ];
		}

		$result[] = array(
			'thumbs'    => array_merge( $items, $items ),
			// Défilement lent (~13 px/s en desktop). L'écart entre colonnes
			// évite qu'elles se recalent visuellement les unes sur les autres.
			'duration'  => 120 + ( ( $i % 3 ) * 18 ),
			'offset'    => $offsets[ $i % 3 ],
			'direction' => ( $i % 2 ) ? 'down' : 'up',
		);
	}

	return $result;
}
