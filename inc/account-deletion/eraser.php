<?php
/**
 * Suppression de compte — pipeline d'exécution.
 *
 * Principe directeur : les commandes et les abonnements terminés ne sont NI
 * supprimés, NI anonymisés, NI mis à la corbeille. Ils sont simplement détachés
 * du compte (`customer_id` = 0), obligations comptables obligent.
 *
 * Ce détachement doit précéder `wp_delete_user()`, sans quoi deux callbacks du
 * cœur des extensions s'exécutent sur des objets encore rattachés :
 *
 *  - WC_Subscriptions_Manager::trash_users_subscriptions() (hook `delete_user`)
 *    appelle `$subscription->delete( true )` — une suppression DÉFINITIVE, en
 *    dépit du nom de la méthode ;
 *  - WC_Memberships_User_Memberships::delete_user_memberships() met les
 *    adhésions à la corbeille.
 *
 * Une fois le détachement fait et vérifié (contrôle 14), ces deux callbacks ne
 * trouvent plus rien et deviennent inoffensifs. Si la vérification échoue, tout
 * est remis en place et la suppression est abandonnée.
 *
 * Deux tables du brief se sont révélées inexploitables à l'audit du 2026-09-01,
 * et sont donc ignorées plutôt que traitées à l'aveugle :
 *  - `{prefix}login_log` (Simple JWT Login) n'a ni user_id ni e-mail : ses
 *    colonnes sont id / ip / msg / l_added / l_status / l_type. Rien n'y relie
 *    une ligne à un compte.
 *  - `{prefix}180c_consent_log` n'a pas non plus de colonne user_id : le
 *    consentement y est stocké sous un `consent_id` anonyme (UUID).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Types de contenu qu'un compte client peut légitimement porter en post_author.
 *
 * Tout autre type découvert au contrôle 3 interrompt le pipeline : le compte
 * porte du contenu éditorial, la suppression n'est plus une opération anodine.
 *
 * @return string[] Types de contenu.
 */
function _180c_account_deletion_expected_authored_types(): array {
	return array( 'shop_order', 'shop_subscription', 'shop_order_refund', 'wc_user_membership' );
}

/**
 * Valeur de `post_author` posée par WooCommerce à la création d'une commande.
 *
 * Référence : WC_Abstract_Order_Data_Store_CPT::create(), qui passe
 * `'post_author' => 1` à wp_insert_post(). Les commandes antérieures à
 * WooCommerce 3.5.0 portent en revanche l'ID du client — d'où la remise à cette valeur au moment du détachement.
 *
 * @return int ID d'auteur.
 */
function _180c_account_deletion_default_post_author(): int {
	return 1;
}

/**
 * Exécute (ou simule) la suppression complète d'un compte.
 *
 * @param int  $user_id Identifiant WP.
 * @param bool $dry_run Vrai pour produire le plan sans aucune mutation.
 * @return array{ok:bool,plan:array,errors:array} Résultat détaillé.
 */
function _180c_account_deletion_execute( int $user_id, bool $dry_run = false ): array {
	$plan   = array( 'dry_run' => $dry_run );
	$errors = array();

	// --- 1. Éligibilité --------------------------------------------------
	if ( ! _180c_account_deletion_is_eligible( $user_id ) ) {
		return _180c_account_deletion_abort( $plan, $errors, 'not_eligible', $user_id );
	}

	// --- 2. Instantané ---------------------------------------------------
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return _180c_account_deletion_abort( $plan, $errors, 'user_not_found', $user_id );
	}

	$snapshot = array(
		'email'      => (string) $user->user_email,
		'first_name' => (string) $user->first_name,
	);

	// --- 3. Contenu porté par le compte ----------------------------------
	$authored = _180c_account_deletion_authored_types( $user_id );

	$plan['authored'] = $authored;

	$unexpected = array_diff( array_keys( $authored ), _180c_account_deletion_expected_authored_types() );

	if ( ! empty( $unexpected ) ) {
		$errors[] = 'unexpected_authored_types: ' . implode( ',', $unexpected );

		return _180c_account_deletion_abort( $plan, $errors, 'unexpected_authored_types', $user_id );
	}

	// --- 4. Inventaire ---------------------------------------------------
	$orders        = _180c_account_deletion_get_orders( $user_id );
	$subscriptions = _180c_account_deletion_get_subscriptions( $user_id );
	$memberships   = _180c_account_deletion_get_memberships( $user_id );

	$snapshot['had_subscription'] = ! empty( $subscriptions );

	$plan['orders']        = _180c_account_deletion_ids( $orders );
	$plan['subscriptions'] = _180c_account_deletion_ids( $subscriptions );
	$plan['memberships']   = _180c_account_deletion_ids( $memberships );
	$plan['snapshot']      = array(
		'has_email'        => '' !== $snapshot['email'],
		'had_subscription' => $snapshot['had_subscription'],
	);

	// --- 5 à 10. Données propres au compte -------------------------------
	$plan['favorites']   = _180c_account_deletion_purge_favorites( $user_id, $dry_run );
	$plan['tokens']      = _180c_account_deletion_purge_payment_data( $user_id, $dry_run );
	$plan['comments']    = _180c_account_deletion_anonymise_comments( $user_id, $dry_run );
	$plan['login_log']   = 'skipped: table sans identifiant utilisateur';
	$plan['consent_log'] = 'skipped: table sans colonne user_id';

	if ( ! $dry_run ) {
		_180c_account_deletion_delete_memberships( $memberships );
	}

	// --- 11 et 12. Services externes (non bloquants) ---------------------
	// Retrait systématique : un compte supprimé ne reçoit plus de newsletter.
	// Mode archive, donc réversible côté utilisateur (cf. externals.php).
	$newsletter_removed = _180c_account_deletion_mailchimp_forget( $snapshot['email'], $dry_run );

	if ( ! $newsletter_removed && ! $dry_run ) {
		$errors[] = 'mailchimp_archive_failed';
	}

	$plan['newsletter_removed'] = $newsletter_removed;
	$plan['onesignal']          = _180c_account_deletion_onesignal_forget( $user_id, $dry_run );

	// --- 13. Détachement --------------------------------------------------
	$detachable = array_merge( array_values( $orders ), array_values( $subscriptions ) );

	if ( $dry_run ) {
		$plan['detach'] = _180c_account_deletion_ids( $detachable );
		$plan['ok']     = true;

		return array(
			'ok'     => true,
			'plan'   => $plan,
			'errors' => $errors,
		);
	}

	$restore = _180c_account_deletion_detach( $detachable, $user_id );

	$plan['detached'] = array_keys( $restore );

	// --- 14. Vérifications avant le point de non-retour -------------------
	$assertion = _180c_account_deletion_assert_detached( $user_id );

	if ( '' !== $assertion ) {
		_180c_account_deletion_rollback( $restore );
		$errors[] = 'assertion_failed: ' . $assertion;

		return _180c_account_deletion_abort( $plan, $errors, 'assertion_failed', $user_id );
	}

	// --- 15. Suppression du compte ----------------------------------------
	// Point d'extension public du thème, conservé du flux historique : c'est
	// lui qui déclenche _180c_favorites_purge_user() (inc/favorites.php).
	do_action( '180c/account_deleted', $user_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).

	require_once ABSPATH . 'wp-admin/includes/user.php';

	if ( ! wp_delete_user( $user_id ) ) {
		_180c_account_deletion_rollback( $restore );
		$errors[] = 'wp_delete_user_failed';

		return _180c_account_deletion_abort( $plan, $errors, 'wp_delete_user_failed', $user_id );
	}

	// --- 16 et 17. Clôture -------------------------------------------------
	// `reason` et `reason_text` restent en base mais ne sont plus alimentés :
	// le formulaire ne pose plus de question (colonnes réservées, cf. README).
	$plan['logged'] = _180c_account_deletion_log_record(
		array(
			'had_subscription'   => $snapshot['had_subscription'],
			'newsletter_removed' => $newsletter_removed,
		)
	);

	$plan['final_email'] = _180c_account_deletion_send_farewell(
		array(
			'email'              => $snapshot['email'],
			'first_name'         => $snapshot['first_name'],
			'newsletter_removed' => $newsletter_removed,
		)
	);

	$plan['ok'] = true;

	return array(
		'ok'     => true,
		'plan'   => $plan,
		'errors' => $errors,
	);
}

/**
 * Interrompt le pipeline en journalisant la raison.
 *
 * @param array  $plan    Plan partiel.
 * @param array  $errors  Erreurs accumulées.
 * @param string $reason  Motif d'abandon.
 * @param int    $user_id Identifiant WP (jamais d'e-mail dans les traces).
 * @return array{ok:bool,plan:array,errors:array} Résultat en échec.
 */
function _180c_account_deletion_abort( array $plan, array $errors, string $reason, int $user_id ): array {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Trace d'abandon, sans donnée personnelle.
	error_log( '[180c account-deletion] abandon (' . $reason . ') pour user ' . $user_id );

	$plan['ok']      = false;
	$plan['aborted'] = $reason;
	$errors[]        = $reason;

	return array(
		'ok'     => false,
		'plan'   => $plan,
		'errors' => array_values( array_unique( $errors ) ),
	);
}

/**
 * Répartition par type de contenu des posts dont le compte est l'auteur.
 *
 * @param int $user_id Identifiant WP.
 * @return array<string,int> Type de contenu => nombre.
 */
function _180c_account_deletion_authored_types( int $user_id ): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_author = %d GROUP BY post_type",
			$user_id
		),
		ARRAY_A
	);

	$types = array();

	foreach ( (array) $rows as $row ) {
		$types[ (string) $row['post_type'] ] = (int) $row['total'];
	}

	return $types;
}

/**
 * Extrait les IDs d'une liste d'objets WooCommerce.
 *
 * @param array $objects Objets exposant get_id().
 * @return int[] Identifiants.
 */
function _180c_account_deletion_ids( array $objects ): array {
	$ids = array();

	foreach ( $objects as $object ) {
		if ( is_object( $object ) && method_exists( $object, 'get_id' ) ) {
			$ids[] = (int) $object->get_id();
		}
	}

	return $ids;
}

/**
 * Supprime les favoris actifs et les pierres tombales du compte.
 *
 * @param int  $user_id Identifiant WP.
 * @param bool $dry_run Vrai pour compter sans supprimer.
 * @return array<string,int> Table => nombre de lignes concernées.
 */
function _180c_account_deletion_purge_favorites( int $user_id, bool $dry_run ): array {
	global $wpdb;

	$counts = array();

	foreach ( array( _180c_favorites_table(), _180c_favorites_tombstones_table() ) as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );

		$counts[ $table ] = $total;

		if ( ! $dry_run && $total > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
		}
	}

	return $counts;
}

/**
 * Supprime les jetons de paiement et la session WooCommerce du compte.
 *
 * @param int  $user_id Identifiant WP.
 * @param bool $dry_run Vrai pour compter sans supprimer.
 * @return int Nombre de jetons concernés.
 */
function _180c_account_deletion_purge_payment_data( int $user_id, bool $dry_run ): int {
	if ( ! class_exists( 'WC_Payment_Tokens' ) ) {
		return 0;
	}

	$tokens = WC_Payment_Tokens::get_customer_tokens( $user_id );
	$total  = is_array( $tokens ) ? count( $tokens ) : 0;

	if ( $dry_run ) {
		return $total;
	}

	foreach ( (array) $tokens as $token ) {
		if ( is_object( $token ) && method_exists( $token, 'delete' ) ) {
			$token->delete();
		}
	}

	if ( function_exists( 'WC' ) && isset( WC()->session ) && method_exists( WC()->session, 'delete_session' ) ) {
		WC()->session->delete_session( $user_id );
	}

	return $total;
}

/**
 * Anonymise les commentaires laissés par le compte.
 *
 * Les commentaires sont désactivés sur le site, mais 13 lignes historiques
 * portent encore un user_id : elles sont détachées plutôt que supprimées.
 *
 * @param int  $user_id Identifiant WP.
 * @param bool $dry_run Vrai pour compter sans modifier.
 * @return int Nombre de commentaires concernés.
 */
function _180c_account_deletion_anonymise_comments( int $user_id, bool $dry_run ): int {
	$comments = get_comments(
		array(
			'user_id' => $user_id,
			'number'  => 0,
		)
	);

	$total = is_array( $comments ) ? count( $comments ) : 0;

	if ( $dry_run ) {
		return $total;
	}

	foreach ( (array) $comments as $comment ) {
		wp_update_comment(
			array(
				'comment_ID'           => (int) $comment->comment_ID,
				'user_id'              => 0,
				'comment_author'       => __( 'Anonyme', '180c' ),
				'comment_author_email' => '',
				'comment_author_url'   => '',
				'comment_author_IP'    => '',
			)
		);
	}

	return $total;
}

/**
 * Supprime définitivement les adhésions terminées du compte.
 *
 * @param array $memberships Adhésions WC Memberships.
 * @return void
 */
function _180c_account_deletion_delete_memberships( array $memberships ): void {
	foreach ( $memberships as $membership ) {
		if ( ! is_object( $membership ) || ! method_exists( $membership, 'get_id' ) ) {
			continue;
		}

		wp_delete_post( (int) $membership->get_id(), true );
	}
}

/**
 * Détache commandes et abonnements du compte.
 *
 * Retourne de quoi tout remettre en place si une vérification ultérieure
 * échoue : pour chaque objet, l'ancien `customer_id` et l'ancien `post_author`.
 *
 * @param array $objects Commandes et abonnements.
 * @param int   $user_id Identifiant WP.
 * @return array<int,array{customer_id:int,post_author:int}> État précédent, indexé par ID.
 */
function _180c_account_deletion_detach( array $objects, int $user_id ): array {
	global $wpdb;

	$restore = array();
	$default = _180c_account_deletion_default_post_author();

	foreach ( $objects as $object ) {
		if ( ! is_object( $object ) || ! method_exists( $object, 'get_id' ) ) {
			continue;
		}

		$id     = (int) $object->get_id();
		$author = (int) get_post_field( 'post_author', $id );

		$restore[ $id ] = array(
			'customer_id' => (int) $object->get_customer_id(),
			'post_author' => $author,
		);

		$object->set_customer_id( 0 );
		$object->save();

		// Commandes antérieures à WooCommerce 3.5.0 : post_author = ID client.
		if ( $author === $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->posts, array( 'post_author' => $default ), array( 'ID' => $id ), array( '%d' ), array( '%d' ) );
			clean_post_cache( $id );
		}
	}

	_180c_account_deletion_flush_subscription_cache( $user_id );

	return $restore;
}

/**
 * Remet en place les rattachements défaits par _180c_account_deletion_detach().
 *
 * @param array<int,array{customer_id:int,post_author:int}> $restore État précédent.
 * @return void
 */
function _180c_account_deletion_rollback( array $restore ): void {
	global $wpdb;

	foreach ( $restore as $id => $previous ) {
		$object = wc_get_order( (int) $id );

		if ( $object && method_exists( $object, 'set_customer_id' ) ) {
			$object->set_customer_id( (int) $previous['customer_id'] );
			$object->save();
		}

		if ( (int) get_post_field( 'post_author', (int) $id ) !== (int) $previous['post_author'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->posts, array( 'post_author' => (int) $previous['post_author'] ), array( 'ID' => (int) $id ), array( '%d' ), array( '%d' ) );
			clean_post_cache( (int) $id );
		}
	}

	if ( ! empty( $restore ) ) {
		$first = wc_get_order( (int) array_key_first( $restore ) );

		if ( $first && method_exists( $first, 'get_customer_id' ) ) {
			_180c_account_deletion_flush_subscription_cache( (int) $first->get_customer_id() );
		}
	}
}

/**
 * Vérifie qu'il ne reste plus rien de rattaché au compte.
 *
 * Les contrôles interrogent la base DIRECTEMENT, sans passer par les getters
 * des extensions : `wc_memberships_get_user_memberships()` mémorise ses
 * résultats dans une propriété d'objet, et `wcs_get_users_subscriptions()`
 * dans une user meta. Après les mutations qui précèdent, ces caches sont
 * périmés dans la même requête — une assertion qui les lirait déclencherait un
 * rollback sur des données pourtant correctes.
 *
 * @param int $user_id Identifiant WP.
 * @return string Chaîne vide si tout est détaché, sinon le nom du contrôle en échec.
 */
function _180c_account_deletion_assert_detached( int $user_id ): string {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$attached = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_customer_user' AND meta_value = %s",
			(string) $user_id
		)
	);

	if ( $attached > 0 ) {
		return 'orders_or_subscriptions_still_attached';
	}

	if ( ! empty( _180c_account_deletion_authored_types( $user_id ) ) ) {
		return 'authored_content_still_attached';
	}

	return '';
}
