<?php
/**
 * Help center markdown importer.
 *
 * @package 180c-theme
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once get_template_directory() . '/inc/lib/Parsedown.php';

/**
 * Imports markdown content into the help center ACF Repeater.
 */
class _180C_Help_Importer_Command {

	const PAGE_ID    = 13125475;
	const SOURCE_DIR = '/content/help/';

	/**
	 * Imports markdown files from /content/help/ into ACF help_sections.
	 *
	 * ATTENTION — commande DESTRUCTIVE. `update_field()` sur un repeater ACF
	 * ne fusionne pas : il REMPLACE l'intégralité de `help_sections`. Tout ce
	 * qui a été saisi en admin et qui n'a pas d'équivalent dans
	 * `/content/help/` est perdu sans confirmation ni sauvegarde.
	 *
	 * Au 2026-08-29 la source Markdown (4 fichiers) et la base (5 sections,
	 * 18 questions) ont divergé au point de n'avoir plus AUCUN identifiant de
	 * section en commun : un import aveugle détruirait la totalité du Centre
	 * d'aide publié. D'où la garde `--force` ci-dessous.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Parse et affiche le résultat sans rien écrire en base.
	 *
	 * [--force]
	 * : Confirme explicitement l'écrasement de `help_sections`. OBLIGATOIRE
	 * pour écrire. Sans ce drapeau, la commande affiche le différentiel des
	 * identifiants puis s'arrête sans rien modifier.
	 *
	 * ## EXAMPLES
	 *
	 *     # Sûr : parse et affiche, n'écrit rien.
	 *     wp 180c-help-import --dry-run
	 *
	 *     # Sûr : affiche ce qui SERAIT détruit, puis s'arrête.
	 *     wp 180c-help-import
	 *
	 *     # DESTRUCTIF : remplace le contenu de la page 13125475.
	 *     wp 180c-help-import --force
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$force   = ! empty( $assoc_args['force'] );
		$source  = get_template_directory() . self::SOURCE_DIR;

		if ( ! is_dir( $source ) ) {
			WP_CLI::error( "Source directory not found: $source" );
		}

		$files = glob( $source . '[0-9]*.md' );
		sort( $files );

		if ( empty( $files ) ) {
			WP_CLI::error( "No markdown files matching [0-9]*.md found in $source" );
		}

		if ( ! function_exists( 'update_field' ) ) {
			WP_CLI::error( 'ACF function update_field() not available. Is ACF Pro active?' );
		}

		$parsedown = new Parsedown();
		$sections  = array();

		foreach ( $files as $file ) {
			$section = $this->parse_file( $file, $parsedown );
			if ( $section ) {
				$sections[] = $section;
				WP_CLI::log(
					sprintf(
						'✓ %s — %d questions',
						$section['section_id'],
						count( $section['section_questions'] )
					)
				);
			}
		}

		if ( empty( $sections ) ) {
			WP_CLI::error( 'No sections parsed. Aborting.' );
		}

		// ------------------------------------------------------------------
		// Différentiel, calculé APRÈS le parsing (il a besoin des deux côtés)
		// et AVANT toute écriture. Affiché dans les trois modes : en --dry-run
		// pour informer, sans drapeau pour justifier le refus, et avec --force
		// parce que l'opérateur qui force doit voir ce qu'il détruit, pas
		// seulement l'affirmer.
		// ------------------------------------------------------------------
		$current   = $this->read_current_sections();
		$destroyed = null === $current ? null : $this->render_diff( $current, $sections );

		if ( $dry_run ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'DRY-RUN. Structure parsée :' );
			WP_CLI::log( '' );
			foreach ( $sections as $s ) {
				WP_CLI::log( '  ' . $s['section_id'] . ' / ' . $s['section_title'] );
				foreach ( $s['section_questions'] as $q ) {
					WP_CLI::log( '    - ' . $q['question_slug'] . ' (' . strlen( $q['question_answer'] ) . ' chars)' );
				}
			}
			if ( null === $destroyed ) {
				WP_CLI::warning( 'Contenu actuel illisible : le différentiel n\'a pas pu être calculé.' );
			}
			WP_CLI::success(
				sprintf(
					'Dry-run OK. %d sections, %d questions. Rien n\'a été écrit.',
					count( $sections ),
					array_sum( array_map( fn( $s ) => count( $s['section_questions'] ), $sections ) )
				)
			);
			return;
		}

		// ------------------------------------------------------------------
		// Garde anti-écrasement.
		// ------------------------------------------------------------------
		if ( null === $destroyed ) {
			// Impossible de lire l'existant : on ne peut pas énoncer ce qui
			// serait détruit, donc on refuse — même avec --force.
			WP_CLI::error(
				sprintf(
					'Impossible de lire le contenu actuel de la page %d. Refus d\'écrire à l\'aveugle, même avec --force.',
					self::PAGE_ID
				)
			);
		}

		if ( ! $force ) {
			WP_CLI::error(
				implode(
					PHP_EOL,
					array(
						'Écriture refusée : --force est requis.',
						sprintf(
							'Cet import REMPLACERAIT le champ help_sections de la page %d (un repeater ACF n\'est pas fusionné, il est remplacé).',
							self::PAGE_ID
						),
						sprintf(
							'%d question(s) actuellement publiée(s) seraient définitivement perdues.',
							count( $destroyed )
						),
						'Relancer avec --dry-run pour inspecter, ou avec --force pour assumer l\'écrasement.',
					)
				)
			);
		}

		$result = update_field( 'help_sections', $sections, self::PAGE_ID );

		if ( false === $result ) {
			WP_CLI::error( 'update_field() returned false. Page ID correct? ACF field group synchronized?' );
		}

		WP_CLI::success(
			sprintf(
				'%d sections importées sur la page %d.',
				count( $sections ),
				self::PAGE_ID
			)
		);
	}

	/**
	 * Lit la structure `help_sections` actuellement en base.
	 *
	 * Distingue trois cas, parce qu'ils n'appellent pas la même décision :
	 *  - page absente ou ACF indisponible  → null (on ne sait pas, on refuse) ;
	 *  - page présente mais champ vide     → array() (premier import légitime) ;
	 *  - page présente et champ rempli     → la structure lue.
	 *
	 * @return array<int, array<string, mixed>>|null Sections, ou null si l'existant est illisible.
	 */
	private function read_current_sections() {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}

		$page = get_post( self::PAGE_ID );
		if ( ! $page instanceof WP_Post ) {
			return null;
		}

		$sections = get_field( 'help_sections', self::PAGE_ID );

		return is_array( $sections ) ? $sections : array();
	}

	/**
	 * Aplatit une structure de sections en identifiants « section/question ».
	 *
	 * @param array<int, array<string, mixed>> $sections Structure de sections.
	 * @return array<string, string> Map `section_id/question_slug` => intitulé de la question.
	 */
	private function flatten_ids( array $sections ) {
		$flat = array();

		foreach ( $sections as $section ) {
			$section_id = isset( $section['section_id'] ) ? (string) $section['section_id'] : '(sans id)';
			$questions  = isset( $section['section_questions'] ) && is_array( $section['section_questions'] )
				? $section['section_questions']
				: array();

			foreach ( $questions as $question ) {
				$slug  = isset( $question['question_slug'] ) ? (string) $question['question_slug'] : '(sans slug)';
				$title = isset( $question['question_title'] ) ? (string) $question['question_title'] : '';

				$flat[ $section_id . '/' . $slug ] = $title;
			}
		}

		return $flat;
	}

	/**
	 * Affiche le différentiel des identifiants entre la base et l'import.
	 *
	 * Les trois listes ne se valent pas : `détruites` est la seule qui engage
	 * une perte de données, elle est donc rendue en dernier et en warning.
	 *
	 * @param array<int, array<string, mixed>> $current  Sections actuellement en base.
	 * @param array<int, array<string, mixed>> $incoming Sections issues du Markdown.
	 * @return array<string, string> Les entrées qui seraient détruites.
	 */
	private function render_diff( array $current, array $incoming ) {
		$before = $this->flatten_ids( $current );
		$after  = $this->flatten_ids( $incoming );

		$kept      = array_intersect_key( $before, $after );
		$added     = array_diff_key( $after, $before );
		$destroyed = array_diff_key( $before, $after );

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( '--- Différentiel help_sections (page %d) ---', self::PAGE_ID ) );
		WP_CLI::log(
			sprintf(
				'  en base : %1$d question(s)   |   import : %2$d question(s)',
				count( $before ),
				count( $after )
			)
		);
		WP_CLI::log( '' );

		WP_CLI::log( sprintf( '  = conservées (%d)', count( $kept ) ) );
		foreach ( array_keys( $kept ) as $id ) {
			WP_CLI::log( '      ' . $id );
		}

		WP_CLI::log( sprintf( '  + ajoutées (%d)', count( $added ) ) );
		foreach ( array_keys( $added ) as $id ) {
			WP_CLI::log( '      ' . $id );
		}

		WP_CLI::log( sprintf( '  - DÉTRUITES (%d)', count( $destroyed ) ) );
		foreach ( $destroyed as $id => $title ) {
			WP_CLI::log( '      ' . $id . ( '' !== $title ? ' — ' . $title : '' ) );
		}
		WP_CLI::log( '' );

		if ( ! empty( $destroyed ) ) {
			WP_CLI::warning(
				sprintf(
					'%d question(s) publiée(s) n\'ont aucun équivalent dans %s et seraient définitivement perdues.',
					count( $destroyed ),
					self::SOURCE_DIR
				)
			);
		}

		return $destroyed;
	}

	/**
	 * Parses one markdown file into a section array.
	 *
	 * @param string    $file      Absolute path.
	 * @param Parsedown $parsedown Parsedown instance.
	 * @return array|null
	 */
	private function parse_file( $file, $parsedown ) {
		$content = file_get_contents( $file );
		if ( false === $content || '' === trim( $content ) ) {
			WP_CLI::warning( "Empty file: $file" );
			return null;
		}

		// section_id from filename: 01-code-promo.md → code-promo.
		$basename   = basename( $file, '.md' );
		$section_id = preg_replace( '/^\d+-/', '', $basename );

		// Split on horizontal rules (--- on its own line, possibly with whitespace).
		$blocks = preg_split( '/^\s*---\s*$/m', $content );

		if ( count( $blocks ) < 2 ) {
			WP_CLI::warning( "File $file has no '---' separator. Skipped." );
			return null;
		}

		// First block must contain the H1.
		$header_block = trim( $blocks[0] );
		if ( ! preg_match( '/^#\s+(.+)$/m', $header_block, $m ) ) {
			WP_CLI::warning( "No '# Title' in header of $file. Skipped." );
			return null;
		}
		$section_title = trim( $m[1] );

		// Remaining blocks = questions.
		$question_blocks = array_slice( $blocks, 1 );
		$questions       = array();

		foreach ( $question_blocks as $block ) {
			$q = $this->parse_question_block( $block, $parsedown );
			if ( $q ) {
				$questions[] = $q;
			}
		}

		if ( empty( $questions ) ) {
			WP_CLI::warning( "No questions parsed from $file. Skipped." );
			return null;
		}

		return array(
			'section_id'        => $section_id,
			'section_title'     => $section_title,
			'section_questions' => $questions,
		);
	}

	/**
	 * Parses one Q&A block.
	 *
	 * @param string    $block     Markdown block between two ---.
	 * @param Parsedown $parsedown Parsedown instance.
	 * @return array|null
	 */
	private function parse_question_block( $block, $parsedown ) {
		$block = trim( $block );
		if ( '' === $block ) {
			return null;
		}

		if ( ! preg_match( '/^##\s+(.+)$/m', $block, $title_match ) ) {
			return null;
		}
		$question_title = trim( $title_match[1] );

		if ( ! preg_match( '/`slug:\s*([a-z0-9\-]+)`/', $block, $slug_match ) ) {
			WP_CLI::warning( "Missing slug for: $question_title" );
			return null;
		}
		$question_slug = trim( $slug_match[1] );

		// Strip the H2 line and the slug marker, keep the rest as answer body.
		$body = preg_replace( '/^##\s+.+$/m', '', $block, 1 );
		$body = preg_replace( '/`slug:\s*[a-z0-9\-]+`/', '', $body, 1 );
		$body = trim( $body );

		$answer_html = $parsedown->text( $body );

		return array(
			'question_slug'   => $question_slug,
			'question_title'  => $question_title,
			'question_answer' => $answer_html,
		);
	}
}

WP_CLI::add_command( '180c-help-import', '_180C_Help_Importer_Command' );
