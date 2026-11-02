<?php
/**
 * Page renderer registry.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use InvalidArgumentException;
use RuntimeException;

/**
 * Registry of renderers that turn a page or layout entry file into HTML.
 *
 * Blockstudio stays agnostic of the rendering technology. A renderer claims
 * entry file extensions and layout file names and receives one request per
 * render. Registration happens on the `blockstudio/pages/renderers` action,
 * which fires once before the first discovery or render of a request, so
 * explicit synchronization and frontend requests see the same registry.
 *
 * @since 7.7.11
 */
final class Page_Renderers {

	/**
	 * Registered renderer definitions indexed by id.
	 *
	 * @var array<string, array{id:string,extensions:array<int,string>,layouts:array<int,string>,render:callable}>
	 */
	private static array $renderers = array();

	/**
	 * Whether the registration action has fired.
	 *
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Register a renderer.
	 *
	 * @since 7.7.11
	 *
	 * @param string $id         Lowercase renderer id, such as `react`.
	 * @param array  $definition Renderer definition: `extensions` (entry file
	 *                           extensions), `layouts` (layout file names) and
	 *                           `render` (a callable receiving the request array
	 *                           and returning `array{html: string, dependencies:
	 *                           list<string>, assets?: list<array{path: string,
	 *                           type?: string}>}`).
	 *
	 * @throws InvalidArgumentException When the definition is invalid or conflicts with a registered renderer.
	 *
	 * @return void
	 */
	public static function register( string $id, array $definition ): void {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $id ) ) {
			throw new InvalidArgumentException( sprintf( 'Page renderer id "%s" must be lowercase letters, digits, hyphens or underscores.', esc_html( $id ) ) );
		}

		if ( isset( self::$renderers[ $id ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Page renderer "%s" is already registered.', esc_html( $id ) ) );
		}

		$render = $definition['render'] ?? null;

		if ( ! is_callable( $render ) ) {
			throw new InvalidArgumentException( sprintf( 'Page renderer "%s" needs a callable render.', esc_html( $id ) ) );
		}

		$extensions = array();

		foreach ( self::string_list( $definition['extensions'] ?? array(), $id, 'extensions' ) as $extension ) {
			$extension = strtolower( ltrim( $extension, '.' ) );

			if ( 1 !== preg_match( '/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $extension ) ) {
				throw new InvalidArgumentException( sprintf( 'Page renderer "%1$s" has an invalid extension "%2$s".', esc_html( $id ), esc_html( $extension ) ) );
			}

			$owner = self::extension_owner( $extension );

			if ( null !== $owner || in_array( $extension, $extensions, true ) ) {
				throw new InvalidArgumentException( sprintf( 'Page renderer "%1$s" claims the extension "%2$s", which "%3$s" already claims.', esc_html( $id ), esc_html( $extension ), esc_html( $owner ?? $id ) ) );
			}

			$extensions[] = $extension;
		}

		$layouts = array();

		foreach ( self::string_list( $definition['layouts'] ?? array(), $id, 'layouts' ) as $layout ) {
			if (
				1 !== preg_match( '/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)+$/', $layout ) ||
				'layout.php' === $layout ||
				'layout.data.php' === $layout
			) {
				throw new InvalidArgumentException( sprintf( 'Page renderer "%1$s" has an invalid layout name "%2$s".', esc_html( $id ), esc_html( $layout ) ) );
			}

			$owner = self::layout_owner( $layout );

			if ( null !== $owner || in_array( $layout, $layouts, true ) ) {
				throw new InvalidArgumentException( sprintf( 'Page renderer "%1$s" claims the layout "%2$s", which "%3$s" already claims.', esc_html( $id ), esc_html( $layout ), esc_html( $owner ?? $id ) ) );
			}

			$layouts[] = $layout;
		}

		self::$renderers[ $id ] = array(
			'id'         => $id,
			'extensions' => $extensions,
			'layouts'    => $layouts,
			'render'     => $render,
		);
	}

	/**
	 * Get a renderer definition.
	 *
	 * @since 7.7.11
	 *
	 * @param string $id Renderer id.
	 *
	 * @return array|null Renderer definition.
	 */
	public static function get( string $id ): ?array {
		self::load();

		return self::$renderers[ $id ] ?? null;
	}

	/**
	 * Find the renderer that claims an entry file by its extension.
	 *
	 * The longest matching extension wins, so `page.blade.tsx` can belong to a
	 * renderer that claims `blade.tsx` while another one claims `tsx`.
	 *
	 * @since 7.7.11
	 *
	 * @param string $path Entry path or file name.
	 *
	 * @return string|null Renderer id.
	 */
	public static function for_entry( string $path ): ?string {
		self::load();

		$basename = strtolower( basename( wp_normalize_path( $path ) ) );
		$match    = null;
		$length   = 0;

		foreach ( self::$renderers as $id => $renderer ) {
			foreach ( $renderer['extensions'] as $extension ) {
				if ( str_ends_with( $basename, '.' . $extension ) && strlen( $extension ) > $length ) {
					$match  = $id;
					$length = strlen( $extension );
				}
			}
		}

		return $match;
	}

	/**
	 * Find the renderer that claims a layout file name.
	 *
	 * @since 7.7.11
	 *
	 * @param string $path Layout path or file name.
	 *
	 * @return string|null Renderer id.
	 */
	public static function for_layout( string $path ): ?string {
		self::load();

		return self::layout_owner( basename( wp_normalize_path( $path ) ) );
	}

	/**
	 * Get every layout file name a renderer claims.
	 *
	 * @since 7.7.11
	 *
	 * @return array<string, string> Renderer ids indexed by layout file name.
	 */
	public static function layout_names(): array {
		self::load();

		$names = array();

		foreach ( self::$renderers as $id => $renderer ) {
			foreach ( $renderer['layouts'] as $layout ) {
				$names[ $layout ] = $id;
			}
		}

		ksort( $names, SORT_STRING );

		return $names;
	}

	/**
	 * Get the registered renderer ids.
	 *
	 * @since 7.7.11
	 *
	 * @return array<int, string> Renderer ids.
	 */
	public static function ids(): array {
		self::load();

		return array_keys( self::$renderers );
	}

	/**
	 * Call a renderer and validate its result.
	 *
	 * @since 7.7.11
	 *
	 * @param array $request Render request with `kind`, `renderer`, `entry`,
	 *                       `data`, `page`, `post_id`, `slot` and `context`.
	 *
	 * @throws RuntimeException When the renderer is unknown or returns an invalid result.
	 *
	 * @return array{html:string,dependencies:array<int,string>,assets:array<int,array{path:string,type?:string,version?:string}>} Render result.
	 */
	public static function render( array $request ): array {
		$id       = is_string( $request['renderer'] ?? null ) ? $request['renderer'] : '';
		$renderer = self::get( $id );

		if ( null === $renderer ) {
			throw new RuntimeException( sprintf( 'Page renderer "%s" is not registered.', esc_html( $id ) ) );
		}

		$result = call_user_func( $renderer['render'], $request );

		if ( ! is_array( $result ) || ! is_string( $result['html'] ?? null ) ) {
			throw new RuntimeException( sprintf( 'Page renderer "%s" must return an array with an html string.', esc_html( $id ) ) );
		}

		$dependencies = array();

		foreach ( is_array( $result['dependencies'] ?? null ) ? $result['dependencies'] : array() as $dependency ) {
			if ( is_string( $dependency ) && '' !== $dependency ) {
				$dependencies[] = wp_normalize_path( $dependency );
			}
		}

		$assets = array();

		foreach ( is_array( $result['assets'] ?? null ) ? $result['assets'] : array() as $asset ) {
			if ( is_string( $asset ) ) {
				$asset = array( 'path' => $asset );
			}

			if ( ! is_array( $asset ) || ! is_string( $asset['path'] ?? null ) || '' === $asset['path'] ) {
				throw new RuntimeException( sprintf( 'Page renderer "%s" returned an asset without a path.', esc_html( $id ) ) );
			}

			$normalized = array( 'path' => wp_normalize_path( $asset['path'] ) );

			foreach ( array( 'type', 'version' ) as $key ) {
				if ( is_scalar( $asset[ $key ] ?? null ) && '' !== (string) $asset[ $key ] ) {
					$normalized[ $key ] = (string) $asset[ $key ];
				}
			}

			$assets[] = $normalized;
		}

		return array(
			'html'         => $result['html'],
			'dependencies' => array_values( array_unique( $dependencies ) ),
			'assets'       => $assets,
		);
	}

	/**
	 * Fire the registration action once.
	 *
	 * @since 7.7.11
	 *
	 * @return void
	 */
	public static function load(): void {
		if ( self::$loaded ) {
			return;
		}

		self::$loaded = true;

		/**
		 * Fires once per request so themes and plugins can register page renderers.
		 *
		 * Register with `Blockstudio\Page_Renderers::register()`.
		 *
		 * @since 7.7.11
		 */
		do_action( 'blockstudio/pages/renderers' );
	}

	/**
	 * Remove every renderer and allow the registration action to fire again.
	 *
	 * @since 7.7.11
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$renderers = array();
		self::$loaded    = false;
	}

	/**
	 * Read a list of strings from a definition.
	 *
	 * @param mixed  $value Definition value.
	 * @param string $id    Renderer id.
	 * @param string $key   Definition key.
	 *
	 * @throws InvalidArgumentException When the value is not a list of strings.
	 *
	 * @return array<int, string> Strings.
	 */
	private static function string_list( mixed $value, string $id, string $key ): array {
		if ( is_string( $value ) ) {
			$value = array( $value );
		}

		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( sprintf( 'Page renderer "%1$s" needs %2$s as a list of strings.', esc_html( $id ), esc_html( $key ) ) );
		}

		$strings = array();

		foreach ( $value as $item ) {
			if ( ! is_string( $item ) || '' === trim( $item ) ) {
				throw new InvalidArgumentException( sprintf( 'Page renderer "%1$s" needs %2$s as a list of strings.', esc_html( $id ), esc_html( $key ) ) );
			}

			$strings[] = trim( $item );
		}

		return $strings;
	}

	/**
	 * Find the renderer that claims an extension.
	 *
	 * @param string $extension Extension.
	 *
	 * @return string|null Renderer id.
	 */
	private static function extension_owner( string $extension ): ?string {
		foreach ( self::$renderers as $id => $renderer ) {
			if ( in_array( $extension, $renderer['extensions'], true ) ) {
				return $id;
			}
		}

		return null;
	}

	/**
	 * Find the renderer that claims a layout file name.
	 *
	 * @param string $layout Layout file name.
	 *
	 * @return string|null Renderer id.
	 */
	private static function layout_owner( string $layout ): ?string {
		foreach ( self::$renderers as $id => $renderer ) {
			if ( in_array( $layout, $renderer['layouts'], true ) ) {
				return $id;
			}
		}

		return null;
	}
}
