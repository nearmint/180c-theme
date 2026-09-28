<?php
/**
 * Hygiène d'indexation — désindexation des surfaces sans valeur (ITEM 7).
 *
 * Audit Google Search Console du 2026-07-30 : l'index contient un volume
 * important d'URLs qui ne peuvent rien gagner et qui diluent le budget de
 * crawl — paginations profondes, URLs paramétrées héritées d'un plugin
 * supprimé, archives de tags quasi vides. Ce module les sort de l'index sans
 * les rendre inaccessibles.
 *
 * ## Pourquoi `180c/seo_robots` et non `wp_robots`
 *
 * Le brief de l'ITEM 7 demandait un filtre `wp_robots`. C'est le point
 * d'extension standard de WordPress, mais il est **inopérant sur ce thème** :
 * l'ITEM 2c a désenregistré la sortie du core (`remove_action( 'wp_head',
 * 'wp_robots', 1 )`, fin de `inc/seo/meta-tags.php`) pour qu'une seule balise
 * `<meta name="robots">` soit émise, celle de `_180c_render_meta()`. Un filtre
 * posé sur `wp_robots` ne serait donc jamais rendu en front.
 *
 * Le point d'extension réellement en vigueur est `180c/seo_robots`, déjà
 * employé par `_180c_seo_noindex_transactional()` (pages Woo) et
 * `_180c_seo_no_index_override()` (case ACF). On s'y branche.
 *
 * ## Convention `follow`
 *
 * Toutes les règles produisent `noindex, follow`, jamais `nofollow` : les pages
 * visées restent des nœuds de navigation utiles (une page 12 d'archive mène à
 * douze articles). On refuse leur indexation, pas leur crawl. Un `nofollow`
 * couperait la circulation vers des contenus qui, eux, doivent être indexés.
 *
 * ## Priorité 20 et principe de non-affaiblissement
 *
 * Le filtre s'exécute après les callbacks de priorité par défaut, et ne
 * remplace jamais une directive contenant déjà `noindex` : un `noindex,
 * nofollow` (404, brouillon) resterait sinon affaibli en `noindex, follow`.
 *
 * ## Hors périmètre, à dessein
 *
 * - **Archives auteur page 1** : indexables, inchangées. Elles captent des
 *   requêtes nominatives réelles (relevé Search Console sur 90 jours). Seules
 *   leurs paginations tombent, via `is_paged()`.
 * - **`is_search()` et `is_date()`** : déjà en `noindex, follow` dans
 *   `_180c_render_meta()`. Non dupliqué ici — deux sources concurrentes pour
 *   une même décision valent moins qu'une seule.
 * - **`recipe_tag`** : figure délibérément au sitemap depuis l'ITEM 4. Seul
 *   `post_tag` est visé, que l'ITEM 4 a retiré du sitemap sans le désindexer.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Paramètres d'URL tolérés sans déclencher la désindexation.
 *
 * Liste blanche volontairement minimale : toute URL portant au moins un
 * paramètre absent de cette liste bascule en `noindex, follow`. L'approche est
 * « tout est refusé sauf » plutôt que « tout est permis sauf », parce que la
 * liste des paramètres parasites n'est pas connue à l'avance — l'audit GSC a
 * relevé `?rcp_action=lostpassword`, résidu de Restrict Content Pro, plugin
 * supprimé depuis, que personne n'aurait pensé à interdire.
 *
 * Entrées et justification :
 *
 *  - `page`, `paged` : pagination WordPress exprimée en query var plutôt qu'en
 *    segment de chemin. Son sort d'indexation est déjà tranché par
 *    `is_paged()` ci-dessous ; la tolérer ici évite qu'une même page soit
 *    désindexée par deux règles pour deux motifs différents, ce qui rendrait
 *    tout diagnostic futur illisible.
 *
 * Les paramètres de prévisualisation sont traités à part, dans
 * `_180c_robots_query_string_is_clean()` : ils ne sont tolérés que pour un
 * utilisateur connecté.
 *
 * @return string[] Noms de paramètres tolérés.
 */
function _180c_robots_allowed_query_vars(): array {
	$allowed = array( 'page', 'paged' );

	/**
	 * Filtre la liste blanche des paramètres d'URL n'entraînant pas de `noindex`.
	 *
	 * @param string[] $allowed Noms de paramètres tolérés.
	 */
	return (array) apply_filters( '180c/robots_allowed_query_vars', $allowed ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Paramètres de prévisualisation, tolérés pour les seuls utilisateurs connectés.
 *
 * Une prévisualisation n'est servie qu'à un utilisateur authentifié disposant
 * du droit d'édition : Googlebot ne la voit jamais. La condition de connexion
 * n'est donc pas une protection — c'est une garantie que ces paramètres ne
 * puissent pas servir de porte dérobée pour faire indexer une URL paramétrée
 * arbitraire en la déguisant en prévisualisation.
 *
 * @return string[] Noms de paramètres de prévisualisation.
 */
function _180c_robots_preview_query_vars(): array {
	return array( 'preview', 'preview_id', 'preview_nonce', 'p', 'page_id', 'post_type' );
}

/**
 * Indique si la query string de la requête courante est exempte de parasites.
 *
 * @return bool Vrai si aucun paramètre, ou uniquement des paramètres tolérés.
 */
function _180c_robots_query_string_is_clean(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture des seuls NOMS de paramètres pour décider d'une directive robots ; aucune valeur n'est lue ni aucune action déclenchée.
	$keys = array_keys( (array) $_GET );

	if ( empty( $keys ) ) {
		return true;
	}

	$allowed = _180c_robots_allowed_query_vars();

	if ( is_user_logged_in() ) {
		$allowed = array_merge( $allowed, _180c_robots_preview_query_vars() );
	}

	foreach ( $keys as $key ) {
		if ( ! in_array( (string) $key, $allowed, true ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Indique si la page courante doit sortir de l'index.
 *
 * Quatre motifs, tous relevés dans l'audit GSC du 2026-07-30 :
 *
 *  1. **URL paramétrée hors liste blanche.** Trois URLs indexées portaient
 *     `?rcp_action=lostpassword` sur des paginations de `/la-gazette/`,
 *     résidu de Restrict Content Pro. Leur canonical déclarait déjà la bonne
 *     URL : Google l'a ignorée. Un `noindex` est le seul signal impératif.
 *  2. **Archives de tags.** `post_tag` compte 14 termes en production, dont 6
 *     seulement portent au moins un contenu (12 articles pour le plus fourni).
 *     Zéro clic et zéro impression sur 90 jours. L'ITEM 4 les a retirées du
 *     sitemap sans les désindexer : la désindexation ferme la boucle.
 *  3. **Paginations (`is_paged()`).** Des centaines d'URLs `/page/N/` indexées pour
 *     quasiment aucun clic sur 90 jours, soit un rendement dérisoire au regard du
 *     budget de crawl consommé. La page 1 de chaque archive reste indexable et
 *     concentre la valeur.
 *  4. **Recherche interne.** Déjà couverte en amont (voir l'en-tête de
 *     fichier) ; mentionnée ici pour que l'inventaire des surfaces traitées
 *     soit complet.
 *
 * @return bool
 */
function _180c_robots_should_noindex(): bool {
	if ( ! _180c_robots_query_string_is_clean() ) {
		return true;
	}

	if ( is_tag() ) {
		return true;
	}

	if ( is_paged() ) {
		return true;
	}

	return false;
}

/**
 * Applique la désindexation des surfaces sans valeur.
 *
 * N'affaiblit jamais une directive existante : si `noindex` est déjà posé
 * (404, brouillon, page transactionnelle), la valeur amont est renvoyée telle
 * quelle, `nofollow` compris.
 *
 * @param string $robots Directive robots calculée en amont.
 * @return string
 */
function _180c_robots_index_hygiene( $robots ) {
	if ( is_admin() || is_feed() ) {
		return $robots;
	}

	if ( _180c_seo_robots_has( $robots, 'noindex' ) ) {
		return $robots;
	}

	return _180c_robots_should_noindex() ? 'noindex, follow' : $robots;
}
add_filter( '180c/seo_robots', '_180c_robots_index_hygiene', 20 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespace de hooks 180c/ imposé par CLAUDE.md.
