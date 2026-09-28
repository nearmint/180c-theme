<?php
/**
 * `/llms.txt` — plan de site à destination des modèles de langage.
 *
 * Convention llmstxt.org : un Markdown court, servi en `text/plain`, qui donne
 * à un agent les points d'entrée du site et les conditions d'usage du contenu,
 * sans qu'il ait à parcourir 2 000 pages pour les déduire.
 *
 * POURQUOI UN HOOK ET PAS UN FICHIER STATIQUE
 * -------------------------------------------
 * Le pipeline de déploiement synchronise le **thème**, pas la racine du site.
 * Un `llms.txt` déposé à la racine du serveur sortirait du dépôt et divergerait au
 * premier changement d'URL, exactement comme le `.htaccess`. Généré ici, il
 * suit le code et les URLs suivent `home_url()`.
 *
 * POURQUOI `template_redirect` ET PAS `add_rewrite_rule`
 * -----------------------------------------------------
 * Une règle de réécriture n'est active qu'après un `flush_rewrite_rules()`,
 * c'est-à-dire une écriture en base — impossible à déclencher sur cet
 * hébergement sans accès shell, et à refaire à chaque déploiement qui
 * toucherait aux permaliens. `template_redirect` ne dépend d'aucun état
 * persistant : la requête atteint WordPress (vérifié — une URL inexistante rend
 * bien une 404 WordPress, aucune règle serveur ne la court-circuite), et on
 * l'intercepte avant le rendu du gabarit 404.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indique si la requête courante vise `/llms.txt`.
 *
 * La comparaison porte sur le chemin seul, débarrassé de la query string et du
 * sous-répertoire d'installation éventuel, pour ne pas dépendre de la forme
 * exacte de `REQUEST_URI`.
 *
 * @return bool
 */
function _180c_is_llms_txt_request() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}

	$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

	// Installation en sous-répertoire : on retire le préfixe avant de comparer.
	if ( '' !== $base && '/' !== $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) - 1 );
	}

	return '/llms.txt' === strtolower( rtrim( $path, '/' ) );
}

/**
 * Points d'entrée listés dans `/llms.txt`.
 *
 * Chaque entrée = [chemin relatif, libellé, ligne de contexte]. **Toute URL
 * ajoutée ici doit avoir été vérifiée en HTTP 200 sur la production.** Deux
 * pièges connus, tous deux en 301 vers `/newsletter/` : `/cahiers-de-delphine/`
 * et `/les-cahiers-de-delphine/`. Ne jamais les lister — un plan de site qui
 * renvoie des redirections apprend au modèle des URLs mortes.
 *
 * @return array<int, array{0:string,1:string,2:string}>
 */
function _180c_llms_txt_entries() {
	$entries = array(
		array( '/', 'Accueil', 'Les dernières recettes, les derniers reportages et la collection des revues.' ),
		array( '/recettes/', 'Recettes', 'Le hub des recettes de saison : sélections éditoriales et entrées par catégorie.' ),
		array( '/toutes-les-recettes/', 'Toutes les recettes', 'L’index complet, filtrable par catégorie culinaire, saison et revue d’origine.' ),
		array( '/la-gazette/', 'La Gazette', 'Reportages, portraits de producteurs et actualité du vin et de l’alimentation.' ),
		array( '/boutique/', 'Boutique', 'Revues 180°C et 12°5, hors-séries, Grands et Petits Cahiers, livres et ePub.' ),
		array( '/abonnement/', 'Abonnement', 'L’accès illimité aux recettes en ligne et sur les applications mobiles.' ),
		array( '/newsletter/', 'Newsletter', 'Les Cahiers de Delphine : une recette et une lecture chaque vendredi.' ),
		array( '/a-propos/', 'À propos', 'La maison d’édition, son équipe et les contributeurs de chaque numéro.' ),
		array( '/contact/', 'Contact', 'Les interlocuteurs de la rédaction, de la boutique et des partenariats.' ),
		array( '/centre-daide/', 'Centre d’aide', 'Commandes, abonnements, lecture des ePub et gestion du compte.' ),
		array( '/plan-du-site/', 'Plan du site', 'L’arborescence complète, en HTML.' ),
		array( '/wp-sitemap.xml', 'Sitemap XML', 'Le sitemap machine, découpé par type de contenu et par taxonomie.' ),
	);

	/**
	 * Filtre les points d'entrée listés dans /llms.txt.
	 *
	 * @param array $entries Triplets [chemin, libellé, contexte].
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (array) apply_filters( '180c/llms_txt_entries', $entries );
}

/**
 * Compose le contenu Markdown de `/llms.txt`.
 *
 * Les URLs sont construites par `home_url()` : le fichier est donc juste dans
 * tous les environnements, et pointe bien `https://www.180c.fr/` en production.
 *
 * Rien n'est inventé : la description reprend celle de l'accueil
 * (`inc/seo/description-map.php`), l'éditeur et l'adresse proviennent des
 * mentions déjà publiées sur le site, et aucune date de fondation ni aucun ISSN
 * n'est avancé — ces valeurs ne figurent nulle part dans le contenu.
 *
 * @return string
 */
function _180c_llms_txt_content() {
	$description = '';
	if ( function_exists( '_180c_description_map_pages' ) ) {
		$descriptions = _180c_description_map_pages();
		$description  = isset( $descriptions['_front'] ) ? trim( (string) $descriptions['_front'] ) : '';
	}

	$lines   = array();
	$lines[] = '# 180°C — Culture Food';
	$lines[] = '';
	if ( '' !== $description ) {
		$lines[] = '> ' . $description;
		$lines[] = '';
	}
	$lines[] = 'Revue culinaire indépendante et sans publicité. Recettes de saison testées,';
	$lines[] = 'reportages de terrain et portraits de celles et ceux qui font la cuisine et le vin.';
	$lines[] = '';

	$lines[] = '## Contenus';
	$lines[] = '';
	foreach ( _180c_llms_txt_entries() as $entry ) {
		list( $path, $label, $context ) = $entry;
		$lines[]                        = sprintf( '- [%s](%s) : %s', $label, home_url( $path ), $context );
	}
	$lines[] = '';

	$lines[] = '## À propos';
	$lines[] = '';
	$lines[] = '180°C est une maison d’édition culinaire indépendante, éditée par Thermostat 6 (SAS),';
	$lines[] = '8 rue des Goncourt, 75011 Paris. Elle publie la revue 180°C, la revue 12°5 consacrée';
	$lines[] = 'au vin, des hors-séries, les Grands et Petits Cahiers, ainsi que des livres.';
	$lines[] = '';
	$lines[] = 'Le site ne diffuse aucune publicité et ne vit que de ses ventes et de ses abonnements.';
	$lines[] = 'Une partie des recettes est réservée aux abonnés.';
	$lines[] = '';

	$lines[] = '## Conditions d’usage';
	$lines[] = '';
	$lines[] = '- Les contenus de ce site (textes, recettes, photographies, illustrations) sont protégés';
	$lines[] = '  par le droit d’auteur. Ils ne sont pas libres de droits.';
	$lines[] = '- **Citation autorisée** dans une réponse générée à la demande d’un lecteur, à condition';
	$lines[] = '  de citer 180°C comme source et de fournir le lien vers la page d’origine.';
	$lines[] = '- **Usage pour l’entraînement de modèles non autorisé.** Les jetons d’exclusion';
	$lines[] = '  correspondants sont déclarés dans ' . home_url( '/robots.txt' ) . '.';
	$lines[] = '- Reproduction intégrale, republication et constitution de jeux de données : nous écrire';
	$lines[] = '  à contact@180c.fr.';
	$lines[] = '';

	$content = implode( "\n", $lines );

	/**
	 * Filtre le contenu complet de /llms.txt.
	 *
	 * @param string $content Markdown final.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	return (string) apply_filters( '180c/llms_txt_content', $content );
}

/**
 * Sert `/llms.txt` en text/plain, puis interrompt le rendu.
 *
 * `DONOTCACHEPAGE` est posé délibérément : WP Super Cache stocke les pages sous
 * forme de fichiers servis en `text/html`, et re-servirait donc ce contenu avec
 * le mauvais type MIME. Le fichier fait quelques kilo-octets et n'est demandé
 * qu'épisodiquement — l'exclure du cache page ne coûte rien et garantit
 * l'en-tête.
 *
 * @return void
 */
function _180c_serve_llms_txt() {
	if ( ! _180c_is_llms_txt_request() ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- constante d'interface imposée par WP Super Cache / W3 Total Cache ; la préfixer la rendrait inopérante.
		define( 'DONOTCACHEPAGE', true );
	}

	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sortie text/plain composée en interne ; tout échappement HTML corromprait le fichier.
	echo _180c_llms_txt_content();
	exit;
}
add_action( 'template_redirect', '_180c_serve_llms_txt', 0 );
