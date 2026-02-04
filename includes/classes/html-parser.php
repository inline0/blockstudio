<?php
/**
 * HTML Parser class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Standalone HTML to WordPress blocks converter.
 *
 * This class parses HTML-like syntax and converts it to WordPress
 * block format. It supports standard HTML elements and the generic
 * <block name=""> syntax for any WordPress block.
 *
 * Developers can register custom block renderers via the
 * `blockstudio/parser/renderers` filter.
 *
 * @since 7.0.0
 */
class Html_Parser {

	/**
	 * Block renderers registry.
	 *
	 * Maps block names to callable renderers that generate the block array
	 * with proper HTML markup.
	 *
	 * @var array<string, callable>
	 */
	private array $block_renderers = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_core_renderers();

		/**
		 * Filter to register custom block renderers.
		 *
		 * Renderers are callables that receive:
		 * - DOMElement $element - The <block> element
		 * - array $attrs - Parsed attributes (name already removed)
		 * - array $inner_blocks - Parsed child blocks
		 * - Html_Parser $parser - The parser instance (for helper methods)
		 *
		 * And return a block array with blockName, attrs, innerBlocks, innerHTML, innerContent.
		 *
		 * @since 7.0.0
		 *
		 * @param array<string, callable> $renderers Block name => renderer callable.
		 * @param Html_Parser             $parser    The parser instance.
		 */
		$this->block_renderers = apply_filters( 'blockstudio/parser/renderers', $this->block_renderers, $this );
	}

	/**
	 * Register all core WordPress block renderers.
	 *
	 * @return void
	 */
	private function register_core_renderers(): void {
		// Text blocks.
		$this->block_renderers['core/paragraph']    = array( $this, 'render_paragraph' );
		$this->block_renderers['core/heading']      = array( $this, 'render_heading' );
		$this->block_renderers['core/list']         = array( $this, 'render_list' );
		$this->block_renderers['core/quote']        = array( $this, 'render_quote' );
		$this->block_renderers['core/pullquote']    = array( $this, 'render_pullquote' );
		$this->block_renderers['core/code']         = array( $this, 'render_code' );
		$this->block_renderers['core/preformatted'] = array( $this, 'render_preformatted' );
		$this->block_renderers['core/verse']        = array( $this, 'render_verse' );

		// Media blocks.
		$this->block_renderers['core/image']   = array( $this, 'render_image' );
		$this->block_renderers['core/gallery'] = array( $this, 'render_gallery' );
		$this->block_renderers['core/audio']   = array( $this, 'render_audio' );
		$this->block_renderers['core/video']   = array( $this, 'render_video' );
		$this->block_renderers['core/cover']   = array( $this, 'render_cover' );
		$this->block_renderers['core/embed']   = array( $this, 'render_embed' );

		// Design blocks.
		$this->block_renderers['core/group']     = array( $this, 'render_group' );
		$this->block_renderers['core/columns']   = array( $this, 'render_columns' );
		$this->block_renderers['core/column']    = array( $this, 'render_column' );
		$this->block_renderers['core/separator'] = array( $this, 'render_separator' );
		$this->block_renderers['core/spacer']    = array( $this, 'render_spacer' );
		$this->block_renderers['core/buttons']   = array( $this, 'render_buttons' );
		$this->block_renderers['core/button']    = array( $this, 'render_button' );

		// Interactive blocks.
		$this->block_renderers['core/details'] = array( $this, 'render_details' );
		$this->block_renderers['core/table']   = array( $this, 'render_table' );
	}

	/**
	 * Parse HTML content to WordPress block format.
	 *
	 * @param string $html The HTML content to parse.
	 *
	 * @return string Serialized WordPress blocks.
	 */
	public function parse( string $html ): string {
		$html = trim( $html );

		if ( empty( $html ) ) {
			return '';
		}

		$html = $this->preprocess_html( $html );

		$doc = new DOMDocument();

		libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<?xml encoding="utf-8"?><div id="bs-root">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$root = $doc->getElementById( 'bs-root' );

		if ( ! $root ) {
			return '';
		}

		$blocks = $this->parse_children( $root );

		return serialize_blocks( $blocks );
	}

	/**
	 * Parse an array of blocks from the parsed content.
	 *
	 * @param string $html The HTML content to parse.
	 *
	 * @return array Array of block arrays.
	 */
	public function parse_to_array( string $html ): array {
		$html = trim( $html );

		if ( empty( $html ) ) {
			return array();
		}

		$html = $this->preprocess_html( $html );

		$doc = new DOMDocument();

		libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<?xml encoding="utf-8"?><div id="bs-root">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$root = $doc->getElementById( 'bs-root' );

		if ( ! $root ) {
			return array();
		}

		return $this->parse_children( $root );
	}

	/**
	 * Preprocess HTML to handle custom elements.
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string The preprocessed HTML.
	 */
	private function preprocess_html( string $html ): string {
		$html = preg_replace( '/<\?php[\s\S]*?\?>/i', '', $html );

		return $html;
	}

	/**
	 * Parse child nodes of an element.
	 *
	 * @param DOMNode $parent_node The parent node.
	 *
	 * @return array Array of parsed blocks.
	 */
	public function parse_children( DOMNode $parent_node ): array {
		$blocks = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $parent_node->childNodes as $node ) {
			if ( $node instanceof DOMText ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				$text = trim( $node->textContent );
				if ( ! empty( $text ) ) {
					$blocks[] = $this->create_paragraph_from_text( $text );
				}
				continue;
			}

			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			$block = $this->parse_element( $node );

			if ( $block ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Parse a single element to a block.
	 *
	 * @param DOMElement $element The element to parse.
	 *
	 * @return array|null The block array or null.
	 */
	private function parse_element( DOMElement $element ): ?array {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		$tag = strtolower( $element->tagName );

		// Generic <block name=""> syntax - the primary way to create blocks.
		if ( 'block' === $tag ) {
			return $this->parse_block_element( $element );
		}

		// Legacy blockstudio_* shorthand.
		if ( str_starts_with( $tag, 'blockstudio_' ) ) {
			return $this->parse_blockstudio_block( $element );
		}

		// Standard HTML elements map to core blocks.
		return match ( $tag ) {
			'p' => $this->create_paragraph( $element ),
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->create_heading( $element ),
			'ul' => $this->create_list( $element, false ),
			'ol' => $this->create_list( $element, true ),
			'img' => $this->create_image( $element ),
			'div' => $this->create_group( $element ),
			'section' => $this->create_group( $element ),
			'blockquote' => $this->create_quote( $element ),
			'hr' => $this->create_separator( $element ),
			'figure' => $this->create_figure( $element ),
			'pre' => $this->create_preformatted( $element ),
			'code' => $this->create_code( $element ),
			'table' => $this->create_table( $element ),
			'audio' => $this->create_audio( $element ),
			'video' => $this->create_video( $element ),
			'details' => $this->create_details( $element ),
			default => $this->create_html_block( $element ),
		};
	}

	/**
	 * Parse a <block name=""> element.
	 *
	 * This is the primary syntax for creating any WordPress block.
	 * Looks up a registered renderer for the block name, or falls back
	 * to a generic block structure.
	 *
	 * @param DOMElement $element The <block> element.
	 *
	 * @return array|null The block array or null.
	 */
	private function parse_block_element( DOMElement $element ): ?array {
		$block_name = $element->getAttribute( 'name' );

		if ( empty( $block_name ) ) {
			return null;
		}

		$attrs = $this->get_element_attributes( $element );
		unset( $attrs['name'] );

		// DOMDocument lowercases attribute names; remap camelCase attributes.
		if ( isset( $attrs['blockeditingmode'] ) ) {
			$attrs['blockEditingMode'] = $attrs['blockeditingmode'];
			unset( $attrs['blockeditingmode'] );
		}

		// Check for registered renderer.
		if ( isset( $this->block_renderers[ $block_name ] ) ) {
			return call_user_func(
				$this->block_renderers[ $block_name ],
				$element,
				$attrs,
				$this
			);
		}

		// Fallback: generic block structure (works for dynamic blocks).
		$inner_blocks = $this->parse_children( $element );

		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Parse a blockstudio_* custom element (legacy shorthand).
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function parse_blockstudio_block( DOMElement $element ): array {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		$tag        = strtolower( $element->tagName );
		$block_name = str_replace( 'blockstudio_', '', $tag );
		$block_name = 'blockstudio/' . str_replace( '_', '-', $block_name );

		$attrs = $this->get_element_attributes( $element );

		$inner_blocks = $this->parse_children( $element );

		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	// =========================================================================
	// Block Renderers - Core Text Blocks
	// =========================================================================

	/**
	 * Render core/paragraph block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_paragraph( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$content = $parser->get_inner_html( $element );
		$html    = '<p>' . $content . '</p>';

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Render core/heading block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_heading( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
		if ( $level < 1 || $level > 6 ) {
			$level = 2;
		}

		$content = $parser->get_inner_html( $element );
		$tag     = 'h' . $level;
		$html    = "<{$tag} class=\"wp-block-heading\">{$content}</{$tag}>";

		$attrs['level'] = $level;

		return array(
			'blockName'    => 'core/heading',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Render core/list block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_list( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$ordered      = ! empty( $attrs['ordered'] );
		$inner_blocks = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				$content        = $parser->get_inner_html( $child );
				$inner_blocks[] = array(
					'blockName'    => 'core/list-item',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '<li>' . $content . '</li>',
					'innerContent' => array( '<li>' . $content . '</li>' ),
				);
			} elseif ( $child instanceof DOMText ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				$text = trim( $child->textContent );
				if ( ! empty( $text ) ) {
					$inner_blocks[] = array(
						'blockName'    => 'core/list-item',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<li>' . esc_html( $text ) . '</li>',
						'innerContent' => array( '<li>' . esc_html( $text ) . '</li>' ),
					);
				}
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
	 * Render core/quote block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_quote( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<blockquote class="wp-block-quote">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</blockquote>';

		return array(
			'blockName'    => 'core/quote',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<blockquote class="wp-block-quote"></blockquote>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/pullquote block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_pullquote( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$content  = '';
		$citation = isset( $attrs['citation'] ) ? $attrs['citation'] : '';

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				$tag = strtolower( $child->tagName );
				if ( 'cite' === $tag ) {
					$citation = $parser->get_inner_html( $child );
				} else {
					$content .= $parser->get_outer_html( $child );
				}
			} elseif ( $child instanceof DOMText ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				$text = trim( $child->textContent );
				if ( ! empty( $text ) ) {
					$content .= '<p>' . esc_html( $text ) . '</p>';
				}
			}
		}

		$html = '<figure class="wp-block-pullquote"><blockquote>' . $content;
		if ( ! empty( $citation ) ) {
			$html             .= '<cite>' . esc_html( $citation ) . '</cite>';
			$attrs['citation'] = $citation;
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
	 * Render core/code block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_code( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$content = $parser->get_inner_html( $element );
		$html    = '<pre class="wp-block-code"><code>' . $content . '</code></pre>';

		return array(
			'blockName'    => 'core/code',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Render core/preformatted block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_preformatted( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$content = $parser->get_inner_html( $element );
		$html    = '<pre class="wp-block-preformatted">' . $content . '</pre>';

		return array(
			'blockName'    => 'core/preformatted',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Render core/verse block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_verse( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$content = $parser->get_inner_html( $element );
		$html    = '<pre class="wp-block-verse">' . $content . '</pre>';

		return array(
			'blockName'    => 'core/verse',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	// =========================================================================
	// Block Renderers - Core Media Blocks
	// =========================================================================

	/**
	 * Render core/image block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_image( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$src = isset( $attrs['src'] ) ? $attrs['src'] : ( isset( $attrs['url'] ) ? $attrs['url'] : '' );
		$alt = isset( $attrs['alt'] ) ? $attrs['alt'] : '';

		if ( ! empty( $src ) ) {
			$attrs['url'] = $src;
		}
		unset( $attrs['src'] );

		$html = '<figure class="wp-block-image"><img src="' . esc_attr( $src ) . '" alt="' . esc_attr( $alt ) . '"/></figure>';

		return array(
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Render core/gallery block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_gallery( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				$tag = strtolower( $child->tagName );
				if ( 'img' === $tag || 'block' === $tag ) {
					$block = $parser->parse_element( $child );
					if ( $block ) {
						$inner_blocks[] = $block;
					}
				}
			}
		}

		$content = array( '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</figure>';

		return array(
			'blockName'    => 'core/gallery',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<figure class="wp-block-gallery has-nested-images columns-default is-cropped"></figure>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/audio block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_audio( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$src = isset( $attrs['src'] ) ? $attrs['src'] : '';

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
	 * Render core/video block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_video( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$src = isset( $attrs['src'] ) ? $attrs['src'] : '';

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
	 * Render core/cover block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_cover( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$url = isset( $attrs['url'] ) ? $attrs['url'] : ( isset( $attrs['src'] ) ? $attrs['src'] : '' );
		unset( $attrs['src'] );

		if ( ! empty( $url ) ) {
			$attrs['url'] = $url;
		}

		if ( ! isset( $attrs['dimRatio'] ) ) {
			$attrs['dimRatio'] = 50;
		}

		$style = '';
		if ( ! empty( $url ) ) {
			$style = ' style="background-image:url(' . esc_attr( $url ) . ')"';
		}

		$content = array( '<div class="wp-block-cover"' . $style . '><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><div class="wp-block-cover__inner-container">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div></div>';

		return array(
			'blockName'    => 'core/cover',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-cover"' . $style . '><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><div class="wp-block-cover__inner-container"></div></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/embed block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_embed( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$url      = isset( $attrs['url'] ) ? $attrs['url'] : '';
		$provider = isset( $attrs['providerNameSlug'] ) ? $attrs['providerNameSlug'] : 'youtube';

		$html = '<figure class="wp-block-embed is-type-video is-provider-' . esc_attr( $provider ) . ' wp-block-embed-' . esc_attr( $provider ) . '"><div class="wp-block-embed__wrapper">' . esc_url( $url ) . '</div></figure>';

		return array(
			'blockName'    => 'core/embed',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	// =========================================================================
	// Block Renderers - Core Design Blocks
	// =========================================================================

	/**
	 * Render core/group block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_group( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		// Handle layout attribute (for row/stack variants).
		$class = 'wp-block-group';
		if ( isset( $attrs['layout'] ) ) {
			if ( is_string( $attrs['layout'] ) ) {
				$attrs['layout'] = json_decode( $attrs['layout'], true );
			}
			if ( isset( $attrs['layout']['type'] ) && 'flex' === $attrs['layout']['type'] ) {
				$class .= ' is-layout-flex';
				if ( isset( $attrs['layout']['orientation'] ) && 'vertical' === $attrs['layout']['orientation'] ) {
					$class .= ' is-vertical';
				}
			}
		}

		$content = array( '<div class="' . $class . '">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/group',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="' . $class . '"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/columns block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_columns( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<div class="wp-block-columns">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/columns',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-columns"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/column block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_column( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<div class="wp-block-column">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/column',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-column"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/separator block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_separator( DOMElement $element, array $attrs, Html_Parser $parser ): array {
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
	 * Render core/spacer block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_spacer( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$height = isset( $attrs['height'] ) ? $attrs['height'] : '100px';

		$html = '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="wp-block-spacer"></div>';

		return array(
			'blockName'    => 'core/spacer',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Render core/buttons block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_buttons( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		$content = array( '<div class="wp-block-buttons">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/buttons',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-buttons"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/button block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_button( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$text = $parser->get_inner_html( $element );
		$url  = isset( $attrs['url'] ) ? $attrs['url'] : ( isset( $attrs['href'] ) ? $attrs['href'] : '#' );
		unset( $attrs['href'] );
		$attrs['url'] = $url;

		$html = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_attr( $url ) . '">' . $text . '</a></div>';

		return array(
			'blockName'    => 'core/button',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	// =========================================================================
	// Block Renderers - Core Interactive Blocks
	// =========================================================================

	/**
	 * Render core/details block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_details( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$summary      = isset( $attrs['summary'] ) ? $attrs['summary'] : '';
		$inner_blocks = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				if ( 'summary' === strtolower( $child->tagName ) ) {
					$summary = $parser->get_inner_html( $child );
				} else {
					$block = $parser->parse_element( $child );
					if ( $block ) {
						$inner_blocks[] = $block;
					}
				}
			}
		}

		if ( ! empty( $summary ) ) {
			$attrs['summary'] = $summary;
		}

		$content = array( '<details class="wp-block-details"><summary>' . esc_html( $summary ) . '</summary>' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</details>';

		return array(
			'blockName'    => 'core/details',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<details class="wp-block-details"><summary>' . esc_html( $summary ) . '</summary></details>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/table block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_table( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$table_html = $parser->get_outer_html( $element );
		$html       = '<figure class="wp-block-table">' . $table_html . '</figure>';

		return array(
			'blockName'    => 'core/table',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	// =========================================================================
	// HTML Element Handlers (for direct HTML like <p>, <h1>, etc.)
	// =========================================================================

	/**
	 * Create a paragraph block from text.
	 *
	 * @param string $text The text content.
	 *
	 * @return array The block array.
	 */
	private function create_paragraph_from_text( string $text ): array {
		$content = '<p>' . esc_html( $text ) . '</p>';

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $content,
			'innerContent' => array( $content ),
		);
	}

	/**
	 * Create a paragraph block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_paragraph( DOMElement $element ): array {
		$content = $this->get_inner_html( $element );
		$html    = '<p>' . $content . '</p>';

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => $this->extract_meta_attributes( $element ),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a heading block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_heading( DOMElement $element ): array {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		$tag     = strtolower( $element->tagName );
		$level   = (int) substr( $tag, 1 );
		$content = $this->get_inner_html( $element );
		$html    = "<{$tag} class=\"wp-block-heading\">{$content}</{$tag}>";

		return array(
			'blockName'    => 'core/heading',
			'attrs'        => array_merge( array( 'level' => $level ), $this->extract_meta_attributes( $element ) ),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a list block.
	 *
	 * @param DOMElement $element  The element.
	 * @param bool       $ordered  Whether the list is ordered.
	 *
	 * @return array The block array.
	 */
	private function create_list( DOMElement $element, bool $ordered ): array {
		$inner_blocks = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
			if ( $child instanceof DOMElement && 'li' === strtolower( $child->tagName ) ) {
				$content        = $this->get_inner_html( $child );
				$inner_blocks[] = array(
					'blockName'    => 'core/list-item',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '<li>' . $content . '</li>',
					'innerContent' => array( '<li>' . $content . '</li>' ),
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
			'attrs'        => array_merge( array( 'ordered' => $ordered ), $this->extract_meta_attributes( $element ) ),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => "<{$tag} class=\"wp-block-list\"></{$tag}>",
			'innerContent' => $content,
		);
	}

	/**
	 * Create an image block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_image( DOMElement $element ): array {
		$src = $element->getAttribute( 'src' );
		$alt = $element->getAttribute( 'alt' );

		$attrs = $this->extract_meta_attributes( $element );

		if ( ! empty( $src ) ) {
			$attrs['url'] = $src;
		}

		if ( ! empty( $alt ) ) {
			$attrs['alt'] = $alt;
		}

		$html = '<figure class="wp-block-image"><img src="' . esc_attr( $src ) . '" alt="' . esc_attr( $alt ) . '"/></figure>';

		return array(
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a group block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_group( DOMElement $element ): array {
		$inner_blocks = $this->parse_children( $element );

		$content = array( '<div class="wp-block-group">' );

		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}

		$content[] = '</div>';

		return array(
			'blockName'    => 'core/group',
			'attrs'        => $this->extract_meta_attributes( $element ),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-group"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Create a quote block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_quote( DOMElement $element ): array {
		$inner_blocks = $this->parse_children( $element );

		$content = array( '<blockquote class="wp-block-quote">' );

		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}

		$content[] = '</blockquote>';

		return array(
			'blockName'    => 'core/quote',
			'attrs'        => $this->extract_meta_attributes( $element ),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<blockquote class="wp-block-quote"></blockquote>',
			'innerContent' => $content,
		);
	}

	/**
	 * Create a separator block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_separator( DOMElement $element ): array {
		$html = '<hr class="wp-block-separator has-alpha-channel-opacity"/>';

		return array(
			'blockName'    => 'core/separator',
			'attrs'        => $this->extract_meta_attributes( $element ),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a figure/image block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_figure( DOMElement $element ): array {
		$img = $element->getElementsByTagName( 'img' )->item( 0 );

		if ( $img instanceof DOMElement ) {
			$block = $this->create_image( $img );

			$meta = $this->extract_meta_attributes( $element );
			if ( ! empty( $meta ) ) {
				$block['attrs'] = array_merge( $block['attrs'], $meta );
			}

			return $block;
		}

		return $this->create_html_block( $element );
	}

	/**
	 * Create a preformatted block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_preformatted( DOMElement $element ): array {
		$content = $this->get_inner_html( $element );
		$html    = '<pre class="wp-block-preformatted">' . $content . '</pre>';

		return array(
			'blockName'    => 'core/preformatted',
			'attrs'        => $this->extract_meta_attributes( $element ),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a code block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_code( DOMElement $element ): array {
		$content = $this->get_inner_html( $element );
		$html    = '<pre class="wp-block-code"><code>' . $content . '</code></pre>';

		return array(
			'blockName'    => 'core/code',
			'attrs'        => $this->extract_meta_attributes( $element ),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a table block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_table( DOMElement $element ): array {
		$html = '<figure class="wp-block-table">' . $this->get_outer_html( $element ) . '</figure>';

		return array(
			'blockName'    => 'core/table',
			'attrs'        => $this->extract_meta_attributes( $element ),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create an audio block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_audio( DOMElement $element ): array {
		$src = $element->getAttribute( 'src' );

		$attrs = $this->extract_meta_attributes( $element );
		if ( ! empty( $src ) ) {
			$attrs['src'] = $src;
		}

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
	 * Create a video block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_video( DOMElement $element ): array {
		$src = $element->getAttribute( 'src' );

		$attrs = $this->extract_meta_attributes( $element );
		if ( ! empty( $src ) ) {
			$attrs['src'] = $src;
		}

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
	 * Create a details block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_details( DOMElement $element ): array {
		$summary      = '';
		$inner_blocks = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
				if ( 'summary' === strtolower( $child->tagName ) ) {
					$summary = $this->get_inner_html( $child );
				} else {
					$block = $this->parse_element( $child );
					if ( $block ) {
						$inner_blocks[] = $block;
					}
				}
			}
		}

		$attrs = $this->extract_meta_attributes( $element );
		if ( ! empty( $summary ) ) {
			$attrs['summary'] = $summary;
		}

		$content = array( '<details class="wp-block-details"><summary>' . esc_html( $summary ) . '</summary>' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</details>';

		return array(
			'blockName'    => 'core/details',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<details class="wp-block-details"><summary>' . esc_html( $summary ) . '</summary></details>',
			'innerContent' => $content,
		);
	}

	/**
	 * Create a raw HTML block.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The block array.
	 */
	private function create_html_block( DOMElement $element ): array {
		$html = $this->get_outer_html( $element );

		return array(
			'blockName'    => 'core/html',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	// =========================================================================
	// Meta Attribute Extraction
	// =========================================================================

	/**
	 * Extract meta attributes (like blockEditingMode) from an HTML element.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The meta attributes found.
	 */
	private function extract_meta_attributes( DOMElement $element ): array {
		$meta = array();

		// DOMDocument lowercases all attribute names.
		if ( $element->hasAttribute( 'blockeditingmode' ) ) {
			$meta['blockEditingMode'] = $element->getAttribute( 'blockeditingmode' );
		}

		return $meta;
	}

	// =========================================================================
	// Helper Methods (Public for use by renderers)
	// =========================================================================

	/**
	 * Get element attributes as array.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The attributes.
	 */
	public function get_element_attributes( DOMElement $element ): array {
		$attrs = array();

		foreach ( $element->attributes as $attr ) {
			$value = $attr->value;

			if ( 'true' === $value ) {
				$value = true;
			} elseif ( 'false' === $value ) {
				$value = false;
			} elseif ( is_numeric( $value ) ) {
				$value = str_contains( $value, '.' ) ? (float) $value : (int) $value;
			}

			$attrs[ $attr->name ] = $value;
		}

		return $attrs;
	}

	/**
	 * Get inner HTML of an element.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return string The inner HTML.
	 */
	public function get_inner_html( DOMElement $element ): string {
		$html = '';

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		foreach ( $element->childNodes as $child ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
			$html .= $element->ownerDocument->saveHTML( $child );
		}

		return trim( $html );
	}

	/**
	 * Get outer HTML of an element.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return string The outer HTML.
	 */
	public function get_outer_html( DOMElement $element ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		return trim( $element->ownerDocument->saveHTML( $element ) );
	}

	/**
	 * Parse a single element (public for renderer access).
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array|null The block array.
	 */
	public function parse_element_public( DOMElement $element ): ?array {
		return $this->parse_element( $element );
	}

	/**
	 * Create parser with settings.
	 *
	 * @return Html_Parser The parser instance.
	 */
	public static function from_settings(): Html_Parser {
		return new self();
	}
}
