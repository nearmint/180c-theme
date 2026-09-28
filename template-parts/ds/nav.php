<?php
/**
 * Design System — navigation latérale globale.
 *
 * Liens vers les 3 pages (Welcome / Foundations / Components), résolus par
 * chemin. La page active expose en plus ses ancres de section (groupes de
 * tokens pour Foundations, lots de composants pour Components) — jump links
 * vers les id="ds-…" / "ds-lot-…" rendus dans le contenu.
 *
 * @var array $args { current: 'welcome'|'foundations'|'components' }
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

$_180c_ds_current = isset( $args['current'] ) ? (string) $args['current'] : 'welcome';

// Résolution des pages par chemin (parent + enfants).
$_180c_ds_pages = array(
	'welcome'     => array(
		'label' => __( 'Welcome', '180c' ),
		'page'  => get_page_by_path( 'design-system' ),
	),
	'foundations' => array(
		'label' => __( 'Foundations', '180c' ),
		'page'  => get_page_by_path( 'design-system/foundations' ),
	),
	'components'  => array(
		'label' => __( 'Components', '180c' ),
		'page'  => get_page_by_path( 'design-system/components' ),
	),
);

/**
 * Ancres de section de la page courante.
 *
 * @param string $key Clé de page courante.
 * @return array<string,string> id d'ancre => libellé.
 */
$_180c_ds_anchors = static function ( $key ) {
	$anchors = array();

	if ( 'foundations' === $key && function_exists( '_180c_ds_parse_tokens' ) ) {
		foreach ( _180c_ds_parse_tokens() as $group ) {
			$anchors[ 'ds-' . $group['key'] ] = $group['title'];
		}
	} elseif ( 'components' === $key && function_exists( '_180c_ds_component_lots' ) ) {
		foreach ( _180c_ds_component_lots() as $slug => $title ) {
			$anchors[ 'ds-lot-' . $slug ] = $title;
		}
	}

	return $anchors;
};
?>

<nav class="ds-nav" aria-label="<?php esc_attr_e( 'Design System', '180c' ); ?>">
	<ul class="ds-nav__list">
		<?php
		foreach ( $_180c_ds_pages as $_180c_ds_key => $_180c_ds_entry ) :
			$_180c_ds_url    = $_180c_ds_entry['page'] instanceof WP_Post ? get_permalink( $_180c_ds_entry['page'] ) : '#';
			$_180c_ds_active = ( $_180c_ds_key === $_180c_ds_current );
			?>
			<li class="ds-nav__item">
				<a
					class="ds-nav__link<?php echo $_180c_ds_active ? ' ds-nav__link--active' : ''; ?>"
					href="<?php echo esc_url( $_180c_ds_url ); ?>"
					<?php echo $_180c_ds_active ? 'aria-current="page"' : ''; ?>
				><?php echo esc_html( $_180c_ds_entry['label'] ); ?></a>

				<?php
				$_180c_ds_sub = $_180c_ds_active ? $_180c_ds_anchors( $_180c_ds_key ) : array();
				if ( ! empty( $_180c_ds_sub ) ) :
					?>
					<ul class="ds-nav__sub">
						<?php foreach ( $_180c_ds_sub as $_180c_ds_anchor_id => $_180c_ds_anchor_label ) : ?>
							<li class="ds-nav__sub-item">
								<a class="ds-nav__sub-link" href="#<?php echo esc_attr( $_180c_ds_anchor_id ); ?>"><?php echo esc_html( $_180c_ds_anchor_label ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
