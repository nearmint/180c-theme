<?php
/**
 * Schema.org FAQPage (JSON-LD) — Centre d'aide.
 *
 * Audit du Centre d'aide (2026-08-29) : la page publie 18 paires question /
 * réponse structurées dans le repeater ACF `help_sections`, rendues en
 * accordéon par `page-aide.php`, et n'exposait aucun balisage FAQ. Le @graph de
 * la page ne portait que `NewsMediaOrganization`, `WebSite` et `BreadcrumbList`.
 *
 * Le nœud est injecté dans le @graph unique de la page via le filtre
 * `180c/schema_graph` (cf. `inc/seo/schema.php`) — jamais dans un second bloc
 * <script>, l'invariant « un seul bloc JSON-LD par page » est conservé. Les
 * nœuds existants ne sont ni lus ni modifiés : on ajoute en fin de tableau.
 *
 * SOURCE DE LA DONNÉE
 * -------------------
 * Exactement la même que le rendu HTML : `get_field( 'help_sections' )`. Aucune
 * seconde source, aucune recopie — si l'accordéon affiche une question, le
 * balisage la porte, et réciproquement. C'est la condition pour que le balisage
 * reste vrai après une édition en admin.
 *
 * NETTOYAGE DU TEXTE DE RÉPONSE
 * -----------------------------
 * `question_answer` est un champ WYSIWYG : du HTML, avec des entités (`&rsquo;`,
 * `&#8211;`) et — séquelle d'un copier-coller relevée par l'audit — des classes
 * Tailwind parasites dans les attributs. Trois précautions :
 *
 *  1. les frontières de blocs (`</p>`, `<br>`, `</li>`) sont remplacées par une
 *     espace AVANT de retirer les balises : `wp_strip_all_tags()` seul collerait
 *     « …vos contenus :1. Rendez-vous… » ;
 *  2. les entités sont décodées APRÈS le premier retrait de balises, puis les
 *     balises sont retirées une seconde fois. Décoder d'abord transformerait un
 *     `&lt;script&gt;` saisi en admin en vraie balise ; décoder ensuite sans
 *     repasser la strip laisserait cette balise dans le JSON-LD ;
 *  3. les espaces, insécables compris (U+00A0), sont normalisés en une seule
 *     espace simple.
 *
 * Les classes parasites disparaissent d'elles-mêmes : elles vivent dans des
 * attributs, donc dans les balises retirées à l'étape 1.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indique si la requête courante est le Centre d'aide.
 *
 * Le test porte sur le TEMPLATE et non sur l'ID de page : une seconde page bâtie
 * sur `page-aide.php` (traduction, refonte préparée en brouillon) doit hériter
 * du balisage sans qu'on ait à venir éditer une constante ici.
 *
 * @return bool
 */
function _180c_faq_is_help_page(): bool {
	return is_page() && is_page_template( 'page-aide.php' );
}

/**
 * Réduit un fragment HTML de réponse à du texte brut.
 *
 * @param string $html Contenu du champ WYSIWYG `question_answer`.
 * @return string Texte brut, entités décodées, espaces normalisés. Chaîne vide si rien d'exploitable.
 */
function _180c_faq_answer_to_text( string $html ): string {
	// 1. Frontières de blocs → espace, sinon les paragraphes se collent.
	$text = preg_replace( '#<(?:/p|br\s*/?|/li|/h[1-6]|/div)\s*>#i', ' ', $html );

	if ( null === $text ) {
		return '';
	}

	// 2. Retrait des balises, décodage des entités, second retrait.
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = wp_strip_all_tags( $text );

	// 3. Normalisation des espaces, insécables compris.
	$text = str_replace( "\u{00A0}", ' ', $text );
	$text = preg_replace( '/\s+/u', ' ', $text );

	return null === $text ? '' : trim( $text );
}

/**
 * Construit la liste des nœuds `Question` à partir de `help_sections`.
 *
 * Une question n'est retenue que si son intitulé ET sa réponse survivent au
 * nettoyage : un balisage FAQ portant une réponse vide est une non-conformité
 * Google, pas un demi-gain.
 *
 * @param array<int, array<string, mixed>> $sections Structure ACF `help_sections`.
 * @param string                           $base_url Permalien de la page, pour les `@id`.
 * @return array<int, array<string, mixed>> Nœuds Question, éventuellement vide.
 */
function _180c_faq_build_questions( array $sections, string $base_url ): array {
	$questions = array();

	foreach ( $sections as $section ) {
		if ( empty( $section['section_questions'] ) || ! is_array( $section['section_questions'] ) ) {
			continue;
		}

		foreach ( $section['section_questions'] as $question ) {
			$name = isset( $question['question_title'] )
				? _180c_faq_answer_to_text( (string) $question['question_title'] )
				: '';
			$text = isset( $question['question_answer'] )
				? _180c_faq_answer_to_text( (string) $question['question_answer'] )
				: '';

			if ( '' === $name || '' === $text ) {
				continue;
			}

			$node = array(
				'@type'          => 'Question',
				'name'           => $name,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $text,
				),
			);

			// `@id` aligné sur l'ancre réelle de l'accordéon (page-aide.php:98),
			// pour que le lien profond du rich result ouvre la bonne question.
			$slug = isset( $question['question_slug'] ) ? sanitize_title( (string) $question['question_slug'] ) : '';
			if ( '' !== $slug ) {
				$node['@id'] = $base_url . '#' . $slug;
			}

			$questions[] = $node;
		}
	}

	return $questions;
}

add_filter( '180c/schema_graph', '_180c_faq_schema_graph', 10 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

/**
 * Ajoute un nœud FAQPage au @graph du Centre d'aide.
 *
 * @param array $graph Structure { '@context', '@graph' }.
 * @return array Structure enrichie, ou inchangée hors Centre d'aide.
 */
function _180c_faq_schema_graph( $graph ) {
	if ( ! is_array( $graph ) || empty( $graph['@graph'] ) || ! is_array( $graph['@graph'] ) ) {
		return $graph;
	}

	if ( ! _180c_faq_is_help_page() || ! function_exists( 'get_field' ) ) {
		return $graph;
	}

	$sections = get_field( 'help_sections' );
	if ( empty( $sections ) || ! is_array( $sections ) ) {
		return $graph;
	}

	$url       = (string) get_permalink();
	$questions = _180c_faq_build_questions( $sections, $url );

	if ( empty( $questions ) ) {
		return $graph;
	}

	$graph['@graph'][] = array(
		'@type'      => 'FAQPage',
		'@id'        => $url . '#faqpage',
		'url'        => $url,
		'name'       => wp_get_document_title(),
		'mainEntity' => $questions,
	);

	return $graph;
}
