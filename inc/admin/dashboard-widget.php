<?php
/**
 * Admin — Widget tableau de bord « Désabonnements (30 derniers jours) ».
 *
 * Vue de pilotage rétention sur le Dashboard WordPress, réservée aux gestionnaires
 * boutique (`manage_woocommerce`). Agrège quatre signaux :
 *   - désabonnements réels sur la fenêtre courante [now-30j, now] vs précédente
 *     [now-60j, now-30j], avec variation % ;
 *   - abonnés actuellement en pause (statut WC `on-hold`), compté en direct et de
 *     façon HPOS-aware via wcs_get_subscriptions() (instantané, pas de comparaison) ;
 *   - répartition des motifs déclarés sur la fenêtre courante ;
 *   - les 5 derniers désabonnements (nom, date, motif, commentaire).
 *
 * Source des désabonnements : les abonnements WC en `cancelled` / `pending-cancel`,
 * datés par leur date de résiliation (`_schedule_cancelled`, en GMT). La table
 * {prefix}180c_unsub_feedback n'est PAS la source : elle n'est alimentée que par le
 * stepper de résiliation front, donc aveugle aux résiliations back-office,
 * passerelle ou échéance. Elle sert uniquement d'enrichissement (motif +
 * commentaire), en jointure logique sur `subscription_id`, avec repli « — ».
 *
 * Données calculées une fois par quart d'heure (transient _180C_UNSUB_DASHBOARD_CACHE,
 * défini dans inc/unsub-feedback.php et purgé événementiellement par ce même fichier).
 * Lecture seule ; aucune écriture. Échappement strict ; requêtes via $wpdb->prepare.
 * Dégrade proprement si la table feedback n'existe pas encore.
 *
 * Chargé uniquement en admin (cf. inc/bootstrap.php).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre le widget sur le Dashboard, pour les gestionnaires boutique.
 *
 * @return void
 */
function _180c_unsub_dashboard_register() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'_180c_unsub_dashboard',
		__( '180°C · Désabonnements (30 derniers jours)', '180c' ),
		'_180c_unsub_dashboard_render'
	);
}
add_action( 'wp_dashboard_setup', '_180c_unsub_dashboard_register' );

/**
 * Durée de vie du cache agrégé du widget.
 *
 * Courte (15 min) car complétée par une invalidation événementielle côté
 * inc/unsub-feedback.php : elle ne sert plus que de filet de sécurité.
 *
 * @var int
 */
const _180C_UNSUB_DASHBOARD_TTL = 15 * MINUTE_IN_SECONDS;

/**
 * Squelette des données du widget : toutes les clés attendues, avec leur défaut.
 *
 * Sert de contrat unique — au calcul, à la validation du cache et au rendu.
 *
 * @return array<string,mixed>
 */
function _180c_unsub_dashboard_defaults(): array {
	return array(
		'has_table' => false,
		'has_wcs'   => false,
		'current'   => 0,
		'previous'  => 0,
		'on_hold'   => 0,
		'answers'   => 0,
		'reasons'   => array(),
		'latest'    => array(),
	);
}

/**
 * Retourne les données en cache, uniquement si elles suivent le schéma courant.
 *
 * Un déploiement qui ajoute une clé laisse en base un transient au format
 * précédent, encore valide plusieurs minutes : le rendre tel quel provoquait des
 * « Undefined array key ». Toute entrée à laquelle il manque une clé attendue est
 * donc ignorée et recalculée, sans avoir à versionner la clé du transient.
 *
 * @return array<string,mixed>|null Données valides, ou null si absentes/périmées.
 */
function _180c_unsub_dashboard_cached(): ?array {
	$cached = get_transient( _180C_UNSUB_DASHBOARD_CACHE );

	if ( ! is_array( $cached ) ) {
		return null;
	}

	// Schéma incomplet (cache écrit par une version antérieure) → à recalculer.
	if ( array_diff_key( _180c_unsub_dashboard_defaults(), $cached ) ) {
		return null;
	}

	return $cached;
}

/**
 * Calcule (et met en cache 15 min) les données agrégées du widget.
 *
 * @return array{
 *     has_table:bool,
 *     has_wcs:bool,
 *     current:int,
 *     previous:int,
 *     on_hold:int,
 *     answers:int,
 *     reasons:array<int,array{label:string,count:int,pct:float}>,
 *     latest:array<int,array{name:string,date:string,reason:string,comment:string}>
 * }
 */
function _180c_unsub_dashboard_data(): array {
	$cached = _180c_unsub_dashboard_cached();
	if ( null !== $cached ) {
		return $cached;
	}

	// Bornes GMT : `_schedule_cancelled` est stocké en GMT par WC Subscriptions.
	$now_gmt = time();
	$now     = gmdate( 'Y-m-d H:i:s', $now_gmt );
	$start30 = gmdate( 'Y-m-d H:i:s', $now_gmt - 30 * DAY_IN_SECONDS );
	$start60 = gmdate( 'Y-m-d H:i:s', $now_gmt - 60 * DAY_IN_SECONDS );

	// Construit sur les défauts : toute clé du contrat est garantie présente.
	$data = array_merge(
		_180c_unsub_dashboard_defaults(),
		array(
			'has_table' => function_exists( '_180c_unsub_table_exists' ) && _180c_unsub_table_exists(),
			'has_wcs'   => function_exists( 'wcs_get_subscriptions' ),
			'current'   => _180c_unsub_dashboard_count_cancelled( $start30, $now, true ),
			'previous'  => _180c_unsub_dashboard_count_cancelled( $start60, $start30, false ),
			'on_hold'   => _180c_unsub_dashboard_count_on_hold(),
			'latest'    => _180c_unsub_dashboard_latest( 5 ),
		)
	);

	if ( $data['has_table'] ) {
		global $wpdb;
		$table = _180c_unsub_table();

		// Le sondage horodate en heure du site (created_at), pas en GMT : bornes dédiées.
		$now_site     = current_time( 'mysql' );
		$start30_site = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- created_at est stocké en heure du site ; on compare dans le même référentiel.

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT reason, COUNT(*) AS total FROM {$table} WHERE created_at >= %s AND created_at <= %s GROUP BY reason ORDER BY total DESC",
				$start30_site,
				$now_site
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Dénominateur = réponses au sondage, jamais le nombre de désabonnements :
		// la plupart des résiliations ne passent pas par le stepper.
		foreach ( (array) $rows as $row ) {
			$data['answers'] += (int) $row->total;
		}

		$total = max( 1, $data['answers'] );
		foreach ( (array) $rows as $row ) {
			$count             = (int) $row->total;
			$data['reasons'][] = array(
				'label' => _180c_unsub_reason_label( (string) $row->reason ),
				'count' => $count,
				'pct'   => round( $count / $total * 100, 1 ),
			);
		}
	}

	set_transient( _180C_UNSUB_DASHBOARD_CACHE, $data, _180C_UNSUB_DASHBOARD_TTL );

	return $data;
}

/**
 * Récupère les abonnements résiliés sur une fenêtre, du plus récent au plus ancien.
 *
 * La date de référence est `_schedule_cancelled` : la date à laquelle la
 * résiliation a été *demandée*. C'est la seule qui reste stable quand un
 * `pending-cancel` bascule ensuite en `cancelled` en fin de période payée
 * (`post_modified`, lui, sauterait à cette date de bascule).
 *
 * @param string $start_gmt      Borne basse GMT (incluse), format MySQL.
 * @param string $end_gmt        Borne haute GMT, format MySQL.
 * @param bool   $end_inclusive  True pour inclure la borne haute.
 * @param int    $limit          Nombre max d'abonnements (-1 = illimité).
 * @return array<int,WC_Subscription> Abonnements indexés par ID.
 */
function _180c_unsub_dashboard_cancelled_subscriptions( string $start_gmt, string $end_gmt, bool $end_inclusive = true, int $limit = -1 ): array {
	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		return array();
	}

	$subs = wcs_get_subscriptions(
		array(
			'subscription_status'    => array( 'cancelled', 'pending-cancel' ),
			'subscriptions_per_page' => $limit,
			'orderby'                => 'meta_value',
			'order'                  => 'DESC',
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Volume borné (abonnements résiliés d'une fenêtre de 30 j) et résultat mis en cache.
			'meta_key'               => '_schedule_cancelled',
			'meta_type'              => 'DATETIME',
			'meta_query'             => array(
				array(
					'key'     => '_schedule_cancelled',
					'value'   => $start_gmt,
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
				array(
					'key'     => '_schedule_cancelled',
					'value'   => $end_gmt,
					'compare' => $end_inclusive ? '<=' : '<',
					'type'    => 'DATETIME',
				),
			),
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	return is_array( $subs ) ? $subs : array();
}

/**
 * Compte les désabonnements sur une fenêtre.
 *
 * @param string $start_gmt     Borne basse GMT (incluse), format MySQL.
 * @param string $end_gmt       Borne haute GMT, format MySQL.
 * @param bool   $end_inclusive True pour inclure la borne haute.
 * @return int
 */
function _180c_unsub_dashboard_count_cancelled( string $start_gmt, string $end_gmt, bool $end_inclusive = true ): int {
	return count( _180c_unsub_dashboard_cancelled_subscriptions( $start_gmt, $end_gmt, $end_inclusive ) );
}

/**
 * Construit la liste des N derniers désabonnements, motif et commentaire inclus.
 *
 * Toutes périodes confondues (la liste ne doit jamais être vide tant qu'un
 * désabonnement existe). Le motif et le commentaire proviennent de la table
 * feedback lorsqu'une réponse au sondage existe pour l'abonnement ; sinon « — ».
 *
 * @param int $limit Nombre de lignes à retourner.
 * @return array<int,array{name:string,date:string,reason:string,comment:string}>
 */
function _180c_unsub_dashboard_latest( int $limit = 5 ): array {
	$dash = '—';
	$subs = _180c_unsub_dashboard_cancelled_subscriptions( '1970-01-01 00:00:00', gmdate( 'Y-m-d H:i:s' ), true, $limit );

	if ( empty( $subs ) ) {
		return array();
	}

	$feedback = _180c_unsub_dashboard_feedback_map( array_keys( $subs ) );
	$latest   = array();

	foreach ( $subs as $subscription_id => $subscription ) {
		$row = $feedback[ (int) $subscription_id ] ?? null;

		$reason = '';
		if ( $row && '' !== $row['reason'] ) {
			$reason = _180c_unsub_reason_label( (string) $row['reason'] );
		}

		$comment = ( $row && '' !== $row['comment'] )
			? mb_strimwidth( $row['comment'], 0, 120, '…' )
			: '';

		// La date affichée est celle de la demande de résiliation, en heure du site.
		$cancelled = (string) $subscription->get_date( 'cancelled', 'site' );
		$timestamp = $cancelled ? strtotime( $cancelled ) : false;

		$latest[] = array(
			'name'    => _180c_unsub_dashboard_customer_name( $subscription ),
			'date'    => $timestamp ? date_i18n( get_option( 'date_format' ), $timestamp ) : $dash,
			'reason'  => '' !== $reason ? $reason : $dash,
			'comment' => '' !== $comment ? $comment : $dash,
		);
	}

	return $latest;
}

/**
 * Charge les réponses au sondage correspondant à une liste d'abonnements.
 *
 * Une seule requête pour tout le lot. Si un abonnement porte plusieurs réponses
 * (l'abonné a repassé le stepper), la plus récente gagne.
 *
 * @param array<int,int|string> $subscription_ids IDs d'abonnements.
 * @return array<int,array{reason:string,comment:string}> Indexé par subscription_id.
 */
function _180c_unsub_dashboard_feedback_map( array $subscription_ids ): array {
	if ( empty( $subscription_ids ) || ! function_exists( '_180c_unsub_table_exists' ) || ! _180c_unsub_table_exists() ) {
		return array();
	}

	$ids = array_values( array_filter( array_map( 'absint', $subscription_ids ) ) );
	if ( empty( $ids ) ) {
		return array();
	}

	global $wpdb;
	$table        = _180c_unsub_table();
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			// $placeholders est une liste de %d générée à partir du seul count($ids).
			"SELECT subscription_id, reason, comment FROM {$table} WHERE subscription_id IN ({$placeholders}) ORDER BY id ASC",
			$ids
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$map = array();
	foreach ( (array) $rows as $row ) {
		// ORDER BY id ASC : la dernière réponse écrasée est la plus récente.
		$map[ (int) $row->subscription_id ] = array(
			'reason'  => (string) $row->reason,
			'comment' => (string) $row->comment,
		);
	}

	return $map;
}

/**
 * Résout le nom affichable d'un abonné.
 *
 * Ordre : display_name du compte, puis prénom + nom de facturation de
 * l'abonnement, puis e-mail de facturation.
 *
 * @param WC_Subscription $subscription Abonnement.
 * @return string Nom échappable, ou « — ».
 */
function _180c_unsub_dashboard_customer_name( $subscription ): string {
	$user_id = (int) $subscription->get_user_id();
	if ( $user_id > 0 ) {
		$user = get_userdata( $user_id );
		if ( $user && '' !== trim( (string) $user->display_name ) ) {
			return trim( (string) $user->display_name );
		}
	}

	$billing = trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() );
	if ( '' !== $billing ) {
		return $billing;
	}

	$email = trim( (string) $subscription->get_billing_email() );

	return '' !== $email ? $email : '—';
}

/**
 * Compte les abonnements actuellement en pause (statut on-hold), HPOS-aware.
 *
 * Utilise l'API WC Subscriptions (jamais wp_count_posts, qui casse sous HPOS).
 * wcs_get_subscriptions() retourne des WC_Subscription indexés par ID via
 * WC_Order_Query : on compte le tableau. Instantané (stock), donc non comparé.
 *
 * @return int Nombre d'abonnements en pause (0 si l'API est absente).
 */
function _180c_unsub_dashboard_count_on_hold(): int {
	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		return 0;
	}

	$subs = wcs_get_subscriptions(
		array(
			'subscription_status'    => 'on-hold',
			'subscriptions_per_page' => -1,
		)
	);

	return is_array( $subs ) ? count( $subs ) : 0;
}

/**
 * Formate la variation entre deux comptages en fragment HTML (flèche + couleur).
 *
 * @param int $current  Comptage de la fenêtre courante.
 * @param int $previous Comptage de la fenêtre précédente.
 * @return string Fragment HTML échappé.
 */
function _180c_unsub_dashboard_variation_html( int $current, int $previous ): string {
	if ( $previous <= 0 ) {
		// Pas de base de comparaison : on n'invente pas de pourcentage.
		$label = ( $current > 0 ) ? __( 'nouveau', '180c' ) : '—';
		return '<span class="dashboard-unsub__delta dashboard-unsub__delta--flat">' . esc_html( $label ) . '</span>';
	}

	$pct  = round( ( $current - $previous ) / $previous * 100 );
	$dir  = $pct > 0 ? 'up' : ( $pct < 0 ? 'down' : 'flat' );
	$sign = $pct > 0 ? '+' : '';
	$icon = 'up' === $dir ? '▲' : ( 'down' === $dir ? '▼' : '＝' );

	return sprintf(
		'<span class="dashboard-unsub__delta dashboard-unsub__delta--%1$s">%2$s %3$s</span>',
		esc_attr( $dir ),
		esc_html( $icon ),
		esc_html( $sign . $pct . ' %' )
	);
}

/**
 * Rend le contenu du widget.
 *
 * @return void
 */
function _180c_unsub_dashboard_render() {
	$data = _180c_unsub_dashboard_data();

	// Styles minimaux inline (widget isolé, pas d'asset dédié).
	?>
	<style>
		.dashboard-unsub__stats{display:flex;gap:1.5em;flex-wrap:wrap;margin:0 0 1em}
		.dashboard-unsub__stat{flex:1 1 8em}
		.dashboard-unsub__num{font-size:1.8em;font-weight:600;line-height:1.1}
		.dashboard-unsub__lbl{color:#646970;font-size:.85em}
		.dashboard-unsub__delta{font-size:.85em;font-weight:600;white-space:nowrap}
		.dashboard-unsub__delta--up{color:#b32d2e}
		.dashboard-unsub__delta--down{color:#1a7d2c}
		.dashboard-unsub__delta--flat{color:#646970;font-weight:400}
		.dashboard-unsub__bars{margin:0 0 1em;list-style:none;padding:0}
		.dashboard-unsub__bar-row{margin:0 0 .5em}
		.dashboard-unsub__bar-head{display:flex;justify-content:space-between;font-size:.85em;margin:0 0 .2em}
		.dashboard-unsub__bar-track{background:#f0f0f1;border-radius:3px;height:8px;overflow:hidden}
		.dashboard-unsub__bar-fill{background:#2271b1;height:8px;border-radius:3px}
		.dashboard-unsub__list{margin:0 0 1em;list-style:none;padding:0}
		.dashboard-unsub__item{border-left:3px solid #dcdcde;padding:.2em 0 .2em .7em;margin:0 0 .7em}
		.dashboard-unsub__item-head{display:flex;justify-content:space-between;gap:1em;font-weight:600}
		.dashboard-unsub__item-date{color:#646970;font-weight:400;white-space:nowrap}
		.dashboard-unsub__item-reason{color:#646970;font-size:.85em}
		.dashboard-unsub__item-comment{font-size:.85em;font-style:italic}
		.dashboard-unsub__foot{border-top:1px solid #dcdcde;padding-top:.8em;display:flex;gap:1em;flex-wrap:wrap}
		.dashboard-unsub__empty{color:#646970;margin:0 0 1em}
	</style>

	<div class="dashboard-unsub">

		<div class="dashboard-unsub__stats">
			<?php if ( $data['has_wcs'] ) : ?>
				<div class="dashboard-unsub__stat">
					<div class="dashboard-unsub__num"><?php echo esc_html( number_format_i18n( $data['current'] ) ); ?></div>
					<div class="dashboard-unsub__lbl">
						<?php esc_html_e( 'Désabonnements (30 j)', '180c' ); ?>
						<?php echo _180c_unsub_dashboard_variation_html( $data['current'], $data['previous'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fragment déjà échappé. ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="dashboard-unsub__stat">
				<div class="dashboard-unsub__num"><?php echo esc_html( number_format_i18n( $data['on_hold'] ) ); ?></div>
				<div class="dashboard-unsub__lbl"><?php esc_html_e( 'Abonnés en pause', '180c' ); ?></div>
			</div>
		</div>

		<?php if ( ! $data['has_wcs'] ) : ?>

			<p class="dashboard-unsub__empty">
				<?php esc_html_e( 'WooCommerce Subscriptions est inactif : les désabonnements ne peuvent pas être calculés.', '180c' ); ?>
			</p>

		<?php else : ?>

			<?php if ( ! empty( $data['reasons'] ) ) : ?>
				<h3 class="dashboard-unsub__subtitle">
					<?php
					printf(
						/* translators: %s: nombre de réponses au sondage de résiliation. */
						esc_html( _n( 'Motifs déclarés (30 j · %s réponse)', 'Motifs déclarés (30 j · %s réponses)', $data['answers'], '180c' ) ),
						esc_html( number_format_i18n( $data['answers'] ) )
					);
					?>
				</h3>
				<ul class="dashboard-unsub__bars">
					<?php foreach ( $data['reasons'] as $reason ) : ?>
						<li class="dashboard-unsub__bar-row">
							<div class="dashboard-unsub__bar-head">
								<span><?php echo esc_html( $reason['label'] ); ?></span>
								<span><?php echo esc_html( $reason['count'] . ' · ' . $reason['pct'] . ' %' ); ?></span>
							</div>
							<div class="dashboard-unsub__bar-track">
								<div class="dashboard-unsub__bar-fill" style="width:<?php echo esc_attr( (string) $reason['pct'] ); ?>%"></div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<h3 class="dashboard-unsub__subtitle"><?php esc_html_e( '5 derniers désabonnements', '180c' ); ?></h3>

			<?php if ( empty( $data['latest'] ) ) : ?>

				<p class="dashboard-unsub__empty">
					<?php esc_html_e( 'Aucun désabonnement enregistré.', '180c' ); ?>
				</p>

			<?php else : ?>

				<ul class="dashboard-unsub__list">
					<?php foreach ( $data['latest'] as $item ) : ?>
						<li class="dashboard-unsub__item">
							<div class="dashboard-unsub__item-head">
								<span><?php echo esc_html( $item['name'] ); ?></span>
								<span class="dashboard-unsub__item-date"><?php echo esc_html( $item['date'] ); ?></span>
							</div>
							<div class="dashboard-unsub__item-reason"><?php echo esc_html( $item['reason'] ); ?></div>
							<div class="dashboard-unsub__item-comment"><?php echo esc_html( $item['comment'] ); ?></div>
						</li>
					<?php endforeach; ?>
				</ul>

			<?php endif; ?>

			<?php if ( ! $data['has_table'] ) : ?>
				<p class="dashboard-unsub__empty">
					<?php esc_html_e( 'Les motifs de résiliation ne sont pas encore collectés.', '180c' ); ?>
				</p>
			<?php endif; ?>

		<?php endif; ?>

		<div class="dashboard-unsub__foot">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=180c-unsub-feedback' ) ); ?>">
				<?php esc_html_e( 'Voir tout', '180c' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=_180c_unsub_export' ), '_180c_unsub_export' ) ); ?>">
				<?php esc_html_e( 'Exporter CSV', '180c' ); ?>
			</a>
		</div>

	</div>
	<?php
}
