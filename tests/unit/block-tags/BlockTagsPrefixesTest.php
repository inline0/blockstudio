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
		$this->set_prefixes( array( 'theme' => 'theme-components' ) );

		$result = Block_Tags::render( '<theme-card title="Homepage" />' );

		$this->assertStringContainsString( 'class="theme-card"', $result );
		$this->assertStringContainsString( 'Homepage', $result );
	}

	public function test_ordered_fallback_namespace_resolves_block(): void {
		$this->set_prefixes( array( 'theme' => array( 'theme-components', 'bsui' ) ) );

		$result = Block_Tags::render( '<theme-button label="Fallback" />' );

		$this->assertStringContainsString( 'class="bsui-button"', $result );
		$this->assertStringContainsString( 'Fallback', $result );
	}

	public function test_multi_hyphen_slug_maps_directly(): void {
		$this->set_prefixes( array( 'theme' => array( 'theme-components', 'bsui' ) ) );

		$result = Block_Tags::render( '<theme-ui-feature-matrix title="Matrix" />' );

		$this->assertStringContainsString( 'class="theme-feature-matrix"', $result );
		$this->assertStringContainsString( 'Matrix', $result );
	}

	public function test_alias_overrides_prefix_resolution_for_same_tag(): void {
		$this->set_prefixes( array( 'theme' => array( 'theme-components', 'bsui' ) ) );
		$this->set_aliases( array( 'theme-button' => 'theme-components/card' ) );

		$result = Block_Tags::render( '<theme-button label="Ignored" />' );

		$this->assertStringContainsString( 'class="theme-card"', $result );
		$this->assertStringNotContainsString( 'bsui-button', $result );
	}

	public function test_unknown_prefixed_tag_is_left_untouched(): void {
		$this->set_prefixes( array( 'theme' => array( 'theme-components', 'bsui' ) ) );

		$input = '<theme-nope title="Nope" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_paired_prefix_tag_preserves_attributes_and_inner_content(): void {
		$this->set_prefixes( array( 'theme' => 'theme-components' ) );

		$result = Block_Tags::render( '<theme-card title="Paired"><span>Inner</span></theme-card>' );

		$this->assertStringContainsString( 'Paired', $result );
		$this->assertStringContainsString( '<span>Inner</span>', $result );
	}

	public function test_allow_deny_applies_to_prefix_resolved_blocks(): void {
		$this->set_prefixes( array( 'theme' => array( 'theme-components', 'bsui' ) ) );
		$this->set_deny( array( 'bsui/*' ) );

		$input = '<theme-button label="Denied" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_invalid_prefix_registrations_are_ignored(): void {
		$this->set_prefixes(
			array(
				'theme-bad' => 'theme-components',
				'1theme'    => 'theme-components',
				'ok'        => 'theme-components',
			)
		);

		$this->assertSame( '<theme-bad-card />', Block_Tags::render( '<theme-bad-card />' ) );
		$this->assertSame( '<1theme-card />', Block_Tags::render( '<1theme-card />' ) );
		$this->assertStringContainsString( 'theme-card', Block_Tags::render( '<ok-card />' ) );
	}

	public function test_prefix_tags_parse_into_block_arrays(): void {
		$this->set_prefixes( array( 'theme' => array( 'theme-components', 'bsui' ) ) );

		$blocks = Block_Tags::parse_inner_blocks( '<theme-card title="Parsed"><theme-button label="Child" /></theme-card>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'theme-components/card', $blocks[0]['blockName'] );
		$this->assertSame( 'Parsed', $this->get_blockstudio_attributes( $blocks[0] )['title'] ?? null );
		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'bsui/button', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	public function test_prefix_tags_parse_before_html_fallback(): void {
		$this->set_prefixes( array( 'theme' => 'theme-components' ) );

		$blocks = Block_Tags::parse_all_elements( '<theme-card title="Parsed"><p>Body</p></theme-card>' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'theme-components/card', $blocks[0]['blockName'] );
		$this->assertSame( 'Parsed', $this->get_blockstudio_attributes( $blocks[0] )['title'] ?? null );
		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	// Nested prefix resolution: a brand prefix composing over a namespace prefix.

	public function test_nested_prefix_resolves_through_inner_prefix(): void {
		$this->set_prefixes(
			array(
				'theme' => array( 'theme-components' ),
				'ui'    => array( 'bsui' ),
			)
		);

		// theme-components/ui-button is not registered, so theme-ui-button falls
		// through to the ui prefix and resolves bsui/button.
		$blocks = Block_Tags::parse_inner_blocks( '<theme-ui-button label="Nested" />' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'bsui/button', $blocks[0]['blockName'] );
		$this->assertSame( 'Nested', $this->get_blockstudio_attributes( $blocks[0] )['label'] ?? null );
	}

	public function test_direct_resolution_takes_precedence_over_nested(): void {
		// ui is a registered prefix, but the direct theme-components match
		// must win: theme-ui-feature-matrix stays
		// theme-components/ui-feature-matrix and never recurses into ui.
		$this->set_prefixes(
			array(
				'theme' => array( 'theme-components' ),
				'ui'    => array( 'bsui' ),
			)
		);

		$blocks = Block_Tags::parse_inner_blocks( '<theme-ui-feature-matrix title="Direct" />' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'theme-components/ui-feature-matrix', $blocks[0]['blockName'] );
	}

	public function test_deep_nested_prefix_chain_resolves(): void {
		$this->set_prefixes(
			array(
				'brand' => array( 'theme-components' ),
				'theme' => array( 'theme-components' ),
				'ui'    => array( 'bsui' ),
			)
		);

		// brand-theme-ui-button peels one registered prefix per level until bsui/button.
		$blocks = Block_Tags::parse_inner_blocks( '<brand-theme-ui-button label="Deep" />' );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'bsui/button', $blocks[0]['blockName'] );
	}

	public function test_nested_unknown_inner_slug_left_untouched(): void {
		$this->set_prefixes(
			array(
				'theme' => array( 'theme-components' ),
				'ui'    => array( 'bsui' ),
			)
		);

		// ui is a registered prefix but bsui/nope does not exist, so nothing resolves.
		$input = '<theme-ui-nope title="Nope" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_nested_requires_registered_inner_prefix(): void {
		// ui is not a registered prefix, so the nested branch is never taken.
		$this->set_prefixes( array( 'theme' => array( 'theme-components' ) ) );

		$input = '<theme-ui-button label="No inner prefix" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_repeated_prefix_does_not_recurse(): void {
		// theme-theme-button repeats the outer prefix, so the guard
		// blocks recursion and the tag is left untouched.
		$this->set_prefixes( array( 'theme' => array( 'theme-components' ) ) );

		$input = '<theme-theme-button label="Loop" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}

	public function test_pathological_nested_chain_terminates(): void {
		// Alternating registered prefixes that never resolve must terminate and
		// leave the tag untouched (guards against runaway recursion).
		$this->set_prefixes(
			array(
				'theme' => array( 'theme-components' ),
				'ui'    => array( 'bsui' ),
			)
		);

		$input = '<theme-ui-theme-ui-theme-ui-nope title="Deep miss" />';

		$this->assertSame( $input, Block_Tags::render( $input ) );
	}
}
