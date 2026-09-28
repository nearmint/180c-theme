<?php
/**
 * Vue — page de connexion.
 *
 * Chargée via template_include lorsque _180c_auth=login.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main-content" class="auth-page">
	<div class="auth-page__inner">
		<?php get_template_part( 'parts/auth/login-form' ); ?>
	</div>
</main>

<?php
get_footer();
