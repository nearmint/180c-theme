<?php
/**
 * Mon compte — écran S0-rôle : endpoint indisponible pour ce compte.
 *
 * Rendu quand un compte dont le rôle n'est pas éligible (auteur, éditeur,
 * administrateur…) atteint l'URL en accès direct. L'entrée de menu, elle, n'est
 * jamais affichée pour ces comptes.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;
?>

<section class="c-account-deletion">
	<p class="c-account-deletion__intro">
		<?php esc_html_e( 'Cette page n\'est pas disponible pour votre compte.', '180c' ); ?>
	</p>
</section>
