<?php

use Blockstudio\Block_Tags;
use PHPUnit\Framework\TestCase;

/**
 * Tests for allow/deny list filtering in Block_Tags::render().
 *
 * Allow/deny is enforced during render() (tag replacement), not during
 * parse_inner_blocks() (block array building). Denied or non-allowed
 * tags are left untouched in the output string.
 */
class BlockTagsAllowDenyTest extends TestCase {

	private array $filter_callbacks = array();

	protected function tearDown(): void {
		foreach ( $this->filter_callbacks as $cb ) {
			remove_filter( $cb[0], $cb[1] );
		}
		$this->filter_callbacks = array();
	}

	private function set_allow( array $patterns ): void {
		$cb = function () use ( $patterns ) {
			return $patterns;
		};
		add_filter( 'blockstudio/block_tags/allow', $cb );
		$this->filter_callbacks[] = array( 'blockstudio/block_tags/allow', $cb );
	}

	private function set_deny( array $patterns ): void {
		$cb = function () use ( $patterns ) {
			return $patterns;
		};
		add_filter( 'blockstudio/block_tags/deny', $cb );
		$this->filter_callbacks[] = array( 'blockstudio/block_tags/deny', $cb );
	}

	// Deny list

	public function test_deny_leaves_tag_untouched(): void {
		$this->set_deny( array( 'core/separator' ) );
		$input  = '<bs:core-separator />';
		$result = Block_Tags::render( $input );
		$this->assertSame( $input, $result );
	}

	public function test_deny_wildcard_leaves_namespace_untouched(): void {
		$this->set_deny( array( 'blockstudio/*' ) );
		$input  = '<bs:blockstudio-type-block-tags-default title="Hi" />';
		$result = Block_Tags::render( $input );
		$this->assertSame( $input, $result );
	}

	public function test_deny_does_not_affect_other_blocks(): void {
		$this->set_deny( array( 'blockstudio/*' ) );
		$result = Block_Tags::render( '<bs:core-separator />' );
		$this->assertNotSame( '<bs:core-separator />', $result );
		$this->assertStringContainsString( '<hr', $result );
	}

	public function test_deny_specific_pattern(): void {
		$this->set_deny( array( 'blockstudio/type-block-tags-*' ) );

		$unit = Block_Tags::render( '<bs:blockstudio-type-unit />' );
		$this->assertNotSame( '<bs:blockstudio-type-unit />', $unit );

		$tags_input = '<bs:blockstudio-type-block-tags-default title="Hi" />';
		$tags       = Block_Tags::render( $tags_input );
		$this->assertSame( $tags_input, $tags );
	}

	// Allow list

	public function test_allow_restricts_to_matching_blocks(): void {
		$this->set_allow( array( 'core/paragraph' ) );

		$p = Block_Tags::render( '<bs:core-paragraph>Hello</bs:core-paragraph>' );
		$this->assertStringContainsString( '<p>', $p );

		$sep_input = '<bs:core-separator />';
		$sep       = Block_Tags::render( $sep_input );
		$this->assertSame( $sep_input, $sep );
	}

	public function test_allow_wildcard_pattern(): void {
		$this->set_allow( array( 'core/*' ) );

		$core = Block_Tags::render( '<bs:core-paragraph>Text</bs:core-paragraph>' );
		$this->assertStringContainsString( '<p>', $core );

		$bs_input = '<bs:blockstudio-type-unit />';
		$bs       = Block_Tags::render( $bs_input );
		$this->assertSame( $bs_input, $bs );
	}

	public function test_allow_multiple_patterns(): void {
		$this->set_allow( array( 'core/paragraph', 'core/heading' ) );

		$p = Block_Tags::render( '<bs:core-paragraph>Text</bs:core-paragraph>' );
		$this->assertStringContainsString( '<p>', $p );

		$h = Block_Tags::render( '<bs:core-heading level="2">Title</bs:core-heading>' );
		$this->assertStringContainsString( '<h2', $h );

		$sep_input = '<bs:core-separator />';
		$this->assertSame( $sep_input, Block_Tags::render( $sep_input ) );
	}

	// Deny takes precedence over allow

	public function test_deny_takes_precedence_over_allow(): void {
		$this->set_allow( array( 'core/*' ) );
		$this->set_deny( array( 'core/separator' ) );

		$p = Block_Tags::render( '<bs:core-paragraph>Text</bs:core-paragraph>' );
		$this->assertStringContainsString( '<p>', $p );

		$sep_input = '<bs:core-separator />';
		$this->assertSame( $sep_input, Block_Tags::render( $sep_input ) );
	}

	// Block syntax

	public function test_deny_works_with_block_syntax(): void {
		$this->set_deny( array( 'core/separator' ) );
		$input  = '<block name="core/separator" />';
		$result = Block_Tags::render( $input );
		$this->assertSame( $input, $result );
	}

	public function test_allow_works_with_block_syntax(): void {
		$this->set_allow( array( 'core/paragraph' ) );

		$p = Block_Tags::render( '<block name="core/paragraph">Text</block>' );
		$this->assertStringContainsString( '<p>', $p );

		$sep_input = '<block name="core/separator" />';
		$this->assertSame( $sep_input, Block_Tags::render( $sep_input ) );
	}

	// Unregistered blocks left untouched by render()

	public function test_unregistered_bs_tag_left_untouched(): void {
		$input  = '<bs:totally-unknown-block title="Hi" />';
		$result = Block_Tags::render( $input );
		$this->assertSame( $input, $result );
	}

	public function test_unregistered_block_tag_left_untouched(): void {
		$input  = '<block name="totally/unknown" />';
		$result = Block_Tags::render( $input );
		$this->assertSame( $input, $result );
	}

	// No allow/deny (default behavior)

	public function test_no_filters_renders_core_blocks(): void {
		$result = Block_Tags::render( '<bs:core-separator />' );
		$this->assertStringContainsString( '<hr', $result );
	}

	public function test_no_filters_renders_blockstudio_blocks(): void {
		$result = Block_Tags::render( '<bs:blockstudio-type-unit />' );
		$this->assertNotSame( '<bs:blockstudio-type-unit />', $result );
	}
}
