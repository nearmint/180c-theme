<?php
/**
 * Deeplink d'ajout au carnet — /mon-carnet/?add={slug-recette}.
 *
 * Lien partageable (apps iOS/Android, newsletter) qui, depuis la page
 * « Mon carnet », ajoute la recette au carnet de l'utilisateur puis redirige
 * vers la fiche recette en y portant un drapeau éphémère (`?carnet=added|already`)
 * que le JS de la page recette transforme en toast de confirmation, avant de
 * nettoyer l'URL (cf. src/js/modules/favorites.js).
 *
 * Sécurité — GET mutant SANS nonce, choix de conception assumé : le deeplink
 * doit rester cliquable hors-session (newsletter, apps) et donc partageable. Les
 * garde-fous qui rendent ce choix sûr :
 *   - action STRICTEMENT additive (jamais de suppression via ce canal) ;
 *   - idempotente (un re-clic ne crée pas de doublon → état 'already', sans même
 *     bumper la date d'ajout, cf. _180c_favorites_add()) ;
 *   - session authentifiée obligatoire (un visiteur déconnecté est renvoyé vers
 *     la connexion, le deeplink complet servant d'URL de retour post-login).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', '_180c_handle_carnet_deeplink' );

/**
 * Traite le deeplink d'ajout au carnet sur la page « Mon carnet ».
 *
 * S'exécute avant le rendu (et avant la redirection inline « déconnecté » de
 * template-mon-carnet.php). Si le paramètre `add` est absent ou si la recette
 * est introuvable, la fonction laisse la page se rendre normalement.
 *
 * @return void
 */
function _180c_handle_carnet_deeplink(): void {
	// Cible uniquement la page « Mon carnet » (détection par template, robuste au slug).
	if ( ! is_page_template( 'template-mon-carnet.php' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET mutant assumé (cf. en-tête).
	$raw_slug = isset( $_GET['add'] ) ? sanitize_title( wp_unslash( $_GET['add'] ) ) : '';
	if ( '' === $raw_slug ) {
		// Pas de deeplink : la page « Mon carnet » se rend normalement.
		return;
	}

	// URL canonique du deeplink (page « Mon carnet » + slug nettoyé), réutilisée
	// comme URL de retour après connexion. On ne reconstruit pas REQUEST_URI brut
	// pour ne pas propager d'autres paramètres dans le redirect_to de login.
	$deeplink_url = add_query_arg( 'add', $raw_slug, get_permalink( get_queried_object_id() ) );

	// Déconnecté : on renvoie vers la connexion ; le deeplink complet est l'URL
	// de retour, qui rejouera ce handler une fois authentifié.
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( $deeplink_url ) );
		exit;
	}

	// Résolution slug → recette publiée.
	$recipe = get_page_by_path( $raw_slug, OBJECT, 'recipe' );
	if ( ! $recipe || ! _180c_favorites_is_valid_recipe( $recipe->ID ) ) {
		// Slug invalide / recette introuvable ou non publiée : param ignoré.
		return;
	}

	// Gate d'accès : recette premium réservée aux abonnés → page Abonnement.
	if ( ! _180c_user_has_recipe_access( $recipe->ID ) ) {
		$subscribe_url = apply_filters( '180c/subscription_url', home_url( '/abonnement/' ) );
		wp_safe_redirect( $subscribe_url );
		exit;
	}

	// Ajout idempotent via le service partagé (même couche que le REST POST).
	$state  = _180c_favorites_add( get_current_user_id(), $recipe->ID ); // 'added' | 'already'.
	$target = add_query_arg( 'carnet', $state, get_permalink( $recipe->ID ) );

	wp_safe_redirect( $target );
	exit;
}
