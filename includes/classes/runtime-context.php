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
	 * Get the complete generic runtime identity for one cache scope.
	 *
	 * The identity covers platform, site, theme, settings, named discovery
	 * contexts, explicit dependency fingerprints, and the consumer-provided
	 * Runtime_Context value. Expensive source traversal is never implicit:
	 * callers that already own a discovery snapshot pass it in $dependencies.
	 * This keeps cold-cache lookup cheap and makes changed-only callers isolate
	 * only the sources they selected.
	 *
	 * @param string              $scope              Runtime scope.
	 * @param string[]            $discovery_contexts Named discovery contexts.
	 * @param array<string,mixed> $dependencies       Explicit dependency identity.
	 *
	 * @return array<string,mixed> Stable runtime identity.
	 */
	public static function identity( string $scope, array $discovery_contexts = array(), array $dependencies = array() ): array {
		$active_plugins = function_exists( 'get_option' )
			? array_values( array_filter( (array) get_option( 'active_plugins', array() ), 'is_string' ) )
			: array();

		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			$active_plugins = array_merge(
				$active_plugins,
				array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) )
			);
		}
		$active_plugins = array_values( array_unique( $active_plugins ) );
		sort( $active_plugins, SORT_STRING );

		$discovery_contexts = array_values(
			array_unique(
				array_filter(
					$discovery_contexts,
					static fn( $context ): bool => is_string( $context ) && '' !== trim( $context )
				)
			)
		);
		sort( $discovery_contexts, SORT_STRING );

		$identity = array(
			'format'       => 1,
			'scope'        => $scope,
			'blockstudio'  => defined( 'BLOCKSTUDIO_VERSION' ) ? (string) BLOCKSTUDIO_VERSION : '',
			'settings'     => class_exists( Settings::class ) ? Settings::fingerprint() : '',
			'runtime'      => class_exists( Runtime_Settings::class ) ? Runtime_Settings::current()->hash() : '',
			'wordpress'    => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
			'php'          => PHP_VERSION,
			'network'      => function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 0,
			'blog'         => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0,
			'home'         => function_exists( 'home_url' ) ? (string) home_url( '/' ) : '',
			'site'         => function_exists( 'site_url' ) ? (string) site_url( '/' ) : '',
			'stylesheet'   => function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '',
			'template'     => function_exists( 'get_template' ) ? (string) get_template() : '',
			'plugins'      => $active_plugins,
			'context'      => self::cache( $scope ),
			'discovery'    => $discovery_contexts,
			'dependencies' => self::normalize_identity( $dependencies ),
		);

		/**
		 * Filter the complete generic runtime identity.
		 *
		 * Use this only for host facts that affect every object in the scope.
		 * Item-specific source fingerprints belong in the dependency argument.
		 *
		 * @since 7.6.0
		 *
		 * @param array<string,mixed> $identity Runtime identity.
		 * @param string              $scope    Runtime scope.
		 */
		$filtered = apply_filters( 'blockstudio/runtime/identity', $identity, $scope );

		return is_array( $filtered ) ? self::normalize_identity( $filtered ) : self::normalize_identity( $identity );
	}

	/**
	 * Get a stable context hash.
	 *
	 * @param string              $scope              Cache scope.
	 * @param string[]            $discovery_contexts Discovery contexts named for key hygiene.
	 * @param array<string,mixed> $dependencies       Explicit dependency identity.
	 *
	 * @return string Context hash.
	 */
	public static function hash( string $scope, array $discovery_contexts = array(), array $dependencies = array() ): string {
		$encoded = wp_json_encode(
			self::identity( $scope, $discovery_contexts, $dependencies ),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return hash( 'sha256', false === $encoded ? '' : $encoded );
	}

	/**
	 * Get a filesystem-safe namespace for the consumer runtime selection.
	 *
	 * @param string              $scope              Runtime scope.
	 * @param string[]            $discovery_contexts Discovery contexts named for key hygiene.
	 * @param array<string,mixed> $dependencies       Explicit dependency identity.
	 *
	 * @return string Context namespace.
	 */
	public static function namespace( string $scope, array $discovery_contexts = array(), array $dependencies = array() ): string {
		return substr( self::hash( $scope, $discovery_contexts, $dependencies ), 0, 20 );
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
			$source_dir     = is_dir( $source_path ) ? $source_path : dirname( $source_path );
			$suggested_path = Runtime_Cache::directory( 'generated' )
				. '/'
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

	/**
	 * Normalize nested identity input.
	 *
	 * @param mixed $value Identity value.
	 *
	 * @return mixed Normalized value.
	 */
	private static function normalize_identity( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::normalize_identity( $item );
		}

		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}

		return $value;
	}
}
