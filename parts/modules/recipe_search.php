<?php
/**
 * Template part — Module home : Recherche de recettes.
 *
 * Sous-champs ACF (layout `recipe_search`) :
 *   - title (text)        — titre affiché au-dessus du champ (optionnel).
 *   - placeholder (text)  — invite du champ de recherche (optionnel).
 *
 * Formulaire GET natif (pas d'AJAX) soumettant vers la page de recherche
 * (home_url('/'), query var native `s`). Le champ caché `tab=recipe`
 * pré-filtre la page de résultats sur le CPT `recipe` : la query var `tab` est
 * enregistrée dans inc/search.php et sélectionne l'onglet « Recettes » par
 * défaut. Pleinement fonctionnel sans JS.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_rs_title       = get_sub_field( 'title' );
$_180c_rs_placeholder = get_sub_field( 'placeholder' );
if ( ! $_180c_rs_placeholder ) {
	$_180c_rs_placeholder = __( 'Rechercher une recette…', '180c' );
}

$_180c_rs_field_id = 'home-recipe-search-' . wp_unique_id();
?>

<section class="home-module home-module--recipe-search">
	<div class="container-180c">
		<div class="home-recipe-search">
			<?php if ( $_180c_rs_title ) : ?>
				<h2 class="home-recipe-search__title"><?php echo esc_html( $_180c_rs_title ); ?></h2>
			<?php endif; ?>

			<form
				class="home-recipe-search__form search-form"
				role="search"
				method="get"
				action="<?php echo esc_url( home_url( '/' ) ); ?>"
				aria-label="<?php esc_attr_e( 'Rechercher une recette', '180c' ); ?>"
			>
				<label for="<?php echo esc_attr( $_180c_rs_field_id ); ?>" class="home-recipe-search__label screen-reader-text">
					<?php esc_html_e( 'Rechercher une recette', '180c' ); ?>
				</label>
				<input
					type="search"
					id="<?php echo esc_attr( $_180c_rs_field_id ); ?>"
					class="home-recipe-search__input search-form__input"
					name="s"
					value=""
					placeholder="<?php echo esc_attr( $_180c_rs_placeholder ); ?>"
					autocomplete="off"
				>
				<input type="hidden" name="tab" value="recipe">
				<button type="submit" class="home-recipe-search__submit btn btn--primary">
					<?php esc_html_e( 'Rechercher', '180c' ); ?>
				</button>
			</form>
		</div>
	</div>
</section>
