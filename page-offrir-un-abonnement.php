<?php
/**
 * Template Name: Offrir un abonnement
 * Template Post Type: page
 *
 * Page autonome du tunnel cadeau : pitch + formulaire cadeau toujours ouvert.
 * Appliquée automatiquement à la page de slug « offrir-un-abonnement » (hiérarchie
 * de templates WordPress `page-{slug}.php`). Le formulaire est partagé avec le
 * module accordéon de page-abonnement.php (template-parts/gift/gift-form.php).
 *
 * @package 180c-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="gift gift--page" data-component="gift">
	<div class="gift__inner container-180c">

		<header class="gift__intro">
			<h1 class="gift__title"><?php esc_html_e( 'Offrir un abonnement', '180c' ); ?></h1>
			<p class="gift__lead">
				<?php esc_html_e( 'Offrez 12 mois de recettes 180°C à une personne qui vous est chère. Un paiement unique, sans reconduction : vous choisissez le bénéficiaire, le message et la date d’envoi.', '180c' ); ?>
			</p>
		</header>

		<div class="gift__panel">
			<?php get_template_part( 'template-parts/gift/gift-form', null, array( 'context' => 'page' ) ); ?>
		</div>

	</div>
</main>

<?php
get_footer();
