<?php
/**
 * Écran d'admin « Relance impayés » — observabilité du tag Mailchimp « CB expirée ».
 *
 * POURQUOI CET ÉCRAN
 * ------------------
 * `inc/mailchimp/unpaid-tag.php` ne journalisait que par `error_log()`. Les
 * journaux PHP de la production ne sont pas lisibles depuis l'administration :
 * ce fichier n'y est pas consultable. Le module était donc muet là où il
 * compte, et rien ne permettait de distinguer « le cron a tourné sans rien
 * faire » de « le cron n'a pas tourné ». C'est cette distinction que l'écran
 * rend visible.
 *
 * LECTURE SEULE, À UNE EXCEPTION PRÈS
 * -----------------------------------
 * La seule action offerte est une exécution **en simulation**, forcée quel que
 * soit `_180C_UNPAID_TAG_DRY_RUN`. Il n'y a volontairement aucun bouton
 * d'exécution réelle : poser le tag pour de bon reste une décision qui se prend
 * en modifiant la constante dans `wp-config.php`, pas en cliquant.
 *
 * COÛT
 * ----
 * L'affichage des candidats parcourt tous les abonnements « on-hold » :
 * mesuré à quelques secondes sur la volumétrie réelle. Volontairement sans cache — un écran de
 * vérification qui montrerait un état d'il y a une heure ne vérifierait rien.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/** Slug de la page. */
const _180C_UNPAID_TAG_PAGE = '180c-unpaid-tag';

/** Action `admin_post` de l'exécution manuelle en simulation. */
const _180C_UNPAID_TAG_RUN_ACTION = '_180c_unpaid_tag_run';

/**
 * Enregistre la sous-page sous le menu WooCommerce.
 *
 * @return void
 */
function _180c_unpaid_tag_admin_menu(): void {
	add_submenu_page(
		'woocommerce',
		__( 'Relance impayés', '180c' ),
		__( 'Relance impayés', '180c' ),
		'manage_woocommerce',
		_180C_UNPAID_TAG_PAGE,
		'_180c_unpaid_tag_admin_render'
	);
}
add_action( 'admin_menu', '_180c_unpaid_tag_admin_menu' );

/**
 * URL de la page, éventuellement porteuse d'un retour d'exécution.
 *
 * @param string $notice Code de notice à transmettre, ou '' pour aucun.
 * @return string
 */
function _180c_unpaid_tag_admin_url( string $notice = '' ): string {
	$url = admin_url( 'admin.php?page=' . _180C_UNPAID_TAG_PAGE );

	return '' === $notice ? $url : add_query_arg( '_180c_notice', $notice, $url );
}

/**
 * Libellé lisible d'un déclencheur.
 *
 * @param string $trigger Valeur stockée.
 * @return string
 */
function _180c_unpaid_tag_admin_trigger_label( string $trigger ): string {
	$labels = array(
		'cron'   => __( 'Cron quotidien', '180c' ),
		'hook'   => __( 'Changement de statut', '180c' ),
		'manuel' => __( 'Manuel', '180c' ),
	);

	return $labels[ $trigger ] ?? $trigger;
}

/**
 * Libellé lisible d'un mode d'exécution.
 *
 * @param string $mode Valeur stockée.
 * @return string
 */
function _180c_unpaid_tag_admin_mode_label( string $mode ): string {
	return 'simulation' === $mode ? __( 'Simulation', '180c' ) : __( 'Réel', '180c' );
}

/**
 * Exécution manuelle, toujours en simulation.
 *
 * @return void
 */
function _180c_unpaid_tag_admin_run(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ) );
	}
	check_admin_referer( _180C_UNPAID_TAG_RUN_ACTION );

	// Le second argument force la simulation : la constante ne peut pas la lever.
	_180c_unpaid_tag_sync( 'manuel', true );

	wp_safe_redirect( _180c_unpaid_tag_admin_url( 'run' ) );
	exit;
}
add_action( 'admin_post_' . _180C_UNPAID_TAG_RUN_ACTION, '_180c_unpaid_tag_admin_run' );

/**
 * Rend le bloc « État ».
 *
 * @return void
 */
function _180c_unpaid_tag_admin_render_state(): void {
	$has_since   = defined( '_180C_UNPAID_TAG_SINCE' ) && '' !== (string) _180C_UNPAID_TAG_SINCE;
	$has_dry_run = defined( '_180C_UNPAID_TAG_DRY_RUN' );
	$dry_run     = _180c_unpaid_tag_is_dry_run();
	$last        = _180c_unpaid_tag_last_run();
	$next        = wp_next_scheduled( _180C_UNPAID_TAG_HOOK );

	echo '<h2>' . esc_html__( 'État', '180c' ) . '</h2>';

	if ( ! $has_since ) {
		echo '<div class="notice notice-error inline"><p>';
		echo esc_html__( 'La constante _180C_UNPAID_TAG_SINCE est absente de wp-config.php : le module ne fait rien du tout.', '180c' );
		echo '</p></div>';
	}

	echo '<table class="widefat striped" style="max-width:900px"><tbody>';

	printf(
		'<tr><th scope="row" style="width:260px">%s</th><td>%s</td></tr>',
		esc_html__( 'Constante _180C_UNPAID_TAG_SINCE', '180c' ),
		$has_since
			? esc_html( sprintf( /* translators: %s: date plancher. */ __( 'présente — %s', '180c' ), (string) _180C_UNPAID_TAG_SINCE ) )
			: '<strong>' . esc_html__( 'absente', '180c' ) . '</strong>'
	);

	printf(
		'<tr><th scope="row">%s</th><td>%s</td></tr>',
		esc_html__( 'Constante _180C_UNPAID_TAG_DRY_RUN', '180c' ),
		$has_dry_run
			? esc_html( _180C_UNPAID_TAG_DRY_RUN ? 'true' : 'false' )
			: esc_html__( 'absente (simulation par défaut)', '180c' )
	);

	printf(
		'<tr><th scope="row">%s</th><td>%s</td></tr>',
		esc_html__( 'Mode effectif', '180c' ),
		$dry_run
			? esc_html__( 'Simulation — aucun tag n’est envoyé à Mailchimp', '180c' )
			: '<strong>' . esc_html__( 'Réel — les tags sont envoyés à Mailchimp', '180c' ) . '</strong>'
	);

	printf(
		'<tr><th scope="row">%s</th><td>%s</td></tr>',
		esc_html__( 'Délai avant relance', '180c' ),
		esc_html( sprintf( /* translators: %d: nombre de jours. */ _n( '%d jour après l’échec', '%d jours après l’échec', _180C_UNPAID_TAG_DELAY_DAYS, '180c' ), _180C_UNPAID_TAG_DELAY_DAYS ) )
	);

	// Dernière exécution + âge.
	if ( null === $last ) {
		printf(
			'<tr><th scope="row">%s</th><td><strong>%s</strong></td></tr>',
			esc_html__( 'Dernière exécution', '180c' ),
			esc_html__( 'aucune exécution enregistrée', '180c' )
		);
	} else {
		$age_seconds = max( 0, time() - (int) $last['timestamp'] );
		$stale       = $age_seconds > ( _180C_UNPAID_TAG_STALE_HOURS * HOUR_IN_SECONDS );

		printf(
			'<tr><th scope="row">%s</th><td>%s<br><span class="description">%s</span></td></tr>',
			esc_html__( 'Dernière exécution', '180c' ),
			esc_html( (string) $last['date'] ),
			esc_html(
				sprintf(
					/* translators: 1: durée écoulée, 2: déclencheur, 3: mode. */
					__( 'il y a %1$s — %2$s, %3$s', '180c' ),
					human_time_diff( (int) $last['timestamp'] ),
					_180c_unpaid_tag_admin_trigger_label( (string) $last['trigger'] ),
					_180c_unpaid_tag_admin_mode_label( (string) $last['mode'] )
				)
			)
		);

		if ( $stale ) {
			printf(
				'<tr><th scope="row">%s</th><td><div class="notice notice-warning inline" style="margin:0"><p>%s</p></div></td></tr>',
				esc_html__( 'Alerte', '180c' ),
				esc_html(
					sprintf(
						/* translators: 1: seuil en heures, 2: durée écoulée. */
						__( 'Aucune exécution depuis plus de %1$d h (dernière il y a %2$s). Le cron ne tourne probablement plus : vérifier WP-Cron et DISABLE_WP_CRON.', '180c' ),
						_180C_UNPAID_TAG_STALE_HOURS,
						human_time_diff( (int) $last['timestamp'] )
					)
				)
			);
		}
	}

	printf(
		'<tr><th scope="row">%s</th><td>%s</td></tr>',
		esc_html__( 'Prochaine exécution planifiée', '180c' ),
		$next
			? esc_html( wp_date( 'Y-m-d H:i', (int) $next ) )
			: '<strong>' . esc_html__( 'aucune — le cron n’est pas planifié', '180c' ) . '</strong>'
	);

	echo '</tbody></table>';
}

/**
 * Rend le formulaire d'exécution manuelle en simulation.
 *
 * @return void
 */
function _180c_unpaid_tag_admin_render_run_form(): void {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:1.5em 0">';
	echo '<input type="hidden" name="action" value="' . esc_attr( _180C_UNPAID_TAG_RUN_ACTION ) . '">';
	wp_nonce_field( _180C_UNPAID_TAG_RUN_ACTION );
	echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Exécuter maintenant en simulation', '180c' ) . '</button>';
	echo ' <span class="description">' . esc_html__( 'Parcourt les abonnements suspendus et journalise le résultat sans rien envoyer à Mailchimp, même si le mode réel est actif.', '180c' ) . '</span>';
	echo '</form>';
}

/**
 * Rend le tableau des dernières exécutions.
 *
 * @param int $limit Nombre de lignes affichées.
 * @return void
 */
function _180c_unpaid_tag_admin_render_runs( int $limit = 20 ): void {
	$runs = array_slice( _180c_unpaid_tag_runs(), 0, $limit );

	echo '<h2>' . esc_html__( 'Dernières exécutions', '180c' ) . '</h2>';

	if ( empty( $runs ) ) {
		echo '<p>' . esc_html__( 'Aucune exécution enregistrée pour l’instant.', '180c' ) . '</p>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr>';
	foreach ( array(
		__( 'Date', '180c' ),
		__( 'Déclencheur', '180c' ),
		__( 'Mode', '180c' ),
		__( 'Évalués', '180c' ),
		__( 'Candidats', '180c' ),
		__( 'Tagués', '180c' ),
		__( 'Retirés', '180c' ),
		__( 'Erreurs', '180c' ),
		__( 'Comptes concernés', '180c' ),
	) as $heading ) {
		echo '<th scope="col">' . esc_html( $heading ) . '</th>';
	}
	echo '</tr></thead><tbody>';

	foreach ( $runs as $run ) {
		$ids       = isset( $run['ids'] ) && is_array( $run['ids'] ) ? $run['ids'] : array();
		$ids_total = isset( $run['ids_total'] ) ? (int) $run['ids_total'] : count( $ids );

		$ids_cell = empty( $ids ) ? '—' : implode( ', ', array_map( 'intval', $ids ) );
		if ( $ids_total > count( $ids ) ) {
			$ids_cell .= ' ' . sprintf(
				/* translators: %d: nombre d'identifiants non affichés. */
				esc_html__( '(+%d non listés)', '180c' ),
				$ids_total - count( $ids )
			);
		}

		echo '<tr>';
		echo '<td>' . esc_html( (string) ( $run['date'] ?? '' ) ) . '</td>';
		echo '<td>' . esc_html( _180c_unpaid_tag_admin_trigger_label( (string) ( $run['trigger'] ?? '' ) ) ) . '</td>';
		echo '<td>' . esc_html( _180c_unpaid_tag_admin_mode_label( (string) ( $run['mode'] ?? '' ) ) ) . '</td>';
		echo '<td>' . esc_html( (string) (int) ( $run['evalues'] ?? 0 ) ) . '</td>';
		echo '<td>' . esc_html( (string) (int) ( $run['candidats'] ?? 0 ) ) . '</td>';
		echo '<td>' . esc_html( (string) (int) ( $run['tagues'] ?? 0 ) ) . '</td>';
		echo '<td>' . esc_html( (string) (int) ( $run['retires'] ?? 0 ) ) . '</td>';
		echo '<td>' . esc_html( (string) (int) ( $run['erreurs'] ?? 0 ) ) . '</td>';
		echo '<td>' . esc_html( $ids_cell ) . '</td>';
		echo '</tr>';

		// Note et détail des erreurs sur une ligne dépliée, pour rester lisible.
		$note    = (string) ( $run['note'] ?? '' );
		$details = isset( $run['erreurs_detail'] ) && is_array( $run['erreurs_detail'] ) ? $run['erreurs_detail'] : array();

		if ( '' === $note && empty( $details ) ) {
			continue;
		}

		echo '<tr><td colspan="9" class="description">';
		if ( '' !== $note ) {
			echo esc_html( $note );
		}
		foreach ( $details as $detail ) {
			printf(
				'<br>%s',
				esc_html(
					sprintf(
						/* translators: 1: identifiant de compte, 2: code d'erreur, 3: message. */
						__( 'compte %1$d — %2$s : %3$s', '180c' ),
						(int) ( $detail['user_id'] ?? 0 ),
						(string) ( $detail['code'] ?? '' ),
						(string) ( $detail['message'] ?? '' )
					)
				)
			);
		}
		echo '</td></tr>';
	}

	echo '</tbody></table>';
}

/**
 * Rend « #123 », lié si l'URL d'édition est disponible.
 *
 * `get_edit_post_link()` et `get_edit_user_link()` renvoient une chaîne vide
 * quand l'objet n'existe plus ou que l'utilisateur n'a pas le droit de
 * l'éditer. Un `<a href="">` renverrait alors sur la page courante : mieux vaut
 * afficher l'identifiant sans lien.
 *
 * @param string $url URL d'édition, éventuellement vide.
 * @param int    $id  Identifiant affiché.
 * @return string HTML.
 */
function _180c_unpaid_tag_admin_link( string $url, int $id ): string {
	$label = '#' . $id;

	return '' === $url ? esc_html( $label ) : '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}

/**
 * Rend le tableau des candidats à venir.
 *
 * @return void
 */
function _180c_unpaid_tag_admin_render_candidates(): void {
	echo '<h2>' . esc_html__( 'Candidats à venir', '180c' ) . '</h2>';

	if ( null === _180c_unpaid_tag_since() ) {
		echo '<p>' . esc_html__( 'Liste indisponible : la constante _180C_UNPAID_TAG_SINCE est absente.', '180c' ) . '</p>';
		return;
	}

	$candidates = _180c_unpaid_tag_candidates();

	echo '<p class="description">' . esc_html__( 'Abonnements suspendus dont l’échec de paiement est postérieur à la date plancher. Ils seront tagués à la date de bascule, si le compte n’a pas régularisé d’ici là.', '180c' ) . '</p>';

	if ( empty( $candidates ) ) {
		echo '<p>' . esc_html__( 'Aucun candidat.', '180c' ) . '</p>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr>';
	foreach ( array(
		__( 'Abonnement', '180c' ),
		__( 'Compte', '180c' ),
		__( 'Échec', '180c' ),
		__( 'Âge', '180c' ),
		__( 'Bascule', '180c' ),
		__( 'Déjà tagué', '180c' ),
	) as $heading ) {
		echo '<th scope="col">' . esc_html( $heading ) . '</th>';
	}
	echo '</tr></thead><tbody>';

	foreach ( $candidates as $row ) {
		echo '<tr>';
		echo '<td>' . wp_kses_post( _180c_unpaid_tag_admin_link( (string) get_edit_post_link( (int) $row['subscription_id'] ), (int) $row['subscription_id'] ) ) . '</td>';
		echo '<td>' . wp_kses_post( _180c_unpaid_tag_admin_link( (string) get_edit_user_link( (int) $row['user_id'] ), (int) $row['user_id'] ) ) . '</td>';
		echo '<td>' . esc_html( (string) $row['echec'] ) . '</td>';
		echo '<td>' . esc_html( sprintf( /* translators: %d: nombre de jours. */ _n( '%d jour', '%d jours', (int) $row['age_jours'], '180c' ), (int) $row['age_jours'] ) ) . '</td>';
		echo '<td>' . esc_html( (string) $row['bascule'] ) . '</td>';
		echo '<td>' . esc_html( $row['tague'] ? __( 'oui', '180c' ) : __( 'non', '180c' ) ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
}

/**
 * Rend la page.
 *
 * @return void
 */
function _180c_unpaid_tag_admin_render(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'un code d'affichage après redirection ; aucune mutation.
	$notice = isset( $_GET['_180c_notice'] ) ? sanitize_key( wp_unslash( $_GET['_180c_notice'] ) ) : '';

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Relance impayés', '180c' ) . '</h1>';
	echo '<p class="description">' . esc_html__( 'Suivi du tag Mailchimp « CB expirée », posé sur les comptes dont l’abonnement est suspendu pour échec de paiement depuis au moins 8 jours. Les pauses volontaires ne sont pas concernées.', '180c' ) . '</p>';

	if ( 'run' === $notice ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Exécution en simulation terminée. Le résultat figure en tête du tableau ci-dessous.', '180c' ) . '</p></div>';
	}

	_180c_unpaid_tag_admin_render_state();
	_180c_unpaid_tag_admin_render_run_form();
	_180c_unpaid_tag_admin_render_runs();
	_180c_unpaid_tag_admin_render_candidates();

	echo '</div>';
}
