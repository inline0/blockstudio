<?php

use Blockstudio\Block_Tags;
use PHPUnit\Framework\TestCase;

/**
 * Guards the block-tag / parser extension points that themes and plugins rely on.
 *
 * A theme registers a block-array builder for an element-mapped custom block
 * (e.g. a `<p>` mapped to `theme/paragraph`) via `blockstudio/block_tags/builders`.
 * That builder has no internal caller, so a "dead code" refactor can silently
 * drop it and empty every paragraph on sync. These tests exercise the real
 * parse path and fail if the dispatch or any block-tag filter is lost.
 */
class BlockTagsExtensionPointsTest extends TestCase {

	private array $added = array();

	protected function tearDown(): void {
		foreach ( $this->added as [$hook, $cb] ) {
			remove_filter( $hook, $cb );
		}
		$this->added = array();
		parent::tearDown();
	}

	private function add( string $hook, callable $cb ): void {
		add_filter( $hook, $cb );
		$this->added[] = array( $hook, $cb );
	}

	private function register_paragraph_builder(): void {
		$this->add(
			'blockstudio/parser/element_mapping',
			static function ( array $mapping ): array {
				$mapping['p'] = 'custom/paragraph';
				return $mapping;
			}
		);
		$this->add(
			'blockstudio/block_tags/builders',
			static function ( array $builders ): array {
				$builders['custom/paragraph'] = static function ( array $attributes, string $inner ): array {
					if ( ! isset( $attributes['content'] ) ) {
						$attributes['content'] = trim( $inner );
					}
					return array(
						'blockName'    => 'custom/paragraph',
						'attrs'        => array( 'blockstudio' => array( 'attributes' => $attributes ) ),
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					);
				};
				return $builders;
			}
		);
	}

	private static function content_of( array $block ): mixed {
		return $block['attrs']['blockstudio']['attributes']['content'] ?? null;
	}

	public function test_custom_builder_captures_inner_text_nested_in_element(): void {
		$this->register_paragraph_builder();

		$blocks = Block_Tags::parse_all_elements( '<section><p>Hello world</p></section>' );
		$inner  = $blocks[0]['innerBlocks'][0];

		$this->assertSame( 'custom/paragraph', $inner['blockName'] );
		$this->assertSame( 'Hello world', self::content_of( $inner ) );
	}

	public function test_custom_builder_captures_inner_text_nested_in_block_tag(): void {
		$this->register_paragraph_builder();

		$blocks = Block_Tags::parse_all_elements( '<bs:core-group><p>Nested in tag</p></bs:core-group>' );
		$inner  = $blocks[0]['innerBlocks'][0];

		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertSame( 'custom/paragraph', $inner['blockName'] );
		$this->assertSame( 'Nested in tag', self::content_of( $inner ) );
	}

	public function test_mapped_paragraph_and_heading_both_preserve_inner_text(): void {
		$this->register_paragraph_builder();
		$this->add(
			'blockstudio/parser/element_mapping',
			static function ( array $mapping ): array {
				$mapping['h2'] = 'custom/heading';
				return $mapping;
			}
		);

		$blocks   = Block_Tags::parse_all_elements( '<section><h2>Title</h2><p>Body</p></section>' );
		$children = $blocks[0]['innerBlocks'];

		$this->assertSame( 'custom/heading', $children[0]['blockName'] );
		$this->assertSame( 'Title', $children[0]['attrs']['content'] ?? null );

		$this->assertSame( 'custom/paragraph', $children[1]['blockName'] );
		$this->assertSame( 'Body', self::content_of( $children[1] ) );
	}

	public function test_all_block_tag_extension_point_filters_fire(): void {
		$hooks = array(
			'blockstudio/parser/element_mapping',
			'blockstudio/parser/renderers',
			'blockstudio/block_tags/builders',
			'blockstudio/block_tags/renderers',
			'blockstudio/block_tags/tag_aliases',
			'blockstudio/block_tags/prefixes',
			'blockstudio/block_tags/allow',
			'blockstudio/block_tags/deny',
		);

		$fired = array();
		foreach ( $hooks as $hook ) {
			$this->add(
				$hook,
				static function ( $value ) use ( $hook, &$fired ) {
					$fired[ $hook ] = true;
					return $value;
				}
			);
		}

		// render() exercises allow/deny/prefixes/tag_aliases plus the build path.
		Block_Tags::render( '<bs:core-paragraph>Hi</bs:core-paragraph>' );
		// parse_all_elements() exercises the raw-element mapping path.
		Block_Tags::parse_all_elements( '<p>Hi</p>' );

		foreach ( $hooks as $hook ) {
			$this->assertArrayHasKey( $hook, $fired, "Filter never applied: {$hook}" );
		}
	}
}
