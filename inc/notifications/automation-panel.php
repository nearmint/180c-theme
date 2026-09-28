<?php
/**
 * Panneau d'administration de l'automation « push à la publication ».
 *
 * Rendu SOUS le tableau de la liste des notifications
 * (edit.php?post_type=180c_notification), sur `manage_posts_extra_tablenav`
 * en position `bottom`. Aucun sous-menu, aucune page d'options séparée.
 *
 * ── Pourquoi aucun <form> n'est imbriqué ici ───────────────────────────────
 * `manage_posts_extra_tablenav` se déclenche depuis
 * `wp-admin/includes/class-wp-posts-list-table.php:619`, c'est-à-dire À
 * L'INTÉRIEUR du `<form id="posts-filter" method="get">` ouvert par
 * `wp-admin/edit.php:488`. Un `<form>` imbriqué est purement et simplement
 * supprimé par le parseur HTML : nos champs se retrouveraient rattachés au
 * formulaire de filtre GET, et le bouton d'enregistrement rechargerait la
 * liste sans rien écrire. Les hooks `admin_footer` et `in_admin_footer`, eux,
 * se déclenchent après la fermeture de `#wpbody-content` : mauvaise colonne.
 *
 * On utilise donc l'attribut HTML5 `form=` : les contrôles visibles vivent
 * dans le panneau, les trois `<form>` qui les possèdent sont émis masqués en
 * pied de page. Le propriétaire d'un champ étant déterminé par cet attribut,
 * rien ne fuit dans le formulaire de filtre. Même principe que le `formaction`
 * déjà utilisé par la metabox « Envoi » du module manuel.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Identifiant du formulaire d'enregistrement des réglages.
 */
define( '_180C_NOTIF_FORM_SAVE', '180c-notif-automation-save' );

/**
 * Identifiant du formulaire d'envoi de test.
 */
define( '_180C_NOTIF_FORM_TEST', '180c-notif-automation-test' );

/**
 * Identifiant du formulaire d'annulation.
 */
define( '_180C_NOTIF_FORM_CANCEL', '180c-notif-automation-cancel' );

/**
 * Indique si l'écran courant est la liste des notifications.
 *
 * @return bool
 */
function _180c_notif_automation_is_list_screen(): bool {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	$screen = get_current_screen();

	return $screen instanceof WP_Screen && 'edit-' . _180C_NOTIF_POST_TYPE === $screen->id;
}

/**
 * Charge les assets du panneau sur le seul écran de liste.
 *
 * CSS et JS servis tels quels depuis `assets/` : c'est la convention du module
 * notifications (cf. `_180c_notif_admin_assets()`), les assets d'administration
 * ne passant pas par Vite. Aucune dépendance, pas de jQuery.
 *
 * @param string $hook Hook de la page admin courante.
 * @return void
 */
function _180c_notif_automation_assets( $hook ): void {
	if ( 'edit.php' !== $hook || ! _180c_notif_automation_is_list_screen() ) {
		return;
	}

	$version = defined( '_180C_VERSION' ) ? _180C_VERSION : null;

	// Le CSS est chargé pour tout le monde sur cet écran : il habille aussi les
	// pastilles des colonnes « Origine » et « Statut d'envoi », visibles sans
	// `manage_options`. Le JS, lui, ne sert qu'au panneau.
	wp_enqueue_style(
		'180c-notif-automation',
		get_template_directory_uri() . '/assets/css/notif-automation.css',
		array(),
		$version
	);

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_enqueue_script(
		'180c-notif-automation',
		get_template_directory_uri() . '/assets/js/notif-automation.js',
		array(),
		$version,
		true
	);
}
add_action( 'admin_enqueue_scripts', '_180c_notif_automation_assets' );

/**
 * Rend la liste cliquable des jetons sous un champ de gabarit.
 *
 * @param string $target_id ID du champ visé.
 * @return void
 */
function _180c_notif_render_token_list( string $target_id ): void {
	echo '<p class="notif-automation__tokens">';
	echo '<span class="notif-automation__tokens-label">' . esc_html__( 'Jetons :', '180c' ) . '</span> ';

	foreach ( _180c_notif_tokens() as $token => $description ) {
		printf(
			'<button type="button" class="notif-automation__token" data-token="%1$s" data-target="%2$s" title="%3$s">%1$s</button> ',
			esc_attr( $token ),
			esc_attr( $target_id ),
			esc_attr( $description )
		);
	}

	echo '</p>';
}

/**
 * Rend le compteur de caractères d'un champ.
 *
 * @param string $target_id   ID du champ visé.
 * @param int    $current     Longueur courante.
 * @param int    $recommended Longueur recommandée.
 * @return void
 */
function _180c_notif_render_counter( string $target_id, int $current, int $recommended ): void {
	printf(
		'<p class="description notif-automation__counter" data-counter-for="%1$s"><span class="notif-automation__count">%2$d</span> %3$s</p>',
		esc_attr( $target_id ),
		(int) $current,
		esc_html(
			sprintf(
				/* translators: %d: longueur recommandée par OneSignal. */
				__( 'caractères (recommandé : %d max).', '180c' ),
				$recommended
			)
		)
	);
}

/**
 * Rend le bloc « Aperçu » : rendu serveur des trois gabarits.
 *
 * Aucun appel API : c'est une projection locale des gabarits sur la dernière
 * recette publiée.
 *
 * @param int $recipe_id ID de la recette d'aperçu.
 * @return void
 */
function _180c_notif_render_preview_block( int $recipe_id ): void {
	echo '<h3 class="notif-automation__subtitle">' . esc_html__( 'Aperçu', '180c' ) . '</h3>';

	if ( ! $recipe_id ) {
		echo '<p class="description">' . esc_html__( 'Aucune recette publiée : aperçu indisponible.', '180c' ) . '</p>';

		return;
	}

	$texts = _180c_notif_render_auto_texts( $recipe_id );

	printf(
		'<p class="description">%s <a href="%s">%s</a></p>',
		esc_html__( 'Rendu sur la dernière recette publiée :', '180c' ),
		esc_url( (string) get_edit_post_link( $recipe_id ) ),
		esc_html( get_the_title( $recipe_id ) )
	);

	echo '<table class="widefat striped notif-automation__preview"><tbody>';

	$rows = array(
		__( 'Titre', '180c' )      => $texts['heading'],
		__( 'Sous-titre', '180c' ) => $texts['subtitle'],
		__( 'Corps', '180c' )      => $texts['content'],
	);

	foreach ( $rows as $label => $value ) {
		printf(
			'<tr><th scope="row">%s</th><td>%s</td><td class="notif-automation__preview-len">%s</td></tr>',
			esc_html( $label ),
			'' !== $value ? esc_html( $value ) : '<em>' . esc_html__( '(vide)', '180c' ) . '</em>',
			esc_html( sprintf( '%d car.', mb_strlen( $value ) ) )
		);
	}

	echo '</tbody></table>';
}

/**
 * Rend le bloc « État ».
 *
 * @return void
 */
function _180c_notif_render_state_block(): void {
	echo '<h3 class="notif-automation__subtitle">' . esc_html__( 'État', '180c' ) . '</h3>';

	// Le prochain créneau calculé est affiché en permanence, même sans
	// notification programmée : sans cette confirmation visuelle, le réglage
	// « jour + heure » reste abstrait.
	printf(
		'<p class="notif-automation__slot">%s <strong>%s</strong></p>',
		esc_html__( 'Prochain créneau :', '180c' ),
		esc_html( _180c_notif_format_slot( _180c_notif_next_slot() ) )
	);

	$scheduled = _180c_notif_scheduled_post();

	if ( ! $scheduled instanceof WP_Post ) {
		echo '<p class="description">' . esc_html__( 'Aucune notification programmée pour l\'instant.', '180c' ) . '</p>';

		return;
	}

	$recipe_id     = (int) get_post_meta( $scheduled->ID, '_180c_notif_target_id', true );
	$scheduled_for = (string) get_post_meta( $scheduled->ID, _180C_NOTIF_META_SCHEDULED_FOR, true );
	$slot_ts       = '' !== $scheduled_for ? (int) strtotime( $scheduled_for . ' UTC' ) : 0;

	echo '<table class="widefat striped notif-automation__state"><tbody>';

	printf(
		'<tr><th scope="row">%s</th><td><a href="%s">%s</a></td></tr>',
		esc_html__( 'Recette', '180c' ),
		esc_url( (string) get_edit_post_link( $recipe_id ) ),
		esc_html( $recipe_id ? get_the_title( $recipe_id ) : __( '(introuvable)', '180c' ) )
	);

	printf(
		'<tr><th scope="row">%s</th><td>%s</td></tr>',
		esc_html__( 'Envoi prévu', '180c' ),
		esc_html( $slot_ts ? _180c_notif_format_slot( $slot_ts ) : __( '(inconnu)', '180c' ) )
	);

	printf(
		'<tr><th scope="row">%s</th><td><a href="%s">#%d</a></td></tr>',
		esc_html__( 'Notification', '180c' ),
		esc_url( (string) get_edit_post_link( $scheduled->ID ) ),
		(int) $scheduled->ID
	);

	echo '</tbody></table>';

	/*
	 * Un changement de réglage ne déplace PAS un envoi déjà déposé chez
	 * OneSignal — le déplacer supposerait de l'annuler et de le recréer, geste
	 * destructeur sur un envoi peut-être imminent. On ne le fait donc pas, mais
	 * on refuse de laisser l'écart passer inaperçu : l'exploitant le voit, et
	 * le bouton d'annulation est juste en dessous.
	 */
	if ( $slot_ts > 0 && ! _180c_notif_slot_matches_settings( $slot_ts ) ) {
		$settings = _180c_notif_automation_settings();
		$weekdays = _180c_notif_weekday_labels();

		printf(
			'<p class="notif-automation__mismatch">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: créneau de l'envoi en attente, 2: jour réglé, 3: heure réglée. */
					__( 'Cet envoi reste programmé pour %1$s, ce qui ne correspond plus au créneau réglé (%2$s à %3$s). Modifier le réglage ne déplace pas un envoi déjà déposé chez OneSignal : pour appliquer le nouveau créneau, annulez cet envoi puis réenregistrez la recette.', '180c' ),
					_180c_notif_format_slot( $slot_ts ),
					$weekdays[ (int) $settings['day'] ] ?? '',
					(string) $settings['time']
				)
			)
		);
	}

	printf(
		'<p><input type="hidden" name="notif_id" value="%1$d" form="%2$s" /><button type="submit" form="%2$s" class="button" onclick="return confirm(\'%3$s\');">%4$s</button></p>',
		(int) $scheduled->ID,
		esc_attr( _180C_NOTIF_FORM_CANCEL ),
		esc_js( __( 'Annuler l\'envoi programmé ?', '180c' ) ),
		esc_html__( 'Annuler l\'envoi programmé', '180c' )
	);
}

/**
 * Rend le bloc « Journal ».
 *
 * @return void
 */
function _180c_notif_render_log_block(): void {
	echo '<h3 class="notif-automation__subtitle">' . esc_html__( 'Journal', '180c' ) . '</h3>';

	$entries = _180c_notif_automation_log_entries( 20 );

	if ( empty( $entries ) ) {
		echo '<p class="description">' . esc_html__( 'Aucun événement enregistré.', '180c' ) . '</p>';

		return;
	}

	$labels = _180c_notif_automation_log_labels();

	echo '<table class="widefat striped notif-automation__log"><thead><tr>';
	printf( '<th>%s</th>', esc_html__( 'Date (UTC)', '180c' ) );
	printf( '<th>%s</th>', esc_html__( 'Événement', '180c' ) );
	printf( '<th>%s</th>', esc_html__( 'Recette', '180c' ) );
	printf( '<th>%s</th>', esc_html__( 'Notification', '180c' ) );
	printf( '<th>%s</th>', esc_html__( 'Motif', '180c' ) );
	echo '</tr></thead><tbody>';

	foreach ( $entries as $entry ) {
		$event     = isset( $entry['event'] ) ? (string) $entry['event'] : '';
		$recipe_id = isset( $entry['recipe_id'] ) ? (int) $entry['recipe_id'] : 0;
		$notif_id  = isset( $entry['notif_id'] ) ? (int) $entry['notif_id'] : 0;

		echo '<tr>';
		printf( '<td><code>%s</code></td>', esc_html( isset( $entry['time'] ) ? (string) $entry['time'] : '' ) );
		printf(
			'<td><span class="notif-automation__badge notif-automation__badge--%s">%s</span></td>',
			esc_attr( $event ),
			esc_html( $labels[ $event ] ?? $event )
		);
		printf(
			'<td>%s</td>',
			$recipe_id
				? '<a href="' . esc_url( (string) get_edit_post_link( $recipe_id ) ) . '">#' . (int) $recipe_id . '</a>'
				: '—'
		);
		printf(
			'<td>%s</td>',
			$notif_id
				? '<a href="' . esc_url( (string) get_edit_post_link( $notif_id ) ) . '">#' . (int) $notif_id . '</a>'
				: '—'
		);
		printf( '<td>%s</td>', esc_html( isset( $entry['reason'] ) ? (string) $entry['reason'] : '' ) );
		echo '</tr>';
	}

	echo '</tbody></table>';
}

/**
 * Rend le panneau complet sous le tableau de la liste.
 *
 * @param string $which Position dans la list table : `top` ou `bottom`.
 * @return void
 */
function _180c_notif_render_automation_panel( $which ): void {
	if ( 'bottom' !== $which || ! _180c_notif_automation_is_list_screen() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings  = _180c_notif_automation_settings();
	$recipe_id = _180c_notif_latest_recipe_id();
	$save      = esc_attr( _180C_NOTIF_FORM_SAVE );
	$weekdays  = _180c_notif_weekday_labels();
	?>
	<div class="notif-automation" id="180c-notif-automation">
		<h2 class="notif-automation__title"><?php esc_html_e( 'Automation — push à la publication d\'une recette', '180c' ); ?></h2>

		<div class="notif-automation__master <?php echo $settings['enabled'] ? 'is-on' : 'is-off'; ?>">
			<label>
				<input type="checkbox" name="_180c_notif_automation[enabled]" value="1"
					form="<?php echo $save; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Échappé à l'affectation. ?>"
					<?php checked( (bool) $settings['enabled'] ); ?> />
				<strong><?php esc_html_e( 'Activer l\'envoi automatique', '180c' ); ?></strong>
			</label>
			<p class="description">
				<?php esc_html_e( 'À chaque créneau, une seule notification part : la dernière recette publiée depuis le créneau précédent. Activer vaut confirmation pour le segment choisi, y compris à large audience — aucune case ne sera redemandée.', '180c' ); ?>
			</p>
		</div>

		<div class="notif-automation__grid">
			<p>
				<label for="180c-notif-day"><strong><?php esc_html_e( 'Jour', '180c' ); ?></strong></label><br>
				<select id="180c-notif-day" name="_180c_notif_automation[day]" form="<?php echo $save; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<?php foreach ( $weekdays as $d => $label ) : ?>
						<option value="<?php echo (int) $d; ?>" <?php selected( (int) $settings['day'], $d ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="180c-notif-time"><strong><?php esc_html_e( 'Heure', '180c' ); ?></strong></label><br>
				<input type="time" id="180c-notif-time" name="_180c_notif_automation[time]"
					value="<?php echo esc_attr( (string) $settings['time'] ); ?>"
					form="<?php echo $save; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
				<span class="description"><?php echo esc_html( sprintf( /* translators: %s: identifiant du fuseau du site. */ __( 'fuseau du site (%s)', '180c' ), wp_timezone()->getName() ) ); ?></span>
			</p>
			<p>
				<label for="180c-notif-lead"><strong><?php esc_html_e( 'Marge minimale', '180c' ); ?></strong></label><br>
				<input type="number" id="180c-notif-lead" name="_180c_notif_automation[min_lead_minutes]" min="0" max="1440" step="1"
					value="<?php echo (int) $settings['min_lead_minutes']; ?>"
					form="<?php echo $save; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
				<span class="description"><?php esc_html_e( 'minutes — en deçà, report d\'une semaine', '180c' ); ?></span>
			</p>
		</div>

		<p><strong><?php esc_html_e( 'Segment', '180c' ); ?></strong></p>
		<p class="notif-automation__segments">
			<?php foreach ( _180c_onesignal_segments() as $key => $segment ) : ?>
				<label>
					<input type="radio" name="_180c_notif_automation[segment]" value="<?php echo esc_attr( $key ); ?>"
						form="<?php echo $save; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
						<?php checked( (string) $settings['segment'], $key ); ?> />
					<?php echo esc_html( is_array( $segment ) ? $segment['name'] : (string) $segment ); ?>
				</label>
			<?php endforeach; ?>
		</p>

		<h3 class="notif-automation__subtitle"><?php esc_html_e( 'Gabarits', '180c' ); ?></h3>

		<p>
			<label for="180c-notif-heading"><strong><?php esc_html_e( 'Titre', '180c' ); ?></strong></label><br>
			<input type="text" id="180c-notif-heading" class="large-text" name="_180c_notif_automation[heading_template]"
				value="<?php echo esc_attr( (string) $settings['heading_template'] ); ?>"
				form="<?php echo $save; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
		</p>
		<?php
		_180c_notif_render_counter( '180c-notif-heading', mb_strlen( (string) $settings['heading_template'] ), 50 );
		_180c_notif_render_token_list( '180c-notif-heading' );
		?>

		<p>
			<label for="180c-notif-subtitle"><strong><?php esc_html_e( 'Sous-titre', '180c' ); ?></strong></label><br>
			<input type="text" id="180c-notif-subtitle" class="large-text" name="_180c_notif_automation[subtitle_template]"
				value="<?php echo esc_attr( (string) $settings['subtitle_template'] ); ?>"
				form="<?php echo $save; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
		</p>
		<p class="description notif-automation__warning">
			<?php esc_html_e( 'Ce champ n\'est rendu que sur iOS et macOS. Il est ignoré sur Android et sur le web push.', '180c' ); ?>
		</p>
		<?php
		_180c_notif_render_counter( '180c-notif-subtitle', mb_strlen( (string) $settings['subtitle_template'] ), 50 );
		_180c_notif_render_token_list( '180c-notif-subtitle' );
		?>

		<p>
			<label for="180c-notif-content"><strong><?php esc_html_e( 'Corps', '180c' ); ?></strong></label><br>
			<textarea id="180c-notif-content" class="large-text" rows="3" name="_180c_notif_automation[content_template]"
				form="<?php echo $save; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_textarea( (string) $settings['content_template'] ); ?></textarea>
		</p>
		<?php
		_180c_notif_render_counter( '180c-notif-content', mb_strlen( (string) $settings['content_template'] ), _180C_NOTIF_BODY_MAX );
		_180c_notif_render_token_list( '180c-notif-content' );
		?>

		<p class="notif-automation__actions">
			<button type="submit" form="<?php echo $save; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="button button-primary">
				<?php esc_html_e( 'Enregistrer les réglages', '180c' ); ?>
			</button>
			<button type="submit" form="<?php echo esc_attr( _180C_NOTIF_FORM_TEST ); ?>" class="button"
				onclick="return confirm('<?php echo esc_js( __( 'Envoyer un test au segment Testeurs, maintenant ?', '180c' ) ); ?>');">
				<?php esc_html_e( 'Envoyer un test aux Testeurs', '180c' ); ?>
			</button>
		</p>

		<?php
		_180c_notif_render_preview_block( $recipe_id );
		_180c_notif_render_state_block();
		_180c_notif_render_log_block();
		?>
	</div>
	<?php
}
add_action( 'manage_posts_extra_tablenav', '_180c_notif_render_automation_panel' );

/**
 * Émet les trois formulaires propriétaires des contrôles du panneau.
 *
 * Masqués et vides de tout champ visible : ils n'existent que pour être
 * désignés par l'attribut `form=` des contrôles rendus plus haut dans la page.
 *
 * @return void
 */
function _180c_notif_automation_forms(): void {
	if ( ! _180c_notif_automation_is_list_screen() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$forms = array(
		_180C_NOTIF_FORM_SAVE   => array( '180c_notif_automation_save', '_180c_notif_automation_save_nonce' ),
		_180C_NOTIF_FORM_TEST   => array( '180c_notif_automation_test', '_180c_notif_automation_test_nonce' ),
		_180C_NOTIF_FORM_CANCEL => array( '180c_notif_automation_cancel', '_180c_notif_automation_cancel_nonce' ),
	);

	foreach ( $forms as $id => $config ) {
		printf(
			'<form id="%s" method="post" action="%s" hidden><input type="hidden" name="action" value="%s" />',
			esc_attr( $id ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $config[0] )
		);
		wp_nonce_field( $config[0], $config[1] );
		echo '</form>';
	}
}
add_action( 'admin_footer', '_180c_notif_automation_forms' );

/**
 * Redirige vers la liste avec un code de résultat, puis termine.
 *
 * @param string $code Code de résultat.
 * @return void
 */
function _180c_notif_automation_redirect( string $code ): void {
	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type'              => _180C_NOTIF_POST_TYPE,
				'_180c_notif_automation' => $code,
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}

/**
 * Vérifie capability et nonce, ou meurt.
 *
 * @param string $action     Action du nonce.
 * @param string $nonce_name Nom du champ de nonce.
 * @return void
 */
function _180c_notif_automation_guard( string $action, string $nonce_name ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permissions insuffisantes.', '180c' ), 403 );
	}

	// `$_REQUEST` et non `$_POST` : l'annulation est aussi déclenchable depuis
	// l'action de ligne de la liste, un lien GET porteur du même nonce — c'est
	// la mécanique des actions de ligne du cœur (corbeille, restauration).
	$nonce = isset( $_REQUEST[ $nonce_name ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $nonce_name ] ) ) : '';

	if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
		wp_die( esc_html__( 'Jeton de sécurité invalide.', '180c' ), 403 );
	}
}

/**
 * Handler d'enregistrement des réglages.
 *
 * @return void
 */
function _180c_notif_automation_handle_save(): void {
	_180c_notif_automation_guard( '180c_notif_automation_save', '_180c_notif_automation_save_nonce' );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce vérifié par _180c_notif_automation_guard() ci-dessus ; le tableau brut passe intégralement par _180c_notif_automation_sanitize() deux lignes plus bas.
	$raw = isset( $_POST['_180c_notif_automation'] ) ? wp_unslash( $_POST['_180c_notif_automation'] ) : array();

	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	// Le sanitizer est appliqué par `sanitize_option_{$option}` à l'écriture ;
	// on le rejoue ici pour disposer des valeurs normalisées côté message.
	$clean = _180c_notif_automation_sanitize( $raw );

	update_option( _180C_NOTIF_AUTOMATION_OPTION, $clean, false );

	/*
	 * `update_option()` renvoie aussi `false` quand la valeur est simplement
	 * inchangée : son retour ne distingue pas « rien à faire » de « écriture
	 * refusée ». Seule la relecture fait foi. Sans ce contrôle, le panneau
	 * annonçait « Réglages enregistrés » même quand rien n'avait été écrit.
	 */
	if ( _180c_notif_automation_settings() !== $clean ) {
		_180c_notif_automation_log(
			'api_error',
			array(
				'reason' => __( 'Écriture des réglages refusée par la base : les valeurs affichées ne sont pas celles enregistrées.', '180c' ),
			)
		);

		_180c_notif_automation_redirect( 'save_failed' );
	}

	_180c_notif_automation_log(
		'settings',
		array(
			'reason' => sprintf(
				/* translators: 1: état de l'interrupteur, 2: créneau, 3: segment. */
				__( '%1$s — créneau %2$s, segment %3$s', '180c' ),
				$clean['enabled'] ? __( 'activée', '180c' ) : __( 'désactivée', '180c' ),
				_180c_notif_format_slot( _180c_notif_next_slot() ),
				_180c_onesignal_segment_name( (string) $clean['segment'] )
			),
		)
	);

	// Avertissement non bloquant : une notification à 4 h du matin est un motif
	// de désabonnement, mais c'est la décision de l'administrateur.
	$hour = (int) substr( (string) $clean['time'], 0, 2 );

	_180c_notif_automation_redirect( ( $hour < 7 || $hour >= 22 ) ? 'saved_hour' : 'saved' );
}
add_action( 'admin_post_180c_notif_automation_save', '_180c_notif_automation_handle_save' );

/**
 * Envoie un test immédiat au segment Testeurs.
 *
 * Ne crée aucun post `180c_notification` : le payload est construit et posté
 * directement. C'est la seule duplication assumée du bloc HTTP du client — la
 * contourner aurait imposé de refactoriser `_180c_onesignal_send()`, dont la
 * non-régression sur le chemin manuel est une exigence du lot. Les helpers de
 * segment et d'image, eux, sont bien réutilisés.
 *
 * @param int $recipe_id ID de la recette servant de support au test.
 * @return true|WP_Error
 */
function _180c_notif_automation_send_test( int $recipe_id ) {
	if ( ! $recipe_id ) {
		return new WP_Error( 'notif_test_no_recipe', __( 'Aucune recette publiée : test impossible.', '180c' ) );
	}

	$segment_name = _180c_onesignal_resolve_segment_name( 'testers' );

	if ( is_wp_error( $segment_name ) ) {
		return $segment_name;
	}

	$texts   = _180c_notif_render_auto_texts( $recipe_id );
	$payload = array(
		'app_id'            => _180c_onesignal_app_id(),
		'target_channel'    => 'push',
		'included_segments' => array( $segment_name ),
		'headings'          => array( 'en' => $texts['heading'] ),
		'contents'          => array( 'en' => '' !== $texts['content'] ? $texts['content'] : $texts['heading'] ),
		'data'              => array(
			'type' => 'recipe',
			'id'   => $recipe_id,
			'url'  => (string) get_permalink( $recipe_id ),
		),
		'idempotency_key'   => wp_generate_uuid4(),
	);

	if ( '' !== $texts['subtitle'] ) {
		$payload['subtitle'] = array( 'en' => $texts['subtitle'] );
	}

	$image_url = _180c_notif_derive_image_url( 'recipe', $recipe_id );
	if ( '' !== $image_url ) {
		$payload['ios_attachments'] = array( '180c_image' => $image_url );
		$payload['big_picture']     = $image_url;
	}

	if ( _180c_onesignal_is_dry_run() ) {
		_180c_log(
			'OneSignal dry-run : test automation',
			array(
				'recipe_id' => $recipe_id,
				'payload'   => $payload,
			)
		);

		return true;
	}

	if ( ! _180c_onesignal_is_configured() ) {
		return new WP_Error( 'notif_not_configured', __( 'OneSignal non configuré.', '180c' ) );
	}

	$args = array(
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Key ' . _180c_onesignal_rest_key(),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		),
		'body'    => wp_json_encode( $payload ),
	);

	$response = wp_remote_post( _180C_ONESIGNAL_ENDPOINT, $args );
	if ( is_wp_error( $response ) ) {
		$response = wp_remote_post( _180C_ONESIGNAL_ENDPOINT, $args );
	}

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error(
			'notif_http_error',
			/* translators: %d: code HTTP renvoyé par OneSignal. */
			sprintf( __( 'OneSignal a répondu HTTP %d.', '180c' ), $code )
		);
	}

	return true;
}

/**
 * Handler du bouton de test.
 *
 * @return void
 */
function _180c_notif_automation_handle_test(): void {
	_180c_notif_automation_guard( '180c_notif_automation_test', '_180c_notif_automation_test_nonce' );

	$recipe_id = _180c_notif_latest_recipe_id();
	$result    = _180c_notif_automation_send_test( $recipe_id );

	if ( is_wp_error( $result ) ) {
		_180c_notif_automation_log(
			'api_error',
			array(
				'recipe_id' => $recipe_id,
				'reason'    => sprintf(
					/* translators: %s: message d'erreur. */
					__( 'Échec du test : %s', '180c' ),
					$result->get_error_message()
				),
			)
		);

		_180c_notif_automation_redirect( 'test_failed' );
	}

	_180c_notif_automation_log(
		'test_sent',
		array(
			'recipe_id' => $recipe_id,
			'reason'    => __( 'Test envoyé au segment Testeurs (aucune notification créée).', '180c' ),
		)
	);

	_180c_notif_automation_redirect( 'test_sent' );
}
add_action( 'admin_post_180c_notif_automation_test', '_180c_notif_automation_handle_test' );

/**
 * Handler d'annulation manuelle depuis le panneau.
 *
 * @return void
 */
function _180c_notif_automation_handle_cancel(): void {
	_180c_notif_automation_guard( '180c_notif_automation_cancel', '_180c_notif_automation_cancel_nonce' );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce vérifié ci-dessus.
	$notif_id = isset( $_REQUEST['notif_id'] ) ? absint( wp_unslash( $_REQUEST['notif_id'] ) ) : 0;
	$post     = $notif_id ? get_post( $notif_id ) : null;

	if ( ! $post instanceof WP_Post || _180C_NOTIF_POST_TYPE !== $post->post_type ) {
		_180c_notif_automation_redirect( 'cancel_failed' );
	}

	$done = _180c_notif_cancel_scheduled( $notif_id, __( 'Annulation manuelle depuis le panneau', '180c' ) );

	_180c_notif_automation_redirect( $done ? 'cancelled' : 'cancel_failed' );
}
add_action( 'admin_post_180c_notif_automation_cancel', '_180c_notif_automation_handle_cancel' );

/**
 * Affiche le résultat d'une action du panneau après redirection.
 *
 * @return void
 */
function _180c_notif_automation_notice(): void {
	if ( ! _180c_notif_automation_is_list_screen() ) {
		return;
	}

	// Lecture seule d'un code de résultat post-redirection ; aucune action mutante.
	if ( ! isset( $_GET['_180c_notif_automation'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$code = sanitize_key( wp_unslash( $_GET['_180c_notif_automation'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$map = array(
		'saved'         => array( 'success', __( 'Réglages de l\'automation enregistrés.', '180c' ) ),
		'save_failed'   => array( 'error', __( 'Échec de l\'enregistrement : la base a refusé l\'écriture, les réglages sont inchangés. Voir le journal ci-dessous.', '180c' ) ),
		'saved_hour'    => array( 'warning', __( 'Réglages enregistrés. L\'heure choisie est hors de la plage 07:00–22:00 : une notification reçue en pleine nuit est un motif fréquent de désabonnement.', '180c' ) ),
		'test_sent'     => array( 'success', __( 'Test envoyé au segment Testeurs. Aucune notification n\'a été créée.', '180c' ) ),
		'test_failed'   => array( 'error', __( 'Échec de l\'envoi de test. Voir le journal ci-dessous.', '180c' ) ),
		'cancelled'     => array( 'success', __( 'Envoi programmé annulé.', '180c' ) ),
		'cancel_failed' => array( 'error', __( 'Annulation impossible : aucun envoi programmé sur cette notification.', '180c' ) ),
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
add_action( 'admin_notices', '_180c_notif_automation_notice' );
