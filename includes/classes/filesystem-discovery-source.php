<?php
/**
 * Filesystem discovery source.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Default discovery source backed by one physical directory.
 *
 * @since 7.5.0
 */
final class Filesystem_Discovery_Source implements Discovery_Source {

	/**
	 * Cached complete inventory.
	 *
	 * @var array<string, Discovery_Entry>|null
	 */
	private ?array $inventory = null;

	/**
	 * Cached logical directories.
	 *
	 * @var array<string, string>
	 */
	private array $directories = array();

	/**
	 * Create a filesystem source.
	 *
	 * @param string      $root_path Root directory.
	 * @param string|null $source_id Optional stable source identifier.
	 */
	public function __construct(
		private readonly string $root_path,
		private readonly ?string $source_id = null
	) {
	}

	/**
	 * Get a stable source identifier.
	 *
	 * @return string Source identifier.
	 */
	public function id(): string {
		return $this->source_id ?? 'filesystem:' . $this->root();
	}

	/**
	 * Get the physical source root.
	 *
	 * @return string Source root.
	 */
	public function root(): string {
		return untrailingslashit( wp_normalize_path( $this->root_path ) );
	}

	/**
	 * Get a metadata fingerprint of the visible inventory.
	 *
	 * @return string Content fingerprint.
	 */
	public function fingerprint(): string {
		$state = array();

		foreach ( $this->entries() as $entry ) {
			$logical_path = $entry->logical_path();

			if ( '_dist' === $logical_path || str_starts_with( $logical_path, '_dist/' ) || str_contains( $logical_path, '/_dist/' ) ) {
				continue;
			}

			$path    = $entry->physical_path();
			$state[] = array(
				$logical_path,
				is_file( $path ) ? (int) filemtime( $path ) : 0,
				is_file( $path ) ? (int) filesize( $path ) : 0,
			);
		}

		$encoded = wp_json_encode( $state );

		return hash( 'sha256', false === $encoded ? '' : $encoded );
	}

	/**
	 * List visible files.
	 *
	 * @param string $directory Logical directory relative to the source root.
	 * @param bool   $recursive Include descendants below the directory.
	 *
	 * @return array<int, Discovery_Entry> Visible file entries.
	 */
	public function entries( string $directory = '', bool $recursive = true ): array {
		$directory = Discovery_Sources::normalize_logical_path( $directory );
		$entries   = array();

		foreach ( $this->inventory() as $logical_path => $entry ) {
			$parent = dirname( $logical_path );
			$parent = '.' === $parent ? '' : $parent;

			if ( '' === $directory ) {
				if ( $recursive || '' === $parent ) {
					$entries[] = $entry;
				}
				continue;
			}

			if ( $recursive ) {
				if ( $parent === $directory || str_starts_with( $parent, $directory . '/' ) ) {
					$entries[] = $entry;
				}
			} elseif ( $parent === $directory ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Resolve one logical file.
	 *
	 * @param string $logical_path Logical path relative to the source root.
	 *
	 * @return Discovery_Entry|null Resolved entry.
	 */
	public function resolve( string $logical_path ): ?Discovery_Entry {
		$logical_path = Discovery_Sources::normalize_logical_path( $logical_path );

		return $this->inventory()[ $logical_path ] ?? null;
	}

	/**
	 * List direct file children.
	 *
	 * @param string $directory Logical directory relative to the source root.
	 *
	 * @return array<int, Discovery_Entry> Direct file children.
	 */
	public function children( string $directory = '' ): array {
		return $this->entries( $directory, false );
	}

	/**
	 * List visible logical directories.
	 *
	 * @param string $directory Logical directory.
	 * @param bool   $recursive Include descendants.
	 *
	 * @return array<int, string> Logical directory paths.
	 */
	public function directories( string $directory = '', bool $recursive = true ): array {
		$this->inventory();
		$directory = Discovery_Sources::normalize_logical_path( $directory );
		$results   = array();

		foreach ( array_keys( $this->directories ) as $logical_path ) {
			$parent = dirname( $logical_path );
			$parent = '.' === $parent ? '' : $parent;

			if ( '' === $directory ) {
				if ( $recursive || '' === $parent ) {
					$results[] = $logical_path;
				}
				continue;
			}

			if ( $parent === $directory || ( $recursive && str_starts_with( $parent, $directory . '/' ) ) ) {
				$results[] = $logical_path;
			}
		}

		return $results;
	}

	/**
	 * Get physical watch inputs.
	 *
	 * @return array<int, string> Watch inputs.
	 */
	public function watch_paths(): array {
		return '' === $this->root() ? array() : array( $this->root() );
	}

	/**
	 * Build and cache the complete inventory.
	 *
	 * @return array<string, Discovery_Entry> Inventory indexed by logical path.
	 */
	private function inventory(): array {
		if ( null !== $this->inventory ) {
			return $this->inventory;
		}

		$this->inventory = array();
		$root            = $this->root();

		if ( '' === $root || ! is_dir( $root ) ) {
			return $this->inventory;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			$physical_path = wp_normalize_path( $file->getPathname() );
			$logical_path  = ltrim( substr( $physical_path, strlen( $root ) ), '/' );

			if ( '' === $logical_path ) {
				continue;
			}

			if ( $file->isDir() ) {
				$this->directories[ $logical_path ] = $physical_path;
				continue;
			}

			if ( ! $file->isFile() ) {
				continue;
			}

			$this->inventory[ $logical_path ] = new Discovery_Entry(
				$logical_path,
				$physical_path,
				array(
					'type' => 'filesystem',
					'root' => $root,
				)
			);
		}

		ksort( $this->inventory, SORT_STRING );
		ksort( $this->directories, SORT_STRING );

		return $this->inventory;
	}
}
