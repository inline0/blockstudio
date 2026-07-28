#!/usr/bin/env php
<?php
/**
 * Standalone Blockstudio block-tag migration command.
 *
 * @package Blockstudio
 */

use Blockstudio\Block_Tag_Migration;

require_once dirname( __DIR__ ) . '/includes/classes/block-tag-migration.php';

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- This standalone command writes diagnostics to a terminal.
// phpcs:disable WordPress.WP.AlternativeFunctions -- WordPress is intentionally not loaded by this standalone command.
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup follows an already detected write failure.

/**
 * Write one message to STDERR and terminate.
 *
 * @param string $message Message.
 * @param int    $code    Exit code.
 *
 * @return never
 */
function blockstudio_tag_migration_fail( string $message, int $code = 1 ): never {
	fwrite( STDERR, "Error: {$message}\n" );
	exit( $code );
}

/**
 * Read a JSON file or inline JSON argument.
 *
 * @param string $value Argument value.
 * @param string $label Human-readable label.
 *
 * @return array Decoded JSON.
 */
function blockstudio_tag_migration_json( string $value, string $label ): array {
	$json = is_file( $value ) ? file_get_contents( $value ) : $value;

	if ( false === $json ) {
		blockstudio_tag_migration_fail( "Unable to read {$label}: {$value}" );
	}

	try {
		$decoded = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		blockstudio_tag_migration_fail( "Invalid {$label} JSON: " . $exception->getMessage() );
	}

	if ( ! is_array( $decoded ) ) {
		blockstudio_tag_migration_fail( "{$label} must decode to an object or array." );
	}

	return $decoded;
}

/**
 * Write a file atomically.
 *
 * @param string $path    Destination.
 * @param string $content Contents.
 *
 * @return void
 */
function blockstudio_tag_migration_write( string $path, string $content ): void {
	$directory = dirname( $path );

	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0777, true ) && ! is_dir( $directory ) ) {
		blockstudio_tag_migration_fail( "Unable to create directory: {$directory}" );
	}

	$temporary = tempnam( $directory, '.blockstudio-tags-' );
	if ( false === $temporary ) {
		blockstudio_tag_migration_fail( "Unable to create a temporary file in {$directory}" );
	}

	if ( false === file_put_contents( $temporary, $content ) || ! rename( $temporary, $path ) ) {
		@unlink( $temporary );
		blockstudio_tag_migration_fail( "Unable to write: {$path}" );
	}
}

/**
 * Whether a path contains an excluded path component.
 *
 * @param string            $relative Relative path.
 * @param array<int,string> $excluded Excluded components.
 *
 * @return bool Whether it is excluded.
 */
function blockstudio_tag_migration_excluded( string $relative, array $excluded ): bool {
	$components = explode( '/', str_replace( '\\', '/', $relative ) );

	foreach ( $components as $component ) {
		if ( in_array( $component, $excluded, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Print command usage.
 *
 * @return void
 */
function blockstudio_tag_migration_usage(): void {
	$usage = <<<'USAGE'
Blockstudio canonical block-tag migration

Usage:
  php bin/migrate-block-tags.php \
    --root=/path/to/project \
    --prefix-map=/outside/prefixes.json \
    --known-blocks=/outside/blocks.json \
    [--aliases=/outside/aliases.json] \
    [--report=/outside/report.json] \
    [--extensions=php,phtml,html,twig,md,mdx] \
    [--exclude=.git,node_modules,vendor] \
    [--dry-run]

The command is dry-run-only unless --apply is supplied. --apply refuses to
write while unknown, ambiguous, or dynamic tags remain unless
--allow-unresolved is also supplied.

Prefix map example:
  {"brand":["theme-components"],"ui":["bsui"]}

Known blocks example:
  ["theme-components/card","bsui/input","core/paragraph"]

Legacy aliases are optional and may use a string target. An array target is
reported as ambiguous instead of guessed:
  {"old-card":"theme-components/card","old-choice":["acme/a","acme/b"]}
USAGE;

	fwrite( STDOUT, $usage . "\n" );
}

/**
 * Execute the standalone command.
 *
 * @return void
 */
function blockstudio_tag_migration_main(): void {
	$options = getopt(
		'',
		array(
			'root:',
			'prefix-map:',
			'known-blocks:',
			'aliases:',
			'report:',
			'extensions:',
			'exclude:',
			'apply',
			'dry-run',
			'allow-unresolved',
			'help',
		)
	);

	if ( isset( $options['help'] ) ) {
		blockstudio_tag_migration_usage();
		return;
	}

	foreach ( array( 'root', 'prefix-map', 'known-blocks' ) as $required ) {
		if ( ! isset( $options[ $required ] ) || ! is_string( $options[ $required ] ) || '' === $options[ $required ] ) {
			blockstudio_tag_migration_fail( "Missing --{$required}. Use --help for usage." );
		}
	}

	if ( isset( $options['apply'], $options['dry-run'] ) ) {
		blockstudio_tag_migration_fail( '--apply and --dry-run are mutually exclusive.' );
	}

	$root = realpath( $options['root'] );
	if ( false === $root || ! is_dir( $root ) ) {
		blockstudio_tag_migration_fail( 'Root does not exist or is not a directory: ' . $options['root'] );
	}

	$root         = rtrim( str_replace( '\\', '/', $root ), '/' );
	$prefix_map   = blockstudio_tag_migration_json( $options['prefix-map'], 'prefix map' );
	$known_blocks = blockstudio_tag_migration_json( $options['known-blocks'], 'known blocks' );
	$aliases      = isset( $options['aliases'] )
		? blockstudio_tag_migration_json( $options['aliases'], 'aliases' )
		: array();

	if ( isset( $prefix_map['prefixes'] ) && is_array( $prefix_map['prefixes'] ) ) {
		$prefix_map = $prefix_map['prefixes'];
	}
	if ( isset( $known_blocks['blocks'] ) && is_array( $known_blocks['blocks'] ) ) {
		$known_blocks = $known_blocks['blocks'];
	}
	if ( isset( $aliases['aliases'] ) && is_array( $aliases['aliases'] ) ) {
		$aliases = $aliases['aliases'];
	}

	$extensions = array_map(
		'strtolower',
		array_filter(
			array_map(
				'trim',
				explode( ',', is_string( $options['extensions'] ?? null ) ? $options['extensions'] : 'php,phtml,html,htm,twig,md,mdx' )
			)
		)
	);
	$excluded   = array_values(
		array_unique(
			array_filter(
				array_map(
					'trim',
					explode(
						',',
						is_string( $options['exclude'] ?? null )
							? $options['exclude']
							: '.git,node_modules,vendor,dist,build,coverage'
					)
				)
			)
		)
	);
	$apply      = isset( $options['apply'] );
	$files      = array();

	$directory_iterator = new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS );
	$filtered_iterator  = new RecursiveCallbackFilterIterator(
		$directory_iterator,
		static function ( SplFileInfo $current ) use ( $root, $excluded ): bool {
			$absolute = str_replace( '\\', '/', $current->getPathname() );
			$relative = ltrim( substr( $absolute, strlen( $root ) ), '/' );

			if ( $current->isLink() ) {
				return false;
			}

			return ! blockstudio_tag_migration_excluded( $relative, $excluded );
		}
	);
	$iterator           = new RecursiveIteratorIterator(
		$filtered_iterator,
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ( $iterator as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() || $file->isLink() ) {
			continue;
		}

		$absolute = str_replace( '\\', '/', $file->getPathname() );
		$relative = ltrim( substr( $absolute, strlen( $root ) ), '/' );

		if ( blockstudio_tag_migration_excluded( $relative, $excluded ) ) {
			continue;
		}

		$extension = strtolower( $file->getExtension() );
		if ( ! in_array( $extension, $extensions, true ) ) {
			continue;
		}

		$files[ $relative ] = $absolute;
	}

	ksort( $files );

	$report         = array(
		'schema_version' => 1,
		'tool'           => 'blockstudio/canonical-block-tags',
		'mode'           => $apply ? 'apply' : 'dry-run',
		'root'           => $root,
		'configuration'  => array(
			'prefix_map_sha256'   => hash( 'sha256', (string) json_encode( $prefix_map ) ),
			'known_blocks_sha256' => hash( 'sha256', (string) json_encode( $known_blocks ) ),
			'aliases_sha256'      => hash( 'sha256', (string) json_encode( $aliases ) ),
			'extensions'          => $extensions,
			'excluded'            => $excluded,
		),
		'summary'        => array(
			'files_scanned'           => 0,
			'files_with_changes'      => 0,
			'replacement_occurrences' => 0,
			'unknown_occurrences'     => 0,
			'ambiguous_occurrences'   => 0,
			'manual_occurrences'      => 0,
		),
		'mappings'       => array(),
		'files'          => array(),
		'unknown'        => array(),
		'ambiguous'      => array(),
		'manual'         => array(),
	);
	$pending_writes = array();

	foreach ( $files as $relative => $absolute ) {
		$source = file_get_contents( $absolute );
		if ( false === $source ) {
			blockstudio_tag_migration_fail( "Unable to read: {$absolute}" );
		}

		try {
			$result = Block_Tag_Migration::migrate_source(
				$source,
				$relative,
				$prefix_map,
				$known_blocks,
				$aliases
			);
		} catch ( Throwable $exception ) {
			blockstudio_tag_migration_fail( $exception->getMessage() );
		}

		++$report['summary']['files_scanned'];
		$report['summary']['replacement_occurrences'] += count( $result['replacements'] );
		$report['summary']['unknown_occurrences']     += count( $result['unknown'] );
		$report['summary']['ambiguous_occurrences']   += count( $result['ambiguous'] );
		$report['summary']['manual_occurrences']      += count( $result['manual'] );

		foreach ( $result['replacements'] as $replacement ) {
			$key = $replacement['tag'] . '>' . $replacement['canonical'];
			if ( ! isset( $report['mappings'][ $key ] ) ) {
				$report['mappings'][ $key ] = array(
					'legacy'      => $replacement['tag'],
					'canonical'   => $replacement['canonical'],
					'block'       => $replacement['block'],
					'occurrences' => 0,
				);
			}
			++$report['mappings'][ $key ]['occurrences'];
		}

		$report['unknown']   = array_merge( $report['unknown'], $result['unknown'] );
		$report['ambiguous'] = array_merge( $report['ambiguous'], $result['ambiguous'] );
		$report['manual']    = array_merge( $report['manual'], $result['manual'] );

		if ( $result['changed'] || $result['unknown'] || $result['ambiguous'] || $result['manual'] ) {
			$report['files'][] = array(
				'path'                    => $relative,
				'changed'                 => $result['changed'],
				'before_sha256'           => hash( 'sha256', $source ),
				'after_sha256'            => hash( 'sha256', $result['source'] ),
				'replacement_occurrences' => count( $result['replacements'] ),
				'unknown_occurrences'     => count( $result['unknown'] ),
				'ambiguous_occurrences'   => count( $result['ambiguous'] ),
				'manual_occurrences'      => count( $result['manual'] ),
			);
		}

		if ( $result['changed'] ) {
			++$report['summary']['files_with_changes'];
			if ( $apply ) {
				$pending_writes[ $absolute ] = $result['source'];
			}
		}
	}

	ksort( $report['mappings'] );
	$report['mappings'] = array_values( $report['mappings'] );

	$has_unresolved = 0 < $report['summary']['unknown_occurrences']
		|| 0 < $report['summary']['ambiguous_occurrences']
		|| array_filter(
			$report['manual'],
			static fn( array $item ): bool => 'dynamic-tag' === $item['reason']
		);

	if ( $apply && $has_unresolved && ! isset( $options['allow-unresolved'] ) ) {
		blockstudio_tag_migration_fail(
			'Unresolved tags remain; no files were written. Review a dry-run report or pass --allow-unresolved.',
			2
		);
	}

	if ( $apply ) {
		foreach ( $pending_writes as $absolute => $contents ) {
			blockstudio_tag_migration_write( $absolute, $contents );
		}
	}

	$encoded = json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( false === $encoded ) {
		blockstudio_tag_migration_fail( 'Unable to encode the migration report.' );
	}
	$encoded .= "\n";

	if ( isset( $options['report'] ) && is_string( $options['report'] ) && '' !== $options['report'] ) {
		blockstudio_tag_migration_write( $options['report'], $encoded );
		fwrite( STDERR, 'Report: ' . $options['report'] . "\n" );
	} else {
		fwrite( STDOUT, $encoded );
	}

	fwrite(
		STDERR,
		sprintf(
			"%s: %d files, %d changed, %d replacements, %d unknown, %d ambiguous, %d manual.\n",
			$apply ? 'Applied' : 'Dry run',
			$report['summary']['files_scanned'],
			$report['summary']['files_with_changes'],
			$report['summary']['replacement_occurrences'],
			$report['summary']['unknown_occurrences'],
			$report['summary']['ambiguous_occurrences'],
			$report['summary']['manual_occurrences']
		)
	);
}

blockstudio_tag_migration_main();
