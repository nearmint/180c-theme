/**
 * Nav sticky « Recettes » — révélation au scroll.
 *
 * Sur la page /recettes/ (template-home.php), révèle la barre sticky
 * `[data-recipes-stickynav]` (recherche + accès rapides) une fois le titre de
 * page (`.gazette-header`) sorti du viewport vers le haut, et la masque dès
 * qu'il redevient visible. Même esprit que la mini-bannière sticky recette
 * (recipe.js) : un IntersectionObserver sur l'élément déclencheur, pas de
 * listener de scroll.
 *
 * Sans JS, la barre reste `hidden` (les mêmes accès vivent dans le menu
 * latéral) — dégradation acceptée.
 *
 * Contrats DOM :
 *   [data-recipes-stickynav]   <nav> de la barre (attribut `hidden` au départ)
 *   .gazette-header            en-tête de page observé comme déclencheur
 *
 * @module recipes-stickynav
 */

const VISIBLE_CLASS = 'recipes-stickynav--visible';

/**
 * Initialise la révélation au scroll de la nav sticky recettes.
 *
 * @return {void}
 */
export function init() {
	const nav = document.querySelector( '[data-recipes-stickynav]' );
	if ( ! nav ) {
		return;
	}

	const trigger = document.querySelector( '.gazette-header' );
	if ( ! trigger ) {
		return;
	}

	// La barre est rendue dans #main (donc dans #site-shell, qui porte
	// `will-change: transform` → containing block qui « piège » le
	// position:fixed). On la déplace en enfant direct de <body>, hors de
	// #site-shell, pour ancrer son positionnement fixe au viewport (même
	// stratégie que la mini-bannière sticky recette dans recipe.js).
	if ( nav.parentElement !== document.body ) {
		document.body.appendChild( nav );
	}

	/**
	 * Affiche ou masque la barre.
	 *
	 * @param {boolean} show Vrai pour révéler la barre.
	 * @return {void}
	 */
	function reveal( show ) {
		nav.hidden = ! show;
		nav.classList.toggle( VISIBLE_CLASS, show );
	}

	// Révèle la barre quand le header de page sort du viewport vers le haut,
	// la masque dès qu'il redevient (même partiellement) visible.
	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				const scrolledPast =
					! entry.isIntersecting && entry.boundingClientRect.top < 0;
				reveal( scrolledPast );
			} );
		},
		{ threshold: 0 }
	);

	observer.observe( trigger );
}
