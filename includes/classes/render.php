<?php
/**
 * Render class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Programmatic block rendering utility for theme developers.
 *
 * This class provides a simple API for rendering Blockstudio blocks
 * directly from PHP code, useful for:
 *
 * - Including blocks in theme templates
 * - Rendering blocks in custom page builders
 * - Generating block output for AJAX/REST responses
 * - Testing block output during development
 *
 * Usage Examples:
 *
 * ```php
 * // Render by block name (echoes output)
 * Render::block('blockstudio/hero');
 *
 * // Render with custom attributes
 * Render::block([
 *     'name' => 'blockstudio/card',
 *     'data' => [
 *         'title' => 'My Card',
 *         'image' => 123  // Attachment ID
 *     ]
 * ]);
 *
 * // Render with inner content
 * Render::block([
 *     'name' => 'blockstudio/section',
 *     'data' => ['background' => 'dark'],
 *     'content' => '<p>Inner HTML content</p>'
 * ]);
 * ```
 *
 * Helper Function:
 * The blockstudio_render_block() function wraps this class:
 * ```php
 * blockstudio_render_block(['name' => 'blockstudio/hero']);
 * ```
 *
 * @since 1.0.0
 */
class Render {

	/**
	 * Public result schema version for normalized declarations and documents.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Render a block by name or configuration.
	 *
	 * Programmatic embeds render frontend-resolved HTML even when called from
	 * another block's editor preview.
	 *
	 * @param string|array $value Block name or configuration array.
	 *
	 * @return false|string|void Returns HTML string, false on failure, or void when echoing.
	 */
	public static function block( $value ) {
		$data    = array();
		$content = false;

		if ( is_array( $value ) ) {
			$name    = $value['name'] ?? $value['id'];
			$data    = $value['data'] ?? array();
			$content = $value['content'] ?? false;
		} else {
			$name = $value;
		}

		$blocks = Build::data();

		if (
			! isset( $blocks[ $name ]['path'] ) &&
			! isset( $data['_BLOCKSTUDIO_EDITOR_STRING'] )
		) {
			return false;
		}

		$editor = $data['_BLOCKSTUDIO_EDITOR_STRING'] ?? false;
		unset( $data['_BLOCKSTUDIO_EDITOR_STRING'] );

		$parent = \WP_Block_Supports::$block_to_render;

		\WP_Block_Supports::$block_to_render = array(
			'blockName' => $name,
			'attrs'     => $data,
		);

		if ( $editor ) {
			try {
				$result = Block::render(
					array(
						'blockstudio' => array(
							'editor'     => $editor,
							'name'       => $name,
							'attributes' => $data,
						),
					)
				);
			} finally {
				\WP_Block_Supports::$block_to_render = $parent;
			}

			return $result;
		} else {
			$mode = isset( $_GET['blockstudioMode'] ) ? sanitize_text_field( wp_unslash( $_GET['blockstudioMode'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_GET['blockstudioMode'] );

			try {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Block render handles escaping.
				echo Block::render(
					array(
						'blockstudio' => array(
							'name'       => $name,
							'attributes' => $data,
						),
					),
					'',
					'',
					$content
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			} finally {
				if ( null !== $mode ) {
					$_GET['blockstudioMode'] = $mode; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}

				\WP_Block_Supports::$block_to_render = $parent;
			}
		}
	}

	/**
	 * Normalize one block declaration or an ordered declaration list.
	 *
	 * The canonical declaration shape is intentionally independent from
	 * WordPress' parsed-block arrays:
	 *
	 * - `name`: canonical `namespace/slug` block name.
	 * - `attributes`: Blockstudio attribute values.
	 * - `content`: trusted inner HTML.
	 * - `children`: ordered nested declarations.
	 *
	 * `data`, `inner`, `innerBlocks`, `layers`, and `example` are accepted as
	 * input conveniences and normalized away. A composition may also declare
	 * its top-level block through `root`.
	 *
	 * @param mixed $value Block declaration, composition, name, or declaration list.
	 *
	 * @return array<int, array{name:string, attributes:array<string, mixed>, content:string, children:array}>
	 *
	 * @throws \InvalidArgumentException When the declaration is malformed.
	 */
	public static function normalize( mixed $value ): array {
		if ( is_string( $value ) ) {
			return array( self::normalize_declaration( array( 'name' => $value ) ) );
		}

		if ( ! is_array( $value ) ) {
			throw new \InvalidArgumentException( 'Block declarations must be a canonical block name or an array.' );
		}

		if ( array_is_list( $value ) ) {
			$normalized = array();

			foreach ( $value as $declaration ) {
				$normalized[] = self::normalize_declaration( $declaration );
			}

			return $normalized;
		}

		return array( self::normalize_declaration( $value ) );
	}

	/**
	 * Render one normalized block composition without echoing.
	 *
	 * @param mixed $value Block declaration, composition, name, or declaration list.
	 *
	 * @return string Rendered HTML.
	 *
	 * @throws \InvalidArgumentException When the declaration is malformed or unknown.
	 * @throws \RuntimeException When a block cannot be rendered.
	 */
	public static function composition( mixed $value ): string {
		$html = '';

		foreach ( self::normalize( $value ) as $declaration ) {
			$html .= self::render_declaration( $declaration );
		}

		return $html;
	}

	/**
	 * Render serialized WordPress block content without creating a document.
	 *
	 * @param string $content Serialized block content or trusted HTML.
	 *
	 * @return string Rendered content.
	 */
	public static function content( string $content ): string {
		return function_exists( 'do_blocks' ) ? do_blocks( $content ) : $content;
	}

	/**
	 * Render a complete frontend document for a block composition.
	 *
	 * The result separates exact head/footer asset markup while also returning
	 * the assembled HTML document. Long-lived callers should invoke
	 * `Batch_Render::reset()` between documents; Canvas does this automatically.
	 *
	 * @param mixed $value Block declaration, composition, name, or declaration list.
	 * @param array $options Document options.
	 *
	 * @return array{
	 *   schemaVersion:int,
	 *   html:string,
	 *   body:string,
	 *   blocks:array<int, string>,
	 *   assets:array{
	 *     head:string,
	 *     footer:string,
	 *     styles:string,
	 *     scripts:string,
	 *     modules:string,
	 *     interactivity:string,
	 *     ui:array{style:string, script:string},
	 *     tailwind:string
	 *   },
	 *   warnings:array<int, array{code:string, message:string}>,
	 *   errors:array<int, array{code:string, message:string}>
	 * }
	 */
	public static function document( mixed $value, array $options = array() ): array {
		$declarations = self::normalize( $value );
		$body         = '';

		foreach ( $declarations as $declaration ) {
			$body .= self::render_declaration( $declaration );
		}

		return Render_Document::from_html(
			$body,
			self::declaration_names( $declarations ),
			$options
		);
	}

	/**
	 * Assemble a complete frontend document from already-rendered HTML.
	 *
	 * @param string        $body        Rendered body HTML.
	 * @param array<string> $block_names Canonical root block names.
	 * @param array         $options     Document options.
	 *
	 * @return array See Render::document().
	 */
	public static function document_from_html( string $body, array $block_names = array(), array $options = array() ): array {
		return Render_Document::from_html( $body, $block_names, $options );
	}

	/**
	 * Normalize one declaration.
	 *
	 * @param mixed $value Declaration value.
	 *
	 * @return array{name:string, attributes:array<string, mixed>, content:string, children:array}
	 *
	 * @throws \InvalidArgumentException When the declaration is malformed.
	 */
	private static function normalize_declaration( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = array( 'name' => $value );
		}

		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			throw new \InvalidArgumentException( 'Each block declaration must be an associative array.' );
		}

		if ( array_key_exists( 'root', $value ) ) {
			$root = $value['root'];
			unset( $value['root'] );

			if ( is_string( $root ) ) {
				$value = array_merge( array( 'name' => $root ), $value );
			} elseif ( is_array( $root ) && ! array_is_list( $root ) ) {
				$value = array_replace_recursive( $root, $value );
			} else {
				throw new \InvalidArgumentException( 'A composition root must be a canonical block name or declaration.' );
			}
		}

		$name = isset( $value['name'] ) && is_string( $value['name'] )
			? strtolower( trim( $value['name'] ) )
			: '';

		if ( ! preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) ) {
			throw new \InvalidArgumentException( 'Block declaration names must use canonical namespace/slug syntax.' );
		}

		$attributes = $value['attributes'] ?? $value['data'] ?? array();
		$content    = $value['content'] ?? '';
		$children   = $value['children'] ?? $value['innerBlocks'] ?? $value['layers'] ?? array();

		if ( array_key_exists( 'inner', $value ) ) {
			if ( is_string( $value['inner'] ) ) {
				$content = $value['inner'];
			} elseif ( is_array( $value['inner'] ) ) {
				$children = $value['inner'];
			} else {
				throw new \InvalidArgumentException( sprintf( 'The inner value for "%s" must be HTML or declarations.', esc_html( $name ) ) );
			}
		}

		$example = $value['example'] ?? array();
		if ( ! is_array( $example ) ) {
			throw new \InvalidArgumentException( sprintf( 'Example data for "%s" must be an array.', esc_html( $name ) ) );
		}

		if ( array() !== $example ) {
			$structured_example = array_intersect_key(
				$example,
				array_flip( array( 'attributes', 'data', 'content', 'children', 'innerBlocks', 'layers', 'inner' ) )
			);

			if ( array() === $structured_example ) {
				$attributes = array_merge( is_array( $attributes ) ? $attributes : array(), $example );
			} else {
				$example_attributes = $example['attributes'] ?? $example['data'] ?? array();

				if ( ! is_array( $example_attributes ) ) {
					throw new \InvalidArgumentException( sprintf( 'Example attributes for "%s" must be an array.', esc_html( $name ) ) );
				}

				$attributes = array_merge( is_array( $attributes ) ? $attributes : array(), $example_attributes );

				if ( array_key_exists( 'content', $example ) ) {
					$content = $example['content'];
				}

				foreach ( array( 'children', 'innerBlocks', 'layers', 'inner' ) as $child_key ) {
					if ( array_key_exists( $child_key, $example ) ) {
						if ( 'inner' === $child_key && is_string( $example[ $child_key ] ) ) {
							$content = $example[ $child_key ];
						} else {
							$children = $example[ $child_key ];
						}
						break;
					}
				}
			}
		}

		if ( ! is_array( $attributes ) || ( array() !== $attributes && array_is_list( $attributes ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Attributes for "%s" must be an associative array.', esc_html( $name ) ) );
		}

		if ( ! is_string( $content ) ) {
			throw new \InvalidArgumentException( sprintf( 'Content for "%s" must be a string.', esc_html( $name ) ) );
		}

		if ( ! is_array( $children ) || ! array_is_list( $children ) ) {
			throw new \InvalidArgumentException( sprintf( 'Children for "%s" must be an ordered declaration list.', esc_html( $name ) ) );
		}

		$normalized_children = array();

		foreach ( $children as $child ) {
			$normalized_children[] = self::normalize_declaration( $child );
		}

		return array(
			'name'       => $name,
			'attributes' => $attributes,
			'content'    => $content,
			'children'   => $normalized_children,
		);
	}

	/**
	 * Render one normalized declaration.
	 *
	 * @param array{name:string, attributes:array<string, mixed>, content:string, children:array} $declaration Declaration.
	 *
	 * @return string Rendered HTML.
	 *
	 * @throws \InvalidArgumentException When the declared block is unknown.
	 * @throws \RuntimeException When block rendering does not return HTML.
	 */
	private static function render_declaration( array $declaration ): string {
		$name   = $declaration['name'];
		$blocks = Build::data();

		if ( ! isset( $blocks[ $name ]['path'] ) ) {
			$registered = Build::blocks();

			if ( ! isset( $registered[ $name ] ) ) {
				throw new \InvalidArgumentException( sprintf( 'The block "%s" is not registered.', esc_html( $name ) ) );
			}
		}

		$inner = $declaration['content'];

		foreach ( $declaration['children'] as $child ) {
			$inner .= self::render_declaration( $child );
		}

		$parent = \WP_Block_Supports::$block_to_render;

		\WP_Block_Supports::$block_to_render = array(
			'blockName' => $name,
			'attrs'     => $declaration['attributes'],
		);

		$mode = isset( $_GET['blockstudioMode'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['blockstudioMode'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['blockstudioMode'] );

		try {
			$html = Block::render(
				array(
					'blockstudio' => array(
						'name'       => $name,
						'attributes' => $declaration['attributes'],
					),
				),
				'',
				'',
				$inner
			);
		} finally {
			if ( null !== $mode ) {
				$_GET['blockstudioMode'] = $mode; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			\WP_Block_Supports::$block_to_render = $parent;
		}

		if ( ! is_string( $html ) ) {
			throw new \RuntimeException( sprintf( 'The block "%s" did not return HTML.', esc_html( $name ) ) );
		}

		return $html;
	}

	/**
	 * Collect canonical block names in declaration order.
	 *
	 * @param array<int, array{name:string, attributes:array<string, mixed>, content:string, children:array}> $declarations Declarations.
	 *
	 * @return array<int, string> Canonical names.
	 */
	private static function declaration_names( array $declarations ): array {
		$names = array();

		foreach ( $declarations as $declaration ) {
			if ( ! in_array( $declaration['name'], $names, true ) ) {
				$names[] = $declaration['name'];
			}

			foreach ( self::declaration_names( $declaration['children'] ) as $child_name ) {
				if ( ! in_array( $child_name, $names, true ) ) {
					$names[] = $child_name;
				}
			}
		}

		return $names;
	}
}
