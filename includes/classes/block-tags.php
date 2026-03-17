<?php
/**
 * Block Tags class.
 *
 * Parses <bs:namespace-block-name> tags in content and renders the corresponding
 * Blockstudio blocks. Page-level rendering is opt-in via the blockTags/enabled
 * setting. Template-level rendering is always active.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Replaces <bs:namespace-block-name attr="value"> tags with rendered
 * Blockstudio block output.
 *
 * @since 7.1.0
 */
class Block_Tags {

	/**
	 * Initialize page-level block tag rendering.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! Settings::get( 'blockTags/enabled' ) ) {
			return;
		}

		add_filter( 'the_content', array( __CLASS__, 'render' ), 5 );
		add_filter( 'widget_text', array( __CLASS__, 'render' ), 5 );
		add_filter( 'blockstudio/block_tags/render', array( __CLASS__, 'render' ) );
	}

	/**
	 * Parse and replace all <bs:*> tags in the given content.
	 *
	 * Supports both self-closing and paired tags:
	 *   <bs:acme-hero title="Hello" />
	 *   <bs:acme-section layout="wide">inner content</bs:acme-section>
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
		$pattern = '/<bs:([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)(\s[^>]*)?>((?:(?!<bs:).)*?)<\/bs:\1>/si';

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

				return self::render_block( $block_name, $attributes, $inner_content );
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
	 * The first hyphen maps to the namespace separator:
	 *   bs:acme-hero resolves to acme/hero
	 *   bs:blockstudio-type-text resolves to blockstudio/type-text
	 *
	 * Only exact matches are returned. Blocks are checked against the
	 * allow/deny lists from blockTags settings.
	 *
	 * @param string $tag_name The tag name (without bs: prefix).
	 * @param array  $blocks   Registered blocks keyed by full name.
	 *
	 * @return string|false The full block name or false if not found.
	 */
	private static function resolve_block_name( string $tag_name, array $blocks ) {
		$pos = strpos( $tag_name, '-' );

		if ( false === $pos ) {
			return false;
		}

		$full_name = substr_replace( $tag_name, '/', $pos, 1 );

		if ( ! isset( $blocks[ $full_name ] ) ) {
			return false;
		}

		$allow = Settings::get( 'blockTags/allow' );
		$deny  = Settings::get( 'blockTags/deny' );

		$allow = apply_filters( 'blockstudio/block_tags/allow', $allow );
		$deny  = apply_filters( 'blockstudio/block_tags/deny', $deny );

		if ( ! empty( $deny ) && is_array( $deny ) ) {
			foreach ( $deny as $pattern ) {
				if ( fnmatch( $pattern, $full_name ) ) {
					return false;
				}
			}
		}

		if ( ! empty( $allow ) && is_array( $allow ) ) {
			foreach ( $allow as $pattern ) {
				if ( fnmatch( $pattern, $full_name ) ) {
					return $full_name;
				}
			}
			return false;
		}

		return $full_name;
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
		$passthrough = array();
		$block_attrs = array();

		foreach ( $attributes as $key => $value ) {
			if ( str_starts_with( $key, 'data-' ) ) {
				$passthrough[ $key ] = $value;
			} elseif ( str_starts_with( $key, 'html-' ) ) {
				$passthrough[ substr( $key, 5 ) ] = $value;
			} else {
				$block_attrs[ $key ] = $value;
			}
		}

		$parent = \WP_Block_Supports::$block_to_render;

		\WP_Block_Supports::$block_to_render = array(
			'blockName' => $block_name,
			'attrs'     => $block_attrs,
		);

		$result = Block::render(
			array(
				'blockstudio' => array(
					'name'       => $block_name,
					'attributes' => $block_attrs,
				),
			),
			$inner_content,
			'',
			$inner_content
		);

		\WP_Block_Supports::$block_to_render = $parent;

		$result = is_string( $result ) ? $result : '';

		if ( ! empty( $passthrough ) && '' !== $result ) {
			$processor = new \WP_HTML_Tag_Processor( $result );

			if ( $processor->next_tag() ) {
				foreach ( $passthrough as $key => $value ) {
					$processor->set_attribute( $key, is_bool( $value ) ? '' : (string) $value );
				}

				$result = $processor->get_updated_html();
			}
		}

		return $result;
	}
}
