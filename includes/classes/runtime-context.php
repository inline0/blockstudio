<?php
/**
 * Runtime context helpers.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Namespaces caches and generated outputs for alternate runtimes.
 *
 * @since 7.5.0
 */
final class Runtime_Context {

	/**
	 * Get the consumer-provided cache context.
	 *
	 * @param string $scope Cache scope.
	 *
	 * @return mixed Serializable context value.
	 */
	public static function cache( string $scope ): mixed {
		/**
		 * Filter the active runtime cache context.
		 *
		 * The value must change whenever the logical inventory or runtime selection
		 * changes. It is included in Blockstudio cache keys and namespaces.
		 *
		 * @since 7.5.0
		 *
		 * @param mixed  $context Cache context. Empty by default.
		 * @param string $scope Cache scope.
		 */
		return apply_filters( 'blockstudio/cache/context', '', $scope );
	}

	/**
	 * Get a stable context hash.
	 *
	 * The hash covers only the consumer-provided cache context. Per the
	 * blockstudio/cache/context contract, that value must change whenever the
	 * logical inventory or runtime selection changes, so traversing discovery
	 * sources here would duplicate the consumer's identity at the cost of a
	 * full tree walk on every cache-key computation. Content freshness is
	 * validated separately by each cache's watch snapshot or input hash, and
	 * keys must not depend on whether sources happen to be constructed yet.
	 *
	 * @param string $scope Cache scope.
	 * @param array  $discovery_contexts Discovery contexts named for key hygiene.
	 *
	 * @return string Context hash.
	 */
	public static function hash( string $scope, array $discovery_contexts = array() ): string {
		$identity = array(
			'context'   => self::cache( $scope ),
			'discovery' => array_values(
				array_filter(
					$discovery_contexts,
					static fn( $discovery_context ): bool => is_string( $discovery_context ) && '' !== $discovery_context
				)
			),
		);

		$encoded = wp_json_encode( $identity );

		return hash( 'sha256', false === $encoded ? '' : $encoded );
	}

	/**
	 * Get a filesystem-safe namespace for the consumer runtime selection.
	 *
	 * Namespaces derive from the consumer cache context alone so a request that
	 * hydrates from cache (no sources constructed) and a request that rebuilds
	 * (sources active) resolve the same generated-output locations.
	 *
	 * @param string $scope Runtime scope.
	 * @param array  $discovery_contexts Discovery contexts named for key hygiene.
	 *
	 * @return string Context namespace.
	 */
	public static function namespace( string $scope, array $discovery_contexts = array() ): string {
		$context = self::cache( $scope );

		if ( '' === $context || null === $context || array() === $context ) {
			return 'default';
		}

		$identity = array(
			'context'   => $context,
			'discovery' => array_values(
				array_filter(
					$discovery_contexts,
					static fn( $discovery_context ): bool => is_string( $discovery_context ) && '' !== $discovery_context
				)
			),
		);

		$encoded = wp_json_encode( $identity );

		return substr( hash( 'sha256', false === $encoded ? '' : $encoded ), 0, 20 );
	}

	/**
	 * Resolve a generated output path.
	 *
	 * @param string $suggested_path Default output path beside the source.
	 * @param string $source_path Source file or directory.
	 * @param string $scope Output scope.
	 * @param array  $metadata Output metadata.
	 *
	 * @return string Resolved output path.
	 */
	public static function output_path( string $suggested_path, string $source_path, string $scope, array $metadata = array() ): string {
		$entry     = Discovery_Sources::entry_for_physical_path( $source_path );
		$read_only = $entry && Discovery_Sources::entry_is_read_only( $entry );

		if ( $read_only ) {
			$uploads        = wp_upload_dir();
			$source_dir     = is_dir( $source_path ) ? $source_path : dirname( $source_path );
			$suggested_path = rtrim( wp_normalize_path( $uploads['basedir'] ), '/' )
				. '/blockstudio/generated/'
				. self::namespace( 'generated', array( 'blocks' ) )
				. '/'
				. substr( hash( 'sha256', wp_normalize_path( $source_dir ) ), 0, 20 );
		}

		/**
		 * Filter generated output paths.
		 *
		 * Alternate runtimes should redirect output for inherited or read-only
		 * sources to a writable context-specific directory.
		 *
		 * @since 7.5.0
		 *
		 * @param string $suggested_path Default output path.
		 * @param string $source_path Source path.
		 * @param string $scope Output scope.
		 * @param array  $metadata Output metadata.
		 */
		$path = apply_filters( 'blockstudio/generated_output/path', $suggested_path, $source_path, $scope, $metadata );

		return is_string( $path ) && '' !== $path ? wp_normalize_path( $path ) : wp_normalize_path( $suggested_path );
	}

	/**
	 * Resolve a public URL for a physical file.
	 *
	 * @param string $url Default URL.
	 * @param string $path Physical file path.
	 * @param string $scope URL scope.
	 *
	 * @return string Resolved URL.
	 */
	public static function file_url( string $url, string $path, string $scope = 'asset' ): string {
		/**
		 * Filter the public URL for a physical runtime file.
		 *
		 * @since 7.5.0
		 *
		 * @param string $url Default URL.
		 * @param string $path Physical file path.
		 * @param string $scope URL scope.
		 */
		$filtered = apply_filters( 'blockstudio/files/url', $url, $path, $scope );

		return is_string( $filtered ) ? $filtered : $url;
	}
}
