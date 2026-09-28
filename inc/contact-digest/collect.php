<?php
/**
 * Digest contact — collecte des messages dans la table de logs de WP Mail Logging.
 *
 * SOURCE
 * ------
 * `{$wpdb->prefix}wpml_mails`, alimentée par le plugin WP Mail Logging. Le thème n'écrit jamais dans cette table : il la lit.
 *
 * Sélection par l'en-tête `X-180C-Origin: contact-form` posé par
 * `_180c_contact_headers()` (inc/rest/contact.php). L'accusé de réception
 * adressé au visiteur porte `contact-ack` et n'est donc jamais collecté : il
 * reprend le corps du message, il ferait doublon.
 *
 * TROIS PIÈGES VÉRIFIÉS SUR LE PLUGIN (lecture du code + envoi réel)
 * ------------------------------------------------------------------------
 * 1. **Les en-têtes sont concaténés en UNE chaîne, séparés par les deux
 *    caractères littéraux `,\n`** — un backslash suivi d'un « n », pas un saut
 *    de ligne. `WPML_MailExtractor::joinArrayWithCommaAndNewLine()` fait
 *    `implode( ',\n', $array )` en quotes SIMPLES. Découper sur "\n" ne rend
 *    donc qu'un seul morceau. Cf. `_180c_contact_digest_split_headers()`.
 *
 * 2. **`timestamp` est en heure LOCALE du site**, posé par
 *    `current_time( 'mysql' )` et non `gmdate()`. Comparer la fenêtre à des
 *    bornes UTC décalerait la sélection d'une heure en permanence (le fuseau du
 *    site est figé à +01:00). Les bornes sont donc formatées avec `wp_date()`.
 *
 * 3. **`subject` est un VARCHAR(200)** tronqué par le plugin à 195 caractères
 *    suivis de « ... ». Un objet ne peut donc pas être considéré comme complet.
 *
 * FRAGILITÉ ASSUMÉE
 * -----------------
 * Ce module dépend d'une table appartenant à un plugin tiers. Si WP Mail
 * Logging est désactivé, ou si sa rotation de logs est activée avec une
 * rétention plus courte que la cadence du digest, la collecte rend un tableau
 * vide sans erreur. `_180c_contact_digest_source_status()` expose cet état à la
 * page d'administration pour que le silence soit lisible plutôt que deviné.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/** Suffixe (sans préfixe) de la table de logs de WP Mail Logging. */
const _180C_CONTACT_DIGEST_TABLE = 'wpml_mails';

/** Plafond de sécurité : nombre de messages lus par fenêtre. */
const _180C_CONTACT_DIGEST_LIMIT = 200;

/** Longueur maximale du corps transmis à l'analyse, en caractères. */
const _180C_CONTACT_DIGEST_BODY_MAX = 1500;

/** Fenêtre de repli quand aucune exécution précédente n'est enregistrée, en secondes. */
const _180C_CONTACT_DIGEST_FALLBACK_WINDOW = 7 * DAY_IN_SECONDS;

/** Option portant l'horodatage Unix de la dernière exécution réussie. */
const _180C_CONTACT_DIGEST_LAST_RUN_OPTION = '_180c_contact_digest_last_run';

/**
 * Nom complet et préfixé de la table de logs.
 *
 * @return string Ex. `wp_wpml_mails`.
 */
function _180c_contact_digest_table(): string {
	global $wpdb;

	return $wpdb->prefix . _180C_CONTACT_DIGEST_TABLE;
}

/**
 * Vérifie que la table de logs existe sur l'environnement courant.
 *
 * Elle est créée à l'activation de WP Mail Logging et supprimée à sa
 * désinstallation. Son absence n'est pas une erreur du thème : c'est un état
 * qu'il faut savoir nommer.
 *
 * @return bool
 */
function _180c_contact_digest_table_exists(): bool {
	global $wpdb;

	$table = _180c_contact_digest_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lecture d'une table tierce, sans équivalent d'API.
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
}

/**
 * Plancher de rétention recommandé pour la rotation de logs, en jours.
 *
 * La rotation DOIT être active — c'est son absence qui a laissé la table
 * atteindre ~115 Mo en production. Mais sa rétention doit couvrir bien plus que
 * la cadence du digest, parce que plusieurs délais s'additionnent avant qu'un
 * message soit effectivement lu :
 *
 *   7 j  — cadence nominale du digest ;
 *   +7 j — une occurrence annulée ou un envoi échoué laisse la fenêtre ouverte
 *          une semaine de plus, par construction ;
 *   +7 j — retard du WP-Cron. La production n'a pas de cron système, et WP
 *          Super Cache sert des pages sans amorcer WordPress : une page servie
 *          depuis le cache ne déclenche aucune tâche planifiée ;
 *   +~9 j — délai humain. La notice d'occurrence annulée ne s'affiche que dans
 *          l'administration : encore faut-il que quelqu'un s'y connecte.
 *
 * Soit 30 jours — qui se trouve être exactement la valeur par défaut du champ
 * `log-rotation-delete-time-days` de WP Mail Logging. Cocher la case suffit
 * donc à obtenir un réglage conforme, sans rien calculer.
 *
 * Filtrable, et non figé : la cadence du digest ou le profil de trafic peuvent
 * changer, et le plancher doit alors suivre sans modification de code.
 *
 * @return int Nombre de jours.
 */
function _180c_contact_digest_rotation_floor_days(): int {
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks `180c/` imposé par CLAUDE.md.
	return max( 8, (int) apply_filters( '180c/contact_digest_rotation_floor_days', 30 ) );
}

/**
 * Taille de table à partir de laquelle une rotation absente devient un défaut.
 *
 * En dessous, une table qui grossit ne coûte rien de perceptible et ne mérite
 * pas d'alerte : avertir sur un non-problème est le meilleur moyen de rendre
 * les alertes invisibles. Au-delà, deux coûts apparaissent — le poids des
 * sauvegardes, et surtout le balayage complet que fait la collecte chaque
 * semaine, `timestamp` ne portant aucun index dans le schéma du plugin (seuls
 * `mail_id` en PRIMARY et un FULLTEXT sur `message`).
 *
 * @return float Taille en Mo.
 */
function _180c_contact_digest_size_alert_mb(): float {
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Namespace de hooks `180c/` imposé par CLAUDE.md.
	return (float) apply_filters( '180c/contact_digest_size_alert_mb', 50.0 );
}

/**
 * Taille occupée par la table, en Mo.
 *
 * @return float 0.0 si la table est absente ou la mesure indisponible.
 */
function _180c_contact_digest_table_size_mb(): float {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- information_schema, sans équivalent d'API.
	$size = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT ROUND( ( data_length + index_length ) / 1024 / 1024, 2 )
			 FROM information_schema.TABLES
			 WHERE table_schema = %s AND table_name = %s',
			DB_NAME,
			_180c_contact_digest_table()
		)
	);

	return null === $size ? 0.0 : (float) $size;
}

/**
 * Décrit l'état de la source, pour affichage en administration.
 *
 * Distingue les silences qu'un simple « 0 message » confondrait : plugin
 * absent, rotation de logs trop courte, ou semaine réellement calme.
 *
 * `rotation_state` résume le diagnostic :
 *   - `ok`        : rotation par date active, rétention au-dessus du plancher ;
 *   - `too_short` : rotation par date active, mais rétention sous le plancher ;
 *   - `off_large` : aucune rotation par date et table au-delà du seuil de taille ;
 *   - `off_small` : aucune rotation par date, table encore modeste — pas d'alerte.
 *
 * @return array{ok:bool, table:string, exists:bool, rotation_days:int, rotation_count:int, size_mb:float, floor_days:int, rotation_state:string}
 */
function _180c_contact_digest_source_status(): array {
	$settings = get_option( 'wpml_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	$rotation_days  = 0;
	$rotation_count = 0;

	if ( ! empty( $settings['log-rotation-delete-time'] ) ) {
		$rotation_days = (int) ( $settings['log-rotation-delete-time-days'] ?? 0 );
	}

	if ( ! empty( $settings['log-rotation-limit-amout'] ) ) {
		$rotation_count = (int) ( $settings['log-rotation-limit-amout-keep'] ?? 0 );
	}

	$exists     = _180c_contact_digest_table_exists();
	$size_mb    = $exists ? _180c_contact_digest_table_size_mb() : 0.0;
	$floor_days = _180c_contact_digest_rotation_floor_days();

	if ( $rotation_days > 0 ) {
		$state = $rotation_days >= $floor_days ? 'ok' : 'too_short';
	} else {
		$state = $size_mb >= _180c_contact_digest_size_alert_mb() ? 'off_large' : 'off_small';
	}

	return array(
		'ok'             => $exists,
		'table'          => _180c_contact_digest_table(),
		'exists'         => $exists,
		'rotation_days'  => $rotation_days,
		'rotation_count' => $rotation_count,
		'size_mb'        => $size_mb,
		'floor_days'     => $floor_days,
		'rotation_state' => $state,
	);
}

/**
 * Calcule les bornes de la fenêtre de collecte, en horodatages Unix.
 *
 * La borne basse est la dernière exécution réussie ; à défaut, sept jours en
 * arrière. Une valeur STRICTEMENT dans le futur (horloge serveur reculée,
 * import de base) est ignorée au profit du repli : mieux vaut un digest qui
 * répète que rien du tout.
 *
 * Strictement, et non `>=` : une dernière exécution ÉGALE à l'instant courant
 * est le cas parfaitement légitime de deux exécutions dans la même seconde —
 * le bouton « envoyer maintenant » cliqué deux fois. Traiter cette égalité
 * comme suspecte faisait repartir la fenêtre sept jours en arrière et
 * renvoyait tout l'historique au lieu d'un « RAS ». Constaté en recette.
 *
 * @return array{from:int, to:int}
 */
function _180c_contact_digest_window(): array {
	$now      = time();
	$last_run = (int) get_option( _180C_CONTACT_DIGEST_LAST_RUN_OPTION, 0 );

	if ( $last_run <= 0 || $last_run > $now ) {
		$last_run = $now - _180C_CONTACT_DIGEST_FALLBACK_WINDOW;
	}

	return array(
		'from' => $last_run,
		'to'   => $now,
	);
}

/**
 * Instant avant lequel la rotation de logs a déjà tout effacé.
 *
 * @return int Horodatage Unix, ou 0 si aucune rotation par date n'est active.
 */
function _180c_contact_digest_rotation_cutoff(): int {
	$settings = get_option( 'wpml_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	if ( empty( $settings['log-rotation-delete-time'] ) ) {
		return 0;
	}

	$days = (int) ( $settings['log-rotation-delete-time-days'] ?? 0 );

	return $days > 0 ? time() - ( $days * DAY_IN_SECONDS ) : 0;
}

/**
 * Nombre de jours de la fenêtre que la rotation de logs a déjà effacés.
 *
 * LE SEUL ENDROIT OÙ UNE ROTATION PEUT CASSER LE DIGEST
 * -----------------------------------------------------
 * Le module ne suppose nulle part une profondeur de logs donnée, sauf ici : la
 * fenêtre de collecte part de la dernière exécution réussie, et rien ne borne
 * son ancienneté. Elle s'allonge à chaque occurrence annulée et à chaque envoi
 * échoué — deux cas que le module crée délibérément pour ne pas perdre de
 * messages. Passé un certain retard, la fenêtre finit par remonter plus loin
 * que ce que la rotation conserve.
 *
 * La collecte rend alors moins de messages qu'il n'en est arrivé, sans erreur
 * et sans trou visible. C'est un « RAS » qui ment : il se lit « semaine calme »
 * alors qu'il signifie « messages effacés avant d'être lus ».
 *
 * D'où cette mesure, remontée à l'écran ET dans l'e-mail : le recouvrement ne
 * peut pas être garanti, il peut en revanche être constaté et dit.
 *
 * Le repli de 7 jours (fenêtre initiale) est couvert par construction : le
 * plancher de rétention ne peut pas descendre sous 8 jours.
 *
 * @param array{from:int, to:int} $window Fenêtre de collecte.
 * @return int Nombre de jours perdus, 0 si la rotation couvre toute la fenêtre.
 */
function _180c_contact_digest_window_gap_days( array $window ): int {
	$cutoff = _180c_contact_digest_rotation_cutoff();

	if ( 0 === $cutoff || $cutoff <= $window['from'] ) {
		return 0;
	}

	return (int) ceil( ( $cutoff - $window['from'] ) / DAY_IN_SECONDS );
}

/**
 * Découpe la chaîne d'en-têtes stockée par WP Mail Logging.
 *
 * Le séparateur est la suite de deux caractères `,` puis `\` puis `n` — écrite
 * `',\n'` en quotes simples dans le plugin, donc jamais interprétée comme un
 * saut de ligne. Le vrai "\n" est tout de même traité : d'autres émetteurs
 * passent leurs en-têtes sous forme de chaîne multiligne, que le plugin
 * recopie telle quelle.
 *
 * @param string $raw Contenu brut de la colonne `headers`.
 * @return string[] En-têtes, un par entrée, débarrassés des espaces de bord.
 */
function _180c_contact_digest_split_headers( string $raw ): array {
	$normalized = str_replace( array( ',\n', "\r\n", "\r" ), "\n", $raw );
	$parts      = explode( "\n", $normalized );

	$headers = array();
	foreach ( $parts as $part ) {
		$part = trim( $part, " \t," );
		if ( '' !== $part ) {
			$headers[] = $part;
		}
	}

	return $headers;
}

/**
 * Extrait la valeur d'un en-tête dans une liste déjà découpée.
 *
 * @param string[] $headers Liste d'en-têtes.
 * @param string   $name    Nom recherché, insensible à la casse.
 * @return string Valeur, ou '' si l'en-tête est absent.
 */
function _180c_contact_digest_header_value( array $headers, string $name ): string {
	$needle = strtolower( $name ) . ':';

	foreach ( $headers as $header ) {
		if ( 0 === strpos( strtolower( $header ), $needle ) ) {
			return trim( substr( $header, strlen( $needle ) ) );
		}
	}

	return '';
}

/**
 * Isole l'adresse e-mail d'une valeur d'en-tête `Reply-To`.
 *
 * Accepte les deux formes produites par le formulaire : « Prénom Nom
 * <a@example.com> » et « a@example.com » nu.
 *
 * @param string $reply_to Valeur brute de l'en-tête.
 * @return string Adresse validée, ou '' si rien d'exploitable.
 */
function _180c_contact_digest_parse_sender( string $reply_to ): string {
	if ( preg_match( '/<([^>]+)>/', $reply_to, $matches ) ) {
		$reply_to = $matches[1];
	}

	$email = sanitize_email( trim( $reply_to ) );

	return is_email( $email ) ? $email : '';
}

/**
 * Lit les messages du formulaire de contact journalisés dans la fenêtre.
 *
 * La sélection combine deux filtres que rien ne permet de fusionner : la
 * fenêtre temporelle sur `timestamp` et la présence de l'en-tête d'origine dans
 * `headers`. Le `LIKE` porte sur l'en-tête complet, valeur comprise, pour ne
 * jamais capturer l'accusé de réception (`contact-ack`) — dont le préfixe est
 * identique.
 *
 * @param array{from:int, to:int}|null $window Fenêtre explicite, ou null pour la fenêtre courante.
 * @return array<int, array{date:string, timestamp:int, expediteur:string, objet:string, corps:string}>
 */
function _180c_contact_digest_collect( ?array $window = null ): array {
	global $wpdb;

	if ( ! _180c_contact_digest_table_exists() ) {
		_180c_log(
			'contact-digest: table de logs absente',
			array( 'table' => _180c_contact_digest_table() ),
			'warning'
		);
		return array();
	}

	$window = $window ?? _180c_contact_digest_window();
	$table  = _180c_contact_digest_table();

	// Bornes formatées en heure LOCALE : `timestamp` est écrit par
	// `current_time( 'mysql' )`, jamais en UTC. Cf. le piège 2 en tête de fichier.
	$from = wp_date( 'Y-m-d H:i:s', $window['from'] );
	$to   = wp_date( 'Y-m-d H:i:s', $window['to'] );

	$marker = _180C_MAIL_ORIGIN_HEADER . ': ' . _180C_MAIL_ORIGIN_CONTACT;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Nom de table sûr ($wpdb->prefix + littéral) ; toutes les valeurs passent par prepare(). Table tierce : ni API ni cache d'objet.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT `timestamp`, `subject`, `message`, `headers`
			 FROM `{$table}`
			 WHERE `timestamp` > %s
			   AND `timestamp` <= %s
			   AND `headers` LIKE %s
			 ORDER BY `timestamp` ASC
			 LIMIT %d",
			$from,
			$to,
			'%' . $wpdb->esc_like( $marker ) . '%',
			_180C_CONTACT_DIGEST_LIMIT
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	if ( empty( $rows ) ) {
		return array();
	}

	$messages = array();

	foreach ( $rows as $row ) {
		$headers = _180c_contact_digest_split_headers( (string) ( $row['headers'] ?? '' ) );

		// Garde-fou : le LIKE porte sur une sous-chaîne, la vérification exacte
		// se fait ici, en-tête découpé. Une valeur `contact-form-autre` qui
		// apparaîtrait un jour serait ainsi écartée.
		$origin = _180c_contact_digest_header_value( $headers, _180C_MAIL_ORIGIN_HEADER );
		if ( _180C_MAIL_ORIGIN_CONTACT !== $origin ) {
			continue;
		}

		$body = trim( wp_strip_all_tags( (string) ( $row['message'] ?? '' ) ) );
		if ( mb_strlen( $body ) > _180C_CONTACT_DIGEST_BODY_MAX ) {
			$body = mb_substr( $body, 0, _180C_CONTACT_DIGEST_BODY_MAX ) . '…';
		}

		$local_date = (string) ( $row['timestamp'] ?? '' );

		$messages[] = array(
			'date'       => $local_date,
			// `timestamp` est en heure locale : le convertir avec `strtotime()`,
			// qui suit le fuseau PHP, rendrait un décalage. `get_gmt_from_date()`
			// applique le fuseau du SITE, seul correct ici.
			'timestamp'  => (int) get_gmt_from_date( $local_date, 'U' ),
			'expediteur' => _180c_contact_digest_parse_sender(
				_180c_contact_digest_header_value( $headers, 'Reply-To' )
			),
			'objet'      => (string) ( $row['subject'] ?? '' ),
			'corps'      => $body,
		);
	}

	return $messages;
}
