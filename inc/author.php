<?php
/**
 * Page auteur — logique métier.
 *
 * Couvre :
 *  - Query vars indépendantes `articles_page` et `recipes_page` (les deux
 *    sections paginent séparément sur la même URL `/author/{nicename}/`).
 *  - Template-tags `_180c_author_*` exposés au template et aux patterns.
 *  - Requêtes paginées (12 items) pour les articles (CPT `post`) et les
 *    recettes (CPT `recipe`).
 *  - Endpoint REST `/180c/v1/author-content` : enhancement « Charger plus »
 *    AJAX (la pagination serveur reste pleinement fonctionnelle sans JS).
 *
 * Le rendu du template vit dans `author.php` (template hierarchy WP) et dans
 * les trois patterns `patterns/author-{header,articles,recipes}.php`.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nombre d'items affichés par page dans chaque section (articles, recettes).
 *
 * Aligne avec la grille responsive : 4 colonnes en desktop → 8 items =
 * exactement 2 lignes (pas de ligne orpheline). Mobile (1 col) = 8 cartes
 * scrollées ; tablette (2 col) = 4 lignes — acceptable en cible.
 */
if ( ! defined( '_180C_AUTHOR_PER_PAGE' ) ) {
	define( '_180C_AUTHOR_PER_PAGE', 8 );
}

/*
 * 1. Query vars indépendantes
 */

/**
 * Enregistre les query vars utilisés par la page auteur.
 *
 * Permet d'avoir deux paginations distinctes sur la même URL
 * (`?articles_page=2#articles` et `?recipes_page=2#recettes`) sans collision
 * avec `paged` (qui pilote la requête principale du contexte auteur).
 *
 * @param array $vars Query vars existants.
 * @return array
 */
add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'articles_page';
		$vars[] = 'recipes_page';
		return $vars;
	}
);

/**
 * Retourne le numéro de page (1+) pour la section `articles`.
 *
 * @return int
 */
function _180c_author_articles_paged() {
	return max( 1, absint( get_query_var( 'articles_page', 1 ) ) );
}

/**
 * Retourne le numéro de page (1+) pour la section `recettes`.
 *
 * @return int
 */
function _180c_author_recipes_paged() {
	return max( 1, absint( get_query_var( 'recipes_page', 1 ) ) );
}

/*
 * 2. Template-tags — identité de l'auteur
 */

/**
 * Retourne le nom d'affichage de l'auteur.
 *
 * Priorité : `first_name last_name` (concaténés), sinon `display_name`.
 *
 * @param int $author_id ID de l'auteur.
 * @return string Nom prêt pour esc_html(), ou chaîne vide.
 */
function _180c_author_display_name( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return '';
	}

	$first = trim( (string) get_the_author_meta( 'first_name', $author_id ) );
	$last  = trim( (string) get_the_author_meta( 'last_name', $author_id ) );
	$full  = trim( $first . ' ' . $last );

	if ( '' !== $full ) {
		return $full;
	}

	return (string) get_the_author_meta( 'display_name', $author_id );
}

/**
 * Retourne le prénom de l'auteur (utilisé pour les titres de section).
 *
 * Fallback sur le display_name si `first_name` est vide.
 *
 * @param int $author_id ID de l'auteur.
 * @return string
 */
function _180c_author_first_name( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return '';
	}

	$first = trim( (string) get_the_author_meta( 'first_name', $author_id ) );

	return '' !== $first ? $first : _180c_author_display_name( $author_id );
}

/**
 * Retourne l'URL de la photo de l'auteur.
 *
 * Priorité : champ ACF `author_photo` (image ID) → Gravatar (`get_avatar_url`).
 * Si le champ ACF est renseigné mais que l'image a été supprimée du media
 * library, on retombe gracieusement sur le Gravatar.
 *
 * @param int    $author_id ID de l'auteur.
 * @param string $size      Taille WP des médias (ex. 'thumbnail', 'medium',
 *                          'large'). Pour le Gravatar, on demande 200px.
 * @return string URL absolue, ou chaîne vide si aucune image disponible.
 */
function _180c_author_photo_url( $author_id, $size = 'medium' ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return '';
	}

	if ( function_exists( 'get_field' ) ) {
		$photo_id = (int) get_field( 'author_photo', 'user_' . $author_id );
		if ( $photo_id > 0 ) {
			$url = wp_get_attachment_image_url( $photo_id, $size );
			if ( $url ) {
				return $url;
			}
		}
	}

	$gravatar = get_avatar_url( $author_id, array( 'size' => 200 ) );
	return $gravatar ? (string) $gravatar : '';
}

/**
 * Retourne l'URL de l'avatar « éditorial » de l'auteur — ACF strict.
 *
 * Pendant strict du champ ACF `author_photo` : renvoie l'URL de l'attachment
 * à la taille demandée, ou chaîne vide si non renseigné / introuvable.
 *
 * Contrairement à _180c_author_photo_url() (qui retombe sur le Gravatar par
 * défaut), cette fonction n'émet jamais une URL Gravatar — utile pour
 * décider d'afficher ou non un bloc média (pas de silhouette grise).
 *
 * @param int $author_id ID de l'auteur.
 * @param int $size      Taille pixel approximative (resolu via la taille
 *                       d'image WP la plus proche). 200 par défaut.
 * @return string URL absolue, ou chaîne vide.
 */
function _180c_author_avatar_url( $author_id, $size = 200 ) {
	$author_id = absint( $author_id );
	if ( ! $author_id || ! function_exists( 'get_field' ) ) {
		return '';
	}

	$photo_id = (int) get_field( 'author_photo', 'user_' . $author_id );
	if ( $photo_id < 1 ) {
		return '';
	}

	// Choix de la taille WP la plus proche du size demandé.
	$wp_size = 'medium';
	$size    = (int) $size;
	if ( $size >= 300 ) {
		$wp_size = 'large';
	} elseif ( $size <= 96 ) {
		$wp_size = 'thumbnail';
	}

	$url = wp_get_attachment_image_url( $photo_id, $wp_size );
	return $url ? (string) $url : '';
}

/**
 * Détermine si l'auteur a une « vraie » photo éditoriale.
 *
 * Source unique : champ ACF `author_photo`. Si l'admin n'a pas renseigné
 * cette image, le header masque entièrement le bloc avatar (et ajoute le
 * modifier `author-header--no-photo`) plutôt que de rendre une silhouette
 * grise (Gravatar mystery person).
 *
 * Note : aucun plugin d'avatar local n'est installé sur ce site (audit du
 * dossier wp-content/plugins/). Une éventuelle évolution vers un plugin
 * type Simple Local Avatars ajouterait une fast-path
 * `get_user_meta($id, 'simple_local_avatar', true)` ici.
 *
 * @param int $author_id ID de l'auteur.
 * @return bool
 */
function _180c_author_has_real_avatar( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id || ! function_exists( 'get_field' ) ) {
		return false;
	}

	$photo_id = (int) get_field( 'author_photo', 'user_' . $author_id );
	if ( $photo_id < 1 ) {
		return false;
	}

	// Vérifie que l'attachment existe encore (admin a pu supprimer l'image).
	$attachment = get_post( $photo_id );
	return $attachment && 'attachment' === $attachment->post_type;
}

/**
 * Retourne l'attachment ID de la photo ACF si présent (sinon 0).
 *
 * Utilisé par le pattern header pour générer un srcset propre quand on a la
 * vraie image (pas seulement un Gravatar).
 *
 * @param int $author_id ID de l'auteur.
 * @return int Attachment ID ou 0.
 */
function _180c_author_photo_attachment_id( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id || ! function_exists( 'get_field' ) ) {
		return 0;
	}

	$photo_id = (int) get_field( 'author_photo', 'user_' . $author_id );

	return $photo_id > 0 ? $photo_id : 0;
}

/**
 * Détermine si la page publique d'un auteur est activée.
 *
 * Source : champ ACF `author_public` (true/false) sur la fiche utilisateur.
 *
 * Comportement opt-out :
 *  - Meta absente (utilisateurs créés avant l'ajout du champ) → traités
 *    comme publics. Sinon on désactiverait silencieusement toutes les
 *    pages auteur existantes au moment du déploiement.
 *  - Meta cochée (1) → publique.
 *  - Meta décochée (0) → masquée (redirect 301, exclu du sitemap, exclu
 *    du rail « autres signatures »).
 *
 * @param int $author_id ID de l'auteur.
 * @return bool
 */
function _180c_author_is_public( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return false;
	}

	if ( ! function_exists( 'get_field' ) ) {
		return true; // ACF indisponible → on n'enferme personne.
	}

	$val = get_field( 'author_public', 'user_' . $author_id );
	// Meta absente (null) ou non encore enregistrée ('') → opt-out par défaut.
	if ( null === $val || '' === $val ) {
		return true;
	}

	return (bool) $val;
}

/**
 * Indique si la section « Les articles de X » doit être masquée pour cet auteur.
 *
 * Exception éditoriale ponctuelle, décidée au cas par cas : la page auteur
 * standard affiche articles + recettes, mais certaines signatures ne doivent
 * exposer que leurs recettes. On adresse ces auteurs par `user_nicename`
 * (le segment d'URL `/author/{nicename}/`) et non par ID, les IDs différant
 * entre la production et les environnements locaux.
 *
 * Ne masque QUE la section articles de la page auteur : les articles restent
 * publiés, indexés et accessibles partout ailleurs.
 *
 * @param int $author_id ID de l'auteur.
 * @return bool True si la section articles doit être masquée.
 */
function _180c_author_hides_articles( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return false;
	}

	/**
	 * Liste des `user_nicename` dont la section articles est masquée.
	 *
	 * @param string[] $nicenames Slugs d'auteurs.
	 */
	$nicenames = apply_filters(
		'180c/author_hide_articles',
		array( 'delphine-brunet' )
	);

	$user = get_userdata( $author_id );
	if ( ! $user ) {
		return false;
	}

	return in_array( $user->user_nicename, (array) $nicenames, true );
}

/**
 * Redirige les pages auteur masquées vers l'accueil en 301.
 *
 * Hook tardif (template_redirect) car on a besoin que la requête WP soit
 * résolue (is_author + get_queried_object_id). 301 = signal SEO permanent
 * (de-index par les moteurs).
 *
 * @return void
 */
add_action(
	'template_redirect',
	function () {
		if ( ! is_author() ) {
			return;
		}

		$author_id = (int) get_queried_object_id();
		if ( $author_id && ! _180c_author_is_public( $author_id ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);

/**
 * Alias public de _180c_author_photo_attachment_id() — signature v3.
 *
 * Le cahier des charges v3 spécifie cette signature (`_180c_author_avatar_id`).
 * On garde aussi `_180c_author_photo_attachment_id` pour ne pas casser les
 * callers historiques. Les deux pointent sur la même source : champ ACF
 * `author_photo`.
 *
 * @param int $author_id ID de l'auteur.
 * @return int Attachment ID ou 0.
 */
function _180c_author_avatar_id( $author_id ) {
	return _180c_author_photo_attachment_id( $author_id );
}

/**
 * Retourne la fonction éditoriale de l'auteur (« Rédactrice », « Chef invité »…).
 *
 * @param int $author_id ID de l'auteur.
 * @return string Chaîne prête pour esc_html(), ou chaîne vide.
 */
function _180c_author_role( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id || ! function_exists( 'get_field' ) ) {
		return '';
	}

	return trim( (string) get_field( 'author_role', 'user_' . $author_id ) );
}

/**
 * Liste blanche des réseaux supportés (slug ACF → libellé public).
 *
 * Tout réseau hors de cette liste est ignoré (sanitisation stricte côté
 * affichage : on n'imprime jamais une classe BEM provenant de données
 * utilisateur sans validation préalable).
 *
 * @return array<string,string>
 */
function _180c_author_socials_labels() {
	/**
	 * Filtre la map des réseaux supportés sur la page auteur.
	 *
	 * @param array<string,string> $labels Slug → libellé.
	 */
	return (array) apply_filters(
		'180c/author_socials_labels',
		array(
			'instagram' => __( 'Instagram', '180c' ),
			'x'         => __( 'X', '180c' ),
			'facebook'  => __( 'Facebook', '180c' ),
			'linkedin'  => __( 'LinkedIn', '180c' ),
			'youtube'   => __( 'YouTube', '180c' ),
			'tiktok'    => __( 'TikTok', '180c' ),
			'threads'   => __( 'Threads', '180c' ),
			'website'   => __( 'Site web', '180c' ),
			'other'     => __( 'Lien', '180c' ),
		)
	);
}

/**
 * Retourne la liste filtrée et normalisée des réseaux de l'auteur.
 *
 * Filtre :
 *  - URLs vides ou invalides (`esc_url_raw` retourne vide).
 *  - Réseaux hors liste blanche.
 *
 * @param int $author_id ID de l'auteur.
 * @return array<int,array{network:string,label:string,url:string}> Liste prête à itérer.
 */
function _180c_author_socials( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id || ! function_exists( 'get_field' ) ) {
		return array();
	}

	$rows = get_field( 'author_socials', 'user_' . $author_id );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return array();
	}

	$labels = _180c_author_socials_labels();
	$out    = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$network = isset( $row['network'] ) ? (string) $row['network'] : '';
		$url     = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';

		if ( '' === $url || ! isset( $labels[ $network ] ) ) {
			continue;
		}

		$out[] = array(
			'network' => $network,
			'label'   => $labels[ $network ],
			'url'     => $url,
		);
	}

	return $out;
}

/*
 * 3. Requêtes paginées
 */

/**
 * WP_Query des articles (post natifs) d'un auteur, paginée.
 *
 * @param int $author_id ID de l'auteur.
 * @param int $paged     Numéro de page (1+).
 * @return WP_Query
 */
function _180c_author_articles_query( $author_id, $paged = 1 ) {
	return new WP_Query(
		array(
			'post_type'           => 'post',
			'author'              => absint( $author_id ),
			'post_status'         => 'publish',
			'posts_per_page'      => _180C_AUTHOR_PER_PAGE,
			'paged'               => max( 1, absint( $paged ) ),
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);
}

/**
 * WP_Query des recettes (CPT recipe) d'un auteur, paginée.
 *
 * @param int $author_id ID de l'auteur.
 * @param int $paged     Numéro de page (1+).
 * @return WP_Query
 */
function _180c_author_recipes_query( $author_id, $paged = 1 ) {
	return new WP_Query(
		array(
			'post_type'      => 'recipe',
			'author'         => absint( $author_id ),
			'post_status'    => 'publish',
			'posts_per_page' => _180C_AUTHOR_PER_PAGE,
			'paged'          => max( 1, absint( $paged ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}

/*
 * 4. Pagination — construction des liens
 *
 * On utilise paginate_links() en `array` pour réancrer chaque lien sur la
 * section correspondante (#articles / #recettes) et préserver la page de
 * l'autre section via add_args. Le résultat est un <nav> contenant une <ul>.
 */

/**
 * Rend la pagination HTML d'une section (articles ou recettes).
 *
 * @param string   $section     'articles' | 'recipes'.
 * @param int      $current     Page courante.
 * @param int      $total_pages Nombre total de pages.
 * @param int      $author_id   ID de l'auteur (pour la base URL).
 * @param string[] $preserve    Couples var → valeur à conserver (ex. l'autre section).
 * @return string HTML (chaîne vide si une seule page).
 */
function _180c_author_render_pagination( $section, $current, $total_pages, $author_id, $preserve = array() ) {
	$total_pages = (int) $total_pages;
	if ( $total_pages < 2 ) {
		return '';
	}

	$is_articles = ( 'articles' === $section );
	$query_var   = $is_articles ? 'articles_page' : 'recipes_page';
	$anchor      = $is_articles ? '#articles' : '#recettes';
	$aria_label  = $is_articles
		? __( 'Pagination des articles', '180c' )
		: __( 'Pagination des recettes', '180c' );

	return _180c_render_pagination(
		array(
			'base'         => trailingslashit( get_author_posts_url( (int) $author_id ) ) . '%_%',
			'format'       => '?' . $query_var . '=%#%',
			'current'      => $current,
			'total'        => $total_pages,
			'add_args'     => $preserve,
			'add_fragment' => $anchor,
			'aria_label'   => $aria_label,
		)
	);
}

/*
 * 5. Auteurs liés — rail « Découvrez d'autres signatures »
 */

/**
 * Retourne les initiales (monogramme) d'un auteur.
 *
 * Première lettre du prénom + première lettre du nom. Fallback sur les
 * deux premières lettres du display_name. Toujours en majuscules.
 *
 * @param int $author_id ID de l'auteur.
 * @return string 1 ou 2 caractères, ou '' si l'auteur n'existe pas.
 */
function _180c_author_monogram( $author_id ) {
	$author_id = absint( $author_id );
	if ( ! $author_id ) {
		return '';
	}

	$first = trim( (string) get_the_author_meta( 'first_name', $author_id ) );
	$last  = trim( (string) get_the_author_meta( 'last_name', $author_id ) );

	if ( '' !== $first && '' !== $last ) {
		return mb_strtoupper( mb_substr( $first, 0, 1 ) . mb_substr( $last, 0, 1 ) );
	}

	$display = (string) get_the_author_meta( 'display_name', $author_id );
	$display = trim( $display );
	if ( '' === $display ) {
		return '';
	}

	// Si le display_name contient un espace, on prend la première lettre de
	// chaque mot (max 2). Sinon, on prend les 2 premières lettres.
	$parts = preg_split( '/\s+/', $display, 2, PREG_SPLIT_NO_EMPTY );
	if ( is_array( $parts ) && count( $parts ) >= 2 ) {
		return mb_strtoupper( mb_substr( $parts[0], 0, 1 ) . mb_substr( $parts[1], 0, 1 ) );
	}

	return mb_strtoupper( mb_substr( $display, 0, 2 ) );
}

/**
 * Retourne les auteurs « liés » à afficher en pied de page auteur.
 *
 * Classés par volume de contenu publié (articles + recettes) décroissant.
 * Cache sur la liste *complète* triée (12 h), filtrage de l'exclusion
 * appliqué après lecture du cache (un même cache sert toutes les pages
 * auteur).
 *
 * @param int $exclude_id ID de l'auteur courant (à exclure du résultat).
 * @param int $limit      Nombre maximum d'auteurs retournés (défaut 6).
 * @return WP_User[] Liste d'objets WP_User triés par volume décroissant.
 */
function _180c_author_related_authors( $exclude_id, $limit = 6 ) {
	$exclude_id = absint( $exclude_id );
	$limit      = max( 1, absint( $limit ) );

	$cache_key = '_180c_top_authors';
	$cached    = get_transient( $cache_key );

	if ( false === $cached || ! is_array( $cached ) ) {
		// Liste tous les auteurs ayant publié un post ou une recette.
		$users = get_users(
			array(
				'has_published_posts' => array( 'post', 'recipe' ),
				'fields'              => array( 'ID', 'display_name', 'user_nicename' ),
			)
		);

		$scored = array();
		foreach ( $users as $user ) {
			$count               = (int) count_user_posts( (int) $user->ID, array( 'post', 'recipe' ), true );
			$scored[ $user->ID ] = $count;
		}

		// Tri décroissant par score, tie-breaker stable (ID décroissant).
		arsort( $scored, SORT_NUMERIC );

		// On garde seulement les IDs triés — get_userdata à la demande.
		$cached = array_keys( $scored );
		set_transient( $cache_key, $cached, 12 * HOUR_IN_SECONDS );
	}

	// Filtrage exclusion + matérialisation des WP_User, dans la limite.
	// On exclut aussi les auteurs marqués non publics (toggle ACF
	// author_public off) — décision v3 : pas de promotion dans le
	// rail pour les comptes éditeurs / techniques masqués.
	$out = array();
	foreach ( $cached as $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id === $exclude_id ) {
			continue;
		}
		if ( ! _180c_author_is_public( $user_id ) ) {
			continue;
		}
		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User ) {
			$out[] = $user;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
	}

	return $out;
}

/*
 * 6. REST endpoint « Charger plus » (enhancement progressif)
 *
 * Route : GET /wp-json/180c/v1/author-content?author_id=…&type=post|recipe&page=…
 * Retourne : { items: "<HTML des cartes>", has_more: bool, page: int }
 *
 * Public en lecture seule. Le fallback sans JS reste la pagination native
 * rendue par paginate_links() dans les patterns.
 */

add_action( 'rest_api_init', '_180c_rest_register_author_content' );

/**
 * Enregistre la route REST « author-content ».
 *
 * @return void
 */
function _180c_rest_register_author_content() {
	register_rest_route(
		_180C_API_NAMESPACE,
		'/author-content',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_get_author_content',
			'permission_callback' => '__return_true',
			'args'                => array(

				/*
				 * `minimum` RETIRÉ, volontairement — ce n'est pas un oubli.
				 *
				 * Il n'était pas appliqué (voir `type` ci-dessous), et le rendre
				 * opposable changerait la réponse : `author_id=0` rend depuis
				 * toujours 404 `author_not_found`, via le contrôle d'existence du
				 * callback. Un `validate_callback` ne peut pas préserver ce code —
				 * le cœur aplatit TOUTE erreur de validation en un unique
				 * `rest_invalid_param` 400 (class-wp-rest-request.php:961-968),
				 * le code d'origine ne survivant que dans `data.details`.
				 *
				 * Plutôt que de déclarer une contrainte qu'on n'applique pas, on
				 * ne déclare que ce qui est vrai : `absint` borne à zéro, et
				 * l'existence de l'auteur est vérifiée par le callback.
				 */
				'author_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'type'      => array(
					'required'          => true,
					'type'              => 'string',
					'enum'              => array( 'post', 'recipe' ),

					/*
					 * Sans cette ligne, l'énum n'était pas appliquée : déclarer un
					 * `sanitize_callback` prive l'argument du
					 * `rest_parse_request_arg` que le cœur assigne par défaut aux
					 * arguments qui n'en ont pas (class-wp-rest-request.php:858-861),
					 * et c'est lui qui valide le schéma. `type=bogus` repartait donc
					 * en 200, servi par la branche « article » du ternaire.
					 */
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_key',
				),
				'page'      => array(
					'required'          => false,
					'type'              => 'integer',
					// Idem : `page=-1` repartait en 200, rattrapé par le
					// `max( 1, … )` du callback. Refusé en 400 désormais.
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'absint',
					'minimum'           => 1,
					'default'           => 1,
				),
			),
		)
	);
}

/**
 * Callback REST : renvoie le HTML des cartes pour une page donnée.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_get_author_content( WP_REST_Request $request ) {
	// Filet : la validation REST est en amont (validate_callback) pour `type` et
	// `page`. Les casts et le `max( 1, … )` restent en défense de profondeur.
	// Pour `author_id`, en revanche, ce contrôle-ci n'est pas un filet mais LA
	// validation : voir le commentaire à l'enregistrement de la route.
	$author_id = absint( $request->get_param( 'author_id' ) );
	$type      = (string) $request->get_param( 'type' );
	$page      = max( 1, absint( $request->get_param( 'page' ) ) );

	// Garde-fou : l'auteur doit exister.
	if ( ! get_userdata( $author_id ) ) {
		return new WP_Error(
			'author_not_found',
			__( 'Auteur introuvable.', '180c' ),
			array( 'status' => 404 )
		);
	}

	$query = ( 'recipe' === $type )
		? _180c_author_recipes_query( $author_id, $page )
		: _180c_author_articles_query( $author_id, $page );

	$html = '';
	if ( $query->have_posts() ) {
		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			if ( 'recipe' === $type ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo _180c_render_recipe_card( get_the_ID(), 'md' );
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo _180c_render_article_card( get_the_ID() );
			}
		}
		$html = (string) ob_get_clean();
		wp_reset_postdata();
	}

	$has_more = $page < (int) $query->max_num_pages;

	return rest_ensure_response(
		array(
			'items'    => $html,
			'has_more' => $has_more,
			'page'     => $page,
		)
	);
}
