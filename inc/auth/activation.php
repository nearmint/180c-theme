<?php
/**
 * Activation du thème — prérequis des routes d'authentification.
 *
 * À l'activation (`after_switch_theme`) :
 *  - crée la table d'audit des tentatives de login (`{prefix}180c_login_attempts`) ;
 *  - vide le cache des règles de réécriture pour activer les routes custom
 *    (/connexion/, /inscription/, /mot-de-passe-oublie/, /reinitialiser-mot-de-passe/).
 *
 * Les règles de réécriture elles-mêmes sont enregistrées sur `init` dans
 * inc/auth/{login,register,password-reset}.php ; ce flush les rend effectives.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tâches d'activation liées à l'authentification.
 *
 * @return void
 */
function _180c_auth_on_theme_activation(): void {
	// Les rewrite rules sont déclarées sur `init` (déjà passé à ce stade) : on
	// les ré-enregistre avant de flusher pour qu'elles soient persistées.
	if ( function_exists( '_180c_login_rewrite' ) ) {
		_180c_login_rewrite();
	}
	if ( function_exists( '_180c_register_rewrite' ) ) {
		_180c_register_rewrite();
	}
	if ( function_exists( '_180c_password_rewrite' ) ) {
		_180c_password_rewrite();
	}

	flush_rewrite_rules();

	if ( function_exists( '_180c_create_login_attempts_table' ) ) {
		_180c_create_login_attempts_table();
	}
}
add_action( 'after_switch_theme', '_180c_auth_on_theme_activation' );
