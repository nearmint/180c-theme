<?php
/**
 * « Offrir un abonnement » — cœur fonctionnel.
 *
 * Bon cadeau différé : le donateur achète un produit simple virtuel (€30) ; le
 * jour d'envoi programmé, le système crée/ouvre l'accès `abonnement-en-ligne` du
 * bénéficiaire pour 12 mois (mécanisme : WC Memberships durée fixe — décision
 * post Phase 0). Aucun objet abonnement n'est créé.
 *
 * Ce fichier porte :
 *  - les constantes `_180C_GIFT_*` ;
 *  - l'auto-installation (version-gated) de la table `{prefix}180c_gifts` ;
 *  - les helpers `_180c_gift_*` ;
 *  - la persistance des données cadeau panier → commande ;
 *  - (Phase 4) la planification Action Scheduler + le handler de livraison.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/*
 * 1. Constantes
 */

/** SKU du produit cadeau (aligné avec le script de création du produit, non versionné). */
define( '_180C_GIFT_PRODUCT_SKU', 'ABO-GIFT-ANN' );

/** Version du schéma de la table cadeaux (incrémenter pour rejouer dbDelta). */
define( '_180C_GIFT_DB_VERSION', '1.0.0' );

/** Option stockant la version de schéma installée. */
define( '_180C_GIFT_DB_OPTION', '_180c_gifts_db_version' );

/** Clé portant les données cadeau sur l'item de panier ET de commande. */
define( '_180C_GIFT_META_KEY', '_180c_gift' );

/** Meta produit marquant le produit cadeau. */
define( '_180C_GIFT_PRODUCT_FLAG', '_180c_is_gift_product' );

/** Hook Action Scheduler de livraison (consommé en Phase 4). */
define( '_180C_GIFT_DELIVER_HOOK', '_180c_gift_deliver' );

/** Longueur maximale du message personnel. */
define( '_180C_GIFT_MESSAGE_MAXLEN', 500 );

/** Durée de l'accès offert, en mois. */
define( '_180C_GIFT_DURATION_MONTHS', 12 );

/*
 * 2. Helpers
 */

/**
 * Nom complet (préfixé) de la table des cadeaux.
 *
 * @return string
 */
function _180c_gift_table() {
	global $wpdb;
	return $wpdb->prefix . '180c_gifts';
}

/**
 * ID du produit cadeau, résolu par SKU (mémoïsé pour la requête).
 *
 * @return int 0 si le produit n'existe pas encore.
 */
function _180c_gift_product_id() {
	static $id = null;

	if ( null === $id ) {
		$id = function_exists( 'wc_get_product_id_by_sku' )
			? (int) wc_get_product_id_by_sku( _180C_GIFT_PRODUCT_SKU )
			: 0;
	}

	return $id;
}

/**
 * Le produit donné est-il le produit cadeau ?
 *
 * Test prioritaire sur la meta marqueur (robuste même si le SKU change),
 * avec repli sur l'ID résolu par SKU.
 *
 * @param int $product_id ID du produit.
 * @return bool
 */
function _180c_gift_is_gift_product( $product_id ) {
	$product_id = (int) $product_id;

	if ( $product_id <= 0 ) {
		return false;
	}

	if ( 'yes' === get_post_meta( $product_id, _180C_GIFT_PRODUCT_FLAG, true ) ) {
		return true;
	}

	return _180c_gift_product_id() === $product_id;
}

/**
 * Heure d'envoi des cadeaux programmés (fuseau Europe/Paris).
 *
 * @return int Heure 0-23 (défaut 8 = 08:00).
 */
function _180c_gift_send_hour() {
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).
	$hour = (int) apply_filters( '180c/gift_send_hour', 8 );
	return max( 0, min( 23, $hour ) );
}

/**
 * Fuseau horaire de référence pour la programmation des envois.
 *
 * @return DateTimeZone
 */
function _180c_gift_timezone() {
	return new DateTimeZone( 'Europe/Paris' );
}

/**
 * Slug du plan membership accordé par le cadeau (source unique de vérité).
 *
 * Cible = `abonnement-en-ligne` (ID 13115885, access_length_type=unlimited) : le
 * plan réellement accordé par le produit annuel `13121519` → parité exacte avec
 * un abonné annuel payant. Type « unlimited » ⇒ aucune durée pilotée par le plan,
 * une `end_date` explicite à +12 mois est donc conservée (Fallback favorable).
 * Filtrable pour absorber une future consolidation de plans.
 *
 * @return string
 */
function _180c_gift_plan_slug() {
	return (string) apply_filters( '180c/gift_plan_slug', 'abonnement-en-ligne' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hook conventionnel du thème (CLAUDE.md « 180c/ »).
}

/*
 * 3. Installation de la table (version-gated)
 */

/**
 * Crée ou met à jour la table `{prefix}180c_gifts` via dbDelta.
 *
 * Idempotent : ne s'exécute que si l'option de version diffère de la cible.
 *
 * @return void
 */
function _180c_gift_install_table() {
	if ( get_option( _180C_GIFT_DB_OPTION ) === _180C_GIFT_DB_VERSION ) {
		return;
	}

	global $wpdb;

	$table           = _180c_gift_table();
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		donor_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		recipient_email VARCHAR(190) NOT NULL DEFAULT '',
		recipient_first_name VARCHAR(120) NOT NULL DEFAULT '',
		message TEXT NULL,
		send_date DATE NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
		subscription_id BIGINT(20) UNSIGNED NULL,
		membership_id BIGINT(20) UNSIGNED NULL,
		as_action_id BIGINT(20) UNSIGNED NULL,
		created_at DATETIME NULL,
		sent_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY order_id (order_id),
		KEY status (status),
		KEY send_date (send_date)
	) {$charset_collate};";

	dbDelta( $sql );

	update_option( _180C_GIFT_DB_OPTION, _180C_GIFT_DB_VERSION, false );
}
add_action( 'admin_init', '_180c_gift_install_table' );

/**
 * Rejoue l'installation de la table en ignorant le verrou de version.
 *
 * Filet de sécurité côté front : `_180c_gift_install_table()` n'est branché que
 * sur `admin_init`, et son garde-fou de version la rend inopérante si l'option
 * a été posée alors que la table a disparu (restauration de base partielle,
 * migration d'hébergement…).
 *
 * @return void
 */
function _180c_gift_install_table_force() {
	delete_option( _180C_GIFT_DB_OPTION );
	_180c_gift_install_table();
}

/**
 * Ajoute une note privée sur la commande, si la commande est exploitable.
 *
 * Les notes de commande sont le seul canal de diagnostic réellement lisible en
 * production : `_180c_log()` n'écrit rien tant que `WP_DEBUG_LOG` est à `false`.
 *
 * @param WC_Order|false|null $order Commande cible.
 * @param string              $note  Texte de la note.
 * @return void
 */
function _180c_gift_order_note( $order, $note ) {
	if ( $order instanceof WC_Order ) {
		$order->add_order_note( $note );
	}
}

/*
 * 4. Sanitation & persistance panier → commande
 */

/**
 * Nettoie un jeu de données cadeau brut en un tableau normalisé sûr.
 *
 * @param array $raw Données brutes (clés : recipient_email, recipient_first_name,
 *                   message, send_date, send_now).
 * @return array Données normalisées.
 */
function _180c_gift_sanitize_data( $raw ) {
	$raw = is_array( $raw ) ? $raw : array();

	$send_now = ! empty( $raw['send_now'] );

	$send_date = '';
	if ( $send_now ) {
		$now       = new DateTime( 'now', _180c_gift_timezone() );
		$send_date = $now->format( 'Y-m-d' );
	} elseif ( ! empty( $raw['send_date'] ) ) {
		$candidate = sanitize_text_field( (string) $raw['send_date'] );
		$dt        = DateTime::createFromFormat( 'Y-m-d', $candidate, _180c_gift_timezone() );
		if ( $dt && $dt->format( 'Y-m-d' ) === $candidate ) {
			$send_date = $candidate;
		}
	}

	$message = isset( $raw['message'] ) ? sanitize_textarea_field( (string) $raw['message'] ) : '';
	if ( function_exists( 'mb_substr' ) ) {
		$message = mb_substr( $message, 0, _180C_GIFT_MESSAGE_MAXLEN );
	} else {
		$message = substr( $message, 0, _180C_GIFT_MESSAGE_MAXLEN );
	}

	return array(
		'recipient_email'      => isset( $raw['recipient_email'] ) ? sanitize_email( (string) $raw['recipient_email'] ) : '',
		'recipient_first_name' => isset( $raw['recipient_first_name'] ) ? sanitize_text_field( (string) $raw['recipient_first_name'] ) : '',
		'message'              => $message,
		'send_date'            => $send_date,
		'send_now'             => $send_now,
	);
}

/**
 * Porte les données cadeau sur l'item de panier à l'ajout.
 *
 * @param array $cart_item_data Données d'item.
 * @param int   $product_id     Produit ajouté.
 * @return array
 */
function _180c_gift_add_cart_item_data( $cart_item_data, $product_id ) {
	if ( ! _180c_gift_is_gift_product( $product_id ) ) {
		return $cart_item_data;
	}

	if ( empty( $cart_item_data[ _180C_GIFT_META_KEY ] ) ) {
		return $cart_item_data;
	}

	$cart_item_data[ _180C_GIFT_META_KEY ] = _180c_gift_sanitize_data( $cart_item_data[ _180C_GIFT_META_KEY ] );

	// Empêche la fusion de deux cadeaux distincts dans un même item.
	$cart_item_data['_180c_gift_uniq'] = md5( wp_json_encode( $cart_item_data[ _180C_GIFT_META_KEY ] ) . microtime() );

	return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', '_180c_gift_add_cart_item_data', 10, 2 );

/**
 * Affiche un récapitulatif lisible du cadeau dans le panier / checkout.
 *
 * @param array $item_data Données affichées.
 * @param array $cart_item Item de panier.
 * @return array
 */
function _180c_gift_get_item_data( $item_data, $cart_item ) {
	if ( empty( $cart_item[ _180C_GIFT_META_KEY ] ) || ! is_array( $cart_item[ _180C_GIFT_META_KEY ] ) ) {
		return $item_data;
	}

	$gift = $cart_item[ _180C_GIFT_META_KEY ];

	if ( ! empty( $gift['recipient_first_name'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Bénéficiaire', '180c' ),
			'value' => $gift['recipient_first_name'],
		);
	}

	if ( ! empty( $gift['send_now'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Envoi', '180c' ),
			'value' => __( 'Immédiat', '180c' ),
		);
	} elseif ( ! empty( $gift['send_date'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Envoi prévu le', '180c' ),
			'value' => date_i18n( get_option( 'date_format' ), strtotime( $gift['send_date'] ) ),
		);
	}

	return $item_data;
}
add_filter( 'woocommerce_get_item_data', '_180c_gift_get_item_data', 10, 2 );

/**
 * Masque le sélecteur de quantité du produit cadeau sur la page panier.
 *
 * Le cadeau est vendu à l'unité (quantité figée à 1) : on remplace le widget
 * stepper (.quantity.qty-input) par un simple champ caché, pour que la ligne ne
 * propose pas de modifier la quantité. Le champ caché préserve la valeur lors
 * de la mise à jour du panier. N'affecte que la ligne du produit cadeau.
 *
 * @param string $quantity_html HTML du sélecteur de quantité.
 * @param string $cart_item_key Clé de l'item de panier.
 * @param array  $cart_item     Item de panier.
 * @return string
 */
function _180c_gift_cart_item_quantity( $quantity_html, $cart_item_key, $cart_item ) {
	if ( empty( $cart_item['product_id'] ) || ! _180c_gift_is_gift_product( (int) $cart_item['product_id'] ) ) {
		return $quantity_html;
	}

	return sprintf(
		'<input type="hidden" name="cart[%s][qty]" value="%d" />',
		esc_attr( $cart_item_key ),
		max( 1, (int) $cart_item['quantity'] )
	);
}
add_filter( 'woocommerce_cart_item_quantity', '_180c_gift_cart_item_quantity', 10, 3 );

/**
 * Recopie les données cadeau de l'item de panier vers l'item de commande.
 *
 * @param WC_Order_Item_Product $item          Item de commande.
 * @param string                $cart_item_key Clé de l'item de panier.
 * @param array                 $values        Données de l'item de panier.
 * @return void
 */
function _180c_gift_create_order_line_item( $item, $cart_item_key, $values ) {
	if ( empty( $values[ _180C_GIFT_META_KEY ] ) || ! is_array( $values[ _180C_GIFT_META_KEY ] ) ) {
		return;
	}

	// Clé underscore → meta masquée dans l'admin commande.
	$item->add_meta_data( _180C_GIFT_META_KEY, $values[ _180C_GIFT_META_KEY ], true );
}
add_action( 'woocommerce_checkout_create_order_line_item', '_180c_gift_create_order_line_item', 10, 3 );

/**
 * Garde « auto-cadeau » au checkout : interdit que l'e-mail de facturation du
 * donateur soit celui du bénéficiaire (cas invité non couvert par le REST, qui
 * ne voit l'e-mail du donateur que pour les connectés).
 *
 * Court-circuit strict si aucun produit cadeau au panier → tunnel standard
 * strictement inchangé.
 *
 * @param array    $data   Données de checkout normalisées.
 * @param WP_Error $errors Collecteur d'erreurs (bloque le paiement si rempli).
 * @return void
 */
function _180c_gift_validate_checkout( $data, $errors ) {
	if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
		return;
	}

	$billing_email = isset( $data['billing_email'] ) ? strtolower( trim( (string) $data['billing_email'] ) ) : '';
	if ( '' === $billing_email ) {
		return;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( empty( $cart_item['product_id'] ) || ! _180c_gift_is_gift_product( (int) $cart_item['product_id'] ) ) {
			continue;
		}

		$recipient = '';
		if ( ! empty( $cart_item[ _180C_GIFT_META_KEY ]['recipient_email'] ) ) {
			$recipient = strtolower( trim( (string) $cart_item[ _180C_GIFT_META_KEY ]['recipient_email'] ) );
		}

		if ( '' !== $recipient && $recipient === $billing_email ) {
			$errors->add(
				'gift_self',
				__( 'L’adresse e-mail du bénéficiaire doit être différente de la vôtre. Pour vous abonner vous-même, choisissez plutôt l’offre mensuelle ou annuelle sur la page Abonnement.', '180c' )
			);
			return;
		}
	}
}
add_action( 'woocommerce_after_checkout_validation', '_180c_gift_validate_checkout', 10, 2 );

/*
 * 5. Planification (commande payée)
 */

/**
 * Une ligne cadeau existe-t-elle déjà pour cette commande ? (idempotence)
 *
 * @param int $order_id ID de commande.
 * @return bool
 */
function _180c_gift_order_has_row( $order_id ) {
	global $wpdb;
	$table = _180c_gift_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE order_id = %d LIMIT 1", (int) $order_id ) );

	return ! empty( $found );
}

/**
 * Calcule l'horodatage d'envoi (UTC). 0 = livraison immédiate.
 *
 * @param bool   $send_now  Envoi immédiat demandé.
 * @param string $send_date Date d'envoi (Y-m-d) ou vide.
 * @return int Timestamp UTC, ou 0 pour « maintenant ».
 */
function _180c_gift_compute_send_timestamp( $send_now, $send_date ) {
	if ( $send_now || '' === (string) $send_date ) {
		return 0;
	}

	$tz   = _180c_gift_timezone();
	$hour = _180c_gift_send_hour();
	$dt   = DateTime::createFromFormat( 'Y-m-d H:i:s', $send_date . sprintf( ' %02d:00:00', $hour ), $tz );

	if ( ! $dt ) {
		return 0;
	}

	$ts = $dt->getTimestamp();

	return ( $ts <= time() ) ? 0 : $ts;
}

/**
 * À la commande payée : enregistre le(s) cadeau(x) et planifie la livraison.
 *
 * Branché sur `processing` et `completed` ; idempotent par `order_id`.
 *
 * @param int $order_id ID de commande.
 * @return void
 */
function _180c_gift_on_order_paid( $order_id ) {
	global $wpdb;

	$order_id = (int) $order_id;

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	// Repère les lignes cadeau AVANT toute requête DB : une commande standard
	// (sans produit cadeau) ressort ici sans surcoût, garantissant que le tunnel
	// classique reste strictement inchangé.
	$gift_items = array();
	foreach ( $order->get_items() as $item ) {
		$product_id = (int) $item->get_product_id();
		if ( ! _180c_gift_is_gift_product( $product_id ) ) {
			continue;
		}

		$data = $item->get_meta( _180C_GIFT_META_KEY, true );
		if ( ! is_array( $data ) || empty( $data['recipient_email'] ) ) {
			continue;
		}

		$gift_items[] = array(
			'product_id' => $product_id,
			'data'       => $data,
		);
	}

	if ( empty( $gift_items ) ) {
		return;
	}

	// Idempotence : ne planifie qu'une fois par commande (processing + completed).
	if ( _180c_gift_order_has_row( $order_id ) ) {
		return;
	}

	$table    = _180c_gift_table();
	$donor_id = (int) $order->get_user_id();

	foreach ( $gift_items as $gift_item ) {
		$product_id = $gift_item['product_id'];
		$data       = _180c_gift_sanitize_data( $gift_item['data'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			array(
				'order_id'             => $order_id,
				'product_id'           => $product_id,
				'donor_user_id'        => $donor_id,
				'recipient_email'      => $data['recipient_email'],
				'recipient_first_name' => $data['recipient_first_name'],
				'message'              => $data['message'],
				'send_date'            => '' !== $data['send_date'] ? $data['send_date'] : null,
				'status'               => 'scheduled',
				'created_at'           => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			// Cas typique : table absente (l'installation est branchée sur
			// `admin_init`, jamais atteint sur un site où personne n'ouvre
			// l'admin). On rejoue l'installation puis on retente une fois,
			// sinon le cadeau payé serait perdu sans la moindre trace.
			_180c_gift_install_table_force();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert(
				$table,
				array(
					'order_id'             => $order_id,
					'product_id'           => $product_id,
					'donor_user_id'        => $donor_id,
					'recipient_email'      => $data['recipient_email'],
					'recipient_first_name' => $data['recipient_first_name'],
					'message'              => $data['message'],
					'send_date'            => '' !== $data['send_date'] ? $data['send_date'] : null,
					'status'               => 'scheduled',
					'created_at'           => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		if ( ! $inserted ) {
			_180c_gift_order_note(
				$order,
				sprintf(
					/* translators: %s: adresse e-mail du bénéficiaire. */
					__( '⚠ Cadeau non enregistré (échec d’écriture en base) pour %s. Livraison à relancer manuellement.', '180c' ),
					$data['recipient_email']
				)
			);
			_180c_log(
				'Gift : échec d’insertion de la ligne cadeau',
				array(
					'order_id' => $order_id,
					'db_error' => $wpdb->last_error,
				),
				'error'
			);
			continue;
		}

		$gift_id = (int) $wpdb->insert_id;
		$ts      = _180c_gift_compute_send_timestamp( ! empty( $data['send_now'] ), (string) $data['send_date'] );

		$action_id = 0;
		if ( 0 === $ts && function_exists( 'as_enqueue_async_action' ) ) {
			$action_id = as_enqueue_async_action( _180C_GIFT_DELIVER_HOOK, array( $gift_id ), '180c-gift' );
		} elseif ( $ts > 0 && function_exists( 'as_schedule_single_action' ) ) {
			$action_id = as_schedule_single_action( $ts, _180C_GIFT_DELIVER_HOOK, array( $gift_id ), '180c-gift' );
		}

		if ( $action_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, array( 'as_action_id' => (int) $action_id ), array( 'id' => $gift_id ), array( '%d' ), array( '%d' ) );
		} else {
			// Action Scheduler absent ou refus de planification : sans cette
			// note, le cadeau resterait « scheduled » pour toujours en silence.
			_180c_gift_order_note(
				$order,
				sprintf(
					/* translators: %s: adresse e-mail du bénéficiaire. */
					__( '⚠ Cadeau pour %s enregistré mais non planifié (Action Scheduler indisponible). Utiliser l’action « Cadeau : (re)livrer » sur cette commande.', '180c' ),
					$data['recipient_email']
				)
			);
			_180c_log( 'Gift : planification impossible', array( 'gift_id' => $gift_id ), 'error' );
		}

		// Confirmation au donateur dès l'achat (« cadeau programmé »).
		// Passe par le dispatcher : `do_action()` seul n'a aucun abonné tant que
		// WC()->mailer() n'a pas été instancié — ce qui n'est pas le cas ici, nos
		// callbacks `woocommerce_order_status_*` étant enregistrés avant ceux de
		// WC_Emails::init_transactional_emails() (branchés sur `init`).
		if ( ! _180c_gift_send_donor_email( $gift_id ) ) {
			_180c_gift_order_note(
				$order,
				__( '⚠ E-mail de confirmation cadeau non envoyé au donateur.', '180c' )
			);
		}
	}
}
add_action( 'woocommerce_order_status_processing', '_180c_gift_on_order_paid' );
add_action( 'woocommerce_order_status_completed', '_180c_gift_on_order_paid' );

/*
 * 6. Livraison
 */

/**
 * Met à jour le statut (et colonnes annexes) d'une ligne cadeau.
 *
 * @param int    $gift_id ID de la ligne cadeau.
 * @param string $status  Nouveau statut.
 * @param array  $extra   Colonnes additionnelles (membership_id, sent_at…).
 * @return void
 */
function _180c_gift_set_status( $gift_id, $status, $extra = array() ) {
	global $wpdb;

	$data    = array_merge( array( 'status' => $status ), $extra );
	$formats = array();

	foreach ( array_keys( $data ) as $key ) {
		$formats[] = in_array( $key, array( 'membership_id', 'subscription_id', 'as_action_id' ), true ) ? '%d' : '%s';
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->update( _180c_gift_table(), $data, array( 'id' => (int) $gift_id ), $formats, array( '%d' ) );
}

/**
 * Génère un identifiant de connexion unique à partir d'un e-mail.
 *
 * @param string $email Adresse e-mail.
 * @return string
 */
function _180c_gift_generate_login( $email ) {
	$parts = explode( '@', $email );
	$base  = sanitize_user( $parts[0], true );

	if ( '' === $base ) {
		$base = 'membre';
	}

	$login = $base;
	$i     = 1;
	while ( username_exists( $login ) ) {
		$login = $base . $i;
		++$i;
	}

	return $login;
}

/**
 * Ajoute N mois (calendaire, UTC) à un timestamp.
 *
 * @param int $base_ts Timestamp UTC de base.
 * @param int $months  Nombre de mois.
 * @return int Timestamp UTC résultant.
 */
function _180c_gift_add_months( $base_ts, $months ) {
	$dt = ( new DateTimeImmutable( '@' . (int) $base_ts ) )->setTimezone( new DateTimeZone( 'UTC' ) );
	return $dt->modify( '+' . (int) $months . ' months' )->getTimestamp();
}

/**
 * Handler Action Scheduler : livre le cadeau (compte + accès + e-mails).
 *
 * Idempotent : ne traite qu'une ligne au statut `scheduled`. En cas d'échec,
 * passe en `failed` (jamais `sent`) et journalise.
 *
 * @param int $gift_id ID de la ligne cadeau.
 * @return void
 *
 * @throws RuntimeException En cas d'échec de création du compte, d'indisponibilité
 *                          des memberships ou d'absence du plan (rattrapé en interne).
 */
function _180c_gift_deliver( $gift_id ) {
	global $wpdb;

	$gift_id = (int) $gift_id;
	$table   = _180c_gift_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $gift_id ) );

	if ( ! $row || 'scheduled' !== $row->status ) {
		return; // Idempotent.
	}

	try {
		// 1. Compte bénéficiaire (créé à la livraison si absent).
		// Note : wp_insert_user() n'envoie aucune notification e-mail par lui-même
		// (contrairement à wc_create_new_customer) → pas de doublon à neutraliser ;
		// le lien de définition de mot de passe est porté par l'e-mail cadeau.
		$set_password_url = '';
		$user             = get_user_by( 'email', $row->recipient_email );

		if ( ! $user ) {
			$user_id = wp_insert_user(
				array(
					'user_login'   => _180c_gift_generate_login( $row->recipient_email ),
					'user_email'   => $row->recipient_email,
					'user_pass'    => wp_generate_password( 24, true, true ),
					'first_name'   => $row->recipient_first_name,
					'display_name' => $row->recipient_first_name,
					'role'         => 'customer',
				)
			);

			if ( is_wp_error( $user_id ) ) {
				throw new RuntimeException( 'user_create_failed: ' . $user_id->get_error_message() );
			}

			$user = get_user_by( 'id', $user_id );
			$key  = get_password_reset_key( $user );
			if ( ! is_wp_error( $key ) ) {
				$set_password_url = network_site_url(
					'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ),
					'login'
				);
			}
		}

		// 2. Octroi / prolongation de l'accès abonnement-en-ligne (durée fixe).
		if ( ! function_exists( 'wc_memberships_get_membership_plan' ) ) {
			throw new RuntimeException( 'memberships_unavailable' );
		}

		$plan = wc_memberships_get_membership_plan( _180c_gift_plan_slug() );
		if ( ! $plan ) {
			throw new RuntimeException( 'plan_missing' );
		}

		$plan_id  = $plan->get_id();
		$months   = _180C_GIFT_DURATION_MONTHS;
		$now_ts   = time();
		$existing = wc_memberships_get_user_membership( $user->ID, $plan_id );

		if ( $existing ) {
			$end_ts     = (int) $existing->get_end_date( 'timestamp' );
			$active     = $existing->is_active();
			$base       = ( $active && $end_ts > $now_ts ) ? $end_ts : $now_ts;
			$target_end = _180c_gift_add_months( $base, $months );

			$existing->set_end_date( gmdate( 'Y-m-d H:i:s', $target_end ) );
			if ( ! $active ) {
				$existing->update_status( 'active' );
			}
			$membership = $existing;
		} else {
			$membership = wc_memberships_create_user_membership(
				array(
					'plan_id'  => $plan_id,
					'user_id'  => $user->ID,
					'order_id' => (int) $row->order_id,
				)
			);

			$target_end = _180c_gift_add_months( $now_ts, $months );
			$membership->set_start_date( gmdate( 'Y-m-d H:i:s', $now_ts ) );
			$membership->set_end_date( gmdate( 'Y-m-d H:i:s', $target_end ) );
			if ( ! $membership->is_active() ) {
				$membership->update_status( 'active' );
			}
		}

		// 3. Contrôle défensif : la date cible a-t-elle tenu ? (plan subscription-tied)
		$actual_end = (int) $membership->get_end_date( 'timestamp' );
		if ( $actual_end < ( $target_end - DAY_IN_SECONDS ) ) {
			_180c_gift_set_status( $gift_id, 'failed' );
			_180c_log(
				'Gift: date de fin non retenue (plan subscription-tied ?)',
				array(
					'gift_id' => $gift_id,
					'target'  => $target_end,
					'actual'  => $actual_end,
				),
				'error'
			);
			return;
		}

		// 4. E-mail cadeau au bénéficiaire (prénom + message + lien d'accès).
		//
		// Ce handler tourne dans une requête Action Scheduler : WooCommerce n'y
		// initialise jamais son mailer, donc un simple `do_action()` n'aurait
		// aucun abonné (les classes ne s'y branchent que dans leur constructeur,
		// exécuté par le filtre `woocommerce_email_classes`). Le dispatcher force
		// WC()->mailer() et renvoie le résultat réel de wp_mail().
		$context = array(
			'user_id'          => (int) $user->ID,
			'set_password_url' => $set_password_url,
			'start_ts'         => $now_ts,
			'end_ts'           => $actual_end,
		);

		$email_sent = _180c_gift_send_recipient_email( $gift_id, $context );

		// 5. Succès : l'accès est ouvert — c'est le fait matériel qui compte, on
		// ne le rejoue pas. Un e-mail perdu est en revanche signalé sur la
		// commande (les logs sont muets en prod, WP_DEBUG_LOG étant à off) :
		// sans cet e-mail, un compte tout juste créé n'a aucun mot de passe et
		// le bénéficiaire ne peut pas se connecter.
		_180c_gift_set_status(
			$gift_id,
			'sent',
			array(
				'membership_id' => (int) $membership->get_id(),
				'sent_at'       => current_time( 'mysql' ),
			)
		);

		if ( ! $email_sent ) {
			_180c_gift_order_note(
				wc_get_order( (int) $row->order_id ),
				sprintf(
					/* translators: %s: adresse e-mail du bénéficiaire. */
					__( '⚠ Accès cadeau ouvert pour %s, mais l’e-mail au bénéficiaire n’est PAS parti. Relancer via l’action « Cadeau : renvoyer l’e-mail au bénéficiaire ».', '180c' ),
					$row->recipient_email
				)
			);
			_180c_log(
				'Gift : e-mail bénéficiaire non envoyé',
				array(
					'gift_id' => $gift_id,
					'user_id' => (int) $user->ID,
				),
				'error'
			);
		}
	} catch ( Throwable $e ) {
		_180c_gift_set_status( $gift_id, 'failed' );
		_180c_log(
			'Échec de livraison cadeau',
			array(
				'gift_id' => $gift_id,
				'error'   => $e->getMessage(),
			),
			'error'
		);
	}
}
add_action( _180C_GIFT_DELIVER_HOOK, '_180c_gift_deliver', 10, 1 );

/*
 * 7. Annulation / remboursement
 */

/**
 * Désplanifie un cadeau encore programmé si la commande est annulée/remboursée.
 *
 * @param int $order_id ID de commande.
 * @return void
 */
function _180c_gift_on_order_cancelled( $order_id ) {
	global $wpdb;

	$table = _180c_gift_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$table} WHERE order_id = %d AND status = %s", (int) $order_id, 'scheduled' ) );

	foreach ( (array) $rows as $r ) {
		if ( function_exists( 'as_unschedule_action' ) ) {
			as_unschedule_action( _180C_GIFT_DELIVER_HOOK, array( (int) $r->id ), '180c-gift' );
		}
		_180c_gift_set_status( (int) $r->id, 'cancelled' );
	}
}
add_action( 'woocommerce_order_status_refunded', '_180c_gift_on_order_cancelled' );
add_action( 'woocommerce_order_status_cancelled', '_180c_gift_on_order_cancelled' );
