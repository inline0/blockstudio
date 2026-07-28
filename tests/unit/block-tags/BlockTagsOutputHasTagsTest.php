<?php

use Blockstudio\Block_Tags;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Block_Tags::output_has_tags(), the cheap gate that decides
 * whether a block template's output needs a render() pass.
 *
 * The gate mirrors render()'s own short-circuit guard, so it must be true
 * whenever render() would transform the string and be driven by live filter
 * state (a prefix/alias registered after a first probe must be seen).
 *
 * The test theme registers "theme" as a prefix globally (test-helper.php), so
 * "zz" is used wherever a guaranteed-unregistered prefix is needed.
 */
class BlockTagsOutputHasTagsTest extends TestCase {

	private array $filter_callbacks = array();

	protected function tearDown(): void {
		foreach ( $this->filter_callbacks as $cb ) {
			remove_filter( $cb[0], $cb[1] );
		}
		$this->filter_callbacks = array();
		parent::tearDown();
	}

	private function set_prefixes( array $prefixes ): void {
		$cb = function () use ( $prefixes ) {
			return $prefixes;
		};
		add_filter( 'blockstudio/block_tags/prefixes', $cb );
		$this->filter_callbacks[] = array( 'blockstudio/block_tags/prefixes', $cb );
	}

	private function set_aliases( array $aliases ): void {
		$cb = function () use ( $aliases ) {
			return $aliases;
		};
		add_filter( 'blockstudio/block_tags/tag_aliases', $cb );
		$this->filter_callbacks[] = array( 'blockstudio/block_tags/tag_aliases', $cb );
	}

	// Built-in syntaxes

	public function test_true_for_bs_tag(): void {
		$this->assertTrue( Block_Tags::output_has_tags( '<div><bs:core-paragraph>Hi</bs:core-paragraph></div>' ) );
	}

	public function test_true_for_block_tag(): void {
		$this->assertTrue( Block_Tags::output_has_tags( '<block name="core/separator" />' ) );
	}

	// Plain output

	public function test_false_for_empty_string(): void {
		$this->assertFalse( Block_Tags::output_has_tags( '' ) );
	}

	public function test_false_for_plain_text(): void {
		$this->assertFalse( Block_Tags::output_has_tags( 'Just some text, no tags at all.' ) );
	}

	public function test_false_for_plain_html(): void {
		$this->assertFalse( Block_Tags::output_has_tags( '<div class="card"><p>Body</p></div>' ) );
	}

	public function test_false_for_unregistered_custom_element(): void {
		$this->assertFalse( Block_Tags::output_has_tags( '<zz-button label="X" />' ) );
	}

	// Registered prefixes and aliases

	public function test_true_for_registered_prefix(): void {
		$this->set_prefixes( array( 'zz' => array( 'bsui' ) ) );
		$this->assertTrue( Block_Tags::output_has_tags( '<p>Before</p><zz-button label="X" />' ) );
	}

	public function test_true_for_registered_alias(): void {
		$this->set_aliases( array( 'ax-button' => 'bsui/button' ) );
		$this->assertTrue( Block_Tags::output_has_tags( '<ax-button label="X" />' ) );
	}

	// Live filter state (the memoization must not cache the first probe).

	public function test_prefix_registered_after_first_probe_is_detected(): void {
		// Probe once with zz unregistered; a stale memo would cache its absence.
		$this->assertFalse( Block_Tags::output_has_tags( '<zz-button />' ) );

		$this->set_prefixes( array( 'zz' => array( 'bsui' ) ) );

		$this->assertTrue( Block_Tags::output_has_tags( '<zz-button />' ) );
	}

	public function test_probe_reflects_filter_state_in_both_directions(): void {
		$cb = static function () {
			return array( 'zz' => array( 'bsui' ) );
		};

		$this->assertFalse( Block_Tags::output_has_tags( '<zz-button />' ) );

		add_filter( 'blockstudio/block_tags/prefixes', $cb );
		$this->assertTrue( Block_Tags::output_has_tags( '<zz-button />' ) );

		remove_filter( 'blockstudio/block_tags/prefixes', $cb );
		$this->assertFalse( Block_Tags::output_has_tags( '<zz-button />' ) );
	}

	// Gate contract vs render()

	public function test_gate_is_true_whenever_render_transforms(): void {
		$this->set_prefixes( array( 'zz' => array( 'bsui' ) ) );
		$this->set_aliases( array( 'ax-button' => 'bsui/button' ) );

		$cases = array(
			'<bs:core-separator />',
			'<block name="core/separator" />',
			'<zz-button label="X" />',
			'<ax-button label="Y" />',
		);

		foreach ( $cases as $input ) {
			$this->assertNotSame( $input, Block_Tags::render( $input ), "render() should transform: {$input}" );
			$this->assertTrue( Block_Tags::output_has_tags( $input ), "gate should be true: {$input}" );
		}
	}

	public function test_gate_false_implies_render_is_a_no_op(): void {
		$plain = array(
			'',
			'Just text.',
			'<div class="card"><p>Body</p></div>',
			'<zz-button label="Not registered" />',
		);

		foreach ( $plain as $input ) {
			$this->assertFalse( Block_Tags::output_has_tags( $input ), "gate should be false: {$input}" );
			$this->assertSame( $input, Block_Tags::render( $input ), "render() should be a no-op: {$input}" );
		}
	}
}
