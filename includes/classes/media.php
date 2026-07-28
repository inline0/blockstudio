<?php
/**
 * Media runtime class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders stable images and registers the optional lazy-loading runtime.
 *
 * @since 7.6.0
 */
final class Media {

	/**
	 * Script and style handle.
	 */
	public const HANDLE = 'blockstudio-media';

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the optional media runtime.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		if ( Runtime_Settings::current()->enabled( 'media/lazy' ) ) {
			add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		}
	}

	/**
	 * Enqueue the media runtime.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$script_path = BLOCKSTUDIO_DIR . '/includes/assets/runtime/media.js';
		$style_path  = BLOCKSTUDIO_DIR . '/includes/assets/runtime/media.css';
		$script_url  = BLOCKSTUDIO_URL . 'includes/assets/runtime/media.js';
		$style_url   = BLOCKSTUDIO_URL . 'includes/assets/runtime/media.css';

		if ( is_file( $style_path ) ) {
			wp_enqueue_style( self::HANDLE, $style_url, array(), (string) filemtime( $style_path ) );
		}
		if ( ! is_file( $script_path ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			$script_url,
			array(),
			(string) filemtime( $script_path ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_add_inline_script(
			self::HANDLE,
			'window.BlockstudioMedia=Object.assign({rootMargin:' .
				wp_json_encode( (string) Runtime_Settings::current()->value( 'media/rootMargin', '300px' ) ) .
				'},window.BlockstudioMedia||{});',
			'before'
		);
	}

	/**
	 * Render an image with stable dimensions.
	 *
	 * Supported inputs include a theme-relative src or attachmentId, explicit
	 * dimensions, alt, class, style, eager, size, and picture sources.
	 *
	 * @param array<string, mixed> $args Image arguments.
	 *
	 * @return string Image markup.
	 */
	public static function image( array $args ): string {
		$resolved = self::resolve_image( $args );
		if ( null === $resolved ) {
			return '';
		}

		$settings  = Runtime_Settings::current();
		$width     = (int) ( $resolved['width'] ?? 0 );
		$height    = (int) ( $resolved['height'] ?? 0 );
		$eager     = ! empty( $args['eager'] );
		$lazy      = ! $eager && $settings->enabled( 'media/lazy' );
		$skeleton  = $lazy && $settings->enabled( 'media/skeleton' );
		$class     = trim( (string) ( $args['class'] ?? '' ) );
		$figure    = trim( 'blockstudio-media' . ( $skeleton ? ' blockstudio-media--skeleton' : '' ) . ( $class ? ' ' . $class : '' ) );
		$style     = trim( (string) ( $args['style'] ?? '' ) );
		$img_attrs = array(
			'alt'      => (string) ( $args['alt'] ?? '' ),
			'class'    => 'blockstudio-media__image',
			'decoding' => 'async',
			'loading'  => $eager ? 'eager' : 'lazy',
		);

		if ( $width > 0 && $height > 0 ) {
			$style = trim( 'aspect-ratio:' . $width . '/' . $height . ';' . $style );
		}
		if ( $width > 0 ) {
			$img_attrs['width'] = (string) $width;
		}
		if ( $height > 0 ) {
			$img_attrs['height'] = (string) $height;
		}
		if ( $lazy ) {
			$img_attrs['src']      = self::placeholder( $width, $height );
			$img_attrs['data-src'] = (string) $resolved['url'];
		} else {
			$img_attrs['src'] = (string) $resolved['url'];
		}

		$image   = '<img ' . self::attributes( $img_attrs ) . ' />';
		$sources = self::sources( $args, $lazy );
		if ( '' !== $sources ) {
			$image = '<picture>' . $sources . $image . '</picture>';
		}

		$figure_attrs = array( 'class' => $figure );
		if ( '' !== $style ) {
			$figure_attrs['style'] = $style;
		}
		if ( $lazy ) {
			$figure_attrs['data-blockstudio-media'] = '';
			$figure_attrs['data-state']             = 'loading';
		}

		return '<figure ' . self::attributes( $figure_attrs ) . '>' . $image . '</figure>';
	}

	/**
	 * Resolve an image source and metadata.
	 *
	 * @param array<string, mixed> $args Image arguments.
	 *
	 * @return array{url:string,width:int,height:int,mime:string}|null Resolved image.
	 */
	private static function resolve_image( array $args ): ?array {
		$theme_root = get_stylesheet_directory();
		$metadata   = null;
		$url        = '';

		if ( isset( $args['attachmentId'] ) ) {
			$attachment_id = (int) $args['attachmentId'];
			if ( $attachment_id <= 0 ) {
				return null;
			}

			$metadata = Media_Metadata::attachment( $theme_root, $attachment_id );
			$size     = $args['size'] ?? 'full';
			$image    = wp_get_attachment_image_src(
				$attachment_id,
				is_string( $size ) || is_array( $size ) ? $size : 'full'
			);
			$url      = is_array( $image ) && is_string( $image[0] ?? null ) && '' !== $image[0]
				? $image[0]
				: ( is_array( $metadata ) && is_string( $metadata['url'] ?? null )
					? $metadata['url']
					: (string) wp_get_attachment_url( $attachment_id ) );
			if ( is_array( $image ) ) {
				$metadata = array_merge(
					is_array( $metadata ) ? $metadata : array(),
					array(
						'width'  => (int) ( $image[1] ?? 0 ),
						'height' => (int) ( $image[2] ?? 0 ),
					)
				);
			}
		} else {
			$src = trim( (string) ( $args['src'] ?? '' ) );
			if ( '' === $src ) {
				return null;
			}

			if ( preg_match( '#^https?://#i', $src ) || '/' === $src[0] ) {
				$url = $src;
			} else {
				$relative = ltrim( str_replace( '\\', '/', $src ), '/' );
				$url      = get_theme_file_uri( $relative );
				$metadata = Media_Metadata::theme_asset( $theme_root, $relative );
			}
		}

		if ( '' === $url ) {
			return null;
		}

		return array(
			'url'    => $url,
			'width'  => (int) ( $args['width'] ?? ( is_array( $metadata ) ? ( $metadata['width'] ?? 0 ) : 0 ) ),
			'height' => (int) ( $args['height'] ?? ( is_array( $metadata ) ? ( $metadata['height'] ?? 0 ) : 0 ) ),
			'mime'   => (string) ( is_array( $metadata ) ? ( $metadata['mime'] ?? '' ) : '' ),
		);
	}

	/**
	 * Render picture source tags.
	 *
	 * @param array<string, mixed> $args Image arguments.
	 * @param bool                 $lazy Whether sources load lazily.
	 *
	 * @return string Source markup.
	 */
	private static function sources( array $args, bool $lazy ): string {
		$sources = $args['sources'] ?? array();
		if ( ! is_array( $sources ) ) {
			return '';
		}

		$tags = array();
		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$srcset = trim( (string) ( $source['srcset'] ?? $source['src'] ?? '' ) );
			if ( '' === $srcset ) {
				continue;
			}

			$attrs = array( $lazy ? 'data-srcset' : 'srcset' => $srcset );
			foreach ( array( 'media', 'type', 'sizes' ) as $key ) {
				if ( isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) && '' !== (string) $source[ $key ] ) {
					$attrs[ $key ] = (string) $source[ $key ];
				}
			}
			$tags[] = '<source ' . self::attributes( $attrs ) . ' />';
		}

		return implode( '', $tags );
	}

	/**
	 * Build a transparent placeholder preserving dimensions.
	 *
	 * @param int $width  Width.
	 * @param int $height Height.
	 *
	 * @return string Data URL.
	 */
	private static function placeholder( int $width, int $height ): string {
		$svg = sprintf(
			'<svg width="%d" height="%d" xmlns="http://www.w3.org/2000/svg"></svg>',
			max( 1, $width ),
			max( 1, $height )
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding a generated transparent SVG data URL.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Serialize safe HTML attributes.
	 *
	 * @param array<string, string> $attributes Attributes.
	 *
	 * @return string Attribute markup.
	 */
	private static function attributes( array $attributes ): string {
		$pairs = array();
		foreach ( $attributes as $name => $value ) {
			$pairs[] = '' === $value
				? esc_attr( $name )
				: sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		return implode( ' ', $pairs );
	}
}
