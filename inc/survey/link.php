<?php
/**
 * Sondage — lien Typeform.
 *
 * Source unique du lien affiché par les quatre touchpoints (sidebar Mon compte,
 * bloc « Nous contacter », Centre d'aide, footer). Aucun d'eux ne lit les
 * options directement : ils appellent tous `_180c_survey_url()` et n'impriment
 * rien si elle rend une chaîne vide.
 *
 * Le sondage est un lien sortant, rien de plus. Aucun script Typeform n'est
 * chargé sur 180c.fr, aucun paramètre ni fragment n'est ajouté à l'URL, et
 * aucun événement n'est mesuré.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL du sondage, ou chaîne vide si aucun touchpoint ne doit s'afficher.
 *
 * Trois conditions cumulées : ACF disponible, interrupteur armé, URL en HTTPS.
 * Une chaîne vide vaut « pas de sondage en cours » — c'est le seul signal dont
 * les touchpoints ont besoin, et il leur évite d'avoir à connaître la raison.
 *
 * Mémoïsé : le footer, le Centre d'aide et le tableau de bord peuvent
 * l'interroger plusieurs fois dans la même page.
 *
 * @return string URL absolue, ou ''.
 */
function _180c_survey_url(): string {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$cache = '';

	if ( ! function_exists( 'get_field' ) ) {
		return $cache;
	}

	if ( ! get_field( 'survey_enabled', 'option' ) ) {
		return $cache;
	}

	$url = trim( (string) get_field( 'survey_typeform_url', 'option' ) );

	// HTTPS exigé : le lien s'ouvre dans un nouvel onglet, un `http://`
	// exposerait la navigation du répondant. La validation ACF le refuse déjà à
	// l'enregistrement ; ce contrôle couvre les valeurs écrites autrement
	// (import SQL, `update_field()`).
	if ( '' === $url || 0 !== stripos( $url, 'https://' ) ) {
		return $cache;
	}

	$cache = esc_url_raw( $url );

	return $cache;
}

/**
 * Injecte le lien du sondage dans la colonne « Aide et contact » du footer.
 *
 * Cette colonne est rendue par un MENU WordPress (location `footer-help`,
 * affectée en local comme en production) et non par le repli codé en dur de
 * `parts/footer/main.php` : l'item doit donc être fabriqué à la volée, sans
 * qu'on ait à l'ajouter à la main dans l'administration.
 *
 * Positionné après « Contact presse », repéré par son URL (`objet=presse`) et
 * NON par son libellé : celui-ci est éditable depuis l'administration, un
 * renommage casserait un repérage textuel sans que rien ne le signale. Si
 * l'item est introuvable — menu réorganisé, entrée supprimée — le lien est
 * ajouté en fin de colonne plutôt que perdu.
 *
 * Le repli codé en dur ne reçoit rien : il ne sert que si la location n'est
 * affectée à aucun menu, cas où le site n'est de toute façon pas dans son état
 * nominal.
 *
 * @param array    $items Objets d'items de menu.
 * @param stdClass $args  Arguments de wp_nav_menu().
 * @return array Items filtrés.
 */
function _180c_survey_footer_menu_item( $items, $args ) {
	if ( empty( $args->theme_location ) || 'footer-help' !== $args->theme_location ) {
		return $items;
	}

	$url = _180c_survey_url();
	if ( '' === $url ) {
		return $items;
	}

	/*
	 * Réindexation OBLIGATOIRE avant tout `array_splice()`.
	 *
	 * Le cœur passe ici un tableau dont les clés commencent à 1, pas à 0 (elles
	 * viennent de `wp_get_nav_menu_items()`). `array_splice()` raisonne en
	 * POSITIONS, jamais en clés : sans cette ligne, l'insertion après la clé 3
	 * tombait à la position 4, c'est-à-dire à la fin de la colonne. Constaté en
	 * local, le lien se retrouvait après « Espace pro ».
	 */
	$items = array_values( $items );

	$item = (object) array(
		'ID'                    => 0,
		'db_id'                 => 0,
		'menu_item_parent'      => 0,
		'object_id'             => 0,
		'object'                => 'custom',
		'type'                  => 'custom',
		'type_label'            => __( 'Lien personnalisé', '180c' ),

		/*
		 * Le libellé porte la mention lecteur d'écran. `Walker_Nav_Menu` insère
		 * le titre SANS l'échapper (il passe seulement par les filtres
		 * `the_title` et `nav_menu_item_title`) : c'est le seul point où
		 * ajouter du markup à un item fabriqué, et la valeur est ici
		 * entièrement sous notre contrôle.
		 */
		'title'                 => sprintf(
			'%1$s<span class="screen-reader-text">%2$s</span>',
			esc_html__( 'Votre avis sur le site', '180c' ),
			esc_html__( '(nouvel onglet)', '180c' )
		),
		'url'                   => $url,
		'target'                => '_blank',
		'xfn'                   => 'noopener',
		'attr_title'            => '',
		'description'           => '',
		'classes'               => array( 'menu-item', 'menu-item-type-custom', 'menu-item-180c-survey' ),
		'current'               => false,
		'current_item_ancestor' => false,
		'current_item_parent'   => false,
	);

	$position = null;
	foreach ( $items as $index => $existing ) {
		if ( false !== strpos( (string) ( $existing->url ?? '' ), 'objet=presse' ) ) {
			$position = $index;
			break;
		}
	}

	if ( null === $position ) {
		$items[] = $item;

		return array_values( $items );
	}

	array_splice( $items, $position + 1, 0, array( $item ) );

	return array_values( $items );
}
add_filter( 'wp_nav_menu_objects', '_180c_survey_footer_menu_item', 10, 2 );
