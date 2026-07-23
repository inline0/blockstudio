<?php

use Blockstudio\Block_Tags;
use PHPUnit\Framework\TestCase;

class BlockTagsPrefixesTest extends TestCase {

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

	private function set_deny( array $patterns ): void {
		$cb = function () use ( $patterns ) {
			return $patterns;
		};

		add_filter( 'blockstudio/block_tags/deny', $cb );
		$this->filter_callbacks[] = array( 'blockstudio/block_tags/deny', $cb );
	}

	private function get_blockstudio_attributes( array $block ): array {
		return $block['attrs']['blockstudio']['attributes'] ?? $block['attrs'];
	}

	public function test_single_namespace_prefix_resolves_block(): void {
		$this->set_prefixes( array( 'dv' => 'divine-homepage' ) );

		$result = Block_Tags::render( '<dv-card title="Homepage" />' );

		$this->assertStringContainsString( 'class="dv-card"', $result );
		$this->assertStringContainsString( 'Homepage', $result );
	}

	public function test_ordered_fallback_namespace_resolves_block(): void {
		$this->set_prefixes( array( 'dv' => array( 'divine-homepage', 'bsui' ) ) );

		$result = Block_Tags::render( '<dv-button label="Fallback" />' );

		$this->assertStringContainsString( 'class="dv-button"', $result );
		$this->assertStringContainsString( 'Fallback', $result );
	}

	public function test_multi_hyphen_slug_maps_directly(): void {
		$this->set_prefixes( array( 'dv' => array( 'divine-homepage', 'bsui' ) ) );

		$result = Block_Tags::render( '<dv-onumia-feature-matrix title="Matrix" />' );

		$this->assertStringContainsString( 'class="dv-feature-matrix"', $result );
		$this->assertStringContainsString( 'Matrix', $result );
	}

	public function test_alias_overrides_prefix_resolution_for_same_tag(): void {
		$this->set_prefixes( array( 'dv' => array( 'divine-homepage', 'bsui' ) ) );
		$this->set_aliases( array( 'dv-button' => 'divine-homepage/card' ) );

		$result = Block_Tags::render( '<dv-button label="Ignored" />' );

		$this->assertStringContainsString( 'class="dv-card"', $result );
		$this->assertStringNotContainsString( 'dv-button', $result );
	}

	public function test_unknown_prefixed_tag_is_left_untouched(): void {
		$this->set_prefixes( array( 'dv' => array( 'divine-homepage', 'bsui' ) ) );

		$input = '<dv-nope title="Nope" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_paired_prefix_tag_preserves_attributes_and_inner_content(): void {
		$this->set_prefixes( array( 'dv' => 'divine-homepage' ) );

		$result = Block_Tags::render( '<dv-card title="Paired"><span>Inner</span></dv-card>' );

		$this->assertStringContainsString( 'Paired', $result );
		$this->assertStringContainsString( '<span>Inner</span>', $result );
	}

	public function test_allow_deny_applies_to_prefix_resolved_blocks(): void {
		$this->set_prefixes( array( 'dv' => array( 'divine-homepage', 'bsui' ) ) );
		$this->set_deny( array( 'bsui/*' ) );

		$input = '<dv-button label="Denied" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_invalid_prefix_registrations_are_ignored(): void {
		$this->set_prefixes(
			array(
				'dv-bad' => 'divine-homepage',
				'1dv'   => 'divine-homepage',
				'ok'    => 'divine-homepage',
			)
		);

		$this->assertSame( '<dv-bad-card />', Block_Tags::render( '<dv-bad-card />' ) );
		$this->assertSame( '<1dv-card />', Block_Tags::render( '<1dv-card />' ) );
		$this->assertStringContainsString( 'dv-card', Block_Tags::render( '<ok-card />' ) );
	}

	public function test_prefix_tags_parse_into_block_arrays(): void {
		$this->set_prefixes( array( 'dv' => array( 'divine-homepage', 'bsui' ) ) );

		$blocks = Block_Tags::parse_inner_blocks( '<dv-card title="Parsed"><dv-button label="Child" /></dv-card>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'divine-homepage/card', $blocks[0]['blockName'] );
		$this->assertSame( 'Parsed', $this->get_blockstudio_attributes( $blocks[0] )['title'] ?? null );
		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'bsui/button', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	public function test_prefix_tags_parse_before_html_fallback(): void {
		$this->set_prefixes( array( 'dv' => 'divine-homepage' ) );

		$blocks = Block_Tags::parse_all_elements( '<dv-card title="Parsed"><p>Body</p></dv-card>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'divine-homepage/card', $blocks[0]['blockName'] );
		$this->assertSame( 'Parsed', $this->get_blockstudio_attributes( $blocks[0] )['title'] ?? null );
		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	// Nested prefix resolution: a brand prefix composing over a namespace prefix.

	public function test_nested_prefix_resolves_through_inner_prefix(): void {
		$this->set_prefixes(
			array(
				'dv' => array( 'divine-homepage' ),
				'ui' => array( 'bsui' ),
			)
		);

		// divine-homepage/ui-button is not registered, so dv-ui-button falls
		// through to the ui prefix and resolves bsui/button.
		$blocks = Block_Tags::parse_inner_blocks( '<dv-ui-button label="Nested" />' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'bsui/button', $blocks[0]['blockName'] );
		$this->assertSame( 'Nested', $this->get_blockstudio_attributes( $blocks[0] )['label'] ?? null );
	}

	public function test_direct_resolution_takes_precedence_over_nested(): void {
		// onumia is a registered prefix, but the direct divine-homepage match
		// must win: dv-onumia-feature-matrix stays divine-homepage/onumia-feature-matrix
		// and never recurses into the onumia prefix.
		$this->set_prefixes(
			array(
				'dv'     => array( 'divine-homepage' ),
				'onumia' => array( 'bsui' ),
			)
		);

		$blocks = Block_Tags::parse_inner_blocks( '<dv-onumia-feature-matrix title="Direct" />' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'divine-homepage/onumia-feature-matrix', $blocks[0]['blockName'] );
	}

	public function test_deep_nested_prefix_chain_resolves(): void {
		$this->set_prefixes(
			array(
				'xy' => array( 'divine-homepage' ),
				'dv' => array( 'divine-homepage' ),
				'ui' => array( 'bsui' ),
			)
		);

		// xy-dv-ui-button peels one registered prefix per level until bsui/button.
		$blocks = Block_Tags::parse_inner_blocks( '<xy-dv-ui-button label="Deep" />' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'bsui/button', $blocks[0]['blockName'] );
	}

	public function test_nested_unknown_inner_slug_left_untouched(): void {
		$this->set_prefixes(
			array(
				'dv' => array( 'divine-homepage' ),
				'ui' => array( 'bsui' ),
			)
		);

		// ui is a registered prefix but bsui/nope does not exist, so nothing resolves.
		$input = '<dv-ui-nope title="Nope" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_nested_requires_registered_inner_prefix(): void {
		// ui is not a registered prefix, so the nested branch is never taken.
		$this->set_prefixes( array( 'dv' => array( 'divine-homepage' ) ) );

		$input = '<dv-ui-button label="No inner prefix" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_repeated_prefix_does_not_recurse(): void {
		// dv-dv-button: the inner segment repeats the outer prefix, so the guard
		// blocks recursion and the tag is left untouched.
		$this->set_prefixes( array( 'dv' => array( 'divine-homepage' ) ) );

		$input = '<dv-dv-button label="Loop" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_pathological_nested_chain_terminates(): void {
		// Alternating registered prefixes that never resolve must terminate and
		// leave the tag untouched (guards against runaway recursion).
		$this->set_prefixes(
			array(
				'dv' => array( 'divine-homepage' ),
				'ui' => array( 'bsui' ),
			)
		);

		$input = '<dv-ui-dv-ui-dv-ui-nope title="Deep miss" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}
}
