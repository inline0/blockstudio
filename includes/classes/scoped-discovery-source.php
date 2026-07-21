<?php
/**
 * Scoped discovery source.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Exposes one logical subdirectory of another source as a source root.
 *
 * @since 7.5.0
 */
final class Scoped_Discovery_Source implements Discovery_Source {

	/**
	 * Normalized logical prefix.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Create a scoped source.
	 *
	 * @param Discovery_Source $source Parent source.
	 * @param string           $prefix Logical subdirectory.
	 * @param string|null      $root_path Optional compatibility root.
	 */
	public function __construct(
		private readonly Discovery_Source $source,
		string $prefix,
		private readonly ?string $root_path = null
	) {
		$this->prefix = Discovery_Sources::normalize_logical_path( $prefix );
	}

	/**
	 * Get the scoped source identifier.
	 *
	 * @return string Source identifier.
	 */
	public function id(): string {
		return $this->source->id() . '#' . $this->prefix;
	}

	/**
	 * Get the compatibility root.
	 *
	 * @return string Source root.
	 */
	public function root(): string {
		if ( null !== $this->root_path ) {
			return wp_normalize_path( $this->root_path );
		}

		return rtrim( $this->source->root(), '/' ) . ( '' === $this->prefix ? '' : '/' . $this->prefix );
	}

	/**
	 * Get the scoped content fingerprint.
	 *
	 * @return string Content fingerprint.
	 */
	public function fingerprint(): string {
		$encoded = wp_json_encode(
			array(
				'prefix'      => $this->prefix,
				'fingerprint' => $this->source->fingerprint(),
			)
		);

		return hash( 'sha256', false === $encoded ? '' : $encoded );
	}

	/**
	 * List visible scoped files.
	 *
	 * @param string $directory Logical directory.
	 * @param bool   $recursive Include descendants.
	 *
	 * @return array<int, Discovery_Entry> Entries.
	 */
	public function entries( string $directory = '', bool $recursive = true ): array {
		$directory  = Discovery_Sources::normalize_logical_path( $directory );
		$parent_dir = $this->parent_path( $directory );
		$entries    = array();

		foreach ( $this->source->entries( $parent_dir, $recursive ) as $entry ) {
			$logical = $this->scoped_path( $entry->logical_path() );

			if ( null === $logical ) {
				continue;
			}

			$entries[] = new Discovery_Entry(
				$logical,
				$entry->physical_path(),
				$entry->provenance(),
				$entry->metadata()
			);
		}

		return $entries;
	}

	/**
	 * Resolve one scoped logical file.
	 *
	 * @param string $logical_path Logical path.
	 *
	 * @return Discovery_Entry|null Resolved entry.
	 */
	public function resolve( string $logical_path ): ?Discovery_Entry {
		$logical_path = Discovery_Sources::normalize_logical_path( $logical_path );
		$entry        = $this->source->resolve( $this->parent_path( $logical_path ) );

		if ( ! $entry ) {
			return null;
		}

		return new Discovery_Entry(
			$logical_path,
			$entry->physical_path(),
			$entry->provenance(),
			$entry->metadata()
		);
	}

	/**
	 * List direct scoped file children.
	 *
	 * @param string $directory Logical directory.
	 *
	 * @return array<int, Discovery_Entry> Entries.
	 */
	public function children( string $directory = '' ): array {
		return $this->entries( $directory, false );
	}

	/**
	 * List visible scoped directories.
	 *
	 * @param string $directory Logical directory.
	 * @param bool   $recursive Include descendants.
	 *
	 * @return array<int, string> Logical directory paths.
	 */
	public function directories( string $directory = '', bool $recursive = true ): array {
		$results = array();

		foreach ( $this->source->directories( $this->parent_path( $directory ), $recursive ) as $path ) {
			$scoped = $this->scoped_path( $path );

			if ( null !== $scoped && '' !== $scoped ) {
				$results[] = $scoped;
			}
		}

		return $results;
	}

	/**
	 * Get parent watch inputs.
	 *
	 * @return array<int, string> Watch inputs.
	 */
	public function watch_paths(): array {
		return $this->source->watch_paths();
	}

	/**
	 * Map a scoped path to the parent source.
	 *
	 * @param string $path Scoped logical path.
	 *
	 * @return string Parent logical path.
	 */
	private function parent_path( string $path ): string {
		return implode( '/', array_filter( array( $this->prefix, $path ), 'strlen' ) );
	}

	/**
	 * Map a parent path into this scope.
	 *
	 * @param string $path Parent logical path.
	 *
	 * @return string|null Scoped path.
	 */
	private function scoped_path( string $path ): ?string {
		if ( '' === $this->prefix ) {
			return $path;
		}

		if ( ! str_starts_with( $path, $this->prefix . '/' ) ) {
			return null;
		}

		return substr( $path, strlen( $this->prefix ) + 1 );
	}
}
