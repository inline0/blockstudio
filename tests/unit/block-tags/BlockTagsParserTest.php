<?php

use Blockstudio\Block_Tags;
use PHPUnit\Framework\TestCase;

class BlockTagsParserTest extends TestCase {

	// -------------------------------------------------------------------------
	// Basic parsing
	// -------------------------------------------------------------------------

	public function test_empty_input(): void {
		$this->assertSame( array(), Block_Tags::parse_inner_blocks( '' ) );
		$this->assertSame( array(), Block_Tags::parse_inner_blocks( '   ' ) );
	}

	public function test_plain_text_produces_no_blocks(): void {
		$this->assertSame( array(), Block_Tags::parse_inner_blocks( 'Just plain text' ) );
	}

	public function test_self_closing_bs_tag(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-separator />' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/separator', $blocks[0]['blockName'] );
	}

	public function test_self_closing_block_element(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/separator" />' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/separator', $blocks[0]['blockName'] );
	}

	public function test_paired_bs_tag(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-paragraph>Hello</bs:core-paragraph>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertStringContainsString( 'Hello', $blocks[0]['innerHTML'] );
	}

	public function test_paired_block_element(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/paragraph">World</block>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertStringContainsString( 'World', $blocks[0]['innerHTML'] );
	}

	// -------------------------------------------------------------------------
	// Syntax equivalence
	// -------------------------------------------------------------------------

	public function test_bs_and_block_produce_identical_output(): void {
		$bs    = Block_Tags::parse_inner_blocks( '<bs:core-paragraph>Same</bs:core-paragraph>' );
		$block = Block_Tags::parse_inner_blocks( '<block name="core/paragraph">Same</block>' );
		$this->assertSame( $bs[0]['innerHTML'], $block[0]['innerHTML'] );
		$this->assertSame( $bs[0]['blockName'], $block[0]['blockName'] );
		$this->assertSame( $bs[0]['attrs'], $block[0]['attrs'] );
	}

	public function test_heading_equivalence(): void {
		$bs    = Block_Tags::parse_inner_blocks( '<bs:core-heading level="3">Title</bs:core-heading>' );
		$block = Block_Tags::parse_inner_blocks( '<block name="core/heading" level="3">Title</block>' );
		$this->assertSame( $bs[0]['innerHTML'], $block[0]['innerHTML'] );
	}

	public function test_separator_equivalence(): void {
		$bs    = Block_Tags::parse_inner_blocks( '<bs:core-separator />' );
		$block = Block_Tags::parse_inner_blocks( '<block name="core/separator" />' );
		$this->assertSame( $bs[0]['innerHTML'], $block[0]['innerHTML'] );
	}

	public function test_image_equivalence(): void {
		$bs    = Block_Tags::parse_inner_blocks( '<bs:core-image url="test.jpg" alt="Alt" />' );
		$block = Block_Tags::parse_inner_blocks( '<block name="core/image" url="test.jpg" alt="Alt" />' );
		$this->assertSame( $bs[0]['innerHTML'], $block[0]['innerHTML'] );
	}

	// -------------------------------------------------------------------------
	// Leaf block builders
	// -------------------------------------------------------------------------

	public function test_paragraph(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-paragraph>Text</bs:core-paragraph>' );
		$this->assertStringContainsString( '<p>Text</p>', $blocks[0]['innerHTML'] );
	}

	public function test_heading_levels(): void {
		for ( $level = 1; $level <= 6; $level++ ) {
			$blocks = Block_Tags::parse_inner_blocks( "<bs:core-heading level=\"{$level}\">H{$level}</bs:core-heading>" );
			$this->assertSame( $level, $blocks[0]['attrs']['level'], "Heading level {$level}" );
			$this->assertStringContainsString( "<h{$level}", $blocks[0]['innerHTML'] );
		}
	}

	public function test_heading_default_level(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-heading>No level</bs:core-heading>' );
		$this->assertSame( 2, $blocks[0]['attrs']['level'] );
		$this->assertStringContainsString( '<h2', $blocks[0]['innerHTML'] );
	}

	public function test_heading_invalid_level_clamps(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-heading level="99">Clamped</bs:core-heading>' );
		$this->assertSame( 6, $blocks[0]['attrs']['level'] );
	}

	public function test_code(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-code>const x = 1;</bs:core-code>' );
		$this->assertStringContainsString( '<code>const x = 1;</code>', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( '<pre class="wp-block-code">', $blocks[0]['innerHTML'] );
	}

	public function test_preformatted(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-preformatted>Pre text</bs:core-preformatted>' );
		$this->assertStringContainsString( '<pre class="wp-block-preformatted">Pre text</pre>', $blocks[0]['innerHTML'] );
	}

	public function test_verse(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-verse>Roses are red</bs:core-verse>' );
		$this->assertStringContainsString( '<pre class="wp-block-verse">', $blocks[0]['innerHTML'] );
	}

	public function test_separator(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-separator />' );
		$this->assertStringContainsString( '<hr', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-separator', $blocks[0]['innerHTML'] );
	}

	public function test_spacer(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-spacer height="50px" />' );
		$this->assertStringContainsString( 'height:50px', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-spacer', $blocks[0]['innerHTML'] );
	}

	public function test_image(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/image" url="photo.jpg" alt="Photo" />' );
		$this->assertStringContainsString( 'src="photo.jpg"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'alt="Photo"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-image', $blocks[0]['innerHTML'] );
	}

	public function test_image_src_to_url_remap(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/image" src="photo.jpg" />' );
		$this->assertSame( 'photo.jpg', $blocks[0]['attrs']['url'] );
		$this->assertArrayNotHasKey( 'src', $blocks[0]['attrs'] );
	}

	public function test_button(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/button" url="https://example.com">Click</block>' );
		$this->assertStringContainsString( 'href="https://example.com"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'Click', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-button', $blocks[0]['innerHTML'] );
	}

	public function test_button_href_to_url_remap(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/button" href="https://example.com">Click</block>' );
		$this->assertSame( 'https://example.com', $blocks[0]['attrs']['url'] );
		$this->assertArrayNotHasKey( 'href', $blocks[0]['attrs'] );
	}

	public function test_embed(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/embed" url="https://youtube.com" providerNameSlug="youtube" />' );
		$this->assertStringContainsString( 'wp-block-embed', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'is-provider-youtube', $blocks[0]['innerHTML'] );
	}

	public function test_audio(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-audio src="song.mp3" />' );
		$this->assertStringContainsString( 'src="song.mp3"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-audio', $blocks[0]['innerHTML'] );
	}

	public function test_video(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-video src="clip.mp4" />' );
		$this->assertStringContainsString( 'src="clip.mp4"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-video', $blocks[0]['innerHTML'] );
	}

	public function test_table(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/table"><tr><td>Cell</td></tr></block>' );
		$this->assertStringContainsString( 'wp-block-table', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( '<td>Cell</td>', $blocks[0]['innerHTML'] );
	}

	public function test_social_link(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/social-link" service="github" url="https://github.com" />' );
		$this->assertSame( 'core/social-link', $blocks[0]['blockName'] );
		$this->assertSame( 'github', $blocks[0]['attrs']['service'] );
		$this->assertSame( '', $blocks[0]['innerHTML'] );
	}

	public function test_more(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-more />' );
		$this->assertStringContainsString( '<!--more-->', $blocks[0]['innerHTML'] );
	}

	public function test_nextpage(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-nextpage />' );
		$this->assertStringContainsString( '<!--nextpage-->', $blocks[0]['innerHTML'] );
	}

	// -------------------------------------------------------------------------
	// List block
	// -------------------------------------------------------------------------

	public function test_list_with_items(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/list"><li>One</li><li>Two</li><li>Three</li></block>' );
		$this->assertSame( 'core/list', $blocks[0]['blockName'] );
		$this->assertCount( 3, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/list-item', $blocks[0]['innerBlocks'][0]['blockName'] );
		$this->assertStringContainsString( 'One', $blocks[0]['innerBlocks'][0]['innerHTML'] );
	}

	public function test_ordered_list(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/list" ordered="true"><li>First</li></block>' );
		$this->assertTrue( $blocks[0]['attrs']['ordered'] );
	}

	// -------------------------------------------------------------------------
	// Container blocks
	// -------------------------------------------------------------------------

	public function test_group_with_children(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/group"><block name="core/paragraph">Inside</block></block>'
		);
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['blockName'] );
		$this->assertStringContainsString( 'wp-block-group', $blocks[0]['innerHTML'] );
	}

	public function test_group_inner_content_has_wrapper(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/group"><block name="core/paragraph">A</block></block>'
		);
		$first = $blocks[0]['innerContent'][0];
		$last  = end( $blocks[0]['innerContent'] );
		$this->assertStringContainsString( '<div', $first );
		$this->assertStringContainsString( '</div>', $last );
	}

	public function test_columns_with_columns(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/columns">' .
				'<block name="core/column"><block name="core/paragraph">L</block></block>' .
				'<block name="core/column"><block name="core/paragraph">R</block></block>' .
			'</block>'
		);
		$this->assertSame( 'core/columns', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/column', $blocks[0]['innerBlocks'][0]['blockName'] );
		$this->assertCount( 1, $blocks[0]['innerBlocks'][0]['innerBlocks'] );
		$this->assertStringContainsString( 'wp-block-columns', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-column', $blocks[0]['innerBlocks'][0]['innerHTML'] );
	}

	public function test_buttons_container(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/buttons">' .
				'<block name="core/button" url="https://a.com">A</block>' .
				'<block name="core/button" url="https://b.com">B</block>' .
			'</block>'
		);
		$this->assertSame( 'core/buttons', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
		$this->assertStringContainsString( 'wp-block-buttons', $blocks[0]['innerHTML'] );
	}

	public function test_social_links_container(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/social-links">' .
				'<block name="core/social-link" service="twitter" url="https://twitter.com" />' .
				'<block name="core/social-link" service="github" url="https://github.com" />' .
			'</block>'
		);
		$this->assertSame( 'core/social-links', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
		$this->assertStringContainsString( 'wp-block-social-links', $blocks[0]['innerHTML'] );
	}

	public function test_quote_container(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/quote"><block name="core/paragraph">Quoted</block></block>'
		);
		$this->assertSame( 'core/quote', $blocks[0]['blockName'] );
		$this->assertStringContainsString( 'wp-block-quote', $blocks[0]['innerHTML'] );
	}

	public function test_inner_content_has_null_placeholders(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/group">' .
				'<block name="core/paragraph">A</block>' .
				'<block name="core/paragraph">B</block>' .
			'</block>'
		);
		$inner_content = $blocks[0]['innerContent'];
		$nulls         = array_filter( $inner_content, fn( $v ) => null === $v );
		$this->assertCount( 2, $nulls );
	}

	// -------------------------------------------------------------------------
	// Deep nesting
	// -------------------------------------------------------------------------

	public function test_3_levels_deep(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/group">' .
				'<block name="core/columns">' .
					'<block name="core/column">' .
						'<block name="core/paragraph">Deep</block>' .
					'</block>' .
				'</block>' .
			'</block>'
		);
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$columns = $blocks[0]['innerBlocks'][0];
		$this->assertSame( 'core/columns', $columns['blockName'] );
		$column = $columns['innerBlocks'][0];
		$this->assertSame( 'core/column', $column['blockName'] );
		$para = $column['innerBlocks'][0];
		$this->assertSame( 'core/paragraph', $para['blockName'] );
		$this->assertStringContainsString( 'Deep', $para['innerHTML'] );
	}

	public function test_5_levels_deep(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-group>' .
				'<bs:core-group>' .
					'<bs:core-group>' .
						'<bs:core-group>' .
							'<bs:core-paragraph>Level 5</bs:core-paragraph>' .
						'</bs:core-group>' .
					'</bs:core-group>' .
				'</bs:core-group>' .
			'</bs:core-group>'
		);
		$b = $blocks[0];
		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertSame( 'core/group', $b['blockName'], "Level {$i} is group" );
			$this->assertCount( 1, $b['innerBlocks'], "Level {$i} has 1 child" );
			$b = $b['innerBlocks'][0];
		}
		$this->assertSame( 'core/group', $b['blockName'] );
		$para = $b['innerBlocks'][0];
		$this->assertSame( 'core/paragraph', $para['blockName'] );
		$this->assertStringContainsString( 'Level 5', $para['innerHTML'] );
	}

	public function test_deep_mixed_syntax(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-group>' .
				'<block name="core/columns">' .
					'<bs:core-column>' .
						'<block name="core/paragraph">Mixed deep</block>' .
					'</bs:core-column>' .
				'</block>' .
			'</bs:core-group>'
		);
		$group   = $blocks[0];
		$columns = $group['innerBlocks'][0];
		$column  = $columns['innerBlocks'][0];
		$para    = $column['innerBlocks'][0];
		$this->assertSame( 'core/group', $group['blockName'] );
		$this->assertSame( 'core/columns', $columns['blockName'] );
		$this->assertSame( 'core/column', $column['blockName'] );
		$this->assertSame( 'core/paragraph', $para['blockName'] );
		$this->assertStringContainsString( 'Mixed deep', $para['innerHTML'] );
	}

	// -------------------------------------------------------------------------
	// Mixed syntax
	// -------------------------------------------------------------------------

	public function test_bs_inside_block(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="core/group"><bs:core-paragraph>Inside</bs:core-paragraph></block>'
		);
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	public function test_block_inside_bs(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-group><block name="core/paragraph">Inside</block></bs:core-group>'
		);
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	public function test_alternating_syntaxes(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<bs:core-paragraph>First</bs:core-paragraph>' .
			'<block name="core/paragraph">Second</block>' .
			'<bs:core-paragraph>Third</bs:core-paragraph>' .
			'<block name="core/paragraph">Fourth</block>'
		);
		$this->assertCount( 4, $blocks );
		for ( $i = 0; $i < 4; $i++ ) {
			$this->assertSame( 'core/paragraph', $blocks[ $i ]['blockName'] );
		}
	}

	// -------------------------------------------------------------------------
	// Multiple siblings
	// -------------------------------------------------------------------------

	public function test_many_siblings(): void {
		$html = '';
		for ( $i = 0; $i < 20; $i++ ) {
			$html .= "<bs:core-paragraph>Block {$i}</bs:core-paragraph>";
		}
		$blocks = Block_Tags::parse_inner_blocks( $html );
		$this->assertCount( 20, $blocks );
	}

	public function test_wide_container(): void {
		$inner = '';
		for ( $i = 0; $i < 10; $i++ ) {
			$inner .= '<block name="core/column"><block name="core/paragraph">Col ' . $i . '</block></block>';
		}
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/columns">' . $inner . '</block>' );
		$this->assertCount( 10, $blocks[0]['innerBlocks'] );
	}

	// -------------------------------------------------------------------------
	// Attribute parsing
	// -------------------------------------------------------------------------

	public function test_numeric_attribute(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-spacer height="100" />' );
		$this->assertSame( 100, $blocks[0]['attrs']['height'] );
	}

	public function test_quoted_string_attribute(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-paragraph align="center">Text</bs:core-paragraph>' );
		$this->assertSame( 'center', $blocks[0]['attrs']['align'] );
	}

	public function test_multiple_attributes(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/image" url="a.jpg" alt="Alt text" width="200" />' );
		$this->assertSame( 'a.jpg', $blocks[0]['attrs']['url'] );
		$this->assertSame( 'Alt text', $blocks[0]['attrs']['alt'] );
		$this->assertSame( 200, $blocks[0]['attrs']['width'] );
	}

	// -------------------------------------------------------------------------
	// Edge cases
	// -------------------------------------------------------------------------

	public function test_tag_without_hyphen_skipped(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:nohyphen />' );
		$this->assertCount( 0, $blocks );
	}

	public function test_unknown_block_uses_fallback(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="custom/my-block">Content</block>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'custom/my-block', $blocks[0]['blockName'] );
	}

	public function test_unknown_block_with_children(): void {
		$blocks = Block_Tags::parse_inner_blocks(
			'<block name="custom/wrapper"><block name="core/paragraph">Child</block></block>'
		);
		$this->assertSame( 'custom/wrapper', $blocks[0]['blockName'] );
		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	public function test_unclosed_tag_skipped(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-paragraph>No close tag' );
		$this->assertCount( 0, $blocks );
	}

	public function test_attributes_with_special_chars(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/paragraph" title="He said &quot;hi&quot;">Content</block>' );
		$this->assertCount( 1, $blocks );
	}

	public function test_self_closing_with_no_space_before_slash(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<bs:core-separator/>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/separator', $blocks[0]['blockName'] );
	}

	// -------------------------------------------------------------------------
	// build_block_array
	// -------------------------------------------------------------------------

	public function test_build_block_array_returns_correct_structure(): void {
		$block = Block_Tags::build_block_array( 'core/paragraph', array(), 'Hello' );
		$this->assertArrayHasKey( 'blockName', $block );
		$this->assertArrayHasKey( 'attrs', $block );
		$this->assertArrayHasKey( 'innerBlocks', $block );
		$this->assertArrayHasKey( 'innerHTML', $block );
		$this->assertArrayHasKey( 'innerContent', $block );
	}

	public function test_build_block_array_fallback(): void {
		$block = Block_Tags::build_block_array( 'unknown/block', array( 'foo' => 'bar' ), '' );
		$this->assertSame( 'unknown/block', $block['blockName'] );
		$this->assertSame( 'bar', $block['attrs']['foo'] );
	}

	// -------------------------------------------------------------------------
	// Container block HTML wrappers
	// -------------------------------------------------------------------------

	public function test_group_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/group', array(), '<bs:core-paragraph>In</bs:core-paragraph>' );
		$this->assertStringContainsString( '<div class="wp-block-group">', $block['innerHTML'] );
		$this->assertStringContainsString( '</div>', $block['innerHTML'] );
	}

	public function test_group_layout_flex(): void {
		$block = Block_Tags::build_block_array( 'core/group', array( 'layout' => '{"type":"flex"}' ), '' );
		$this->assertStringContainsString( 'is-layout-flex', $block['innerHTML'] );
	}

	public function test_group_layout_vertical(): void {
		$block = Block_Tags::build_block_array( 'core/group', array( 'layout' => '{"type":"flex","orientation":"vertical"}' ), '' );
		$this->assertStringContainsString( 'is-vertical', $block['innerHTML'] );
	}

	public function test_columns_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/columns', array(), '' );
		$this->assertStringContainsString( 'wp-block-columns', $block['innerHTML'] );
	}

	public function test_column_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/column', array(), '' );
		$this->assertStringContainsString( 'wp-block-column', $block['innerHTML'] );
	}

	public function test_buttons_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/buttons', array(), '' );
		$this->assertStringContainsString( 'wp-block-buttons', $block['innerHTML'] );
	}

	public function test_quote_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/quote', array(), '<bs:core-paragraph>Quoted</bs:core-paragraph>' );
		$this->assertStringContainsString( '<blockquote class="wp-block-quote">', $block['innerHTML'] );
	}

	public function test_details_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/details', array(), '<summary>Title</summary>' );
		$this->assertStringContainsString( 'wp-block-details', $block['innerHTML'] );
	}

	public function test_social_links_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/social-links', array(), '' );
		$this->assertStringContainsString( 'wp-block-social-links', $block['innerHTML'] );
	}

	public function test_cover_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/cover', array( 'url' => 'bg.jpg' ), '' );
		$this->assertStringContainsString( 'wp-block-cover', $block['innerHTML'] );
	}

	public function test_accordion_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/accordion', array(), '' );
		$this->assertStringContainsString( 'wp-block-accordion', $block['innerHTML'] );
	}

	public function test_query_html_wrapper(): void {
		$block = Block_Tags::build_block_array( 'core/query', array(), '' );
		$this->assertStringContainsString( 'wp-block-query', $block['innerHTML'] );
	}

	// -------------------------------------------------------------------------
	// Leaf block HTML content
	// -------------------------------------------------------------------------

	public function test_paragraph_html(): void {
		$block = Block_Tags::build_block_array( 'core/paragraph', array(), 'Test' );
		$this->assertSame( '<p>Test</p>', $block['innerHTML'] );
		$this->assertSame( array( '<p>Test</p>' ), $block['innerContent'] );
	}

	public function test_heading_h1(): void {
		$block = Block_Tags::build_block_array( 'core/heading', array( 'level' => 1 ), 'Title' );
		$this->assertStringContainsString( '<h1 class="wp-block-heading">Title</h1>', $block['innerHTML'] );
	}

	public function test_heading_h6(): void {
		$block = Block_Tags::build_block_array( 'core/heading', array( 'level' => 6 ), 'Small' );
		$this->assertStringContainsString( '<h6', $block['innerHTML'] );
	}

	public function test_code_html(): void {
		$block = Block_Tags::build_block_array( 'core/code', array(), 'echo 1;' );
		$this->assertSame( '<pre class="wp-block-code"><code>echo 1;</code></pre>', $block['innerHTML'] );
	}

	public function test_separator_html(): void {
		$block = Block_Tags::build_block_array( 'core/separator', array(), '' );
		$this->assertStringContainsString( '<hr', $block['innerHTML'] );
		$this->assertStringContainsString( 'has-alpha-channel-opacity', $block['innerHTML'] );
	}

	public function test_image_html_structure(): void {
		$block = Block_Tags::build_block_array( 'core/image', array( 'url' => 'a.jpg', 'alt' => 'Alt' ), '' );
		$this->assertStringContainsString( '<figure class="wp-block-image">', $block['innerHTML'] );
		$this->assertStringContainsString( '<img', $block['innerHTML'] );
		$this->assertStringContainsString( 'src="a.jpg"', $block['innerHTML'] );
		$this->assertStringContainsString( 'alt="Alt"', $block['innerHTML'] );
	}

	public function test_button_html_structure(): void {
		$block = Block_Tags::build_block_array( 'core/button', array( 'url' => 'https://a.com' ), 'Click' );
		$this->assertStringContainsString( 'wp-block-button', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-element-button', $block['innerHTML'] );
		$this->assertStringContainsString( 'href="https://a.com"', $block['innerHTML'] );
		$this->assertStringContainsString( 'Click', $block['innerHTML'] );
	}

	public function test_embed_html_structure(): void {
		$block = Block_Tags::build_block_array( 'core/embed', array( 'url' => 'https://yt.com', 'providerNameSlug' => 'youtube' ), '' );
		$this->assertStringContainsString( 'wp-block-embed', $block['innerHTML'] );
		$this->assertStringContainsString( 'is-provider-youtube', $block['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-embed-youtube', $block['innerHTML'] );
	}

	public function test_audio_html_structure(): void {
		$block = Block_Tags::build_block_array( 'core/audio', array( 'src' => 'song.mp3' ), '' );
		$this->assertStringContainsString( 'wp-block-audio', $block['innerHTML'] );
		$this->assertStringContainsString( '<audio', $block['innerHTML'] );
		$this->assertStringContainsString( 'src="song.mp3"', $block['innerHTML'] );
	}

	public function test_video_html_structure(): void {
		$block = Block_Tags::build_block_array( 'core/video', array( 'src' => 'clip.mp4' ), '' );
		$this->assertStringContainsString( 'wp-block-video', $block['innerHTML'] );
		$this->assertStringContainsString( '<video', $block['innerHTML'] );
	}

	public function test_more_with_custom_text(): void {
		$block = Block_Tags::build_block_array( 'core/more', array( 'customText' => 'Read on' ), '' );
		$this->assertStringContainsString( '<!--more Read on-->', $block['innerHTML'] );
	}

	public function test_more_with_no_teaser(): void {
		$block = Block_Tags::build_block_array( 'core/more', array( 'noTeaser' => true ), '' );
		$this->assertStringContainsString( '<!--noteaser-->', $block['innerHTML'] );
	}

	public function test_list_inner_blocks_are_list_items(): void {
		$block = Block_Tags::build_block_array( 'core/list', array(), '<li>A</li><li>B</li><li>C</li>' );
		$this->assertCount( 3, $block['innerBlocks'] );
		foreach ( $block['innerBlocks'] as $item ) {
			$this->assertSame( 'core/list-item', $item['blockName'] );
			$this->assertStringContainsString( '<li>', $item['innerHTML'] );
		}
	}

	public function test_pullquote_with_citation(): void {
		$block = Block_Tags::build_block_array( 'core/pullquote', array(), 'Quote text<cite>Author</cite>' );
		$this->assertStringContainsString( 'wp-block-pullquote', $block['innerHTML'] );
		$this->assertStringContainsString( '<cite>', $block['innerHTML'] );
		$this->assertSame( 'Author', $block['attrs']['citation'] );
	}

	public function test_social_link_is_empty_html(): void {
		$block = Block_Tags::build_block_array( 'core/social-link', array( 'service' => 'github' ), '' );
		$this->assertSame( '', $block['innerHTML'] );
		$this->assertSame( 'github', $block['attrs']['service'] );
	}

	// -------------------------------------------------------------------------
	// Syntax equivalence for ALL block types
	// -------------------------------------------------------------------------

	#[\PHPUnit\Framework\Attributes\DataProvider( 'leafBlockProvider' )]
	public function test_leaf_block_equivalence( string $block_name, string $bs_name, string $content, array $attrs ): void {
		$attr_str = '';
		foreach ( $attrs as $k => $v ) {
			$attr_str .= " {$k}=\"{$v}\"";
		}

		$bs_tag    = '' === $content
			? "<bs:{$bs_name}{$attr_str} />"
			: "<bs:{$bs_name}{$attr_str}>{$content}</bs:{$bs_name}>";
		$block_tag = '' === $content
			? "<block name=\"{$block_name}\"{$attr_str} />"
			: "<block name=\"{$block_name}\"{$attr_str}>{$content}</block>";

		$bs_blocks    = Block_Tags::parse_inner_blocks( $bs_tag );
		$block_blocks = Block_Tags::parse_inner_blocks( $block_tag );

		$this->assertCount( 1, $bs_blocks, "{$block_name} bs: count" );
		$this->assertCount( 1, $block_blocks, "{$block_name} block count" );
		$this->assertSame( $bs_blocks[0]['innerHTML'], $block_blocks[0]['innerHTML'], "{$block_name} innerHTML match" );
		$this->assertSame( $bs_blocks[0]['blockName'], $block_blocks[0]['blockName'], "{$block_name} blockName match" );
	}

	public static function leafBlockProvider(): array {
		return array(
			'paragraph'    => array( 'core/paragraph', 'core-paragraph', 'Text', array() ),
			'heading'      => array( 'core/heading', 'core-heading', 'Title', array( 'level' => '2' ) ),
			'code'         => array( 'core/code', 'core-code', 'x=1', array() ),
			'preformatted' => array( 'core/preformatted', 'core-preformatted', 'Pre', array() ),
			'verse'        => array( 'core/verse', 'core-verse', 'Roses', array() ),
			'separator'    => array( 'core/separator', 'core-separator', '', array() ),
			'spacer'       => array( 'core/spacer', 'core-spacer', '', array( 'height' => '50px' ) ),
			'image'        => array( 'core/image', 'core-image', '', array( 'url' => 'a.jpg', 'alt' => 'A' ) ),
			'audio'        => array( 'core/audio', 'core-audio', '', array( 'src' => 's.mp3' ) ),
			'video'        => array( 'core/video', 'core-video', '', array( 'src' => 'v.mp4' ) ),
		);
	}

	// -------------------------------------------------------------------------
	// Dynamic blocks (fallback path)
	// -------------------------------------------------------------------------

	public function test_dynamic_block_produces_valid_array(): void {
		$block = Block_Tags::build_block_array( 'core/archives', array(), '' );
		$this->assertSame( 'core/archives', $block['blockName'] );
		$this->assertArrayHasKey( 'attrs', $block );
		$this->assertArrayHasKey( 'innerBlocks', $block );
		$this->assertArrayHasKey( 'innerHTML', $block );
		$this->assertArrayHasKey( 'innerContent', $block );
	}

	public function test_dynamic_block_preserves_attrs(): void {
		$block = Block_Tags::build_block_array( 'core/latest-posts', array( 'postsToShow' => 5 ), '' );
		$this->assertSame( 'core/latest-posts', $block['blockName'] );
		$this->assertSame( 5, $block['attrs']['postsToShow'] );
	}

	public function test_dynamic_container_with_children(): void {
		$block = Block_Tags::build_block_array(
			'core/post-template',
			array(),
			'<block name="core/post-title" /><block name="core/post-date" />'
		);
		$this->assertSame( 'core/post-template', $block['blockName'] );
		$this->assertCount( 2, $block['innerBlocks'] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'dynamicBlockProvider' )]
	public function test_dynamic_blocks_via_bs_syntax( string $block_name, string $bs_name ): void {
		$blocks = Block_Tags::parse_inner_blocks( "<bs:{$bs_name} />" );
		$this->assertCount( 1, $blocks );
		$this->assertSame( $block_name, $blocks[0]['blockName'] );
	}

	public static function dynamicBlockProvider(): array {
		return array(
			'archives'       => array( 'core/archives', 'core-archives' ),
			'calendar'       => array( 'core/calendar', 'core-calendar' ),
			'categories'     => array( 'core/categories', 'core-categories' ),
			'latest-posts'   => array( 'core/latest-posts', 'core-latest-posts' ),
			'search'         => array( 'core/search', 'core-search' ),
			'site-title'     => array( 'core/site-title', 'core-site-title' ),
			'site-logo'      => array( 'core/site-logo', 'core-site-logo' ),
			'loginout'       => array( 'core/loginout', 'core-loginout' ),
			'post-title'     => array( 'core/post-title', 'core-post-title' ),
			'post-date'      => array( 'core/post-date', 'core-post-date' ),
			'post-excerpt'   => array( 'core/post-excerpt', 'core-post-excerpt' ),
			'post-content'   => array( 'core/post-content', 'core-post-content' ),
			'navigation'     => array( 'core/navigation', 'core-navigation' ),
			'tag-cloud'      => array( 'core/tag-cloud', 'core-tag-cloud' ),
			'rss'            => array( 'core/rss', 'core-rss' ),
			'shortcode'      => array( 'core/shortcode', 'core-shortcode' ),
			'file'           => array( 'core/file', 'core-file' ),
		);
	}

	// -------------------------------------------------------------------------
	// All container block wrappers
	// -------------------------------------------------------------------------

	#[\PHPUnit\Framework\Attributes\DataProvider( 'containerBlockProvider' )]
	public function test_container_block_has_inner_blocks( string $block_name ): void {
		$block = Block_Tags::build_block_array(
			$block_name,
			array(),
			'<block name="core/paragraph">Child</block>'
		);
		$this->assertSame( $block_name, $block['blockName'] );
		$this->assertCount( 1, $block['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $block['innerBlocks'][0]['blockName'] );
	}

	public static function containerBlockProvider(): array {
		return array(
			'group'           => array( 'core/group' ),
			'columns'         => array( 'core/columns' ),
			'column'          => array( 'core/column' ),
			'buttons'         => array( 'core/buttons' ),
			'quote'           => array( 'core/quote' ),
			'cover'           => array( 'core/cover' ),
			'details'         => array( 'core/details' ),
			'social-links'    => array( 'core/social-links' ),
			'query'           => array( 'core/query' ),
			'comments'        => array( 'core/comments' ),
			'accordion'       => array( 'core/accordion' ),
			'accordion-item'  => array( 'core/accordion-item' ),
			'accordion-panel' => array( 'core/accordion-panel' ),
		);
	}

	// -------------------------------------------------------------------------
	// Boolean attribute conversion
	// -------------------------------------------------------------------------

	public function test_boolean_true_attribute(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/paragraph" active="true" />' );
		$this->assertSame( true, $blocks[0]['attrs']['active'] );
	}

	public function test_boolean_false_attribute(): void {
		$blocks = Block_Tags::parse_inner_blocks( '<block name="core/paragraph" active="false" />' );
		$this->assertSame( false, $blocks[0]['attrs']['active'] );
	}

	// -------------------------------------------------------------------------
	// Key remapping
	// -------------------------------------------------------------------------

	public function test_key_remapped_to_blockstudio_key(): void {
		$block = Block_Tags::build_block_array( 'core/heading', array( 'key' => 'title', 'level' => 2 ), 'Title' );
		$this->assertSame( 'title', $block['attrs']['__BLOCKSTUDIO_KEY'] );
		$this->assertArrayNotHasKey( 'key', $block['attrs'] );
	}

	// -------------------------------------------------------------------------
	// Stress test
	// -------------------------------------------------------------------------

	public function test_100_nested_groups(): void {
		$html = '';
		for ( $i = 0; $i < 100; $i++ ) {
			$html .= '<bs:core-group>';
		}
		$html .= '<bs:core-paragraph>Bottom</bs:core-paragraph>';
		for ( $i = 0; $i < 100; $i++ ) {
			$html .= '</bs:core-group>';
		}

		$blocks = Block_Tags::parse_inner_blocks( $html );
		$this->assertCount( 1, $blocks );

		$b = $blocks[0];
		for ( $i = 0; $i < 99; $i++ ) {
			$this->assertSame( 'core/group', $b['blockName'] );
			$this->assertCount( 1, $b['innerBlocks'] );
			$b = $b['innerBlocks'][0];
		}
		$this->assertSame( 'core/group', $b['blockName'] );
		$para = $b['innerBlocks'][0];
		$this->assertSame( 'core/paragraph', $para['blockName'] );
		$this->assertStringContainsString( 'Bottom', $para['innerHTML'] );
	}
}
