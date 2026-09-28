<?php
/**
 * Template part — Module home : Hero éditorial.
 *
 * Sous-champs ACF :
 *   - hero_auto_latest_recipe (true_false) — met en avant le dernier contenu publié
 *   - hero_auto_post_type (select : recipe|post) — type du contenu automatique
 *   - selected_post (post_object multi-type : post, recipe, product)
 *   - display_style (radio : full|split)
 *   - cta_label (text)
 *   - cta_url (url)
 *
 * Le bloc sert aussi bien la home Recettes (dernière recette) que la home
 * Gazette (dernier article) : le rendu `_180c_render_hero_post()` est agnostique
 * du type (titre / extrait / image / permalien génériques), seul l'overlay
 * favori reste réservé aux recettes.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks/_helpers.php';

$auto_latest   = (bool) get_sub_field( 'hero_auto_latest_recipe' );
$display_style = get_sub_field( 'display_style' ) ?: 'full';
$cta_label     = get_sub_field( 'cta_label' );
$cta_url       = get_sub_field( 'cta_url' );

if ( $auto_latest ) {
	// Mode automatique : ignore la sélection manuelle, résout le dernier contenu
	// publié du type choisi (recette par défaut, ou article pour la Gazette).
	$auto_type = (string) ( get_sub_field( 'hero_auto_post_type' ) ?: 'recipe' );
	if ( ! in_array( $auto_type, array( 'recipe', 'post' ), true ) ) {
		$auto_type = 'recipe';
	}

	$latest = get_posts(
		array(
			'post_type'      => $auto_type,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);

	$selected_post = ! empty( $latest ) ? $latest[0] : null;
} else {
	$post_id = get_sub_field( 'selected_post' );

	if ( ! $post_id ) {
		return;
	}

	// get_sub_field avec post_object retourne un objet WP_Post ou un ID.
	$selected_post = is_numeric( $post_id ) ? get_post( (int) $post_id ) : $post_id;
}

if ( ! $selected_post instanceof WP_Post ) {
	return;
}
?>

<section class="home-module home-module--hero_editorial" aria-label="<?php esc_attr_e( 'À la une', '180c' ); ?>">
	<div class="container-180c">
		<?php
		echo _180c_render_hero_post(
			$selected_post,
			sanitize_key( $display_style ),
			$cta_label ? sanitize_text_field( $cta_label ) : '',
			$cta_url ? esc_url_raw( $cta_url ) : '',
			// Toujours <h2> : le <h1> de la page est rendu par le template, pas
			// par un module. Sur template-home c'est gazette-header, sur la
			// front-page c'est le <h1> sr-only de front-page.php. Un module dont
			// la présence dépend de la composition ACF ne peut pas porter le
			// <h1> : il disparaît si l'éditeur le retire, et se duplique dès
			// qu'un second titre de page existe.
			'h2'
		);
		?>
	</div>
</section>
