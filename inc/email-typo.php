<?php
/**
 * Détection des fautes de frappe dans le domaine d'une adresse e-mail.
 *
 * Origine : doublons de comptes abonnés (support, septembre 2026). Une abonnée
 * a payé deux ans avec une adresse en `wandoo.fr` au lieu de `wanadoo.fr` : le
 * domaine parqué accepte tout sans rebond, la newsletter premium partait dans
 * le vide et un second compte a été créé à côté de l'ancien. Rien ne signalait
 * la faute à la saisie.
 *
 * Ce fichier porte la SOURCE UNIQUE des listes de domaines. Le JS
 * (`src/js/modules/email-suggest.js`) les reçoit via `window._180c.emailDomains`
 * et ne les duplique pas.
 *
 * La suggestion n'est jamais bloquante : une adresse suspecte reste acceptée.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Domaines de référence : ceux que l'on propose en correction.
 *
 * Les fournisseurs les plus fréquents chez les abonnés 180°C.
 */
const _180C_EMAIL_REFERENCE_DOMAINS = array(
	'wanadoo.fr',
	'orange.fr',
	'free.fr',
	'sfr.fr',
	'laposte.net',
	'gmail.com',
	'hotmail.fr',
	'hotmail.com',
	'yahoo.fr',
	'outlook.fr',
	'icloud.com',
);

/**
 * Domaines légitimes proches d'un domaine de référence : jamais de suggestion.
 *
 * Sans cette liste, une distance de Levenshtein ≤ 2 produit environ 50 % de
 * faux positifs (mesuré en prod le 2026-09-28 : 33 comptes sur 70 à domaine
 * « proche » utilisaient un vrai fournisseur, dont 13 en `ymail.com`).
 *
 * Deux sources :
 * - les 14 domaines réellement observés chez nos clients (33 comptes) ;
 * - des variantes nationales réelles des mêmes fournisseurs, à distance ≤ 2.
 *
 * Ajouter ici tout domaine légitime signalé à tort ; ne jamais élargir la
 * distance pour compenser.
 */
const _180C_EMAIL_LEGIT_DOMAINS = array(
	// Observés en prod.
	'ymail.com',
	'mail.com',
	'mailo.com',
	'mozmail.com',
	'dbmail.com',
	'hotmail.it',
	'hotmail.be',
	'hotmail.de',
	'outlook.de',
	'yahoo.de',
	'yahoo.es',
	'yahoo.ca',
	'yahoo.ie',
	'yahoo.it',
	// Variantes nationales réelles, non encore observées.
	'email.com',
	'yahoo.be',
	'hotmail.es',
	'hotmail.ca',
	'hotmail.ch',
	'outlook.be',
	'outlook.es',
	'outlook.it',
	'orange.be',
);

/**
 * Distance maximale entre un domaine saisi et un domaine de référence.
 */
const _180C_EMAIL_TYPO_MAX_DISTANCE = 2;

/**
 * Propose une correction pour une adresse dont le domaine semble mal saisi.
 *
 * @param string $email Adresse saisie.
 * @return string Adresse corrigée (partie locale conservée telle quelle), ou
 *                chaîne vide si le domaine est connu, légitime ou trop éloigné.
 */
function _180c_email_domain_suggestion( string $email ): string {
	$email = trim( $email );
	$at    = strrpos( $email, '@' );
	if ( false === $at || 0 === $at ) {
		return '';
	}

	$local  = substr( $email, 0, $at );
	$domain = strtolower( substr( $email, $at + 1 ) );
	if ( '' === $domain
		|| in_array( $domain, _180C_EMAIL_REFERENCE_DOMAINS, true )
		|| in_array( $domain, _180C_EMAIL_LEGIT_DOMAINS, true ) ) {
		return '';
	}

	$best      = '';
	$best_dist = _180C_EMAIL_TYPO_MAX_DISTANCE + 1;
	foreach ( _180C_EMAIL_REFERENCE_DOMAINS as $ref ) {
		$dist = levenshtein( $domain, $ref );
		if ( $dist < $best_dist ) {
			$best      = $ref;
			$best_dist = $dist;
		}
	}

	return $best_dist <= _180C_EMAIL_TYPO_MAX_DISTANCE ? $local . '@' . $best : '';
}

/**
 * Expose les listes de domaines au JS (`window._180c.emailDomains`).
 *
 * Données statiques : compatibles avec le cache page (WP Super Cache).
 *
 * @return void
 */
function _180c_email_typo_inline_data(): void {
	wp_add_inline_script(
		'180c-data',
		sprintf(
			'window._180c = Object.assign(window._180c || {}, { emailDomains: %s });',
			wp_json_encode(
				array(
					'reference'   => _180C_EMAIL_REFERENCE_DOMAINS,
					'legit'       => _180C_EMAIL_LEGIT_DOMAINS,
					'maxDistance' => _180C_EMAIL_TYPO_MAX_DISTANCE,
				)
			)
		)
	);
}
// Priorité 6 : après l'enregistrement de `180c-data` (priorité 5, inc/enqueue.php).
add_action( 'wp_enqueue_scripts', '_180c_email_typo_inline_data', 6 );
