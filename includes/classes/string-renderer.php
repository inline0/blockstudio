<?php
/**
 * String renderer class.
 *
 * Parses <bs:block-name> tags in content and renders the corresponding
 * Blockstudio blocks. Opt-in via the stringRenderer/enabled setting.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Replaces <bs:block-name attr="value"> tags in post content
 * with rendered Blockstudio block output.
 *
 * @since 7.1.0
 */
class String_Renderer {

	/**
	 * Initialize the string renderer.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! Settings::get( 'stringRenderer/enabled' ) ) {
			return;
		}

		add_filter( 'the_content', array( __CLASS__, 'render' ), 5 );
		add_filter( 'widget_text', array( __CLASS__, 'render' ), 5 );
		add_filter( 'blockstudio/string_renderer/render', array( __CLASS__, 'render' ) );
	}

	/**
	 * Parse and replace all <bs:*> tags in the given content.
	 *
	 * Supports both self-closing and paired tags:
	 *   <bs:hero title="Hello" />
	 *   <bs:section layout="wide">inner content</bs:section>
	 *
	 * @param string $content The content to process.
	 *
	 * @return string Content with <bs:*> tags replaced by rendered blocks.
	 */
	public static function render( $content ): string {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content ?? '';
		}

		if ( false === strpos( $content, '<bs:' ) ) {
			return $content;
		}

		$blocks = Build::blocks();

		if ( empty( $blocks ) ) {
			return $content;
		}

		do {
			$previous = $content;
			$content  = self::replace_self_closing_tags( $content, $blocks );
			$content  = self::replace_paired_tags( $content, $blocks );
		} while ( $content !== $previous );

		return $content;
	}

	/**
	 * Replace paired <bs:name>...</bs:name> tags.
	 *
	 * @param string $content The content to process.
	 * @param array  $blocks  Registered blocks.
	 *
	 * @return string Processed content.
	 */
	private static function replace_paired_tags( string $content, array $blocks ): string {
		$pattern = '/<bs:([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)(\s[^>]*)?>(.*?)<\/bs:\1>/si';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $blocks ) {
				$tag_name      = $matches[1];
				$attr_string   = trim( $matches[2] ?? '' );
				$inner_content = $matches[3];
				$attributes    = self::parse_attributes( $attr_string );
				$block_name    = self::resolve_block_name( $tag_name, $blocks );

				if ( ! $block_name ) {
					return $matches[0];
				}

				return self::render_block( $block_name, $attributes ) . $inner_content;
			},
			$content
		) ?? $content;
	}

	/**
	 * Replace self-closing <bs:name /> tags.
	 *
	 * @param string $content The content to process.
	 * @param array  $blocks  Registered blocks.
	 *
	 * @return string Processed content.
	 */
	private static function replace_self_closing_tags( string $content, array $blocks ): string {
		$pattern = '/<bs:([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)(\s[^>]*)?\s*\/>/si';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $blocks ) {
				$tag_name    = $matches[1];
				$attr_string = trim( $matches[2] ?? '' );
				$attributes  = self::parse_attributes( $attr_string );
				$block_name  = self::resolve_block_name( $tag_name, $blocks );

				if ( ! $block_name ) {
					return $matches[0];
				}

				return self::render_block( $block_name, $attributes );
			},
			$content
		) ?? $content;
	}

	/**
	 * Resolve a tag name to a registered Blockstudio block name.
	 *
	 * Lookup order:
	 *   1. Exact namespace match: bs:acme--hero resolves to acme/hero
	 *   2. First block whose slug matches: bs:hero resolves to the first registered block with that slug
	 *
	 * @param string $tag_name The tag name (without bs: prefix).
	 * @param array  $blocks   Registered blocks keyed by full name.
	 *
	 * @return string|false The full block name or false if not found.
	 */
	private static function resolve_block_name( string $tag_name, array $blocks ) {
		if ( str_contains( $tag_name, '--' ) ) {
			$full_name = str_replace( '--', '/', $tag_name );

			if ( isset( $blocks[ $full_name ] ) ) {
				return $full_name;
			}

			return false;
		}

		foreach ( $blocks as $name => $data ) {
			$parts = explode( '/', $name );

			if ( isset( $parts[1] ) && $parts[1] === $tag_name ) {
				return $name;
			}
		}

		return false;
	}

	/**
	 * Parse HTML-style attributes from a tag string.
	 *
	 * Handles quoted values, unquoted values, and boolean attributes:
	 *   title="Hello World" count=3 featured
	 *
	 * @param string $attr_string Raw attribute string.
	 *
	 * @return array Parsed key-value attribute pairs.
	 */
	private static function parse_attributes( string $attr_string ): array {
		if ( '' === $attr_string ) {
			return array();
		}

		$attributes = array();
		$pattern    = '/([a-zA-Z_][\w-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/';

		if ( preg_match_all( $pattern, $attr_string, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$key = $match[1];

				if ( isset( $match[2] ) && '' !== $match[2] ) {
					$value = $match[2];
				} elseif ( isset( $match[3] ) && '' !== $match[3] ) {
					$value = $match[3];
				} elseif ( isset( $match[4] ) && '' !== $match[4] ) {
					$value = $match[4];
				} elseif ( count( $match ) <= 2 ) {
					$value = true;
				} else {
					$value = '';
				}

				if ( is_string( $value ) && is_numeric( $value ) ) {
					$value = str_contains( $value, '.' ) ? (float) $value : (int) $value;
				}

				if ( is_string( $value ) ) {
					$json = json_decode( $value, true );

					if ( is_array( $json ) ) {
						$value = $json;
					}
				}

				$attributes[ $key ] = $value;
			}
		}

		return $attributes;
	}

	/**
	 * Render a Blockstudio block with the given attributes.
	 *
	 * @param string $block_name    Full block name (e.g. blockstudio/hero).
	 * @param array  $attributes    Block attributes from tag.
	 * @param string $inner_content Optional inner content for container blocks.
	 *
	 * @return string Rendered block HTML.
	 */
	private static function render_block( string $block_name, array $attributes, string $inner_content = '' ): string {
		$parent = \WP_Block_Supports::$block_to_render;

		\WP_Block_Supports::$block_to_render = array(
			'blockName' => $block_name,
			'attrs'     => $attributes,
		);

		$result = Block::render(
			array(
				'blockstudio' => array(
					'name'       => $block_name,
					'attributes' => $attributes,
				),
			),
			'',
			'',
			$inner_content
		);

		\WP_Block_Supports::$block_to_render = $parent;

		return is_string( $result ) ? $result : '';
	}
}
