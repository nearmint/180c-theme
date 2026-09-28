<?php
/**
 * Admin — champ « Qualificatif SEO » natif sur les termes.
 *
 * Remplace la saisie que fournissait le groupe ACF « SEO ». Le qualificatif est
 * la partie droite du title : `{Sujet} — {Qualificatif} · 180°C`.
 *
 * La term meta écrite ici (`_180c_seo_qualifier`) est déjà consommée par
 * `_180c_title_term_entry()` (inc/seo/title-map.php), dont la chaîne de
 * résolution est : **term meta → mapping PHP en dur → repli de taxonomie**.
 * Le mapping en dur garantit donc qu'aucune suppression de saisie ne dégrade
 * un title : ce champ ne fait qu'ouvrir la main à la rédaction.
 *
 * Les taxonomies concernées viennent de `_180c_title_qualifier_meta_taxonomies()`
 * — la **même** fonction que celle qui décide, côté rendu, quelles taxonomies
 * lisent la meta. Écran d'édition et moteur de rendu ne peuvent pas diverger :
 * un champ affiché est un champ lu.
 *
 * Choix assumé : on n'utilise **pas** la description du terme comme
 * qualificatif. C'est un texte long, affiché en tête de page catégorie ; il
 * produirait un title d'un paragraphe. La description sert à la meta
 * description (inc/seo/meta-tags.php), pas au title.
 *
 * @package 180c
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clé de la term meta.
 *
 * @var string
 */
const _180C_TERM_QUALIFIER_META = '_180c_seo_qualifier';

/**
 * Longueur maximale du qualificatif.
 *
 * Le title vise 60 caractères suffixe compris. Au-delà de 40 pour le seul
 * qualificatif, `_180c_build_title()` le laisse tomber à l'assemblage : la
 * saisie serait perdue sans que rien ne le dise. La limite est donc posée à
 * la saisie, là où elle est visible.
 *
 * @var int
 */
const _180C_TERM_QUALIFIER_MAXLENGTH = 40;

/**
 * Capacité requise pour éditer le qualificatif d'une taxonomie.
 *
 * Pour `category`, `edit_terms` vaut `manage_categories`. Passer par la
 * capacité déclarée de la taxonomie plutôt que par une constante en dur évite
 * un contrôle faux le jour où le filtre `180c/title_qualifier_meta_taxonomies`
 * ajoutera une taxonomie aux capacités différentes.
 *
 * @param string $taxonomy Nom de la taxonomie.
 * @return string
 */
function _180c_term_qualifier_capability( string $taxonomy ): string {
	$object = get_taxonomy( $taxonomy );

	return ( $object && isset( $object->cap->edit_terms ) )
		? (string) $object->cap->edit_terms
		: 'manage_categories';
}

/**
 * Branche le champ sur les taxonomies autorisées.
 *
 * @return void
 */
function _180c_term_qualifier_register(): void {
	if ( ! function_exists( '_180c_title_qualifier_meta_taxonomies' ) ) {
		return;
	}

	foreach ( _180c_title_qualifier_meta_taxonomies() as $taxonomy ) {
		$taxonomy = (string) $taxonomy;
		add_action( $taxonomy . '_add_form_fields', '_180c_term_qualifier_add_field' );
		add_action( $taxonomy . '_edit_form_fields', '_180c_term_qualifier_edit_field', 10, 2 );
		add_action( 'created_' . $taxonomy, '_180c_term_qualifier_save' );
		add_action( 'edited_' . $taxonomy, '_180c_term_qualifier_save' );
	}
}
add_action( 'admin_init', '_180c_term_qualifier_register' );

/**
 * Rend l'aide affichée sous le champ.
 *
 * @param string $taxonomy Nom de la taxonomie.
 * @param int    $term_id  ID du terme, 0 à la création.
 * @return void
 */
function _180c_term_qualifier_help( string $taxonomy, int $term_id = 0 ): void {
	$preview = '';

	if ( $term_id ) {
		$term = get_term( $term_id, $taxonomy );
		if ( $term instanceof WP_Term && function_exists( '_180c_title_term_entry' ) ) {
			$entry   = _180c_title_term_entry( $term );
			$preview = function_exists( '_180c_build_title' )
				? _180c_build_title( $entry['subject'], $entry['qualifier'] )
				: '';
		}
	}
	?>
	<p class="description">
		<?php
		printf(
			/* translators: %d : nombre maximal de caractères. */
			esc_html__( 'Partie droite du title, après le tiret cadratin : « Sujet — Qualificatif · 180°C ». %d caractères maximum. Laisser vide pour utiliser la valeur par défaut définie dans le thème.', '180c' ),
			(int) _180C_TERM_QUALIFIER_MAXLENGTH
		);
		?>
		<span id="180c-qualifier-count" aria-live="polite"></span>
	</p>
	<?php if ( '' !== $preview ) : ?>
		<p class="description">
			<strong><?php esc_html_e( 'Title actuel :', '180c' ); ?></strong>
			<code><?php echo esc_html( $preview ); ?></code>
		</p>
	<?php endif; ?>
	<script>
	( function () {
		var input = document.getElementById( '180c-seo-qualifier' );
		var out   = document.getElementById( '180c-qualifier-count' );
		if ( ! input || ! out ) { return; }
		var max = <?php echo (int) _180C_TERM_QUALIFIER_MAXLENGTH; ?>;
		var render = function () {
			out.textContent = ' — ' + input.value.length + '/' + max;
			out.style.color = input.value.length > max ? '#b32d2e' : '';
		};
		input.addEventListener( 'input', render );
		render();
	}() );
	</script>
	<?php
}

/**
 * Champ sur l'écran de création d'un terme.
 *
 * @param string $taxonomy Nom de la taxonomie.
 * @return void
 */
function _180c_term_qualifier_add_field( $taxonomy ): void {
	$taxonomy = (string) $taxonomy;
	if ( ! current_user_can( _180c_term_qualifier_capability( $taxonomy ) ) ) {
		return;
	}
	wp_nonce_field( '180c_term_qualifier', '180c_term_qualifier_nonce' );
	?>
	<div class="form-field">
		<label for="180c-seo-qualifier"><?php esc_html_e( 'Qualificatif SEO', '180c' ); ?></label>
		<input
			type="text"
			id="180c-seo-qualifier"
			name="_180c_seo_qualifier"
			value=""
			maxlength="<?php echo (int) _180C_TERM_QUALIFIER_MAXLENGTH; ?>"
		>
		<?php _180c_term_qualifier_help( $taxonomy ); ?>
	</div>
	<?php
}

/**
 * Champ sur l'écran d'édition d'un terme.
 *
 * @param WP_Term $term     Terme édité.
 * @param string  $taxonomy Nom de la taxonomie.
 * @return void
 */
function _180c_term_qualifier_edit_field( $term, $taxonomy ): void {
	$taxonomy = (string) $taxonomy;
	if ( ! current_user_can( _180c_term_qualifier_capability( $taxonomy ) ) ) {
		return;
	}
	$value = ( $term instanceof WP_Term )
		? (string) get_term_meta( $term->term_id, _180C_TERM_QUALIFIER_META, true )
		: '';
	wp_nonce_field( '180c_term_qualifier', '180c_term_qualifier_nonce' );
	?>
	<tr class="form-field">
		<th scope="row">
			<label for="180c-seo-qualifier"><?php esc_html_e( 'Qualificatif SEO', '180c' ); ?></label>
		</th>
		<td>
			<input
				type="text"
				id="180c-seo-qualifier"
				name="_180c_seo_qualifier"
				value="<?php echo esc_attr( $value ); ?>"
				maxlength="<?php echo (int) _180C_TERM_QUALIFIER_MAXLENGTH; ?>"
				class="regular-text"
			>
			<?php _180c_term_qualifier_help( $taxonomy, ( $term instanceof WP_Term ) ? (int) $term->term_id : 0 ); ?>
		</td>
	</tr>
	<?php
}

/**
 * Enregistre le qualificatif.
 *
 * Une saisie vide **supprime** la meta plutôt que d'écrire une chaîne vide :
 * la chaîne de résolution retombe alors sur le mapping PHP, ce qui est le
 * comportement attendu quand on efface le champ.
 *
 * @param int $term_id ID du terme.
 * @return void
 */
function _180c_term_qualifier_save( $term_id ): void {
	$term_id = (int) $term_id;
	$term    = get_term( $term_id );

	if ( ! $term instanceof WP_Term ) {
		return;
	}

	if ( ! current_user_can( _180c_term_qualifier_capability( $term->taxonomy ) ) ) {
		return;
	}

	// Le champ peut être absent (édition rapide, appel programmatique) : dans ce
	// cas on ne touche à rien, sans quoi une quick-edit effacerait la saisie.
	if ( ! isset( $_POST['_180c_seo_qualifier'] ) ) {
		return;
	}

	if ( ! isset( $_POST['180c_term_qualifier_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['180c_term_qualifier_nonce'] ) ), '180c_term_qualifier' ) ) {
		return;
	}

	$value = sanitize_text_field( wp_unslash( $_POST['_180c_seo_qualifier'] ) );
	$value = trim( mb_substr( $value, 0, _180C_TERM_QUALIFIER_MAXLENGTH ) );

	if ( '' === $value ) {
		delete_term_meta( $term_id, _180C_TERM_QUALIFIER_META );
		return;
	}

	update_term_meta( $term_id, _180C_TERM_QUALIFIER_META, $value );
}
