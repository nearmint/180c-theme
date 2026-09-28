<?php
/**
 * Synchronisation du tag premium Mailchimp avec le cycle de vie WooCommerce
 * Memberships.
 *
 * POURQUOI CE FICHIER (audit vision cible, 2026-08-21)
 * ----------------------------------------------------
 * `inc/mailchimp/subscription-sync.php` branche le tag premium sur les
 * transitions d'un objet WC_Subscription. Or la source de vérité de l'accès
 * premium dans ce thème n'est PAS l'abonnement, c'est l'ADHÉSION :
 * `_180c_is_recipe_subscriber()` interroge `wc_memberships_is_user_active_member()`
 * (inc/helpers.php). Les deux ne coïncident pas :
 *
 *  - une adhésion peut naître SANS abonnement — un cadeau (`inc/gift/gift.php`
 *    octroie `abonnement-en-ligne` en direct), un octroi manuel en admin, un
 *    `wc_memberships_grant_membership_access_from_purchase` ;
 *  - une adhésion peut mourir SANS transition d'abonnement — expiration à date,
 *    mise en pause sur échec de paiement (l'abonnement passe `on-hold`, aucun
 *    hook n'était écouté), annulation suite à remboursement.
 *
 * Conséquence mesurée sur l'audience le 2026-08-21 (clone local) : des
 * adhérents actifs sans tag exploitable, et des centaines de porteurs
 * `subscribed` du tag sans aucune adhésion recettes active (adhésions
 * `wcm-paused`, `wcm-expired` ou `wcm-cancelled` encore taggées).
 *
 * Ce fichier écoute donc `wc_memberships_user_membership_status_changed`, le
 * SEUL évènement qui couvre l'ensemble des entrées et sorties d'adhésion, et
 * réaligne le tag sur l'état réel. Il ne remplace pas subscription-sync.php :
 * les deux convergent vers le même appel idempotent, le premier arrivé gagne et
 * le second est un no-op côté Mailchimp.
 *
 * Contrat respecté (RG2, RG3, RG6, RG8) :
 *  - entrée en adhésion  → contact garanti `subscribed` PUIS tag posé ;
 *  - sortie d'adhésion   → tag retiré, contact laissé `subscribed` (il retombe
 *    mécaniquement dans le gratuit, jamais désinscrit par le code) ;
 *  - idempotent, fail-open : tout échec Mailchimp est journalisé et rendu, il
 *    n'interrompt jamais le flux WooCommerce qui a déclenché la transition.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Statuts d'adhésion valant accès premium.
 *
 * Aligné sur `WC_Memberships_User_Memberships::get_active_access_membership_statuses()`
 * (le même jeu que celui sur lequel `wc_memberships_is_user_active_member()`
 * se prononce), lu depuis le plugin quand il est disponible pour éviter toute
 * dérive si l'éditeur en ajoute un.
 *
 * @return string[] Statuts sans le préfixe `wcm-`.
 */
function _180c_mc_membership_active_statuses(): array {
	if ( function_exists( 'wc_memberships' ) ) {
		$instance = wc_memberships()->get_user_memberships_instance();
		if ( $instance && method_exists( $instance, 'get_active_access_membership_statuses' ) ) {
			$statuses = (array) $instance->get_active_access_membership_statuses();
			if ( ! empty( $statuses ) ) {
				return $statuses;
			}
		}
	}

	return array( 'active', 'complimentary', 'free_trial', 'pending' );
}

/**
 * Indique si l'utilisateur porte encore une AUTRE adhésion recettes active.
 *
 * Interrogé uniquement au moment de retirer le tag : 16 comptes cumulent deux
 * adhésions recettes actives en base, retirer le tag sur la sortie de l'une
 * couperait à tort la seconde. Requête directe en base plutôt que
 * `wc_memberships_is_user_active_member()` : au moment où
 * `wc_memberships_user_membership_status_changed` se déclenche, les caches
 * objets du plugin peuvent encore porter l'ancien statut de l'adhésion courante.
 *
 * @param int $user_id       Compte concerné.
 * @param int $exclude_id    ID de l'adhésion en cours de transition (exclue).
 * @return bool True si une autre adhésion recettes active subsiste.
 */
function _180c_mc_membership_has_other_active( int $user_id, int $exclude_id ): bool {
	global $wpdb;

	if ( $user_id <= 0 ) {
		return false;
	}

	$plan_ids = array();
	foreach ( _180c_recipe_plan_slugs() as $slug ) {
		$plan = get_page_by_path( (string) $slug, OBJECT, 'wc_membership_plan' );
		if ( $plan ) {
			$plan_ids[] = (int) $plan->ID;
		}
	}
	if ( empty( $plan_ids ) ) {
		return false;
	}

	$statuses = array();
	foreach ( _180c_mc_membership_active_statuses() as $status ) {
		$statuses[] = 'wcm-' . $status;
	}

	$plan_ph   = implode( ',', array_fill( 0, count( $plan_ids ), '%d' ) );
	$status_ph = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders générés par array_fill() (2 + count($plan_ids) + count($statuses)), et exactement autant de valeurs passées à prepare() ; le sniff ne sait pas les compter.
	$sql = $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts}
		 WHERE post_type = 'wc_user_membership'
		   AND post_author = %d
		   AND ID <> %d
		   AND post_parent IN ( {$plan_ph} )
		   AND post_status IN ( {$status_ph} )",
		...array_merge( array( $user_id, $exclude_id ), $plan_ids, $statuses )
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Lecture ponctuelle sur transition d'adhésion, requête déjà préparée ci-dessus.
	return (int) $wpdb->get_var( $sql ) > 0;
}

/**
 * Adresse e-mail Mailchimp canonique d'un compte.
 *
 * L'identité d'un contact dans l'audience est l'e-mail DU COMPTE : c'est celui
 * que lisent `/newsletter/premium`, `/newsletter/status` et
 * `/newsletter/account-optin` (`$user->user_email`). Toute écriture premium doit
 * viser ce contact-là, sinon le toggle d'une app affiche « non premium » pendant
 * que la newsletter part sur une autre adresse.
 *
 * @param int $user_id ID du compte.
 * @return string E-mail valide, ou chaîne vide.
 */
function _180c_mc_member_email_for_user( int $user_id ): string {
	if ( $user_id <= 0 ) {
		return '';
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';
	}
	$email = sanitize_email( (string) $user->user_email );

	return is_email( $email ) ? $email : '';
}

/**
 * Aligne le tag premium sur l'état d'adhésion d'un compte.
 *
 * Best-effort : toute erreur est journalisée et rendue à l'appelant, jamais
 * levée. Idempotent — Mailchimp ignore la pose d'un tag déjà actif comme le
 * retrait d'un tag déjà inactif.
 *
 * @param int  $user_id ID du compte.
 * @param bool $active  True pour poser le tag, false pour le retirer.
 * @return bool True si l'écriture a abouti.
 */
function _180c_mc_membership_apply_tag( int $user_id, bool $active ): bool {
	if ( ! function_exists( '_180c_mc_premium_set' ) ) {
		return false;
	}

	$email = _180c_mc_member_email_for_user( $user_id );
	if ( '' === $email ) {
		return false;
	}

	$result = _180c_mc_premium_set( $email, $active );
	if ( is_wp_error( $result ) ) {
		_180c_log(
			'Sync tag premium adhésion échouée',
			array(
				'user_id' => $user_id,
				'active'  => $active,
				'error'   => $result->get_error_code(),
			),
			'warning'
		);
		return false;
	}

	// Traçabilité de la source : uniquement à la pose. Les tags de source ne sont
	// jamais retirés (cf. inc/mailchimp/source-tags.php). Best-effort.
	if ( $active && function_exists( '_180c_mc_tag_source' ) ) {
		_180c_mc_tag_source( $email, 'web-subscription-sync' );
	}

	// Miroir local : état initial du toggle « Mon Compte ».
	update_user_meta( $user_id, '_180c_newsletter_premium', $active ? 1 : 0 );

	/**
	 * Déclenché après un réalignement du tag premium depuis une adhésion.
	 *
	 * @param int  $user_id ID du compte.
	 * @param bool $active  État appliqué (true = tag posé, false = tag retiré).
	 */
	do_action( '180c/mc/membership_premium_synced', $user_id, $active ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.

	return true;
}

/**
 * Réagit à toute transition de statut d'une adhésion recettes.
 *
 * Couvre d'un seul tenant l'activation (achat, cadeau, octroi admin, reprise
 * après pause) et la sortie (expiration, pause sur échec de paiement,
 * annulation, remboursement) — là où subscription-sync.php ne voyait que les
 * transitions d'un WC_Subscription.
 *
 * @param mixed  $user_membership Adhésion concernée (WC_Memberships_User_Membership).
 * @param string $old_status      Ancien statut, sans le préfixe `wcm-`.
 * @param string $new_status      Nouveau statut, sans le préfixe `wcm-`.
 * @return void
 */
function _180c_mc_membership_status_changed( $user_membership, $old_status, $new_status ): void {
	unset( $old_status );

	if ( ! is_object( $user_membership ) || ! method_exists( $user_membership, 'get_plan' ) ) {
		return;
	}

	$plan = $user_membership->get_plan();
	if ( ! $plan || ! method_exists( $plan, 'get_slug' ) ) {
		return;
	}

	// Hors périmètre recettes (plan gratuit « Inscrits au site », etc.) : rien à faire.
	if ( ! in_array( (string) $plan->get_slug(), _180c_recipe_plan_slugs(), true ) ) {
		return;
	}

	$user_id = (int) $user_membership->get_user_id();
	if ( $user_id <= 0 ) {
		return;
	}

	$active = in_array( (string) $new_status, _180c_mc_membership_active_statuses(), true );

	// Sortie d'adhésion : ne retirer le tag que si plus AUCUNE autre adhésion
	// recettes active ne subsiste sur ce compte.
	if ( ! $active && _180c_mc_membership_has_other_active( $user_id, (int) $user_membership->get_id() ) ) {
		return;
	}

	/**
	 * Faut-il synchroniser le tag premium pour cette transition d'adhésion ?
	 *
	 * @param bool   $sync       True par défaut.
	 * @param int    $user_id    Compte concerné.
	 * @param bool   $active     État cible (true = tag posé).
	 * @param string $new_status Nouveau statut d'adhésion.
	 */
	$sync = (bool) apply_filters( '180c/mc/membership_syncs_premium', true, $user_id, $active, (string) $new_status ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks 180c/ imposé par CLAUDE.md.
	if ( ! $sync ) {
		return;
	}

	_180c_mc_membership_apply_tag( $user_id, $active );
}
add_action( 'wc_memberships_user_membership_status_changed', '_180c_mc_membership_status_changed', 10, 3 );

/**
 * Adhésion nouvellement créée déjà active → pose le tag.
 *
 * `wc_memberships_user_membership_status_changed` ne se déclenche que sur une
 * TRANSITION. Une adhésion créée directement en `active` — le cas d'un cadeau
 * (`inc/gift/gift.php`) ou d'un octroi programmé — n'en produit pas toujours.
 * Ce second point d'entrée comble ce trou ; il est idempotent avec le premier.
 *
 * @param mixed $membership_plan Plan concerné.
 * @param array $args            { user_id, user_membership_id, is_update }.
 * @return void
 */
function _180c_mc_membership_created( $membership_plan, $args ): void {
	if ( ! is_object( $membership_plan ) || ! method_exists( $membership_plan, 'get_slug' ) ) {
		return;
	}
	if ( ! in_array( (string) $membership_plan->get_slug(), _180c_recipe_plan_slugs(), true ) ) {
		return;
	}

	$user_id = isset( $args['user_id'] ) ? (int) $args['user_id'] : 0;
	$mid     = isset( $args['user_membership_id'] ) ? (int) $args['user_membership_id'] : 0;
	if ( $user_id <= 0 || $mid <= 0 ) {
		return;
	}

	$status = str_replace( 'wcm-', '', (string) get_post_status( $mid ) );
	if ( ! in_array( $status, _180c_mc_membership_active_statuses(), true ) ) {
		return;
	}

	_180c_mc_membership_apply_tag( $user_id, true );
}
add_action( 'wc_memberships_user_membership_created', '_180c_mc_membership_created', 10, 2 );
