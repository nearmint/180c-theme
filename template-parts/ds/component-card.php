<?php
/**
 * Design System — partial « component-card ».
 *
 * Fiche d'un composant : titre, badge (live/snapshot), bandeau legacy
 * optionnel, aperçu rendu (vrai partial BEM ou snapshot statique), classe BEM
 * racine, fichier source, variantes, note d'usage.
 *
 * @var array $args {
 *   @type string $name     Nom du composant.
 *   @type string $bem      Classe BEM racine (ex. .card-180c).
 *   @type string $file     Chemin source (partial PHP ou CSS).
 *   @type array  $variants Liste des variantes/modifiers.
 *   @type string $note     Note d'usage (optionnel).
 *   @type string $preview  HTML d'aperçu déjà rendu (vrai partial ou snapshot).
 *   @type string $mode     'live' (vrai partial) ou 'snapshot' (HTML statique).
 *   @type string $legacy   Message de bandeau « legacy » (optionnel) ; si non
 *                          vide, affiche un bandeau d'avertissement.
 * }
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_name     = isset( $args['name'] ) ? (string) $args['name'] : '';
$_180c_bem      = isset( $args['bem'] ) ? (string) $args['bem'] : '';
$_180c_file     = isset( $args['file'] ) ? (string) $args['file'] : '';
$_180c_variants = isset( $args['variants'] ) ? (array) $args['variants'] : array();
$_180c_note     = isset( $args['note'] ) ? (string) $args['note'] : '';
$_180c_preview  = isset( $args['preview'] ) ? (string) $args['preview'] : '';
$_180c_mode     = isset( $args['mode'] ) ? (string) $args['mode'] : '';
$_180c_legacy   = isset( $args['legacy'] ) ? (string) $args['legacy'] : '';

if ( '' === $_180c_name ) {
	return;
}

$_180c_anchor = sanitize_title( $_180c_name );
?>

<article class="ds-component<?php echo '' !== $_180c_legacy ? ' ds-component--legacy' : ''; ?>" id="ds-c-<?php echo esc_attr( $_180c_anchor ); ?>">
	<header class="ds-component__header">
		<h3 class="ds-component__name"><?php echo esc_html( $_180c_name ); ?></h3>
		<?php if ( 'snapshot' === $_180c_mode || 'live' === $_180c_mode ) : ?>
			<span class="ds-component__badge ds-component__badge--<?php echo esc_attr( $_180c_mode ); ?>">
				<?php echo 'live' === $_180c_mode ? esc_html__( 'live', '180c' ) : esc_html__( 'snapshot', '180c' ); ?>
			</span>
		<?php endif; ?>
	</header>

	<?php if ( '' !== $_180c_legacy ) : ?>
		<p class="ds-component__legacy" role="note">⚠ <?php echo esc_html( $_180c_legacy ); ?></p>
	<?php endif; ?>

	<?php if ( '' !== $_180c_preview ) : ?>
		<div class="ds-component__preview">
			<?php
			// Aperçu : markup déjà rendu en amont (vrai partial BEM ou snapshot
			// statique préparé côté serveur), volontairement non ré-échappé.
			echo $_180c_preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
	<?php endif; ?>

	<dl class="ds-component__meta">
		<?php if ( '' !== $_180c_bem ) : ?>
			<dt><?php esc_html_e( 'Classe racine', '180c' ); ?></dt>
			<dd><code><?php echo esc_html( $_180c_bem ); ?></code></dd>
		<?php endif; ?>
		<?php if ( '' !== $_180c_file ) : ?>
			<dt><?php esc_html_e( 'Source', '180c' ); ?></dt>
			<dd><code><?php echo esc_html( $_180c_file ); ?></code></dd>
		<?php endif; ?>
		<?php if ( ! empty( $_180c_variants ) ) : ?>
			<dt><?php esc_html_e( 'Variantes', '180c' ); ?></dt>
			<dd>
				<?php foreach ( $_180c_variants as $_180c_variant ) : ?>
					<code class="ds-component__variant"><?php echo esc_html( (string) $_180c_variant ); ?></code>
				<?php endforeach; ?>
			</dd>
		<?php endif; ?>
	</dl>

	<?php if ( '' !== $_180c_note ) : ?>
		<p class="ds-component__note"><?php echo esc_html( $_180c_note ); ?></p>
	<?php endif; ?>
</article>
