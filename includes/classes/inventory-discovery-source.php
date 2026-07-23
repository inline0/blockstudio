<?php
/**
 * In-memory inventory discovery source.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Exposes a consumer-composed logical inventory.
 *
 * Consumers compose parent and overlay files before constructing this source.
 * A later entry for the same logical path replaces the previous entry. Deleted
 * files are omitted from the final inventory.
 *
 * @since 7.5.0
 */
final class Inventory_Discovery_Source implements Discovery_Source {

	/**
	 * Visible entries indexed by logical path.
	 *
	 * @var array<string, Discovery_Entry>
	 */
	private array $inventory = array();

	/**
	 * Visible logical directories.
	 *
	 * @var array<string, true>
	 */
	private array $directories = array();

	/**
	 * Create an inventory source.
	 *
	 * Entry values may be Discovery_Entry objects, physical path strings, or
	 * arrays with `path`, `provenance`, and `metadata` keys.
	 *
	 * @param string      $source_id Stable source identifier.
	 * @param string      $root_path Compatibility root.
	 * @param array       $entries Visible entries keyed by logical path.
	 * @param string|null $content_fingerprint Optional precomputed fingerprint.
	 * @param array       $watch_paths_list Physical watch inputs.
	 * @param array       $directory_paths Visible logical directories.
	 */
	public function __construct(
		private readonly string $source_id,
		private readonly string $root_path,
		array $entries,
		private readonly ?string $content_fingerprint = null,
		private readonly array $watch_paths_list = array(),
		array $directory_paths = array()
	) {
		foreach ( $entries as $logical_path => $value ) {
			$entry = $this->normalize_entry( $logical_path, $value );

			if ( $entry ) {
				$this->inventory[ $entry->logical_path() ] = $entry;
			}
		}

		foreach ( array_keys( $this->inventory ) as $logical_path ) {
			$directory = dirname( $logical_path );

			while ( '.' !== $directory && '' !== $directory ) {
				$this->directories[ $directory ] = true;
				$directory                       = dirname( $directory );
			}
		}

		foreach ( $directory_paths as $key => $value ) {
			$directory = is_string( $key ) && ! is_int( $key ) ? $key : $value;

			if ( is_string( $directory ) && '' !== Discovery_Sources::normalize_logical_path( $directory ) ) {
				$this->directories[ Discovery_Sources::normalize_logical_path( $directory ) ] = true;
			}
		}

		ksort( $this->inventory, SORT_STRING );
		ksort( $this->directories, SORT_STRING );
	}

	/**
	 * Get the source identifier.
	 *
	 * @return string Source identifier.
	 */
	public function id(): string {
		return $this->source_id;
	}

	/**
	 * Get the compatibility root.
	 *
	 * @return string Source root.
	 */
	public function root(): string {
		return untrailingslashit( wp_normalize_path( $this->root_path ) );
	}

	/**
	 * Get the visible inventory fingerprint.
	 *
	 * @return string Content fingerprint.
	 */
	public function fingerprint(): string {
		if ( null !== $this->content_fingerprint ) {
			return $this->content_fingerprint;
		}

		$state = array();

		foreach ( $this->inventory as $entry ) {
			$path    = $entry->physical_path();
			$state[] = array(
				$entry->logical_path(),
				$path,
				is_file( $path ) ? (int) filemtime( $path ) : 0,
				is_file( $path ) ? (int) filesize( $path ) : 0,
				$entry->provenance(),
				$entry->metadata(),
			);
		}

		$encoded = wp_json_encode( $state );

		return hash( 'sha256', false === $encoded ? '' : $encoded );
	}

	/**
	 * List visible files.
	 *
	 * @param string $directory Logical directory.
	 * @param bool   $recursive Include descendants.
	 *
	 * @return array<int, Discovery_Entry> Entries.
	 */
	public function entries( string $directory = '', bool $recursive = true ): array {
		$directory = Discovery_Sources::normalize_logical_path( $directory );
		$entries   = array();

		foreach ( $this->inventory as $logical_path => $entry ) {
			$parent = dirname( $logical_path );
			$parent = '.' === $parent ? '' : $parent;

			if ( '' === $directory ) {
				if ( $recursive || '' === $parent ) {
					$entries[] = $entry;
				}
				continue;
			}

			if ( $parent === $directory || ( $recursive && str_starts_with( $parent, $directory . '/' ) ) ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Resolve one visible logical file.
	 *
	 * @param string $logical_path Logical path.
	 *
	 * @return Discovery_Entry|null Resolved entry.
	 */
	public function resolve( string $logical_path ): ?Discovery_Entry {
		$logical_path = Discovery_Sources::normalize_logical_path( $logical_path );

		return $this->inventory[ $logical_path ] ?? null;
	}

	/**
	 * List direct file children.
	 *
	 * @param string $directory Logical directory.
	 *
	 * @return array<int, Discovery_Entry> Entries.
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
		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( mixed $path ): string => is_string( $path ) ? wp_normalize_path( $path ) : '',
						$this->watch_paths_list
					)
				)
			)
		);
	}

	/**
	 * Normalize one constructor entry.
	 *
	 * @param mixed $logical_path Logical path key.
	 * @param mixed $value Entry value.
	 *
	 * @return Discovery_Entry|null Normalized entry.
	 */
	private function normalize_entry( mixed $logical_path, mixed $value ): ?Discovery_Entry {
		if ( $value instanceof Discovery_Entry ) {
			return $value;
		}

		if ( ! is_string( $logical_path ) ) {
			return null;
		}

		$logical_path = Discovery_Sources::normalize_logical_path( $logical_path );

		if ( '' === $logical_path ) {
			return null;
		}

		if ( is_string( $value ) ) {
			return new Discovery_Entry( $logical_path, wp_normalize_path( $value ) );
		}

		if ( ! is_array( $value ) || ! is_string( $value['path'] ?? null ) || '' === $value['path'] ) {
			return null;
		}

		return new Discovery_Entry(
			$logical_path,
			wp_normalize_path( $value['path'] ),
			is_array( $value['provenance'] ?? null ) ? $value['provenance'] : array(),
			is_array( $value['metadata'] ?? null ) ? $value['metadata'] : array()
		);
	}
}
