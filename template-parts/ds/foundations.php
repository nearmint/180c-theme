<?php
/**
 * Design System — section Foundations.
 *
 * Rendu vivant des tokens depuis src/css/tokens.css + bloc @theme de main.css,
 * via _180c_ds_parse_tokens(). Source unique = fichiers CSS (aucune valeur en
 * dur dans ce template : tout vient du parseur).
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_ds_groups = function_exists( '_180c_ds_parse_tokens' ) ? _180c_ds_parse_tokens() : array();
?>

<header class="ds-section__header">
	<h1 class="ds-section__title"><?php esc_html_e( 'Foundations', '180c' ); ?></h1>
	<p class="ds-section__lead"><?php esc_html_e( 'Tokens design rendus en direct depuis tokens.css + le bloc @theme de main.css.', '180c' ); ?></p>
</header>

<?php if ( empty( $_180c_ds_groups ) ) : ?>
	<p class="ds-empty"><?php esc_html_e( 'Aucun token trouvé. Vérifier src/css/tokens.css et src/css/main.css.', '180c' ); ?></p>
<?php endif; ?>

<?php foreach ( $_180c_ds_groups as $_180c_ds_group ) : ?>
	<section class="ds-group" id="ds-<?php echo esc_attr( $_180c_ds_group['key'] ); ?>" aria-labelledby="ds-<?php echo esc_attr( $_180c_ds_group['key'] ); ?>-title">
		<h2 class="ds-group__title" id="ds-<?php echo esc_attr( $_180c_ds_group['key'] ); ?>-title"><?php echo esc_html( $_180c_ds_group['title'] ); ?></h2>

		<?php
		switch ( $_180c_ds_group['render'] ) {

			// ----- Couleurs : swatches light + dark. -----
			case 'color':
				echo '<div class="ds-grid ds-grid--swatch">';
				foreach ( $_180c_ds_group['rows'] as $_180c_ds_row ) {
					get_template_part(
						'template-parts/ds/swatch',
						null,
						array(
							'token' => $_180c_ds_row['token'],
							'light' => $_180c_ds_row['light'] ?? '',
							'dark'  => $_180c_ds_row['dark'] ?? '',
							'value' => $_180c_ds_row['value'] ?? '',
						)
					);
				}
				echo '</div>';
				break;

			// ----- Familles de polices : specimen pleine casse. -----
			case 'font':
				echo '<div class="ds-grid ds-grid--font">';
				foreach ( $_180c_ds_group['rows'] as $_180c_ds_row ) {
					get_template_part(
						'template-parts/ds/specimen',
						null,
						array(
							'token'  => $_180c_ds_row['token'],
							'value'  => $_180c_ds_row['value'],
							'sample' => 'Aa Bb Cc · 180°C · 1234567890',
							'style'  => 'font-family: var(' . $_180c_ds_row['token'] . '); font-size: var(--text-2xl);',
						)
					);
				}
				echo '</div>';
				break;

			// ----- Échelle typo : specimen démonstratif par taille. -----
			case 'scale':
				echo '<div class="ds-stack">';
				foreach ( $_180c_ds_group['rows'] as $_180c_ds_row ) {
					get_template_part(
						'template-parts/ds/specimen',
						null,
						array(
							'token'  => $_180c_ds_row['token'],
							'value'  => $_180c_ds_row['value'],
							'sample' => 'Le goût des bonnes choses',
							'style'  => 'font-size: var(' . $_180c_ds_row['token'] . '); line-height: var(--line-height-tight);',
						)
					);
				}
				echo '</div>';
				break;

			// ----- Espacements : barres dont la largeur = le token. -----
			case 'space':
				echo '<ul class="ds-spaces">';
				foreach ( $_180c_ds_group['rows'] as $_180c_ds_row ) {
					printf(
						'<li class="ds-space"><span class="ds-space__bar" style="inline-size: var(%1$s);" aria-hidden="true"></span><code class="ds-space__token">%2$s</code><span class="ds-space__value">%3$s</span></li>',
						esc_attr( $_180c_ds_row['token'] ),
						esc_html( $_180c_ds_row['token'] ),
						esc_html( $_180c_ds_row['value'] )
					);
				}
				echo '</ul>';
				break;

			// ----- Rayons : boîtes arrondies. -----
			case 'radius':
				echo '<div class="ds-grid ds-grid--box">';
				foreach ( $_180c_ds_group['rows'] as $_180c_ds_row ) {
					printf(
						'<figure class="ds-box"><span class="ds-box__demo" style="border-radius: var(%1$s);" aria-hidden="true"></span><figcaption><code>%2$s</code><span class="ds-box__value">%3$s</span></figcaption></figure>',
						esc_attr( $_180c_ds_row['token'] ),
						esc_html( $_180c_ds_row['token'] ),
						esc_html( $_180c_ds_row['value'] )
					);
				}
				echo '</div>';
				break;

			// ----- Ombres : boîtes avec box-shadow. -----
			case 'shadow':
				echo '<div class="ds-grid ds-grid--box">';
				foreach ( $_180c_ds_group['rows'] as $_180c_ds_row ) {
					printf(
						'<figure class="ds-box"><span class="ds-box__demo ds-box__demo--shadow" style="box-shadow: var(%1$s);" aria-hidden="true"></span><figcaption><code>%2$s</code><span class="ds-box__value">%3$s</span></figcaption></figure>',
						esc_attr( $_180c_ds_row['token'] ),
						esc_html( $_180c_ds_row['token'] ),
						esc_html( $_180c_ds_row['value'] )
					);
				}
				echo '</div>';
				break;

			// ----- Valeurs : tableau token / valeur. -----
			case 'value':
			default:
				echo '<table class="ds-table"><thead><tr><th scope="col">' . esc_html__( 'Token', '180c' ) . '</th><th scope="col">' . esc_html__( 'Valeur', '180c' ) . '</th></tr></thead><tbody>';
				foreach ( $_180c_ds_group['rows'] as $_180c_ds_row ) {
					printf(
						'<tr><td><code>%1$s</code></td><td><span class="ds-table__value">%2$s</span></td></tr>',
						esc_html( $_180c_ds_row['token'] ),
						esc_html( $_180c_ds_row['value'] )
					);
				}
				echo '</tbody></table>';
				break;
		}
		?>
	</section>
<?php endforeach; ?>
