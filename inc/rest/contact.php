<?php
/**
 * Endpoints REST du formulaire de contact — envoi natif `wp_mail()`.
 *
 * Remplace l'ancienne soumission vers Formspree (6 endpoints externes) par un
 * envoi serveur maison. Le routage par objet s'appuie sur la clé `email` du
 * mapping `_180c_contact_recipients()` (inc/contact.php), source unique de
 * vérité.
 *
 * Routes :
 *   POST /180c/v1/contact/       — soumission du formulaire.
 *   GET  /180c/v1/contact/nonce/ — nonce frais (robustesse cache hébergeur / WP Super
 *                                  Cache : une page HTML servie depuis un cache
 *                                  peut porter un nonce périmé).
 *
 * Le paramètre de nonce s'appelle `cf_nonce` et NON `_wpnonce` : ce dernier est
 * réservé par l'API REST du cœur (validé comme nonce `wp_rest`), ce qui
 * provoquerait un 403 avant même d'atteindre le handler. Même leçon que
 * `nl_nonce` sur inc/rest/newsletter.php.
 *
 * Aucun stockage en base : le message ne vit que dans les deux e-mails émis.
 * L'IP n'est jamais journalisée ni stockée en clair (uniquement hachée en clé de
 * transient) ; l'adresse du visiteur n'apparaît dans aucun log.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/** Longueur minimale du message (alignée sur la validation client). */
const _180C_CONTACT_MIN_MESSAGE = 20;

/** Longueur minimale du nom (alignée sur la validation client). */
const _180C_CONTACT_MIN_NAME = 2;

/** Rate limit : nombre de soumissions autorisées par fenêtre et par IP. */
const _180C_CONTACT_RATE_MAX = 3;

/** Rate limit : durée de la fenêtre, en secondes. */
const _180C_CONTACT_RATE_WINDOW = 300;

/** Action du nonce public du formulaire de contact. */
const _180C_CONTACT_NONCE_ACTION = '180c_contact_public';

/**
 * Nom de l'en-tête de traçage posé sur les e-mails du thème.
 *
 * Sert de clé de sélection au digest hebdomadaire (inc/contact-digest/), qui
 * lit la table de logs de WP Mail Logging. Sans cet en-tête, distinguer un
 * message du formulaire de contact des quelques milliers d'e-mails
 * transactionnels WooCommerce imposerait de deviner d'après l'objet — un
 * couplage au wording, donc à la première retouche éditoriale près.
 */
const _180C_MAIL_ORIGIN_HEADER = 'X-180C-Origin';

/** Valeur d'origine du message adressé à l'équipe : la seule que le digest lit. */
const _180C_MAIL_ORIGIN_CONTACT = 'contact-form';

/**
 * Valeur d'origine de l'accusé de réception adressé au visiteur.
 *
 * Volontairement DISTINCTE de `contact-form`. L'accusé reprend le corps du
 * message : marqué à l'identique, il ferait compter chaque soumission deux fois
 * dans le digest, et y ferait figurer l'adresse de l'équipe comme expéditeur.
 * Il est tout de même marqué — la traçabilité vaut pour les deux e-mails.
 */
const _180C_MAIL_ORIGIN_CONTACT_ACK = 'contact-ack';

add_action( 'rest_api_init', '_180c_rest_register_contact' );

/**
 * Enregistre les routes REST du formulaire de contact.
 *
 * Les deux routes sont ouvertes (`__return_true`) : le formulaire est public.
 * La protection repose sur honeypot + nonce + rate-limit IP, jamais sur une
 * authentification.
 *
 * @return void
 */
function _180c_rest_register_contact() {
	// POST /contact/ — soumission. Les paramètres sont lus et validés dans le
	// handler (pipeline à court-circuit), pas déclarés en `args` : cela permet de
	// répondre 200 silencieux au honeypot avant toute autre vérification.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/contact',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => '_180c_rest_contact_submit',
			'permission_callback' => '__return_true',
		)
	);

	// GET /contact/nonce/ — nonce frais pour le formulaire public.
	register_rest_route(
		_180C_API_NAMESPACE,
		'/contact/nonce',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_180c_rest_contact_nonce',
			'permission_callback' => '__return_true',
		)
	);
}

// ============================================================
// Anti-abus
// ============================================================

/**
 * Rate limiting du formulaire de contact (fenêtre fixe, par IP).
 *
 * Décalque `_180c_nl_public_rate_limited()` (inc/rest/newsletter.php) avec un
 * seuil adapté : un formulaire de contact n'a pas la cadence d'un opt-in
 * newsletter. Clé de transient `180c_ct_{md5(ip)}` : l'IP n'est jamais stockée
 * ni journalisée en clair. Le TTL n'est pas réinitialisé à chaque incrément (la
 * fin de fenêtre est conservée dans l'état).
 *
 * @param string $ip Adresse IP du client.
 * @return bool True si la limite est dépassée (requête à rejeter en 429).
 */
function _180c_contact_rate_limited( string $ip ): bool {
	$key   = '180c_ct_' . md5( $ip );
	$now   = time();
	$state = get_transient( $key );

	if ( ! is_array( $state ) || empty( $state['reset'] ) || $now >= (int) $state['reset'] ) {
		set_transient(
			$key,
			array(
				'count' => 1,
				'reset' => $now + _180C_CONTACT_RATE_WINDOW,
			),
			_180C_CONTACT_RATE_WINDOW
		);
		return false;
	}

	if ( (int) $state['count'] >= _180C_CONTACT_RATE_MAX ) {
		return true;
	}

	$state['count'] = (int) $state['count'] + 1;
	$ttl            = max( 1, (int) $state['reset'] - $now );
	set_transient( $key, $state, $ttl );
	return false;
}

/**
 * Détecte un retour chariot ou un saut de ligne dans une valeur brute.
 *
 * Les champs `name` et `email` sont injectés dans un en-tête `Reply-To` : un
 * `\r` ou un `\n` permettrait d'y greffer des en-têtes arbitraires (injection
 * d'en-têtes SMTP). Une valeur porteuse est rejetée, jamais nettoyée
 * silencieusement.
 *
 * @param string $value Valeur brute reçue.
 * @return bool True si la valeur contient un CR ou un LF.
 */
function _180c_contact_has_newline( string $value ): bool {
	return (bool) preg_match( '/[\r\n]/', $value );
}

// ============================================================
// Handlers
// ============================================================

/**
 * Handler GET /contact/nonce — fournit un nonce frais au formulaire public.
 *
 * Donnée non sensible : le nonce est lié à la seule action publique
 * `180c_contact_public`.
 *
 * @return WP_REST_Response
 */
function _180c_rest_contact_nonce(): WP_REST_Response {
	return new WP_REST_Response(
		array( 'nonce' => wp_create_nonce( _180C_CONTACT_NONCE_ACTION ) ),
		200
	);
}

/**
 * Handler POST /contact — valide puis achemine le message par e-mail.
 *
 * Pipeline à court-circuit : honeypot → nonce → rate-limit → validation →
 * envoi à l'équipe → accusé de réception au visiteur → réponse.
 *
 * `get_param()` lit indifféremment un corps JSON ou form-encodé : le fallback
 * sans JavaScript (POST natif du formulaire) atteint donc le même handler.
 *
 * @param WP_REST_Request $request Requête REST.
 * @return WP_REST_Response|WP_Error
 */
function _180c_rest_contact_submit( WP_REST_Request $request ) {
	// 1. Honeypot : un champ `_gotcha` rempli signe un bot. Réponse 200 « ok »
	// silencieuse — on ne révèle jamais le rejet et on n'envoie aucun e-mail.
	if ( '' !== trim( (string) $request->get_param( '_gotcha' ) ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	// 2. Nonce de session (action `180c_contact_public`), paramètre `cf_nonce`.
	$nonce = (string) $request->get_param( 'cf_nonce' );
	if ( ! wp_verify_nonce( $nonce, _180C_CONTACT_NONCE_ACTION ) ) {
		return new WP_Error(
			'invalid_nonce',
			__( 'Session expirée, rechargez la page et réessayez.', '180c' ),
			array( 'status' => 403 )
		);
	}

	// 3. Rate-limit IP (avant tout traitement coûteux).
	if ( _180c_contact_rate_limited( _180c_get_client_ip() ) ) {
		return new WP_Error(
			'rate_limited',
			__( 'Trop de messages envoyés. Merci de réessayer dans quelques minutes.', '180c' ),
			array( 'status' => 429 )
		);
	}

	// 4. Objet : doit correspondre à une clé du mapping. Aucune résolution par
	// libellé — le libellé est du wording, la clé est le contrat de routage.
	$recipients = function_exists( '_180c_contact_recipients' ) ? _180c_contact_recipients() : array();
	if ( empty( $recipients ) ) {
		_180c_log( 'contact: mapping des destinataires vide', array(), 'error' );
		return new WP_Error(
			'contact_unavailable',
			__( 'Une erreur est survenue. Merci de réessayer plus tard.', '180c' ),
			array( 'status' => 500 )
		);
	}

	$objet = sanitize_key( (string) $request->get_param( 'objet' ) );
	if ( ! isset( $recipients[ $objet ] ) ) {
		return new WP_Error(
			'invalid_objet',
			__( 'Merci de choisir l\'objet de votre message.', '180c' ),
			array(
				'status' => 400,
				'field'  => 'objet',
			)
		);
	}

	$to    = isset( $recipients[ $objet ]['email'] ) ? (string) $recipients[ $objet ]['email'] : '';
	$label = isset( $recipients[ $objet ]['label'] ) ? (string) $recipients[ $objet ]['label'] : $objet;

	if ( ! is_email( $to ) ) {
		_180c_log( 'contact: destinataire absent ou invalide', array( 'objet' => $objet ), 'error' );
		return new WP_Error(
			'contact_unavailable',
			__( 'Une erreur est survenue. Merci de réessayer plus tard.', '180c' ),
			array( 'status' => 500 )
		);
	}

	// 5. Nom : refus explicite des CR/LF (injection d'en-têtes), puis longueur.
	$raw_name = (string) $request->get_param( 'name' );
	if ( _180c_contact_has_newline( $raw_name ) ) {
		return new WP_Error(
			'invalid_name',
			__( 'Merci d\'indiquer votre prénom et votre nom.', '180c' ),
			array(
				'status' => 400,
				'field'  => 'name',
			)
		);
	}

	$name = trim( sanitize_text_field( $raw_name ) );
	if ( mb_strlen( $name ) < _180C_CONTACT_MIN_NAME ) {
		return new WP_Error(
			'invalid_name',
			__( 'Votre nom doit contenir au moins 2 caractères.', '180c' ),
			array(
				'status' => 400,
				'field'  => 'name',
			)
		);
	}

	// 6. E-mail : même garde CR/LF, puis validation stricte.
	$raw_email = (string) $request->get_param( 'email' );
	if ( _180c_contact_has_newline( $raw_email ) ) {
		return new WP_Error(
			'invalid_email',
			__( 'Cette adresse e-mail ne semble pas valide.', '180c' ),
			array(
				'status' => 400,
				'field'  => 'email',
			)
		);
	}

	$email = sanitize_email( trim( $raw_email ) );
	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'invalid_email',
			__( 'Cette adresse e-mail ne semble pas valide.', '180c' ),
			array(
				'status' => 400,
				'field'  => 'email',
			)
		);
	}

	// 7. Message : les sauts de ligne sont ici légitimes (corps, pas en-tête).
	$message = trim( wp_strip_all_tags( sanitize_textarea_field( (string) $request->get_param( 'message' ) ) ) );
	if ( mb_strlen( $message ) < _180C_CONTACT_MIN_MESSAGE ) {
		return new WP_Error(
			'invalid_message',
			__( 'Votre message doit contenir au moins 20 caractères.', '180c' ),
			array(
				'status' => 400,
				'field'  => 'message',
			)
		);
	}

	// 8. Envoi à l'équipe. Un échec ne doit JAMAIS être masqué par un 200.
	$sent = wp_mail(
		$to,
		_180c_contact_team_subject( $label, $name ),
		_180c_contact_team_body( $name, $email, $label, $message ),
		_180c_contact_headers( sprintf( '%s <%s>', $name, $email ) )
	);

	if ( ! $sent ) {
		_180c_log( 'contact: envoi équipe échoué', array( 'objet' => $objet ), 'error' );
		return new WP_Error(
			'mail_failed',
			__( 'Une erreur est survenue. Merci de réessayer plus tard.', '180c' ),
			array( 'status' => 500 )
		);
	}

	// 9. Accusé de réception au visiteur (best-effort). Le message de l'équipe est
	// parti : un échec ici ne doit pas transformer un succès en erreur pour le
	// visiteur, qui renverrait alors un doublon.
	$acked = wp_mail(
		$email,
		_180c_contact_ack_subject(),
		_180c_contact_ack_body( $name, $label, $message ),
		_180c_contact_headers( $to, _180C_MAIL_ORIGIN_CONTACT_ACK )
	);

	if ( ! $acked ) {
		_180c_log( 'contact: accusé de réception non envoyé', array( 'objet' => $objet ), 'warning' );
	}

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

// ============================================================
// Composition des e-mails (texte brut UTF-8)
// ============================================================

/**
 * En-têtes communs aux deux e-mails du formulaire.
 *
 * L'expéditeur n'est volontairement PAS forcé : `inc/emails/wp-core-sender.php`
 * pose déjà `wp_mail_from` / `wp_mail_from_name` (« 180°C » et l'adresse
 * expéditrice WooCommerce), ce qui garantit un From sur le domaine et donc
 * l'alignement SPF/DKIM. Le filtre `_180c_contact_from_email` permet de basculer
 * sur une autre adresse du domaine sans toucher à cette logique ; une valeur
 * vide (défaut) laisse l'héritage en place.
 *
 * @param string $reply_to Valeur de l'en-tête Reply-To (déjà formatée).
 * @param string $origin   Valeur de l'en-tête `X-180C-Origin` (traçage digest).
 * @return string[] Liste d'en-têtes.
 */
function _180c_contact_headers( string $reply_to, string $origin = _180C_MAIL_ORIGIN_CONTACT ): array {
	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'Reply-To: ' . $reply_to,
		_180C_MAIL_ORIGIN_HEADER . ': ' . $origin,
	);

	/**
	 * Adresse expéditrice spécifique au formulaire de contact.
	 *
	 * @param string $from Adresse d'envoi. Vide (défaut) = hériter de `wp_mail_from`.
	 */
	$from = (string) apply_filters( '_180c_contact_from_email', '' );

	if ( '' !== $from && is_email( $from ) ) {
		$from_name = defined( '_180C_MAIL_FROM_NAME' ) ? _180C_MAIL_FROM_NAME : get_bloginfo( 'name' );
		$headers[] = sprintf( 'From: %s <%s>', $from_name, $from );
	}

	return $headers;
}

/**
 * Objet de l'e-mail adressé à l'équipe.
 *
 * @param string $label Libellé de l'objet choisi.
 * @param string $name  Nom du visiteur.
 * @return string
 */
function _180c_contact_team_subject( string $label, string $name ): string {
	return sprintf(
		/* translators: 1: libellé de l'objet du message, 2: nom du visiteur. */
		__( '[Contact 180°C] %1$s — %2$s', '180c' ),
		$label,
		$name
	);
}

/**
 * Corps de l'e-mail adressé à l'équipe.
 *
 * @param string $name    Nom du visiteur.
 * @param string $email   E-mail du visiteur.
 * @param string $label   Libellé de l'objet choisi.
 * @param string $message Message du visiteur.
 * @return string
 */
function _180c_contact_team_body( string $name, string $email, string $label, string $message ): string {
	$lines = array(
		__( 'Nouveau message via le formulaire de contact du site.', '180c' ),
		'',
		sprintf( /* translators: %s: nom du visiteur. */ __( 'Nom : %s', '180c' ), $name ),
		sprintf( /* translators: %s: e-mail du visiteur. */ __( 'Email : %s', '180c' ), $email ),
		sprintf( /* translators: %s: libellé de l'objet du message. */ __( 'Objet : %s', '180c' ), $label ),
		'',
		__( 'Message :', '180c' ),
		$message,
		'',
		'—',
		__( 'Répondre directement à cet email écrira au visiteur (Reply-To).', '180c' ),
	);

	return implode( "\n", $lines );
}

/**
 * Objet de l'accusé de réception adressé au visiteur.
 *
 * @return string
 */
function _180c_contact_ack_subject(): string {
	return __( 'Nous avons bien reçu votre message — 180°C', '180c' );
}

/**
 * Corps de l'accusé de réception adressé au visiteur.
 *
 * @param string $name    Nom du visiteur.
 * @param string $label   Libellé de l'objet choisi.
 * @param string $message Message du visiteur.
 * @return string
 */
function _180c_contact_ack_body( string $name, string $label, string $message ): string {
	$lines = array(
		sprintf( /* translators: %s: nom du visiteur. */ __( 'Bonjour %s,', '180c' ), $name ),
		'',
		__( 'Votre message a bien été transmis à l\'équipe 180°C. Nous revenons vers vous au plus vite.', '180c' ),
		'',
		__( 'Rappel de votre demande', '180c' ),
		sprintf( /* translators: %s: libellé de l'objet du message. */ __( 'Objet : %s', '180c' ), $label ),
		__( 'Message :', '180c' ),
		$message,
		'',
		__( 'À très vite,', '180c' ),
		__( 'L\'équipe 180°C', '180c' ),
	);

	return implode( "\n", $lines );
}
