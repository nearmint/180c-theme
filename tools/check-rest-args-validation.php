<?php
/**
 * Garde statique — contraintes de schéma REST réellement opposables.
 *
 * LA RÈGLE WORDPRESS, QU'ON OUBLIE TOUJOURS
 * -----------------------------------------
 * Un argument déclaré à la main dans `register_rest_route()` EST validé contre
 * son schéma — `enum`, `minimum`, `maximum`, `type`… — mais seulement s'il ne
 * déclare PAS de `sanitize_callback`. Le cœur lui assigne alors
 * `rest_parse_request_arg`, qui valide avant d'assainir :
 *
 *     // wp-includes/rest-api/class-wp-rest-request.php:858-861
 *     // If the arg has a type but no sanitize_callback attribute,
 *     // default to rest_parse_request_arg.
 *     if ( ! array_key_exists( 'sanitize_callback', $param_args )
 *          && ! empty( $param_args['type'] ) ) {
 *         $param_args['sanitize_callback'] = 'rest_parse_request_arg';
 *     }
 *
 * Déclarer un `sanitize_callback` — `absint`, `sanitize_key`, `sanitize_email` —
 * remplace donc ce défaut et DÉSACTIVE SILENCIEUSEMENT la validation du schéma.
 * Les contraintes déclarées deviennent décoratives, sans le moindre avertissement.
 *
 * C'est ce qui a laissé passer, pendant des mois :
 *   - `author-content?type=bogus` en 200 au lieu de 400 ;
 *   - `notifications?per_page=9999` accepté ;
 *   - et surtout un 500 (503 Varnish en production) sur la route PUBLIQUE
 *     `/newsletter/subscribe`, `sanitize_email()` appelant `strlen()` sans cast
 *     sur un `{"email": []}` que `type: string` aurait dû refuser.
 *
 * CE QUE CETTE GARDE VÉRIFIE
 * --------------------------
 *   1. Un argument déclarant `enum`, `minimum`, `maximum`, `format` ou `pattern`
 *      ET un `sanitize_callback`, SANS `validate_callback`.
 *   2. Un argument déclarant `'sanitize_callback' => 'sanitize_email'` sans
 *      `validate_callback` — cas particulier du 1, la contrainte implicite étant
 *      ici `type: string` et l'enjeu un fatal, pas une valeur hors bornes.
 *
 * DEUX FAÇONS DE LA SATISFAIRE, toutes deux légitimes :
 *   - ajouter `'validate_callback' => 'rest_validate_request_arg'` — la
 *     contrainte devient opposable, la réponse devient 400 `rest_invalid_param` ;
 *   - retirer la contrainte — à faire quand la rendre opposable changerait une
 *     réponse d'erreur déjà contractuelle, le cœur aplatissant toute erreur de
 *     `validate_callback` en un unique `rest_invalid_param` 400
 *     (class-wp-rest-request.php:961-968). Le callback assume alors le contrôle,
 *     et le dit en commentaire.
 *
 * Analyse STATIQUE, et non parcours de `rest_get_server()->get_routes()` : le
 * job de déploiement ne charge jamais WordPress (ni base, ni `wp-load`).
 *
 * Usage : php tools/check-rest-args-validation.php [répertoire]
 * Sortie : 0 si tout est conforme, 1 sinon.
 *
 * @package 180c
 */

$root = realpath( $argv[1] ?? __DIR__ . '/../inc' );

if ( false === $root ) {
	fwrite( STDERR, "Répertoire introuvable.\n" );
	exit( 1 );
}

/** Racine du thème, pour n'afficher que des chemins relatifs lisibles. */
$theme_root = realpath( __DIR__ . '/..' );

/** Contraintes que seul `rest_validate_request_arg` applique. */
const CONSTRAINTS = array(
	'enum',
	'minimum',
	'maximum',
	'exclusiveMinimum',
	'exclusiveMaximum',
	'format',
	'pattern',
	'minLength',
	'maxLength',
	'minItems',
	'maxItems',
	'multipleOf',
);

/**
 * Extrait le bloc parenthésé qui commence à l'offset donné.
 *
 * @param string $src    Source PHP.
 * @param int    $offset Position juste après la parenthèse ouvrante.
 * @return array{0:string,1:int} Contenu du bloc et position de fin.
 */
function block_at( string $src, int $offset ): array {
	$depth = 1;
	$i     = $offset;
	$len   = strlen( $src );

	while ( $i < $len && $depth > 0 ) {
		if ( '(' === $src[ $i ] ) {
			++$depth;
		} elseif ( ')' === $src[ $i ] ) {
			--$depth;
		}
		++$i;
	}

	return array( substr( $src, $offset, $i - 1 - $offset ), $i );
}

$files = array();
$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
foreach ( $it as $f ) {
	if ( $f->isFile() && 'php' === $f->getExtension() ) {
		$files[] = $f->getPathname();
	}
}
sort( $files );

$violations = array();

foreach ( $files as $file ) {
	$src = file_get_contents( $file );
	if ( false === strpos( $src, 'register_rest_route' ) ) {
		continue;
	}

	// Chaque bloc `'args' => array( … )`.
	if ( ! preg_match_all( "/'args'\s*=>\s*array\(/", $src, $m, PREG_OFFSET_CAPTURE ) ) {
		continue;
	}

	foreach ( $m[0] as $match ) {
		$args_start          = $match[1] + strlen( $match[0] );
		list( $args_block, ) = block_at( $src, $args_start );

		// Chaque argument nommé de premier niveau.
		if ( ! preg_match_all( "/'([a-zA-Z_][\w]*)'\s*=>\s*array\(/", $args_block, $am, PREG_OFFSET_CAPTURE ) ) {
			continue;
		}

		foreach ( $am[0] as $idx => $amatch ) {
			$arg_name          = $am[1][ $idx ][0];
			$arg_start         = $amatch[1] + strlen( $amatch[0] );
			list( $arg_block, ) = block_at( $args_block, $arg_start );

			// Un argument ne contient jamais de sous-bloc `'args'`.
			if ( false !== strpos( $arg_block, "'args'" ) ) {
				continue;
			}

			$has_validate = false !== strpos( $arg_block, "'validate_callback'" );
			$has_sanitize = false !== strpos( $arg_block, "'sanitize_callback'" );

			if ( $has_validate || ! $has_sanitize ) {
				continue;
			}

			$found = array();
			foreach ( CONSTRAINTS as $c ) {
				if ( preg_match( "/'" . $c . "'\s*=>/", $arg_block ) ) {
					$found[] = $c;
				}
			}

			$is_email = (bool) preg_match( "/'sanitize_callback'\s*=>\s*'sanitize_email'/", $arg_block );
			if ( $is_email ) {
				$found[] = 'sanitize_email';
			}

			if ( empty( $found ) ) {
				continue;
			}

			$line = substr_count( substr( $src, 0, $args_start + $amatch[1] ), "\n" ) + 1;

			$violations[] = sprintf(
				'%s:%d  argument « %s » — %s déclaré(s) avec un sanitize_callback, sans validate_callback',
				ltrim( str_replace( $theme_root, '', $file ), '/' ),
				$line,
				$arg_name,
				implode( ', ', $found )
			);
		}
	}
}

if ( $violations ) {
	fwrite( STDERR, "Contraintes de schéma REST non opposables :\n\n" );
	foreach ( $violations as $v ) {
		fwrite( STDERR, '  ' . $v . "\n" );
	}
	fwrite(
		STDERR,
		"\nUn sanitize_callback désactive la validation du schéma "
		. "(wp-includes/rest-api/class-wp-rest-request.php:858-861).\n"
		. "Corriger en ajoutant 'validate_callback' => 'rest_validate_request_arg',\n"
		. "ou en retirant la contrainte si la rendre opposable changerait une\n"
		. "réponse d'erreur déjà contractuelle. Voir l'en-tête de ce fichier.\n"
	);
	exit( 1 );
}

echo 'OK — ' . count( $files ) . " fichiers PHP analysés, aucune contrainte de schéma REST inopposable\n";
exit( 0 );
