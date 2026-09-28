<?php
/**
 * Admin — Feedback de désabonnement : liste + export CSV.
 *
 * Sous-page « Désabonnements » sous le menu WooCommerce. Affiche un résumé
 * agrégé par motif (la valeur produit de la collecte), une liste paginée
 * (WP_List_Table) et un export CSV. Lecture seule sur la table
 * {prefix}180c_unsub_feedback (Phase 3) ; libellés via _180c_unsub_reasons().
 *
 * Chargé uniquement en admin (cf. inc/bootstrap.php). Accès réservé à
 * `manage_woocommerce` ; export protégé par nonce.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

// La classe de base WP_List_Table n'est pas chargée par défaut (comme dbDelta).
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Liste paginée des feedbacks de désabonnement.
 */
class _180C_Unsub_Feedback_List_Table extends WP_List_Table {

	/**
	 * Constructeur.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'desabonnement',
				'plural'   => 'desabonnements',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Colonnes de la liste.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'created_at'      => __( 'Date', '180c' ),
			'user'            => __( 'Utilisateur', '180c' ),
			'subscription_id' => __( 'Abonnement', '180c' ),
			'reason'          => __( 'Motif', '180c' ),
			'comment'         => __( 'Commentaire', '180c' ),
		);
	}

	/**
	 * Colonnes triables.
	 *
	 * @return array<string, array>
	 */
	public function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Motif de filtre courant (validé contre la liste blanche).
	 *
	 * @return string Slug valide ou chaîne vide.
	 */
	private function current_reason_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture GET d'affichage (filtre liste).
		$reason = isset( $_GET['reason'] ) ? sanitize_text_field( wp_unslash( $_GET['reason'] ) ) : '';
		return ( $reason && array_key_exists( $reason, _180c_unsub_reasons() ) ) ? $reason : '';
	}

	/**
	 * Prépare les items (requête + pagination + tri + filtre).
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;
		$table = _180c_unsub_table();

		$per_page = 20;
		$current  = $this->get_pagenum();
		$offset   = ( $current - 1 ) * $per_page;

		$reason = $this->current_reason_filter();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture GET d'affichage (tri).
		$order = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $reason ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE reason = %s", $reason ) );
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE reason = %s ORDER BY created_at {$order} LIMIT %d OFFSET %d",
					$reason,
					$per_page,
					$offset
				)
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY created_at {$order} LIMIT %d OFFSET %d",
					$per_page,
					$offset
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->items           = $items;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'created_at' );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Rendu par défaut d'une colonne.
	 *
	 * @param object $item        Ligne.
	 * @param string $column_name Nom de colonne.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return esc_html( (string) ( $item->$column_name ?? '' ) );
	}

	/**
	 * Colonne Date.
	 *
	 * @param object $item Ligne.
	 * @return string
	 */
	public function column_created_at( $item ) {
		return esc_html( (string) $item->created_at );
	}

	/**
	 * Colonne Utilisateur (lien vers user-edit + email).
	 *
	 * @param object $item Ligne.
	 * @return string
	 */
	public function column_user( $item ) {
		$user = get_userdata( (int) $item->user_id );
		if ( ! $user ) {
			return esc_html( '#' . (int) $item->user_id );
		}

		return sprintf(
			'<a href="%s">%s</a><br><span class="description">%s</span>',
			esc_url( get_edit_user_link( $user->ID ) ),
			esc_html( $user->display_name ),
			esc_html( $user->user_email )
		);
	}

	/**
	 * Colonne Abonnement (lien vers l'édition, HPOS-aware).
	 *
	 * @param object $item Ligne.
	 * @return string
	 */
	public function column_subscription_id( $item ) {
		$id  = (int) $item->subscription_id;
		$sub = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $id ) : null;

		if ( $sub instanceof \WC_Subscription ) {
			return sprintf( '<a href="%s">#%d</a>', esc_url( $sub->get_edit_order_url() ), $id );
		}

		return esc_html( '#' . $id );
	}

	/**
	 * Colonne Motif (libellé).
	 *
	 * @param object $item Ligne.
	 * @return string
	 */
	public function column_reason( $item ) {
		return esc_html( _180c_unsub_reason_label( (string) $item->reason ) );
	}

	/**
	 * Colonne Commentaire (tronqué, complet en title).
	 *
	 * @param object $item Ligne.
	 * @return string
	 */
	public function column_comment( $item ) {
		$comment = (string) ( $item->comment ?? '' );
		if ( '' === $comment ) {
			return '—';
		}

		$short = mb_strimwidth( $comment, 0, 80, '…' );
		return sprintf( '<span title="%s">%s</span>', esc_attr( $comment ), esc_html( $short ) );
	}

	/**
	 * Message liste vide.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'Aucun feedback de désabonnement pour le moment.', '180c' );
	}
}

/**
 * Enregistre la sous-page admin sous le menu WooCommerce.
 *
 * @return void
 */
function _180c_unsub_admin_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'Désabonnements', '180c' ),
		__( 'Désabonnements', '180c' ),
		'manage_woocommerce',
		'180c-unsub-feedback',
		'_180c_unsub_admin_render_page'
	);
}
add_action( 'admin_menu', '_180c_unsub_admin_menu' );

/**
 * Rend le résumé agrégé (comptage par motif).
 *
 * @return void
 */
function _180c_unsub_admin_render_summary() {
	global $wpdb;
	$table = _180c_unsub_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( "SELECT reason, COUNT(*) AS total FROM {$table} GROUP BY reason ORDER BY total DESC" );
	if ( empty( $rows ) ) {
		return;
	}

	echo '<h2>' . esc_html__( 'Répartition par motif', '180c' ) . '</h2>';
	echo '<table class="widefat striped" style="max-width:600px">';
	echo '<thead><tr><th scope="col">' . esc_html__( 'Motif', '180c' ) . '</th><th scope="col">' . esc_html__( 'Total', '180c' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		echo '<tr><td>' . esc_html( _180c_unsub_reason_label( (string) $row->reason ) ) . '</td><td>' . esc_html( (string) $row->total ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * Rend la page admin (résumé + filtre + export + liste).
 *
 * @return void
 */
function _180c_unsub_admin_render_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ) );
	}

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Désabonnements', '180c' ) . '</h1>';

	if ( ! _180c_unsub_table_exists() ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'La table de feedback n\'existe pas encore.', '180c' ) . '</p></div></div>';
		return;
	}

	_180c_unsub_admin_render_summary();

	$list = new _180C_Unsub_Feedback_List_Table();
	$list->prepare_items();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture GET d'affichage (filtre).
	$reason = isset( $_GET['reason'] ) ? sanitize_text_field( wp_unslash( $_GET['reason'] ) ) : '';
	$reason = ( $reason && array_key_exists( $reason, _180c_unsub_reasons() ) ) ? $reason : '';

	$export_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=_180c_unsub_export' . ( $reason ? '&reason=' . rawurlencode( $reason ) : '' ) ),
		'_180c_unsub_export'
	);

	// Filtre par motif (GET) + bouton d'export.
	echo '<form method="get" style="margin:1em 0;display:flex;gap:.5em;align-items:center;flex-wrap:wrap">';
	echo '<input type="hidden" name="page" value="180c-unsub-feedback">';
	echo '<label class="screen-reader-text" for="filter-reason">' . esc_html__( 'Filtrer par motif', '180c' ) . '</label>';
	echo '<select name="reason" id="filter-reason">';
	echo '<option value="">' . esc_html__( 'Tous les motifs', '180c' ) . '</option>';
	foreach ( _180c_unsub_reasons() as $slug => $label ) {
		echo '<option value="' . esc_attr( $slug ) . '"' . selected( $reason, $slug, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
	echo '<button type="submit" class="button">' . esc_html__( 'Filtrer', '180c' ) . '</button>';
	echo '<a class="button button-secondary" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Exporter en CSV', '180c' ) . '</a>';
	echo '</form>';

	// Liste (le hidden page conserve le contexte sur la pagination/tri).
	echo '<form method="get">';
	echo '<input type="hidden" name="page" value="180c-unsub-feedback">';
	if ( $reason ) {
		echo '<input type="hidden" name="reason" value="' . esc_attr( $reason ) . '">';
	}
	$list->display();
	echo '</form>';

	echo '</div>';
}

/**
 * Exporte le feedback en CSV (admin_post, nonce + capability).
 *
 * @return void
 */
function _180c_unsub_export_csv() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ) );
	}

	check_admin_referer( '_180c_unsub_export' );

	if ( ! _180c_unsub_table_exists() ) {
		wp_die( esc_html__( 'La table de feedback n\'existe pas encore.', '180c' ) );
	}

	global $wpdb;
	$table   = _180c_unsub_table();
	$reasons = _180c_unsub_reasons();

	$reason = isset( $_GET['reason'] ) ? sanitize_text_field( wp_unslash( $_GET['reason'] ) ) : '';
	$reason = ( $reason && array_key_exists( $reason, $reasons ) ) ? $reason : '';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $reason ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE reason = %s ORDER BY created_at DESC", $reason ) );
	} else {
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$filename = 'desabonnements-180c-' . wp_date( 'Y-m-d' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Flux CSV vers la sortie.
	$out = fopen( 'php://output', 'w' );

	// BOM UTF-8 pour Excel.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped
	fwrite( $out, "\xEF\xBB\xBF" );

	fputcsv( $out, array( 'id', 'created_at', 'user_id', 'user_email', 'subscription_id', 'reason_slug', 'reason_label', 'comment' ) );

	foreach ( (array) $rows as $row ) {
		$user = get_userdata( (int) $row->user_id );
		fputcsv(
			$out,
			array(
				$row->id,
				$row->created_at,
				$row->user_id,
				$user ? $user->user_email : '',
				$row->subscription_id,
				$row->reason,
				_180c_unsub_reason_label( (string) $row->reason ),
				(string) ( $row->comment ?? '' ),
			)
		);
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Flux CSV vers la sortie.
	fclose( $out );
	exit;
}
add_action( 'admin_post__180c_unsub_export', '_180c_unsub_export_csv' );
