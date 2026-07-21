<?php
/**
 * Discovery source factory.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Creates injectable discovery sources for Blockstudio subsystems.
 *
 * @since 7.5.0
 */
final class Discovery_Sources {

	/**
	 * Sources activated during the current request.
	 *
	 * @var array<string, array<int, Discovery_Source>>
	 */
	private static array $active = array();

	/**
	 * Physical source entry lookup cache.
	 *
	 * @var array<string, Discovery_Entry|null>
	 */
	private static array $physical_entries = array();

	/**
	 * Create sources for physical path defaults.
	 *
	 * Consumers may replace the returned filesystem sources with one composed
	 * logical source. The composed source applies shadow and deletion semantics.
	 *
	 * @param string $context Discovery context such as blocks, pages, or patterns.
	 * @param array  $paths Default physical root paths.
	 *
	 * @return array<int, Discovery_Source> Discovery sources.
	 */
	public static function for_paths( string $context, array $paths ): array {
		$sources = array();

		foreach ( $paths as $path ) {
			if ( is_string( $path ) && '' !== $path ) {
				$sources[] = new Filesystem_Discovery_Source( $path );
			}
		}

		/**
		 * Filter logical discovery sources for a Blockstudio subsystem.
		 *
		 * Replace the default filesystem sources with a composed source to expose
		 * a virtual inventory. Returned sources must already apply their desired
		 * shadow, addition, and deletion semantics.
		 *
		 * @since 7.5.0
		 *
		 * @param array<int, Discovery_Source> $sources Default filesystem sources.
		 * @param string                       $context Discovery context.
		 * @param array<int, string>           $paths Default physical paths.
		 */
		$filtered = apply_filters( 'blockstudio/discovery/sources', $sources, $context, $paths );

		if ( ! is_array( $filtered ) ) {
			self::$active[ $context ] = $sources;
			self::$physical_entries   = array();
			return $sources;
		}

		$sources = array_values(
			array_filter(
				$filtered,
				static fn( mixed $source ): bool => $source instanceof Discovery_Source
			)
		);

		self::$active[ $context ] = $sources;
		self::$physical_entries   = array();

		return $sources;
	}

	/**
	 * Get one source for a single-root discovery call.
	 *
	 * @param string $context Discovery context.
	 * @param string $path Default physical root.
	 *
	 * @return Discovery_Source Discovery source.
	 */
	public static function for_path( string $context, string $path ): Discovery_Source {
		$sources = self::for_paths( $context, array( $path ) );

		if ( isset( $sources[0] ) ) {
			return $sources[0];
		}

		$empty                    = new Inventory_Discovery_Source( 'empty:' . $context . ':' . hash( 'sha256', $path ), $path, array() );
		self::$active[ $context ] = array( $empty );

		return $empty;
	}

	/**
	 * Expose one logical subdirectory as a discovery source.
	 *
	 * @param Discovery_Source $source Parent source.
	 * @param string           $prefix Logical subdirectory.
	 * @param string|null      $root Optional compatibility root.
	 *
	 * @return Discovery_Source Scoped source.
	 */
	public static function scope( Discovery_Source $source, string $prefix, ?string $root = null ): Discovery_Source {
		return new Scoped_Discovery_Source( $source, $prefix, $root );
	}

	/**
	 * Build a stable identity for discovery sources.
	 *
	 * @param string                       $context Discovery context.
	 * @param array<int, Discovery_Source> $sources Discovery sources.
	 *
	 * @return array Discovery identity.
	 */
	public static function identity( string $context, array $sources ): array {
		$identity = array(
			'context' => $context,
			'sources' => array(),
		);

		foreach ( $sources as $source ) {
			if ( ! $source instanceof Discovery_Source ) {
				continue;
			}

			$identity['sources'][] = array(
				'id'          => $source->id(),
				'root'        => $source->root(),
				'fingerprint' => $source->fingerprint(),
			);
		}

		return $identity;
	}

	/**
	 * Get the identity of sources activated for a context.
	 *
	 * @param string $context Discovery context.
	 *
	 * @return array Discovery identity.
	 */
	public static function active_identity( string $context ): array {
		return self::identity( $context, self::$active[ $context ] ?? array() );
	}

	/**
	 * Get sources activated for a context.
	 *
	 * @param string $context Discovery context.
	 *
	 * @return array<int, Discovery_Source> Active sources.
	 */
	public static function active_sources( string $context ): array {
		return self::$active[ $context ] ?? array();
	}

	/**
	 * Get stable source-selection identity without content fingerprints.
	 *
	 * @param string $context Discovery context.
	 *
	 * @return array<int, array{id:string, root:string}> Source selection.
	 */
	public static function active_selection( string $context ): array {
		$selection = array();

		foreach ( self::$active[ $context ] ?? array() as $source ) {
			if ( $source instanceof Filesystem_Discovery_Source ) {
				continue;
			}

			$selection[] = array(
				'id'   => $source->id(),
				'root' => $source->root(),
			);
		}

		return $selection;
	}

	/**
	 * Get watch inputs for sources activated for a context.
	 *
	 * @param string $context Discovery context.
	 *
	 * @return array<int, string> Watch inputs.
	 */
	public static function active_watch_paths( string $context ): array {
		$paths = array();

		foreach ( self::$active[ $context ] ?? array() as $source ) {
			$paths = array_merge( $paths, $source->watch_paths() );
		}

		return array_values( array_unique( array_map( 'wp_normalize_path', $paths ) ) );
	}

	/**
	 * Find active logical source metadata for a physical file or directory.
	 *
	 * @param string $path Physical file or directory path.
	 *
	 * @return Discovery_Entry|null Matching source entry.
	 */
	public static function entry_for_physical_path( string $path ): ?Discovery_Entry {
		$path            = untrailingslashit( wp_normalize_path( $path ) );
		$directory_match = null;

		if ( array_key_exists( $path, self::$physical_entries ) ) {
			return self::$physical_entries[ $path ];
		}

		foreach ( self::$active as $sources ) {
			foreach ( $sources as $source ) {
				foreach ( $source->entries() as $entry ) {
					$physical = untrailingslashit( wp_normalize_path( $entry->physical_path() ) );

					if ( $physical === $path ) {
						self::$physical_entries[ $path ] = $entry;
						return $entry;
					}

					if ( is_dir( $path ) && dirname( $physical ) === $path ) {
						$directory_match ??= $entry;

						if ( self::entry_is_read_only( $entry ) ) {
							self::$physical_entries[ $path ] = $entry;
							return $entry;
						}
					}
				}
			}
		}

		self::$physical_entries[ $path ] = $directory_match;

		return $directory_match;
	}

	/**
	 * Determine whether a source entry is inherited or read-only.
	 *
	 * @param Discovery_Entry $entry Source entry.
	 *
	 * @return bool Whether generated output must be redirected.
	 */
	public static function entry_is_read_only( Discovery_Entry $entry ): bool {
		$metadata   = $entry->metadata();
		$provenance = $entry->provenance();

		return false === ( $metadata['writable'] ?? true )
			|| false === ( $provenance['writable'] ?? true )
			|| true === ( $metadata['inherited'] ?? false )
			|| true === ( $provenance['inherited'] ?? false )
			|| true === ( $metadata['readOnly'] ?? false )
			|| true === ( $provenance['readOnly'] ?? false );
	}

	/**
	 * Reset current request source state.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$active           = array();
		self::$physical_entries = array();
	}

	/**
	 * Normalize a logical path.
	 *
	 * @param string $path Logical path.
	 *
	 * @return string Safe relative logical path.
	 */
	public static function normalize_logical_path( string $path ): string {
		$path = trim( str_replace( '\\', '/', $path ), '/' );

		if ( '' === $path || '.' === $path ) {
			return '';
		}

		$segments = array();

		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}

			if ( '..' === $segment ) {
				array_pop( $segments );
				continue;
			}

			$segments[] = $segment;
		}

		return implode( '/', $segments );
	}
}
