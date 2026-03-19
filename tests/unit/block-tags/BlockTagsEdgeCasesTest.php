<?php

use Blockstudio\Block_Tags;
use PHPUnit\Framework\TestCase;

class BlockTagsEdgeCasesTest extends TestCase {

	// Nested same-name tags

	public function test_nested_groups_same_name(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-group><bs:core-group><bs:core-paragraph>Deep</bs:core-paragraph></bs:core-group></bs:core-group>'
		);
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/group', $blocks[0]['innerBlocks'][0]['blockName'] );
		$this->assertCount( 1, $blocks[0]['innerBlocks'][0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['blockName'] );
	}

	public function test_nested_groups_block_syntax(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/group"><block name="core/group"><block name="core/paragraph">Deep</block></block></block>'
		);
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/group', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	public function test_three_level_same_name_nesting(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-group>'
			. '<bs:core-group>'
			. '<bs:core-group>'
			. '<bs:core-paragraph>Level 3</bs:core-paragraph>'
			. '</bs:core-group>'
			. '</bs:core-group>'
			. '</bs:core-group>'
		);
		$level1 = $blocks[0];
		$level2 = $level1['innerBlocks'][0];
		$level3 = $level2['innerBlocks'][0];
		$this->assertCount( 1, $level3['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $level3['innerBlocks'][0]['blockName'] );
	}

	public function test_sibling_same_name_blocks(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-group><bs:core-paragraph>A</bs:core-paragraph></bs:core-group>'
			. '<bs:core-group><bs:core-paragraph>B</bs:core-paragraph></bs:core-group>'
		);
		$this->assertCount( 2, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertSame( 'core/group', $blocks[1]['blockName'] );
	}

	// Malformed input

	public function test_unclosed_tag_produces_no_blocks(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-paragraph' );
		$this->assertEmpty( $blocks );
	}

	public function test_empty_block_name_skipped(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="" />' );
		$this->assertEmpty( $blocks );
	}

	public function test_block_without_name_attribute_skipped(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block />' );
		$this->assertEmpty( $blocks );
	}

	public function test_bs_tag_without_hyphen_skipped(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:invalid />' );
		$this->assertEmpty( $blocks );
	}

	// Attribute edge cases

	public function test_attribute_with_single_quotes(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			"<bs:core-heading level='3'>Title</bs:core-heading>"
		);
		$this->assertCount( 1, $blocks );
		$this->assertSame( 3, $blocks[0]['attrs']['level'] );
	}

	public function test_boolean_true_attribute(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-list ordered="true"><li>Item</li></bs:core-list>'
		);
		$this->assertCount( 1, $blocks );
		$this->assertTrue( $blocks[0]['attrs']['ordered'] );
	}

	public function test_boolean_false_attribute(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-list ordered="false"><li>Item</li></bs:core-list>'
		);
		$this->assertCount( 1, $blocks );
		$this->assertFalse( $blocks[0]['attrs']['ordered'] );
	}

	public function test_numeric_string_converted_to_int(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-heading level="4">Title</bs:core-heading>'
		);
		$this->assertSame( 4, $blocks[0]['attrs']['level'] );
	}

	public function test_float_attribute(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/separator" opacity="0.5" />'
		);
		$this->assertSame( 0.5, $blocks[0]['attrs']['opacity'] );
	}

	public function test_json_array_attribute(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/group" layout=\'{"type":"flex"}\' />'
		);
		$this->assertIsArray( $blocks[0]['attrs']['layout'] );
		$this->assertSame( 'flex', $blocks[0]['attrs']['layout']['type'] );
	}

	public function test_string_true_not_case_insensitive(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/separator" custom="True" />'
		);
		$this->assertSame( 'True', $blocks[0]['attrs']['custom'] );
	}

	// Mixed syntax nesting

	public function test_bs_inside_block_inside_bs(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-group>'
			. '<block name="core/columns">'
			. '<bs:core-column><bs:core-paragraph>Deep</bs:core-paragraph></bs:core-column>'
			. '</block>'
			. '</bs:core-group>'
		);
		$this->assertCount( 1, $blocks );
		$group = $blocks[0];
		$this->assertSame( 'core/group', $group['blockName'] );
		$columns = $group['innerBlocks'][0];
		$this->assertSame( 'core/columns', $columns['blockName'] );
		$column = $columns['innerBlocks'][0];
		$this->assertSame( 'core/column', $column['blockName'] );
	}

	// Consistent output for identical blocks

	public function test_identical_blocks_produce_same_output(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-separator /><bs:core-separator />'
		);
		$this->assertCount( 2, $blocks );
		$this->assertSame( $blocks[0]['innerHTML'], $blocks[1]['innerHTML'] );
		$this->assertSame( $blocks[0]['blockName'], $blocks[1]['blockName'] );
	}

	// parse_all_elements edge cases

	public function test_parse_all_elements_preserves_text_between_blocks(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<h1>Title</h1>Some text in between<p>Paragraph</p>'
		);
		$this->assertCount( 3, $blocks );
		$this->assertSame( 'core/heading', $blocks[0]['blockName'] );
		$this->assertSame( 'core/paragraph', $blocks[1]['blockName'] );
		$this->assertStringContainsString( 'Some text in between', $blocks[1]['innerHTML'] );
	}

	public function test_parse_all_elements_handles_nested_divs_deeply(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<div><div><div><p>Deep</p></div></div></div>'
		);
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
	}

	public function test_parse_all_elements_mixed_block_tags_and_html(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<p>Before</p>'
			. '<block name="core/separator" />'
			. '<div><bs:core-paragraph>Inside div</bs:core-paragraph></div>'
			. '<p>After</p>'
		);
		$this->assertCount( 4, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertSame( 'core/separator', $blocks[1]['blockName'] );
		$this->assertSame( 'core/group', $blocks[2]['blockName'] );
		$this->assertSame( 'core/paragraph', $blocks[3]['blockName'] );
	}

	public function test_parse_all_elements_empty_div(): void {
		$blocks = Block_Tags::parse_all_elements( '<div></div>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertEmpty( $blocks[0]['innerBlocks'] );
	}
}
