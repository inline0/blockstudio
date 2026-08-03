<?php
/**
 * UI class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Registers bundled UI components when enabled via blockstudio.json.
 *
 * @since 7.3.0
 */
class Ui {

	/**
	 * Bundled UI block source marker.
	 */
	private const BLOCKS_PATH_MARKER = '/includes/ui/blocks/';


	/**
	 * Whether the hooks have already been registered.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Whether UI blocks have already been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Initialize UI block registration.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$initialized || ! self::enabled() ) {
			return;
		}

		self::$initialized = true;

		add_action( 'init', array( __CLASS__, 'register' ), PHP_INT_MAX - 2 );
	}

	/**
	 * Check whether bundled UI components are enabled.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return true === Settings::get( 'ui/enabled' );
	}

	/**
	 * Register bundled UI component directories.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered || ! self::enabled() || ! class_exists( 'Blockstudio\Build' ) ) {
			return;
		}

		self::$registered = true;

		foreach ( self::directories() as $directory ) {
			Build::init(
				array(
					'dir' => $directory,
				)
			);
		}
	}

	/**
	 * Get bundled UI component directories.
	 *
	 * @return array<string>
	 */
	public static function directories(): array {
		$directories = apply_filters(
			'blockstudio/ui/directories',
			array(
				BLOCKSTUDIO_DIR . '/includes/ui/blocks',
			)
		);

		if ( ! is_array( $directories ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$directories,
				function ( $directory ): bool {
					return is_string( $directory ) && '' !== $directory;
				}
			)
		);
	}

	/**
	 * Return the public bundled UI family inventory.
	 *
	 * Implementation blocks remain visible as dependency metadata, while each
	 * public family is represented by its `root` registration.
	 *
	 * @return array<int, array{
	 *   slug:string,
	 *   title:string,
	 *   rootName:string,
	 *   rootPath:string,
	 *   implementationNames:array<int, string>,
	 *   blocks:array<string, array{
	 *     name:string,
	 *     title:string,
	 *     path:string,
	 *     family:string,
	 *     role:string,
	 *     parent:array<int, string>,
	 *     attributes:array
	 *   }>
	 * }> UI families.
	 */
	public static function inventory(): array {
		$families = array();

		foreach ( self::registrations() as $block ) {
			if ( '' === $block['family'] || 'app' === $block['role'] ) {
				continue;
			}

			$slug = $block['family'];

			if ( ! isset( $families[ $slug ] ) ) {
				$families[ $slug ] = array(
					'slug'                => $slug,
					'title'               => self::humanize( $slug ),
					'rootName'            => '',
					'rootPath'            => '',
					'implementationNames' => array(),
					'blocks'              => array(),
				);
			}

			$families[ $slug ]['blocks'][ $block['name'] ] = $block;
			$families[ $slug ]['implementationNames'][]    = $block['name'];

			if ( 'root' === $block['role'] ) {
				$families[ $slug ]['rootName'] = $block['name'];
				$families[ $slug ]['rootPath'] = $block['path'];
				$families[ $slug ]['title']    = $block['title'];
			}
		}

		foreach ( $families as $slug => &$family ) {
			if ( '' === $family['rootName'] ) {
				unset( $families[ $slug ] );
				continue;
			}

			$family['implementationNames'] = array_values( array_unique( $family['implementationNames'] ) );
			usort(
				$family['implementationNames'],
				static function ( string $left, string $right ) use ( $family ): int {
					if ( $left === $family['rootName'] ) {
						return -1;
					}

					if ( $right === $family['rootName'] ) {
						return 1;
					}

					return strcasecmp( $left, $right );
				}
			);
			ksort( $family['blocks'], SORT_STRING );
		}
		unset( $family );

		$families = array_values( $families );
		usort(
			$families,
			static function ( array $left, array $right ): int {
				$title_order = strcasecmp( $left['title'], $right['title'] );

				return 0 !== $title_order
					? $title_order
					: strcasecmp( $left['slug'], $right['slug'] );
			}
		);

		/**
		 * Filter the public UI family inventory.
		 *
		 * @since 7.6.0
		 *
		 * @param array $families Public bundled UI families.
		 */
		$filtered = apply_filters( 'blockstudio/ui/inventory', $families );

		return is_array( $filtered ) ? array_values( $filtered ) : $families;
	}

	/**
	 * Return one deterministic example declaration for every public UI family.
	 *
	 * Examples are derived from the live registrations: defaults are preserved,
	 * required implementation layers are nested under their declared parents,
	 * and otherwise-empty text fields receive stable illustrative values.
	 *
	 * @return array<int, array{
	 *   id:string,
	 *   family:string,
	 *   title:string,
	 *   rootName:string,
	 *   declaration:array,
	 *   dependencies:array<int, string>,
	 *   provenance:string
	 * }> UI examples.
	 */
	public static function examples(): array {
		$examples = array();

		foreach ( self::inventory() as $family ) {
			$declaration = self::example_declaration(
				$family['rootName'],
				$family['blocks'],
				array()
			);

			$examples[] = array(
				'id'           => 'blockstudio-ui:' . $family['slug'],
				'family'       => $family['slug'],
				'title'        => $family['title'],
				'rootName'     => $family['rootName'],
				'declaration'  => $declaration,
				'dependencies' => $family['implementationNames'],
				'provenance'   => 'blockstudio-ui',
			);
		}

		/**
		 * Filter generated UI examples.
		 *
		 * Callers may replace example data or composition while retaining
		 * canonical block names. Rendering remains owned by Render.
		 *
		 * @since 7.6.0
		 *
		 * @param array $examples UI example records.
		 * @param array $families Public UI inventory.
		 */
		$filtered = apply_filters( 'blockstudio/ui/examples', $examples, self::inventory() );

		return is_array( $filtered ) ? array_values( $filtered ) : $examples;
	}

	/**
	 * Check whether one block belongs to Blockstudio's bundled UI library.
	 *
	 * @param mixed $block Block record or canonical name.
	 *
	 * @return bool Whether the block is bundled UI.
	 */
	public static function is_bundled_block( mixed $block ): bool {
		if ( is_string( $block ) ) {
			$blocks = Build::data();
			$block  = $blocks[ $block ] ?? null;
		}

		$path = self::record_path( $block );

		return self::is_bundled_path( $path );
	}

	/**
	 * Return bundled UI global assets for a selected document.
	 *
	 * @param array<string> $block_names Selected block names.
	 * @param string        $html        Rendered HTML.
	 *
	 * @return array{style:string,script:string} Exact asset markup.
	 */
	public static function global_assets( array $block_names, string $html = '' ): array {
		$uses_ui = str_contains( $html, 'data-bsui-' );

		if ( ! $uses_ui ) {
			foreach ( $block_names as $name ) {
				if ( is_string( $name ) && self::is_bundled_block( $name ) ) {
					$uses_ui = true;
					break;
				}
			}
		}

		if ( ! $uses_ui ) {
			return array(
				'style'  => '',
				'script' => '',
			);
		}

		$root   = BLOCKSTUDIO_DIR . '/includes/ui/blocks/';
		$style  = self::global_asset( $root . 'global-style.css', 'style', 'blockstudio-ui-global' );
		$script = self::global_asset( $root . 'global-script.js', 'script', 'blockstudio-ui-global-script' );

		return array(
			'style'  => $style,
			'script' => $script,
		);
	}

	/**
	 * Normalize all bundled UI registrations.
	 *
	 * @return array<int, array{
	 *   name:string,
	 *   title:string,
	 *   path:string,
	 *   family:string,
	 *   role:string,
	 *   parent:array<int, string>,
	 *   attributes:array
	 * }> Registrations.
	 */
	private static function registrations(): array {
		$registrations = array();

		foreach ( Build::blocks() as $key => $block ) {
			$path = self::record_path( $block );

			if ( ! self::is_bundled_path( $path ) ) {
				continue;
			}

			$name = self::record_string( $block, 'name' );

			if ( '' === $name && is_string( $key ) ) {
				$name = $key;
			}

			$name = strtolower( trim( $name ) );

			if ( ! preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) ) {
				continue;
			}

			$relative = '';
			$position = strpos( $path, self::BLOCKS_PATH_MARKER );

			if ( false !== $position ) {
				$relative = substr( $path, $position + strlen( self::BLOCKS_PATH_MARKER ) );
			}

			$segments = array_values( array_filter( explode( '/', trim( $relative, '/' ) ) ) );
			$family   = $segments[0] ?? '';
			$role     = $segments[1] ?? '';
			$title    = self::record_string( $block, 'title' );
			$parent   = self::record_value( $block, 'parent' );

			if ( is_string( $parent ) ) {
				$parent = array( $parent );
			}

			$parents = array();

			if ( is_array( $parent ) ) {
				foreach ( $parent as $parent_name ) {
					if ( is_string( $parent_name ) && preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $parent_name ) ) {
						$parents[] = strtolower( $parent_name );
					}
				}
			}

			$blockstudio = self::record_value( $block, 'blockstudio' );
			$attributes  = is_array( $blockstudio )
				&& is_array( $blockstudio['attributes'] ?? null )
				? $blockstudio['attributes']
				: array();

			$registrations[] = array(
				'name'       => $name,
				'title'      => '' === trim( $title ) ? self::humanize( substr( $name, strpos( $name, '/' ) + 1 ) ) : trim( $title ),
				'path'       => $path,
				'family'     => is_string( $family ) ? $family : '',
				'role'       => is_string( $role ) ? $role : '',
				'parent'     => array_values( array_unique( $parents ) ),
				'attributes' => $attributes,
			);
		}

		usort(
			$registrations,
			static fn( array $left, array $right ): int => strcmp( $left['name'], $right['name'] )
		);

		return $registrations;
	}

	/**
	 * Build one example node and its required implementation descendants.
	 *
	 * @param string               $name   Canonical name.
	 * @param array<string, array> $blocks Family blocks.
	 * @param array<string, true>  $stack  Cycle guard.
	 *
	 * @return array Example declaration.
	 */
	private static function example_declaration( string $name, array $blocks, array $stack ): array {
		if ( isset( $stack[ $name ] ) || ! isset( $blocks[ $name ] ) ) {
			return array(
				'name'       => $name,
				'attributes' => array(),
				'children'   => array(),
			);
		}

		$stack[ $name ] = true;
		$children       = array();

		foreach ( $blocks as $child ) {
			if ( in_array( $name, $child['parent'], true ) ) {
				$children[] = self::example_declaration( $child['name'], $blocks, $stack );
			}
		}

		return array(
			'name'       => $name,
			'attributes' => self::example_attributes( $blocks[ $name ]['attributes'] ),
			'children'   => $children,
		);
	}

	/**
	 * Generate deterministic example values from Blockstudio field definitions.
	 *
	 * @param array $definitions Attribute definitions.
	 *
	 * @return array<string, mixed> Attribute values.
	 */
	private static function example_attributes( array $definitions ): array {
		$attributes = array();

		foreach ( $definitions as $key => $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$id = isset( $definition['id'] ) && is_string( $definition['id'] )
				? $definition['id']
				: ( is_string( $key ) ? $key : '' );

			if ( '' === $id || ! empty( $definition['hidden'] ) ) {
				continue;
			}

			if ( array_key_exists( 'default', $definition ) ) {
				$attributes[ $id ] = $definition['default'];
				continue;
			}

			$type = is_string( $definition['type'] ?? null ) ? $definition['type'] : '';

			if ( 'select' === $type && is_array( $definition['options'] ?? null ) ) {
				$first = $definition['options'][0] ?? null;
				$value = is_array( $first ) ? ( $first['value'] ?? null ) : $first;

				if ( is_scalar( $value ) ) {
					$attributes[ $id ] = $value;
				}
				continue;
			}

			if ( in_array( $type, array( 'text', 'textarea', 'richtext', 'url', 'email' ), true ) ) {
				$attributes[ $id ] = self::example_text( $id, $definition );
			} elseif ( in_array( $type, array( 'number', 'range' ), true ) ) {
				$attributes[ $id ] = isset( $definition['min'] ) && is_numeric( $definition['min'] )
					? (float) $definition['min']
					: 1;
			} elseif ( in_array( $type, array( 'toggle', 'boolean' ), true ) ) {
				$attributes[ $id ] = false;
			}
		}

		return $attributes;
	}

	/**
	 * Generate one stable illustrative text value.
	 *
	 * @param string $id         Attribute ID.
	 * @param array  $definition Definition.
	 *
	 * @return string Example value.
	 */
	private static function example_text( string $id, array $definition ): string {
		if ( isset( $definition['placeholder'] ) && is_scalar( $definition['placeholder'] ) ) {
			return (string) $definition['placeholder'];
		}

		if ( 'src' === strtolower( $id ) || str_contains( strtolower( $id ), 'href' ) || str_contains( strtolower( $id ), 'url' ) ) {
			return '#';
		}

		if ( str_contains( strtolower( $id ), 'email' ) ) {
			return 'person@example.com';
		}

		$label = isset( $definition['label'] ) && is_scalar( $definition['label'] )
			? (string) $definition['label']
			: self::humanize( $id );

		return trim( $label );
	}

	/**
	 * Read and wrap one global UI asset.
	 *
	 * @param string $path     Path.
	 * @param string $tag      HTML tag.
	 * @param string $id       Element ID.
	 *
	 * @return string Asset markup.
	 */
	private static function global_asset( string $path, string $tag, string $id ): string {
		if ( ! is_file( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled immutable UI asset.
		$contents = file_get_contents( $path );

		if ( ! is_string( $contents ) || '' === trim( $contents ) ) {
			return '';
		}

		return '<' . $tag . ' id="' . esc_attr( $id ) . '">' . trim( $contents ) . '</' . $tag . '>';
	}

	/**
	 * Get one record value.
	 *
	 * @param mixed  $record Record.
	 * @param string $key    Key.
	 *
	 * @return mixed Value.
	 */
	private static function record_value( mixed $record, string $key ): mixed {
		if ( is_array( $record ) ) {
			return $record[ $key ] ?? null;
		}

		if ( is_object( $record ) && property_exists( $record, $key ) ) {
			return $record->{$key};
		}

		return null;
	}

	/**
	 * Get one scalar record value as a string.
	 *
	 * @param mixed  $record Record.
	 * @param string $key    Key.
	 *
	 * @return string Value.
	 */
	private static function record_string( mixed $record, string $key ): string {
		$value = self::record_value( $record, $key );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Resolve a block source path.
	 *
	 * @param mixed $record Block record.
	 *
	 * @return string Normalized path.
	 */
	private static function record_path( mixed $record ): string {
		$path = self::record_string( $record, 'path' );

		if ( '' === $path ) {
			$blockstudio = self::record_value( $record, 'blockstudio' );
			$data        = is_array( $blockstudio ) && is_array( $blockstudio['data'] ?? null )
				? $blockstudio['data']
				: array();
			$path        = is_string( $data['path'] ?? null ) ? $data['path'] : '';
		}

		return rtrim( (string) preg_replace( '#/+#', '/', str_replace( '\\', '/', trim( $path ) ) ), '/' );
	}

	/**
	 * Check a normalized source path.
	 *
	 * @param string $path Path.
	 *
	 * @return bool Whether bundled.
	 */
	private static function is_bundled_path( string $path ): bool {
		return str_contains( $path, self::BLOCKS_PATH_MARKER );
	}

	/**
	 * Humanize one slug.
	 *
	 * @param string $slug Slug.
	 *
	 * @return string Label.
	 */
	private static function humanize( string $slug ): string {
		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}
}
