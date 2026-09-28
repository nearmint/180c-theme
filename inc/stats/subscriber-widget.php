<?php
/**
 * Stats — Widget « 180°C — Abonnés » sur le tableau de bord WordPress.
 *
 * Réservé aux gestionnaires boutique (`manage_woocommerce`). Lit le payload des
 * KPIs mis en cache par inc/stats/subscriber-stats.php (recalcul nocturne) et,
 * à défaut de cache, déclenche un calcul de secours une fois. Affiche six
 * indicateurs en grille compacte : valeur formatée (locale FR) + variation
 * ↑/↓ colorée selon le sens favorable de chaque KPI.
 *
 * Aucune écriture en base hormis le fallback de calcul (qui passe par le
 * moteur). Échappement strict de toutes les sorties.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enregistre le widget sur le Dashboard, pour les gestionnaires boutique.
 *
 * @return void
 */
function _180c_subscriber_stats_widget_register() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'_180c_subscriber_stats',
		__( '180°C — Abonnés', '180c' ),
		'_180c_render_subscriber_stats_widget'
	);
}
add_action( 'wp_dashboard_setup', '_180c_subscriber_stats_widget_register' );

/**
 * Charge le style du widget, uniquement sur l'écran « Tableau de bord ».
 *
 * @param string $hook Identifiant de l'écran admin courant.
 * @return void
 */
function _180c_subscriber_stats_widget_styles( $hook ) {
	if ( 'index.php' !== $hook ) {
		return;
	}
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	wp_enqueue_style(
		'_180c-admin-stats',
		_180C_THEME_URI . '/assets/css/admin-stats.css',
		array(),
		_180C_VERSION
	);
}
add_action( 'admin_enqueue_scripts', '_180c_subscriber_stats_widget_styles' );

/**
 * Construit le fragment HTML de variation (flèche + couleur sémantique).
 *
 * La couleur reflète le sens *favorable* du KPI : une hausse de résiliations
 * ou de pauses est rouge, une hausse d'abonnés est verte. Renvoie « — » quand
 * la donnée de comparaison est indisponible (montée en charge).
 *
 * @param int|float|null $current        Valeur courante.
 * @param int|float|null $previous       Valeur de la période/snapshot précédent.
 * @param bool           $positive_is_up True si « monter » est favorable.
 * @return string Fragment HTML échappé.
 */
function _180c_stats_variation_html( $current, $previous, bool $positive_is_up ): string {
	if ( null === $previous ) {
		return '<span class="_180c-stats__delta _180c-stats__delta--na" title="' . esc_attr__( 'Donnée de comparaison pas encore disponible', '180c' ) . '">—</span>';
	}

	$current  = (float) $current;
	$previous = (float) $previous;
	$diff     = $current - $previous;

	if ( abs( $diff ) < 0.005 ) {
		return '<span class="_180c-stats__delta _180c-stats__delta--flat">＝</span>';
	}

	$is_up = $diff > 0;

	// Variation en %, sauf si base nulle (on n'invente pas de pourcentage).
	if ( $previous > 0 ) {
		$pct   = round( $diff / $previous * 100 );
		$label = ( $is_up ? '+' : '' ) . number_format_i18n( $pct ) . ' %';
	} else {
		$label = __( 'nouveau', '180c' );
	}

	// Sens favorable → vert (good), défavorable → rouge (bad).
	$is_good = ( $is_up === $positive_is_up );
	$tone    = $is_good ? 'good' : 'bad';
	$icon    = $is_up ? '▲' : '▼';

	return sprintf(
		'<span class="_180c-stats__delta _180c-stats__delta--%1$s">%2$s %3$s</span>',
		esc_attr( $tone ),
		esc_html( $icon ),
		esc_html( $label )
	);
}

/**
 * Formate un montant mensuel en euros (locale FR, suffixe « /mois »).
 *
 * @param float $amount Montant mensuel.
 * @return string Montant formaté et échappé.
 */
function _180c_stats_format_mrr( float $amount ): string {
	if ( function_exists( 'wc_price' ) ) {
		$price = wp_strip_all_tags( wc_price( $amount, array( 'decimals' => 0 ) ) );
	} else {
		$price = number_format_i18n( $amount ) . ' €';
	}

	/* translators: %s: formatted monthly revenue amount. */
	return sprintf( __( '%s /mois', '180c' ), $price );
}

/**
 * Rend une cellule KPI : libellé, valeur, variation.
 *
 * @param string $label     Libellé du KPI.
 * @param string $value     Valeur déjà formatée et échappée.
 * @param string $variation Fragment HTML de variation (déjà échappé).
 * @param string $note      Note secondaire optionnelle (déjà échappée).
 * @return void
 */
function _180c_stats_render_cell( string $label, string $value, string $variation, string $note = '' ) {
	?>
	<div class="_180c-stats__cell">
		<div class="_180c-stats__label"><?php echo esc_html( $label ); ?></div>
		<div class="_180c-stats__value"><?php echo esc_html( $value ); ?></div>
		<div class="_180c-stats__meta">
			<?php echo $variation; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fragment construit et échappé par _180c_stats_variation_html(). ?>
			<?php if ( '' !== $note ) : ?>
				<span class="_180c-stats__note"><?php echo $note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Note pré-échappée par l'appelant. ?></span>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Rend le contenu du widget.
 *
 * @return void
 */
function _180c_render_subscriber_stats_widget() {
	$stats = _180c_get_subscriber_stats();

	if ( empty( $stats['wcs_available'] ) ) {
		echo '<p class="_180c-stats__empty">' . esc_html__( 'WooCommerce Subscriptions indisponible : les statistiques abonnés ne peuvent pas être calculées.', '180c' ) . '</p>';
		return;
	}

	$k = $stats['kpis'];

	// Note « depuis le … » pour l'usage site tant que la collecte est jeune.
	$usage_note = '';
	$since      = isset( $stats['last_seen_since'] ) ? (string) $stats['last_seen_since'] : '';
	if ( '' !== $since ) {
		$since_ts = strtotime( $since );
		if ( $since_ts && ( time() - $since_ts ) < 30 * DAY_IN_SECONDS ) {
			/* translators: %s: tracker deployment date. */
			$since_fmt  = esc_html__( 'depuis le %s', '180c' );
			$usage_note = sprintf(
				$since_fmt,
				esc_html( date_i18n( get_option( 'date_format' ), $since_ts ) )
			);
		}
	}

	$usage_total    = (int) $k['usage']['total'];
	$usage_value    = (int) $k['usage']['value'];
	$usage_pct      = ( $usage_total > 0 ) ? round( $usage_value / $usage_total * 100 ) : 0;
	$usage_prev_pct = null;
	if ( null !== $k['usage']['prev'] && ! empty( $k['usage']['prev_total'] ) ) {
		$usage_prev_pct = round( (int) $k['usage']['prev'] / (int) $k['usage']['prev_total'] * 100 );
	}

	// Sous-libellé du split mensuel/annuel pour les abonnés actifs.
	/* translators: 1: monthly subscribers count, 2: yearly subscribers count. */
	$active_fmt  = __( '%1$s mensuels · %2$s annuels', '180c' );
	$active_note = esc_html(
		sprintf(
			$active_fmt,
			number_format_i18n( (int) $k['active']['monthly'] ),
			number_format_i18n( (int) $k['active']['yearly'] )
		)
	);
	?>
	<div class="_180c-stats">
		<div class="_180c-stats__grid">

			<?php
			_180c_stats_render_cell(
				__( 'Abonnés actifs', '180c' ),
				number_format_i18n( (int) $k['active']['value'] ),
				_180c_stats_variation_html( $k['active']['value'], $k['active']['prev'], (bool) $k['active']['positive_is_up'] ),
				$active_note
			);

			_180c_stats_render_cell(
				__( 'MRR', '180c' ),
				_180c_stats_format_mrr( (float) $k['mrr']['value'] ),
				_180c_stats_variation_html( $k['mrr']['value'], $k['mrr']['prev'], (bool) $k['mrr']['positive_is_up'] )
			);

			_180c_stats_render_cell(
				__( 'Nouveaux abonnés (30 j)', '180c' ),
				number_format_i18n( (int) $k['new']['value'] ),
				_180c_stats_variation_html( $k['new']['value'], $k['new']['prev'], (bool) $k['new']['positive_is_up'] )
			);

			_180c_stats_render_cell(
				__( 'Résiliations (30 j)', '180c' ),
				number_format_i18n( (int) $k['cancellations']['value'] ),
				_180c_stats_variation_html( $k['cancellations']['value'], $k['cancellations']['prev'], (bool) $k['cancellations']['positive_is_up'] )
			);

			_180c_stats_render_cell(
				__( 'Abonnements en pause', '180c' ),
				number_format_i18n( (int) $k['on_hold']['value'] ),
				_180c_stats_variation_html( $k['on_hold']['value'], $k['on_hold']['prev'], (bool) $k['on_hold']['positive_is_up'] )
			);

			/* translators: 1: percentage seen, 2: count seen, 3: total active subscribers. */
			$usage_fmt     = __( '%1$s%% (%2$s/%3$s)', '180c' );
			$usage_display = sprintf(
				$usage_fmt,
				number_format_i18n( $usage_pct ),
				number_format_i18n( $usage_value ),
				number_format_i18n( $usage_total )
			);
			_180c_stats_render_cell(
				__( 'Usage site (30 j)', '180c' ),
				$usage_display,
				_180c_stats_variation_html( $usage_pct, $usage_prev_pct, (bool) $k['usage']['positive_is_up'] ),
				$usage_note
			);
			?>

		</div>

		<?php if ( ! empty( $stats['generated_at'] ) ) : ?>
			<p class="_180c-stats__foot">
				<?php
				$gen_ts = strtotime( (string) $stats['generated_at'] );
				printf(
					/* translators: %s: last computation date/time. */
					esc_html__( 'Mis à jour le %s', '180c' ),
					esc_html( $gen_ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $gen_ts ) : (string) $stats['generated_at'] )
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}
