<?php
/**
 * Liste des notifications — surfaçage de l'état de l'automation.
 *
 * Ajoute une colonne « Origine », un filtre déroulant sur cette origine, un
 * statut d'envoi enrichi (programmée / annulée) et une action de ligne
 * « Annuler l'envoi ».
 *
 * Reprise de la colonne « Statut d'envoi » : le callback historique
 * `_180c_notif_column_content()` écrit directement dans la sortie, deux
 * callbacks accrochés au même hook produiraient donc deux cellules
 * superposées. On le désaccroche et on le rappelle nous-mêmes pour les
 * colonnes qu'il gère encore (« Segment ») — l'alternative, dupliquer sa
 * logique, aurait fait diverger les deux rendus au premier correctif.
 *
 * Aucune dépendance JavaScript nouvelle : la confirmation d'annulation est un
 * `confirm()` natif. Le CSS des pastilles vit dans assets/css/notif-automation.css,
 * déjà chargé sur cet écran.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clé de la variable de requête du filtre d'origine.
 */
define( '_180C_NOTIF_ORIGIN_QUERY_VAR', '180c_notif_origin' );

/**
 * Ajoute la colonne « Origine » avant le statut d'envoi.
 *
 * @param array<string,string> $columns Colonnes existantes.
 * @return array<string,string>
 */
function _180c_notif_automation_columns( $columns ) {
	$new = array();

	foreach ( $columns as $key => $label ) {
		if ( '180c_notif_status' === $key ) {
			$new['180c_notif_origin'] = __( 'Origine', '180c' );
		}
		$new[ $key ] = $label;
	}

	// Filet : si la colonne de statut a disparu, l'origine s'ajoute en fin.
	if ( ! isset( $new['180c_notif_origin'] ) ) {
		$new['180c_notif_origin'] = __( 'Origine', '180c' );
	}

	return $new;
}
add_filter( 'manage_' . _180C_NOTIF_POST_TYPE . '_posts_columns', '_180c_notif_automation_columns', 20 );

/**
 * Reprend la main sur le rendu des colonnes personnalisées.
 *
 * @return void
 */
function _180c_notif_automation_take_over_columns(): void {
	remove_action( 'manage_' . _180C_NOTIF_POST_TYPE . '_posts_custom_column', '_180c_notif_column_content', 10 );
	add_action( 'manage_' . _180C_NOTIF_POST_TYPE . '_posts_custom_column', '_180c_notif_automation_column_content', 10, 2 );
}
add_action( 'admin_init', '_180c_notif_automation_take_over_columns' );

/**
 * Formate une date MySQL GMT en date lisible, fuseau et langue du site.
 *
 * @param string $mysql_gmt Date MySQL en GMT.
 * @param bool   $with_time Inclure l'heure.
 * @return string Chaîne vide si la date est absente ou illisible.
 */
function _180c_notif_format_gmt( string $mysql_gmt, bool $with_time = true ): string {
	if ( '' === $mysql_gmt ) {
		return '';
	}

	$ts = strtotime( $mysql_gmt . ' UTC' );

	if ( ! $ts ) {
		return '';
	}

	// Langue du SITE et non de l'utilisateur : voir _180c_notif_switch_to_site_locale().
	$switched = _180c_notif_switch_to_site_locale();

	$formatted = $with_time
		? sprintf(
			/* translators: 1: date, 2: heure. */
			__( '%1$s à %2$s', '180c' ),
			wp_date( 'j F Y', $ts ),
			wp_date( 'H:i', $ts )
		)
		: wp_date( 'j F Y', $ts );

	_180c_notif_restore_locale( $switched );

	return $formatted;
}

/**
 * Rend le contenu des colonnes personnalisées.
 *
 * @param string $column  Clé de colonne.
 * @param int    $post_id ID du post.
 * @return void
 */
function _180c_notif_automation_column_content( $column, $post_id ): void {
	$post_id = (int) $post_id;

	if ( '180c_notif_origin' === $column ) {
		$origin = _180c_notif_origin( $post_id );
		printf(
			'<span class="notif-automation__badge notif-automation__badge--origin-%s">%s</span>',
			esc_attr( $origin ),
			esc_html( 'auto' === $origin ? __( 'Auto', '180c' ) : __( 'Manuel', '180c' ) )
		);

		return;
	}

	if ( '180c_notif_status' === $column ) {
		_180c_notif_automation_render_status( $post_id );

		return;
	}

	// Colonnes restées à la charge du module manuel (« Segment »). Appel direct :
	// `inc/notifications/admin.php` est requis juste avant ce fichier, dans le
	// même bloc `is_admin()` de `inc/bootstrap.php`, et cette colonne n'est
	// rendue que sur une list table d'administration. Un `function_exists()`
	// serait une garde qui ne peut pas se déclencher.
	_180c_notif_column_content( $column, $post_id );
}

/**
 * Rend la cellule « Statut d'envoi ».
 *
 * Ordre de test volontaire : un envoi délivré prime sur tout le reste, puis
 * l'annulation, puis la programmation. Le comportement des notifications
 * envoyées est identique à l'existant.
 *
 * @param int $post_id ID de la notification.
 * @return void
 */
function _180c_notif_automation_render_status( int $post_id ): void {
	$onesignal_id = (string) get_post_meta( $post_id, '_180c_onesignal_id', true );

	if ( '' !== $onesignal_id ) {
		$sent_at = (string) get_post_meta( $post_id, '_180c_notif_sent_at', true );
		echo '<span style="color:#46b450;">&#10003; ' . esc_html__( 'Envoyée', '180c' ) . '</span>';
		if ( '' !== $sent_at ) {
			echo '<br><small>' . esc_html( $sent_at ) . '</small>';
		}

		return;
	}

	$cancelled_at = (string) get_post_meta( $post_id, _180C_NOTIF_META_CANCELLED_AT, true );

	if ( '' !== $cancelled_at ) {
		_180c_notif_render_status_badge( 'cancelled', __( 'Annulée', '180c' ), $cancelled_at );

		return;
	}

	$scheduled_for = (string) get_post_meta( $post_id, _180C_NOTIF_META_SCHEDULED_FOR, true );
	$scheduled_id  = (string) get_post_meta( $post_id, _180C_NOTIF_META_SCHEDULED_ID, true );

	if ( '' !== $scheduled_id && '' !== $scheduled_for ) {
		_180c_notif_render_status_badge( 'scheduled', __( 'Programmée', '180c' ), $scheduled_for );

		return;
	}

	if ( '' !== (string) get_post_meta( $post_id, '_180c_notif_last_error', true ) ) {
		echo '<span style="color:#b32d2e;">' . esc_html__( 'Échec', '180c' ) . '</span>';

		return;
	}

	echo '<span>' . esc_html__( 'Brouillon', '180c' ) . '</span>';
}

/**
 * Rend une pastille de statut : libellé court, date sur la ligne suivante.
 *
 * La date est SÉPARÉE de la pastille, et non incluse dedans. Mesuré : un libellé
 * « Programmée pour le 12 septembre 2026 à 10:30 » produit une pastille de
 * 268 px dans une cellule de 178 px, et `white-space: nowrap` la faisait alors
 * déborder de 100 px sur la colonne « Segment » voisine. La colonne « Envoyée »
 * historique procède déjà ainsi : pastille courte, date en `<small>` dessous.
 * On s'aligne dessus plutôt que de contredire la règle `nowrap`, qui reste juste
 * pour toutes les autres pastilles, courtes par nature.
 *
 * @param string $modifier  Suffixe de classe BEM (`scheduled`, `cancelled`).
 * @param string $label     Libellé court de la pastille.
 * @param string $mysql_gmt Date MySQL GMT à afficher dessous.
 * @return void
 */
function _180c_notif_render_status_badge( string $modifier, string $label, string $mysql_gmt ): void {
	printf(
		'<span class="notif-automation__badge notif-automation__badge--%s">%s</span>',
		esc_attr( $modifier ),
		esc_html( $label )
	);

	$date = _180c_notif_format_gmt( $mysql_gmt );

	if ( '' !== $date ) {
		printf( '<br><small>%s</small>', esc_html( $date ) );
	}
}

/**
 * Ajoute le filtre d'origine au-dessus de la liste.
 *
 * `restrict_manage_posts` se déclenche à l'intérieur du formulaire de filtre
 * GET : c'est précisément l'endroit prévu pour un `<select>` de filtre, à côté
 * des filtres natifs. Le contraste avec le panneau de réglages, qui ne peut pas
 * s'y trouver, tient à ce que celui-ci a besoin de ses propres formulaires POST.
 *
 * @param string $post_type Type de contenu de la liste.
 * @param string $which     Position (`top` ou `bottom`).
 * @return void
 */
function _180c_notif_automation_origin_filter( $post_type, $which ): void {
	if ( _180C_NOTIF_POST_TYPE !== $post_type || 'top' !== $which ) {
		return;
	}

	// Lecture d'un paramètre de filtre en GET ; aucune action mutante.
	$current = isset( $_GET[ _180C_NOTIF_ORIGIN_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		? sanitize_key( wp_unslash( $_GET[ _180C_NOTIF_ORIGIN_QUERY_VAR ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		: '';

	$options = array(
		''       => __( 'Toutes les origines', '180c' ),
		'auto'   => __( 'Auto', '180c' ),
		'manual' => __( 'Manuel', '180c' ),
	);

	printf( '<select name="%s">', esc_attr( _180C_NOTIF_ORIGIN_QUERY_VAR ) );
	foreach ( $options as $value => $label ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $value ),
			selected( $current, $value, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
}
add_action( 'restrict_manage_posts', '_180c_notif_automation_origin_filter', 10, 2 );

/**
 * Applique le filtre d'origine à la requête de la liste.
 *
 * Les notifications manuelles historiques n'ont pas la meta : « Manuel » se lit
 * donc « meta absente OU différente de auto ».
 *
 * @param WP_Query $query Requête.
 * @return void
 */
function _180c_notif_automation_filter_query( $query ): void {
	if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
		return;
	}

	if ( _180C_NOTIF_POST_TYPE !== $query->get( 'post_type' ) ) {
		return;
	}

	// Lecture d'un paramètre de filtre en GET ; aucune action mutante.
	$origin = isset( $_GET[ _180C_NOTIF_ORIGIN_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		? sanitize_key( wp_unslash( $_GET[ _180C_NOTIF_ORIGIN_QUERY_VAR ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		: '';

	if ( 'auto' !== $origin && 'manual' !== $origin ) {
		return;
	}

	$meta_query = (array) $query->get( 'meta_query' );

	if ( 'auto' === $origin ) {
		$meta_query[] = array(
			'key'   => _180C_NOTIF_META_ORIGIN,
			'value' => 'auto',
		);
	} else {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => _180C_NOTIF_META_ORIGIN,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => _180C_NOTIF_META_ORIGIN,
				'value'   => 'auto',
				'compare' => '!=',
			),
		);
	}

	$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtre d'administration explicite.
}
add_action( 'pre_get_posts', '_180c_notif_automation_filter_query' );

/**
 * URL d'annulation d'un envoi programmé, protégée par nonce.
 *
 * @param int $notif_id ID de la notification.
 * @return string
 */
function _180c_notif_automation_cancel_url( int $notif_id ): string {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'   => '180c_notif_automation_cancel',
				'notif_id' => $notif_id,
			),
			admin_url( 'admin-post.php' )
		),
		'180c_notif_automation_cancel',
		'_180c_notif_automation_cancel_nonce'
	);
}

/**
 * Ajoute l'action de ligne « Annuler l'envoi » sur les notifications programmées.
 *
 * @param array<string,string> $actions Actions existantes.
 * @param WP_Post              $post    Post de la ligne.
 * @return array<string,string>
 */
function _180c_notif_automation_row_actions( $actions, $post ) {
	if ( ! $post instanceof WP_Post || _180C_NOTIF_POST_TYPE !== $post->post_type ) {
		return $actions;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return $actions;
	}

	$scheduled_id = (string) get_post_meta( $post->ID, _180C_NOTIF_META_SCHEDULED_ID, true );
	$sent         = (string) get_post_meta( $post->ID, '_180c_onesignal_id', true );
	$cancelled    = (string) get_post_meta( $post->ID, _180C_NOTIF_META_CANCELLED_AT, true );

	if ( '' === $scheduled_id || '' !== $sent || '' !== $cancelled ) {
		return $actions;
	}

	$actions['180c_cancel'] = sprintf(
		'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
		esc_url( _180c_notif_automation_cancel_url( (int) $post->ID ) ),
		esc_js( __( 'Annuler l\'envoi programmé de cette notification ?', '180c' ) ),
		esc_html__( 'Annuler l\'envoi', '180c' )
	);

	return $actions;
}
add_filter( 'post_row_actions', '_180c_notif_automation_row_actions', 10, 2 );
