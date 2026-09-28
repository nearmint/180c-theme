<?php
/**
 * Traçabilité de la source d'inscription newsletter — tags Mailchimp normalisés.
 *
 * Le champ « Source » natif de Mailchimp est en LECTURE SEULE via l'API v3 : il
 * est attribué d'après le credential appelant, jamais par paramètre de requête.
 * La seule donnée de source fiable et fine-grain se porte donc par TAGS, canal
 * intégralement contrôlé par le code. Aucun code de ce thème ne doit tenter
 * d'écrire le champ Source natif.
 *
 * Deux tags additifs sont posés à chaque inscription :
 *   - `src-plt:{web|ios|android}`  — plateforme d'origine ;
 *   - `src-loc:{emplacement}`      — emplacement précis du point d'entrée.
 *
 * Le slug reçu est résolu via une allow-list stricte : aucune valeur libre ne
 * crée de tag. Un slug inconnu retombe sur `src-loc:unknown` et est journalisé.
 *
 * Écritures membre (mêmes primitives que inc/mailchimp/premium-tag.php) :
 *   - ensure subscribed : PUT  /lists/{aud}/members/{hash} (status_if_new=subscribed)
 *   - pose des tags     : POST /lists/{aud}/members/{hash}/tags {tags:[{name,status}]}
 *
 * Tous les appels passent par `_180c_mc_request()` (api.php) : la clé
 * API ne quitte jamais le serveur et n'est jamais journalisée.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allow-list des slugs de source acceptés → tags Mailchimp correspondants.
 *
 * Toute valeur hors de cette table est traitée comme inconnue (cf.
 * `_180c_mc_source_tags()`). Filtrable pour étendre la table sans toucher au
 * code — les valeurs doivent rester des noms de tags Mailchimp valides.
 *
 * @return array<string,string[]> Map slug => liste de noms de tags.
 */
function _180c_mc_source_map(): array {
	$map = array(
		// Site web — formulaires front.
		'web-newsletter-page'   => array( 'src-plt:web', 'src-loc:newsletter-page' ),
		'web-block-form'        => array( 'src-plt:web', 'src-loc:block-form' ),
		'web-home-module'       => array( 'src-plt:web', 'src-loc:home-module' ),
		'web-cahiers'           => array( 'src-plt:web', 'src-loc:cahiers' ),
		// Site web — parcours compte / commande.
		'web-account-signup'    => array( 'src-plt:web', 'src-loc:account-signup' ),
		'web-account-prefs'     => array( 'src-plt:web', 'src-loc:account-prefs' ),
		'web-checkout'          => array( 'src-plt:web', 'src-loc:checkout' ),
		'web-subscription-sync' => array( 'src-plt:web', 'src-loc:subscription-sync' ),
		// Apps mobiles.
		'ios-app'               => array( 'src-plt:ios', 'src-loc:app-newsletter' ),
		'android-app'           => array( 'src-plt:android', 'src-loc:app-newsletter' ),
	);

	/**
	 * Filtre l'allow-list des sources d'inscription newsletter.
	 *
	 * @param array<string,string[]> $map Map slug => liste de noms de tags.
	 */
	return (array) apply_filters( '_180c_mc_source_map', $map );
}

/**
 * Résout un slug de source en liste de tags Mailchimp (fonction PURE).
 *
 * Aucun appel réseau, aucun effet de bord hormis la journalisation d'un slug
 * inconnu — la fonction est directement testable via `wp eval-file`.
 *
 * @param string $source Slug de source transmis par le point d'entrée.
 * @return string[] Tags à poser (jamais vide).
 */
function _180c_mc_source_tags( string $source ): array {
	$slug = strtolower( trim( $source ) );
	$map  = _180c_mc_source_map();

	if ( '' !== $slug && isset( $map[ $slug ] ) && ! empty( $map[ $slug ] ) ) {
		return array_values( (array) $map[ $slug ] );
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Journalisation volontaire des slugs non reconnus (contrat d'audit source).
	error_log( '[180c][mc-source] slug inconnu: ' . $source );

	return array( 'src-loc:unknown' );
}

/**
 * Audience Mailchimp cible des écritures de tags de source.
 *
 * Réutilise le résolveur newsletter (`_180c_nl_audience_id()`) quand il est
 * chargé, sinon les résolveurs équivalents — les trois retournent la même
 * audience unique (`_180C_MC_AUDIENCE_ID`, requise dans `wp-config.php`).
 *
 * @return string ID d'audience Mailchimp.
 */
function _180c_mc_source_audience_id(): string {
	if ( function_exists( '_180c_nl_audience_id' ) ) {
		return _180c_nl_audience_id();
	}
	if ( defined( '_180C_MC_AUDIENCE_ID' ) && '' !== (string) _180C_MC_AUDIENCE_ID ) {
		return (string) _180C_MC_AUDIENCE_ID;
	}
	if ( function_exists( '_180c_mc_audience_id' ) ) {
		return _180c_mc_audience_id();
	}
	return '';
}

/**
 * Pose une liste de tags sur un membre de l'audience (additif, idempotent).
 *
 * Writer générique : garantit d'abord que le contact existe et est `subscribed`
 * (un tag ne s'applique qu'à un membre existant ; `status_if_new=subscribed` ne
 * dégrade jamais un membre déjà présent), puis active les tags demandés. Aucun
 * tag n'est jamais retiré par cette fonction.
 *
 * @param string   $email     Adresse e-mail du membre.
 * @param string[] $tag_names Noms de tags à activer.
 * @return true|WP_Error True si l'écriture a abouti, WP_Error sinon.
 */
function _180c_mc_tags_add( string $email, array $tag_names ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', __( 'Adresse e-mail invalide.', '180c' ), array( 'status' => 400 ) );
	}

	$tags = array();
	foreach ( $tag_names as $name ) {
		$name = trim( (string) $name );
		if ( '' !== $name ) {
			$tags[] = array(
				'name'   => $name,
				'status' => 'active',
			);
		}
	}

	if ( empty( $tags ) ) {
		return new WP_Error( 'no_tags', __( 'Aucun tag à poser.', '180c' ) );
	}

	$aud  = _180c_mc_source_audience_id();
	$hash = md5( strtolower( trim( $email ) ) );

	// 1. S'assurer que le contact existe et est subscribed (prérequis au tag).
	$ensure = _180c_mc_request(
		'PUT',
		'/lists/' . $aud . '/members/' . $hash,
		array(
			'email_address' => $email,
			'status_if_new' => 'subscribed',
		)
	);
	if ( is_wp_error( $ensure ) ) {
		return $ensure;
	}
	if ( (int) $ensure['code'] >= 400 ) {
		return _180c_mc_source_rejected( (int) $ensure['code'] );
	}

	// 2. Activer les tags (succès Mailchimp = 204 sans corps). Additif : les tags
	// déjà présents sont ignorés côté Mailchimp, l'appel reste idempotent.
	$res = _180c_mc_request(
		'POST',
		'/lists/' . $aud . '/members/' . $hash . '/tags',
		array( 'tags' => $tags )
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( (int) $res['code'] >= 400 ) {
		return _180c_mc_source_rejected( (int) $res['code'] );
	}

	return true;
}

/**
 * Construit le WP_Error « refus Mailchimp » sans exposer de détail sensible.
 *
 * @param int $code Code HTTP renvoyé par Mailchimp.
 * @return WP_Error
 */
function _180c_mc_source_rejected( int $code ): WP_Error {
	_180c_log( 'Mailchimp a refusé une écriture de tag de source', array( 'status' => $code ), 'warning' );
	return new WP_Error(
		'mailchimp_rejected',
		__( 'Demande non aboutie, réessayez plus tard.', '180c' ),
		array( 'status' => 502 )
	);
}

/**
 * Pose les tags de source correspondant à un slug — best-effort.
 *
 * À appeler APRÈS une inscription/upsert réussie. Un échec d'écriture des tags
 * ne doit jamais faire échouer l'inscription de l'utilisateur : l'erreur est
 * journalisée et la fonction rend la main sans lever d'exception.
 *
 * @param string $email Adresse e-mail du membre.
 * @param string $slug  Slug de source (cf. `_180c_mc_source_map()`).
 * @return void
 */
function _180c_mc_tag_source( string $email, string $slug ): void {
	$result = _180c_mc_tags_add( $email, _180c_mc_source_tags( $slug ) );

	if ( is_wp_error( $result ) ) {
		_180c_log(
			'Pose des tags de source newsletter échouée',
			array(
				'slug'  => $slug,
				'error' => $result->get_error_code(),
			),
			'warning'
		);
	}
}
