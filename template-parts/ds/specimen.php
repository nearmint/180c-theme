<?php
/**
 * Design System — partial « specimen ».
 *
 * Spécimen typographique ou échelle (famille, taille, weight…). Affiche un
 * rendu démonstratif + le nom du token + sa valeur. Utilisé par la section
 * Foundations (typographie, espacements, rayons, ombres). Finalisé en Phase 2.
 *
 * @var array $args {
 *   @type string $token  Nom du token (ex. --text-2xl).
 *   @type string $value  Valeur affichée.
 *   @type string $sample Texte ou contenu de démonstration.
 *   @type string $style  Déclaration inline appliquée au rendu (ex. font-size: var(--text-2xl)).
 * }
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_token  = isset( $args['token'] ) ? (string) $args['token'] : '';
$_180c_value  = isset( $args['value'] ) ? (string) $args['value'] : '';
$_180c_sample = isset( $args['sample'] ) ? (string) $args['sample'] : 'Aa';
$_180c_style  = isset( $args['style'] ) ? (string) $args['style'] : '';
?>

<figure class="ds-specimen">
	<div class="ds-specimen__render" style="<?php echo esc_attr( $_180c_style ); ?>"><?php echo esc_html( $_180c_sample ); ?></div>
	<figcaption class="ds-specimen__caption">
		<?php if ( '' !== $_180c_token ) : ?>
			<code class="ds-specimen__token"><?php echo esc_html( $_180c_token ); ?></code>
		<?php endif; ?>
		<?php if ( '' !== $_180c_value ) : ?>
			<span class="ds-specimen__value"><?php echo esc_html( $_180c_value ); ?></span>
		<?php endif; ?>
	</figcaption>
</figure>
