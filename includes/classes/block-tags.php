<?php
/**
 * Block Tags class.
 *
 * Unified renderer for block tags in content. Supports three syntaxes:
 *   <bs:namespace-block attr="value" />
 *   <block name="namespace/block" attr="value" />
 *   <custom-tag attr="value" />
 *
 * Renders both Blockstudio blocks (via Block::render) and core WordPress
 * blocks (via Html_Parser + render_block). Page-level rendering is opt-in
 * via blockTags/enabled. Template-level rendering is always active.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Replaces block tags with rendered block output.
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
	 * Get the renderer registry for core block types.
	 *
	 * Returns a map of block names to renderer callables. The callables are
	 * trait methods on the Html_Parser instance.
	 *
	 * @param Html_Parser $parser The parser instance (renderers are bound to it).
	 *
	 * @return array<string, callable> Block name => renderer callable.
	 */
	public static function get_renderers( Html_Parser $parser ): array {
		$renderers = array(
			'core/paragraph'         => array( $parser, 'render_paragraph' ),
			'core/heading'           => array( $parser, 'render_heading' ),
			'core/list'              => array( $parser, 'render_list' ),
			'core/quote'             => array( $parser, 'render_quote' ),
			'core/pullquote'         => array( $parser, 'render_pullquote' ),
			'core/code'              => array( $parser, 'render_code' ),
			'core/preformatted'      => array( $parser, 'render_preformatted' ),
			'core/verse'             => array( $parser, 'render_verse' ),
			'core/image'             => array( $parser, 'render_image' ),
			'core/gallery'           => array( $parser, 'render_gallery' ),
			'core/audio'             => array( $parser, 'render_audio' ),
			'core/video'             => array( $parser, 'render_video' ),
			'core/cover'             => array( $parser, 'render_cover' ),
			'core/embed'             => array( $parser, 'render_embed' ),
			'core/group'             => array( $parser, 'render_group' ),
			'core/columns'           => array( $parser, 'render_columns' ),
			'core/column'            => array( $parser, 'render_column' ),
			'core/separator'         => array( $parser, 'render_separator' ),
			'core/spacer'            => array( $parser, 'render_spacer' ),
			'core/buttons'           => array( $parser, 'render_buttons' ),
			'core/button'            => array( $parser, 'render_button' ),
			'core/details'           => array( $parser, 'render_details' ),
			'core/table'             => array( $parser, 'render_table' ),
			'core/social-links'      => array( $parser, 'render_social_links' ),
			'core/social-link'       => array( $parser, 'render_social_link' ),
			'core/media-text'        => array( $parser, 'render_media_text' ),
			'core/more'              => array( $parser, 'render_more' ),
			'core/nextpage'          => array( $parser, 'render_nextpage' ),
			'core/accordion'         => array( $parser, 'render_accordion' ),
			'core/accordion-item'    => array( $parser, 'render_accordion_item' ),
			'core/accordion-heading' => array( $parser, 'render_accordion_heading' ),
			'core/accordion-panel'   => array( $parser, 'render_accordion_panel' ),
			'core/query'             => array( $parser, 'render_query' ),
			'core/comments'          => array( $parser, 'render_comments' ),
		);

		$renderers = apply_filters( 'blockstudio/block_tags/builders', $renderers, $parser );
		$renderers = apply_filters( 'blockstudio/block_tags/renderers', $renderers, $parser );
		$renderers = apply_filters( 'blockstudio/parser/renderers', $renderers, $parser );

		return $renderers;
	}

	/**
	 * Parse and replace all block tags in the given content.
	 *
	 * Supports both <bs:namespace-block> and <block name="namespace/block"> syntax.
	 *
	 * @param string $content The content to process.
	 *
	 * @return string Content with block tags replaced by rendered blocks.
	 */
	public static function render( $content ): string {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$aliases    = self::get_tag_aliases();
		$prefixes   = self::get_tag_prefixes();
		$has_bs     = false !== strpos( $content, '<bs:' );
		$has_block  = false !== strpos( $content, '<block ' );
		$has_alias  = self::has_alias_tags( $content, $aliases );
		$has_prefix = self::has_prefix_tags( $content, $prefixes );

		if ( ! $has_bs && ! $has_block && ! $has_alias && ! $has_prefix ) {
			return $content;
		}

		Perf::start( 'block-tags' );

		$blocks = Build::blocks();

		do {
			$previous = $content;

			if ( $has_alias ) {
				$content = self::replace_paired_alias_tags( $content, $aliases );
				$content = self::replace_self_closing_alias_tags( $content, $aliases );
			}

			if ( $has_prefix ) {
				$content = self::replace_paired_prefix_tags( $content, $prefixes, $aliases, $blocks );
				$content = self::replace_self_closing_prefix_tags( $content, $prefixes, $aliases, $blocks );
			}

			if ( $has_bs ) {
				$content = self::replace_paired_bs_tags( $content, $blocks );
				$content = self::replace_self_closing_bs_tags( $content, $blocks );
			}

			if ( $has_block ) {
				$content = self::replace_paired_block_elements( $content );
				$content = self::replace_self_closing_block_elements( $content );
			}

			$has_bs     = false !== strpos( $content, '<bs:' );
			$has_block  = false !== strpos( $content, '<block ' );
			$has_alias  = self::has_alias_tags( $content, $aliases );
			$has_prefix = self::has_prefix_tags( $content, $prefixes );
		} while ( $content !== $previous && ( $has_bs || $has_block || $has_alias || $has_prefix ) );

		Perf::stop( 'block-tags', 'Block Tags' );

		return $content;
	}

	// -------------------------------------------------------------------------
	// <custom-tag> alias syntax
	// -------------------------------------------------------------------------

	/**
	 * Replace paired alias tags.
	 *
	 * @param string               $content The content to process.
	 * @param array<string,string> $aliases Custom tag => block name map.
	 *
	 * @return string Processed content.
	 */
	private static function replace_paired_alias_tags( string $content, array $aliases ): string {
		$offset = 0;

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( false !== ( $open_pos = self::find_next_alias_tag( $content, $offset, $aliases ) ) ) {
			$parsed_open = self::parse_alias_open_tag( $content, $open_pos, $aliases );
			if ( null === $parsed_open ) {
				$offset = $open_pos + 1;
				continue;
			}

			$tag_name   = $parsed_open['tag_name'];
			$block_name = $parsed_open['block_name'];
			$tag_end    = $parsed_open['name_end'];
			$gt_pos     = self::find_closing_angle( $content, $tag_end );

			if ( false === $gt_pos ) {
				break;
			}

			if ( '/' === $content[ $gt_pos - 1 ] ) {
				$offset = $gt_pos + 1;
				continue;
			}

			$close_tag = '</' . $tag_name . '>';
			$close_pos = self::find_matching_close( $content, $gt_pos, '<' . $tag_name, $close_tag );

			if ( false === $close_pos ) {
				$offset = $gt_pos + 1;
				continue;
			}

			$attr_string   = trim( substr( $content, $tag_end, $gt_pos - $tag_end ) );
			$inner_content = substr( $content, $gt_pos + 1, $close_pos - $gt_pos - 1 );
			$attributes    = self::parse_attributes( $attr_string );
			$block_name    = self::check_allow_deny( $block_name );

			if ( ! $block_name ) {
				$offset = $close_pos + strlen( $close_tag );
				continue;
			}

			$rendered    = self::render_block( $block_name, $attributes, $inner_content );
			$full_length = $close_pos + strlen( $close_tag ) - $open_pos;
			$content     = substr_replace( $content, $rendered, $open_pos, $full_length );
			$offset      = $open_pos + strlen( $rendered );
		}

		return $content;
	}

	/**
	 * Replace self-closing alias tags.
	 *
	 * @param string               $content The content to process.
	 * @param array<string,string> $aliases Custom tag => block name map.
	 *
	 * @return string Processed content.
	 */
	private static function replace_self_closing_alias_tags( string $content, array $aliases ): string {
		if ( empty( $aliases ) ) {
			return $content;
		}

		$pattern = '/<([a-z][a-z0-9]*(?:-[a-z0-9]+)*)(\s[^>]*)?\s*\/>/si';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $aliases ) {
				$tag_name = strtolower( $matches[1] );

				if ( ! isset( $aliases[ $tag_name ] ) ) {
					return $matches[0];
				}

				$attr_string = trim( $matches[2] ?? '' );
				$attributes  = self::parse_attributes( $attr_string );
				$block_name  = self::check_allow_deny( $aliases[ $tag_name ] );

				if ( ! $block_name ) {
					return $matches[0];
				}

				return self::render_block( $block_name, $attributes );
			},
			$content
		) ?? $content;
	}

	// -------------------------------------------------------------------------
	// <prefix-slug> namespace shorthand syntax
	// -------------------------------------------------------------------------

	/**
	 * Replace paired prefix tags.
	 *
	 * @param string                      $content  The content to process.
	 * @param array<string,array<string>> $prefixes Prefix => namespaces map.
	 * @param array<string,string>        $aliases  Custom tag => block name map.
	 * @param array                       $blocks   Registered Blockstudio blocks.
	 *
	 * @return string Processed content.
	 */
	private static function replace_paired_prefix_tags( string $content, array $prefixes, array $aliases, array $blocks ): string {
		$offset = 0;

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( false !== ( $open_pos = self::find_next_prefix_tag( $content, $offset, $prefixes, $aliases, $blocks ) ) ) {
			$parsed_open = self::parse_prefix_open_tag( $content, $open_pos, $prefixes, $aliases, $blocks );
			if ( null === $parsed_open ) {
				$offset = $open_pos + 1;
				continue;
			}

			$tag_name   = $parsed_open['tag_name'];
			$block_name = $parsed_open['block_name'];
			$tag_end    = $parsed_open['name_end'];
			$gt_pos     = self::find_closing_angle( $content, $tag_end );

			if ( false === $gt_pos ) {
				break;
			}

			if ( '/' === $content[ $gt_pos - 1 ] ) {
				$offset = $gt_pos + 1;
				continue;
			}

			$close_tag = '</' . $tag_name . '>';
			$close_pos = self::find_matching_close( $content, $gt_pos, '<' . $tag_name, $close_tag );

			if ( false === $close_pos ) {
				$offset = $gt_pos + 1;
				continue;
			}

			$attr_string   = trim( substr( $content, $tag_end, $gt_pos - $tag_end ) );
			$inner_content = substr( $content, $gt_pos + 1, $close_pos - $gt_pos - 1 );
			$attributes    = self::parse_attributes( $attr_string );
			$rendered      = self::render_block( $block_name, $attributes, $inner_content );
			$full_length   = $close_pos + strlen( $close_tag ) - $open_pos;
			$content       = substr_replace( $content, $rendered, $open_pos, $full_length );
			$offset        = $open_pos + strlen( $rendered );
		}

		return $content;
	}

	/**
	 * Replace self-closing prefix tags.
	 *
	 * @param string                      $content  The content to process.
	 * @param array<string,array<string>> $prefixes Prefix => namespaces map.
	 * @param array<string,string>        $aliases  Custom tag => block name map.
	 * @param array                       $blocks   Registered Blockstudio blocks.
	 *
	 * @return string Processed content.
	 */
	private static function replace_self_closing_prefix_tags( string $content, array $prefixes, array $aliases, array $blocks ): string {
		if ( empty( $prefixes ) ) {
			return $content;
		}

		$pattern = '/<([a-z][a-z0-9]*(?:-[a-z0-9]+)*)(\s[^>]*)?\s*\/>/si';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $prefixes, $aliases, $blocks ) {
				$tag_name = strtolower( $matches[1] );

				if ( isset( $aliases[ $tag_name ] ) ) {
					return $matches[0];
				}

				$block_name = self::resolve_prefix_tag_name( $tag_name, $prefixes, $blocks );

				if ( ! $block_name ) {
					return $matches[0];
				}

				$attr_string = trim( $matches[2] ?? '' );
				$attributes  = self::parse_attributes( $attr_string );

				return self::render_block( $block_name, $attributes );
			},
			$content
		) ?? $content;
	}

	// -------------------------------------------------------------------------
	// <bs:namespace-block> syntax
	// -------------------------------------------------------------------------

	/**
	 * Replace paired <bs:name>...</bs:name> tags.
	 *
	 * @param string $content The content to process.
	 * @param array  $blocks  Registered Blockstudio blocks.
	 *
	 * @return string Processed content.
	 */
	private static function replace_paired_bs_tags( string $content, array $blocks ): string {
		$offset = 0;

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( false !== ( $open_pos = strpos( $content, '<bs:', $offset ) ) ) {
			$tag_start   = $open_pos + 4;
			$tag_end     = $tag_start;
			$content_len = strlen( $content );

			while ( $tag_end < $content_len && preg_match( '/[a-z0-9-]/', $content[ $tag_end ] ) ) {
				++$tag_end;
			}

			$tag_name = substr( $content, $tag_start, $tag_end - $tag_start );

			if ( '' === $tag_name ) {
				$offset = $open_pos + 1;
				continue;
			}

			$gt_pos = self::find_closing_angle( $content, $tag_end );

			if ( false === $gt_pos ) {
				break;
			}

			if ( '/' === $content[ $gt_pos - 1 ] ) {
				$offset = $gt_pos + 1;
				continue;
			}

			$close_tag = '</bs:' . $tag_name . '>';
			$close_pos = self::find_matching_close( $content, $gt_pos, '<bs:' . $tag_name, $close_tag );

			if ( false === $close_pos ) {
				$offset = $gt_pos + 1;
				continue;
			}

			$attr_string   = trim( substr( $content, $tag_end, $gt_pos - $tag_end ) );
			$inner_content = substr( $content, $gt_pos + 1, $close_pos - $gt_pos - 1 );
			$attributes    = self::parse_attributes( $attr_string );
			$block_name    = self::resolve_bs_tag_name( $tag_name, $blocks );

			if ( ! $block_name ) {
				$offset = $close_pos + strlen( $close_tag );
				continue;
			}

			$rendered    = self::render_block( $block_name, $attributes, $inner_content );
			$full_length = $close_pos + strlen( $close_tag ) - $open_pos;
			$content     = substr_replace( $content, $rendered, $open_pos, $full_length );
			$offset      = $open_pos + strlen( $rendered );
		}

		return $content;
	}

	/**
	 * Replace self-closing <bs:name /> tags.
	 *
	 * @param string $content The content to process.
	 * @param array  $blocks  Registered Blockstudio blocks.
	 *
	 * @return string Processed content.
	 */
	private static function replace_self_closing_bs_tags( string $content, array $blocks ): string {
		$pattern = '/<bs:([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)(\s[^>]*)?\s*\/>/si';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $blocks ) {
				$tag_name    = $matches[1];
				$attr_string = trim( $matches[2] ?? '' );
				$attributes  = self::parse_attributes( $attr_string );
				$block_name  = self::resolve_bs_tag_name( $tag_name, $blocks );

				if ( ! $block_name ) {
					return $matches[0];
				}

				return self::render_block( $block_name, $attributes );
			},
			$content
		) ?? $content;
	}

	// -------------------------------------------------------------------------
	// <block name="namespace/block"> syntax
	// -------------------------------------------------------------------------

	/**
	 * Replace paired <block name="...">...</block> tags.
	 *
	 * @param string $content The content to process.
	 *
	 * @return string Processed content.
	 */
	private static function replace_paired_block_elements( string $content ): string {
		$offset = 0;

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( false !== ( $open_pos = strpos( $content, '<block ', $offset ) ) ) {
			$gt_pos = self::find_closing_angle( $content, $open_pos + 6 );

			if ( false === $gt_pos ) {
				break;
			}

			if ( '/' === $content[ $gt_pos - 1 ] ) {
				$offset = $gt_pos + 1;
				continue;
			}

			$close_tag = '</block>';
			$close_pos = self::find_matching_close( $content, $gt_pos, '<block ', $close_tag );

			if ( false === $close_pos ) {
				$offset = $gt_pos + 1;
				continue;
			}

			$attr_string   = trim( substr( $content, $open_pos + 6, $gt_pos - $open_pos - 6 ) );
			$inner_content = substr( $content, $gt_pos + 1, $close_pos - $gt_pos - 1 );
			$attributes    = self::parse_attributes( $attr_string );
			$block_name    = $attributes['name'] ?? '';
			unset( $attributes['name'] );

			$block_name = self::check_allow_deny( $block_name );

			if ( ! $block_name ) {
				$offset = $close_pos + strlen( $close_tag );
				continue;
			}

			$rendered    = self::render_block( $block_name, $attributes, $inner_content );
			$full_length = $close_pos + strlen( $close_tag ) - $open_pos;
			$content     = substr_replace( $content, $rendered, $open_pos, $full_length );
			$offset      = $open_pos + strlen( $rendered );
		}

		return $content;
	}

	/**
	 * Replace self-closing <block name="..." /> tags.
	 *
	 * @param string $content The content to process.
	 *
	 * @return string Processed content.
	 */
	private static function replace_self_closing_block_elements( string $content ): string {
		$pattern = '/<block\s+([^>]*?)\s*\/>/si';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$attr_string = trim( $matches[1] );
				$attributes  = self::parse_attributes( $attr_string );
				$block_name  = $attributes['name'] ?? '';
				unset( $attributes['name'] );

				$block_name = self::check_allow_deny( $block_name );

				if ( ! $block_name ) {
					return $matches[0];
				}

				return self::render_block( $block_name, $attributes );
			},
			$content
		) ?? $content;
	}

	// -------------------------------------------------------------------------
	// Resolution and allow/deny
	// -------------------------------------------------------------------------

	/**
	 * Return custom tag aliases for block tag parsing.
	 *
	 * Alias keys are lowercase custom-element names such as "theme-button".
	 * Alias values are canonical block names such as "bsui/button".
	 *
	 * @return array<string,string>
	 */
	private static function get_tag_aliases(): array {
		$aliases = apply_filters( 'blockstudio/block_tags/tag_aliases', array() );

		if ( ! is_array( $aliases ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $aliases as $tag_name => $block_name ) {
			if ( ! is_string( $tag_name ) || ! is_string( $block_name ) ) {
				continue;
			}

			$tag_name   = strtolower( trim( $tag_name ) );
			$block_name = trim( $block_name );

			if (
				! preg_match( '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)+$/', $tag_name )
				|| '' === $block_name
				|| false === strpos( $block_name, '/' )
			) {
				continue;
			}

			$normalized[ $tag_name ] = $block_name;
		}

		return $normalized;
	}

	/**
	 * Whether content contains any configured alias tags.
	 *
	 * @param string               $content Content to inspect.
	 * @param array<string,string> $aliases Custom tag => block name map.
	 *
	 * @return bool True when any alias opening tag appears.
	 */
	private static function has_alias_tags( string $content, array $aliases ): bool {
		foreach ( $aliases as $tag_name => $_block_name ) {
			if ( false !== strpos( $content, '<' . $tag_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the next configured alias tag opening.
	 *
	 * @param string               $content Content to scan.
	 * @param int                  $offset  Start offset.
	 * @param array<string,string> $aliases Custom tag => block name map.
	 *
	 * @return int|false Tag position, or false when none exists.
	 */
	private static function find_next_alias_tag( string $content, int $offset, array $aliases ) {
		if ( empty( $aliases ) ) {
			return false;
		}

		$len = strlen( $content );

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( $offset < $len && false !== ( $pos = strpos( $content, '<', $offset ) ) ) {
			if ( null !== self::parse_alias_open_tag( $content, $pos, $aliases ) ) {
				return $pos;
			}

			$offset = $pos + 1;
		}

		return false;
	}

	/**
	 * Parse an alias opening tag at a given position.
	 *
	 * @param string               $content Content to parse.
	 * @param int                  $pos     Position of the opening <.
	 * @param array<string,string> $aliases Custom tag => block name map.
	 *
	 * @return array{tag_name:string,block_name:string,name_end:int}|null
	 */
	private static function parse_alias_open_tag( string $content, int $pos, array $aliases ): ?array {
		if ( '<' !== ( $content[ $pos ] ?? '' ) || '/' === ( $content[ $pos + 1 ] ?? '' ) ) {
			return null;
		}

		$remaining = substr( $content, $pos, 80 );
		if ( ! preg_match( '/^<([a-z][a-z0-9]*(?:-[a-z0-9]+)*)(?=[\s>\/])/i', $remaining, $matches ) ) {
			return null;
		}

		$tag_name = strtolower( $matches[1] );
		if ( ! isset( $aliases[ $tag_name ] ) ) {
			return null;
		}

		return array(
			'tag_name'   => $tag_name,
			'block_name' => $aliases[ $tag_name ],
			'name_end'   => $pos + 1 + strlen( $matches[1] ),
		);
	}

	/**
	 * Return prefix namespace shorthands for block tag parsing.
	 *
	 * Prefix keys are lowercase names such as "theme". Values are one or more
	 * block namespaces that are tried in order for tags like <theme-card />.
	 *
	 * @return array<string,array<string>>
	 */
	private static function get_tag_prefixes(): array {
		$prefixes = Settings::get( 'blockTags/prefixes' );
		$prefixes = apply_filters( 'blockstudio/block_tags/prefixes', $prefixes );

		if ( ! is_array( $prefixes ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $prefixes as $prefix => $namespaces ) {
			if ( ! is_string( $prefix ) ) {
				continue;
			}

			$prefix = strtolower( trim( $prefix ) );

			if ( ! preg_match( '/^[a-z][a-z0-9]*$/', $prefix ) ) {
				continue;
			}

			if ( is_string( $namespaces ) ) {
				$namespaces = array( $namespaces );
			}

			if ( ! is_array( $namespaces ) ) {
				continue;
			}

			$valid_namespaces = array();

			foreach ( $namespaces as $namespace ) {
				if ( ! is_string( $namespace ) ) {
					continue;
				}

				$namespace = strtolower( trim( $namespace ) );

				if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', $namespace ) ) {
					continue;
				}

				if ( ! in_array( $namespace, $valid_namespaces, true ) ) {
					$valid_namespaces[] = $namespace;
				}
			}

			if ( ! empty( $valid_namespaces ) ) {
				$normalized[ $prefix ] = $valid_namespaces;
			}
		}

		return $normalized;
	}

	/**
	 * Whether content contains any configured prefix tags.
	 *
	 * @param string                      $content  Content to inspect.
	 * @param array<string,array<string>> $prefixes Prefix => namespaces map.
	 *
	 * @return bool True when any prefix opening tag appears.
	 */
	private static function has_prefix_tags( string $content, array $prefixes ): bool {
		foreach ( $prefixes as $prefix => $_namespaces ) {
			if ( false !== strpos( $content, '<' . $prefix . '-' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the next configured prefix tag opening.
	 *
	 * @param string                      $content  Content to scan.
	 * @param int                         $offset   Start offset.
	 * @param array<string,array<string>> $prefixes Prefix => namespaces map.
	 * @param array<string,string>        $aliases  Custom tag => block name map.
	 * @param array                       $blocks   Registered Blockstudio blocks.
	 *
	 * @return int|false Tag position, or false when none exists.
	 */
	private static function find_next_prefix_tag( string $content, int $offset, array $prefixes, array $aliases, array $blocks ) {
		if ( empty( $prefixes ) ) {
			return false;
		}

		$len = strlen( $content );

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( $offset < $len && false !== ( $pos = strpos( $content, '<', $offset ) ) ) {
			if ( null !== self::parse_prefix_open_tag( $content, $pos, $prefixes, $aliases, $blocks ) ) {
				return $pos;
			}

			$offset = $pos + 1;
		}

		return false;
	}

	/**
	 * Parse a prefix opening tag at a given position.
	 *
	 * @param string                      $content  Content to parse.
	 * @param int                         $pos      Position of the opening <.
	 * @param array<string,array<string>> $prefixes Prefix => namespaces map.
	 * @param array<string,string>        $aliases  Custom tag => block name map.
	 * @param array                       $blocks   Registered Blockstudio blocks.
	 *
	 * @return array{tag_name:string,block_name:string,name_end:int}|null
	 */
	private static function parse_prefix_open_tag( string $content, int $pos, array $prefixes, array $aliases, array $blocks ): ?array {
		if ( '<' !== ( $content[ $pos ] ?? '' ) || '/' === ( $content[ $pos + 1 ] ?? '' ) ) {
			return null;
		}

		$remaining = substr( $content, $pos, 120 );
		if ( ! preg_match( '/^<([a-z][a-z0-9]*(?:-[a-z0-9]+)*)(?=[\s>\/])/i', $remaining, $matches ) ) {
			return null;
		}

		$tag_name = strtolower( $matches[1] );

		if ( isset( $aliases[ $tag_name ] ) ) {
			return null;
		}

		$block_name = self::resolve_prefix_tag_name( $tag_name, $prefixes, $blocks );

		if ( ! $block_name ) {
			return null;
		}

		return array(
			'tag_name'   => $tag_name,
			'block_name' => $block_name,
			'name_end'   => $pos + 1 + strlen( $matches[1] ),
		);
	}

	/**
	 * Resolve a bs: tag name to a block name.
	 *
	 * First hyphen maps to namespace separator. Checks both Blockstudio
	 * blocks and the WordPress block type registry.
	 *
	 * @param string $tag_name The tag name (without bs: prefix).
	 * @param array  $blocks   Registered Blockstudio blocks.
	 *
	 * @return string|false The full block name or false if not found.
	 */
	private static function resolve_bs_tag_name( string $tag_name, array $blocks ) {
		$pos = 0;
		while ( true ) {
			$pos = strpos( $tag_name, '-', $pos );

			if ( false === $pos ) {
				break;
			}

			$full_name = substr_replace( $tag_name, '/', $pos, 1 );

			$is_registered = isset( $blocks[ $full_name ] )
				|| \WP_Block_Type_Registry::get_instance()->is_registered( $full_name );

			if ( $is_registered ) {
				return self::check_allow_deny( $full_name );
			}

			++$pos;
		}

		return false;
	}

	/**
	 * Resolve a prefix tag name to a block name.
	 *
	 * The prefix fixes the namespace, so the remaining dashed portion maps
	 * directly to the block slug and each namespace is tried in order.
	 *
	 * @param string                      $tag_name The full custom tag name.
	 * @param array<string,array<string>> $prefixes Prefix => namespaces map.
	 * @param array                       $blocks   Registered Blockstudio blocks.
	 *
	 * @return string|false The full block name or false if not found.
	 */
	private static function resolve_prefix_tag_name( string $tag_name, array $prefixes, array $blocks ) {
		$hyphen_pos = strpos( $tag_name, '-' );

		if ( false === $hyphen_pos ) {
			return false;
		}

		$prefix = substr( $tag_name, 0, $hyphen_pos );
		$slug   = substr( $tag_name, $hyphen_pos + 1 );

		if ( '' === $slug || empty( $prefixes[ $prefix ] ) ) {
			return false;
		}

		foreach ( $prefixes[ $prefix ] as $namespace ) {
			$full_name = $namespace . '/' . $slug;

			$is_registered = isset( $blocks[ $full_name ] )
				|| \WP_Block_Type_Registry::get_instance()->is_registered( $full_name );

			if ( $is_registered ) {
				return self::check_allow_deny( $full_name );
			}
		}

		// Nested prefixes: a brand prefix can compose over a namespace prefix,
		// so <theme-ui-input> falls through to <ui-input> and resolves bsui/input.
		// Recursion is on the strictly shorter slug, so it always terminates.
		$nested_hyphen = strpos( $slug, '-' );
		if ( false !== $nested_hyphen ) {
			$nested_prefix = substr( $slug, 0, $nested_hyphen );
			if ( $nested_prefix !== $prefix && ! empty( $prefixes[ $nested_prefix ] ) ) {
				return self::resolve_prefix_tag_name( $slug, $prefixes, $blocks );
			}
		}

		return false;
	}

	/**
	 * Whether rendered output contains any block tag worth parsing.
	 *
	 * Mirrors render()'s own short-circuit guard: covers the built-in
	 * <bs:...> / <block ...> syntaxes plus every registered prefix
	 * (<theme-..., <ui-...) and alias tag, so block templates can emit those
	 * tags instead of calling render helpers directly. Prefixes and aliases
	 * are resolved fresh so a filter registered after the first probe is
	 * always seen.
	 *
	 * @param string $string The rendered output.
	 *
	 * @return bool
	 */
	public static function output_has_tags( string $string ): bool {
		if ( str_contains( $string, '<bs:' ) || str_contains( $string, '<block ' ) ) {
			return true;
		}

		return self::has_prefix_tags( $string, self::get_tag_prefixes() )
			|| self::has_alias_tags( $string, self::get_tag_aliases() );
	}

	/**
	 * Check a block name against allow/deny lists.
	 *
	 * @param string $block_name Full block name (e.g. core/paragraph).
	 *
	 * @return string|false The block name if allowed, false if denied or empty.
	 */
	private static function check_allow_deny( string $block_name ) {
		if ( '' === $block_name || false === strpos( $block_name, '/' ) ) {
			return false;
		}

		$is_registered = isset( Build::blocks()[ $block_name ] )
			|| \WP_Block_Type_Registry::get_instance()->is_registered( $block_name );

		if ( ! $is_registered ) {
			return false;
		}

		$allow = Settings::get( 'blockTags/allow' );
		$deny  = Settings::get( 'blockTags/deny' );

		$allow = apply_filters( 'blockstudio/block_tags/allow', $allow );
		$deny  = apply_filters( 'blockstudio/block_tags/deny', $deny );

		if ( ! empty( $deny ) && is_array( $deny ) ) {
			foreach ( $deny as $pattern ) {
				if ( fnmatch( $pattern, $block_name ) ) {
					return false;
				}
			}
		}

		if ( ! empty( $allow ) && is_array( $allow ) ) {
			foreach ( $allow as $pattern ) {
				if ( fnmatch( $pattern, $block_name ) ) {
					return $block_name;
				}
			}
			return false;
		}

		return $block_name;
	}

	// -------------------------------------------------------------------------
	// Shared utilities
	// -------------------------------------------------------------------------

	/**
	 * Find the closing > of an opening tag, skipping quoted attribute values.
	 *
	 * @param string $content The content string.
	 * @param int    $start   Position to start scanning from.
	 *
	 * @return int|false Position of the > character, or false if not found.
	 */
	private static function find_closing_angle( string $content, int $start ) {
		$pos         = $start;
		$content_len = strlen( $content );
		$in_quote    = false;
		$quote_ch    = '';

		while ( $pos < $content_len ) {
			$ch = $content[ $pos ];

			if ( $in_quote ) {
				if ( $ch === $quote_ch ) {
					$in_quote = false;
				}
			} elseif ( '"' === $ch || "'" === $ch ) {
				$in_quote = true;
				$quote_ch = $ch;
			} elseif ( '>' === $ch ) {
				return $pos;
			}

			++$pos;
		}

		return false;
	}

	/**
	 * Find the matching closing tag, handling nesting depth.
	 *
	 * @param string $content   The content string.
	 * @param int    $gt_pos    Position of the opening tag's >.
	 * @param string $open_tag  Opening tag prefix (e.g. '<bs:name' or '<block ').
	 * @param string $close_tag Full closing tag (e.g. '</bs:name>' or '</block>').
	 *
	 * @return int|false Position of the matching closing tag, or false.
	 */
	private static function find_matching_close( string $content, int $gt_pos, string $open_tag, string $close_tag ) {
		$search_pos  = $gt_pos + 1;
		$depth       = 1;
		$close_pos   = false;
		$content_len = strlen( $content );

		while ( $depth > 0 && $search_pos < $content_len ) {
			$next_open  = strpos( $content, $open_tag, $search_pos );
			$next_close = strpos( $content, $close_tag, $search_pos );

			if ( false === $next_close ) {
				break;
			}

			if ( false !== $next_open && $next_open < $next_close ) {
				$check_gt = strpos( $content, '>', $next_open );
				if ( false !== $check_gt && '/' !== $content[ $check_gt - 1 ] ) {
					++$depth;
				}
				$search_pos = ( false !== $check_gt ) ? $check_gt + 1 : $next_open + 1;
			} else {
				--$depth;
				if ( 0 === $depth ) {
					$close_pos = $next_close;
				}
				$search_pos = $next_close + strlen( $close_tag );
			}
		}

		return ( $depth > 0 ) ? false : $close_pos;
	}

	/**
	 * Parse inner content into an array of WordPress block arrays.
	 *
	 * Scans for <block> and <bs:> tags, builds block arrays recursively
	 * using the registered renderers. Blockstudio blocks use the fallback.
	 *
	 * @param string $content Inner content to parse.
	 *
	 * @return array Array of WordPress block arrays.
	 */
	public static function parse_inner_blocks( string $content ): array {
		if ( '' === trim( $content ) ) {
			return array();
		}

		$blocks            = array();
		$offset            = 0;
		$len               = strlen( $content );
		$aliases           = self::get_tag_aliases();
		$prefixes          = self::get_tag_prefixes();
		$registered_blocks = Build::blocks();
		$has_prefix_tags   = self::has_prefix_tags( $content, $prefixes );

		while ( $offset < $len ) {
			$bs_pos     = strpos( $content, '<bs:', $offset );
			$block_pos  = strpos( $content, '<block ', $offset );
			$alias_pos  = self::find_next_alias_tag( $content, $offset, $aliases );
			$prefix_pos = $has_prefix_tags ? self::find_next_prefix_tag( $content, $offset, $prefixes, $aliases, $registered_blocks ) : false;
			$candidates = array();

			if ( false !== $bs_pos ) {
				$candidates[] = array(
					'pos'      => $bs_pos,
					'priority' => 2,
					'type'     => 'bs',
				);
			}
			if ( false !== $block_pos ) {
				$candidates[] = array(
					'pos'      => $block_pos,
					'priority' => 3,
					'type'     => 'block',
				);
			}
			if ( false !== $alias_pos ) {
				$candidates[] = array(
					'pos'      => $alias_pos,
					'priority' => 0,
					'type'     => 'alias',
				);
			}
			if ( false !== $prefix_pos ) {
				$candidates[] = array(
					'pos'      => $prefix_pos,
					'priority' => 1,
					'type'     => 'prefix',
				);
			}

			if ( empty( $candidates ) ) {
				break;
			}

			usort(
				$candidates,
				static function ( array $a, array $b ): int {
					$position_comparison = $a['pos'] <=> $b['pos'];

					if ( 0 !== $position_comparison ) {
						return $position_comparison;
					}

					return $a['priority'] <=> $b['priority'];
				}
			);

			$pos   = $candidates[0]['pos'];
			$type  = $candidates[0]['type'];
			$is_bs = 'bs' === $type;

			$tag_name = '';
			if ( $is_bs ) {
				$tag_start = $pos + 4;
				$tag_end   = $tag_start;
				while ( $tag_end < $len && preg_match( '/[a-z0-9-]/', $content[ $tag_end ] ) ) {
					++$tag_end;
				}
				$tag_name = substr( $content, $tag_start, $tag_end - $tag_start );
				if ( '' === $tag_name || false === strpos( $tag_name, '-' ) ) {
					$offset = $pos + 1;
					continue;
				}
				$block_name = substr_replace( $tag_name, '/', strpos( $tag_name, '-' ), 1 );
				$attr_start = $tag_end;
			} elseif ( 'alias' === $type ) {
				$parsed_alias = self::parse_alias_open_tag( $content, $pos, $aliases );
				if ( null === $parsed_alias ) {
					$offset = $pos + 1;
					continue;
				}
				$tag_name   = $parsed_alias['tag_name'];
				$block_name = $parsed_alias['block_name'];
				$attr_start = $parsed_alias['name_end'];
			} elseif ( 'prefix' === $type ) {
				$parsed_prefix = self::parse_prefix_open_tag( $content, $pos, $prefixes, $aliases, $registered_blocks );
				if ( null === $parsed_prefix ) {
					$offset = $pos + 1;
					continue;
				}
				$tag_name   = $parsed_prefix['tag_name'];
				$block_name = $parsed_prefix['block_name'];
				$attr_start = $parsed_prefix['name_end'];
			} else {
				$attr_start = $pos + 6;
				$block_name = null;
			}

			$gt_pos = self::find_closing_angle( $content, $attr_start );
			if ( false === $gt_pos ) {
				break;
			}

			$is_self_closing = ( '/' === $content[ $gt_pos - 1 ] );
			$attr_end        = $is_self_closing ? $gt_pos - 1 : $gt_pos;
			$attr_string     = trim( substr( $content, $attr_start, $attr_end - $attr_start ) );
			$attrs           = self::parse_attributes( $attr_string );

			if ( 'block' === $type ) {
				$block_name = $attrs['name'] ?? '';
				unset( $attrs['name'] );
			}

			if ( empty( $block_name ) ) {
				$offset = $gt_pos + 1;
				continue;
			}

			$inner = '';
			if ( ! $is_self_closing ) {
				if ( $is_bs ) {
					$close_tag = '</bs:' . $tag_name . '>';
					$open_tag  = '<bs:' . $tag_name;
				} elseif ( 'alias' === $type || 'prefix' === $type ) {
					$close_tag = '</' . $tag_name . '>';
					$open_tag  = '<' . $tag_name;
				} else {
					$close_tag = '</block>';
					$open_tag  = '<block ';
				}
				$close_pos = self::find_matching_close( $content, $gt_pos, $open_tag, $close_tag );
				if ( false === $close_pos ) {
					$offset = $gt_pos + 1;
					continue;
				}
				$inner  = substr( $content, $gt_pos + 1, $close_pos - $gt_pos - 1 );
				$offset = $close_pos + strlen( $close_tag );
			} else {
				$offset = $gt_pos + 1;
			}

			$blocks[] = self::build_block_array( $block_name, $attrs, $inner );
		}

		return $blocks;
	}

	/**
	 * HTML tag to block name mapping for pages/patterns parsing.
	 *
	 * @var array<string, string>
	 */
	private static array $html_tag_map = array(
		'p'          => 'core/paragraph',
		'h1'         => 'core/heading',
		'h2'         => 'core/heading',
		'h3'         => 'core/heading',
		'h4'         => 'core/heading',
		'h5'         => 'core/heading',
		'h6'         => 'core/heading',
		'div'        => 'core/group',
		'section'    => 'core/group',
		'blockquote' => 'core/quote',
		'hr'         => 'core/separator',
		'img'        => 'core/image',
		'pre'        => 'core/preformatted',
		'code'       => 'core/code',
		'ul'         => 'core/list',
		'ol'         => 'core/list',
		'table'      => 'core/table',
		'figure'     => 'core/image',
		'audio'      => 'core/audio',
		'video'      => 'core/video',
		'details'    => 'core/details',
	);

	/**
	 * HTML elements that should parse their inner HTML as child blocks when
	 * mapped to a custom block.
	 *
	 * @var array<string, bool>
	 */
	private static array $html_container_tags = array(
		'blockquote' => true,
		'details'    => true,
		'div'        => true,
		'figure'     => true,
		'ol'         => true,
		'section'    => true,
		'table'      => true,
		'ul'         => true,
	);

	/**
	 * Self-closing HTML tags.
	 *
	 * @var array<string, bool>
	 */
	private static array $void_tags = array(
		'hr'  => true,
		'img' => true,
		'br'  => true,
	);

	/**
	 * Return the HTML element to block mapping.
	 *
	 * Mapping values may be block names or callables. Callable mappings receive
	 * the element attributes, inner HTML, and tag name, and must return a block
	 * name string.
	 *
	 * @return array<string, string|callable>
	 */
	private static function get_html_tag_map(): array {
		$mapping = apply_filters( 'blockstudio/parser/element_mapping', self::$html_tag_map, null );

		return is_array( $mapping ) ? $mapping : self::$html_tag_map;
	}

	/**
	 * Resolve one HTML tag to a target block name.
	 *
	 * @param mixed  $mapping_value Block name string or callable mapper.
	 * @param string $html_tag      HTML tag name.
	 * @param array  $attrs         Parsed element attributes.
	 * @param string $inner         Inner HTML.
	 *
	 * @return string Target block name, or empty string to skip.
	 */
	private static function resolve_html_block_name( mixed $mapping_value, string $html_tag, array $attrs, string $inner ): string {
		if ( is_callable( $mapping_value ) ) {
			$mapping_value = call_user_func( $mapping_value, $attrs, $inner, $html_tag );
		}

		return is_string( $mapping_value ) ? $mapping_value : '';
	}

	/**
	 * Whether a mapped element's inner HTML should become a content attribute.
	 *
	 * @param string                        $html_tag          HTML tag name.
	 * @param string                        $block_name        Target block name.
	 * @param array                         $attrs             Parsed attributes.
	 * @param string                        $inner             Inner HTML.
	 * @param array                         $registered_blocks Registered Blockstudio blocks.
	 * @param array<string,string|callable> $map               HTML tag map.
	 * @param array<string,string>          $aliases           Custom tag aliases.
	 * @param array<string,array<string>>   $prefixes          Prefix namespace map.
	 *
	 * @return bool True when content should be routed.
	 */
	private static function should_route_inner_content_to_attribute(
		string $html_tag,
		string $block_name,
		array $attrs,
		string $inner,
		array $registered_blocks,
		array $map,
		array $aliases,
		array $prefixes
	): bool {
		$text_tags = array(
			'p' => true,
		);

		if (
			! isset( $text_tags[ $html_tag ] )
			|| isset( $attrs['content'] )
			|| '' === trim( $inner )
			|| str_starts_with( $block_name, 'core/' )
			|| ! self::block_declares_richtext_content_attribute( $block_name, $registered_blocks )
		) {
			return false;
		}

		return ! self::inner_content_has_nested_block_tags( $inner, $map, $aliases, $prefixes, $registered_blocks );
	}

	/**
	 * Whether a registered Blockstudio block declares content as richtext.
	 *
	 * @param string $block_name        Block name.
	 * @param array  $registered_blocks Registered Blockstudio blocks.
	 *
	 * @return bool True when the block has a content richtext attribute.
	 */
	private static function block_declares_richtext_content_attribute( string $block_name, array $registered_blocks ): bool {
		if ( ! isset( $registered_blocks[ $block_name ] ) ) {
			return false;
		}

		$block      = $registered_blocks[ $block_name ];
		$attributes = array();

		if ( is_object( $block ) ) {
			$attributes = $block->blockstudio['attributes'] ?? array();
		} elseif ( is_array( $block ) ) {
			$attributes = $block['blockstudio']['attributes'] ?? array();
		}

		foreach ( $attributes as $attribute ) {
			if (
				is_array( $attribute )
				&& 'content' === ( $attribute['id'] ?? null )
				&& 'richtext' === ( $attribute['type'] ?? $attribute['field'] ?? null )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether routed richtext content contains nested block-level structures.
	 *
	 * @param string                        $inner             Inner HTML.
	 * @param array<string,string|callable> $map               HTML tag map.
	 * @param array<string,string>          $aliases           Custom tag aliases.
	 * @param array<string,array<string>>   $prefixes          Prefix namespace map.
	 * @param array                         $registered_blocks Registered Blockstudio blocks.
	 *
	 * @return bool True when nested blocks or mapped elements are present.
	 */
	private static function inner_content_has_nested_block_tags(
		string $inner,
		array $map,
		array $aliases,
		array $prefixes,
		array $registered_blocks
	): bool {
		if (
			false !== strpos( $inner, '<bs:' )
			|| false !== strpos( $inner, '<block ' )
			|| self::has_alias_tags( $inner, $aliases )
			|| self::has_prefix_tags( $inner, $prefixes )
		) {
			return true;
		}

		if ( ! preg_match_all( '/<\s*\/?\s*([a-z][a-z0-9]*)\b/i', $inner, $matches ) ) {
			return false;
		}

		$inline_tags = array(
			'a'      => true,
			'b'      => true,
			'br'     => true,
			'code'   => true,
			'del'    => true,
			'em'     => true,
			'i'      => true,
			'ins'    => true,
			'mark'   => true,
			's'      => true,
			'small'  => true,
			'span'   => true,
			'strong' => true,
			'sub'    => true,
			'sup'    => true,
			'u'      => true,
		);

		foreach ( $matches[1] as $tag ) {
			$tag = strtolower( $tag );

			if ( isset( $map[ $tag ] ) ) {
				return true;
			}

			if ( ! isset( $inline_tags[ $tag ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse all elements including raw HTML tags (for pages/patterns).
	 *
	 * Like parse_inner_blocks but also recognizes raw HTML elements
	 * (<p>, <div>, <h1>, etc.) and maps them to WordPress blocks.
	 *
	 * @param string $content Content to parse.
	 *
	 * @return array Array of WordPress block arrays.
	 */
	public static function parse_all_elements( string $content ): array {
		if ( '' === trim( $content ) ) {
			return array();
		}

		$blocks            = array();
		$offset            = 0;
		$len               = strlen( $content );
		$map               = self::get_html_tag_map();
		$aliases           = self::get_tag_aliases();
		$prefixes          = self::get_tag_prefixes();
		$registered_blocks = Build::blocks();
		$has_prefix_tags   = self::has_prefix_tags( $content, $prefixes );

		while ( $offset < $len ) {
			// Find next tag of any kind.
			$tag_pos = strpos( $content, '<', $offset );

			if ( false === $tag_pos ) {
				// Remaining text.
				$text = trim( substr( $content, $offset ) );
				if ( '' !== $text ) {
					$blocks[] = self::build_paragraph( array(), $text );
				}
				break;
			}

			// Text before tag.
			if ( $tag_pos > $offset ) {
				$text = trim( substr( $content, $offset, $tag_pos - $offset ) );
				if ( '' !== $text ) {
					$blocks[] = self::build_paragraph( array(), $text );
				}
			}

			// Check what kind of tag.
			$parsed_alias = self::parse_alias_open_tag( $content, $tag_pos, $aliases );
			if ( null !== $parsed_alias ) {
				$result = self::parse_single_alias_tag( $content, $tag_pos, $parsed_alias );

				if ( null !== $result ) {
					$block_arr = $result['block'];

					if ( ! empty( $result['inner'] ) ) {
						$is_container = ! str_starts_with( $block_arr['blockName'], 'core/' );

						if ( $is_container ) {
							$inner_blocks = self::parse_all_elements( $result['inner'] );
							$original_ic  = $block_arr['innerContent'];
							$has_wrapper  = ! empty( $original_ic ) && is_string( $original_ic[0] );
							$new_ic       = $has_wrapper ? array( $original_ic[0] ) : array();
							foreach ( $inner_blocks as $ib ) {
								$new_ic[] = null;
							}
							if ( $has_wrapper ) {
								$new_ic[] = end( $original_ic );
							}
							$block_arr['innerBlocks']  = $inner_blocks;
							$block_arr['innerContent'] = $new_ic;
						}
					}

					$blocks[] = $block_arr;
					$offset   = $result['offset'];
					continue;
				}
			}

			$parsed_prefix = $has_prefix_tags ? self::parse_prefix_open_tag( $content, $tag_pos, $prefixes, $aliases, $registered_blocks ) : null;
			if ( null !== $parsed_prefix ) {
				$result = self::parse_single_alias_tag( $content, $tag_pos, $parsed_prefix );

				if ( null !== $result ) {
					$block_arr = $result['block'];

					if ( ! empty( $result['inner'] ) ) {
						$is_container = ! str_starts_with( $block_arr['blockName'], 'core/' );

						if ( $is_container ) {
							$inner_blocks = self::parse_all_elements( $result['inner'] );
							$original_ic  = $block_arr['innerContent'];
							$has_wrapper  = ! empty( $original_ic ) && is_string( $original_ic[0] );
							$new_ic       = $has_wrapper ? array( $original_ic[0] ) : array();
							foreach ( $inner_blocks as $ib ) {
								$new_ic[] = null;
							}
							if ( $has_wrapper ) {
								$new_ic[] = end( $original_ic );
							}
							$block_arr['innerBlocks']  = $inner_blocks;
							$block_arr['innerContent'] = $new_ic;
						}
					}

					$blocks[] = $block_arr;
					$offset   = $result['offset'];
					continue;
				}
			}

			if ( '<bs:' === substr( $content, $tag_pos, 4 ) || '<block ' === substr( $content, $tag_pos, 7 ) ) {
				$is_bs  = '<bs:' === substr( $content, $tag_pos, 4 );
				$result = self::parse_single_block_tag( $content, $tag_pos, $is_bs );

				if ( null !== $result ) {
					$block_arr = $result['block'];

					// In parse_all_elements context, container blocks need
					// their inner content parsed with parse_all_elements too.
					if ( ! empty( $result['inner'] ) ) {
						$container_names = array(
							'core/group',
							'core/columns',
							'core/column',
							'core/buttons',
							'core/quote',
							'core/cover',
							'core/details',
							'core/gallery',
							'core/social-links',
							'core/query',
							'core/comments',
							'core/media-text',
							'core/accordion',
							'core/accordion-item',
							'core/accordion-panel',
						);

						$is_container = in_array( $block_arr['blockName'], $container_names, true )
							|| ! str_starts_with( $block_arr['blockName'], 'core/' );
						if ( $is_container ) {
							$inner_blocks = self::parse_all_elements( $result['inner'] );
							$original_ic  = $block_arr['innerContent'];
							$has_wrapper  = ! empty( $original_ic ) && is_string( $original_ic[0] );
							$new_ic       = $has_wrapper ? array( $original_ic[0] ) : array();
							foreach ( $inner_blocks as $ib ) {
								$new_ic[] = null;
							}
							if ( $has_wrapper ) {
								$new_ic[] = end( $original_ic );
							}
							$block_arr['innerBlocks']  = $inner_blocks;
							$block_arr['innerContent'] = $new_ic;
						}
					}

					$blocks[] = $block_arr;
					$offset   = $result['offset'];
					continue;
				}
			}

			// Check for HTML comment (<!-- ... -->).
			if ( '<!--' === substr( $content, $tag_pos, 4 ) ) {
				$comment_end = strpos( $content, '-->', $tag_pos + 4 );
				$offset      = false !== $comment_end ? $comment_end + 3 : $tag_pos + 1;
				continue;
			}

			// Check for closing tag (skip).
			if ( '</' === substr( $content, $tag_pos, 2 ) ) {
				$gt     = strpos( $content, '>', $tag_pos );
				$offset = false !== $gt ? $gt + 1 : $tag_pos + 1;
				continue;
			}

			// Raw HTML element.
			$tag_match = array();
			if ( preg_match( '/^<([a-z][a-z0-9]*)/i', substr( $content, $tag_pos, 80 ), $tag_match ) ) {
				$html_tag = strtolower( $tag_match[1] );

				if ( isset( $map[ $html_tag ] ) ) {
					$gt_pos = self::find_closing_angle( $content, $tag_pos + 1 );
					if ( false === $gt_pos ) {
						$offset = $tag_pos + 1;
						continue;
					}

					$is_void     = isset( self::$void_tags[ $html_tag ] ) || '/' === $content[ $gt_pos - 1 ];
					$attr_start  = $tag_pos + strlen( $tag_match[0] );
					$attr_end    = $is_void && '/' === $content[ $gt_pos - 1 ] ? $gt_pos - 1 : $gt_pos;
					$attr_string = trim( substr( $content, $attr_start, $attr_end - $attr_start ) );
					$attrs       = self::parse_attributes( $attr_string );
					$inner       = '';
					$block_name  = '';

					if ( ! $is_void ) {
						$close_tag = '</' . $html_tag . '>';
						$open_tag  = '<' . $html_tag;
						$close_pos = self::find_matching_close( $content, $gt_pos, $open_tag, $close_tag );
						if ( false !== $close_pos ) {
							$inner  = substr( $content, $gt_pos + 1, $close_pos - $gt_pos - 1 );
							$offset = $close_pos + strlen( $close_tag );
						} else {
							$offset = $gt_pos + 1;
							continue;
						}
					} else {
						$offset = $gt_pos + 1;
					}

					$block_name = self::resolve_html_block_name( $map[ $html_tag ], $html_tag, $attrs, $inner );
					if ( '' === $block_name ) {
						continue;
					}

					$inner_for_block = $inner;

					// Heading level from tag name.
					if ( preg_match( '/^h[1-6]$/', $html_tag ) ) {
						$attrs['level'] = (int) substr( $html_tag, 1 );

						if ( 'core/heading' !== $block_name && '' !== trim( $inner ) && ! isset( $attrs['content'] ) ) {
							$attrs['content'] = trim( $inner );
							$inner_for_block  = '';
						}
					} elseif ( self::should_route_inner_content_to_attribute( $html_tag, $block_name, $attrs, $inner, $registered_blocks, $map, $aliases, $prefixes ) ) {
						$attrs['content'] = trim( $inner );
						$inner_for_block  = '';
					}

					// Ordered list.
					if ( 'core/list' === $block_name && 'ol' === $html_tag ) {
						$attrs['ordered'] = true;
					}

					// Image: extract src/alt from tag attrs or inner <img>.
					if ( 'core/image' === $block_name ) {
						if ( isset( $attrs['src'] ) ) {
							$attrs['url'] = $attrs['src'];
							unset( $attrs['src'] );
						}
						// <figure> wrapping <img>: extract img attrs.
						// <figure> without <img>: fall back to HTML block.
						if ( 'figure' === $html_tag ) {
							if ( '' !== $inner && preg_match( '/<img\s+([^>]*)\/?>/i', $inner, $img_match ) ) {
								$img_attrs = self::parse_attributes( $img_match[1] );
								if ( isset( $img_attrs['src'] ) ) {
									$attrs['url'] = $img_attrs['src'];
								}
								if ( isset( $img_attrs['alt'] ) ) {
									$attrs['alt'] = $img_attrs['alt'];
								}
							} else {
								$raw_html = '<figure' . substr( $content, $attr_start, $gt_pos - $attr_start + 1 );
								if ( '' !== $inner ) {
									$raw_html .= $inner . '</figure>';
								}
								$blocks[] = array(
									'blockName'    => 'core/html',
									'attrs'        => array(),
									'innerBlocks'  => array(),
									'innerHTML'    => $raw_html,
									'innerContent' => array( $raw_html ),
								);
								continue;
							}
						}
					}

					// For container HTML elements, recursively parse with parse_all_elements.
					$container_blocks = array( 'core/group', 'core/quote' );
					$is_container     = in_array( $block_name, $container_blocks, true )
						|| ( isset( self::$html_container_tags[ $html_tag ] ) && ! str_starts_with( $block_name, 'core/' ) );
					if ( $is_container && '' !== $inner ) {
						$inner_blocks = self::parse_all_elements( $inner );
						$block_array  = self::build_block_array( $block_name, $attrs, '' );

						// Rebuild innerContent using wrapper fragments when the renderer provides them.
						$original_ic = $block_array['innerContent'];
						$has_wrapper = ! empty( $original_ic ) && is_string( $original_ic[0] );
						$new_ic      = $has_wrapper ? array( $original_ic[0] ) : array();
						foreach ( $inner_blocks as $ib ) {
							$new_ic[] = null;
						}
						if ( $has_wrapper ) {
							$new_ic[] = end( $original_ic );
						}
						$block_array['innerBlocks']  = $inner_blocks;
						$block_array['innerContent'] = $new_ic;
						$blocks[]                    = $block_array;
					} else {
						$blocks[] = self::build_block_array( $block_name, $attrs, $inner_for_block );
					}
					continue;
				}
			}

			// Unknown HTML tag: create core/html block.
			if ( preg_match( '/^<([a-z][a-z0-9]*)/i', substr( $content, $tag_pos, 80 ), $unk_match ) ) {
				$unk_tag = strtolower( $unk_match[1] );
				$gt_pos  = self::find_closing_angle( $content, $tag_pos + 1 );
				if ( false !== $gt_pos ) {
					$is_void = isset( self::$void_tags[ $unk_tag ] ) || '/' === $content[ $gt_pos - 1 ];
					$raw     = '';
					if ( $is_void ) {
						$raw    = substr( $content, $tag_pos, $gt_pos - $tag_pos + 1 );
						$offset = $gt_pos + 1;
					} else {
						$close_tag = '</' . $unk_tag . '>';
						$close_pos = self::find_matching_close( $content, $gt_pos, '<' . $unk_tag, $close_tag );
						if ( false !== $close_pos ) {
							$raw    = substr( $content, $tag_pos, $close_pos + strlen( $close_tag ) - $tag_pos );
							$offset = $close_pos + strlen( $close_tag );
						} else {
							$offset = $gt_pos + 1;
							continue;
						}
					}
					if ( '' !== $raw ) {
						$blocks[] = array(
							'blockName'    => 'core/html',
							'attrs'        => array(),
							'innerBlocks'  => array(),
							'innerHTML'    => $raw,
							'innerContent' => array( $raw ),
						);
						continue;
					}
				}
			}
			$offset = $tag_pos + 1;
		}

		return $blocks;
	}

	/**
	 * Parse a single alias tag at the given position.
	 *
	 * @param string $content      The content string.
	 * @param int    $pos          Position of the opening <.
	 * @param array  $parsed_alias Parsed alias metadata.
	 *
	 * @return array{block: array, inner: string, offset: int}|null Parsed block and new offset, or null.
	 */
	private static function parse_single_alias_tag( string $content, int $pos, array $parsed_alias ): ?array {
		$tag_name   = $parsed_alias['tag_name'];
		$block_name = $parsed_alias['block_name'];
		$attr_start = $parsed_alias['name_end'];
		$gt_pos     = self::find_closing_angle( $content, $attr_start );

		if ( false === $gt_pos ) {
			return null;
		}

		$is_self_closing = ( '/' === $content[ $gt_pos - 1 ] );
		$attr_end        = $is_self_closing ? $gt_pos - 1 : $gt_pos;
		$attr_string     = trim( substr( $content, $attr_start, $attr_end - $attr_start ) );
		$attrs           = self::parse_attributes( $attr_string );
		$inner           = '';
		$offset          = $gt_pos + 1;

		if ( ! $is_self_closing ) {
			$close_tag = '</' . $tag_name . '>';
			$open_tag  = '<' . $tag_name;
			$close_pos = self::find_matching_close( $content, $gt_pos, $open_tag, $close_tag );
			if ( false === $close_pos ) {
				return null;
			}
			$inner  = substr( $content, $gt_pos + 1, $close_pos - $gt_pos - 1 );
			$offset = $close_pos + strlen( $close_tag );
		}

		return array(
			'block'  => self::build_block_array( $block_name, $attrs, $inner ),
			'inner'  => $inner,
			'offset' => $offset,
		);
	}

	/**
	 * Parse a single <block> or <bs:> tag at the given position.
	 *
	 * @param string $content The content string.
	 * @param int    $pos     Position of the opening <.
	 * @param bool   $is_bs   Whether this is a <bs:> tag.
	 *
	 * @return array{block: array, inner: string, offset: int}|null Parsed block, inner content, and new offset, or null.
	 */
	private static function parse_single_block_tag( string $content, int $pos, bool $is_bs ): ?array {
		$len      = strlen( $content );
		$tag_name = '';

		if ( $is_bs ) {
			$tag_start = $pos + 4;
			$tag_end   = $tag_start;
			while ( $tag_end < $len && preg_match( '/[a-z0-9-]/', $content[ $tag_end ] ) ) {
				++$tag_end;
			}
			$tag_name = substr( $content, $tag_start, $tag_end - $tag_start );
			if ( '' === $tag_name || false === strpos( $tag_name, '-' ) ) {
				return null;
			}
			$block_name = self::resolve_bs_tag_name( $tag_name, Build::blocks() );
			if ( ! $block_name ) {
				$block_name = substr_replace( $tag_name, '/', strpos( $tag_name, '-' ), 1 );
			}
			$attr_start = $tag_end;
		} else {
			$attr_start = $pos + 6;
			$block_name = null;
		}

		$gt_pos = self::find_closing_angle( $content, $attr_start );
		if ( false === $gt_pos ) {
			return null;
		}

		$is_self_closing = ( '/' === $content[ $gt_pos - 1 ] );
		$attr_end        = $is_self_closing ? $gt_pos - 1 : $gt_pos;
		$attr_string     = trim( substr( $content, $attr_start, $attr_end - $attr_start ) );
		$attrs           = self::parse_attributes( $attr_string );

		if ( ! $is_bs ) {
			$block_name = $attrs['name'] ?? '';
			unset( $attrs['name'] );
		}

		if ( empty( $block_name ) ) {
			return null;
		}

		$inner  = '';
		$offset = $gt_pos + 1;

		if ( ! $is_self_closing ) {
			if ( $is_bs ) {
				$close_tag = '</bs:' . $tag_name . '>';
				$open_tag  = '<bs:' . $tag_name;
			} else {
				$close_tag = '</block>';
				$open_tag  = '<block ';
			}
			$close_pos = self::find_matching_close( $content, $gt_pos, $open_tag, $close_tag );
			if ( false === $close_pos ) {
				return null;
			}
			$inner  = substr( $content, $gt_pos + 1, $close_pos - $gt_pos - 1 );
			$offset = $close_pos + strlen( $close_tag );
		}

		return array(
			'block'  => self::build_block_array( $block_name, $attrs, $inner ),
			'inner'  => $inner,
			'offset' => $offset,
		);
	}

	/**
	 * Build a WordPress block array.
	 *
	 * Checks the renderer registry, calls the appropriate renderer.
	 * Falls back to a generic block array with recursive inner blocks.
	 *
	 * @param string $block_name    Full block name.
	 * @param array  $attrs         Block attributes.
	 * @param string $inner_content Inner content string.
	 *
	 * @return array WordPress block array.
	 */
	public static function build_block_array( string $block_name, array $attrs, string $inner_content = '' ): array {
		// Remap key → __BLOCKSTUDIO_KEY for keyed block merging.
		if (
			array_key_exists( 'key', $attrs ) &&
			is_scalar( $attrs['key'] ) &&
			'' !== (string) $attrs['key']
		) {
			$attrs['__BLOCKSTUDIO_KEY'] = $attrs['key'];
			unset( $attrs['key'] );
		}

		$renderers = self::get_renderers( new Html_Parser() );
		if ( isset( $renderers[ $block_name ] ) ) {
			return call_user_func( $renderers[ $block_name ], $attrs, $inner_content );
		}

		// Generic fallback.
		$inner_blocks      = self::parse_inner_blocks( $inner_content );
		$inner_content_arr = array();
		foreach ( $inner_blocks as $block ) {
			$inner_content_arr[] = null;
		}

		// Blockstudio blocks expect field attrs under blockstudio.attributes.
		$has_blockstudio_key = array_key_exists( '__BLOCKSTUDIO_KEY', $attrs );
		$blockstudio_key     = $attrs['__BLOCKSTUDIO_KEY'] ?? null;

		unset( $attrs['__BLOCKSTUDIO_KEY'] );

		$block_attrs = $attrs;
		if ( ! empty( $attrs ) && ! isset( $attrs['blockstudio'] ) ) {
			$bs_blocks = Build::blocks();
			if ( isset( $bs_blocks[ $block_name ] ) ) {
				$block_attrs = array(
					'blockstudio' => array(
						'attributes' => $attrs,
					),
				);
			}
		}

		if ( $has_blockstudio_key ) {
			$block_attrs = array_merge(
				array( '__BLOCKSTUDIO_KEY' => $blockstudio_key ),
				$block_attrs
			);
		}

		return array(
			'blockName'    => $block_name,
			'attrs'        => $block_attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => $inner_content_arr,
		);
	}

	/**
	 * Build a core/paragraph block array.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 *
	 * @return array Block array.
	 */
	private static function build_paragraph( array $attrs, string $inner ): array {
		$html = '<p>' . $inner . '</p>';
		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Parse HTML-style attributes from a tag string.
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

				if ( 'true' === $value ) {
					$value = true;
				} elseif ( 'false' === $value ) {
					$value = false;
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

	// -------------------------------------------------------------------------
	// Block rendering
	// -------------------------------------------------------------------------

	/**
	 * Render a block with the given attributes.
	 *
	 * Routes to Block::render() for Blockstudio blocks or to render_core_block()
	 * for core/non-Blockstudio blocks.
	 *
	 * @param string $block_name    Full block name.
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

		$bs_blocks = Build::blocks();

		if ( isset( $bs_blocks[ $block_name ] ) ) {
			// Pre-process all nested tags for Blockstudio blocks.
			if ( '' !== $inner_content ) {
				$aliases    = self::get_tag_aliases();
				$prefixes   = self::get_tag_prefixes();
				$has_nested = false !== strpos( $inner_content, '<bs:' )
					|| false !== strpos( $inner_content, '<block ' )
					|| self::has_alias_tags( $inner_content, $aliases )
					|| self::has_prefix_tags( $inner_content, $prefixes );
				if ( $has_nested ) {
					$inner_content = self::render( $inner_content );
				}
			}
			$result = self::render_bs_block( $block_name, $block_attrs, $inner_content );
		} else {
			$result = self::render_core_block( $block_name, $block_attrs, $inner_content );
		}

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

	/**
	 * Render a Blockstudio block.
	 *
	 * @param string $block_name    Full block name.
	 * @param array  $block_attrs   Block attributes.
	 * @param string $inner_content Inner content from paired tags.
	 *
	 * @return string Rendered HTML.
	 */
	private static function render_bs_block( string $block_name, array $block_attrs, string $inner_content ): string {
		$parent = \WP_Block_Supports::$block_to_render;

		\WP_Block_Supports::$block_to_render = array(
			'blockName' => $block_name,
			'attrs'     => $block_attrs,
		);

		// Block tags embed rendered HTML, not editor blocks. Force frontend
		// mode so <InnerBlocks /> gets replaced with actual content.
		$mode = isset( $_GET['blockstudioMode'] ) ? sanitize_text_field( wp_unslash( $_GET['blockstudioMode'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['blockstudioMode'] );

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

		if ( null !== $mode ) {
			$_GET['blockstudioMode'] = $mode; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		\WP_Block_Supports::$block_to_render = $parent;

		$result = is_string( $result ) ? $result : '';

		return $result;
	}

	/**
	 * Render a core/non-Blockstudio block.
	 *
	 * Builds a block array directly using string-based builders,
	 * then calls WordPress render_block(). No DOMDocument.
	 *
	 * @param string $block_name    Full block name.
	 * @param array  $block_attrs   Block attributes.
	 * @param string $inner_content Inner content.
	 *
	 * @return string Rendered HTML.
	 */
	private static function render_core_block( string $block_name, array $block_attrs, string $inner_content ): string {
		$block_array = self::build_block_array( $block_name, $block_attrs, $inner_content );
		return \render_block( $block_array );
	}
}
