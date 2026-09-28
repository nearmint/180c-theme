<?php
/**
 * Page Contact — configuration & SEO.
 *
 * Source unique de vérité du mapping objet → destinataire, helpers de
 * détection de la page, et intégration SEO.
 *
 * SEO : le thème dispose déjà d'un module SEO natif complet
 * (inc/seo/meta-tags.php + inc/seo/schema.php) qui émet title, meta description,
 * robots, canonical, Open Graph, Twitter Cards et un unique @graph JSON-LD pour
 * chaque page. On NE duplique donc AUCUNE balise : on se branche sur les points
 * d'extension du module.
 *  - Title  : table centrale inc/seo/title-map.php (clé « contact »).
 *  - Desc.  : filtre `180c/seo_description` (propagé à <meta>, og:description, JSON-LD).
 *  - Schema : filtre `180c/schema_graph` (ajoute un nœud ContactPage au @graph).
 * Robots (index, follow) et og:type=website sont déjà le comportement natif pour
 * une page : rien à forcer.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slug de la page contact (filtrable).
 *
 * @return string
 */
function _180c_contact_page_slug() {
	return (string) apply_filters( '_180c_contact_page_slug', 'contact' );
}

/**
 * Mapping objet → label + destinataire e-mail (filtrable, source unique de vérité).
 *
 * Le routage est assuré par la clé `email`, consommée par l'endpoint REST
 * `180c/v1/contact` (inc/rest/contact.php). La clé du tableau (`redaction`,
 * `presse`…) est le contrat de routage transmis par le formulaire ; le `label`
 * n'est que du wording affiché et ne doit jamais servir à router.
 *
 * Les adresses de destination ne sont pas versionnées : elles sont lues dans la
 * constante `_180C_CONTACT_RECIPIENTS` (wp-config.php), tableau
 * `objet => adresse`. Un objet absent de la constante est routé vers
 * `admin_email`, pour qu'aucun message ne soit perdu.
 *
 * @return array<string, array{label:string, email:string}>
 */
function _180c_contact_recipients() {
	$labels = array(
		'redaction'      => 'Une question sur un article ou une recette',
		'presse'         => 'Une demande presse ou médias',
		'pro'            => 'Un partenariat ou une collaboration',
		'support-client' => 'Une question sur ma commande ou mon abonnement',
		'support-tech'   => 'Un bug ou un problème technique sur le site',
		'autre'          => 'Autre demande',
	);

	$emails   = defined( '_180C_CONTACT_RECIPIENTS' ) && is_array( _180C_CONTACT_RECIPIENTS ) ? _180C_CONTACT_RECIPIENTS : array();
	$fallback = (string) get_option( 'admin_email' );

	$recipients = array();
	foreach ( $labels as $objet => $label ) {
		$recipients[ $objet ] = array(
			'label' => $label,
			'email' => isset( $emails[ $objet ] ) && is_email( $emails[ $objet ] ) ? (string) $emails[ $objet ] : $fallback,
		);
	}

	return (array) apply_filters( '_180c_contact_recipients', $recipients );
}

/**
 * Vrai sur la page contact uniquement.
 *
 * @return bool
 */
function _180c_is_contact_page() {
	return is_page( _180c_contact_page_slug() );
}

/*
 * ---------------------------------------------------------------------------
 * SEO — intégration au module natif (aucune balise dupliquée).
 * ---------------------------------------------------------------------------
 */

/*
 * Le <title> de la page contact n'est plus posé ici. Il est résolu par la
 * table centrale (inc/seo/title-map.php, clé « contact ») et assemblé par
 * _180c_build_title() : « Contact — Écrire à la rédaction · 180°C ».
 *
 * L'ancienne valeur en dur (« Nous écrire — 180°C ») saisissait la marque à la
 * main avec un cadratin au lieu du suffixe ` · 180°C`, et court-circuitait le
 * module SEO à la priorité 10.
 */

add_filter( '180c/seo_description', '_180c_contact_seo_description' );

/**
 * Meta description de la page contact.
 *
 * Propagée par le module à <meta name="description"> et og:description.
 *
 * @param string $description Description courante.
 * @return string
 */
function _180c_contact_seo_description( $description ) {
	if ( _180c_is_contact_page() ) {
		return 'Une question, une remarque ? Contactez la rédaction de 180°C.';
	}
	return $description;
}

add_filter( '180c/schema_graph', '_180c_contact_schema_graph' );

/**
 * Ajoute un nœud ContactPage au @graph Schema.org de la page contact.
 *
 * Conserve l'invariant « un seul bloc JSON-LD par page » du module schema.php.
 *
 * @param array $graph Structure { '@context', '@graph' }.
 * @return array
 */
function _180c_contact_schema_graph( $graph ) {
	if ( ! _180c_is_contact_page() || empty( $graph['@graph'] ) || ! is_array( $graph['@graph'] ) ) {
		return $graph;
	}

	$url = (string) get_permalink();

	$graph['@graph'][] = array(
		'@type' => 'ContactPage',
		'@id'   => $url . '#contactpage',
		'name'  => wp_get_document_title(),
		'url'   => $url,
	);

	return $graph;
}
