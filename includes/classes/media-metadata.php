<?php
/**
 * Media metadata class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Reads deterministic image metadata from a theme's assets/media.json file.
 *
 * @since 7.6.0
 */
final class Media_Metadata {

	/**
	 * Cached manifests by normalized theme root.
	 *
	 * @var array<string, array{fingerprint:string,data:array<string, mixed>}>
	 */
	private static array $cache = array();

	/**
	 * Get the media manifest for a theme root.
	 *
	 * @param string $theme_root Absolute theme root.
	 *
	 * @return array<string, mixed> Media manifest.
	 */
	public static function for_theme_root( string $theme_root ): array {
		$theme_root  = self::normalize( $theme_root );
		$path        = $theme_root . '/assets/media.json';
		$fingerprint = self::fingerprint( $path );

		if (
			isset( self::$cache[ $theme_root ] ) &&
			self::$cache[ $theme_root ]['fingerprint'] === $fingerprint
		) {
			return self::$cache[ $theme_root ]['data'];
		}

		$data = self::empty_manifest();
		if ( is_file( $path ) && is_readable( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a theme-owned metadata manifest.
			$decoded = json_decode( (string) file_get_contents( $path ), true );
			if ( is_array( $decoded ) ) {
				$data = array(
					'version'     => (int) ( $decoded['version'] ?? 1 ),
					'themeAssets' => is_array( $decoded['themeAssets'] ?? null ) ? $decoded['themeAssets'] : array(),
					'attachments' => is_array( $decoded['attachments'] ?? null ) ? $decoded['attachments'] : array(),
				);
			}
		}

		self::$cache[ $theme_root ] = array(
			'fingerprint' => $fingerprint,
			'data'        => $data,
		);

		return $data;
	}

	/**
	 * Get metadata for a theme asset.
	 *
	 * @param string $theme_root   Absolute theme root.
	 * @param string $relative_path Theme-relative asset path.
	 *
	 * @return array<string, mixed>|null Asset metadata.
	 */
	public static function theme_asset( string $theme_root, string $relative_path ): ?array {
		$manifest = self::for_theme_root( $theme_root );
		$assets   = $manifest['themeAssets'];
		$key      = ltrim( str_replace( '\\', '/', $relative_path ), '/' );
		$value    = is_array( $assets ) ? ( $assets[ $key ] ?? null ) : null;

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Get metadata for a WordPress attachment.
	 *
	 * @param string $theme_root   Absolute theme root.
	 * @param int    $attachment_id Attachment ID.
	 *
	 * @return array<string, mixed>|null Attachment metadata.
	 */
	public static function attachment( string $theme_root, int $attachment_id ): ?array {
		$manifest    = self::for_theme_root( $theme_root );
		$attachments = $manifest['attachments'];
		$value       = is_array( $attachments ) ? ( $attachments[ (string) $attachment_id ] ?? null ) : null;

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Clear the metadata cache.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$cache = array();
	}

	/**
	 * Empty manifest.
	 *
	 * @return array{version:int,themeAssets:array,attachments:array} Empty manifest.
	 */
	private static function empty_manifest(): array {
		return array(
			'version'     => 1,
			'themeAssets' => array(),
			'attachments' => array(),
		);
	}

	/**
	 * Normalize a filesystem path.
	 *
	 * @param string $path Filesystem path.
	 *
	 * @return string Normalized path.
	 */
	private static function normalize( string $path ): string {
		$path = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $path ) : str_replace( '\\', '/', $path );

		return rtrim( $path, '/' );
	}

	/**
	 * Fingerprint a manifest file.
	 *
	 * @param string $path Manifest path.
	 *
	 * @return string File fingerprint.
	 */
	private static function fingerprint( string $path ): string {
		clearstatcache( true, $path );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return 'missing';
		}

		$hash = hash_file( 'sha256', $path );

		return is_string( $hash ) ? $hash : 'unreadable';
	}
}
