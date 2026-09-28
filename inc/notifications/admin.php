<?php
/**
 * Interface d'administration du CPT « 180c_notification ».
 *
 * Deux metaboxes : « Contenu » (corps, image, cible, segment) et « Envoi »
 * (état + bouton « Envoyer maintenant »). L'envoi passe par un handler dédié
 * admin_post_180c_send_notification, protégé par nonce + capability. Le bouton
 * d'envoi utilise l'attribut HTML5 `formaction` pour soumettre le formulaire
 * d'édition (donc la case de confirmation App Store) vers admin-post.php, sans
 * imbriquer de <form> (interdit en HTML).
 *
 * Aucune écriture en base hors post meta du CPT.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue l'asset d'admin (champs conditionnels + recherche de cible + aperçu
 * live) uniquement sur les écrans d'édition du CPT notification.
 *
 * Asset versionné servi tel quel — aucun passage par Vite, aucune dépendance npm.
 *
 * @param string $hook Hook de la page admin courante.
 * @return void
 */
function _180c_notif_admin_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || _180C_NOTIF_POST_TYPE !== $screen->post_type ) {
		return;
	}

	$version = defined( '_180C_VERSION' ) ? _180C_VERSION : null;

	wp_enqueue_style(
		'180c-notif-admin',
		get_template_directory_uri() . '/assets/css/notif-admin.css',
		array(),
		$version
	);

	wp_enqueue_script(
		'180c-notif-admin',
		get_template_directory_uri() . '/assets/js/notif-admin.js',
		array(),
		$version,
		true
	);

	wp_localize_script(
		'180c-notif-admin',
		'_180cNotif',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( '180c_notif_target' ),
			'appName'      => get_bloginfo( 'name' ),
			'appIcon'      => _180c_notif_app_icon_url(),
			'placeholder'  => __( 'Tapez un titre…', '180c' ),
			'bodyMax'      => 178,
			'previewTitle' => __( 'Titre de la notification', '180c' ),
			'previewBody'  => __( 'Le corps du message s’affichera ici.', '180c' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', '_180c_notif_admin_assets' );

/**
 * URL de l'icône d'app affichée dans l'aperçu (favicon du site, ou vide).
 *
 * @return string
 */
function _180c_notif_app_icon_url() {
	$icon_id = (int) get_option( 'site_icon' );
	if ( $icon_id ) {
		$src = wp_get_attachment_image_url( $icon_id, array( 96, 96 ) );
		if ( $src ) {
			return (string) $src;
		}
	}

	return '';
}

/**
 * Enregistre les metaboxes du CPT notification.
 *
 * @return void
 */
function _180c_notif_add_meta_boxes() {
	add_meta_box(
		'180c_notif_content',
		__( 'Contenu', '180c' ),
		'_180c_notif_render_content_box',
		_180C_NOTIF_POST_TYPE,
		'normal',
		'high'
	);

	// Enregistré juste après « Contenu », même contexte/priorité : rendu
	// directement sous le panneau Contenu dans la colonne principale.
	add_meta_box(
		'180c_notif_preview',
		__( 'Aperçu', '180c' ),
		'_180c_notif_render_preview_box',
		_180C_NOTIF_POST_TYPE,
		'normal',
		'high'
	);

	add_meta_box(
		'180c_notif_send',
		__( 'Envoi', '180c' ),
		'_180c_notif_render_send_box',
		_180C_NOTIF_POST_TYPE,
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_' . _180C_NOTIF_POST_TYPE, '_180c_notif_add_meta_boxes' );

/**
 * Rend la metabox « Contenu ».
 *
 * @param WP_Post $post Notification en cours d'édition.
 * @return void
 */
function _180c_notif_render_content_box( $post ) {
	wp_nonce_field( '180c_notif_save', '_180c_notif_nonce' );

	$body        = (string) get_post_meta( $post->ID, '_180c_notif_body', true );
	$target_type = (string) get_post_meta( $post->ID, '_180c_notif_target_type', true );
	$target_id   = (int) get_post_meta( $post->ID, '_180c_notif_target_id', true );

	// Seuls `recipe` et `product` sont proposés à la saisie. Une notification
	// héritée (article/url/none) s'affiche par défaut sur « Recette » sans que
	// sa meta soit réécrite tant qu'elle n'est pas resauvegardée.
	$ui_type = in_array( $target_type, array( 'recipe', 'product' ), true ) ? $target_type : 'recipe';

	$target_title = $target_id ? (string) get_the_title( $target_id ) : '';
	?>
	<p>
		<strong><?php esc_html_e( 'Type de contenu', '180c' ); ?></strong><br>
		<label style="margin-right:1.5em;">
			<input type="radio" name="_180c_notif_target_type" value="recipe" <?php checked( $ui_type, 'recipe' ); ?> />
			<?php esc_html_e( 'Recette', '180c' ); ?>
		</label>
		<label>
			<input type="radio" name="_180c_notif_target_type" value="product" <?php checked( $ui_type, 'product' ); ?> />
			<?php esc_html_e( 'Produit', '180c' ); ?>
		</label>
	</p>

	<p>
		<label for="_180c_notif_target_search"><strong><?php esc_html_e( 'Sélection', '180c' ); ?></strong></label><br>
		<input type="text" id="_180c_notif_target_search" list="_180c_notif_target_list"
			class="regular-text" autocomplete="off"
			value="<?php echo esc_attr( $target_title ? $target_title . ' (#' . $target_id . ')' : '' ); ?>"
			placeholder="<?php esc_attr_e( 'Tapez un titre…', '180c' ); ?>" />
		<datalist id="_180c_notif_target_list"></datalist>
		<input type="hidden" id="_180c_notif_target_id" name="_180c_notif_target_id" value="<?php echo esc_attr( (string) $target_id ); ?>" />
	</p>

	<p>
		<label for="_180c_notif_body"><strong><?php esc_html_e( 'Corps du message', '180c' ); ?></strong></label><br>
		<textarea id="_180c_notif_body" name="_180c_notif_body" rows="3" class="large-text" maxlength="240"><?php echo esc_textarea( $body ); ?></textarea>
		<span class="description">
			<span id="_180c_notif_body_count"><?php echo esc_html( (string) mb_strlen( $body ) ); ?></span>
			<?php esc_html_e( ' caractères (recommandé : 178 max).', '180c' ); ?>
		</span>
	</p>
	<?php
}

/**
 * Rend la metabox « Aperçu » : reproduction fidèle d'une notification iOS
 * (icône + nom d'app, titre, corps, vignette de l'image dérivée), mise à jour
 * en direct par l'asset d'admin. Le rendu initial reflète l'état enregistré.
 *
 * @param WP_Post $post Notification en cours d'édition.
 * @return void
 */
function _180c_notif_render_preview_box( $post ) {
	$title       = (string) get_the_title( $post );
	$body        = (string) get_post_meta( $post->ID, '_180c_notif_body', true );
	$image_url   = _180c_notif_resolve_image_url( (int) $post->ID );
	$app_name    = (string) get_bloginfo( 'name' );
	$app_icon    = _180c_notif_app_icon_url();
	$placeholder = '' === trim( $title ) ? __( 'Titre de la notification', '180c' ) : $title;
	$body_holder = '' === trim( $body ) ? __( 'Le corps du message s’affichera ici.', '180c' ) : $body;
	?>
	<p class="description"><?php esc_html_e( 'Aperçu indicatif du rendu sur écran verrouillé iOS.', '180c' ); ?></p>
	<div class="notif-ios-preview" id="_180c_notif_preview" aria-hidden="true">
		<div class="notif-ios-preview__card">
			<div class="notif-ios-preview__head">
				<span class="notif-ios-preview__appicon">
					<?php if ( '' !== $app_icon ) : ?>
						<img id="_180c_notif_preview_icon" src="<?php echo esc_url( $app_icon ); ?>" alt="" />
					<?php else : ?>
						<span id="_180c_notif_preview_icon" class="notif-ios-preview__appicon-ph"></span>
					<?php endif; ?>
				</span>
				<span class="notif-ios-preview__appname" id="_180c_notif_preview_app_name"><?php echo esc_html( mb_strtoupper( $app_name ) ); ?></span>
				<span class="notif-ios-preview__time"><?php esc_html_e( 'maintenant', '180c' ); ?></span>
			</div>
			<div class="notif-ios-preview__row">
				<div class="notif-ios-preview__text">
					<div class="notif-ios-preview__title" id="_180c_notif_preview_title"><?php echo esc_html( $placeholder ); ?></div>
					<div class="notif-ios-preview__body" id="_180c_notif_preview_body"><?php echo esc_html( $body_holder ); ?></div>
				</div>
				<div class="notif-ios-preview__thumb" id="_180c_notif_preview_thumb" style="<?php echo '' === $image_url ? 'display:none;' : ''; ?>">
					<img id="_180c_notif_preview_image" src="<?php echo esc_url( $image_url ); ?>" alt="" />
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Rend la metabox « Envoi ».
 *
 * @param WP_Post $post Notification en cours d'édition.
 * @return void
 */
function _180c_notif_render_send_box( $post ) {
	$onesignal_id = (string) get_post_meta( $post->ID, '_180c_onesignal_id', true );
	$sent_at      = (string) get_post_meta( $post->ID, '_180c_notif_sent_at', true );
	$last_error   = (string) get_post_meta( $post->ID, '_180c_notif_last_error', true );
	$segment      = (string) get_post_meta( $post->ID, '_180c_notif_segment', true );

	if ( '' !== $onesignal_id ) {
		echo '<p><span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> <strong>' . esc_html__( 'Envoyée', '180c' ) . '</strong></p>';
		if ( '' !== $sent_at ) {
			printf(
				'<p>%s <code>%s</code></p>',
				esc_html__( 'Le', '180c' ),
				esc_html( $sent_at )
			);
		}
		printf(
			'<p>%s<br><code style="word-break:break-all;">%s</code></p>',
			esc_html__( 'ID OneSignal :', '180c' ),
			esc_html( $onesignal_id )
		);
		return;
	}

	echo '<p><strong>' . esc_html__( 'Brouillon — non envoyée', '180c' ) . '</strong></p>';

	if ( '' !== $last_error ) {
		printf(
			'<p style="color:#b32d2e;">%s<br><em>%s</em></p>',
			esc_html__( 'Dernière erreur :', '180c' ),
			esc_html( $last_error )
		);
	}

	$selected_segment = '' !== $segment ? _180c_onesignal_normalize_segment_key( $segment ) : _180c_onesignal_default_segment();
	?>
	<p><strong><?php esc_html_e( 'Segment', '180c' ); ?></strong></p>
	<?php foreach ( _180c_onesignal_segments() as $key => $seg ) : ?>
		<label style="display:block;margin:.15em 0;">
			<input type="radio" name="_180c_notif_segment" value="<?php echo esc_attr( $key ); ?>" <?php checked( $selected_segment, $key ); ?> />
			<?php echo esc_html( is_array( $seg ) ? $seg['name'] : (string) $seg ); ?>
		</label>
	<?php endforeach; ?>
	<?php

	if ( ! _180c_onesignal_is_configured() && ! _180c_onesignal_is_dry_run() ) {
		echo '<p class="description">' . esc_html__( 'Envoi indisponible : OneSignal non configuré (voir wp-config.php).', '180c' ) . '</p>';
		return;
	}

	if ( 'auto-draft' === $post->post_status ) {
		echo '<p class="description">' . esc_html__( 'Enregistrez la notification avant de pouvoir l\'envoyer.', '180c' ) . '</p>';
		return;
	}

	wp_nonce_field( '180c_send_notification', '_180c_send_nonce' );

	if ( _180c_onesignal_is_dry_run() ) {
		echo '<p class="description">' . esc_html__( 'Mode simulation (dry-run) actif : aucun envoi réel.', '180c' ) . '</p>';
	}

	$effective_segment = '' !== $segment ? $segment : _180c_onesignal_default_segment();
	$needs_confirm     = _180c_onesignal_segment_requires_confirmation( $effective_segment );
	?>
	<?php if ( $needs_confirm ) : ?>
	<p>
		<label>
			<input type="checkbox" name="_180c_notif_segment_confirm" value="1" />
			<?php
			printf(
				/* translators: %s: nom du segment cible. */
				esc_html__( 'Je confirme l\'envoi à un large public : %s.', '180c' ),
				esc_html( _180c_onesignal_segment_name( $effective_segment ) )
			);
			?>
		</label>
	</p>
	<?php endif; ?>
	<p>
		<button type="submit"
			name="action"
			value="180c_send_notification"
			class="button button-primary button-large"
			formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			formmethod="post"
			onclick="return confirm( '<?php echo esc_js( __( 'Envoyer cette notification maintenant ?', '180c' ) ); ?>' );">
			<?php esc_html_e( 'Envoyer maintenant', '180c' ); ?>
		</button>
	</p>
	<p class="description">
		<?php
		printf(
			/* translators: %s: nom du segment cible. */
			esc_html__( 'Cible : %s. Enregistrez d\'abord vos modifications.', '180c' ),
			esc_html( _180c_onesignal_segment_name( '' !== $segment ? $segment : _180c_onesignal_default_segment() ) )
		);
		?>
	</p>
	<?php
}

/**
 * Persiste les post meta de contenu à l'enregistrement de la notification.
 *
 * @param int $post_id ID du post.
 * @return void
 */
function _180c_notif_save_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$nonce = isset( $_POST['_180c_notif_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_180c_notif_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, '180c_notif_save' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$body = isset( $_POST['_180c_notif_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_180c_notif_body'] ) ) : '';
	update_post_meta( $post_id, '_180c_notif_body', $body );

	// L'image n'est plus saisie : elle est dérivée de la cible à la lecture
	// (feed + payload). La meta _180c_notif_image_id n'est donc plus écrite ;
	// les valeurs déjà présentes sur d'anciennes notifications restent intactes.

	$target_type = isset( $_POST['_180c_notif_target_type'] )
		? _180c_notif_sanitize_target_type( wp_unslash( $_POST['_180c_notif_target_type'] ) )
		: 'recipe';
	update_post_meta( $post_id, '_180c_notif_target_type', $target_type );

	$target_id = isset( $_POST['_180c_notif_target_id'] ) ? absint( wp_unslash( $_POST['_180c_notif_target_id'] ) ) : 0;
	update_post_meta( $post_id, '_180c_notif_target_id', ( in_array( $target_type, array( 'recipe', 'product' ), true ) ? $target_id : 0 ) );

	$segment = isset( $_POST['_180c_notif_segment'] )
		? _180c_notif_sanitize_segment( wp_unslash( $_POST['_180c_notif_segment'] ) )
		: _180c_onesignal_default_segment();
	update_post_meta( $post_id, '_180c_notif_segment', $segment );
}
add_action( 'save_post_' . _180C_NOTIF_POST_TYPE, '_180c_notif_save_meta' );

/**
 * Handler d'envoi manuel « Envoyer maintenant ».
 *
 * Soumis via le bouton `formaction` du formulaire d'édition. Vérifie nonce,
 * capability, confirmation App Store, puis appelle le client OneSignal.
 *
 * @return void
 */
function _180c_notif_handle_send() {
	$nonce = isset( $_POST['_180c_send_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_180c_send_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, '180c_send_notification' ) ) {
		wp_die( esc_html__( 'Jeton de sécurité invalide.', '180c' ), 403 );
	}

	$post_id = isset( $_POST['post_ID'] ) ? absint( wp_unslash( $_POST['post_ID'] ) ) : 0;
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( ! $post || _180C_NOTIF_POST_TYPE !== $post->post_type ) {
		wp_die( esc_html__( 'Notification introuvable.', '180c' ), 404 );
	}

	if ( ! current_user_can( 'publish_posts' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'Permissions insuffisantes.', '180c' ), 403 );
	}

	// Idempotence : déjà envoyée → on ne renvoie jamais.
	if ( '' !== (string) get_post_meta( $post_id, '_180c_onesignal_id', true ) ) {
		_180c_notif_redirect_after_send( $post_id, 'already' );
	}

	// Confirmation obligatoire pour les segments à large audience
	// (`all_users`, `subscribers`). `testers` s'envoie sans friction.
	$segment = (string) get_post_meta( $post_id, '_180c_notif_segment', true );
	if ( _180c_onesignal_segment_requires_confirmation( $segment ) && empty( $_POST['_180c_notif_segment_confirm'] ) ) {
		_180c_notif_redirect_after_send( $post_id, 'confirm_required' );
	}

	if ( ! function_exists( '_180c_onesignal_send' ) ) {
		_180c_notif_redirect_after_send( $post_id, 'client_missing' );
	}

	$result = _180c_onesignal_send( $post_id );

	if ( is_wp_error( $result ) ) {
		_180c_notif_redirect_after_send( $post_id, 'error' );
	}

	_180c_notif_redirect_after_send( $post_id, 'sent' );
}
add_action( 'admin_post_180c_send_notification', '_180c_notif_handle_send' );

/**
 * Redirige vers l'écran d'édition avec un code de statut, puis termine.
 *
 * @param int    $post_id ID de la notification.
 * @param string $code    Code de statut (sent|already|error|confirm_required|client_missing).
 * @return void
 */
function _180c_notif_redirect_after_send( $post_id, $code ) {
	$url = add_query_arg(
		array(
			'post'             => $post_id,
			'action'           => 'edit',
			'_180c_notif_sent' => $code,
		),
		admin_url( 'post.php' )
	);
	wp_safe_redirect( $url );
	exit;
}

/**
 * Affiche le résultat d'un envoi après redirection.
 *
 * @return void
 */
function _180c_notif_send_notice() {
	// Lecture seule d'un code de statut post-redirection ; pas d'action mutante.
	if ( ! isset( $_GET['_180c_notif_sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$code = sanitize_key( wp_unslash( $_GET['_180c_notif_sent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$map = array(
		'sent'             => array( 'success', __( 'Notification envoyée.', '180c' ) ),
		'already'          => array( 'warning', __( 'Cette notification a déjà été envoyée.', '180c' ) ),
		'confirm_required' => array( 'error', __( 'Cochez la case de confirmation pour envoyer à ce segment à large audience.', '180c' ) ),
		'client_missing'   => array( 'error', __( 'Client OneSignal indisponible.', '180c' ) ),
		'error'            => array( 'error', __( 'Échec de l\'envoi. Voir la dernière erreur dans la metabox Envoi.', '180c' ) ),
	);

	if ( ! isset( $map[ $code ] ) ) {
		return;
	}

	printf(
		'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
		esc_attr( $map[ $code ][0] ),
		esc_html( $map[ $code ][1] )
	);
}
add_action( 'admin_notices', '_180c_notif_send_notice' );

/**
 * Ajoute les colonnes « Statut d'envoi » et « Segment » à la liste.
 *
 * @param array<string,string> $columns Colonnes existantes.
 * @return array<string,string>
 */
function _180c_notif_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['180c_notif_status']  = __( 'Statut d\'envoi', '180c' );
			$new['180c_notif_segment'] = __( 'Segment', '180c' );
		}
	}
	return $new;
}
add_filter( 'manage_' . _180C_NOTIF_POST_TYPE . '_posts_columns', '_180c_notif_columns' );

/**
 * Rend le contenu des colonnes personnalisées.
 *
 * @param string $column  Clé de colonne.
 * @param int    $post_id ID du post.
 * @return void
 */
function _180c_notif_column_content( $column, $post_id ) {
	if ( '180c_notif_status' === $column ) {
		$onesignal_id = (string) get_post_meta( $post_id, '_180c_onesignal_id', true );
		if ( '' !== $onesignal_id ) {
			$sent_at = (string) get_post_meta( $post_id, '_180c_notif_sent_at', true );
			echo '<span style="color:#46b450;">&#10003; ' . esc_html__( 'Envoyée', '180c' ) . '</span>';
			if ( '' !== $sent_at ) {
				echo '<br><small>' . esc_html( $sent_at ) . '</small>';
			}
		} elseif ( '' !== (string) get_post_meta( $post_id, '_180c_notif_last_error', true ) ) {
			echo '<span style="color:#b32d2e;">' . esc_html__( 'Échec', '180c' ) . '</span>';
		} else {
			echo '<span>' . esc_html__( 'Brouillon', '180c' ) . '</span>';
		}
	}

	if ( '180c_notif_segment' === $column ) {
		$segment = (string) get_post_meta( $post_id, '_180c_notif_segment', true );
		echo esc_html( '' !== $segment ? _180c_onesignal_segment_name( $segment ) : '—' );
	}
}
add_action( 'manage_' . _180C_NOTIF_POST_TYPE . '_posts_custom_column', '_180c_notif_column_content', 10, 2 );

/**
 * Affiche, sur l'écran de liste des notifications, un diagnostic d'état des
 * trois segments : identifiant canonique, nom résolu côté OneSignal, statut
 * actif. Permet de repérer d'un coup d'œil un segment renommé ou supprimé.
 *
 * L'API de listing ne renvoie pas le nombre d'abonnés (metadata uniquement),
 * ce compteur est donc volontairement absent.
 *
 * @return void
 */
function _180c_notif_segments_diagnostic() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-' . _180C_NOTIF_POST_TYPE !== $screen->id ) {
		return;
	}

	$index = _180c_onesignal_fetch_segments_index();
	$rows  = '';

	foreach ( _180c_onesignal_segments() as $key => $seg ) {
		$id       = isset( $seg['id'] ) ? (string) $seg['id'] : '';
		$stored   = isset( $seg['name'] ) ? (string) $seg['name'] : '';
		$resolved = '—';
		$state    = '—';

		if ( is_wp_error( $index ) ) {
			$resolved = esc_html__( 'listing indisponible', '180c' );
		} elseif ( '' !== $id && isset( $index[ $id ] ) ) {
			$name     = '' !== $index[ $id ]['name'] ? $index[ $id ]['name'] : $stored;
			$diverges = $name !== $stored;
			$resolved = esc_html( $name ) . ( $diverges ? ' <span style="color:#b32d2e;">' . esc_html__( '(renommé)', '180c' ) . '</span>' : '' );
			$state    = ! empty( $index[ $id ]['is_active'] )
				? '<span style="color:#46b450;">' . esc_html__( 'actif', '180c' ) . '</span>'
				: '<span style="color:#b32d2e;">' . esc_html__( 'inactif', '180c' ) . '</span>';
		} elseif ( '' !== $id ) {
			$resolved = '<span style="color:#b32d2e;">' . esc_html__( 'introuvable (supprimé ?)', '180c' ) . '</span>';
			$state    = '<span style="color:#b32d2e;">' . esc_html__( 'envoi refusé', '180c' ) . '</span>';
		}

		$rows .= sprintf(
			'<tr><td><code>%s</code></td><td><code style="word-break:break-all;">%s</code></td><td>%s</td><td>%s</td></tr>',
			esc_html( $key ),
			esc_html( $id ),
			$resolved,
			$state
		);
	}

	$note = is_wp_error( $index )
		? '<p class="description">' . esc_html( sprintf( /* translators: %s: message d'erreur. */ __( 'État des segments indisponible : %s', '180c' ), $index->get_error_message() ) ) . '</p>'
		: '';

	printf(
		'<div class="notice notice-info"><p><strong>%s</strong></p><table class="widefat striped" style="max-width:760px;"><thead><tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr></thead><tbody>%s</tbody></table>%s</div>',
		esc_html__( 'État des segments OneSignal', '180c' ),
		esc_html__( 'Clé', '180c' ),
		esc_html__( 'Identifiant', '180c' ),
		esc_html__( 'Nom résolu', '180c' ),
		esc_html__( 'Statut', '180c' ),
		$rows, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fragments échappés ci-dessus.
		$note // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fragment échappé ci-dessus.
	);
}
add_action( 'admin_notices', '_180c_notif_segments_diagnostic' );

/**
 * Endpoint admin-ajax unique servant à la fois la recherche de cible et les
 * données d'aperçu (titre + image dérivée). Jamais exposé publiquement :
 * protégé par nonce + capability.
 *
 * Paramètres POST :
 *   - type : `recipe` | `product` (obligatoire)
 *   - id   : identifiant d'une cible → renvoie ce seul item (mode aperçu)
 *   - q    : terme de recherche → renvoie jusqu'à 20 résultats (mode recherche)
 *
 * Chaque item : { id, title, image_url }. image_url = image dérivée en taille
 * proportionnelle (ou chaîne vide).
 *
 * @return void
 */
function _180c_notif_ajax_target() {
	check_ajax_referer( '180c_notif_target', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Accès refusé.', '180c' ) ), 403 );
	}

	$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	if ( ! in_array( $type, array( 'recipe', 'product' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Type invalide.', '180c' ) ), 400 );
	}

	// Mode aperçu : un identifiant précis est fourni.
	$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
	if ( $id ) {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== $type || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Cible introuvable.', '180c' ) ), 404 );
		}
		wp_send_json_success( array( 'item' => _180c_notif_target_item( $type, $id ) ) );
	}

	// Mode recherche.
	$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
	if ( mb_strlen( $q ) < 2 ) {
		wp_send_json_success( array( 'results' => array() ) );
	}

	$query = new WP_Query(
		array(
			'post_type'      => $type,
			// Produits : tous statuts de catalogue confondus (aucun filtre de
			// visibilité product_visibility appliqué ici).
			'post_status'    => 'publish',
			's'              => $q,
			'posts_per_page' => 20,
			'no_found_rows'  => true,
			'orderby'        => 'relevance',
		)
	);

	$results = array();
	foreach ( $query->posts as $p ) {
		$results[] = _180c_notif_target_item( $type, (int) $p->ID );
	}

	wp_send_json_success( array( 'results' => $results ) );
}
add_action( 'wp_ajax_180c_notif_target', '_180c_notif_ajax_target' );

/**
 * Construit un item de cible pour l'endpoint : id, titre, image dérivée.
 *
 * @param string $type Type de cible (`recipe` | `product`).
 * @param int    $id   Identifiant de la cible.
 * @return array{id:int,title:string,image_url:string}
 */
function _180c_notif_target_item( $type, $id ) {
	return array(
		'id'        => (int) $id,
		'title'     => html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' ),
		'image_url' => _180c_notif_derive_image_url( $type, (int) $id ),
	);
}
