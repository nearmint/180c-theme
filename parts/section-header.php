<?php
/**
 * Template part : en-tête de section d'archive / liste.
 *
 * En-tête standard des surfaces archive / taxonomie / recherche : eyebrow
 * contextuel (« Catégorie », « Cuisine », « Recherche »…), titre <h1>,
 * sous-titre et description de terme optionnels. Un seul <h1> par page : ce
 * composant le porte, les sections secondaires utilisent <h2>.
 *
 * Usage :
 *   get_template_part( 'parts/section-header', null, array(
 *       'eyebrow'     => __( 'Catégorie', '180c' ),
 *       'title'       => single_term_title( '', false ),
 *       'subtitle'    => '',                 // optionnel
 *       'description' => term_description(),  // HTML, échappé via wp_kses_post
 *   ) );
 *
 * @package 180c
 *
 * @var array $args {
 *     @type string $title       Titre principal (<h1>). Requis — sinon rien n'est rendu.
 *     @type string $eyebrow     Sur-titre contextuel. Optionnel.
 *     @type string $subtitle    Sous-titre court. Optionnel.
 *     @type string $description HTML descriptif (description de terme). Optionnel.
 * }
 */

defined( 'ABSPATH' ) || exit;

$sh_title       = isset( $args['title'] ) ? (string) $args['title'] : '';
$sh_eyebrow     = isset( $args['eyebrow'] ) ? (string) $args['eyebrow'] : '';
$sh_subtitle    = isset( $args['subtitle'] ) ? (string) $args['subtitle'] : '';
$sh_description = isset( $args['description'] ) ? (string) $args['description'] : '';

if ( '' === $sh_title ) {
	return;
}
?>
<header class="section-header">
	<div class="container">
		<?php if ( '' !== $sh_eyebrow ) : ?>
			<p class="section-header__eyebrow"><?php echo esc_html( $sh_eyebrow ); ?></p>
		<?php endif; ?>

		<h1 class="section-header__title"><?php echo esc_html( $sh_title ); ?></h1>

		<?php if ( '' !== $sh_subtitle ) : ?>
			<p class="section-header__subtitle"><?php echo esc_html( $sh_subtitle ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $sh_description ) : ?>
			<div class="section-header__description">
				<?php echo wp_kses_post( $sh_description ); ?>
			</div>
		<?php endif; ?>
	</div>
</header>
