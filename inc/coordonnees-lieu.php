<?php
/**
 * Bloc acf/coordonnees-lieu — helpers de rendu + intégration JSON-LD.
 *
 * Le bloc ACF (inc/blocks/coordonnees-lieu/) affiche les coordonnées d'un
 * lieu en fin d'article. Ce module fournit :
 *   - les helpers partagés par render.php (libellés, icônes, normalisation
 *     téléphone, libellé de site) ;
 *   - l'intégration Schema.org : pour chaque instance du bloc dans un article,
 *     un nœud Place/LocalBusiness est ajouté au @graph unique de la page et
 *     rattaché au nœud Article via `about`.
 *
 * Pourquoi lire les blocs au moment du wp_head (et non au rendu du bloc) :
 * le JSON-LD est émis en wp_head (priorité 5), AVANT le rendu du corps. On
 * lit donc les instances du bloc via parse_blocks() + acf_setup_meta(), ce
 * qui préserve l'invariant « un seul bloc JSON-LD par page ».
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// Référentiel type_lieu.

/**
 * Référentiel des types de lieu : value => [label, schema @type].
 *
 * @return array<string,array{label:string,type:string}>
 */
function _180c_cl_types() {
	return array(
		'restaurant'  => array(
			'label' => 'Restaurant',
			'type'  => 'Restaurant',
		),
		'hotel'       => array(
			'label' => 'Hôtel / Hôtel-restaurant',
			'type'  => 'Hotel',
		),
		'cafe'        => array(
			'label' => 'Café / Bar / Salon de thé',
			'type'  => 'CafeOrCoffeeShop',
		),
		'boulangerie' => array(
			'label' => 'Boulangerie / Pâtisserie',
			'type'  => 'Bakery',
		),
		'domaine'     => array(
			'label' => 'Domaine / Vignoble / Cave',
			'type'  => 'Winery',
		),
		'producteur'  => array(
			'label' => 'Producteur / Artisan',
			'type'  => 'LocalBusiness',
		),
		'commerce'    => array(
			'label' => 'Commerce / Boutique / Épicerie',
			'type'  => 'Store',
		),
		'autre'       => array(
			'label' => 'Autre',
			'type'  => 'LocalBusiness',
		),
	);
}

/**
 * Libellé humain d'un type de lieu.
 *
 * @param string $value Valeur du select type_lieu.
 * @return string
 */
function _180c_cl_type_label( $value ) {
	$types = _180c_cl_types();
	return isset( $types[ $value ] ) ? $types[ $value ]['label'] : '';
}

/**
 * Type Schema.org d'un type de lieu (défaut LocalBusiness).
 *
 * @param string $value Valeur du select type_lieu.
 * @return string
 */
function _180c_cl_type_schema( $value ) {
	$types = _180c_cl_types();
	return isset( $types[ $value ] ) ? $types[ $value ]['type'] : 'LocalBusiness';
}

/**
 * Icône SVG inline du kicker « type de lieu » (pin générique).
 *
 * @param string $value Valeur du select (réservé pour variantes futures).
 * @return string SVG.
 */
function _180c_cl_type_icon( $value ) {
	unset( $value );
	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>';
}

// Site & réseaux.

/**
 * Libellé « propre » d'un site à partir de son URL : host + chemin, sans
 * protocole, sans www., sans slash final.
 *
 * @param string $url URL du site.
 * @return string
 */
function _180c_cl_site_label( $url ) {
	$label = preg_replace( '#^https?://#i', '', trim( $url ) );
	$label = preg_replace( '#^www\.#i', '', $label );
	$label = rtrim( $label, '/' );
	return $label;
}

/**
 * Détecte le réseau social d'une URL et renvoie son type, son libellé a11y
 * et son icône SVG.
 *
 * @param string $url URL du profil.
 * @return array{type:string,label:string,icon:string}
 */
function _180c_cl_reseau_meta( $url ) {
	$paths   = array(
		'instagram' => '<path d="M12 2.2c3.2 0 3.6 0 4.9.07 1.2.06 1.8.25 2.2.42.6.22 1 .48 1.4.9.42.4.68.8.9 1.4.17.4.36 1 .42 2.2.07 1.3.07 1.7.07 4.9s0 3.6-.07 4.9c-.06 1.2-.25 1.8-.42 2.2-.22.6-.48 1-.9 1.4-.4.42-.8.68-1.4.9-.4.17-1 .36-2.2.42-1.3.07-1.7.07-4.9.07s-3.6 0-4.9-.07c-1.2-.06-1.8-.25-2.2-.42-.6-.22-1-.48-1.4-.9-.42-.4-.68-.8-.9-1.4-.17-.4-.36-1-.42-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.9c.06-1.2.25-1.8.42-2.2.22-.6.48-1 .9-1.4.4-.42.8-.68 1.4-.9.4-.17 1-.36 2.2-.42C8.4 2.2 8.8 2.2 12 2.2zm0 3.05A6.75 6.75 0 1 0 18.75 12 6.75 6.75 0 0 0 12 5.25zm0 11.13A4.38 4.38 0 1 1 16.38 12 4.38 4.38 0 0 1 12 16.38zm6.96-11.4a1.58 1.58 0 1 1-1.57-1.58 1.58 1.58 0 0 1 1.57 1.58z"/>',
		'facebook'  => '<path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"/>',
		'twitter'   => '<path d="M18.9 2.5h3.3l-7.2 8.24L23.7 21.5h-6.6l-5.18-6.77-5.92 6.77H2.7l7.7-8.8L2 2.5h6.77l4.68 6.19zm-1.16 17h1.83L8.34 4.4H6.38z"/>',
		'tiktok'    => '<path d="M16.6 5.82a4.28 4.28 0 0 1-1.06-2.82h-3.2v12.93a2.59 2.59 0 1 1-2.6-2.6c.27 0 .53.04.78.12V8.2a5.94 5.94 0 0 0-.78-.05 5.8 5.8 0 1 0 5.8 5.8V8.1a7.45 7.45 0 0 0 4.36 1.4V6.3a4.28 4.28 0 0 1-3.3-.48z"/>',
		'youtube'   => '<path d="M23.5 6.5a3 3 0 0 0-2.12-2.12C19.5 3.86 12 3.86 12 3.86s-7.5 0-9.38.52A3 3 0 0 0 .5 6.5 31.3 31.3 0 0 0 0 12a31.3 31.3 0 0 0 .5 5.5 3 3 0 0 0 2.12 2.12c1.88.52 9.38.52 9.38.52s7.5 0 9.38-.52a3 3 0 0 0 2.12-2.12A31.3 31.3 0 0 0 24 12a31.3 31.3 0 0 0-.5-5.5zM9.6 15.6V8.4l6.2 3.6z"/>',
		'linkedin'  => '<path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.44-2.13 2.94v5.67H9.35V9h3.42v1.56h.05a3.75 3.75 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.56V9h3.56z"/>',
	);
	$labels  = array(
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'twitter'   => 'X (Twitter)',
		'tiktok'    => 'TikTok',
		'youtube'   => 'YouTube',
		'linkedin'  => 'LinkedIn',
	);
	$match   = '';
	$needles = array(
		'instagram' => array( 'instagram.' ),
		'facebook'  => array( 'facebook.', 'fb.com' ),
		'twitter'   => array( 'twitter.', 'x.com' ),
		'tiktok'    => array( 'tiktok.' ),
		'youtube'   => array( 'youtube.', 'youtu.be' ),
		'linkedin'  => array( 'linkedin.' ),
	);
	foreach ( $needles as $type => $list ) {
		foreach ( $list as $needle ) {
			if ( false !== stripos( $url, $needle ) ) {
				$match = $type;
				break 2;
			}
		}
	}
	if ( '' !== $match ) {
		return array(
			'type'  => $match,
			'label' => $labels[ $match ],
			'icon'  => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $match ] . '</svg>',
		);
	}
	// Domaine inconnu → icône lien générique.
	return array(
		'type'  => 'link',
		'label' => __( 'Lien', '180c' ),
		'icon'  => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3.9 12a3.1 3.1 0 0 1 3.1-3.1h4V7h-4a5 5 0 0 0 0 10h4v-1.9h-4A3.1 3.1 0 0 1 3.9 12zM8 13h8v-2H8zm9-6h-4v1.9h4a3.1 3.1 0 0 1 0 6.2h-4V17h4a5 5 0 0 0 0-10z"/></svg>',
	);
}

// Normalisation téléphone.

/**
 * Indicatif international depuis un nom de pays (défaut France = 33).
 *
 * @param string $pays Nom du pays.
 * @return string Indicatif sans « + » (ex. '33').
 */
function _180c_cl_dialcode( $pays ) {
	$map = array(
		'France'      => '33',
		'Belgique'    => '32',
		'Suisse'      => '41',
		'Espagne'     => '34',
		'Italie'      => '39',
		'Royaume-Uni' => '44',
		'Danemark'    => '45',
		'Maroc'       => '212',
	);
	return isset( $map[ $pays ] ) ? $map[ $pays ] : '33';
}

/**
 * Normalise un numéro de téléphone pour un href `tel:` (format international
 * compact). Ne réécrit pas un format inconnu : retombe sur les chiffres bruts.
 *
 * Règles : « (0) » trunk supprimé ; « 00 » → « + » ; national « 0X… » →
 * « +<indicatif pays>X… » ; « + » conservé.
 *
 * @param string $numero Numéro saisi.
 * @param string $pays   Pays du lieu (pour les numéros nationaux).
 * @return string Numéro normalisé (ex. '+33XXXXXXXXX').
 */
function _180c_cl_tel_to_intl( $numero, $pays = 'France' ) {
	$n = trim( (string) $numero );
	// Supprimer le trunk « (0) » fréquent dans « +33 (0)4 … ».
	$n        = preg_replace( '/\(\s*0\s*\)/', '', $n );
	$has_plus = ( strpos( $n, '+' ) === 0 );
	$digits   = preg_replace( '/\D+/', '', $n );

	if ( '' === $digits ) {
		return '';
	}
	if ( $has_plus ) {
		return '+' . $digits;
	}
	if ( strpos( $digits, '00' ) === 0 ) {
		return '+' . substr( $digits, 2 );
	}
	if ( strpos( $digits, '0' ) === 0 ) {
		return '+' . _180c_cl_dialcode( $pays ) . substr( $digits, 1 );
	}
	return $digits;
}

// Intégration Schema.org (@graph unique).

/**
 * Construit les nœuds Schema.org « lieu » à partir des blocs parsés d'un
 * article, et la liste de leurs @id (pour le rattachement `about`).
 *
 * Fonction pure (testable hors contexte de requête) : prend des blocs déjà
 * parsés et le permalien, lit les valeurs ACF de chaque instance via
 * acf_setup_meta().
 *
 * @param array  $blocks    Résultat de parse_blocks().
 * @param string $permalink Permalien de l'article (base des @id).
 * @return array{nodes:array<int,array>,ids:array<int,string>}
 */
function _180c_cl_schema_nodes_from_blocks( array $blocks, $permalink ) {
	$nodes = array();
	$ids   = array();
	$index = 0;

	// Aplatir récursivement pour capter les blocs imbriqués éventuels.
	$flat  = array();
	$stack = $blocks;
	while ( $stack ) {
		$b = array_shift( $stack );
		if ( ! is_array( $b ) ) {
			continue;
		}
		$flat[] = $b;
		if ( ! empty( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ) {
			foreach ( $b['innerBlocks'] as $ib ) {
				$stack[] = $ib;
			}
		}
	}

	foreach ( $flat as $block ) {
		if ( empty( $block['blockName'] ) || 'acf/coordonnees-lieu' !== $block['blockName'] ) {
			continue;
		}
		$data = isset( $block['attrs']['data'] ) && is_array( $block['attrs']['data'] ) ? $block['attrs']['data'] : array();
		if ( empty( $data ) ) {
			continue;
		}
		++$index;

		// Lecture des champs ACF dans le contexte du bloc.
		$fake_id = 'block_180c_cl_' . $index;
		$can_acf = function_exists( 'acf_setup_meta' ) && function_exists( 'acf_reset_meta' );
		if ( $can_acf ) {
			acf_setup_meta( $data, $fake_id, true );
		}
		$get = function ( $name ) use ( $can_acf, $data, $fake_id ) {
			if ( $can_acf ) {
				return get_field( $name, $fake_id );
			}
			return isset( $data[ $name ] ) ? $data[ $name ] : null;
		};

		$nom        = trim( (string) $get( 'nom' ) );
		$complement = trim( (string) $get( 'complement' ) );
		$type       = (string) $get( 'type_lieu' );
		$adresse    = trim( (string) $get( 'adresse' ) );
		$pays       = trim( (string) $get( 'pays' ) );
		$telephones = (array) $get( 'telephones' );
		$email      = trim( (string) $get( 'email' ) );
		$site_url   = trim( (string) $get( 'site_url' ) );
		$reseaux    = (array) $get( 'reseaux' );

		if ( $can_acf ) {
			acf_reset_meta( $fake_id );
		}

		if ( '' === $nom && '' === $adresse ) {
			continue; // bloc vide : pas de nœud.
		}

		$id   = $permalink . '#lieu-' . $index;
		$node = array(
			'@type' => _180c_cl_type_schema( $type ),
			'@id'   => $id,
			'name'  => '' !== $complement ? $nom . ' ' . $complement : $nom,
		);

		if ( '' !== $adresse ) {
			$street          = implode( ', ', array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $adresse ) ) ) );
			$node['address'] = array(
				'@type'          => 'PostalAddress',
				'streetAddress'  => $street,
				'addressCountry' => '' !== $pays ? $pays : 'France',
			);
		}

		// Premier téléphone normalisé.
		foreach ( $telephones as $tel ) {
			$num = is_array( $tel ) && isset( $tel['numero'] ) ? trim( (string) $tel['numero'] ) : '';
			if ( '' !== $num ) {
				$node['telephone'] = _180c_cl_tel_to_intl( $num, $pays );
				break;
			}
		}

		if ( '' !== $email ) {
			$node['email'] = $email;
		}
		if ( '' !== $site_url ) {
			$node['url'] = $site_url;
		}

		$same_as = array();
		foreach ( $reseaux as $r ) {
			$ru = is_array( $r ) && isset( $r['url'] ) ? trim( (string) $r['url'] ) : '';
			if ( '' !== $ru ) {
				$same_as[] = $ru;
			}
		}
		if ( $same_as ) {
			$node['sameAs'] = array_values( array_unique( $same_as ) );
		}

		$nodes[] = $node;
		$ids[]   = $id;
	}

	return array(
		'nodes' => $nodes,
		'ids'   => $ids,
	);
}

/**
 * Rattache les nœuds « lieu » des blocs au @graph de la page et au nœud
 * Article (`about`), en préservant l'invariant « un seul bloc JSON-LD ».
 *
 * @param array $result Structure { '@context', '@graph' }.
 * @return array
 */
function _180c_cl_filter_schema_graph( $result ) {
	if ( ! is_singular( 'post' ) || empty( $result['@graph'] ) ) {
		return $result;
	}
	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return $result;
	}

	$built = _180c_cl_schema_nodes_from_blocks( parse_blocks( $post->post_content ), get_permalink( $post ) );

	return _180c_cl_attach_to_graph( $result, $built );
}
add_filter( '180c/schema_graph', '_180c_cl_filter_schema_graph', 20 );

/**
 * Ajoute les nœuds « lieu » au @graph et rattache leurs @id au nœud Article
 * via `about`. Fonction pure (testable).
 *
 * @param array $result Structure { '@context', '@graph' }.
 * @param array $built  Sortie de _180c_cl_schema_nodes_from_blocks().
 * @return array
 */
function _180c_cl_attach_to_graph( $result, $built ) {
	if ( empty( $built['nodes'] ) ) {
		return $result;
	}

	foreach ( $built['nodes'] as $node ) {
		$result['@graph'][] = $node;
	}

	// Rattacher les lieux à l'Article via `about`.
	foreach ( $result['@graph'] as &$node ) {
		if ( isset( $node['@type'] ) && 'Article' === $node['@type'] ) {
			$about = isset( $node['about'] ) ? (array) $node['about'] : array();
			foreach ( $built['ids'] as $id ) {
				$about[] = array( '@id' => $id );
			}
			$node['about'] = array_values( $about );
			break;
		}
	}
	unset( $node );

	return $result;
}
