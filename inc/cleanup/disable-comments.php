<?php
/**
 * Désactivation complète des commentaires sur le site.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// Désactiver le support post comments + page comments.
add_action(
	'init',
	function () {
		remove_post_type_support( 'post', 'comments' );
		remove_post_type_support( 'post', 'trackbacks' );
		remove_post_type_support( 'page', 'comments' );
		remove_post_type_support( 'page', 'trackbacks' );
	}
);

// Fermer commentaires et pings sur le front.
add_filter( 'comments_open', '__return_false', 20, 2 );
add_filter( 'pings_open', '__return_false', 20, 2 );

// Retirer comments des menus admin.
add_action(
	'admin_menu',
	function () {
		remove_menu_page( 'edit-comments.php' );
	}
);

// Retirer comments de l'admin bar.
add_action(
	'wp_before_admin_bar_render',
	function () {
		global $wp_admin_bar;
		$wp_admin_bar->remove_menu( 'comments' );
	}
);

// Retourner 0 commentaire partout.
add_filter(
	'wp_count_comments',
	function () {
		return (object) array(
			'approved'       => 0,
			'moderated'      => 0,
			'spam'           => 0,
			'trash'          => 0,
			'post-trashed'   => 0,
			'total_comments' => 0,
			'all'            => 0,
		);
	}
);
