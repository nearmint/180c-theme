/**
 * Reading progress bar.
 *
 * Progressive enhancement strictement décoratif :
 *  - cible `.reading-progress__bar` (la barre) + `.article__body` (la zone
 *    de lecture sur laquelle on calcule la progression) ;
 *  - bascule un `transform: scaleX(progress)` à chaque frame (rAF)
 *    pour ne jamais déclencher de reflow ;
 *  - un seul listener `scroll` passif + un seul listener `resize` passif ;
 *  - no-op silencieux si l'un des nœuds manque (autres pages, JS désactivé,
 *    DOM en train d'hydrater).
 */

const init = () => {
	const bar = document.querySelector( '.reading-progress__bar' );
	const article = document.querySelector( '.article__body' );

	if ( ! bar || ! article ) {
		return;
	}

	let ticking = false;
	let articleTop = 0;
	let articleHeight = 0;
	let viewportHeight = 0;

	const measure = () => {
		const rect = article.getBoundingClientRect();
		articleTop = rect.top + window.scrollY;
		articleHeight = article.offsetHeight;
		viewportHeight = window.innerHeight;
	};

	const update = () => {
		ticking = false;

		// Distance scrollée à l'intérieur de l'article (clampée 0..1).
		// On considère la progression complète une fois le bas de l'article
		// arrivé en bas du viewport — c'est plus parlant pour le lecteur
		// que d'attendre que le bas atteigne le haut.
		const scrolled = window.scrollY - articleTop + viewportHeight;
		const total = Math.max( 1, articleHeight );
		const progress = Math.max( 0, Math.min( 1, scrolled / total ) );

		bar.style.transform = `scaleX(${ progress })`;
	};

	const onScroll = () => {
		if ( ticking ) {
			return;
		}
		ticking = true;
		window.requestAnimationFrame( update );
	};

	const onResize = () => {
		measure();
		onScroll();
	};

	measure();
	update();

	window.addEventListener( 'scroll', onScroll, { passive: true } );
	window.addEventListener( 'resize', onResize, { passive: true } );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init, { once: true } );
} else {
	init();
}

export { init };
