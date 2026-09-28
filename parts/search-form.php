<?php
/**
 * Template part : formulaire de recherche partagé.
 *
 * Form natif GET soumettant vers /?s={query} — pleinement fonctionnel sans
 * JS. Pré-rempli avec get_search_query() sur la page de résultats. Utilisé par
 * l'état vide / sans-résultat de la page de recherche ; le menu latéral
 * (parts/site-side-menu.php) embarque sa propre variante toujours visible.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="search-field" class="search-form__label sr-only"><?php esc_html_e( 'Rechercher sur 180°C', '180c' ); ?></label>
	<input
		type="search"
		id="search-field"
		class="search-form__input"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Rechercher une recette, un article, un auteur…', '180c' ); ?>"
		autocomplete="off"
	>
	<button type="submit" class="search-form__submit">
		<svg class="search-form__icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
		<span class="sr-only"><?php esc_html_e( 'Lancer la recherche', '180c' ); ?></span>
	</button>
</form>
