<?php
/**
 * Automation « push à la publication d'une recette » — réglages et primitives.
 *
 * Ce fichier ne déclenche aucun envoi et n'accroche aucun hook de publication :
 * il porte uniquement l'état de configuration et les fonctions pures dont le
 * panneau d'administration (automation-panel.php) et le moteur (automation.php)
 * ont besoin :
 *   - l'option unique `_180c_notif_automation` et son sanitizer strict ;
 *   - le calcul du prochain créneau hebdomadaire ;
 *   - le rendu des gabarits de texte et leur troncature ;
 *   - le journal en anneau.
 *
 * Écart assumé vs le brief, qui plaçait `_180c_notif_next_slot()` et
 * `_180c_notif_render_template()` en phase 3 : le panneau de la phase 1 en a
 * besoin (bloc « Aperçu » et bloc « État »). Les garder ici évite un
 * `function_exists()` défensif dans le panneau et laisse chaque commit
 * fonctionnel isolément. Le moteur les consomme sans les redéfinir.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clé de l'option de réglages de l'automation.
 */
define( '_180C_NOTIF_AUTOMATION_OPTION', '_180c_notif_automation' );

/**
 * Clé de l'option portant le journal en anneau.
 */
define( '_180C_NOTIF_AUTOMATION_LOG_OPTION', '_180c_notif_automation_log' );

/**
 * Nombre d'entrées conservées dans le journal.
 */
define( '_180C_NOTIF_AUTOMATION_LOG_SIZE', 50 );

/**
 * Longueur recommandée du corps d'une notification.
 *
 * Source : https://documentation.onesignal.com/docs/en/push — « Recommended
 * limit: ~150 characters » pour `contents`. La même page précise qu'aucune
 * plateforme ne tronque à un nombre fixe de caractères : c'est une
 * recommandation d'affichage, pas une limite d'API. La troncature est donc
 * faite ici, proprement, plutôt que subie par l'OS du destinataire.
 */
define( '_180C_NOTIF_BODY_MAX', 150 );

/**
 * Réglages par défaut de l'automation.
 *
 * Le créneau par défaut est le samedi 10:30 et non le vendredi : 73 des 100
 * dernières recettes de production sont publiées le vendredi entre 9 h et
 * 12 h 30, si bien qu'un créneau vendredi 11:30 reporterait la notification
 * d'une semaine entière dans trois cas sur cinq. Valeur modifiable en
 * back-office : c'est un défaut, pas une contrainte.
 *
 * @return array<string,mixed>
 */
function _180c_notif_automation_defaults(): array {
	return array(
		'enabled'           => false,
		'day'               => 6,
		'time'              => '10:30',
		'segment'           => 'testers',
		'heading_template'  => '{titre}',
		'subtitle_template' => '',
		'content_template'  => '{intro}',
		'min_lead_minutes'  => 5,
	);
}

/**
 * Retourne les réglages courants, complétés par les valeurs par défaut.
 *
 * @return array<string,mixed>
 */
function _180c_notif_automation_settings(): array {
	$stored = get_option( _180C_NOTIF_AUTOMATION_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	return array_merge( _180c_notif_automation_defaults(), $stored );
}

/**
 * Retourne une valeur de réglage.
 *
 * @param string $key Clé de réglage.
 * @return mixed Valeur, ou null si la clé est inconnue.
 */
function _180c_notif_automation_setting( string $key ) {
	$settings = _180c_notif_automation_settings();

	return $settings[ $key ] ?? null;
}

/**
 * Indique si l'automation est active.
 *
 * @return bool
 */
function _180c_notif_automation_is_enabled(): bool {
	return (bool) _180c_notif_automation_setting( 'enabled' );
}

/**
 * Sanitize strict des réglages de l'automation.
 *
 * Chaque clé est ramenée dans son domaine de validité ; toute clé inconnue est
 * écartée. Accroché à `sanitize_option__180c_notif_automation` par
 * `register_setting()`, il s'applique donc à TOUTE écriture par
 * `update_option()`, pas seulement à la soumission du panneau.
 *
 * @param mixed $value Valeur brute.
 * @return array<string,mixed>
 */
function _180c_notif_automation_sanitize( $value ): array {
	$defaults = _180c_notif_automation_defaults();

	if ( ! is_array( $value ) ) {
		return $defaults;
	}

	$clean = $defaults;

	$clean['enabled'] = ! empty( $value['enabled'] );

	if ( isset( $value['day'] ) ) {
		$day          = (int) $value['day'];
		$clean['day'] = ( $day >= 1 && $day <= 7 ) ? $day : $defaults['day'];
	}

	if ( isset( $value['time'] ) ) {
		$time = trim( (string) $value['time'] );
		if ( preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m ) ) {
			$clean['time'] = sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
		}
	}

	if ( isset( $value['segment'] ) ) {
		$clean['segment'] = _180c_notif_sanitize_segment( (string) $value['segment'] );
	}

	foreach ( array( 'heading_template', 'subtitle_template', 'content_template' ) as $key ) {
		if ( isset( $value[ $key ] ) ) {
			$clean[ $key ] = sanitize_textarea_field( (string) $value[ $key ] );
		}
	}

	if ( isset( $value['min_lead_minutes'] ) ) {
		$lead                      = (int) $value['min_lead_minutes'];
		$clean['min_lead_minutes'] = max( 0, min( 1440, $lead ) );
	}

	return $clean;
}

/**
 * Enregistre l'option auprès de l'API des réglages.
 *
 * L'intérêt n'est pas l'écran options.php (non utilisé : le panneau a ses
 * propres handlers `admin_post_`) mais l'accrochage du sanitizer sur
 * `sanitize_option_{$option}`, qui rend toute écriture non conforme impossible.
 *
 * @return void
 */
function _180c_notif_automation_register_setting(): void {
	register_setting(
		'_180c_notif_automation',
		_180C_NOTIF_AUTOMATION_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => '_180c_notif_automation_sanitize',
			'default'           => _180c_notif_automation_defaults(),
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', '_180c_notif_automation_register_setting' );

/**
 * Calcule l'horodatage du prochain créneau hebdomadaire.
 *
 * Raisonne dans le fuseau du site (`wp_timezone()`), jamais en UTC ni dans le
 * fuseau du serveur. Le résultat est strictement postérieur à `$from` et
 * respecte la marge `min_lead_minutes` : un créneau trop proche est reporté
 * d'une semaine.
 *
 * Changements d'heure : la date est reconstruite avec `setTime()` après chaque
 * saut de semaine, dans un `DateTimeImmutable` porteur du fuseau. Au passage à
 * l'heure d'été, une heure locale inexistante est normalisée vers l'avant par
 * PHP ; au passage à l'heure d'hiver, l'heure ambiguë résout sur sa première
 * occurrence. Dans les deux cas il reste exactement un créneau par semaine :
 * jamais doublé, jamais supprimé.
 *
 * @param int|null $from_timestamp Horodatage de référence (défaut : maintenant).
 * @return int Horodatage Unix du prochain créneau.
 */
function _180c_notif_next_slot( ?int $from_timestamp = null ): int {
	$settings = _180c_notif_automation_settings();
	$from     = null !== $from_timestamp ? $from_timestamp : time();
	$lead     = (int) $settings['min_lead_minutes'] * MINUTE_IN_SECONDS;
	$day      = (int) $settings['day'];

	$parts  = explode( ':', (string) $settings['time'] );
	$hour   = isset( $parts[0] ) ? (int) $parts[0] : 0;
	$minute = isset( $parts[1] ) ? (int) $parts[1] : 0;

	$tz   = wp_timezone();
	$slot = ( new DateTimeImmutable( '@' . $from ) )->setTimezone( $tz )->setTime( $hour, $minute );

	// Report sur le bon jour ISO (1 = lundi … 7 = dimanche).
	$delta = ( $day - (int) $slot->format( 'N' ) + 7 ) % 7;
	if ( $delta > 0 ) {
		$slot = $slot->modify( '+' . $delta . ' days' )->setTime( $hour, $minute );
	}

	// Deux itérations au plus : créneau déjà passé, puis créneau trop proche.
	$guard = 0;
	while ( ( $slot->getTimestamp() <= $from || ( $slot->getTimestamp() - $from ) < $lead ) && $guard < 3 ) {
		$slot = $slot->modify( '+7 days' )->setTime( $hour, $minute );
		++$guard;
	}

	return $slot->getTimestamp();
}

/**
 * Bascule temporairement sur la locale du SITE, et non celle de l'utilisateur.
 *
 * `wp_date()` et `$wp_locale` suivent `determine_locale()`, qui privilégie la
 * langue du profil de l'utilisateur connecté. Un administrateur dont le profil
 * est en anglais lisait donc « Saturday 12 September » dans le panneau, tandis
 * que le journal — dont les motifs sont écrits depuis WP-CLI ou depuis le cron,
 * sans utilisateur — affichait « samedi 12 septembre » pour le même créneau.
 * Deux formulations pour une seule date, sur le même écran.
 *
 * On force donc la locale du site partout où ce lot rend une date lisible. Les
 * dates machine (`gmdate()`, format MySQL, ISO 8601) n'ont pas de locale et ne
 * sont pas concernées.
 *
 * @return bool Vrai si une bascule a eu lieu et doit être défaite.
 */
function _180c_notif_switch_to_site_locale(): bool {
	if ( ! function_exists( 'switch_to_locale' ) || ! function_exists( 'get_user_locale' ) ) {
		return false;
	}

	$site = get_locale();

	if ( get_user_locale() === $site ) {
		return false;
	}

	return (bool) switch_to_locale( $site );
}

/**
 * Défait la bascule de locale.
 *
 * @param bool $switched Retour de `_180c_notif_switch_to_site_locale()`.
 * @return void
 */
function _180c_notif_restore_locale( bool $switched ): void {
	if ( $switched && function_exists( 'restore_previous_locale' ) ) {
		restore_previous_locale();
	}
}

/**
 * Indique si un créneau déjà programmé correspond encore au réglage courant.
 *
 * Compare le jour ISO et l'heure locale du créneau aux valeurs réglées, et non
 * au résultat de `_180c_notif_next_slot()` : un envoi légitimement reporté à la
 * semaine suivante correspond toujours au réglage, il ne doit pas être signalé.
 *
 * @param int $timestamp Horodatage Unix du créneau programmé.
 * @return bool
 */
function _180c_notif_slot_matches_settings( int $timestamp ): bool {
	$settings = _180c_notif_automation_settings();
	$slot     = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() );

	return (int) $slot->format( 'N' ) === (int) $settings['day']
		&& $slot->format( 'H:i' ) === (string) $settings['time'];
}

/**
 * Formate un créneau en clair, dans la langue et le fuseau du site.
 *
 * Exemple : « samedi 12 septembre à 10:30 ».
 *
 * @param int $timestamp Horodatage Unix.
 * @return string
 */
function _180c_notif_format_slot( int $timestamp ): string {
	$switched = _180c_notif_switch_to_site_locale();

	$formatted = sprintf(
		/* translators: 1: date au format « samedi 12 septembre », 2: heure au format « 10:30 ». */
		__( '%1$s à %2$s', '180c' ),
		wp_date( 'l j F', $timestamp ),
		wp_date( 'H:i', $timestamp )
	);

	_180c_notif_restore_locale( $switched );

	return $formatted;
}

/**
 * Retourne les noms de jour de la semaine, indexés en ISO-8601 (1 = lundi).
 *
 * Dans la langue du SITE, comme le reste des dates du panneau.
 *
 * @return array<int,string>
 */
function _180c_notif_weekday_labels(): array {
	$switched = _180c_notif_switch_to_site_locale();
	$labels   = array();

	for ( $day = 1; $day <= 7; $day++ ) {
		// `get_weekday()` est indexé 0 = dimanche : 7 % 7 ramène bien dimanche sur 0.
		$labels[ $day ] = (string) $GLOBALS['wp_locale']->get_weekday( $day % 7 );
	}

	_180c_notif_restore_locale( $switched );

	return $labels;
}

/**
 * Liste des jetons de gabarit disponibles.
 *
 * Périmètre arrêté après mesure des taux de remplissage réels en production
 * (1 572 recettes) : seuls des champs remplis à au moins 78 % sur les 50
 * dernières recettes sont proposés. Les temps de préparation, de cuisson et de
 * repos (0,1 %), le numéro d'origine (2 % sur les récentes) et les étiquettes
 * (8 %) sont volontairement absents : un jeton adossé à un champ vide est un
 * piège, pas une fonctionnalité.
 *
 * @return array<string,string> Jeton => description affichée dans le panneau.
 */
function _180c_notif_tokens(): array {
	return array(
		'{titre}'     => __( 'Titre de la recette', '180c' ),
		'{intro}'     => __( 'Chapô de la recette (repli : extrait)', '180c' ),
		'{categorie}' => __( 'Type de plat (Plat, Dessert…)', '180c' ),
		'{saison}'    => __( 'Saison (Été, Hiver…)', '180c' ),
		'{portions}'  => __( 'Portions (« 4 personnes »)', '180c' ),
		'{site}'      => __( 'Nom du site', '180c' ),
	);
}

/**
 * Normalise une valeur de jeton pour un payload JSON.
 *
 * Le payload part en JSON, pas en HTML : les balises sont retirées et les
 * entités décodées, faute de quoi un titre contenant « &rsquo; » arriverait tel
 * quel sur l'écran verrouillé du destinataire.
 *
 * @param string $value Valeur brute.
 * @return string
 */
function _180c_notif_token_text( string $value ): string {
	$value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' );

	return trim( preg_replace( '/\s+/u', ' ', $value ) );
}

/**
 * Retourne le premier terme d'une taxonomie pour une recette.
 *
 * @param int    $recipe_id ID de la recette.
 * @param string $taxonomy  Taxonomie.
 * @return string Nom du terme, ou chaîne vide.
 */
function _180c_notif_first_term_name( int $recipe_id, string $taxonomy ): string {
	$terms = get_the_terms( $recipe_id, $taxonomy );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	$first = reset( $terms );

	return isset( $first->name ) ? (string) $first->name : '';
}

/**
 * Résout la valeur d'un jeton pour une recette donnée.
 *
 * @param string $token     Jeton, accolades comprises.
 * @param int    $recipe_id ID de la recette.
 * @return string Valeur, ou chaîne vide si le champ n'est pas renseigné.
 */
function _180c_notif_token_value( string $token, int $recipe_id ): string {
	switch ( $token ) {
		case '{titre}':
			return _180c_notif_token_text( (string) get_the_title( $recipe_id ) );

		case '{intro}':
			// Repli implicite sur l'extrait : le chapô ACF n'est rempli que sur
			// 39,6 % du corpus historique (100 % des 50 dernières recettes).
			$intro = (string) _180c_acf( 'recipe_intro', $recipe_id, '' );
			if ( '' === trim( $intro ) ) {
				// Repli de second rang : ACF peut renvoyer null si le champ n'a
				// jamais été enregistré sur ce post (résolution par la meta
				// `_recipe_intro`), alors que la valeur brute existe.
				$intro = (string) get_post_meta( $recipe_id, 'recipe_intro', true );
			}
			if ( '' === trim( $intro ) ) {
				$post  = get_post( $recipe_id );
				$intro = $post ? (string) $post->post_excerpt : '';
			}
			return _180c_notif_token_text( $intro );

		case '{categorie}':
			return _180c_notif_token_text( _180c_notif_first_term_name( $recipe_id, 'recipe_category' ) );

		case '{saison}':
			return _180c_notif_token_text( _180c_notif_first_term_name( $recipe_id, 'recipe_season' ) );

		case '{portions}':
			$servings = (int) _180c_acf( 'servings', $recipe_id, 0 );
			if ( $servings <= 0 ) {
				return '';
			}
			$unit = _180c_notif_token_text( (string) _180c_acf( 'servings_unit', $recipe_id, '' ) );
			return '' !== $unit ? $servings . ' ' . $unit : (string) $servings;

		case '{site}':
			return _180c_notif_token_text( (string) get_bloginfo( 'name' ) );
	}

	return '';
}

/**
 * Rend un gabarit de texte sur une recette.
 *
 * Un jeton sans valeur est purement et simplement retiré, puis les espaces
 * sont normalisés et la ponctuation orpheline nettoyée : l'utilisateur final
 * ne doit jamais voir ni accolade, ni double espace, ni tiret esseulé. Un jeton
 * inconnu subit le même sort qu'un jeton vide.
 *
 * @param string $template  Gabarit.
 * @param int    $recipe_id ID de la recette.
 * @return string Texte rendu.
 */
function _180c_notif_render_template( string $template, int $recipe_id ): string {
	if ( '' === trim( $template ) || $recipe_id <= 0 ) {
		return '';
	}

	$rendered = preg_replace_callback(
		'/\{[a-z_]+\}/u',
		static function ( $matches ) use ( $recipe_id ) {
			return _180c_notif_token_value( $matches[0], $recipe_id );
		},
		$template
	);

	$rendered = (string) $rendered;

	// Ponctuation restée orpheline après le retrait d'un jeton vide.
	$rendered = preg_replace( '/\s*([–—-])\s*(?=$|[–—-])/u', '', $rendered );
	$rendered = preg_replace( '/\(\s*\)|\[\s*\]/u', '', $rendered );
	$rendered = preg_replace( '/\s+([,.;:!?])/u', '$1', $rendered );
	$rendered = preg_replace( '/\s+/u', ' ', (string) $rendered );

	return trim( (string) $rendered, " \t\n\r\0\x0B–—-·|," );
}

/**
 * Tronque un texte sur limite de mot.
 *
 * @param string $text  Texte.
 * @param int    $limit Longueur maximale, caractères de l'ellipse compris.
 * @return string
 */
function _180c_notif_truncate_words( string $text, int $limit = _180C_NOTIF_BODY_MAX ): string {
	$text = trim( $text );

	if ( $limit <= 0 || mb_strlen( $text ) <= $limit ) {
		return $text;
	}

	$cut   = mb_substr( $text, 0, $limit - 1 );
	$space = mb_strrpos( $cut, ' ' );

	if ( false !== $space && $space > 0 ) {
		$cut = mb_substr( $cut, 0, $space );
	}

	return rtrim( $cut, " \t,;:.–—-" ) . '…';
}

/**
 * Retourne l'ID de la dernière recette publiée.
 *
 * @return int ID, ou 0 si aucune recette publiée.
 */
function _180c_notif_latest_recipe_id(): int {
	$ids = get_posts(
		array(
			'post_type'        => 'recipe',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	return ! empty( $ids ) ? (int) $ids[0] : 0;
}

/**
 * Ajoute une entrée au journal de l'automation.
 *
 * Anneau de 50 entrées dans une option non autoloadée. Aucune table créée :
 * le volume attendu est d'une poignée d'entrées par semaine.
 *
 * @param string              $event Code d'événement (scheduled, replaced, cancelled…).
 * @param array<string,mixed> $data  Contexte : recipe_id, notif_id, reason.
 * @return void
 */
function _180c_notif_automation_log( string $event, array $data = array() ): void {
	$entries = get_option( _180C_NOTIF_AUTOMATION_LOG_OPTION, array() );

	if ( ! is_array( $entries ) ) {
		$entries = array();
	}

	array_unshift(
		$entries,
		array(
			'time'      => gmdate( 'Y-m-d H:i:s' ),
			'event'     => sanitize_key( $event ),
			'recipe_id' => isset( $data['recipe_id'] ) ? (int) $data['recipe_id'] : 0,
			'notif_id'  => isset( $data['notif_id'] ) ? (int) $data['notif_id'] : 0,
			'reason'    => isset( $data['reason'] ) ? sanitize_text_field( (string) $data['reason'] ) : '',
		)
	);

	update_option(
		_180C_NOTIF_AUTOMATION_LOG_OPTION,
		array_slice( $entries, 0, _180C_NOTIF_AUTOMATION_LOG_SIZE ),
		false
	);
}

/**
 * Retourne les entrées du journal, de la plus récente à la plus ancienne.
 *
 * @param int $limit Nombre maximal d'entrées.
 * @return array<int,array<string,mixed>>
 */
function _180c_notif_automation_log_entries( int $limit = _180C_NOTIF_AUTOMATION_LOG_SIZE ): array {
	$entries = get_option( _180C_NOTIF_AUTOMATION_LOG_OPTION, array() );

	if ( ! is_array( $entries ) ) {
		return array();
	}

	return array_slice( $entries, 0, max( 1, $limit ) );
}

/**
 * Libellés humains des événements du journal.
 *
 * @return array<string,string>
 */
function _180c_notif_automation_log_labels(): array {
	return array(
		'scheduled'   => __( 'Programmée', '180c' ),
		'replaced'    => __( 'Remplacée', '180c' ),
		'rescheduled' => __( 'Reprogrammée', '180c' ),
		'cancelled'   => __( 'Annulée', '180c' ),
		'skipped'     => __( 'Recette non retenue', '180c' ),
		'sent'        => __( 'Envoi effectif', '180c' ),
		'api_error'   => __( 'Erreur API', '180c' ),
		'disabled'    => __( 'Automation désactivée', '180c' ),
		'test_sent'   => __( 'Test envoyé', '180c' ),
		'repaired'    => __( 'Auto-réparation', '180c' ),
		'settings'    => __( 'Réglages enregistrés', '180c' ),
	);
}
