<?php
/**
 * Écran d'administration « Digest contact » (Outils).
 *
 * POURQUOI CET ÉCRAN
 * ------------------
 * La production n'offre ni SSH ni WP-CLI : ni
 * `wp cron event list`, ni la lecture d'`error_log`. Sans cet écran, rien ne
 * permettrait de distinguer « le cron a tourné et la semaine était calme » de
 * « le cron n'est plus planifié » — deux états qui se ressemblent exactement
 * vus de la boîte de réception : aucun e-mail.
 *
 * L'écran affiche donc, en plus des réglages, les trois faits qu'on ne peut
 * vérifier autrement : la date du dernier envoi, celle du prochain
 * déclenchement planifié, et l'état de la source de collecte.
 *
 * LE BOUTON D'ENVOI IMMÉDIAT CONSOMME LA FENÊTRE
 * ----------------------------------------------
 * C'est le même `_180c_contact_digest_run()` que le cron : en cas de succès, il
 * avance `_180c_contact_digest_last_run`, et les messages envoyés ne seront donc
 * pas repris dans le digest du lundi suivant. Ce n'est pas une perte — ils ont
 * été reçus — mais c'est un déplacement de fenêtre, annoncé en toutes lettres
 * à côté du bouton plutôt que découvert après coup.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/** Slug de la page. */
const _180C_CONTACT_DIGEST_PAGE = '180c-contact-digest';

/** Action `admin_post` d'enregistrement des réglages. */
const _180C_CONTACT_DIGEST_SAVE_ACTION = '_180c_contact_digest_save';

/** Action `admin_post` d'envoi immédiat. */
const _180C_CONTACT_DIGEST_SEND_ACTION = '_180c_contact_digest_send_now';

/**
 * Enregistre la sous-page sous le menu « Outils ».
 *
 * @return void
 */
function _180c_contact_digest_admin_menu(): void {
	add_submenu_page(
		'tools.php',
		__( 'Digest contact', '180c' ),
		__( 'Digest contact', '180c' ),
		'manage_options',
		_180C_CONTACT_DIGEST_PAGE,
		'_180c_contact_digest_admin_render'
	);
}
add_action( 'admin_menu', '_180c_contact_digest_admin_menu' );

/**
 * URL de la page, éventuellement porteuse d'un code de retour.
 *
 * @param string $notice Code de notice à transmettre, ou '' pour aucun.
 * @return string
 */
function _180c_contact_digest_admin_url( string $notice = '' ): string {
	$url = admin_url( 'tools.php?page=' . _180C_CONTACT_DIGEST_PAGE );

	return '' === $notice ? $url : add_query_arg( '_180c_notice', $notice, $url );
}

/**
 * Enregistre les réglages soumis.
 *
 * @return void
 */
function _180c_contact_digest_admin_save(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ) );
	}
	check_admin_referer( _180C_CONTACT_DIGEST_SAVE_ACTION );

	// Un champ vidé remet le destinataire sur l'adresse d'administration : c'est
	// la valeur par défaut du getter, l'option est donc effacée et non forcée.
	$raw       = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';
	$recipient = is_email( $raw ) ? $raw : '';

	update_option( _180C_CONTACT_DIGEST_RECIPIENT_OPTION, $recipient, false );
	update_option(
		_180C_CONTACT_DIGEST_ENABLED_OPTION,
		empty( $_POST['enabled'] ) ? '0' : '1',
		false
	);

	$notice = ( '' === $raw || is_email( $raw ) ) ? 'saved' : 'bad_email';

	wp_safe_redirect( _180c_contact_digest_admin_url( $notice ) );
	exit;
}
add_action( 'admin_post_' . _180C_CONTACT_DIGEST_SAVE_ACTION, '_180c_contact_digest_admin_save' );

/**
 * Déclenche un envoi immédiat.
 *
 * @return void
 */
function _180c_contact_digest_admin_send(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ) );
	}
	check_admin_referer( _180C_CONTACT_DIGEST_SEND_ACTION );

	// Un destinataire enregistré est la seule condition NON négociable de l'envoi
	// manuel. L'absence de clé d'API, elle, ne bloque pas : le mode brut est
	// annoncé à l'écran, l'opérateur sait ce qu'il déclenche. Une adresse jamais
	// réglée, en revanche, enverrait le digest vers `admin_email` — envoi réussi,
	// information perdue, et fenêtre de collecte consommée pour rien.
	if ( ! _180c_contact_digest_recipient_is_configured() ) {
		wp_safe_redirect( _180c_contact_digest_admin_url( 'no_recipient' ) );
		exit;
	}

	$result = _180c_contact_digest_run( 'manuel' );

	wp_safe_redirect( _180c_contact_digest_admin_url( $result['sent'] ? 'sent_' . $result['mode'] : 'send_failed' ) );
	exit;
}
add_action( 'admin_post_' . _180C_CONTACT_DIGEST_SEND_ACTION, '_180c_contact_digest_admin_send' );

/**
 * Rend la notice correspondant au code de retour présent dans l'URL.
 *
 * @return void
 */
function _180c_contact_digest_admin_notice(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple code d'affichage, sans effet.
	$notice = isset( $_GET['_180c_notice'] ) ? sanitize_key( wp_unslash( $_GET['_180c_notice'] ) ) : '';

	if ( '' === $notice ) {
		return;
	}

	$messages = array(
		'saved'        => array( 'success', __( 'Réglages enregistrés.', '180c' ) ),
		'bad_email'    => array( 'error', __( 'Adresse invalide : le digest repart vers l\'adresse d\'administration.', '180c' ) ),
		'sent_analyse' => array( 'success', __( 'Digest envoyé, messages analysés.', '180c' ) ),
		'sent_brut'    => array( 'warning', __( 'Digest envoyé en mode brut : l\'analyse n\'a pas pu être effectuée. Le détail figure dans l\'e-mail.', '180c' ) ),
		'sent_ras'     => array( 'success', __( 'Digest « RAS » envoyé : aucun message sur la période.', '180c' ) ),
		'send_failed'  => array( 'error', __( 'L\'envoi a échoué. La fenêtre de collecte n\'a pas été déplacée.', '180c' ) ),
		'no_recipient' => array( 'error', __( 'Envoi refusé : aucun destinataire enregistré. Le digest serait parti vers l\'adresse d\'administration et la fenêtre de collecte aurait été consommée. Réglez l\'adresse ci-dessous, puis relancez.', '180c' ) ),
	);

	if ( ! isset( $messages[ $notice ] ) ) {
		return;
	}

	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $messages[ $notice ][0] ),
		esc_html( $messages[ $notice ][1] )
	);
}

/**
 * Libellé de ce qui manque, par code de blocage.
 *
 * @param string $blocker Code rendu par `_180c_contact_digest_blockers()`.
 * @return string
 */
function _180c_contact_digest_blocker_label( string $blocker ): string {
	$labels = array(
		'recipient' => __( 'le destinataire n\'a jamais été enregistré', '180c' ),
		'api_key'   => __( 'la constante _180C_ANTHROPIC_API_KEY est absente de wp-config.php', '180c' ),
	);

	return $labels[ $blocker ] ?? $blocker;
}

/**
 * Notice persistante signalant une occurrence planifiée annulée.
 *
 * Affichée sur TOUT l'écran d'administration, et non sur la seule page du
 * digest : une occurrence annulée est précisément l'événement que personne ne
 * va chercher. Elle n'est pas `is-dismissible` — on la fait disparaître en
 * complétant la configuration, pas en la fermant.
 *
 * La trace est effacée ici même dès que les manques sont comblés : sans cela,
 * la notice survivrait jusqu'au lundi suivant en décrivant un problème déjà
 * résolu, ce qui est la meilleure façon d'apprendre à l'ignorer.
 *
 * @return void
 */
function _180c_contact_digest_skipped_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$skipped = get_option( _180C_CONTACT_DIGEST_SKIPPED_OPTION, array() );

	if ( ! is_array( $skipped ) || empty( $skipped['blockers'] ) ) {
		return;
	}

	if ( ! _180c_contact_digest_blockers() ) {
		delete_option( _180C_CONTACT_DIGEST_SKIPPED_OPTION );
		return;
	}

	$manques = array();
	foreach ( (array) $skipped['blockers'] as $blocker ) {
		$manques[] = _180c_contact_digest_blocker_label( (string) $blocker );
	}

	printf(
		'<div class="notice notice-warning"><p><strong>%1$s</strong><br>%2$s<br>%3$s <a href="%4$s">%5$s</a></p></div>',
		esc_html(
			sprintf(
				/* translators: %s: date de l'occurrence annulée. */
				__( 'Digest contact : l\'envoi du %s n\'a pas eu lieu.', '180c' ),
				_180c_contact_digest_admin_date( (int) ( $skipped['time'] ?? 0 ) )
			)
		),
		esc_html(
			sprintf(
				/* translators: %s: liste de ce qui manque. */
				__( 'Configuration incomplète : %s.', '180c' ),
				implode( __( ', et ', '180c' ), $manques )
			)
		),
		esc_html__( 'Aucun message n\'est perdu : la fenêtre de collecte est préservée et ces messages figureront dans le premier envoi qui aboutira.', '180c' ),
		esc_url( _180c_contact_digest_admin_url() ),
		esc_html__( 'Compléter la configuration', '180c' )
	);
}
add_action( 'admin_notices', '_180c_contact_digest_skipped_notice' );

/**
 * Formate un horodatage dans le fuseau du site, ou un tiret s'il est absent.
 *
 * @param int $timestamp Horodatage Unix, ou 0.
 * @return string
 */
function _180c_contact_digest_admin_date( int $timestamp ): string {
	if ( $timestamp <= 0 ) {
		return '—';
	}

	return wp_date( 'l j F Y, H:i', $timestamp );
}

/**
 * Rend la page.
 *
 * @return void
 */
function _180c_contact_digest_admin_render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ) );
	}

	$last_run = (int) get_option( _180C_CONTACT_DIGEST_LAST_RUN_OPTION, 0 );
	$next_run = (int) wp_next_scheduled( _180C_CONTACT_DIGEST_HOOK );
	$source   = _180c_contact_digest_source_status();
	$has_key  = '' !== _180c_contact_digest_api_key();
	$window   = _180c_contact_digest_window();
	$pending  = count( _180c_contact_digest_collect( $window ) );

	// Destinataire jamais réglé : l'option est vide et le digest part vers
	// `admin_email`. Sur ce site, c'est une boîte de
	// service, pas celle de la personne qui traite les bugs. Le digest partirait
	// donc sans erreur, et personne ne le lirait.
	$recipient_is_default = ! _180c_contact_digest_recipient_is_configured();
	$blockers             = _180c_contact_digest_blockers();
	$gap_days             = _180c_contact_digest_window_gap_days( $window );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Digest contact', '180c' ); ?></h1>

		<p class="description" style="max-width:52em;">
			<?php esc_html_e( 'Chaque lundi à 8 h, les messages reçus par le formulaire de contact depuis le dernier envoi sont soumis à l\'API Claude, qui ne retient que les signalements de bug et les propositions d\'amélioration. Le résultat part vers une adresse unique.', '180c' ); ?>
		</p>

		<?php _180c_contact_digest_admin_notice(); ?>

		<?php if ( $recipient_is_default ) : ?>
			<div class="notice notice-warning" style="max-width:52em;">
				<p>
					<strong><?php esc_html_e( 'Le destinataire n\'a pas encore été réglé.', '180c' ); ?></strong><br>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: adresse e-mail d'administration du site. */
							__( 'Le digest partira vers l\'adresse d\'administration du site (%s). Réglez le destinataire ci-dessous AVANT le premier envoi : un digest envoyé à la mauvaise adresse ne signale rien, il arrive simplement dans une boîte que personne ne relève.', '180c' ),
							(string) get_option( 'admin_email' )
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'État', '180c' ); ?></h2>
		<table class="widefat striped" style="max-width:52em;">
			<tbody>
				<tr>
					<th scope="row" style="width:16em;"><?php esc_html_e( 'Dernier envoi', '180c' ); ?></th>
					<td>
						<?php echo esc_html( _180c_contact_digest_admin_date( $last_run ) ); ?>
						<?php if ( $last_run <= 0 ) : ?>
							<span class="description"><?php esc_html_e( '— jamais envoyé : la première fenêtre couvrira les 7 derniers jours.', '180c' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Prochain déclenchement', '180c' ); ?></th>
					<td>
						<?php if ( $next_run > 0 ) : ?>
							<?php echo esc_html( _180c_contact_digest_admin_date( $next_run ) ); ?>
							<?php if ( $blockers ) : ?>
								<br><strong style="color:#996800;"><?php esc_html_e( 'Cette occurrence sera annulée.', '180c' ); ?></strong>
								<span class="description">
									<?php
									$manques = array_map( '_180c_contact_digest_blocker_label', $blockers );
									echo esc_html(
										sprintf(
											/* translators: %s: liste de ce qui manque. */
											__( 'Il manque : %s. Aucun e-mail ne partira et la fenêtre de collecte restera ouverte — rien ne sera perdu.', '180c' ),
											implode( __( ', et ', '180c' ), $manques )
										)
									);
									?>
								</span>
							<?php endif; ?>
						<?php else : ?>
							<strong style="color:#b32d2e;"><?php esc_html_e( 'Aucune planification.', '180c' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Rechargez une page du site : la planification se rétablit au chargement suivant.', '180c' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Messages en attente', '180c' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %d: nombre de messages déjà collectés sur la fenêtre en cours. */
							esc_html( _n( '%d message dans la fenêtre en cours.', '%d messages dans la fenêtre en cours.', $pending, '180c' ) ),
							(int) $pending
						);
						?>
						<?php if ( $gap_days > 0 ) : ?>
							<br><strong style="color:#b32d2e;"><?php esc_html_e( 'Comptage incomplet.', '180c' ); ?></strong>
							<span class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: nombre de jours effacés. */
										_n(
											'La rotation des logs a effacé %d jour au début de la fenêtre : les messages de ce jour-là ne sont plus lisibles et ne seront jamais rapportés.',
											'La rotation des logs a effacé %d jours au début de la fenêtre : les messages de ces jours-là ne sont plus lisibles et ne seront jamais rapportés.',
											$gap_days,
											'180c'
										),
										$gap_days
									)
								);
								?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Clé d\'API', '180c' ); ?></th>
					<td>
						<?php if ( $has_key ) : ?>
							<?php esc_html_e( 'Définie.', '180c' ); ?>
							<span class="description"><?php echo esc_html( sprintf( /* translators: %s: identifiant du modèle. */ __( 'Modèle : %s', '180c' ), _180c_contact_digest_model() ) ); ?></span>
						<?php else : ?>
							<strong style="color:#996800;"><?php esc_html_e( 'Absente.', '180c' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Le digest part quand même, en mode brut : tous les messages, sans tri. Définissez _180C_ANTHROPIC_API_KEY dans wp-config.php pour activer l\'analyse.', '180c' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Source des messages', '180c' ); ?></th>
					<td>
						<?php if ( ! $source['exists'] ) : ?>
							<strong style="color:#b32d2e;"><?php esc_html_e( 'Table de logs introuvable.', '180c' ); ?></strong>
							<span class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: nom de la table attendue. */
										__( 'La table %s n\'existe pas : le plugin WP Mail Logging est-il actif ? Sans lui, aucun message ne peut être collecté.', '180c' ),
										$source['table']
									)
								);
								?>
							</span>
						<?php else : ?>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: taille de la table en Mo. */
									__( 'WP Mail Logging, table présente (%s Mo).', '180c' ),
									number_format_i18n( $source['size_mb'], 2 )
								)
							);
							?>
							<br>
							<?php
							// La rotation de logs DOIT être active : c'est son absence qui a
							// laissé la table atteindre ~115 Mo en production. Mais sa
							// rétention doit dépasser largement la cadence du digest, car
							// plusieurs délais s'additionnent avant qu'un message soit lu
							// (cf. _180c_contact_digest_rotation_floor_days()). D'où quatre
							// diagnostics distincts, et non un avertissement unique.
							switch ( $source['rotation_state'] ) {
								case 'ok':
									printf(
										'<span class="description">%s</span>',
										esc_html(
											sprintf(
												/* translators: 1: rétention configurée en jours, 2: plancher recommandé en jours. */
												__( 'Rotation des logs active : %1$d jours de rétention (plancher recommandé : %2$d). Réglage conforme.', '180c' ),
												$source['rotation_days'],
												$source['floor_days']
											)
										)
									);
									break;

								case 'too_short':
									printf(
										'<strong style="color:#b32d2e;">%1$s</strong> <span class="description">%2$s</span>',
										esc_html__( 'Rétention trop courte.', '180c' ),
										esc_html(
											sprintf(
												/* translators: 1: rétention configurée en jours, 2: plancher recommandé en jours. */
												__( 'La rotation supprime les e-mails de plus de %1$d jours, en dessous du plancher de %2$d. Un envoi retardé — occurrence annulée, échec d\'envoi, WP-Cron en retard derrière le cache — trouverait des messages déjà effacés, et le digest les omettrait sans rien signaler. Portez la rétention à %2$d jours au moins.', '180c' ),
												$source['rotation_days'],
												$source['floor_days']
											)
										)
									);
									break;

								case 'off_large':
									printf(
										'<strong style="color:#996800;">%1$s</strong> <span class="description">%2$s</span>',
										esc_html__( 'Rotation des logs désactivée.', '180c' ),
										esc_html(
											sprintf(
												/* translators: 1: taille de la table en Mo, 2: plancher recommandé en jours. */
												__( 'La table atteint %1$s Mo et ne sera jamais purgée : la collecte hebdomadaire la balaie intégralement, `timestamp` ne portant aucun index. Activez la rotation par date avec %2$d jours de rétention (Réglages de WP Mail Logging → Log Rotation). Ne videz jamais la table à la main : elle est la source du digest.', '180c' ),
												number_format_i18n( $source['size_mb'], 2 ),
												$source['floor_days']
											)
										)
									);
									break;

								default:
									printf(
										'<span class="description">%s</span>',
										esc_html(
											sprintf(
												/* translators: %d: plancher recommandé en jours. */
												__( 'Rotation des logs désactivée. Sans conséquence à cette taille, mais la table ne cessera de croître : prévoyez une rotation par date à %d jours de rétention.', '180c' ),
												$source['floor_days']
											)
										)
									);
									break;
							}
							?>
							<?php if ( $source['rotation_count'] > 0 ) : ?>
								<br><span class="description" style="color:#b32d2e;">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: nombre de lignes conservées. */
											__( 'Rotation par NOMBRE active : seuls les %d derniers e-mails sont conservés, tous expéditeurs confondus. Aucune garantie de durée ne s\'en déduit — une rafale d\'e-mails transactionnels peut effacer les messages de contact de la veille. Préférez la rotation par date.', '180c' ),
											$source['rotation_count']
										)
									);
									?>
								</span>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Réglages', '180c' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( _180C_CONTACT_DIGEST_SAVE_ACTION ); ?>">
			<?php wp_nonce_field( _180C_CONTACT_DIGEST_SAVE_ACTION ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="_180c-digest-recipient"><?php esc_html_e( 'Destinataire', '180c' ); ?></label>
					</th>
					<td>
						<input type="email" class="regular-text" id="_180c-digest-recipient" name="recipient"
							value="<?php echo esc_attr( (string) get_option( _180C_CONTACT_DIGEST_RECIPIENT_OPTION, '' ) ); ?>"
							placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>">
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: adresse e-mail d'administration du site. */
									__( 'Une seule adresse. Laissé vide, le digest part vers l\'adresse d\'administration (%s).', '180c' ),
									(string) get_option( 'admin_email' )
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Envoi hebdomadaire', '180c' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( _180c_contact_digest_is_enabled() ); ?>>
							<?php esc_html_e( 'Envoyer le digest chaque lundi à 8 h.', '180c' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Décoché, le cron reste planifié mais ne fait rien : réactiver suffit, sans replanifier.', '180c' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Enregistrer', '180c' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Envoi immédiat', '180c' ); ?></h2>
		<div class="notice notice-warning inline" style="max-width:52em;margin:0 0 12px;">
			<p>
				<?php esc_html_e( 'Ce bouton consomme la fenêtre en cours : les messages envoyés maintenant ne figureront PAS dans le digest de lundi, qui repartira de cet instant. Rien n\'est perdu — mais rien n\'est répété non plus.', '180c' ); ?>
			</p>
			<?php if ( $recipient_is_default ) : ?>
				<p>
					<strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: adresse e-mail d'administration du site. */
								__( 'Destinataire non réglé : cet envoi partirait vers %s. Enregistrez d\'abord la bonne adresse ci-dessus — la fenêtre serait consommée pour rien.', '180c' ),
								(string) get_option( 'admin_email' )
							)
						);
						?>
					</strong>
				</p>
			<?php endif; ?>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( _180C_CONTACT_DIGEST_SEND_ACTION ); ?>">
			<?php wp_nonce_field( _180C_CONTACT_DIGEST_SEND_ACTION ); ?>
			<?php submit_button( __( 'Générer et m\'envoyer maintenant', '180c' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
