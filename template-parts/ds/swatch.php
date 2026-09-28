<?php
/**
 * Design System — partial « swatch ».
 *
 * Pastille(s) de couleur + nom du token + valeurs. Affiche les variantes
 * light ET dark côte à côte (les valeurs sont parsées depuis les fichiers CSS,
 * jamais codées en dur). Si un alias est fourni, son expression source est
 * affichée (ex. var(--color-bg)).
 *
 * @var array $args {
 *   @type string $token Nom du token CSS (ex. --color-accent).
 *   @type string $light Valeur littérale en mode clair (ex. #FFFFFF).
 *   @type string $dark  Valeur littérale en mode sombre.
 *   @type string $value Expression source si alias (optionnel, ex. var(--color-bg)).
 * }
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_token = isset( $args['token'] ) ? (string) $args['token'] : '';
$_180c_light = isset( $args['light'] ) ? (string) $args['light'] : '';
$_180c_dark  = isset( $args['dark'] ) ? (string) $args['dark'] : '';
$_180c_alias = isset( $args['value'] ) ? (string) $args['value'] : '';

if ( '' === $_180c_token ) {
	return;
}

// N'affiche le second chip que si la valeur dark diffère de la light.
$_180c_has_dark = ( '' !== $_180c_dark && $_180c_dark !== $_180c_light );
?>

<figure class="ds-swatch">
	<div class="ds-swatch__chips">
		<span
			class="ds-swatch__chip ds-swatch__chip--light"
			style="background: <?php echo esc_attr( '' !== $_180c_light ? $_180c_light : 'var(' . $_180c_token . ')' ); ?>;"
			title="<?php echo esc_attr( $_180c_has_dark ? __( 'Light', '180c' ) : '' ); ?>"
			aria-hidden="true"
		></span>
		<?php if ( $_180c_has_dark ) : ?>
			<span
				class="ds-swatch__chip ds-swatch__chip--dark"
				style="background: <?php echo esc_attr( $_180c_dark ); ?>;"
				title="<?php esc_attr_e( 'Dark', '180c' ); ?>"
				aria-hidden="true"
			></span>
		<?php endif; ?>
	</div>
	<figcaption class="ds-swatch__caption">
		<code class="ds-swatch__token"><?php echo esc_html( $_180c_token ); ?></code>
		<?php if ( '' !== $_180c_alias ) : ?>
			<span class="ds-swatch__value ds-swatch__value--alias"><?php echo esc_html( $_180c_alias ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $_180c_light ) : ?>
			<span class="ds-swatch__value">
				<?php
				echo $_180c_has_dark
					? esc_html( sprintf( /* translators: 1: light value, 2: dark value. */ __( '%1$s (light) · %2$s (dark)', '180c' ), $_180c_light, $_180c_dark ) )
					: esc_html( $_180c_light );
				?>
			</span>
		<?php endif; ?>
	</figcaption>
</figure>
