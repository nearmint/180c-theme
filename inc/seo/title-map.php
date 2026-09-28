<?php
/**
 * Table de correspondance des `<title>` — pages statiques et taxonomies.
 *
 * Source de vérité **en PHP**, jamais en base : un title est une décision
 * éditoriale versionnée, pas une donnée de contenu. La seule exception est le
 * qualificatif des catégories de La Gazette, surchargeable par la rédaction via
 * la term meta `_180c_seo_qualifier` (cf. _180c_title_term_entry()).
 *
 * Chaque entrée est un tuple :
 *
 *     array( 'subject' => string, 'qualifier' => string, 'args' => array )
 *
 * Un `qualifier` présent mais vide signifie « mappé, volontairement sans
 * qualificatif » — à distinguer d'une absence de mapping, qui déclenche le
 * repli générique de la taxonomie.
 *
 * Les compteurs `{N}` sont substitués à l'exécution depuis `$term->count`,
 * jamais figés ici.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Jeton remplacé par le nombre de contenus du terme (`$term->count`).
 *
 * @var string
 */
const _180C_TITLE_COUNT_TOKEN = '{N}';

/*
 * ---------------------------------------------------------------------------
 * 1. Pages statiques (clé = slug de page, ou clé virtuelle)
 * ---------------------------------------------------------------------------
 */

/**
 * Table des pages statiques, indexée par `post_name`.
 *
 * Les clés `_front` (accueil) et `_recipe_archive` (archive du CPT recipe,
 * `/toutes-les-recettes/`) sont virtuelles : ces deux surfaces ne sont pas des
 * pages WordPress.
 *
 * @return array<string, array{subject:string, qualifier:string, args:array}>
 */
function _180c_title_map_pages(): array {
	$map = array(
		// Accueil — exception assumée : marque en tête, pas de suffixe, pas de
		// troncature. Le qualificatif a perdu « cuisine » le 2026-08-03 : à
		// 65 caractères le title était tronqué en SERP, et c'est le mot le moins
		// discriminant des trois (une revue culinaire ne parle que de ça).
		// 56 caractères, sous la limite d'affichage de Google.
		'_front'                    => array(
			'subject'   => '180°C Culture Food',
			'qualifier' => 'Recettes de saison et vins sincères',
			'args'      => array(
				'no_brand'    => true,
				'no_truncate' => true,
			),
		),
		// Archive du CPT recipe (has_archive => 'toutes-les-recettes').
		'_recipe_archive'           => array(
			'subject'   => 'Toutes les recettes',
			'qualifier' => 'Index complet et filtres',
			'args'      => array(),
		),
		'a-propos'                  => array(
			'subject'   => 'À propos',
			'qualifier' => 'Une édition culinaire indépendante',
			'args'      => array(),
		),
		'abonnement'                => array(
			'subject'   => 'Abonnement',
			'qualifier' => '1 500 recettes, sans engagement',
			'args'      => array(),
		),
		'offrir-un-abonnement'      => array(
			'subject'   => 'Offrir un abonnement',
			'qualifier' => 'Idée cadeau gourmande',
			'args'      => array(),
		),
		'boutique'                  => array(
			'subject'   => 'Boutique',
			'qualifier' => 'Revues, livres et hors-séries',
			'args'      => array(),
		),
		'la-gazette'                => array(
			'subject'   => 'La Gazette',
			'qualifier' => 'Actualité du vin et de l’alimentation',
			'args'      => array(),
		),
		'recettes'                  => array(
			'subject'   => 'Recettes de saison',
			'qualifier' => '1 500+ idées testées',
			'args'      => array(),
		),
		'newsletter'                => array(
			'subject'   => 'Les Cahiers de Delphine',
			'qualifier' => 'Newsletter recettes',
			'args'      => array(),
		),
		'contact'                   => array(
			'subject'   => 'Contact',
			'qualifier' => 'Écrire à la rédaction',
			'args'      => array(),
		),
		'centre-daide'              => array(
			'subject'   => 'Centre d’aide',
			'qualifier' => 'Commande, abonnement, ePub',
			'args'      => array(),
		),
		'mon-carnet'                => array(
			'subject'   => 'Mon carnet de recettes',
			'qualifier' => '',
			'args'      => array(),
		),
		'plan-du-site'              => array(
			'subject'   => 'Plan du site',
			'qualifier' => 'Toutes les rubriques',
			'args'      => array(),
		),
		'mentions-legales'          => array(
			'subject'   => 'Mentions légales',
			'qualifier' => '',
			'args'      => array(),
		),
		'cgv'                       => array(
			'subject'   => 'Conditions générales d’utilisation et de vente',
			'qualifier' => '',
			'args'      => array(),
		),
		'politique-confidentialite' => array(
			'subject'   => 'Politique de confidentialité',
			'qualifier' => '',
			'args'      => array(),
		),
	);

	/**
	 * Filtre la table des titles de pages statiques.
	 *
	 * @param array $map Table indexée par slug de page.
	 */
	return (array) apply_filters( '180c/title_map_pages', $map ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/*
 * ---------------------------------------------------------------------------
 * 2. Taxonomies (clé = slug de terme)
 * ---------------------------------------------------------------------------
 */

/**
 * Table des titles de termes, indexée par taxonomie puis par slug.
 *
 * Les taxonomies sont désignées par leur **nom d'enregistrement**
 * (`recipe_category`…), pas par leur slug de réécriture d'URL
 * (`categorie-recette`…).
 *
 * @return array<string, array<string, array{subject:string, qualifier:string}>>
 */
function _180c_title_map_terms(): array {
	$map = array(
		'recipe_category'    => array(
			'plat'           => array(
				'subject'   => 'Recettes de plats',
				'qualifier' => '{N} idées de saison',
			),
			'dessert'        => array(
				'subject'   => 'Recettes de desserts',
				'qualifier' => '{N} idées gourmandes',
			),
			'entree'         => array(
				'subject'   => 'Recettes d’entrées',
				'qualifier' => '{N} idées de saison',
			),
			'accompagnement' => array(
				'subject'   => 'Recettes d’accompagnement',
				'qualifier' => '{N} idées de saison',
			),
			'apero'          => array(
				'subject'   => 'Recettes apéro',
				'qualifier' => '{N} idées à partager',
			),
			'petit-dejeuner' => array(
				'subject'   => 'Recettes de petit-déjeuner',
				'qualifier' => '{N} idées maison',
			),
			'boisson'        => array(
				'subject'   => 'Recettes de boissons',
				'qualifier' => '{N} idées maison',
			),
		),
		'recipe_season'      => array(
			'ete'          => array(
				'subject'   => 'Recettes d’été',
				'qualifier' => '{N} idées à cuisiner',
			),
			'hiver'        => array(
				'subject'   => 'Recettes d’hiver',
				'qualifier' => '{N} idées à cuisiner',
			),
			'automne'      => array(
				'subject'   => 'Recettes d’automne',
				'qualifier' => '{N} idées à cuisiner',
			),
			'printemps'    => array(
				'subject'   => 'Recettes de printemps',
				'qualifier' => '{N} idées à cuisiner',
			),
			'toute-saison' => array(
				'subject'   => 'Recettes toute l’année',
				'qualifier' => '{N} basiques',
			),
		),
		'recipe_publication' => array(
			'cahiers-de-delphine' => array(
				'subject'   => 'Les Cahiers de Delphine',
				'qualifier' => 'Toutes les recettes',
			),
			'180c'                => array(
				'subject'   => 'Recettes de la revue 180°C',
				'qualifier' => 'Des recettes et des hommes',
			),
			'12degres5'           => array(
				'subject'   => 'Recettes 12°5',
				'qualifier' => 'Cuisine et accords vins',
			),
			'selections'          => array(
				'subject'   => 'Sélections de recettes',
				'qualifier' => 'Menus et thématiques',
			),
			// `non-classe` : aucun mapping, repli générique + noindex.
		),
		'category'           => array(
			'opinion'                 => array(
				'subject'   => 'Actus et opinions',
				'qualifier' => 'La Gazette',
			),
			'agenda-evenement'        => array(
				'subject'   => 'Agenda et événements gourmands',
				'qualifier' => '',
			),
			'carnet-de-croute'        => array(
				'subject'   => 'Carnet de croûte',
				'qualifier' => 'Destinations gourmandes',
			),
			'culture-food'            => array(
				'subject'   => 'Culture food',
				'qualifier' => 'Histoires et savoirs culinaires',
			),
			'fait-maison'             => array(
				'subject'   => 'Fait maison',
				'qualifier' => 'Techniques et fermentations',
			),
			'lhomme-de-gout'          => array(
				'subject'   => 'L’homme de goût',
				'qualifier' => 'Portraits d’épicuriens',
			),
			'portraits'               => array(
				'subject'   => 'Portraits',
				'qualifier' => 'Chefs, artisans et vignerons',
			),
			'portraits-et-reportages' => array(
				'subject'   => 'Portraits et reportages',
				'qualifier' => 'La Gazette',
			),
			'reportages'              => array(
				'subject'   => 'Reportages',
				'qualifier' => 'Producteurs, chefs et terroirs',
			),
			'videos'                  => array(
				'subject'   => 'Vidéos',
				'qualifier' => 'Reportages et recettes en images',
			),
			// `la-gazette` : aucun mapping, noindex + canonical /la-gazette/.
		),
		'product_cat'        => array(
			'revues-180c'        => array(
				'subject'   => 'Les revues 180°C',
				'qualifier' => 'Des recettes et des hommes',
			),
			'revue-12-5'         => array(
				'subject'   => 'Les revues 12°5',
				'qualifier' => 'Des raisins et des hommes',
			),
			'grands-cahiers'     => array(
				'subject'   => 'Les Grands Cahiers',
				'qualifier' => 'Monographies culinaires',
			),
			'les-petits-cahiers' => array(
				'subject'   => 'Les Petits Cahiers',
				'qualifier' => 'Un produit, des recettes',
			),
			'publication'        => array(
				'subject'   => 'Livres et publications',
				'qualifier' => 'Boutique',
			),
			'epub'               => array(
				'subject'   => 'Livres numériques ePub',
				'qualifier' => 'Boutique',
			),
			// `abonnements` : aucun mapping, noindex + canonical /abonnement/.
		),
	);

	/**
	 * Filtre la table des titles de termes.
	 *
	 * @param array $map Table indexée par taxonomie puis par slug de terme.
	 */
	return (array) apply_filters( '180c/title_map_terms', $map ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Qualificatif de repli d'une taxonomie sans mapping pour le terme courant.
 *
 * `%s` est remplacé par le compteur du terme lorsqu'il est présent.
 *
 * @param string $taxonomy Nom de la taxonomie.
 * @return string Chaîne vide si la taxonomie n'a pas de repli.
 */
function _180c_title_taxonomy_fallback_qualifier( string $taxonomy ): string {
	$fallbacks = array(
		// Gabarit générique du brief : « {Nom du tag} — {N} recettes ».
		'recipe_tag' => '{N} recettes',
		// Rubriques éditoriales non mappées.
		'category'   => 'La Gazette',
	);

	/**
	 * Filtre les qualificatifs de repli par taxonomie.
	 *
	 * @param array $fallbacks Table taxonomie => qualificatif de repli.
	 */
	$fallbacks = (array) apply_filters( '180c/title_taxonomy_fallbacks', $fallbacks ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	return isset( $fallbacks[ $taxonomy ] ) ? (string) $fallbacks[ $taxonomy ] : '';
}

/**
 * Résout le couple sujet/qualificatif d'un terme.
 *
 * Ordre de résolution :
 *  1. sujet = mapping PHP, sinon nom du terme ;
 *  2. qualificatif = term meta `_180c_seo_qualifier` si saisie, sinon mapping
 *     PHP, sinon repli de taxonomie ;
 *  3. substitution du jeton `{N}` par `$term->count`.
 *
 * La term meta ne s'applique qu'aux taxonomies qui l'autorisent (`category`),
 * pour éviter qu'une saisie admin ne contredise silencieusement un mapping
 * éditorial versionné.
 *
 * @param WP_Term $term Terme courant.
 * @return array{subject:string, qualifier:string}
 */
function _180c_title_term_entry( WP_Term $term ): array {
	$map   = _180c_title_map_terms();
	$entry = isset( $map[ $term->taxonomy ][ $term->slug ] )
		? $map[ $term->taxonomy ][ $term->slug ]
		: null;

	$subject   = ( null !== $entry ) ? (string) $entry['subject'] : (string) $term->name;
	$qualifier = ( null !== $entry )
		? (string) $entry['qualifier']
		: _180c_title_taxonomy_fallback_qualifier( $term->taxonomy );

	// Surcharge rédactionnelle, uniquement sur les taxonomies autorisées.
	if ( in_array( $term->taxonomy, _180c_title_qualifier_meta_taxonomies(), true ) ) {
		$meta = trim( (string) get_term_meta( $term->term_id, '_180c_seo_qualifier', true ) );
		if ( '' !== $meta ) {
			$qualifier = $meta;
		}
	}

	$count     = (string) max( 0, (int) $term->count );
	$subject   = str_replace( _180C_TITLE_COUNT_TOKEN, $count, $subject );
	$qualifier = str_replace( _180C_TITLE_COUNT_TOKEN, $count, $qualifier );

	return array(
		'subject'   => $subject,
		'qualifier' => $qualifier,
	);
}

/**
 * Taxonomies dont le qualificatif est surchargeable par term meta.
 *
 * @return string[]
 */
function _180c_title_qualifier_meta_taxonomies(): array {
	/**
	 * Filtre la liste des taxonomies acceptant `_180c_seo_qualifier`.
	 *
	 * @param string[] $taxonomies Noms de taxonomies.
	 */
	return (array) apply_filters( '180c/title_qualifier_meta_taxonomies', array( 'category' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/*
 * ---------------------------------------------------------------------------
 * 3. Robots & canonicals pilotés par la table
 * ---------------------------------------------------------------------------
 */

/**
 * Termes forcés en `noindex`, sous la forme `taxonomie:slug`.
 *
 * @return string[]
 */
function _180c_title_noindex_terms(): array {
	$terms = array(
		'category:la-gazette',
		'product_cat:abonnements',
		'recipe_publication:non-classe',
	);

	/**
	 * Filtre la liste des termes en noindex.
	 *
	 * @param string[] $terms Identifiants `taxonomie:slug`.
	 */
	return (array) apply_filters( '180c/title_noindex_terms', $terms ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Canonicals de substitution pour les termes redondants, `taxonomie:slug`
 * => chemin relatif à la racine du site.
 *
 * @return array<string, string>
 */
function _180c_title_term_canonicals(): array {
	$canonicals = array(
		'category:la-gazette'     => '/la-gazette/',
		'product_cat:abonnements' => '/abonnement/',
	);

	/**
	 * Filtre les canonicals de substitution des termes.
	 *
	 * @param array $canonicals Table `taxonomie:slug` => chemin.
	 */
	return (array) apply_filters( '180c/title_term_canonicals', $canonicals ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Seuil de `noindex` des archives minces — **désactivé par défaut**.
 *
 * En dessous de ce nombre de contenus, une archive de taxonomie serait
 * considérée comme trop mince pour mériter l'indexation. Concernées à ce jour :
 * `categorie-recette/petit-dejeuner` (8) et `categorie-recette/boisson` (3).
 *
 * C'est une **décision éditoriale, pas technique** : le seuil reste à 0
 * (inactif) tant qu'elle n'est pas tranchée. Pour l'activer, filtrer :
 *
 *     add_filter( '180c/title_thin_archive_threshold', fn() => 15 );
 *
 * @return int 0 = désactivé.
 */
function _180c_title_thin_archive_threshold(): int {
	/**
	 * Filtre le seuil de noindex des archives minces.
	 *
	 * @param int $threshold Nombre de contenus minimum. 0 = fonctionnalité inactive.
	 */
	return (int) apply_filters( '180c/title_thin_archive_threshold', 0 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}
