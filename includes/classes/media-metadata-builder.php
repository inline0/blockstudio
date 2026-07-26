<?php
/**
 * Media metadata builder class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Builds deterministic theme-asset and attachment image metadata.
 *
 * @since 7.6.0
 */
final class Media_Metadata_Builder {

	/**
	 * Supported image extensions.
	 *
	 * @var string[]
	 */
	private const IMAGE_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg' );

	/**
	 * Build a media manifest.
	 *
	 * @param string      $theme_root      Absolute theme root.
	 * @param bool        $include_library Whether to include WordPress attachments.
	 * @param string|null $prefix          Optional path below assets to scan.
	 *
	 * @return array{version:int,themeAssets:array<string, array<string, mixed>>,attachments:array<string, array<string, mixed>>} Manifest.
	 */
	public function build( string $theme_root, bool $include_library = true, ?string $prefix = null ): array {
		$theme_root = $this->normalize( $theme_root );
		$prefix     = $this->normalize_prefix( $prefix );

		return array(
			'version'     => 1,
			'themeAssets' => $this->theme_assets( $theme_root, $prefix ),
			'attachments' => $include_library ? $this->attachments() : array(),
		);
	}

	/**
	 * Write a media manifest.
	 *
	 * @param string      $theme_root      Absolute theme root.
	 * @param bool        $include_library Whether to include WordPress attachments.
	 * @param string|null $prefix          Optional path below assets to scan.
	 * @param string|null $target_path     Optional explicit output path.
	 *
	 * @return string Written manifest path.
	 * @throws \RuntimeException When the manifest directory or file cannot be written.
	 */
	public function write( string $theme_root, bool $include_library = true, ?string $prefix = null, ?string $target_path = null ): string {
		$theme_root = $this->normalize( $theme_root );
		$target     = null !== $target_path && '' !== trim( $target_path )
			? $this->normalize( $target_path )
			: $theme_root . '/assets/media.json';
		$directory  = dirname( $target );

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			throw new \RuntimeException( sprintf( 'Unable to create media metadata directory: %s', esc_html( $directory ) ) );
		}

		$encoded = wp_json_encode(
			$this->build( $theme_root, $include_library, $prefix ),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a deterministic theme-owned build artifact.
		if ( ! is_string( $encoded ) || false === file_put_contents( $target, $encoded . "\n" ) ) {
			throw new \RuntimeException( sprintf( 'Unable to write media metadata: %s', esc_html( $target ) ) );
		}

		Media_Metadata::reset();

		return $target;
	}

	/**
	 * Scan theme assets.
	 *
	 * @param string      $theme_root Absolute theme root.
	 * @param string|null $prefix     Optional assets-relative prefix.
	 *
	 * @return array<string, array<string, mixed>> Asset metadata.
	 */
	private function theme_assets( string $theme_root, ?string $prefix ): array {
		$assets_root = $theme_root . '/assets';
		if ( ! is_dir( $assets_root ) ) {
			return array();
		}

		$assets   = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $assets_root, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof \SplFileInfo || ! $file->isFile() ) {
				continue;
			}
			if ( ! in_array( strtolower( $file->getExtension() ), self::IMAGE_EXTENSIONS, true ) ) {
				continue;
			}

			$path     = $this->normalize( $file->getPathname() );
			$relative = 'assets/' . ltrim( substr( $path, strlen( $assets_root ) ), '/' );
			if ( null !== $prefix && 'assets/' . $prefix !== $relative && ! str_starts_with( $relative, 'assets/' . $prefix . '/' ) ) {
				continue;
			}

			$metadata = $this->file_metadata( $path );
			if ( null !== $metadata ) {
				$assets[ $relative ] = $metadata;
			}
		}

		ksort( $assets, SORT_STRING );

		return $assets;
	}

	/**
	 * Build WordPress attachment metadata.
	 *
	 * Keys are attachment IDs. PHP normalises numeric string keys back to int,
	 * so the cast at the assignment documents intent rather than changing the
	 * key type.
	 *
	 * @return array<int|string, array<string, mixed>> Attachment metadata.
	 */
	private function attachments(): array {
		if ( ! function_exists( 'get_posts' ) || ! function_exists( 'wp_get_attachment_metadata' ) || ! function_exists( 'wp_get_attachment_url' ) ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$attachments = array();
		foreach ( $ids as $id ) {
			$id       = (int) $id;
			$metadata = $id > 0 ? wp_get_attachment_metadata( $id ) : false;
			$url      = $id > 0 ? wp_get_attachment_url( $id ) : false;
			$width    = is_array( $metadata ) ? (int) ( $metadata['width'] ?? 0 ) : 0;
			$height   = is_array( $metadata ) ? (int) ( $metadata['height'] ?? 0 ) : 0;

			if ( $width <= 0 || $height <= 0 || ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$mime                        = function_exists( 'get_post_mime_type' ) ? get_post_mime_type( $id ) : '';
			$attachments[ (string) $id ] = array(
				'width'  => $width,
				'height' => $height,
				'type'   => 'image',
				'mime'   => is_string( $mime ) ? $mime : '',
				'url'    => $url,
			);
		}

		ksort( $attachments, SORT_NATURAL );

		return $attachments;
	}

	/**
	 * Read image dimensions and mime type.
	 *
	 * @param string $path Image path.
	 *
	 * @return array<string, mixed>|null Image metadata.
	 */
	private function file_metadata( string $path ): ?array {
		if ( 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$dimensions = $this->svg_dimensions( $path );

			return null === $dimensions
				? null
				: array(
					'width'  => $dimensions[0],
					'height' => $dimensions[1],
					'type'   => 'image',
					'mime'   => 'image/svg+xml',
				);
		}

		set_error_handler( static fn(): bool => true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Invalid image files are a supported input.
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_getimagesize -- Reading local theme image dimensions.
			$size = getimagesize( $path );
		} finally {
			restore_error_handler();
		}

		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			return null;
		}

		return array(
			'width'  => (int) $size[0],
			'height' => (int) $size[1],
			'type'   => 'image',
			'mime'   => is_string( $size['mime'] ?? null ) ? $size['mime'] : '',
		);
	}

	/**
	 * Read SVG width and height.
	 *
	 * @param string $path SVG path.
	 *
	 * @return array{int,int}|null Dimensions.
	 */
	private function svg_dimensions( string $path ): ?array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the opening tag of a local SVG.
		$head = file_get_contents( $path, false, null, 0, 4096 );
		if ( ! is_string( $head ) || ! preg_match( '/<svg\b[^>]*>/i', $head, $tag ) ) {
			return null;
		}

		$dimensions = array();
		foreach ( array( 'width', 'height' ) as $attribute ) {
			if ( preg_match( '/\b' . $attribute . '\s*=\s*(["\'])\s*([0-9]*\.?[0-9]+)(?:px)?\s*\1/i', $tag[0], $match ) ) {
				$dimensions[] = (float) $match[2];
			}
		}
		if ( 2 !== count( $dimensions ) && preg_match( '/\bviewBox\s*=\s*(["\'])(.*?)\1/i', $tag[0], $view_box ) ) {
			$values = preg_split( '/[\s,]+/', trim( $view_box[2] ) );
			if ( is_array( $values ) && 4 === count( $values ) && is_numeric( $values[2] ) && is_numeric( $values[3] ) ) {
				$dimensions = array( (float) $values[2], (float) $values[3] );
			}
		}
		if ( 2 !== count( $dimensions ) || $dimensions[0] <= 0 || $dimensions[1] <= 0 ) {
			return null;
		}

		return array( (int) round( $dimensions[0] ), (int) round( $dimensions[1] ) );
	}

	/**
	 * Normalize a path.
	 *
	 * @param string $path Path.
	 *
	 * @return string Normalized path.
	 */
	private function normalize( string $path ): string {
		$path = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $path ) : str_replace( '\\', '/', $path );

		return rtrim( $path, '/' );
	}

	/**
	 * Normalize an assets-relative prefix.
	 *
	 * @param string|null $prefix Prefix.
	 *
	 * @return string|null Normalized prefix.
	 */
	private function normalize_prefix( ?string $prefix ): ?string {
		if ( null === $prefix ) {
			return null;
		}

		$prefix = trim( str_replace( '\\', '/', $prefix ), '/' );
		if ( str_starts_with( $prefix, 'assets/' ) ) {
			$prefix = substr( $prefix, 7 );
		}

		return '' === $prefix ? null : $prefix;
	}
}
