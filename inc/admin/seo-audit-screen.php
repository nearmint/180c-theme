<?php
/**
 * Admin — Page « Outils → Audit SEO (ACF) ».
 *
 * Écran **temporaire** et **strictement en lecture**. Il relève, avant la
 * suppression du groupe ACF « SEO », ce que les quatre champs
 * (`seo_title`, `seo_description`, `og_image_override`, `no_index`) portent
 * réellement en production.
 *
 * Pourquoi il existe : `no_index` est un contrôle éditorial. Le jour où le code
 * cesse de le lire, toute page volontairement désindexée redevient indexable
 * **en silence**. La base locale ne reflète pas la production : ce relevé doit
 * être fait sur le serveur, et lu, avant que le groupe ne soit supprimé dans
 * wp-admin. `og_image_override` pose le même problème, en moins grave.
 *
 * Tout est concentré dans ce seul fichier pour qu'un unique `git revert` du
 * commit qui l'introduit suffise à le retirer une fois l'arbitrage clos.
 *
 * **Aucune écriture.** Aucun `update_*`, `delete_*`, `add_*`, `INSERT`,
 * `UPDATE` ni `DELETE`. L'export CSV est produit côté navigateur, sans fichier
 * écrit sur le serveur.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

const _180C_SEO_AUDIT_SLUG = '180c-seo-audit';

/**
 * Nombre maximal de lignes affichées.
 *
 * Garde-fou mémoire. Le compteur de synthèse, lui, porte sur la totalité :
 * si la troncature s'applique, l'écran le dit explicitement plutôt que de
 * laisser croire à un relevé exhaustif.
 *
 * @var int
 */
const _180C_SEO_AUDIT_MAX_ROWS = 2000;

/**
 * Les quatre champs du groupe ACF `group_content_seo`.
 *
 * @return array<int, string>
 */
function _180c_seo_audit_fields(): array {
	return array( 'seo_title', 'seo_description', 'og_image_override', 'no_index' );
}

/**
 * Enregistre la page sous le menu « Outils ».
 *
 * @return void
 */
function _180c_seo_audit_register_page(): void {
	add_submenu_page(
		'tools.php',
		__( 'Audit SEO (ACF)', '180c' ),
		__( 'Audit SEO (ACF)', '180c' ),
		'manage_options',
		_180C_SEO_AUDIT_SLUG,
		'_180c_seo_audit_render_page'
	);
}
add_action( 'admin_menu', '_180c_seo_audit_register_page' );

/**
 * Relève les valeurs des quatre champs, par post.
 *
 * Deux populations distinctes sont comptées :
 *
 *  - **brut** : toutes les lignes de `postmeta`, révisions comprises ;
 *  - **effectif** : hors `post_type = 'revision'` et hors
 *    `post_status = 'inherit'`.
 *
 * WordPress recopie les meta sur les révisions. Mesuré en local : 22 valeurs
 * `seo_title` non vides, dont **11 sur des révisions** — la moitié du volume
 * ne rend jamais rien. Afficher les deux chiffres garde l'écart visible au
 * lieu de le masquer derrière un filtre silencieux.
 *
 * @return array{rows:array, totals:array, raw_totals:array, truncated:bool}
 */
function _180c_seo_audit_collect(): array {
	global $wpdb;

	$fields       = _180c_seo_audit_fields();
	$placeholders = implode( ', ', array_fill( 0, count( $fields ), '%s' ) );

	// Comptage brut (révisions incluses), par champ.
	$raw_totals = array_fill_keys( $fields, 0 );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders n'est qu'une suite de %s construite depuis un tableau littéral ; toutes les valeurs passent par prepare(). Les noms de tables viennent de $wpdb.
	$raw_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT meta_key, COUNT(*) AS n
			 FROM {$wpdb->postmeta}
			 WHERE meta_key IN ( $placeholders )
			   AND meta_value NOT IN ( '', '0' )
			 GROUP BY meta_key",
			...$fields
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	foreach ( (array) $raw_rows as $raw_row ) {
		$raw_totals[ $raw_row->meta_key ] = (int) $raw_row->n;
	}

	// Relevé effectif, une ligne par post.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders n'est qu'une suite de %s construite depuis un tableau littéral ; toutes les valeurs passent par prepare(). Les noms de tables viennent de $wpdb.
	$records = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_status, p.post_title
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key IN ( $placeholders )
			   AND pm.meta_value NOT IN ( '', '0' )
			   AND p.post_type <> 'revision'
			   AND p.post_status <> 'inherit'
			 ORDER BY pm.post_id",
			...$fields
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$rows = array();
	foreach ( (array) $records as $record ) {
		$post_id = (int) $record->post_id;

		if ( ! isset( $rows[ $post_id ] ) ) {
			$rows[ $post_id ] = array(
				'post_id'           => $post_id,
				'post_type'         => (string) $record->post_type,
				'post_status'       => (string) $record->post_status,
				'post_title'        => (string) $record->post_title,
				'permalink'         => (string) get_permalink( $post_id ),
				'seo_title'         => '',
				'seo_description'   => '',
				'og_image_override' => '',
				'no_index'          => '',
			);
		}

		$rows[ $post_id ][ $record->meta_key ] = (string) $record->meta_value;
	}

	// Mise en forme des deux champs qui ne s'affichent pas bruts.
	$totals = array_fill_keys( $fields, 0 );
	foreach ( $rows as $post_id => $row ) {
		if ( '' !== $row['og_image_override'] ) {
			$attachment_id                         = (int) $row['og_image_override'];
			$file                                  = $attachment_id ? get_post_meta( $attachment_id, '_wp_attached_file', true ) : '';
			$rows[ $post_id ]['og_image_override'] = $attachment_id
				? sprintf( '%d — %s', $attachment_id, $file ? basename( (string) $file ) : __( '(fichier introuvable)', '180c' ) )
				: '';
		}

		if ( '' !== $row['no_index'] ) {
			$rows[ $post_id ]['no_index'] = ( '1' === $row['no_index'] || 'true' === $row['no_index'] ) ? 'oui' : '';
		}

		foreach ( $fields as $field ) {
			if ( '' !== $rows[ $post_id ][ $field ] ) {
				++$totals[ $field ];
			}
		}
	}

	// Une ligne peut n'avoir plus aucune valeur après normalisation (no_index à
	// « 0 » déguisé) : on ne la garde pas.
	$rows = array_values(
		array_filter(
			$rows,
			static function ( array $row ): bool {
				foreach ( _180c_seo_audit_fields() as $field ) {
					if ( '' !== $row[ $field ] ) {
						return true;
					}
				}
				return false;
			}
		)
	);

	$truncated = count( $rows ) > _180C_SEO_AUDIT_MAX_ROWS;
	if ( $truncated ) {
		$rows = array_slice( $rows, 0, _180C_SEO_AUDIT_MAX_ROWS );
	}

	return array(
		'rows'       => $rows,
		'totals'     => $totals,
		'raw_totals' => $raw_totals,
		'truncated'  => $truncated,
	);
}

/**
 * Relève les mêmes champs côté `termmeta`.
 *
 * Le groupe `group_content_seo` est attaché à quatre `post_type` (`recipe`,
 * `post`, `page`, `product`) et à **aucune taxonomie** — vérifié dans
 * `acf-json/group_content_seo.json`. Aucune term meta ne devrait donc exister.
 *
 * La section est rendue quand même : une location ACF a pu être modifiée en
 * base sans que le JSON local le reflète, et la production n'est pas la copie
 * locale. Un tableau vide est ici une information, pas une absence de contrôle.
 *
 * @return array<int, object>
 */
function _180c_seo_audit_collect_terms(): array {
	global $wpdb;

	$fields       = _180c_seo_audit_fields();
	$placeholders = implode( ', ', array_fill( 0, count( $fields ), '%s' ) );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders n'est qu'une suite de %s construite depuis un tableau littéral ; toutes les valeurs passent par prepare(). Les noms de tables viennent de $wpdb.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tm.term_id, tm.meta_key, tm.meta_value, t.name, tt.taxonomy
			 FROM {$wpdb->termmeta} tm
			 INNER JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
			 WHERE tm.meta_key IN ( $placeholders )
			   AND tm.meta_value NOT IN ( '', '0' )
			 ORDER BY tt.taxonomy, t.name",
			...$fields
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
}

/**
 * Rend la page d'audit.
 *
 * @return void
 */
function _180c_seo_audit_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', '180c' ) );
	}

	$data      = _180c_seo_audit_collect();
	$term_rows = _180c_seo_audit_collect_terms();
	$labels    = array(
		'seo_title'         => __( 'Titre SEO', '180c' ),
		'seo_description'   => __( 'Meta description', '180c' ),
		'og_image_override' => __( 'Image OG', '180c' ),
		'no_index'          => __( 'Ne pas indexer', '180c' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Audit SEO (ACF) — lecture seule', '180c' ); ?></h1>

		<div class="notice notice-info inline">
			<p>
				<strong><?php esc_html_e( 'Cet écran n’écrit rien.', '180c' ); ?></strong>
				<?php esc_html_e( 'Il relève ce que portent les quatre champs du groupe ACF « SEO » avant sa suppression. À lire — et à exporter — avant de supprimer le groupe dans wp-admin : une fois supprimé, les valeurs de no_index ne sont plus visibles nulle part, et les pages volontairement désindexées redeviendraient indexables sans que rien ne le signale.', '180c' ); ?>
			</p>
		</div>

		<h2><?php esc_html_e( 'Synthèse', '180c' ); ?></h2>
		<table class="widefat striped" style="max-width:820px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Champ', '180c' ); ?></th>
					<th><?php esc_html_e( 'Posts concernés (effectif)', '180c' ); ?></th>
					<th><?php esc_html_e( 'Lignes en base (brut, révisions incluses)', '180c' ); ?></th>
					<th><?php esc_html_e( 'Écart', '180c' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( _180c_seo_audit_fields() as $field ) : ?>
				<?php
				$effective = (int) $data['totals'][ $field ];
				$raw       = (int) $data['raw_totals'][ $field ];
				?>
				<tr>
					<td><code><?php echo esc_html( $field ); ?></code> — <?php echo esc_html( $labels[ $field ] ); ?></td>
					<td><strong><?php echo (int) $effective; ?></strong></td>
					<td><?php echo (int) $raw; ?></td>
					<td><?php echo esc_html( $raw - $effective > 0 ? sprintf( '+%d (révisions)', $raw - $effective ) : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e( 'Seule la colonne « effectif » compte : WordPress recopie les meta sur les révisions, qui ne rendent jamais de page. L’écart est affiché pour que le filtrage reste vérifiable.', '180c' ); ?>
		</p>

		<?php if ( $data['truncated'] ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php
					printf(
						/* translators: %d : nombre maximal de lignes affichées. */
						esc_html__( 'Affichage tronqué aux %d premières lignes. Les compteurs de synthèse ci-dessus, eux, portent sur la totalité.', '180c' ),
						(int) _180C_SEO_AUDIT_MAX_ROWS
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Détail par contenu', '180c' ); ?></h2>
		<p>
			<button type="button" class="button button-primary" id="180c-seo-audit-export">
				<?php esc_html_e( 'Exporter en CSV', '180c' ); ?>
			</button>
			<span class="description"><?php esc_html_e( 'Export généré par le navigateur — aucun fichier n’est écrit sur le serveur.', '180c' ); ?></span>
		</p>

		<table class="widefat striped" id="180c-seo-audit-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', '180c' ); ?></th>
					<th><?php esc_html_e( 'Type', '180c' ); ?></th>
					<th><?php esc_html_e( 'Statut', '180c' ); ?></th>
					<th><?php esc_html_e( 'Titre', '180c' ); ?></th>
					<th><?php esc_html_e( 'URL', '180c' ); ?></th>
					<th><?php esc_html_e( 'seo_title', '180c' ); ?></th>
					<th><?php esc_html_e( 'seo_description', '180c' ); ?></th>
					<th><?php esc_html_e( 'og_image_override', '180c' ); ?></th>
					<th><?php esc_html_e( 'no_index', '180c' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $data['rows'] ) : ?>
				<tr><td colspan="9"><?php esc_html_e( 'Aucune valeur renseignée sur ce site.', '180c' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $data['rows'] as $row ) : ?>
				<tr<?php echo '' !== $row['no_index'] ? ' style="background:#fcf0f1"' : ''; ?>>
					<td><?php echo (int) $row['post_id']; ?></td>
					<td><?php echo esc_html( $row['post_type'] ); ?></td>
					<td><?php echo esc_html( $row['post_status'] ); ?></td>
					<td><?php echo esc_html( $row['post_title'] ); ?></td>
					<td><a href="<?php echo esc_url( $row['permalink'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['permalink'] ); ?></a></td>
					<td><?php echo esc_html( $row['seo_title'] ); ?></td>
					<td><?php echo esc_html( mb_strimwidth( $row['seo_description'], 0, 80, '…' ) ); ?></td>
					<td><?php echo esc_html( $row['og_image_override'] ); ?></td>
					<td><?php echo '' !== $row['no_index'] ? '<strong>oui</strong>' : ''; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Term meta', '180c' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Le groupe ACF « SEO » est attaché à quatre types de contenu (recipe, post, page, product) et à aucune taxonomie — vérifié dans acf-json/group_content_seo.json. Ce tableau doit donc être vide. Il est affiché quand même : une location ACF a pu être modifiée en base sans que le JSON local le reflète.', '180c' ); ?>
		</p>
		<table class="widefat striped" style="max-width:820px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'term_id', '180c' ); ?></th>
					<th><?php esc_html_e( 'Taxonomie', '180c' ); ?></th>
					<th><?php esc_html_e( 'Terme', '180c' ); ?></th>
					<th><?php esc_html_e( 'Champ', '180c' ); ?></th>
					<th><?php esc_html_e( 'Valeur', '180c' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $term_rows ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'Aucune term meta — conforme à l’attendu.', '180c' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $term_rows as $term_row ) : ?>
				<tr>
					<td><?php echo (int) $term_row->term_id; ?></td>
					<td><?php echo esc_html( $term_row->taxonomy ); ?></td>
					<td><?php echo esc_html( $term_row->name ); ?></td>
					<td><code><?php echo esc_html( $term_row->meta_key ); ?></code></td>
					<td><?php echo esc_html( mb_strimwidth( (string) $term_row->meta_value, 0, 80, '…' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<script type="application/json" id="180c-seo-audit-data">
			<?php echo wp_json_encode( $data['rows'] ); ?>
		</script>
		<script>
		( function () {
			var btn = document.getElementById( '180c-seo-audit-export' );
			if ( ! btn ) { return; }
			btn.addEventListener( 'click', function () {
				var node = document.getElementById( '180c-seo-audit-data' );
				var rows = JSON.parse( node.textContent || '[]' );
				var cols = [ 'post_id', 'post_type', 'post_status', 'post_title', 'permalink', 'seo_title', 'seo_description', 'og_image_override', 'no_index' ];
				var esc  = function ( v ) { return '"' + String( v === null ? '' : v ).replace( /"/g, '""' ) + '"'; };
				var csv  = [ cols.join( ',' ) ];
				rows.forEach( function ( r ) {
					csv.push( cols.map( function ( c ) { return esc( r[ c ] ); } ).join( ',' ) );
				} );
				// ﻿ : BOM UTF-8, sans quoi Excel casse les accents.
				var blob = new Blob( [ '﻿' + csv.join( '\r\n' ) ], { type: 'text/csv;charset=utf-8;' } );
				var a    = document.createElement( 'a' );
				a.href     = URL.createObjectURL( blob );
				a.download = 'seo-acf-audit.csv';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( a.href );
			} );
		}() );
		</script>
	</div>
	<?php
}
