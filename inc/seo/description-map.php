<?php
/**
 * Table des meta descriptions et utilitaires de mise en forme.
 *
 * Pendant de `inc/seo/title-map.php` pour la description. Depuis la
 * suppression du groupe ACF « SEO », le champ `seo_description` n'existe plus :
 * cette table est la source des pages statiques, et les contextes dynamiques
 * (contenus, taxonomies, auteurs) sont dérivés du contenu réel.
 *
 * **Règle dure : jamais de description vide.** Chaque chaîne de résolution se
 * termine par un repli qui produit toujours quelque chose. Une page sans
 * description laisse Google composer un extrait au hasard.
 *
 * Cible : 150 à 160 caractères, coupés sur une limite de mot. L'ellipse n'est
 * ajoutée **que** si la coupe a réellement tronqué le texte — une phrase
 * complète n'a pas à finir par « … ».
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Longueur maximale visée pour une meta description.
 *
 * @var int
 */
const _180C_DESCRIPTION_MAX_LEN = 160;

/**
 * Table des descriptions de pages statiques, indexée par `post_name`.
 *
 * Les clés `_front` et `_recipe_archive` sont virtuelles, comme dans
 * `_180c_title_map_pages()` — ces deux surfaces ne sont pas des pages
 * WordPress.
 *
 * @return array<string, string>
 */
function _180c_description_map_pages(): array {
	$map = array(
		'_front'                    => __( '180°C, la revue culture food : 1 500+ recettes de saison testées, reportages et portraits de producteurs. Indépendante et sans publicité.', '180c' ),
		'_recipe_archive'           => __( 'L’index complet des recettes 180°C : filtrez par catégorie, saison et revue pour trouver l’idée qui vous manque, des entrées aux desserts.', '180c' ),
		'a-propos'                  => __( '180°C est une maison d’édition culinaire indépendante. Découvrez son histoire, son équipe et les convictions qui guident chacune de ses revues.', '180c' ),
		'abonnement'                => __( 'Abonnez-vous à 180°C et accédez en illimité à 1 500+ recettes de saison, sur le web et les applications. Sans engagement, résiliable à tout moment.', '180c' ),
		'offrir-un-abonnement'      => __( 'Offrez douze mois de recettes 180°C. Un abonnement cadeau sans reconduction, activé à la date de votre choix, pour faire plaisir à un gourmand.', '180c' ),
		'boutique'                  => __( 'Toutes les publications 180°C : revues, hors-séries, Grands et Petits Cahiers, livres et ePub. Expédition depuis la France, paiement sécurisé.', '180c' ),
		'la-gazette'                => __( 'La Gazette de 180°C : actualité du vin et de l’alimentation, portraits de producteurs, reportages de terrain et carnets de voyage gourmands.', '180c' ),
		'recettes'                  => __( '1 500+ recettes de saison testées par la rédaction de 180°C : entrées, plats, desserts, apéro et boissons, à filtrer par saison et par envie.', '180c' ),
		'newsletter'                => __( 'Les Cahiers de Delphine, chaque vendredi : une recette de saison, des conseils de cuisine et les coulisses de la revue 180°C. Gratuit.', '180c' ),
		'contact'                   => __( 'Une question sur une commande, un abonnement ou un article ? Écrivez à la rédaction de 180°C, nous répondons sous quelques jours ouvrés.', '180c' ),
		'centre-daide'              => __( 'Commande, livraison, abonnement, ePub, compte : retrouvez les réponses aux questions les plus fréquentes sur le centre d’aide de 180°C.', '180c' ),
		'mon-carnet'                => __( 'Retrouvez toutes les recettes 180°C que vous avez mises de côté, réunies dans votre carnet personnel et synchronisées avec l’application.', '180c' ),
		'plan-du-site'              => __( 'Toutes les rubriques de 180°C en un coup d’œil : recettes, Gazette, boutique, abonnement et pages pratiques. Le plan complet du site.', '180c' ),
		'mentions-legales'          => __( 'Éditeur, hébergeur, directeur de la publication et propriété intellectuelle : les mentions légales du site 180°C.', '180c' ),
		'cgv'                       => __( 'Les conditions générales d’utilisation et de vente de 180°C : commandes, livraison, abonnements, rétractation et service client.', '180c' ),
		'politique-confidentialite' => __( 'Comment 180°C collecte, utilise et protège vos données personnelles, et comment exercer vos droits d’accès, de rectification et de suppression.', '180c' ),
	);

	/**
	 * Filtre la table des descriptions de pages statiques.
	 *
	 * @param array $map Table indexée par slug de page.
	 */
	return (array) apply_filters( '180c/description_map_pages', $map ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
}

/**
 * Repli ultime, quand aucun contexte n'a produit de description.
 *
 * Ne renvoie **jamais** la tagline du site : elle est vide en base, et c'est
 * elle qui produisait les pages sans description.
 *
 * @return string
 */
function _180c_description_default(): string {
	return __( '180°C, la revue culture food : recettes de saison, reportages et portraits de celles et ceux qui font la cuisine et le vin.', '180c' );
}

/**
 * Nettoie un texte destiné à une meta description.
 *
 * Retire les shortcodes, les balises et les blocs non textuels, puis normalise
 * les blancs — y compris les espaces Unicode que les rédacteurs saisissent
 * sans le savoir.
 *
 * @param string $text Texte brut.
 * @return string
 */
function _180c_description_clean( string $text ): string {
	// Les commentaires de blocs Gutenberg (<!-- wp:image ... /-->) survivent à
	// wp_strip_all_tags : ils sont retirés en premier, sinon leurs attributs
	// JSON se retrouvent dans la description.
	$text = (string) preg_replace( '/<!--\s*\/?wp:.*?-->/s', ' ', $text );
	$text = strip_shortcodes( $text );
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = (string) preg_replace( '/[\s\x{00A0}\x{202F}\x{2009}]+/u', ' ', $text );

	return trim( $text );
}

/**
 * Coupe une description sur une limite de mot.
 *
 * L'ellipse n'est ajoutée que si la coupe a effectivement tronqué : une phrase
 * qui tient déjà dans la limite est renvoyée telle quelle.
 *
 * @param string $text Texte déjà nettoyé.
 * @param int    $max  Longueur maximale.
 * @return string
 */
function _180c_description_trim( string $text, int $max = _180C_DESCRIPTION_MAX_LEN ): string {
	$text = trim( $text );

	if ( '' === $text || mb_strlen( $text ) <= $max ) {
		return $text;
	}

	// On réserve un caractère pour l'ellipse.
	$cut   = mb_substr( $text, 0, $max - 1 );
	$space = mb_strrpos( $cut, ' ' );
	if ( false !== $space && $space > 0 ) {
		$cut = mb_substr( $cut, 0, $space );
	}

	// Une ponctuation en fin de coupe ferait « … » après une virgule.
	$cut = rtrim( $cut, ' ,;:.!?—–-' );

	return $cut . '…';
}

/**
 * Compose « de {nom} » avec l'élision française.
 *
 * Les noms de termes sont capitalisés en base (« Entrée », « Été »). Concaténer
 * « de » devant produirait « recettes de Entrée ». On passe donc le nom en
 * minuscules et on élide devant une voyelle ou un h muet : « recettes
 * d’entrée », « recettes d’été », « recettes de plat ».
 *
 * @param string $name Nom du terme.
 * @return string
 */
function _180c_description_elide( string $name ): string {
	$name = trim( $name );

	if ( '' === $name ) {
		return '';
	}

	// Les noms propres et sigles (« 12°5 », « 180°C ») gardent leur casse : les
	// abaisser les rendrait méconnaissables. Heuristique : un nom qui contient
	// un chiffre ou plusieurs majuscules n'est pas un nom commun.
	$is_proper = (bool) preg_match( '/\d/u', $name )
		|| ( (int) preg_match_all( '/\p{Lu}/u', $name ) > 1 );

	$label = $is_proper ? $name : mb_strtolower( $name );

	// Élision devant voyelle (accentuée comprise) ou h muet.
	$first = mb_strtolower( mb_substr( $label, 0, 1 ) );
	$elide = in_array( $first, array( 'a', 'e', 'i', 'o', 'u', 'y', 'à', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ö', 'û', 'ù', 'ü', 'h' ), true );

	return $elide ? 'd’' . $label : 'de ' . $label;
}

/**
 * Compose la description d'un terme sans description saisie.
 *
 * Le nom du contenu dépend de la taxonomie : les taxonomies de recettes
 * listent des recettes, `category` et `post_tag` listent des articles. Servir
 * « recettes » sur une rubrique de La Gazette serait faux.
 *
 * @param WP_Term $term Terme courant.
 * @return string
 */
function _180c_description_term_fallback( WP_Term $term ): string {
	$recipe_taxonomies = array( 'recipe_category', 'recipe_season', 'recipe_tag' );
	$count             = max( 0, (int) $term->count );

	// `recipe_publication` porte des noms déjà articulés (« Les recettes 180°C »,
	// « Les Cahiers de Delphine ») : une élision y produirait « recettes de Les
	// recettes 180°C ». La tournure « publiées dans » les accepte tels quels.
	if ( 'recipe_publication' === $term->taxonomy ) {
		return sprintf(
			/* translators: 1: nombre de recettes, 2: nom de la publication. */
			_n(
				'%1$d recette publiée dans %2$s, à retrouver sur 180°C, la revue culture food.',
				'%1$d recettes publiées dans %2$s, à retrouver sur 180°C, la revue culture food.',
				$count,
				'180c'
			),
			$count,
			$term->name
		);
	}

	if ( in_array( $term->taxonomy, $recipe_taxonomies, true ) ) {
		return sprintf(
			/* translators: 1: nombre de recettes, 2: complément déjà élidé (ex. « d’entrée », « de plat »). */
			_n(
				'%1$d recette %2$s à découvrir sur 180°C, la revue culture food.',
				'%1$d recettes %2$s à découvrir sur 180°C, la revue culture food.',
				$count,
				'180c'
			),
			$count,
			_180c_description_elide( $term->name )
		);
	}

	if ( 'product_cat' === $term->taxonomy ) {
		return sprintf(
			/* translators: %s: nom de la catégorie produit. */
			__( '%s : revues, livres et cahiers de cuisine édités par 180°C. Expédition depuis la France, paiement sécurisé.', '180c' ),
			$term->name
		);
	}

	return sprintf(
		/* translators: 1: nombre d'articles, 2: nom du terme. */
		_n(
			'%1$d article classé %2$s dans La Gazette de 180°C, la revue culture food.',
			'%1$d articles classés %2$s dans La Gazette de 180°C, la revue culture food.',
			$count,
			'180c'
		),
		$count,
		$term->name
	);
}
