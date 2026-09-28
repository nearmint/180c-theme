<?php
/**
 * Service favoris — couche d'accès aux données + sync site ↔ app.
 *
 * Deux tables custom :
 *   - `{prefix}user_favorites`          : favoris actifs (1 ligne = 1 favori actif).
 *   - `{prefix}user_favorites_removed`  : pierres tombales (tombstones) des favoris
 *                                         retirés, horodatées pour le last-write-wins.
 *
 * Pourquoi deux tables plutôt qu'une colonne `status` ? La grille « Mes recettes
 * favorites » (parts/account/favoris.php) et les helpers de lecture interrogent
 * directement `user_favorites` en supposant que chaque ligne est un favori actif.
 * On préserve donc cet invariant : un retrait supprime la ligne active ET écrit
 * une pierre tombale. La table de tombstones n'est lue que par la synchronisation.
 *
 * Stratégie de synchronisation : last-write-wins par recette. Chaque état (actif
 * ou retiré) porte un horodatage UTC ; lors d'un sync, l'horodatage le plus récent
 * (client app vs serveur) l'emporte. Les tombstones empêchent la « résurrection »
 * d'un favori retiré sur un appareil mais ré-ajouté depuis un autre avec un
 * horodatage plus ancien.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// Noms de tables
// ============================================================

/**
 * Nom complet (préfixé) de la table des favoris actifs.
 *
 * @return string
 */
function _180c_favorites_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'user_favorites';
}

/**
 * Nom complet (préfixé) de la table des pierres tombales de favoris.
 *
 * @return string
 */
function _180c_favorites_tombstones_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'user_favorites_removed';
}

// ============================================================
// Création / disponibilité des tables
// ============================================================

/**
 * Crée les tables favoris via dbDelta si elles sont absentes.
 *
 * Exécutée à l'activation du thème (`after_switch_theme`). En production,
 * les tables peuvent aussi être créées via le SQL one-shot
 * le script SQL de création des tables (non versionné).
 *
 * @return void
 */
function _180c_favorites_create_tables(): void {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$active          = _180c_favorites_table();
	$tombstones      = _180c_favorites_tombstones_table();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql_active = "CREATE TABLE {$active} (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT UNSIGNED NOT NULL,
		recipe_id BIGINT UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY uniq_user_recipe (user_id, recipe_id),
		KEY idx_user (user_id),
		KEY idx_recipe (recipe_id),
		KEY idx_user_created (user_id, created_at)
	) {$charset_collate};";

	$sql_tombstones = "CREATE TABLE {$tombstones} (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT UNSIGNED NOT NULL,
		recipe_id BIGINT UNSIGNED NOT NULL,
		removed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY uniq_user_recipe (user_id, recipe_id),
		KEY idx_user (user_id)
	) {$charset_collate};";

	dbDelta( $sql_active );
	dbDelta( $sql_tombstones );

	delete_transient( '_180c_favorites_tables_ok' );
}
add_action( 'after_switch_theme', '_180c_favorites_create_tables' );

/**
 * Indique si les deux tables favoris existent.
 *
 * Résultat mis en cache dans un transient pour éviter un SHOW TABLES répété.
 *
 * @return bool
 */
function _180c_favorites_tables_ready(): bool {
	$cached = get_transient( '_180c_favorites_tables_ok' );
	if ( false !== $cached ) {
		return (bool) $cached;
	}

	global $wpdb;
	$active     = _180c_favorites_table();
	$tombstones = _180c_favorites_tombstones_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$has_active     = $active === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $active ) );
	$has_tombstones = $tombstones === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tombstones ) );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	$ready = $has_active && $has_tombstones;
	set_transient( '_180c_favorites_tables_ok', $ready ? 1 : 0, HOUR_IN_SECONDS );

	return $ready;
}

/**
 * Version courante du schéma favoris.
 *
 * La v2 ajoute l'index composite `idx_user_created (user_id,
 * created_at)` sur la table active, pour servir la requête ORDER BY created_at
 * DESC de la page « Mon carnet » et du module home sans filesort.
 *
 * @var int
 */
const _180C_FAVORITES_DB_VERSION = 2;

/**
 * Met à niveau le schéma favoris sur les installations existantes.
 *
 * `_180c_favorites_create_tables()` n'est rejoué que sur `after_switch_theme` ;
 * cette garde versionnée applique les évolutions de schéma (ajout d'index) aux
 * sites déjà installés, sans nécessiter un changement de thème. dbDelta ajoute
 * uniquement l'index manquant (opération idempotente).
 *
 * @return void
 */
function _180c_favorites_maybe_upgrade(): void {
	$installed = (int) get_option( '_180c_favorites_db_version', 1 );

	if ( $installed >= _180C_FAVORITES_DB_VERSION ) {
		return;
	}

	if ( _180c_favorites_tables_ready() ) {
		_180c_favorites_create_tables();
	}

	update_option( '_180c_favorites_db_version', _180C_FAVORITES_DB_VERSION, false );
}
add_action( 'init', '_180c_favorites_maybe_upgrade' );

// ============================================================
// Hydratation (lecture mutualisée — 1 requête par requête HTTP)
// ============================================================

/**
 * Jeu mémoïsé des IDs de recettes favorites de l'utilisateur courant.
 *
 * Une seule requête par cycle PHP, quel que soit le nombre de cartes recette
 * affichées. Permet de marquer l'état initial (`aria-pressed`) de tous les
 * boutons favoris côté serveur en O(1), sans round-trip JS ni CLS, et sans
 * requête par carte. Retourne une map `recipe_id => true`.
 *
 * @return array<int,bool> Map des IDs favoris (vide si déconnecté).
 */
function _180c_favorite_ids_lookup(): array {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id || ! _180c_favorites_tables_ready() ) {
		$cache = array();
		return $cache;
	}

	global $wpdb;
	$table = _180c_favorites_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT recipe_id FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id
		)
	);

	$cache = array();
	foreach ( (array) $ids as $id ) {
		$cache[ (int) $id ] = true;
	}

	return $cache;
}

/**
 * Indique si une recette est favorite pour l'utilisateur courant (via le cache).
 *
 * Variante O(1) de `_180c_is_recipe_favorite()` : s'appuie sur le jeu mémoïsé
 * `_180c_favorite_ids_lookup()` pour marquer en masse l'état initial des boutons
 * sans une requête par carte. À utiliser dans les boucles de rendu (rails,
 * grilles). Retourne toujours false pour un visiteur déconnecté.
 *
 * @param int $recipe_id ID de la recette.
 * @return bool
 */
function _180c_favorite_is_marked( int $recipe_id ): bool {
	if ( $recipe_id < 1 ) {
		return false;
	}

	$lookup = _180c_favorite_ids_lookup();

	return isset( $lookup[ $recipe_id ] );
}

// ============================================================
// Rendu du bouton favori (composant partagé .favorite-button)
// ============================================================

/**
 * Enveloppe un bouton favori dans le popover « carnet » (.carnet-hint).
 *
 * Touch-point de promotion de l'abonnement, rendu UNIQUEMENT pour les visiteurs
 * non connectés. Il s'ajoute au bouton sans jamais le remplacer : un bouton
 * actif (surface « card ») garde son clic — qui redirige vers /connexion/ via
 * src/js/modules/favorites.js — et un bouton désactivé (surface « single »)
 * reste désactivé. Aucune régression pour les comptes connectés, qui ne
 * reçoivent tout simplement pas ce markup.
 *
 * Déclenchement : survol sur appareil pointeur, focus clavier, et tap sur les
 * seuls wrappers `--locked` (bouton désactivé, donc sans clic à préserver).
 * Un `<button disabled>` n'émettant aucun événement de souris, c'est le
 * wrapper qui les reçoit — d'où `pointer-events: none` posé en CSS sur le
 * bouton désactivé enveloppé (cf. src/css/components/carnet-hint.css).
 *
 * Sans JavaScript, le panneau reste replié : le bouton se comporte exactement
 * comme avant.
 *
 * Le contenu du panneau est UNIQUE — même titre, même texte, même CTA sur
 * toutes les surfaces. `$surface` ne pilote que l'ancrage du panneau (classe
 * modifier consommée par le CSS) : une carte l'encarte sous son bouton overlay,
 * une fiche recette le fait flotter sous le bouton libellé. Aucun contenu n'est
 * conditionnel.
 *
 * @param string $button_html HTML du bouton favori déjà rendu.
 * @param string $surface     'card' | 'single' — ancrage seulement.
 * @param bool   $disabled    True si le bouton enveloppé est désactivé.
 * @param string $panel_id    ID du panneau (cible de l'`aria-describedby`).
 * @return string HTML échappé.
 */
function _180c_render_carnet_hint( string $button_html, string $surface, bool $disabled, string $panel_id ): string {
	/**
	 * URL de la page d'abonnement visée par le CTA du popover.
	 *
	 * Volontairement `180c/subscription_url` (landing éditoriale) et non
	 * `180c/paywall_subscribe_url` (panier pré-rempli) : ce touch-point est un
	 * point de découverte, pas une fin de tunnel.
	 *
	 * @param string $url URL par défaut.
	 */
	$subscribe_url = (string) apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	$classes = 'carnet-hint carnet-hint--' . $surface . ( $disabled ? ' carnet-hint--locked' : '' );

	ob_start();
	?>
	<div
		class="<?php echo esc_attr( $classes ); ?>"
		data-carnet-hint
		<?php // Un bouton désactivé n'est pas focusable : le wrapper prend le relais pour que la description et son CTA restent atteignables au clavier. ?>
		<?php if ( $disabled ) : ?>
		tabindex="0"
		aria-describedby="<?php echo esc_attr( $panel_id ); ?>"
		<?php endif; ?>
	>
		<?php echo $button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé par _180c_render_favorite_button(). ?>

		<div class="carnet-hint__panel" id="<?php echo esc_attr( $panel_id ); ?>" role="tooltip" data-carnet-hint-panel>
			<strong class="carnet-hint__title"><?php esc_html_e( 'Réservé aux abonnés', '180c' ); ?></strong>
			<p class="carnet-hint__desc"><?php esc_html_e( 'Mettez cette recette de côté et constituez votre carnet.', '180c' ); ?></p>
			<a
				class="btn btn--primary btn--sm carnet-hint__cta"
				href="<?php echo esc_url( $subscribe_url ); ?>"
				<?php echo _180c_umami_attrs( 'subscribe_cta_click', array( 'position' => 'carnet_hint' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs déjà échappés par _180c_umami_attrs(). ?>
			>
				<?php esc_html_e( 'Je m’abonne', '180c' ); ?>
			</a>
			<?php // Croix affichée par CSS sur les seuls appareils sans survol : ailleurs, mouseleave / Échap / perte de focus suffisent. ?>
			<button type="button" class="carnet-hint__close" data-carnet-hint-close aria-label="<?php esc_attr_e( 'Fermer', '180c' ); ?>">&times;</button>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Rend le bouton favori « Mon carnet » pour une recette.
 *
 * Bouton réel (jamais lien) consommé par deux surfaces :
 *   - 'card'   : overlay coin haut-droit d'une recipe-card (icône seule).
 *   - 'single' : zone d'actions de la fiche recette (icône + label visible).
 *
 * L'état initial (`aria-pressed`, label) est hydraté côté serveur via
 * `_180c_favorite_is_marked()` (1 requête/page, O(1) par carte) : pas de
 * round-trip JS au chargement, pas de CLS. Le toggle est piloté par
 * src/js/modules/favorites.js. Visible pour tous (le clic d'un visiteur
 * déconnecté redirige vers la connexion).
 *
 * Visiteur NON connecté : le bouton est enveloppé dans le popover « carnet »
 * (`_180c_render_carnet_hint()`), qui promeut l'abonnement au survol / tap.
 * L'enveloppe est purement additive — le comportement du bouton, actif comme
 * désactivé, est inchangé. Les comptes connectés reçoivent le bouton nu.
 *
 * @param int    $recipe_id     ID de la recette.
 * @param string $surface       'card' (défaut) | 'single'.
 * @param bool   $disabled      True → bouton désactivé : attribut disabled +
 *                              is-disabled + aria-disabled, sans
 *                              data-favorite-toggle (aucun bind JS).
 * @param string $disabled_title Tooltip affiché à l'état désactivé. Vide →
 *                              « Réservé aux abonnés » par défaut.
 * @return string HTML échappé (chaîne vide si ID invalide) — bouton nu pour un
 *                compte connecté, bouton enveloppé dans .carnet-hint sinon.
 */
function _180c_render_favorite_button( int $recipe_id, string $surface = 'card', bool $disabled = false, string $disabled_title = '' ): string {
	$recipe_id = absint( $recipe_id );
	if ( $recipe_id < 1 ) {
		return '';
	}

	$surface = in_array( $surface, array( 'card', 'single' ), true ) ? $surface : 'card';
	$is_fav  = _180c_favorite_is_marked( $recipe_id );

	if ( '' === $disabled_title ) {
		$disabled_title = __( 'Réservé aux abonnés', '180c' );
	}

	$label_add    = __( 'Ajouter à mon carnet', '180c' );
	$label_remove = __( 'Retirer de mon carnet', '180c' );
	$current      = $is_fav ? $label_remove : $label_add;

	$btn_class = 'favorite-button favorite-button--' . $surface . ( $disabled ? ' is-disabled' : '' );

	// Popover « carnet » : visiteurs non connectés uniquement. L'ID du panneau
	// doit rester unique sur la page — une même recette peut apparaître
	// plusieurs fois (hero + rail + grille).
	//
	// `wp_unique_id()` et non un compteur `static` local : le compteur repartait
	// de zéro à chaque processus et, surtout, ne connaissait que SES propres
	// appels. Tout autre rendu du même panneau — module dupliqué, fragment
	// produit par un second passage, contenu assemblé hors de cette fonction —
	// pouvait reproduire la même valeur et créer un doublon d'`id`, qui casse
	// silencieusement l'`aria-describedby` (le lecteur d'écran ne résout que la
	// PREMIÈRE occurrence). Le compteur de `wp_unique_id()` est global à la
	// requête, donc partagé par tous les appelants.
	$show_hint = ! is_user_logged_in();
	$panel_id  = '';

	if ( $show_hint ) {
		$panel_id = wp_unique_id( 'carnet-hint-' . $recipe_id . '-' );
	}

	// Cœur outline (non favori) et cœur plein (favori) — formes distinctes,
	// permutées en CSS selon aria-pressed (état non porté par la couleur seule).
	$heart_outline = '<svg class="favorite-button__heart favorite-button__heart--outline" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';
	$heart_filled  = '<svg class="favorite-button__heart favorite-button__heart--filled" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';

	ob_start();
	?>
	<button
		type="button"
		class="<?php echo esc_attr( $btn_class ); ?>"
		<?php if ( ! $disabled ) : ?>
		data-favorite-toggle
		<?php else : ?>
		disabled
		aria-disabled="true"
		title="<?php echo esc_attr( $disabled_title ); ?>"
		<?php endif; ?>
		data-recipe-id="<?php echo esc_attr( (string) $recipe_id ); ?>"
		data-favorite-surface="<?php echo esc_attr( $surface ); ?>"
		data-label-add="<?php echo esc_attr( $label_add ); ?>"
		data-label-remove="<?php echo esc_attr( $label_remove ); ?>"
		aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>"
		aria-label="<?php echo esc_attr( $current ); ?>"
		<?php // Bouton actif (surface « card ») : il reste focusable, c'est donc lui qui porte la description. Sur un bouton désactivé, elle est portée par le wrapper, seul élément atteignable. ?>
		<?php if ( $show_hint && ! $disabled ) : ?>
		aria-describedby="<?php echo esc_attr( $panel_id ); ?>"
		<?php endif; ?>
	>
		<span class="favorite-button__icon" aria-hidden="true">
			<?php
			echo $heart_outline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique contrôlé.
			echo $heart_filled;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique contrôlé.
			?>
		</span>
		<?php if ( 'single' === $surface ) : ?>
			<span class="favorite-button__label"><?php echo esc_html( $current ); ?></span>
		<?php endif; ?>
		<span class="favorite-button__status" data-favorite-status role="status" aria-live="polite"></span>
	</button>
	<?php
	$button_html = (string) ob_get_clean();

	if ( ! $show_hint ) {
		return $button_html;
	}

	return _180c_render_carnet_hint( $button_html, $surface, $disabled, $panel_id );
}

// ============================================================
// Rendu de la page « Mon carnet » (/mon-carnet/)
// ============================================================

/**
 * Rend le contenu de la page « Mon carnet » (favoris du user courant).
 *
 * Une seule requête principale : `_180c_get_user_favorite_ids()` puis une
 * `WP_Query` `post__in` ordonnée (created_at DESC préservé via
 * `orderby => post__in`). Toutes les recettes du carnet sont rendues d'un bloc
 * (cap dur `_180C_CARNET_MAX` — jamais `posts_per_page => -1`) afin d'alimenter
 * le filtrage / la recherche **côté client instantané** (module JS
 * `mon-carnet-filters`). Chaque carte porte `data-recipe-types`,
 * `data-recipe-seasons` et `data-search` (titre normalisé) pour ce filtrage.
 *
 * État vide gracieux + CTA si aucun favori. Le gating connecté et le noindex
 * sont portés par le template appelant (template-mon-carnet.php).
 *
 * @return string HTML du <main>.
 */
function _180c_render_mon_carnet(): string {
	// Cap dur : couvre les carnets réalistes sans jamais charger -1. Au-delà,
	// les favoris les plus récents priment (ordre post__in = created_at DESC).
	$max = defined( '_180C_CARNET_MAX' ) ? (int) _180C_CARNET_MAX : 120;

	$favorite_ids = function_exists( '_180c_get_user_favorite_ids' )
		? _180c_get_user_favorite_ids( get_current_user_id() )
		: array();

	$query = null;
	if ( ! empty( $favorite_ids ) ) {
		$query = new WP_Query(
			array(
				'post_type'           => 'recipe',
				'post_status'         => 'publish',
				'post__in'            => $favorite_ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => $max,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);
	}

	// Construit la grille tout en collectant les termes réellement présents
	// (on n'affiche un filtre que pour les valeurs qui existent dans le carnet).
	$grid_items     = '';
	$type_present   = array(); // slug => WP_Term.
	$season_present = array(); // slug => WP_Term.

	if ( $query && $query->have_posts() ) {
		foreach ( $query->posts as $recipe_post ) {
			$pid       = (int) $recipe_post->ID;
			$card_html = _180c_render_recipe_card( $pid, 'md' );
			if ( ! $card_html ) {
				continue;
			}

			$type_slugs = array();
			$types      = get_the_terms( $pid, 'recipe_category' );
			if ( $types && ! is_wp_error( $types ) ) {
				foreach ( $types as $term ) {
					$type_slugs[]                = $term->slug;
					$type_present[ $term->slug ] = $term;
				}
			}

			$season_slugs = array();
			$seasons      = get_the_terms( $pid, 'recipe_season' );
			if ( $seasons && ! is_wp_error( $seasons ) ) {
				foreach ( $seasons as $term ) {
					$season_slugs[]                = $term->slug;
					$season_present[ $term->slug ] = $term;
				}
			}

			$grid_items .= sprintf(
				'<li class="recipes-grid__item" role="listitem" data-carnet-item data-recipe-types="%1$s" data-recipe-seasons="%2$s" data-search="%3$s">%4$s</li>',
				esc_attr( implode( ' ', $type_slugs ) ),
				esc_attr( implode( ' ', $season_slugs ) ),
				esc_attr( _180c_normalize_search( get_the_title( $pid ) ) ),
				$card_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans le renderer.
			);
		}
	}

	// Ordre saisons : chronologique si reconnu, sinon alpha (fallback).
	$season_order = array( 'printemps', 'ete', 'automne', 'hiver' );
	uksort(
		$season_present,
		static function ( $a, $b ) use ( $season_order ) {
			$ia = array_search( $a, $season_order, true );
			$ib = array_search( $b, $season_order, true );
			$ia = ( false === $ia ) ? PHP_INT_MAX : $ia;
			$ib = ( false === $ib ) ? PHP_INT_MAX : $ib;
			return $ia <=> $ib;
		}
	);
	// Types : ordre alphabétique.
	uasort(
		$type_present,
		static function ( $a, $b ) {
			return strcoll( $a->name, $b->name );
		}
	);

	// URL de l'archive « Toutes les recettes » (CTA miroir du bouton « Mon
	// carnet de recettes » présent sur cette archive).
	$archive_url = get_post_type_archive_link( 'recipe' );
	if ( ! $archive_url ) {
		$archive_url = home_url( '/toutes-les-recettes/' );
	}

	ob_start();
	?>
	<main id="main" class="site-main site-container mon-carnet">

		<header class="archive-header">
			<div class="container-180c">
				<div class="archive-header__bar">
					<h1 class="archive-header__title">
						<?php esc_html_e( 'Mon carnet de recettes', '180c' ); ?>
					</h1>
					<a
						class="btn btn--ghost archive-header__action"
						href="<?php echo esc_url( $archive_url ); ?>"
					>
						<?php esc_html_e( 'Toutes les recettes', '180c' ); ?>
					</a>
				</div>
			</div>
		</header>

		<?php if ( '' !== $grid_items ) : ?>

			<form
				class="recipe-filters recipe-filters--explore recipe-filters--carnet"
				data-carnet-filters
				role="search"
				aria-label="<?php esc_attr_e( 'Filtrer et rechercher dans mon carnet', '180c' ); ?>"
				onsubmit="return false;"
			>
				<div class="container-180c">
					<div class="recipe-filters__bar">

						<div class="recipe-filters__group--search">
							<label class="screen-reader-text" for="carnet-search">
								<?php esc_html_e( 'Rechercher une recette par mot-clé', '180c' ); ?>
							</label>
							<input
								type="search"
								id="carnet-search"
								class="recipe-filters__search"
								data-carnet-search
								placeholder="<?php esc_attr_e( 'Trouver une recette dans mon carnet…', '180c' ); ?>"
								autocomplete="off"
							/>
						</div>

						<?php
						$carnet_dropdowns = array(
							'type'   => array(
								'label' => __( 'Type de plat', '180c' ),
								'terms' => $type_present,
							),
							'season' => array(
								'label' => __( 'Saison', '180c' ),
								'terms' => $season_present,
							),
						);
						foreach ( $carnet_dropdowns as $carnet_key => $carnet_group ) :
							if ( empty( $carnet_group['terms'] ) ) {
								continue;
							}
							$carnet_panel_id = 'carnet-dd-panel-' . $carnet_key;
							?>
						<div
							class="recipe-filters__dropdown"
							data-carnet-dropdown
							data-filter-group="<?php echo esc_attr( $carnet_key ); ?>"
						>
							<button
								type="button"
								class="recipe-filters__dropdown-toggle"
								data-carnet-dropdown-toggle
								aria-expanded="false"
								aria-haspopup="true"
								aria-controls="<?php echo esc_attr( $carnet_panel_id ); ?>"
							>
								<span class="recipe-filters__dropdown-label"><?php echo esc_html( $carnet_group['label'] ); ?></span>
								<span class="recipe-filters__dropdown-count" data-carnet-dropdown-count hidden></span>
								<svg class="recipe-filters__dropdown-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
									<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
								</svg>
							</button>
							<div
								class="recipe-filters__dropdown-panel"
								id="<?php echo esc_attr( $carnet_panel_id ); ?>"
								data-carnet-dropdown-panel
								role="group"
								aria-label="<?php echo esc_attr( $carnet_group['label'] ); ?>"
								hidden
							>
								<ul class="recipe-filters__options" role="list">
								<?php foreach ( $carnet_group['terms'] as $carnet_term ) : ?>
									<li class="recipe-filters__option-item" role="listitem">
										<label class="recipe-filters__option">
											<input
												type="checkbox"
												class="recipe-filters__checkbox"
												data-carnet-filter
												data-filter-group="<?php echo esc_attr( $carnet_key ); ?>"
												data-filter-value="<?php echo esc_attr( $carnet_term->slug ); ?>"
											/>
											<span class="recipe-filters__option-label"><?php echo esc_html( $carnet_term->name ); ?></span>
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
							data-carnet-reset
							hidden
						>
							<?php esc_html_e( 'Tout effacer', '180c' ); ?>
						</button>

					</div>
				</div>
			</form>

			<div class="container-180c">
				<p class="mon-carnet__count" data-carnet-count role="status" aria-live="polite"></p>

				<ul class="recipes-grid" role="list" data-carnet-grid>
					<?php echo $grid_items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- items déjà échappés ci-dessus. ?>
				</ul>

				<div class="mon-carnet__noresults" data-carnet-noresults hidden>
					<p class="mon-carnet__noresults-msg">
						<?php esc_html_e( 'Aucune recette ne correspond à votre recherche.', '180c' ); ?>
					</p>
					<button type="button" class="btn btn--ghost" data-carnet-reset>
						<?php esc_html_e( 'Réinitialiser les filtres', '180c' ); ?>
					</button>
				</div>
			</div>

		<?php else : ?>

			<div class="container-180c">
				<?php
				get_template_part(
					'parts/empty-state',
					null,
					array(
						'title'     => __( 'Votre carnet est vide', '180c' ),
						'message'   => __( 'Ajoutez des recettes à votre carnet en cliquant sur le cœur depuis n\'importe quelle fiche recette.', '180c' ),
						'cta_url'   => home_url( '/recettes/' ),
						'cta_label' => __( 'Explorer les recettes', '180c' ),
					)
				);
				?>
			</div>

		<?php endif; ?>

	</main>
	<?php
	wp_reset_postdata();

	return (string) ob_get_clean();
}

/**
 * Normalise une chaîne pour la recherche client (minuscule, sans accents).
 *
 * Mise en miroir de la normalisation JS (suppression des diacritiques) afin que
 * la recherche du carnet soit insensible à la casse et aux accents.
 *
 * @param string $value Chaîne brute.
 * @return string Chaîne normalisée.
 */
function _180c_normalize_search( string $value ): string {
	$value = wp_strip_all_tags( $value );
	$value = remove_accents( $value );
	return strtolower( trim( $value ) );
}

// ============================================================
// Horodatage (UTC)
// ============================================================

/**
 * Datetime MySQL « maintenant » en UTC (Y-m-d H:i:s).
 *
 * @return string
 */
function _180c_favorites_now_gmt(): string {
	return current_time( 'mysql', true );
}

/**
 * Normalise un horodatage client (ISO 8601 ou epoch) en datetime MySQL UTC.
 *
 * Tolère : chaîne ISO 8601 (`2026-05-20T10:00:00Z`), epoch en secondes, epoch
 * en millisecondes. Toute valeur absente ou illisible retombe sur l'instant
 * courant (le changement est alors traité comme « tout juste survenu »).
 *
 * @param mixed $value Horodatage brut transmis par le client.
 * @return array{mysql:string,epoch:int} Datetime MySQL UTC + epoch en secondes.
 */
function _180c_favorites_normalize_ts( $value ): array {
	$epoch = 0;

	if ( is_numeric( $value ) ) {
		$num = (float) $value;
		// Heuristique : au-delà de l'an ~2286 en secondes, c'est des millisecondes.
		$epoch = ( $num > 9999999999 ) ? (int) round( $num / 1000 ) : (int) $num;
	} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
		$parsed = strtotime( $value );
		$epoch  = false !== $parsed ? $parsed : 0;
	}

	if ( $epoch <= 0 ) {
		$epoch = time();
	}

	return array(
		'mysql' => gmdate( 'Y-m-d H:i:s', $epoch ),
		'epoch' => $epoch,
	);
}

/**
 * Convertit un datetime MySQL stocké (interprété UTC) en epoch secondes.
 *
 * @param string|null $datetime Datetime MySQL (Y-m-d H:i:s) ou null.
 * @return int Epoch en secondes, 0 si vide/illisible.
 */
function _180c_favorites_datetime_to_epoch( $datetime ): int {
	if ( empty( $datetime ) ) {
		return 0;
	}
	$epoch = strtotime( $datetime . ' UTC' );
	return false !== $epoch ? $epoch : 0;
}

// ============================================================
// Écritures (add / remove) — maintien des tombstones
// ============================================================

/**
 * Ajoute un favori (et purge sa pierre tombale) de façon idempotente.
 *
 * Service d'écriture partagé : consommé par le callback REST POST /favorites/
 * (inc/rest/favorites.php), par le sync last-write-wins, et par le deeplink
 * /mon-carnet/?add={slug} (inc/carnet-deeplink.php).
 *
 * Idempotence : si le favori actif existe déjà, on renvoie 'already' SANS
 * exécuter l'INSERT ON DUPLICATE — ce qui éviterait sinon de bumper created_at
 * et de faire remonter la recette en tête du carnet (tri par date d'ajout). Un
 * re-clic du deeplink est ainsi sans effet sur l'ordre du carnet.
 *
 * @param int         $user_id   ID utilisateur.
 * @param int         $recipe_id ID recette.
 * @param string|null $when_gmt  Datetime MySQL UTC du changement (défaut : maintenant).
 * @return string 'added' si nouvel ajout, 'already' si le favori existait déjà.
 */
function _180c_favorites_add( int $user_id, int $recipe_id, ?string $when_gmt = null ): string {
	global $wpdb;

	$active = _180c_favorites_table();
	$tomb   = _180c_favorites_tombstones_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM {$active} WHERE user_id = %d AND recipe_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id,
			$recipe_id
		)
	);

	if ( $exists ) {
		return 'already';
	}

	$when = $when_gmt ?? _180c_favorites_now_gmt();

	// ON DUPLICATE conservé comme garde-fou anti-collision (course SELECT→INSERT
	// entre deux requêtes concurrentes) ; le cas nominal passe par l'INSERT.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$active} (user_id, recipe_id, created_at) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id,
			$recipe_id,
			$when
		)
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->delete(
		$tomb,
		array(
			'user_id'   => $user_id,
			'recipe_id' => $recipe_id,
		),
		array( '%d', '%d' )
	);

	return 'added';
}

/**
 * Retire un favori et écrit (ou rafraîchit) sa pierre tombale.
 *
 * @param int         $user_id   ID utilisateur.
 * @param int         $recipe_id ID recette.
 * @param string|null $when_gmt  Datetime MySQL UTC du changement (défaut : maintenant).
 * @return void
 */
function _180c_favorites_remove( int $user_id, int $recipe_id, ?string $when_gmt = null ): void {
	global $wpdb;

	$when   = $when_gmt ?? _180c_favorites_now_gmt();
	$active = _180c_favorites_table();
	$tomb   = _180c_favorites_tombstones_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->delete(
		$active,
		array(
			'user_id'   => $user_id,
			'recipe_id' => $recipe_id,
		),
		array( '%d', '%d' )
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$tomb} (user_id, recipe_id, removed_at) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE removed_at = VALUES(removed_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id,
			$recipe_id,
			$when
		)
	);
}

// ============================================================
// Purge utilisateur (RGPD) — hook account_deleted
// ============================================================

/**
 * Supprime favoris actifs ET tombstones d'un utilisateur.
 *
 * Branché sur l'action `180c/account_deleted` (cf. inc/auth/account-delete.php)
 * pour compléter la suppression RGPD côté tombstones, que ce module ne possède
 * pas directement dans le flux Mon Compte.
 *
 * @param int $user_id ID utilisateur supprimé.
 * @return void
 */
function _180c_favorites_purge_user( int $user_id ): void {
	global $wpdb;

	if ( $user_id <= 0 ) {
		return;
	}

	foreach ( array( _180c_favorites_table(), _180c_favorites_tombstones_table() ) as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
	}
}
add_action( '180c/account_deleted', '_180c_favorites_purge_user' );

// ============================================================
// Purge des tombstones anciennes (cron mensuel — borne la croissance)
// ============================================================

/**
 * Supprime les pierres tombales de plus de 180 jours.
 *
 * Au-delà, aucun client hors-ligne ne se resynchronise de façon réaliste : la
 * tombstone n'a plus d'utilité pour le last-write-wins et peut être purgée.
 *
 * @return void
 */
function _180c_favorites_tombstones_cleanup(): void {
	if ( ! _180c_favorites_tables_ready() ) {
		return;
	}

	global $wpdb;
	$tomb = _180c_favorites_tombstones_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$deleted = $wpdb->query( "DELETE FROM {$tomb} WHERE removed_at < DATE_SUB( UTC_TIMESTAMP(), INTERVAL 180 DAY )" );

	_180c_log( 'Favorites tombstones cleanup done.', array( 'deleted' => absint( $deleted ) ) );
}
add_action( '_180c_favorites_tombstones_cleanup', '_180c_favorites_tombstones_cleanup' );

/**
 * Planifie le cron mensuel de purge des tombstones si nécessaire.
 *
 * Réutilise la récurrence « monthly » déclarée dans inc/auth/rate-limit.php.
 *
 * @return void
 */
function _180c_favorites_schedule_cleanup(): void {
	if ( ! wp_next_scheduled( '_180c_favorites_tombstones_cleanup' ) ) {
		wp_schedule_event( time(), 'monthly', '_180c_favorites_tombstones_cleanup' );
	}
}
add_action( 'init', '_180c_favorites_schedule_cleanup' );
