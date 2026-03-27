<?php

use Blockstudio\Block_Merger;
use PHPUnit\Framework\TestCase;

class BlockMergerTest extends TestCase {

	private Block_Merger $merger;

	protected function setUp(): void {
		$this->merger = new Block_Merger();
	}

	// Helper to build a minimal block array.

	private function block( string $name, array $attrs = array(), string $inner_html = '', array $inner_blocks = array() ): array {
		$inner_content = array();

		if ( ! empty( $inner_blocks ) ) {
			$inner_content[] = $inner_html ? '<div>' : '';
			foreach ( $inner_blocks as $ignored ) {
				$inner_content[] = null;
			}
			$inner_content[] = $inner_html ? '</div>' : '';
		} else {
			$inner_content[] = $inner_html;
		}

		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerHTML'    => $inner_html,
			'innerContent' => $inner_content,
			'innerBlocks'  => $inner_blocks,
		);
	}

	private function keyed_block( string $name, string $key, string $inner_html = '', array $extra_attrs = array(), array $inner_blocks = array() ): array {
		$attrs = array_merge( array( '__BLOCKSTUDIO_KEY' => $key ), $extra_attrs );
		return $this->block( $name, $attrs, $inner_html, $inner_blocks );
	}

	// merge() with empty inputs

	public function test_merge_empty_arrays_returns_empty(): void {
		$result = $this->merger->merge( array(), array() );
		$this->assertSame( array(), $result );
	}

	public function test_merge_empty_new_returns_empty(): void {
		$old = array( $this->block( 'core/paragraph', array(), '<p>Old</p>' ) );
		$result = $this->merger->merge( array(), $old );
		$this->assertSame( array(), $result );
	}

	public function test_merge_empty_old_returns_new_unchanged(): void {
		$new = array( $this->block( 'core/paragraph', array(), '<p>New</p>' ) );
		$result = $this->merger->merge( $new, array() );
		$this->assertCount( 1, $result );
		$this->assertSame( '<p>New</p>', $result[0]['innerHTML'] );
	}

	// Unkeyed blocks pass through

	public function test_unkeyed_blocks_pass_through_from_template(): void {
		$new = array(
			$this->block( 'core/paragraph', array(), '<p>Template paragraph</p>' ),
			$this->block( 'core/heading', array(), '<h2>Template heading</h2>' ),
		);
		$old = array(
			$this->block( 'core/paragraph', array(), '<p>Old paragraph</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertCount( 2, $result );
		$this->assertSame( '<p>Template paragraph</p>', $result[0]['innerHTML'] );
		$this->assertSame( '<h2>Template heading</h2>', $result[1]['innerHTML'] );
	}

	// Keyed block merging preserves old content

	public function test_keyed_block_preserves_old_inner_html(): void {
		$new = array( $this->keyed_block( 'core/paragraph', 'intro', '<p>Template text</p>' ) );
		$old = array( $this->keyed_block( 'core/paragraph', 'intro', '<p>User-edited text</p>' ) );

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>User-edited text</p>', $result[0]['innerHTML'] );
	}

	public function test_keyed_block_preserves_old_inner_content(): void {
		$new = array( $this->keyed_block( 'core/paragraph', 'intro', '<p>New</p>' ) );
		$old_block = $this->keyed_block( 'core/paragraph', 'intro', '<p>Old</p>' );
		$old_block['innerContent'] = array( '<p>Custom inner content</p>' );

		$result = $this->merger->merge( $new, array( $old_block ) );
		$this->assertSame( array( '<p>Custom inner content</p>' ), $result[0]['innerContent'] );
	}

	public function test_keyed_block_preserves_old_inner_blocks(): void {
		$child = $this->block( 'core/paragraph', array(), '<p>Child</p>' );

		$new = array( $this->keyed_block( 'core/group', 'wrapper', '<div>', array(), array() ) );
		$old = array( $this->keyed_block( 'core/group', 'wrapper', '<div>', array(), array( $child ) ) );

		$result = $this->merger->merge( $new, $old );
		$this->assertCount( 1, $result[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $result[0]['innerBlocks'][0]['blockName'] );
	}

	public function test_keyed_block_uses_new_attrs(): void {
		$new = array(
			$this->keyed_block( 'core/paragraph', 'intro', '<p>New</p>', array( 'className' => 'new-class' ) ),
		);
		$old = array(
			$this->keyed_block( 'core/paragraph', 'intro', '<p>Old</p>', array( 'className' => 'old-class' ) ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( 'new-class', $result[0]['attrs']['className'] );
		$this->assertSame( 'intro', $result[0]['attrs']['__BLOCKSTUDIO_KEY'] );
	}

	// Block type changed: template wins

	public function test_block_type_changed_template_wins_entirely(): void {
		$new = array( $this->keyed_block( 'core/heading', 'intro', '<h2>New heading</h2>' ) );
		$old = array( $this->keyed_block( 'core/paragraph', 'intro', '<p>Old paragraph</p>' ) );

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( 'core/heading', $result[0]['blockName'] );
		$this->assertSame( '<h2>New heading</h2>', $result[0]['innerHTML'] );
	}

	public function test_block_type_changed_keeps_new_attrs(): void {
		$new = array(
			$this->keyed_block( 'core/heading', 'intro', '<h2>New</h2>', array( 'level' => 2 ) ),
		);
		$old = array(
			$this->keyed_block( 'core/paragraph', 'intro', '<p>Old</p>', array( 'dropCap' => true ) ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( 2, $result[0]['attrs']['level'] );
		$this->assertArrayNotHasKey( 'dropCap', $result[0]['attrs'] );
	}

	// Multiple keyed blocks

	public function test_multiple_keyed_blocks_merge_independently(): void {
		$new = array(
			$this->keyed_block( 'core/paragraph', 'first', '<p>New first</p>' ),
			$this->keyed_block( 'core/paragraph', 'second', '<p>New second</p>' ),
		);
		$old = array(
			$this->keyed_block( 'core/paragraph', 'first', '<p>Old first</p>' ),
			$this->keyed_block( 'core/paragraph', 'second', '<p>Old second</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Old first</p>', $result[0]['innerHTML'] );
		$this->assertSame( '<p>Old second</p>', $result[1]['innerHTML'] );
	}

	// Mixed keyed and unkeyed blocks

	public function test_mixed_keyed_and_unkeyed_blocks(): void {
		$new = array(
			$this->block( 'core/heading', array(), '<h1>Template title</h1>' ),
			$this->keyed_block( 'core/paragraph', 'content', '<p>New content</p>' ),
			$this->block( 'core/separator', array(), '<hr />' ),
		);
		$old = array(
			$this->keyed_block( 'core/paragraph', 'content', '<p>User content</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertCount( 3, $result );
		$this->assertSame( '<h1>Template title</h1>', $result[0]['innerHTML'] );
		$this->assertSame( '<p>User content</p>', $result[1]['innerHTML'] );
		$this->assertSame( '<hr />', $result[2]['innerHTML'] );
	}

	// Key in old not present in new is dropped

	public function test_old_keyed_block_not_in_new_is_dropped(): void {
		$new = array(
			$this->keyed_block( 'core/paragraph', 'kept', '<p>Kept</p>' ),
		);
		$old = array(
			$this->keyed_block( 'core/paragraph', 'kept', '<p>Old kept</p>' ),
			$this->keyed_block( 'core/paragraph', 'removed', '<p>Should be gone</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertCount( 1, $result );
		$this->assertSame( 'kept', $result[0]['attrs']['__BLOCKSTUDIO_KEY'] );
	}

	// New keyed block without old match passes through

	public function test_new_keyed_block_without_old_match_passes_through(): void {
		$new = array(
			$this->keyed_block( 'core/paragraph', 'brand-new', '<p>Brand new block</p>' ),
		);

		$result = $this->merger->merge( $new, array() );
		$this->assertCount( 1, $result );
		$this->assertSame( '<p>Brand new block</p>', $result[0]['innerHTML'] );
	}

	// Nested blocks: key map is flat across the tree

	public function test_keyed_block_nested_in_old_matches_top_level_new(): void {
		$new = array(
			$this->keyed_block( 'core/paragraph', 'deep', '<p>New deep</p>' ),
		);

		$nested_block = $this->keyed_block( 'core/paragraph', 'deep', '<p>Old nested content</p>' );
		$old = array(
			$this->block( 'core/group', array(), '<div>', array( $nested_block ) ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Old nested content</p>', $result[0]['innerHTML'] );
	}

	public function test_keyed_block_deeply_nested_in_old(): void {
		$leaf = $this->keyed_block( 'core/paragraph', 'leaf', '<p>Deep leaf content</p>' );
		$mid = $this->block( 'core/group', array(), '<div>', array( $leaf ) );
		$old = array(
			$this->block( 'core/group', array(), '<div>', array( $mid ) ),
		);

		$new = array(
			$this->keyed_block( 'core/paragraph', 'leaf', '<p>Template leaf</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Deep leaf content</p>', $result[0]['innerHTML'] );
	}

	// New blocks with inner blocks recurse

	public function test_new_unkeyed_container_recurses_into_inner_blocks(): void {
		$inner_new = $this->keyed_block( 'core/paragraph', 'inner', '<p>New inner</p>' );
		$new = array(
			$this->block( 'core/group', array(), '<div>', array( $inner_new ) ),
		);

		$old = array(
			$this->keyed_block( 'core/paragraph', 'inner', '<p>Old inner content</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Old inner content</p>', $result[0]['innerBlocks'][0]['innerHTML'] );
	}

	public function test_deeply_nested_new_blocks_recurse(): void {
		$deep_new = $this->keyed_block( 'core/paragraph', 'deep', '<p>New deep</p>' );
		$mid_new = $this->block( 'core/group', array(), '<div>', array( $deep_new ) );
		$new = array(
			$this->block( 'core/group', array(), '<div>', array( $mid_new ) ),
		);

		$old = array(
			$this->keyed_block( 'core/paragraph', 'deep', '<p>Old deep content</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$inner = $result[0]['innerBlocks'][0]['innerBlocks'][0];
		$this->assertSame( '<p>Old deep content</p>', $inner['innerHTML'] );
	}

	// Keyed blocks matched at different nesting levels

	public function test_old_nested_matches_new_nested(): void {
		$inner_old = $this->keyed_block( 'core/paragraph', 'para', '<p>Old paragraph</p>' );
		$old = array(
			$this->block( 'core/group', array(), '<div>', array( $inner_old ) ),
		);

		$inner_new = $this->keyed_block( 'core/paragraph', 'para', '<p>New paragraph</p>' );
		$new = array(
			$this->block( 'core/columns', array(), '<div>', array( $inner_new ) ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Old paragraph</p>', $result[0]['innerBlocks'][0]['innerHTML'] );
	}

	// Reordering: template order wins, content preserved

	public function test_reordered_keyed_blocks_follow_template_order(): void {
		$new = array(
			$this->keyed_block( 'core/paragraph', 'second', '<p>New second</p>' ),
			$this->keyed_block( 'core/paragraph', 'first', '<p>New first</p>' ),
		);
		$old = array(
			$this->keyed_block( 'core/paragraph', 'first', '<p>Old first</p>' ),
			$this->keyed_block( 'core/paragraph', 'second', '<p>Old second</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( 'second', $result[0]['attrs']['__BLOCKSTUDIO_KEY'] );
		$this->assertSame( '<p>Old second</p>', $result[0]['innerHTML'] );
		$this->assertSame( 'first', $result[1]['attrs']['__BLOCKSTUDIO_KEY'] );
		$this->assertSame( '<p>Old first</p>', $result[1]['innerHTML'] );
	}

	// Duplicate keys in old blocks

	public function test_duplicate_keys_in_old_triggers_warning(): void {
		$old = array(
			$this->keyed_block( 'core/paragraph', 'dupe', '<p>First occurrence</p>' ),
			$this->keyed_block( 'core/paragraph', 'dupe', '<p>Second occurrence</p>' ),
		);
		$new = array(
			$this->keyed_block( 'core/paragraph', 'dupe', '<p>Template</p>' ),
		);

		$warning_triggered = false;
		set_error_handler(
			function ( $errno, $errstr ) use ( &$warning_triggered ) {
				if ( E_USER_WARNING === $errno && str_contains( $errstr, 'Duplicate block key' ) ) {
					$warning_triggered = true;
				}
				return true;
			}
		);

		$result = $this->merger->merge( $new, $old );

		restore_error_handler();

		$this->assertTrue( $warning_triggered );
		$this->assertSame( '<p>First occurrence</p>', $result[0]['innerHTML'] );
	}

	public function test_duplicate_key_warning_contains_key_name(): void {
		$old = array(
			$this->keyed_block( 'core/paragraph', 'my-key', '<p>First</p>' ),
			$this->keyed_block( 'core/paragraph', 'my-key', '<p>Second</p>' ),
		);
		$new = array(
			$this->keyed_block( 'core/paragraph', 'my-key', '<p>New</p>' ),
		);

		$captured_message = '';
		set_error_handler(
			function ( $errno, $errstr ) use ( &$captured_message ) {
				if ( E_USER_WARNING === $errno ) {
					$captured_message = $errstr;
				}
				return true;
			}
		);

		$this->merger->merge( $new, $old );

		restore_error_handler();

		$this->assertStringContainsString( 'my-key', $captured_message );
	}

	// Duplicate key: first occurrence wins in the key map

	public function test_duplicate_key_first_occurrence_wins(): void {
		$old = array(
			$this->keyed_block( 'core/paragraph', 'dupe', '<p>Winner</p>' ),
			$this->keyed_block( 'core/paragraph', 'dupe', '<p>Loser</p>' ),
		);
		$new = array(
			$this->keyed_block( 'core/paragraph', 'dupe', '<p>Template</p>' ),
		);

		set_error_handler( function () { return true; } );
		$result = $this->merger->merge( $new, $old );
		restore_error_handler();

		$this->assertSame( '<p>Winner</p>', $result[0]['innerHTML'] );
	}

	// Merge is idempotent for matching blocks

	public function test_merge_is_idempotent_when_blocks_match(): void {
		$blocks = array(
			$this->keyed_block( 'core/paragraph', 'a', '<p>Content A</p>' ),
			$this->keyed_block( 'core/heading', 'b', '<h2>Content B</h2>' ),
		);

		$result1 = $this->merger->merge( $blocks, $blocks );

		$merger2 = new Block_Merger();
		$result2 = $merger2->merge( $result1, $blocks );

		$this->assertSame( $result1[0]['innerHTML'], $result2[0]['innerHTML'] );
		$this->assertSame( $result1[1]['innerHTML'], $result2[1]['innerHTML'] );
	}

	// Key map isolation: separate merge() calls don't share state

	public function test_separate_merges_have_independent_key_maps(): void {
		$new1 = array( $this->keyed_block( 'core/paragraph', 'shared-key', '<p>Template 1</p>' ) );
		$old1 = array( $this->keyed_block( 'core/paragraph', 'shared-key', '<p>Old 1</p>' ) );

		$result1 = $this->merger->merge( $new1, $old1 );
		$this->assertSame( '<p>Old 1</p>', $result1[0]['innerHTML'] );

		$new2 = array( $this->keyed_block( 'core/paragraph', 'shared-key', '<p>Template 2</p>' ) );
		$old2 = array( $this->keyed_block( 'core/paragraph', 'shared-key', '<p>Old 2</p>' ) );

		$result2 = $this->merger->merge( $new2, $old2 );
		$this->assertSame( '<p>Old 2</p>', $result2[0]['innerHTML'] );
	}

	// Blocks with null key attribute are treated as unkeyed

	public function test_null_key_value_treated_as_unkeyed(): void {
		$new = array(
			$this->block( 'core/paragraph', array( '__BLOCKSTUDIO_KEY' => null ), '<p>New</p>' ),
		);
		$old = array(
			$this->block( 'core/paragraph', array( '__BLOCKSTUDIO_KEY' => null ), '<p>Old</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>New</p>', $result[0]['innerHTML'] );
	}

	// Integer key values

	public function test_integer_key_zero_is_valid(): void {
		$new = array( $this->keyed_block( 'core/paragraph', '0', '<p>New</p>' ) );
		$old = array( $this->keyed_block( 'core/paragraph', '0', '<p>Old</p>' ) );

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Old</p>', $result[0]['innerHTML'] );
	}

	// Empty string key

	public function test_empty_string_key_is_valid(): void {
		$new = array( $this->keyed_block( 'core/paragraph', '', '<p>New</p>' ) );
		$old = array( $this->keyed_block( 'core/paragraph', '', '<p>Old</p>' ) );

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Old</p>', $result[0]['innerHTML'] );
	}

	// Large number of blocks

	public function test_many_keyed_blocks_merge_correctly(): void {
		$new = array();
		$old = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$new[] = $this->keyed_block( 'core/paragraph', "key-{$i}", "<p>New {$i}</p>" );
			$old[] = $this->keyed_block( 'core/paragraph', "key-{$i}", "<p>Old {$i}</p>" );
		}

		$result = $this->merger->merge( $new, $old );
		$this->assertCount( 50, $result );

		for ( $i = 0; $i < 50; $i++ ) {
			$this->assertSame( "<p>Old {$i}</p>", $result[ $i ]['innerHTML'] );
		}
	}

	// Template adds new blocks alongside existing keyed blocks

	public function test_template_adds_new_blocks_around_keyed(): void {
		$new = array(
			$this->block( 'core/heading', array(), '<h1>New title</h1>' ),
			$this->keyed_block( 'core/paragraph', 'body', '<p>New body</p>' ),
			$this->block( 'core/separator', array(), '<hr />' ),
			$this->keyed_block( 'core/paragraph', 'footer', '<p>New footer</p>' ),
		);
		$old = array(
			$this->keyed_block( 'core/paragraph', 'body', '<p>User body</p>' ),
			$this->keyed_block( 'core/paragraph', 'footer', '<p>User footer</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertCount( 4, $result );
		$this->assertSame( '<h1>New title</h1>', $result[0]['innerHTML'] );
		$this->assertSame( '<p>User body</p>', $result[1]['innerHTML'] );
		$this->assertSame( '<hr />', $result[2]['innerHTML'] );
		$this->assertSame( '<p>User footer</p>', $result[3]['innerHTML'] );
	}

	// Keyed container block preserves old inner blocks (not recursed)

	public function test_keyed_container_preserves_entire_old_inner_tree(): void {
		$old_child_a = $this->block( 'core/paragraph', array(), '<p>Child A</p>' );
		$old_child_b = $this->block( 'core/heading', array(), '<h3>Child B</h3>' );
		$old = array(
			$this->keyed_block( 'core/group', 'wrapper', '<div>', array(), array( $old_child_a, $old_child_b ) ),
		);

		$new_child = $this->block( 'core/paragraph', array(), '<p>Replacement</p>' );
		$new = array(
			$this->keyed_block( 'core/group', 'wrapper', '<div>', array(), array( $new_child ) ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertCount( 2, $result[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $result[0]['innerBlocks'][0]['blockName'] );
		$this->assertSame( '<p>Child A</p>', $result[0]['innerBlocks'][0]['innerHTML'] );
		$this->assertSame( 'core/heading', $result[0]['innerBlocks'][1]['blockName'] );
	}

	// Keyed new block not matched skips recursion into its inner blocks

	public function test_unmatched_keyed_new_block_still_recurses_children(): void {
		$inner = $this->keyed_block( 'core/paragraph', 'child', '<p>New child</p>' );
		$new = array(
			$this->block( 'core/group', array(), '<div>', array( $inner ) ),
		);
		$old = array(
			$this->keyed_block( 'core/paragraph', 'child', '<p>Old child</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Old child</p>', $result[0]['innerBlocks'][0]['innerHTML'] );
	}

	// Key map from deeply nested old tree

	public function test_key_map_finds_blocks_at_any_depth(): void {
		$level3 = $this->keyed_block( 'core/paragraph', 'level3', '<p>L3 content</p>' );
		$level2 = $this->block( 'core/group', array(), '<div>', array( $level3 ) );
		$level1 = $this->block( 'core/group', array(), '<div>', array( $level2 ) );
		$old = array( $level1 );

		$new = array(
			$this->keyed_block( 'core/paragraph', 'level3', '<p>Template L3</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>L3 content</p>', $result[0]['innerHTML'] );
	}

	// Blocks without blockName (spacer/freeform)

	public function test_blocks_without_attrs_key_pass_through(): void {
		$new = array(
			array(
				'blockName'    => null,
				'attrs'        => array(),
				'innerHTML'    => "\n",
				'innerContent' => array( "\n" ),
				'innerBlocks'  => array(),
			),
		);

		$result = $this->merger->merge( $new, array() );
		$this->assertCount( 1, $result );
		$this->assertSame( "\n", $result[0]['innerHTML'] );
	}

	// Merge preserves block structure for serialization

	public function test_merged_block_has_all_required_keys(): void {
		$new = array( $this->keyed_block( 'core/paragraph', 'test', '<p>New</p>' ) );
		$old = array( $this->keyed_block( 'core/paragraph', 'test', '<p>Old</p>' ) );

		$result = $this->merger->merge( $new, $old );

		$expected_keys = array( 'blockName', 'attrs', 'innerHTML', 'innerContent', 'innerBlocks' );
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result[0], "Missing key: {$key}" );
		}
	}

	// Duplicate key in nested old block: first found at top level wins

	public function test_duplicate_key_across_nesting_levels_top_wins(): void {
		$nested = $this->keyed_block( 'core/paragraph', 'dupe', '<p>Nested</p>' );
		$old = array(
			$this->keyed_block( 'core/paragraph', 'dupe', '<p>Top level</p>' ),
			$this->block( 'core/group', array(), '<div>', array( $nested ) ),
		);

		$new = array( $this->keyed_block( 'core/paragraph', 'dupe', '<p>Template</p>' ) );

		set_error_handler( function () { return true; } );
		$result = $this->merger->merge( $new, $old );
		restore_error_handler();

		$this->assertSame( '<p>Top level</p>', $result[0]['innerHTML'] );
	}

	// Fresh instance does not carry over key map

	public function test_fresh_instance_has_clean_state(): void {
		$old = array( $this->keyed_block( 'core/paragraph', 'stale', '<p>Stale</p>' ) );
		$this->merger->merge( array(), $old );

		$merger2 = new Block_Merger();
		$new = array( $this->keyed_block( 'core/paragraph', 'stale', '<p>Template</p>' ) );
		$result = $merger2->merge( $new, array() );

		$this->assertSame( '<p>Template</p>', $result[0]['innerHTML'] );
	}

	// Multiple sibling containers, each with keyed children

	public function test_multiple_containers_with_keyed_children(): void {
		$child_a = $this->keyed_block( 'core/paragraph', 'child-a', '<p>New A</p>' );
		$child_b = $this->keyed_block( 'core/paragraph', 'child-b', '<p>New B</p>' );
		$new = array(
			$this->block( 'core/group', array(), '<div>', array( $child_a ) ),
			$this->block( 'core/group', array(), '<div>', array( $child_b ) ),
		);

		$old = array(
			$this->keyed_block( 'core/paragraph', 'child-a', '<p>Old A</p>' ),
			$this->keyed_block( 'core/paragraph', 'child-b', '<p>Old B</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Old A</p>', $result[0]['innerBlocks'][0]['innerHTML'] );
		$this->assertSame( '<p>Old B</p>', $result[1]['innerBlocks'][0]['innerHTML'] );
	}

	// Keyed block with no innerBlocks in new but matched old

	public function test_keyed_block_empty_inner_blocks_replaced_by_old(): void {
		$new_block = $this->keyed_block( 'core/group', 'container', '<div>' );
		$new_block['innerBlocks'] = array();

		$old_child = $this->block( 'core/paragraph', array(), '<p>Existing child</p>' );
		$old_block = $this->keyed_block( 'core/group', 'container', '<div>', array(), array( $old_child ) );

		$result = $this->merger->merge( array( $new_block ), array( $old_block ) );
		$this->assertCount( 1, $result[0]['innerBlocks'] );
		$this->assertSame( '<p>Existing child</p>', $result[0]['innerBlocks'][0]['innerHTML'] );
	}

	// Same block name, different keys

	public function test_same_block_name_different_keys_merge_correctly(): void {
		$new = array(
			$this->keyed_block( 'core/paragraph', 'alpha', '<p>New alpha</p>' ),
			$this->keyed_block( 'core/paragraph', 'beta', '<p>New beta</p>' ),
		);
		$old = array(
			$this->keyed_block( 'core/paragraph', 'beta', '<p>Old beta</p>' ),
			$this->keyed_block( 'core/paragraph', 'alpha', '<p>Old alpha</p>' ),
		);

		$result = $this->merger->merge( $new, $old );
		$this->assertSame( '<p>Old alpha</p>', $result[0]['innerHTML'] );
		$this->assertSame( '<p>Old beta</p>', $result[1]['innerHTML'] );
	}
}
