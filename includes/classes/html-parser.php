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

	use Paragraph_Renderer;
	use Heading_Renderer;
	use List_Renderer;
	use Quote_Renderer;
	use Pullquote_Renderer;
	use Code_Renderer;
	use Preformatted_Renderer;
	use Verse_Renderer;
	use Image_Renderer;
	use Gallery_Renderer;
	use Audio_Renderer;
	use Video_Renderer;
	use Cover_Renderer;
	use Embed_Renderer;
	use Group_Renderer;
	use Columns_Renderer;
	use Separator_Renderer;
	use Spacer_Renderer;
	use Buttons_Renderer;
	use Details_Renderer;
	use Table_Renderer;
	use Social_Links_Renderer;
	use Media_Text_Renderer;
	use More_Renderer;
	use Accordion_Renderer;
	use Query_Renderer;

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

		// Social blocks.
		$this->block_renderers['core/social-links'] = array( $this, 'render_social_links' );
		$this->block_renderers['core/social-link']  = array( $this, 'render_social_link' );

		// Media & text.
		$this->block_renderers['core/media-text'] = array( $this, 'render_media_text' );

		// Pagination / more blocks.
		$this->block_renderers['core/more']     = array( $this, 'render_more' );
		$this->block_renderers['core/nextpage'] = array( $this, 'render_nextpage' );

		// Accordion blocks.
		$this->block_renderers['core/accordion']         = array( $this, 'render_accordion' );
		$this->block_renderers['core/accordion-item']    = array( $this, 'render_accordion_item' );
		$this->block_renderers['core/accordion-heading'] = array( $this, 'render_accordion_heading' );
		$this->block_renderers['core/accordion-panel']   = array( $this, 'render_accordion_panel' );

		// Query / comments containers.
		$this->block_renderers['core/query']    = array( $this, 'render_query' );
		$this->block_renderers['core/comments'] = array( $this, 'render_comments' );
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

		if ( isset( $attrs['key'] ) ) {
			$attrs['__BLOCKSTUDIO_KEY'] = $attrs['key'];
			unset( $attrs['key'] );
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

		if ( isset( $attrs['key'] ) ) {
			$attrs['__BLOCKSTUDIO_KEY'] = $attrs['key'];
			unset( $attrs['key'] );
		}

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

		if ( $element->hasAttribute( 'key' ) ) {
			$meta['__BLOCKSTUDIO_KEY'] = $element->getAttribute( 'key' );
		}

		return $meta;
	}

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
