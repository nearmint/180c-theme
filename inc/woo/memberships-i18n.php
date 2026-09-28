<?php
/**
 * Traduction FR ciblée de WooCommerce Memberships — 180°C.
 *
 * Le plugin WooCommerce Memberships est premium (hors wordpress.org) et ne
 * fournit aucun pack de langue fr_FR (`wp language plugin install` échoue :
 * « Language not available »). Seules quelques chaînes de ce plugin sont
 * réellement exposées aux clientes, toutes dans les e-mails transactionnels :
 *
 *  1. Le bloc « Thanks for purchasing a membership!… » injecté par le plugin
 *     dans TOUS les e-mails client de commande (hook `woocommerce_email_order_meta`
 *     → `WC_Memberships_Emails::maybe_render_thank_you_content()` →
 *     `wc_memberships_get_order_thank_you_links()`). Ce bloc s'affiche aussi
 *     sur la page de remerciement (thank-you) : la traduction ci-dessous
 *     corrige les deux surfaces d'un coup.
 *  2. L'e-mail « Membership Ended » (le seul e-mail spécifique membership
 *     actuellement activé), dont l'objet / le titre / le corps par défaut sont
 *     en anglais.
 *
 * Plutôt que de compiler et maintenir un `.mo` complet (des milliers de
 * chaînes, à re-générer à chaque MAJ du plugin), on traduit chirurgicalement
 * ces chaînes via le filtre `gettext`, scopé au text domain du plugin.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Corps FR de l'e-mail « Membership Ended ».
 *
 * Les balises entre accolades ({member_name}, {membership_plan}, {site_title},
 * {membership_renewal_url}) sont des merge tags remplacés par le plugin après
 * traduction : elles doivent rester intactes.
 *
 * @return string HTML du corps traduit.
 */
function _180c_memberships_ended_email_body_fr(): string {
	return '
		<p>Bonjour {member_name},</p>
		<p>Votre accès à {membership_plan} sur {site_title} vient de prendre fin.</p>
		<p>Pour continuer à profiter des contenus et avantages réservés aux abonnés, renouvelez votre abonnement.</p>
		<p><a href="{membership_renewal_url}">Cliquez ici pour vous connecter et renouveler votre abonnement</a>.</p>
		<p>{site_title}</p>
	';
}

/**
 * Traduit en français les chaînes de WooCommerce Memberships visibles côté client.
 *
 * @param string $translation Traduction courante (identique au texte source si aucune).
 * @param string $text        Texte source anglais passé à `__()`.
 * @param string $domain      Text domain de l'appel.
 * @return string Traduction FR le cas échéant, sinon la valeur courante.
 */
function _180c_memberships_gettext_fr( string $translation, string $text, string $domain ): string {
	if ( 'woocommerce-memberships' !== $domain ) {
		return $translation;
	}

	// Chaînes simples (une ligne). Les %1$s/%2$s et {merge_tags} sont préservés.
	static $map = array(
		'Thanks for purchasing a membership!'
			=> 'Merci pour votre abonnement !',
		'You can view more details about your membership from %1$syour account%2$s.'
			=> 'Retrouvez le détail de votre abonnement dans %1$svotre compte%2$s.',
		'You can view details for each membership in your account:'
			=> 'Retrouvez le détail de chacun de vos abonnements dans votre compte :',
		'Your {site_title} membership has expired'
			=> 'Votre abonnement {site_title} a expiré',
		'Renew your {membership_plan}'
			=> 'Renouvelez votre {membership_plan}',
	);

	if ( isset( $map[ $text ] ) ) {
		return $map[ $text ];
	}

	// Corps de l'e-mail « Membership Ended » : le texte source est un bloc HTML
	// multiligne indenté par tabulations. On le détecte par une phrase
	// distinctive (robuste aux différences d'espaces) plutôt que par une
	// correspondance exacte fragile.
	if ( false !== strpos( $text, 'your access to {membership_plan}' ) ) {
		return _180c_memberships_ended_email_body_fr();
	}

	return $translation;
}
add_filter( 'gettext', '_180c_memberships_gettext_fr', 10, 3 );
