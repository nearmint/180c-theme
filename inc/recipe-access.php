<?php
/**
 * Logique d'accès aux recettes (paywall).
 *
 * Source de vérité unique pour déterminer si l'utilisateur courant a accès au
 * contenu complet d'une recette (ingrédients + étapes + compléments) ou non.
 *
 * Mécanismes vérifiés, dans l'ordre :
 *  1. Recette non premium (ACF `recipe_is_premium` = false) → accès libre.
 *  2. Rôle rédaction (capacité `edit_posts`) → toujours accès (preview éditeur).
 *  3. Abonnement actif : WC Memberships (plan canonique `abonne-recettes`) puis,
 *     en fallback, WC Subscriptions et la meta `access_recipes` (apps mobiles).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Détermine si l'utilisateur courant a accès au contenu complet d'une recette.
 *
 * Server-side, sans dépendance JS : le rendu du paywall est décidé ici afin
 * d'éviter tout FOUC et tout contournement client.
 *
 * @param int|null $post_id ID de la recette (par défaut : recette courante).
 * @return bool True si l'accès complet est accordé.
 */
function _180c_user_has_recipe_access( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

	// -----------------------------------------------------------------
	// Cas 1 — Recette non premium : accès libre pour tout le monde.
	// `recipe_is_premium` a pour défaut true (cf. group_recipe_fields.json).
	// -----------------------------------------------------------------
	if ( ! _180c_recipe_is_premium( $post_id ) ) {
		return true;
	}

	// -----------------------------------------------------------------
	// Cas 2 — Rédaction (admin / éditeur) : toujours accès (preview).
	// -----------------------------------------------------------------
	if ( current_user_can( 'edit_posts' ) ) {
		return true;
	}

	// -----------------------------------------------------------------
	// Cas 3 — Abonné connecté.
	// -----------------------------------------------------------------
	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();

		// a) WC Memberships — mécanisme canonique 180°C (plan `abonne-recettes`).
		if ( _180c_is_recipe_subscriber() ) {
			return true;
		}

		// b) WC Subscriptions — fallback (abonnement actif sans membership mappé).
		if ( function_exists( 'wcs_user_has_subscription' )
			&& wcs_user_has_subscription( $user_id, '', 'active' ) ) {
			return true;
		}

		// c) Meta utilisateur — fallback (octroi manuel / apps mobiles).
		if ( 'granted' === get_user_meta( $user_id, 'access_recipes', true ) ) {
			return true;
		}
	}

	/**
	 * Filtre final permettant d'accorder l'accès par un mécanisme tiers.
	 *
	 * @param bool $has_access Accès accordé (false à ce stade).
	 * @param int  $post_id    ID de la recette.
	 */
	return (bool) apply_filters( '180c/user_has_recipe_access', false, $post_id );
}

/**
 * Indique si une recette est marquée comme premium.
 *
 * Lit le champ ACF `recipe_is_premium` (défaut true si absent) et honore le
 * filtre `180c/recipe_is_always_free` pour forcer une recette en accès libre.
 *
 * @param int|null $post_id ID de la recette (par défaut : courante).
 * @return bool True si la recette est premium (donc derrière paywall).
 */
function _180c_recipe_is_premium( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

	// Override éditorial explicite : recette toujours gratuite.
	if ( $post_id && apply_filters( '180c/recipe_is_always_free', false, $post_id ) ) {
		return false;
	}

	$is_premium = true; // Défaut : premium (aligné sur le default_value ACF).

	if ( $post_id && function_exists( 'get_field' ) ) {
		$value = get_field( 'recipe_is_premium', $post_id );
		// ACF true_false renvoie un booléen ; null/'' = champ non renseigné → défaut.
		if ( null !== $value && '' !== $value ) {
			$is_premium = (bool) $value;
		}
	}

	return $is_premium;
}

/**
 * Statut d'abonnement global de l'utilisateur (indépendant d'une recette).
 *
 * Version « statut utilisateur » de _180c_user_has_recipe_access() : ne tient
 * pas compte du caractère premium d'un post ni des capacités éditeur. Sert au
 * endpoint 180c/v1/me (apps mobiles) pour piloter l'UI d'abonnement.
 *
 * Mécanismes, dans l'ordre :
 *  a) WC Memberships (plan canonique `abonne-recettes` + historiques).
 *  b) WC Subscriptions — fallback (abonnement actif sans membership mappé).
 *  c) Meta `access_recipes == 'granted'` — fallback (octroi manuel / apps).
 *
 * @param int|null $user_id ID utilisateur (par défaut : utilisateur courant).
 * @return bool True si l'utilisateur est abonné aux recettes.
 */
function _180c_user_is_subscriber( $user_id = null ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	if ( $user_id < 1 ) {
		return false;
	}

	// a) WC Memberships — mécanisme canonique (s'appuie sur l'utilisateur courant).
	if ( get_current_user_id() === $user_id && _180c_is_recipe_subscriber() ) {
		return true;
	}

	// b) WC Subscriptions — fallback.
	if ( function_exists( 'wcs_user_has_subscription' )
		&& wcs_user_has_subscription( $user_id, '', 'active' ) ) {
		return true;
	}

	// c) Meta utilisateur — fallback (octroi manuel / apps mobiles).
	if ( 'granted' === get_user_meta( $user_id, 'access_recipes', true ) ) {
		return true;
	}

	/**
	 * Filtre final permettant d'accorder le statut abonné par un tiers.
	 *
	 * @param bool $is_subscriber Statut courant (false à ce stade).
	 * @param int  $user_id       ID de l'utilisateur.
	 */
	return (bool) apply_filters( '180c/user_is_subscriber', false, $user_id );
}

/**
 * Indique si le contenu premium d'une recette doit être verrouillé en REST.
 *
 * Vrai uniquement si la recette est premium ET que l'utilisateur courant n'y a
 * pas accès. Source de vérité du champ REST `recipe_locked` et du gating des
 * champs ACF (ingrédients / étapes).
 *
 * @param int $post_id ID de la recette.
 * @return bool True si le contenu doit être verrouillé.
 */
function _180c_recipe_rest_is_locked( $post_id ) {
	$post_id = (int) $post_id;

	if ( ! _180c_recipe_is_premium( $post_id ) ) {
		return false;
	}

	return ! _180c_user_has_recipe_access( $post_id );
}
