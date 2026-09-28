<?php
/**
 * Bloc acf/coordonnees-lieu — rendu serveur (template ACF).
 *
 * Contexte ACF disponible : $block, $content, $is_preview, $post_id.
 * Les valeurs de champs sont lues via get_field() (résolues depuis les
 * données du bloc, y compris en rendu via render_block()).
 *
 * Le JSON-LD du lieu n'est PAS émis ici : il est injecté dans le @graph
 * unique de la page (wp_head) par inc/coordonnees-lieu.php via le filtre
 * `180c/schema_graph`, afin de préserver l'invariant « un seul bloc JSON-LD
 * par page ».
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$cl_nom        = (string) get_field( 'nom' );
$cl_complement = (string) get_field( 'complement' );
$cl_type       = (string) get_field( 'type_lieu' );
$cl_adresse    = (string) get_field( 'adresse' );
$cl_pays       = (string) get_field( 'pays' );
$cl_telephones = (array) get_field( 'telephones' );
$cl_email      = (string) get_field( 'email' );
$cl_site_url   = (string) get_field( 'site_url' );
$cl_site_label = (string) get_field( 'site_libelle' );
$cl_reseaux    = (array) get_field( 'reseaux' );

$cl_is_preview = ! empty( $is_preview );

// Placeholder éditeur quand le bloc est vide.
if ( '' === $cl_nom && '' === $cl_adresse && empty( $cl_telephones ) ) {
	if ( $cl_is_preview ) {
		echo '<aside class="block-180c-coordonnees-lieu block-180c-coordonnees-lieu--placeholder">'
			. esc_html__( 'Coordonnées du lieu : renseignez les champs dans les réglages du bloc.', '180c' )
			. '</aside>';
	}
	return;
}

// Attributs de wrapper (anchor + className éventuels).
$cl_classes = 'block-180c-coordonnees-lieu';
if ( ! empty( $block['className'] ) ) {
	$cl_classes .= ' ' . $block['className'];
}
$cl_anchor = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

// Pays affiché seulement s'il diffère de France (sinon redondant).
$cl_show_country = ( '' !== $cl_pays && 0 !== strcasecmp( $cl_pays, 'France' ) );
?>
<aside class="<?php echo esc_attr( $cl_classes ); ?>"<?php echo $cl_anchor; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php esc_attr_e( 'Coordonnées du lieu', '180c' ); ?>">

	<?php if ( '' !== $cl_type ) : ?>
		<p class="block-180c-coordonnees-lieu__type">
			<span class="block-180c-coordonnees-lieu__type-icon" aria-hidden="true"><?php echo _180c_cl_type_icon( $cl_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php echo esc_html( _180c_cl_type_label( $cl_type ) ); ?>
		</p>
	<?php endif; ?>

	<?php if ( '' !== $cl_nom ) : ?>
		<h3 class="block-180c-coordonnees-lieu__nom">
			<?php echo esc_html( $cl_nom ); ?>
			<?php if ( '' !== $cl_complement ) : ?>
				<span class="block-180c-coordonnees-lieu__complement"><?php echo esc_html( $cl_complement ); ?></span>
			<?php endif; ?>
		</h3>
	<?php endif; ?>

	<?php if ( '' !== $cl_adresse ) : ?>
		<address class="block-180c-coordonnees-lieu__adresse">
			<?php
			echo nl2br( esc_html( $cl_adresse ) );
			if ( $cl_show_country ) {
				echo '<br>' . esc_html( $cl_pays );
			}
			?>
		</address>
	<?php endif; ?>

	<?php if ( ! empty( $cl_telephones ) || '' !== $cl_email || '' !== $cl_site_url ) : ?>
		<ul class="block-180c-coordonnees-lieu__contacts">
			<?php
			foreach ( $cl_telephones as $cl_tel ) {
				$cl_num = isset( $cl_tel['numero'] ) ? trim( (string) $cl_tel['numero'] ) : '';
				if ( '' === $cl_num ) {
					continue;
				}
				$cl_lib  = isset( $cl_tel['libelle'] ) ? trim( (string) $cl_tel['libelle'] ) : '';
				$cl_href = _180c_cl_tel_to_intl( $cl_num, $cl_pays );
				?>
				<li class="block-180c-coordonnees-lieu__contact block-180c-coordonnees-lieu__tel">
					<?php if ( '' !== $cl_lib ) : ?>
						<span class="block-180c-coordonnees-lieu__tel-label"><?php echo esc_html( $cl_lib ); ?></span>
					<?php endif; ?>
					<a href="<?php echo esc_attr( 'tel:' . $cl_href ); ?>"><?php echo esc_html( $cl_num ); ?></a>
				</li>
				<?php
			}
			?>

			<?php if ( '' !== $cl_email ) : ?>
				<li class="block-180c-coordonnees-lieu__contact block-180c-coordonnees-lieu__email">
					<a href="<?php echo esc_attr( 'mailto:' . $cl_email ); ?>"><?php echo esc_html( $cl_email ); ?></a>
				</li>
			<?php endif; ?>

			<?php if ( '' !== $cl_site_url ) : ?>
				<li class="block-180c-coordonnees-lieu__contact block-180c-coordonnees-lieu__site">
					<a href="<?php echo esc_url( $cl_site_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( '' !== $cl_site_label ? $cl_site_label : _180c_cl_site_label( $cl_site_url ) ); ?>
					</a>
				</li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>

	<?php if ( ! empty( $cl_reseaux ) ) : ?>
		<ul class="block-180c-coordonnees-lieu__reseaux">
			<?php
			foreach ( $cl_reseaux as $cl_reseau ) {
				$cl_url = isset( $cl_reseau['url'] ) ? trim( (string) $cl_reseau['url'] ) : '';
				if ( '' === $cl_url ) {
					continue;
				}
				$cl_meta = _180c_cl_reseau_meta( $cl_url );
				?>
				<li class="block-180c-coordonnees-lieu__reseau">
					<a href="<?php echo esc_url( $cl_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $cl_meta['label'] ); ?>">
						<?php echo $cl_meta['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</li>
				<?php
			}
			?>
		</ul>
	<?php endif; ?>

</aside>
