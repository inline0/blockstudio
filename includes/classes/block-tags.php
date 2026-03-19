<?php
/**
 * Block Tags class.
 *
 * Unified renderer for block tags in content. Supports two syntaxes:
 *   <bs:namespace-block attr="value" />
 *   <block name="namespace/block" attr="value" />
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
			return $content ?? '';
		}

		$has_bs    = false !== strpos( $content, '<bs:' );
		$has_block = false !== strpos( $content, '<block ' );

		if ( ! $has_bs && ! $has_block ) {
			return $content;
		}

		$blocks = Build::blocks();

		do {
			$previous = $content;

			if ( $has_bs ) {
				$content = self::replace_paired_bs_tags( $content, $blocks );
				$content = self::replace_self_closing_bs_tags( $content, $blocks );
			}

			if ( $has_block ) {
				$content = self::replace_paired_block_elements( $content );
				$content = self::replace_self_closing_block_elements( $content );
			}

			$has_bs    = false !== strpos( $content, '<bs:' );
			$has_block = false !== strpos( $content, '<block ' );
		} while ( $content !== $previous && ( $has_bs || $has_block ) );

		return $content;
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
		$pos = strpos( $tag_name, '-' );

		if ( false === $pos ) {
			return false;
		}

		$full_name = substr_replace( $tag_name, '/', $pos, 1 );

		$is_registered = isset( $blocks[ $full_name ] )
			|| \WP_Block_Type_Registry::get_instance()->is_registered( $full_name );

		if ( ! $is_registered ) {
			return false;
		}

		return self::check_allow_deny( $full_name );
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

		$blocks = array();
		$offset = 0;
		$len    = strlen( $content );

		while ( $offset < $len ) {
			$bs_pos    = strpos( $content, '<bs:', $offset );
			$block_pos = strpos( $content, '<block ', $offset );

			if ( false === $bs_pos && false === $block_pos ) {
				break;
			}

			if ( false === $bs_pos ) {
				$pos   = $block_pos;
				$is_bs = false;
			} elseif ( false === $block_pos ) {
				$pos   = $bs_pos;
				$is_bs = true;
			} else {
				$is_bs = $bs_pos < $block_pos;
				$pos   = $is_bs ? $bs_pos : $block_pos;
			}

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

			if ( ! $is_bs ) {
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

		$blocks = array();
		$offset = 0;
		$len    = strlen( $content );

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
							$open_tag     = ! empty( $original_ic ) ? $original_ic[0] : '';
							$close_tag    = ! empty( $original_ic ) ? end( $original_ic ) : '';
							$new_ic       = array( $open_tag );
							foreach ( $inner_blocks as $ib ) {
								$new_ic[] = null;
							}
							$new_ic[]                  = $close_tag;
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
			if ( preg_match( '/^<([a-z][a-z0-9]*)/i', substr( $content, $tag_pos, 20 ), $tag_match ) ) {
				$html_tag = strtolower( $tag_match[1] );

				if ( isset( self::$html_tag_map[ $html_tag ] ) ) {
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
					$block_name  = self::$html_tag_map[ $html_tag ];

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

					// Heading level from tag name.
					if ( 'core/heading' === $block_name ) {
						$attrs['level'] = (int) substr( $html_tag, 1 );
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
					if ( in_array( $block_name, $container_blocks, true ) && '' !== $inner ) {
						$inner_blocks = self::parse_all_elements( $inner );
						$block_array  = self::build_block_array( $block_name, $attrs, '' );

						// Rebuild innerContent using the wrapper from the trait renderer.
						$original_ic = $block_array['innerContent'];
						$open_tag    = ! empty( $original_ic ) ? $original_ic[0] : '';
						$close_tag   = ! empty( $original_ic ) ? end( $original_ic ) : '';
						$new_ic      = array( $open_tag );
						foreach ( $inner_blocks as $ib ) {
							$new_ic[] = null;
						}
						$new_ic[]                    = $close_tag;
						$block_array['innerBlocks']  = $inner_blocks;
						$block_array['innerContent'] = $new_ic;
						$blocks[]                    = $block_array;
					} else {
						$blocks[] = self::build_block_array( $block_name, $attrs, $inner );
					}
					continue;
				}
			}

			// Unknown HTML tag: create core/html block.
			if ( preg_match( '/^<([a-z][a-z0-9]*)/i', substr( $content, $tag_pos, 20 ), $unk_match ) ) {
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
	 * Parse a single <block> or <bs:> tag at the given position.
	 *
	 * @param string $content The content string.
	 * @param int    $pos     Position of the opening <.
	 * @param bool   $is_bs   Whether this is a <bs:> tag.
	 *
	 * @return array{block: array, offset: int}|null Parsed block and new offset, or null.
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
			$block_name = substr_replace( $tag_name, '/', strpos( $tag_name, '-' ), 1 );
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
		static $builders        = null;
		static $trait_renderers = null;

		// Remap key → __BLOCKSTUDIO_KEY for keyed block merging.
		if ( ! empty( $attrs['key'] ) ) {
			$attrs['__BLOCKSTUDIO_KEY'] = $attrs['key'];
			unset( $attrs['key'] );
		}

		if ( null === $builders ) {
			$builders = self::get_block_builders();
		}

		// Static builders first (leaf blocks).
		if ( isset( $builders[ $block_name ] ) ) {
			return call_user_func( $builders[ $block_name ], $attrs, $inner_content );
		}

		// Trait renderers (container blocks with proper HTML wrappers).
		if ( null === $trait_renderers ) {
			$trait_renderers = self::get_renderers( new Html_Parser() );
		}
		if ( isset( $trait_renderers[ $block_name ] ) ) {
			return call_user_func( $trait_renderers[ $block_name ], $attrs, $inner_content );
		}

		// Generic fallback.
		$inner_blocks      = self::parse_inner_blocks( $inner_content );
		$inner_content_arr = array();
		foreach ( $inner_blocks as $block ) {
			$inner_content_arr[] = null;
		}

		// Blockstudio blocks expect attrs under blockstudio.attributes.
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

		return array(
			'blockName'    => $block_name,
			'attrs'        => $block_attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => $inner_content_arr,
		);
	}

	/**
	 * Get the string-based block builder registry.
	 *
	 * Each builder takes (array $attrs, string $inner_content) and
	 * returns a WordPress block array.
	 *
	 * @return array<string, callable> Block name => builder callable.
	 */
	private static function get_block_builders(): array {
		$b = array(
			'core/paragraph'    => array( __CLASS__, 'build_paragraph' ),
			'core/heading'      => array( __CLASS__, 'build_heading' ),
			'core/code'         => array( __CLASS__, 'build_code' ),
			'core/preformatted' => array( __CLASS__, 'build_preformatted' ),
			'core/verse'        => array( __CLASS__, 'build_verse' ),
			'core/separator'    => array( __CLASS__, 'build_separator' ),
			'core/spacer'       => array( __CLASS__, 'build_spacer' ),
			'core/image'        => array( __CLASS__, 'build_image' ),
			'core/audio'        => array( __CLASS__, 'build_audio' ),
			'core/video'        => array( __CLASS__, 'build_video' ),
			'core/embed'        => array( __CLASS__, 'build_embed' ),
			'core/button'       => array( __CLASS__, 'build_button' ),
			'core/more'         => array( __CLASS__, 'build_more' ),
			'core/nextpage'     => array( __CLASS__, 'build_nextpage' ),
			'core/table'        => array( __CLASS__, 'build_table' ),
			'core/social-link'  => array( __CLASS__, 'build_social_link' ),
			'core/list'         => array( __CLASS__, 'build_list' ),
			'core/pullquote'    => array( __CLASS__, 'build_pullquote' ),
		);

		return apply_filters( 'blockstudio/block_tags/builders', $b );
	}

	// -------------------------------------------------------------------------
	// Block array builders
	// -------------------------------------------------------------------------

	/**
	 * Build core/paragraph block array.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
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
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_heading( array $attrs, string $inner ): array {
		$level          = isset( $attrs['level'] ) ? max( 1, min( 6, (int) $attrs['level'] ) ) : 2;
		$attrs['level'] = $level;
		$tag            = 'h' . $level;
		$html           = "<{$tag} class=\"wp-block-heading\">{$inner}</{$tag}>";
		return array(
			'blockName'    => 'core/heading',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_code( array $attrs, string $inner ): array {
		$html = '<pre class="wp-block-code"><code>' . $inner . '</code></pre>';
		return array(
			'blockName'    => 'core/code',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_preformatted( array $attrs, string $inner ): array {
		$html = '<pre class="wp-block-preformatted">' . $inner . '</pre>';
		return array(
			'blockName'    => 'core/preformatted',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_verse( array $attrs, string $inner ): array {
		$html = '<pre class="wp-block-verse">' . $inner . '</pre>';
		return array(
			'blockName'    => 'core/verse',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_separator( array $attrs, string $inner ): array {
		$html = '<hr class="wp-block-separator has-alpha-channel-opacity"/>';
		return array(
			'blockName'    => 'core/separator',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_spacer( array $attrs, string $inner ): array {
		$height = $attrs['height'] ?? '100px';
		$html   = '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="wp-block-spacer"></div>';
		return array(
			'blockName'    => 'core/spacer',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_image( array $attrs, string $inner ): array {
		if ( isset( $attrs['src'] ) && ! isset( $attrs['url'] ) ) {
			$attrs['url'] = $attrs['src'];
			unset( $attrs['src'] );
		}
		$url  = $attrs['url'] ?? '';
		$alt  = $attrs['alt'] ?? '';
		$html = '<figure class="wp-block-image"><img src="' . esc_attr( $url ) . '" alt="' . esc_attr( $alt ) . '"/></figure>';
		return array(
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_audio( array $attrs, string $inner ): array {
		$src  = $attrs['src'] ?? '';
		$html = '<figure class="wp-block-audio"><audio controls src="' . esc_attr( $src ) . '"></audio></figure>';
		return array(
			'blockName'    => 'core/audio',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_video( array $attrs, string $inner ): array {
		$src  = $attrs['src'] ?? '';
		$html = '<figure class="wp-block-video"><video controls src="' . esc_attr( $src ) . '"></video></figure>';
		return array(
			'blockName'    => 'core/video',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_embed( array $attrs, string $inner ): array {
		$url      = $attrs['url'] ?? '';
		$provider = $attrs['providerNameSlug'] ?? 'youtube';
		$html     = '<figure class="wp-block-embed is-type-video is-provider-' . esc_attr( $provider ) . ' wp-block-embed-' . esc_attr( $provider ) . '"><div class="wp-block-embed__wrapper">' . esc_html( $url ) . '</div></figure>';
		return array(
			'blockName'    => 'core/embed',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_button( array $attrs, string $inner ): array {
		if ( isset( $attrs['href'] ) && ! isset( $attrs['url'] ) ) {
			$attrs['url'] = $attrs['href'];
			unset( $attrs['href'] );
		}
		$url  = $attrs['url'] ?? '';
		$html = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_attr( $url ) . '">' . $inner . '</a></div>';
		return array(
			'blockName'    => 'core/button',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_more( array $attrs, string $inner ): array {
		$custom = $attrs['customText'] ?? '';
		$html   = '' !== $custom ? '<!--more ' . esc_html( $custom ) . '-->' : '<!--more-->';
		if ( ! empty( $attrs['noTeaser'] ) ) {
			$html .= "\n<!--noteaser-->";
		}
		return array(
			'blockName'    => 'core/more',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_nextpage( array $attrs, string $inner ): array {
		return array(
			'blockName'    => 'core/nextpage',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '<!--nextpage-->',
			'innerContent' => array( '<!--nextpage-->' ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_table( array $attrs, string $inner ): array {
		$html = '<figure class="wp-block-table"><table>' . $inner . '</table></figure>';
		return array(
			'blockName'    => 'core/table',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_social_link( array $attrs, string $inner ): array {
		return array(
			'blockName'    => 'core/social-link',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_list( array $attrs, string $inner ): array {
		$ordered      = ! empty( $attrs['ordered'] );
		$inner_blocks = array();

		if ( preg_match_all( '/<li>(.*?)<\/li>/si', $inner, $matches ) ) {
			foreach ( $matches[1] as $item_content ) {
				$li_html        = '<li>' . $item_content . '</li>';
				$inner_blocks[] = array(
					'blockName'    => 'core/list-item',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => $li_html,
					'innerContent' => array( $li_html ),
				);
			}
		}

		$tag     = $ordered ? 'ol' : 'ul';
		$content = array( "<{$tag} class=\"wp-block-list\">" );
		foreach ( $inner_blocks as $item ) {
			$content[] = null;
		}
		$content[] = "</{$tag}>";

		return array(
			'blockName'    => 'core/list',
			'attrs'        => array( 'ordered' => $ordered ),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => "<{$tag} class=\"wp-block-list\"></{$tag}>",
			'innerContent' => $content,
		);
	}

	/**
	 * Block array builder.
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $inner Inner content.
	 * @return array Block array.
	 */
	private static function build_pullquote( array $attrs, string $inner ): array {
		$citation = $attrs['citation'] ?? '';
		$content  = $inner;

		if ( preg_match( '/<cite>(.*?)<\/cite>/si', $inner, $match ) ) {
			$citation = $match[1];
			$content  = str_replace( $match[0], '', $inner );
		}

		if ( ! empty( $citation ) ) {
			$attrs['citation'] = $citation;
		}

		$html = '<figure class="wp-block-pullquote"><blockquote>' . trim( $content );
		if ( '' !== $citation ) {
			$html .= '<cite>' . esc_html( $citation ) . '</cite>';
		}
		$html .= '</blockquote></figure>';

		return array(
			'blockName'    => 'core/pullquote',
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
				$has_nested = false !== strpos( $inner_content, '<bs:' )
					|| false !== strpos( $inner_content, '<block ' );
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
	 * @param string $inner_content Inner content.
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

		return is_string( $result ) ? $result : '';
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
