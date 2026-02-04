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
 * block format. It supports standard HTML elements and custom
 * blockstudio_* elements for Blockstudio blocks.
 *
 * @since 7.0.0
 */
class Html_Parser {

	/**
	 * Default element to block mappings.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_MAPPINGS = array(
		'p'          => 'core/paragraph',
		'h1'         => 'core/heading',
		'h2'         => 'core/heading',
		'h3'         => 'core/heading',
		'h4'         => 'core/heading',
		'h5'         => 'core/heading',
		'h6'         => 'core/heading',
		'ul'         => 'core/list',
		'ol'         => 'core/list',
		'img'        => 'core/image',
		'div'        => 'core/group',
		'blockquote' => 'core/quote',
		'hr'         => 'core/separator',
		'figure'     => 'core/image',
	);

	/**
	 * Element to block mappings.
	 *
	 * @var array<string, string>
	 */
	private array $mappings;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $custom_mappings Optional custom element mappings.
	 */
	public function __construct( array $custom_mappings = array() ) {
		$this->mappings = array_merge( self::DEFAULT_MAPPINGS, $custom_mappings );
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
	 * Converts blockstudio_* elements to a format that DOMDocument can parse.
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
	private function parse_children( DOMNode $parent_node ): array {
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

		if ( 'blockstudio' === $tag ) {
			return $this->parse_generic_blockstudio( $element );
		}

		if ( str_starts_with( $tag, 'blockstudio_' ) ) {
			return $this->parse_blockstudio_block( $element );
		}

		return match ( $tag ) {
			'p' => $this->create_paragraph( $element ),
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->create_heading( $element ),
			'ul' => $this->create_list( $element, false ),
			'ol' => $this->create_list( $element, true ),
			'img' => $this->create_image( $element ),
			'div' => $this->create_group( $element ),
			'blockquote' => $this->create_quote( $element ),
			'hr' => $this->create_separator(),
			'figure' => $this->create_figure( $element ),
			default => $this->create_html_block( $element ),
		};
	}

	/**
	 * Parse a blockstudio_* custom element.
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

	/**
	 * Parse a generic <blockstudio> element with name attribute.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array|null The block array or null.
	 */
	private function parse_generic_blockstudio( DOMElement $element ): ?array {
		$block_name = $element->getAttribute( 'name' );

		if ( empty( $block_name ) ) {
			return null;
		}

		$attrs = $this->get_element_attributes( $element );
		unset( $attrs['name'] );

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
	 * Get attributes from an element.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return array The attributes.
	 */
	private function get_element_attributes( DOMElement $element ): array {
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
			'attrs'        => array(),
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
		$html    = "<{$tag}>{$content}</{$tag}>";

		return array(
			'blockName'    => 'core/heading',
			'attrs'        => array( 'level' => $level ),
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
		$content = array( "<{$tag}>" );

		foreach ( $inner_blocks as $item ) {
			$content[] = null;
		}

		$content[] = "</{$tag}>";

		return array(
			'blockName'    => 'core/list',
			'attrs'        => array( 'ordered' => $ordered ),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => "<{$tag}></{$tag}>",
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

		$attrs = array();

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
			'attrs'        => array(),
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
			'attrs'        => array(),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<blockquote class="wp-block-quote"></blockquote>',
			'innerContent' => $content,
		);
	}

	/**
	 * Create a separator block.
	 *
	 * @return array The block array.
	 */
	private function create_separator(): array {
		$html = '<hr class="wp-block-separator has-alpha-channel-opacity"/>';

		return array(
			'blockName'    => 'core/separator',
			'attrs'        => array(),
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
			return $this->create_image( $img );
		}

		return $this->create_html_block( $element );
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
	 * Get inner HTML of an element.
	 *
	 * @param DOMElement $element The element.
	 *
	 * @return string The inner HTML.
	 */
	private function get_inner_html( DOMElement $element ): string {
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
	private function get_outer_html( DOMElement $element ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOM property.
		return trim( $element->ownerDocument->saveHTML( $element ) );
	}

	/**
	 * Get the configured mappings.
	 *
	 * @return array<string, string> The mappings.
	 */
	public function get_mappings(): array {
		return $this->mappings;
	}

	/**
	 * Create parser with settings from blockstudio.json.
	 *
	 * @return Html_Parser The parser instance.
	 */
	public static function from_settings(): Html_Parser {
		$custom_mappings = Settings::get( 'parser/mappings', array() );

		return new self( is_array( $custom_mappings ) ? $custom_mappings : array() );
	}
}
