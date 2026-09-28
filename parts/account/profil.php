<?php
/**
 * Mon Compte — Mon profil.
 *
 * Formulaire de modification du compte (via shortcode/action Woo native).
 * Section suppression de compte (stub RGPD — Phase 5).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
	return;
}
?>

<div class="account-profil">

	<h1 class="account-profil__title"><?php esc_html_e( 'Mon profil', '180c' ); ?></h1>

	<?php /* Formulaire Woo natif (edit-account) */ ?>
	<section class="account-profil__section" aria-labelledby="profil-identity-title">
		<h2 id="profil-identity-title" class="account-profil__section-title">
			<?php esc_html_e( 'Mes informations', '180c' ); ?>
		</h2>

		<?php
		/**
		 * Charge le formulaire natif WooCommerce de modification du compte.
		 * Ce template gère : prénom, nom, email, mot de passe.
		 */
		wc_get_template( 'myaccount/form-edit-account.php' );
		?>
	</section>

	<?php /* Section adresses */ ?>
	<section class="account-profil__section" aria-labelledby="profil-addresses-title">
		<h2 id="profil-addresses-title" class="account-profil__section-title">
			<?php esc_html_e( 'Mes adresses', '180c' ); ?>
		</h2>

		<?php
		/**
		 * Charge le formulaire natif WooCommerce de gestion des adresses.
		 */
		wc_get_template( 'myaccount/my-address.php' );
		?>
	</section>

	<?php /* Section suppression de compte — stub RGPD (Phase 5) */ ?>
	<section class="account-profil__section account-profil__section--danger" aria-labelledby="profil-delete-title">
		<h2 id="profil-delete-title" class="account-profil__section-title">
			<?php esc_html_e( 'Suppression de compte', '180c' ); ?>
		</h2>
		<p class="account-profil__danger-desc">
			<?php esc_html_e( 'La suppression de votre compte est définitive. Vos données personnelles seront effacées conformément à notre politique de confidentialité.', '180c' ); ?>
		</p>
		<a
			href="<?php echo esc_url( home_url( '/politique-confidentialite/' ) ); ?>"
			class="btn btn--danger"
			data-account-delete-trigger
		>
			<?php esc_html_e( 'Supprimer mon compte', '180c' ); ?>
		</a>
		<p class="account-profil__danger-note">
			<small>
				<?php
				echo wp_kses(
					__( 'Pour toute demande RGPD, contactez-nous à <a href="mailto:contact@180c.fr">contact@180c.fr</a>. Un email de confirmation vous sera envoyé.', '180c' ),
					array(
						'a' => array( 'href' => array() ),
					)
				);
				?>
			</small>
		</p>
	</section>

</div>
