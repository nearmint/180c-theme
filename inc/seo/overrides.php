<?php
/**
 * Overrides SEO saisis par la rédaction (`seo_title`, `seo_description`).
 *
 * Ce module **redonne la main** aux deux champs ACF du groupe
 * `group_content_seo`, que le chantier « le gabarit devient la seule source du
 * title » (f6c9808) avait débranchés.
 *
 * Pourquoi ce retour en arrière est assumé
 * ----------------------------------------
 * Le débranchement corrigeait un vrai défaut : une valeur fautive en base
 * contournait le gabarit **sans que rien ne le signale** (cf. le cas
 * « recette Pudding aux tomates et chorizo »). Mais il a aussi supprimé le seul
 * levier permettant de découpler le `<title>` du titre éditorial.
 *
 * Or l'audit GSC du 2026-07-30 montre que ce couplage coûte l'essentiel du
 * trafic organique : les titres à jeux de mots de La Gazette se positionnent
 * en page 1 et ne sont pas cliqués. Relevé sur 90 jours (2026-05-02 → 07-30) :
 *
 *   - `capres`
 *   - `fleur de capre`
 *   - `fideua`
 *   - `philippe mille`
 *   - `pied bleu`
 *   - `casserons`
 *
 * Un titre en position 1 presque jamais cliqué n'est pas un problème de
 * classement, c'est un problème de formulation. Aucun gabarit ne peut le
 * résoudre : seule une réécriture par contenu le peut.
 *
 * Ce qui change par rapport à l'ancien override
 * ---------------------------------------------
 * L'ancien mécanisme injectait `seo_title` **comme sujet** du gabarit, qui lui
 * ajoutait ensuite qualificatif, pagination et suffixe de marque : le résultat
 * final n'était donc jamais celui affiché dans le champ, et une saisie
 * maladroite produisait un title composite incompréhensible.
 *
 * Ici la valeur est servie **telle quelle**, sans le moindre assemblage. Ce qui
 * est saisi est ce qui sort. C'est vérifiable d'un coup d'œil dans un
 * view-source, et l'écran « Outils → Audit SEO (ACF) » liste à tout moment
 * l'intégralité des valeurs en base.
 *
 * Le champ reste vide par défaut : sans saisie, le comportement est
 * **strictement inchangé** (gabarit `inc/seo/titles.php` + tables
 * `title-map.php` / `description-map.php`).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Identifiant d'objet ACF de la surface courante.
 *
 * ACF adresse un post par son ID entier et un terme par la chaîne
 * `term_{id}` ; `get_field()` accepte aussi directement un `WP_Term`. On
 * renvoie ici l'identifiant canonique afin que les deux appelants (title et
 * description) visent exactement la même surface.
 *
 * Seuls les contextes qui portent un objet éditable sont couverts : contenus
 * singuliers et archives de taxonomie. Les archives de CPT, la recherche, les
 * pages auteur et la 404 n'ont pas d'objet en base à surcharger — leurs titles
 * restent pilotés par les tables versionnées.
 *
 * LA HOME EN FAIT PARTIE, ET C'EST CONTRE-INTUITIF
 * ------------------------------------------------
 * Une version antérieure de ce commentaire rangeait la home parmi les surfaces
 * NON surchargeables. C'est faux : `show_on_front = 'page'` (le réglage du
 * site), donc la home est une page WordPress, donc `is_singular()` y répond
 * vrai et `get_queried_object_id()` renvoie l'ID de cette page.
 *
 * Le title de l'accueil a par conséquent DEUX sources concurrentes :
 *
 *   1. la table versionnée `inc/seo/title-map.php`, entrée virtuelle `_front`,
 *      consommée par `_180c_title_resolve_context()` — priorité 5 ;
 *   2. le champ ACF `seo_title` de la page d'accueil, servi tel quel par
 *      `_180c_seo_title_acf_override()` — **priorité 4, donc gagnant**.
 *
 * Tant qu'un `seo_title` est renseigné sur la page d'accueil, toute
 * modification de `title-map.php` reste sans effet visible. C'est exactement le
 * cas de figure rencontré le 2026-08-03 : la base locale portait un override
 * absent de la production, et les deux environnements rendaient donc deux
 * titles différents pour le même code. L'écran « Outils → Audit SEO (ACF) »
 * liste les valeurs réellement en base et permet de trancher.
 *
 * @return int|string|null Identifiant ACF, ou null hors contexte surchargeable.
 */
function _180c_seo_override_object_id() {
	if ( is_singular() ) {
		$post_id = (int) get_queried_object_id();

		return $post_id > 0 ? $post_id : null;
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		return ( $term instanceof WP_Term ) ? 'term_' . (int) $term->term_id : null;
	}

	return null;
}

/**
 * Lit un override SEO sur la surface courante.
 *
 * Renvoie systématiquement une chaîne vide — jamais null — pour que les
 * appelants n'aient qu'un seul test à faire. Les balises et les espaces
 * parasites (y compris les insécables collés par un copier-coller depuis un
 * traitement de texte) sont normalisés : ils fausseraient le comptage de
 * caractères sans être visibles à la relecture.
 *
 * @param string $field Nom du champ ACF (`seo_title` ou `seo_description`).
 * @return string Valeur normalisée, ou chaîne vide si absente.
 */
function _180c_seo_override( string $field ): string {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$object_id = _180c_seo_override_object_id();
	if ( null === $object_id ) {
		return '';
	}

	$value = get_field( $field, $object_id );
	if ( ! is_string( $value ) ) {
		return '';
	}

	// `_180c_title_normalize()` (inc/seo/titles.php) fait exactement ce travail
	// et il est déjà chargé : strip des tags, décodage des entités, réduction
	// des blancs Unicode.
	return _180c_title_normalize( $value );
}

/**
 * Lit un override SEO renvoyant un identifiant de pièce jointe.
 *
 * Le champ `og_image_override` est déclaré en `return_format: id` : ACF rend
 * donc un entier. On tolère malgré tout la forme tableau, qu'ACF renvoie si le
 * `return_format` du groupe est modifié depuis wp-admin sans que le JSON local
 * le reflète — auquel cas un `(int)` sur un tableau vaudrait 1, et pointerait
 * silencieusement sur une pièce jointe arbitraire.
 *
 * @param string $field Nom du champ ACF.
 * @return int ID de la pièce jointe, ou 0.
 */
function _180c_seo_override_id( string $field ): int {
	if ( ! function_exists( 'get_field' ) ) {
		return 0;
	}

	$object_id = _180c_seo_override_object_id();
	if ( null === $object_id ) {
		return 0;
	}

	$value = get_field( $field, $object_id );

	if ( is_array( $value ) ) {
		$value = isset( $value['ID'] ) ? $value['ID'] : 0;
	} elseif ( $value instanceof WP_Post ) {
		$value = $value->ID;
	}

	return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
}

/**
 * Lit un override SEO booléen.
 *
 * ACF stocke un `true_false` en `'1'` / `'0'` : on ne peut pas se contenter
 * d'un cast, `'0'` étant une chaîne non vide dans certains tests naïfs.
 *
 * @param string $field Nom du champ ACF.
 * @return bool
 */
function _180c_seo_override_flag( string $field ): bool {
	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}

	$object_id = _180c_seo_override_object_id();
	if ( null === $object_id ) {
		return false;
	}

	return (bool) get_field( $field, $object_id );
}

/**
 * Sert `seo_title` comme `<title>` complet, sans aucun assemblage.
 *
 * Priorité 4, soit **avant** le point d'extension `180c/seo_title_pattern`
 * (priorité 5, meta-tags.php) et avant le gabarit (priorité 6,
 * title-resolver.php) : une décision éditoriale prise contenu par contenu prime
 * sur une règle générale, c'est tout l'objet du champ.
 *
 * Aucun suffixe ` · 180°C` n'est ajouté. La valeur saisie est le title final,
 * marque comprise si la rédaction juge utile de l'y mettre — les 60 caractères
 * disponibles sont trop précieux pour qu'on en dépense 8 d'office sur une
 * requête comme « fleur de câpre ».
 *
 * `og:title` et `twitter:title` dérivent de `wp_get_document_title()`
 * (meta-tags.php) : ils suivent automatiquement.
 *
 * @param string $title Title résolu en amont ('' à ce stade).
 * @return string Override si saisi, `$title` inchangé sinon.
 */
function _180c_seo_title_acf_override( $title ) {
	if ( '' !== (string) $title ) {
		return $title;
	}

	$override = _180c_seo_override( 'seo_title' );

	return ( '' !== $override ) ? $override : $title;
}
add_filter( 'pre_get_document_title', '_180c_seo_title_acf_override', 4 );

/**
 * Rétablit la désindexation réelle pilotée par la case `no_index`.
 *
 * Le champ ne retirait plus la page que du sitemap (`inc/seo/sitemap.php`),
 * sans poser la moindre directive robots : une page cochée « ne pas indexer »
 * restait parfaitement indexable, et le seul signal envoyé à Google était son
 * absence d'un fichier qu'il ne consulte qu'à titre indicatif. Un contrôle
 * éditorial qui ne contrôle rien est pire que pas de contrôle du tout.
 *
 * Branché sur `180c/seo_robots`, et **non** sur `wp_robots`. Le core émet déjà
 * sa propre balise robots via `wp_robots` (relevé en production :
 * `<meta name='robots' content='max-image-preview:large'>`, que WooCommerce
 * complète sur les pages transactionnelles), en plus de celle du thème. Y
 * ajouter le `noindex` créerait une seconde source de vérité, concurrente de
 * celle que `_180c_render_meta()` calcule — deux balises pouvant se
 * contredire. `180c/seo_robots` est le point d'extension que le thème
 * documente et emploie déjà pour les pages transactionnelles et les termes
 * redondants.
 *
 * `follow` et non `nofollow` : on ne veut pas indexer la page, mais les liens
 * qu'elle porte vers le reste du site doivent continuer à transmettre le
 * crawl. C'est la convention retenue partout ailleurs dans le module.
 *
 * L'exclusion du sitemap reste en place et n'est pas dupliquée ici : les deux
 * mécanismes lisent le même champ, chacun pour sa surface.
 *
 * @param string $robots Directive robots calculée en amont.
 * @return string
 */
function _180c_seo_no_index_override( $robots ) {
	return _180c_seo_override_flag( 'no_index' ) ? 'noindex, follow' : $robots;
}
add_filter( '180c/seo_robots', '_180c_seo_no_index_override' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespace de hooks 180c/ imposé par CLAUDE.md.
