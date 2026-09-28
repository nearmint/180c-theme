/**
 * Entrée principale JS du thème 180°C.
 *
 * Charge tous les modules nécessaires.
 */

import '../css/main.css';

// Nonces : plus aucun n'est inliné dans le HTML (il est mis en cache et leur
// survivrait). Ils sont récupérés à la demande sur GET /180c/v1/session. Cet
// appel n'amorce rien par lui-même — il pose seulement les écouteurs qui
// déclencheront la récupération au premier survol/focus d'un élément menant à
// une action qui en a besoin. Un visiteur qui ne fait que lire n'émet rien.
import { initIntentPrefetch } from './modules/session.js';

// Modules conditionnels (chargés selon le contexte de la page).
import './modules/dark-mode.js';
import './modules/side-menu.js';
import './modules/header-scroll.js';
import './modules/account-dropdown.js';
import './modules/cart.js';
import './modules/smart-banner.js';
import './modules/auth.js';
import './modules/favorites.js';
import './modules/consent.js';
import './modules/analytics-ga4.js';
// Attribution WooCommerce (sourcebuster + order-attribution) : dequeuée côté
// serveur, réinjectée ici seulement après acceptation. Le module s'auto-init et
// sort sans rien faire si le payload PHP est absent (pages sans WooCommerce).
import './modules/order-attribution-consent.js';
// Events Umami non déclaratifs (les autres passent par les attributs
// data-umami-event posés en PHP). Import statique : le module s'auto-initialise
// et sort sans rien faire si le script Umami est absent ou bloqué.
import './modules/umami-events.js';
import './modules/help.js';
// Barre de partage (recette + article) : copier-lien → toast. Import STATIQUE
// volontaire (auto-init + garde [data-share-bar] interne). PAS d'import
// dynamique : un chunk lazy important toast.js (déjà dans le bundle principal)
// ferait double-évaluer l'entrée → side-menu/panier liés deux fois.
import './modules/share-bar.js';

initIntentPrefetch();

// Mosaïque « Complétez votre collection » : suspension de l'animation hors
// viewport et en onglet masqué. Markup rendu par PHP uniquement si le module
// est composé → on ne charge le JS que si la section est là. Sans JS, la
// mosaïque défile comme avant.
if ( document.querySelector( '.collection-mosaic' ) ) {
	import( './modules/collection-mosaic.js' ).then( ( m ) => m.initCollectionMosaic() );
}

// Bandeau d'information administrable : fermeture + garde date.
// Markup rendu par PHP uniquement si le bandeau doit s'afficher → on ne charge
// le module que si `[data-info-banner]` est présent. Sans JS, le bandeau reste
// lisible (non refermable).
if ( document.querySelector( '[data-info-banner]' ) ) {
	import( './modules/info-banner.js' ).then( ( m ) => m.initInfoBanner() );
}

// Fiche recette : interactivité (scaling portions, timer, liste de courses,
// sticky CTA paywall) chargée à la demande.
if ( document.querySelector( '.recipe' ) ) {
	import( './modules/recipe.js' ).then( ( m ) => m.init() );
}

// Article single : barre de progression de lecture.
// Enhancement strictement décoratif (aria-hidden) — la barre reste à 0 sans JS.
if ( document.querySelector( '.reading-progress__bar' ) ) {
	import( './modules/reading-progress.js' );
}

// Crédit photo repliable (.media-credit). Sans JS, la légende reste visible.
if ( document.querySelector( '.media-credit' ) ) {
	import( './modules/media-credit.js' );
}

// Légendes image/galerie repliables (« Voir plus ») dans le corps d'article.
// Enhancement progressif : sans JS, la légende reste intégralement visible.
if (
	document.querySelector(
		'.article__body :is(.wp-block-image, .wp-block-gallery) figcaption'
	)
) {
	import( './caption-readmore.js' );
}

// Embed YouTube : modale de lecture sur clic de la façade (rendue côté PHP).
// Sans JS, la façade reste un lien vers la page YouTube (repli a11y).
if ( document.querySelector( '[data-yt-facade]' ) ) {
	import( './youtube-modal.js' );
}

// Galerie d'article en slideshow (> 1 image) + « afficher en grand ».
// Enhancement progressif : sans JS, la grille native Gutenberg est conservée.
if ( document.querySelector( '.article__body .wp-block-gallery' ) ) {
	import( './gallery-slideshow.js' );
}

// Lightbox sur les images du corps article : enhancement progressif.
// Sélecteur `figure:has(> img)` :
//  - couvre l'éditeur **classique** (`figure.wp-caption`, le cas réel
//    en production sur 180c.fr) ;
//  - couvre l'éditeur Gutenberg (`figure.wp-block-image`) ;
//  - couvre chaque item d'une galerie classique/bloc, **mais exclut**
//    le wrapper `figure.wp-block-gallery` (son enfant direct n'est pas
//    un <img>, donc :has(> img) ne matche pas le wrapper).
//
// Sans JS, les figures s'affichent normalement (légende visible,
// pas de zoom). La fiche recette charge la lightbox indépendamment
// via recipe.js (sélecteur de trigger explicite).
// Exclut les figures de galerie : elles sont prises en charge par
// gallery-slideshow.js (lightbox NAVIGABLE sur l'ensemble du jeu), pas par
// l'enhancement image-unique global.
const articleBodyZoomSelector =
	'.article__body figure:has(> img):not(.wp-block-gallery figure)';
// Hero d'article : bouton « afficher en grand » explicite (data-lightbox-trigger
// dans la figure portant data-lightbox-src) — même pattern que la fiche recette.
const heroZoomSelector = '.article-hero__media[data-lightbox-src]';
if (
	document.querySelector( articleBodyZoomSelector ) ||
	document.querySelector( heroZoomSelector )
) {
	import( './modules/lightbox.js' ).then( ( m ) => {
		if ( document.querySelector( articleBodyZoomSelector ) ) {
			m.initLightbox( { enhanceSelector: articleBodyZoomSelector } );
		}
		if ( document.querySelector( heroZoomSelector ) ) {
			m.initLightbox( {
				triggerSelector: '.article-hero__media [data-lightbox-trigger]',
			} );
		}
	} );
}

// Page auteur : enhancement « Charger plus » sur les grilles paginées.
if ( document.querySelector( '[data-author-section]' ) ) {
	import( './modules/author.js' );
}

// Archive « Toutes les recettes » : filtres dynamiques + scroll infini.
if ( document.querySelector( '[data-recipes-archive]' ) ) {
	import( './modules/recipes-archive.js' ).then( ( m ) => m.init() );
}

// Page « Recettes » : nav sticky (recherche + accès) révélée au scroll.
if ( document.querySelector( '[data-recipes-stickynav]' ) ) {
	import( './modules/recipes-stickynav.js' ).then( ( m ) => m.init() );
}

// Page « Cahiers de Delphine » : import à la demande.
if ( document.querySelector( '[data-component="cdd-optin"]' ) ) {
	import( './modules/cahiers-de-delphine.js' ).then( ( m ) => m.init() );
}

// Page « Contact » : import à la demande.
if ( document.querySelector( '#contact-form' ) ) {
	import( './modules/contact.js' ).then( ( m ) => m.init() );
}

// Page « Abonnement » : carousel + accordéons d'offres/FAQ.
if ( document.querySelector( '[data-component="subscribe"]' ) ) {
	import( './modules/subscribe.js' ).then( ( m ) => m.init() );
}

// « Offrir un abonnement » : module accordéon (page Abonnement) ou formulaire
// autonome (page dédiée). Accordéon a11y + ouverture ?offrir/#offrir + submit REST.
if ( document.querySelector( '[data-component="gift"]' ) ) {
	import( './modules/gift.js' ).then( ( m ) => m.init() );
}

// Formulaires newsletter (page /newsletter/, module home, bloc Gutenberg) :
// module unifié single opt-in, chargé si au moins un formulaire est présent.
if ( document.querySelector( '[data-newsletter-form]' ) ) {
	import( './modules/newsletter.js' ).then( ( m ) => m.init() );
}

// Checkout : accordéon coupon, validation inline, états bouton, montant
// dynamique. Chargé uniquement sur le checkout actif (pas sur le thankyou).
if ( document.querySelector( 'form.checkout' ) ) {
	import( './modules/checkout.js' ).then( ( m ) => m.init() );
}

// Page panier : validation inline du code promo (champ vide). Chargé
// uniquement si le champ coupon du panier est présent.
if ( document.querySelector( '.cart-table__coupon' ) ) {
	import( './modules/cart-coupon.js' ).then( ( m ) => m.init() );
}

// Home / Gazette : rails horizontaux (flèches desktop) + skeleton loaders.
if ( document.querySelector( '.home-module' ) ) {
	import( './modules/rail.js' ).then( ( m ) => m.init() );
}

// Home / Gazette : slider de recettes (une recette en vedette à la fois).
// Enhancement only — sans JS le premier slide reste lisible et la piste
// scrolle nativement ; les contrôles restent masqués.
if ( document.querySelector( '[data-recipes-slider]' ) ) {
	import( './modules/recipes-slider.js' ).then( ( m ) => m.init() );
}

// Fiche produit : barre d'achat sticky + hook GA4 (galerie native conservée).
if ( document.querySelector( '.product-page .summary' ) ) {
	import( './modules/product.js' ).then( ( m ) => m.init() );
}

// Fiche produit : carrousel de la bande de vignettes (flèches prev/next sur la
// nav flexslider). Enhancement only — le swap natif fonctionne sans.
if ( document.querySelector( '.product-page__gallery' ) ) {
	import( './modules/product-gallery.js' ).then( ( m ) => m.init() );
}

// Fiche produit : onglets (Description / Reportages / Recettes / Infos techniques).
// Enhancement only — sans JS, tous les panneaux restent empilés et lisibles.
if ( document.querySelector( '[data-product-tabs]' ) ) {
	import( './modules/product-tabs.js' ).then( ( m ) => m.init() );
}

// Modale de confirmation réutilisable (déconnexion, désabonnement…).
// Enhancement progressif : sans JS, les liens [data-confirm] naviguent direct.
if ( document.querySelector( 'a[data-confirm], button[data-confirm]' ) ) {
	import( './modules/confirm-dialog.js' ).then( ( m ) => m.init() );
}

// Mon Compte : toggle opt-in newsletter « Cahiers de Delphine » (abonnés actifs).
if ( document.querySelector( '[data-account-optin]' ) ) {
	import( './modules/account-newsletter-optin.js' ).then( ( m ) => m.init() );
}

// Mon Compte : toggle des notifications web (abonnés). Import dynamique : le
// module n'existe que pour cette section. Il ne charge RIEN de OneSignal tant
// que l'utilisateur n'a pas basculé l'interrupteur.
if ( document.querySelector( '[data-push-optin]' ) ) {
	import( './modules/push-optin.js' ).then( ( m ) => m.init() );
}

// Invite d'activation des notifications, après 2 recettes consultées. Le
// serveur ne rend l'élément que pour un abonné sans opt-in ; le module décide
// ensuite de l'afficher. Il compte les recettes vues même quand il ne montre
// rien — c'est ce qui permet au seuil d'être atteint.
if ( document.querySelector( '[data-push-prompt]' ) ) {
	import( './modules/push-prompt.js' ).then( ( m ) => m.init() );
}

// Mon compte (mobile) : menu compte en bottom-sheet. Import dynamique (et non
// statique) pour garder ce module et sa dépendance partagée bottom-sheet.js hors
// du graphe de l'entrée — sinon Vite bundle bottom-sheet dans l'entrée et les
// chunks dynamiques ré-importent l'entrée (double exécution de main.js).
if ( document.querySelector( '.js-my-account-toggle' ) ) {
	import( './modules/account-menu-toggle.js' ).then( ( m ) => m.init() );
}

// Mon carnet : filtres taxonomie + recherche dynamique (filtrage client instantané).
if ( document.querySelector( '[data-carnet-filters]' ) ) {
	import( './modules/mon-carnet-filters.js' ).then( ( m ) => m.init() );
}

// Mon compte : stepper de résiliation (parcours de rétention). Chargé seulement
// si le lien discret « Se désabonner » est présent (abo résiliable). Sans JS, ce
// lien navigue vers /centre-daide/#resiliation (fallback).
if ( document.querySelector( '[data-unsub-open]' ) ) {
	import( './modules/unsubscribe-flow.js' ).then( ( m ) => m.init() );
}

// Onboarding abonné : carousel de bienvenue sur « order-received » (commande
// contenant un abonnement). Chargé seulement si le markup est présent. Sans JS,
// les 5 écrans restent empilés et lisibles (PE).
if ( document.querySelector( '[data-onboarding]' ) ) {
	import( './modules/onboarding.js' ).then( ( m ) => m.init() );
}

// Popover « carnet » sur le bouton favori : rendu par PHP pour les visiteurs
// NON connectés uniquement. Sans JS, le panneau reste replié et le bouton
// favori garde son comportement d'origine (aucune régression).
if ( document.querySelector( '[data-carnet-hint]' ) ) {
	import( './modules/carnet-hint.js' ).then( ( m ) => m.initCarnetHint() );
}
