<?php
/**
 * Sondage — page d'options « Sondage » (Marketing).
 *
 * Chargée en administration uniquement (cf. inc/bootstrap.php). Le front n'a
 * besoin ni de la page ni de ses hooks : `get_field( …, 'option' )` lit les
 * valeurs dans `wp_options` sans que la page soit enregistrée.
 *
 * Deux champs, pas un de plus : le lien Typeform et l'interrupteur. Tout le
 * reste — audience, ciblage, déclenchement, capping, mode test, identifiant de
 * campagne — appartenait à la modale et a été retiré avec elle.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/** Slug de la page d'options. Référencé par la localisation du groupe ACF. */
const _180C_SURVEY_PAGE_SLUG = '180c-survey';

/**
 * Enregistre la sous-page d'options « Sondage ».
 *
 * Déclarée sous Apparence, puis déplacée sous Marketing en dernière position
 * par `inc/admin-menu.php` — qui aliase au passage le hookname de rendu,
 * lequel dépend du parent.
 *
 * Le menu Marketing existe pourtant dès la priorité 6 : `parent_slug` pourrait
 * valoir `woocommerce-marketing` d'emblée. On s'en abstient pour la même raison
 * que « Bandeau Boutique » (cf. inc/info-banner.php) : l'entrée tomberait alors
 * sous `Marketing::reorder_marketing_submenu`, qui trie le sous-menu par ordre
 * alphabétique en priorité 99 — la même qu'ACF, donc dans un ordre non garanti.
 * Le déplacement en priorité 9999 est ce qui assure la dernière position.
 *
 * `page_title` et `menu_title` sont tenus identiques : ACF rend son propre
 * `page_title`, hors de portée de `$submenu`.
 */
add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
			return;
		}

		acf_add_options_sub_page(
			array(
				'page_title'  => __( 'Sondage', '180c' ),
				'menu_title'  => __( 'Sondage', '180c' ),
				'menu_slug'   => _180C_SURVEY_PAGE_SLUG,
				'parent_slug' => 'themes.php',
				'capability'  => 'edit_theme_options',
				'position'    => false,
			)
		);
	}
);

/**
 * Purge le cache de pages, si un plugin de cache le propose.
 *
 * `wp_cache_clear_cache()` est la fonction de WP Super Cache, absent en local :
 * la garde `function_exists` fait de cet appel un no-op en développement.
 *
 * Indispensable en production : le footer et le Centre d'aide portent des
 * touchpoints, et leur HTML anonyme est servi depuis un fichier statique. Sans
 * purge, un sondage qu'on vient d'éteindre continuerait d'y afficher son lien —
 * et un sondage qu'on vient d'ouvrir resterait invisible.
 *
 * @return bool Vrai si une purge a réellement eu lieu.
 */
function _180c_survey_purge_page_cache(): bool {
	if ( ! function_exists( 'wp_cache_clear_cache' ) ) {
		return false;
	}

	wp_cache_clear_cache();

	return true;
}

/**
 * Purge le cache après enregistrement de la page d'options.
 *
 * `acf/save_post` en priorité 20 : après l'écriture des valeurs par ACF
 * (priorité 10).
 *
 * @param string|int $post_id Cible ACF de l'enregistrement.
 * @return void
 */
function _180c_survey_after_save( $post_id ): void {
	if ( 'options' !== $post_id && 'option' !== $post_id ) {
		return;
	}

	// La page d'options du sondage n'est pas la seule du site : sans ce
	// contrôle, enregistrer « Bandeau Boutique » ou « Newsletters » purgerait
	// le cache et afficherait une notice sans rapport.
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || false === strpos( (string) $screen->id, _180C_SURVEY_PAGE_SLUG ) ) {
		return;
	}

	if ( _180c_survey_purge_page_cache() ) {
		set_transient( '_180c_survey_notice_' . get_current_user_id(), 'purged', MINUTE_IN_SECONDS );
	}
}
add_action( 'acf/save_post', '_180c_survey_after_save', 20 );

/**
 * Notices de la page d'options.
 *
 * Deux familles :
 *  - confirmation de purge, portée par un transient à usage unique plutôt que
 *    par un paramètre d'URL — qui aurait survécu au rechargement et rejoué le
 *    message indéfiniment ;
 *  - erreur de configuration : sondage activé sans lien valide. Cette notice-là
 *    n'est pas ponctuelle, elle décrit un état — elle reste affichée tant que
 *    l'état dure.
 *
 * @return void
 */
function _180c_survey_admin_notices(): void {
	$screen = get_current_screen();

	if ( ! $screen || false === strpos( (string) $screen->id, _180C_SURVEY_PAGE_SLUG ) ) {
		return;
	}

	$key    = '_180c_survey_notice_' . get_current_user_id();
	$notice = get_transient( $key );

	if ( 'purged' === $notice ) {
		delete_transient( $key );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Cache des pages vidé.', '180c' )
		);
	}

	// `_180c_survey_url()` rend '' dès qu'une des conditions manque ; on ne
	// signale l'anomalie que si l'interrupteur est armé, un sondage éteint sans
	// lien étant un état parfaitement normal.
	if ( function_exists( 'get_field' ) && get_field( 'survey_enabled', 'option' ) && '' === _180c_survey_url() ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Le sondage est activé mais le lien Typeform est vide ou n\'est pas en https:// : aucun lien ne s\'affiche sur le site.', '180c' )
		);
	}
}
add_action( 'admin_notices', '_180c_survey_admin_notices' );

/**
 * Refuse d'enregistrer un lien qui n'est pas en HTTPS.
 *
 * Le champ est de type `url`, qui n'impose pas de protocole. Le lien part dans
 * un `target="_blank"` : un `http://` exposerait la navigation du répondant.
 *
 * @param bool|string $valid True si valide, sinon message d'erreur.
 * @param mixed       $value Valeur soumise.
 * @return bool|string
 */
function _180c_survey_validate_url( $valid, $value ) {
	// Une erreur amont (champ requis, format) court-circuite notre contrôle.
	if ( true !== $valid ) {
		return $valid;
	}

	$value = trim( (string) $value );

	if ( '' === $value ) {
		return $valid;
	}

	if ( 0 !== stripos( $value, 'https://' ) ) {
		return __( 'Le lien du sondage doit commencer par https://.', '180c' );
	}

	return $valid;
}
add_filter( 'acf/validate_value/name=survey_typeform_url', '_180c_survey_validate_url', 10, 2 );
