<?php

use Blockstudio\Block_Tags;
use PHPUnit\Framework\TestCase;

class BlockTagsHtmlParsingTest extends TestCase {

	// -------------------------------------------------------------------------
	// Raw HTML element parsing (pages/patterns)
	// -------------------------------------------------------------------------

	public function test_paragraph_from_p_tag(): void {
		$blocks = Block_Tags::parse_all_elements( '<p>Hello world</p>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertStringContainsString( 'Hello world', $blocks[0]['innerHTML'] );
	}

	public function test_heading_from_h1(): void {
		$blocks = Block_Tags::parse_all_elements( '<h1>Title</h1>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/heading', $blocks[0]['blockName'] );
		$this->assertSame( 1, $blocks[0]['attrs']['level'] );
	}

	public function test_heading_from_h3(): void {
		$blocks = Block_Tags::parse_all_elements( '<h3>Subtitle</h3>' );
		$this->assertSame( 3, $blocks[0]['attrs']['level'] );
	}

	public function test_heading_all_levels(): void {
		for ( $level = 1; $level <= 6; $level++ ) {
			$blocks = Block_Tags::parse_all_elements( "<h{$level}>H{$level}</h{$level}>" );
			$this->assertSame( $level, $blocks[0]['attrs']['level'], "h{$level} level" );
		}
	}

	public function test_div_becomes_group(): void {
		$blocks = Block_Tags::parse_all_elements( '<div><p>Inside</p></div>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
	}

	public function test_section_becomes_group(): void {
		$blocks = Block_Tags::parse_all_elements( '<section><p>Inside</p></section>' );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
	}

	public function test_blockquote_becomes_quote(): void {
		$blocks = Block_Tags::parse_all_elements( '<blockquote><p>Quoted</p></blockquote>' );
		$this->assertSame( 'core/quote', $blocks[0]['blockName'] );
	}

	public function test_hr_becomes_separator(): void {
		$blocks = Block_Tags::parse_all_elements( '<hr />' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/separator', $blocks[0]['blockName'] );
	}

	public function test_hr_without_slash(): void {
		$blocks = Block_Tags::parse_all_elements( '<hr>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/separator', $blocks[0]['blockName'] );
	}

	public function test_img_becomes_image(): void {
		$blocks = Block_Tags::parse_all_elements( '<img src="photo.jpg" alt="Photo" />' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/image', $blocks[0]['blockName'] );
		$this->assertSame( 'photo.jpg', $blocks[0]['attrs']['url'] );
		$this->assertSame( 'Photo', $blocks[0]['attrs']['alt'] );
	}

	public function test_pre_becomes_preformatted(): void {
		$blocks = Block_Tags::parse_all_elements( '<pre>Preformatted text</pre>' );
		$this->assertSame( 'core/preformatted', $blocks[0]['blockName'] );
	}

	public function test_code_becomes_code_block(): void {
		$blocks = Block_Tags::parse_all_elements( '<code>const x = 1;</code>' );
		$this->assertSame( 'core/code', $blocks[0]['blockName'] );
	}

	public function test_ul_becomes_list(): void {
		$blocks = Block_Tags::parse_all_elements( '<ul><li>One</li><li>Two</li></ul>' );
		$this->assertSame( 'core/list', $blocks[0]['blockName'] );
		$this->assertCount( 2, $blocks[0]['innerBlocks'] );
	}

	public function test_ol_becomes_ordered_list(): void {
		$blocks = Block_Tags::parse_all_elements( '<ol><li>First</li></ol>' );
		$this->assertSame( 'core/list', $blocks[0]['blockName'] );
		$this->assertTrue( $blocks[0]['attrs']['ordered'] );
	}

	public function test_table_becomes_table_block(): void {
		$blocks = Block_Tags::parse_all_elements( '<table><tr><td>Cell</td></tr></table>' );
		$this->assertSame( 'core/table', $blocks[0]['blockName'] );
	}

	public function test_audio_becomes_audio_block(): void {
		$blocks = Block_Tags::parse_all_elements( '<audio src="song.mp3"></audio>' );
		$this->assertSame( 'core/audio', $blocks[0]['blockName'] );
	}

	public function test_video_becomes_video_block(): void {
		$blocks = Block_Tags::parse_all_elements( '<video src="clip.mp4"></video>' );
		$this->assertSame( 'core/video', $blocks[0]['blockName'] );
	}

	public function test_details_becomes_details_block(): void {
		$blocks = Block_Tags::parse_all_elements( '<details><summary>Title</summary>Content</details>' );
		$this->assertSame( 'core/details', $blocks[0]['blockName'] );
	}

	// -------------------------------------------------------------------------
	// Plain text between tags
	// -------------------------------------------------------------------------

	public function test_plain_text_becomes_paragraph(): void {
		$blocks = Block_Tags::parse_all_elements( 'Just plain text' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertStringContainsString( 'Just plain text', $blocks[0]['innerHTML'] );
	}

	public function test_text_between_blocks(): void {
		$blocks = Block_Tags::parse_all_elements( '<h1>Title</h1>Some text<p>Paragraph</p>' );
		$this->assertCount( 3, $blocks );
		$this->assertSame( 'core/heading', $blocks[0]['blockName'] );
		$this->assertSame( 'core/paragraph', $blocks[1]['blockName'] );
		$this->assertStringContainsString( 'Some text', $blocks[1]['innerHTML'] );
		$this->assertSame( 'core/paragraph', $blocks[2]['blockName'] );
	}

	// -------------------------------------------------------------------------
	// Mixed: HTML + block tags
	// -------------------------------------------------------------------------

	public function test_mixed_html_and_block_tags(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<h1>Title</h1><block name="core/separator" /><p>After separator</p>'
		);
		$this->assertCount( 3, $blocks );
		$this->assertSame( 'core/heading', $blocks[0]['blockName'] );
		$this->assertSame( 'core/separator', $blocks[1]['blockName'] );
		$this->assertSame( 'core/paragraph', $blocks[2]['blockName'] );
	}

	public function test_mixed_html_and_bs_tags(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<p>Before</p><bs:core-separator /><p>After</p>'
		);
		$this->assertCount( 3, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertSame( 'core/separator', $blocks[1]['blockName'] );
		$this->assertSame( 'core/paragraph', $blocks[2]['blockName'] );
	}

	public function test_block_tag_inside_html_div(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<div><block name="core/paragraph">Inside div</block></div>'
		);
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['blockName'] );
	}

	// -------------------------------------------------------------------------
	// Nested raw HTML
	// -------------------------------------------------------------------------

	public function test_nested_divs(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<div><div><p>Deep</p></div></div>'
		);
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
	}

	// -------------------------------------------------------------------------
	// Multiple sibling elements
	// -------------------------------------------------------------------------

	public function test_multiple_paragraphs(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<p>First</p><p>Second</p><p>Third</p>'
		);
		$this->assertCount( 3, $blocks );
		foreach ( $blocks as $block ) {
			$this->assertSame( 'core/paragraph', $block['blockName'] );
		}
	}

	public function test_full_page_template(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<h1>Page Title</h1>' .
			'<p>Introduction text.</p>' .
			'<h2>Section</h2>' .
			'<p>Section content.</p>' .
			'<img src="photo.jpg" alt="Photo" />' .
			'<hr />' .
			'<blockquote><p>A quote.</p></blockquote>'
		);
		$this->assertCount( 7, $blocks );
		$this->assertSame( 'core/heading', $blocks[0]['blockName'] );
		$this->assertSame( 'core/paragraph', $blocks[1]['blockName'] );
		$this->assertSame( 'core/heading', $blocks[2]['blockName'] );
		$this->assertSame( 'core/paragraph', $blocks[3]['blockName'] );
		$this->assertSame( 'core/image', $blocks[4]['blockName'] );
		$this->assertSame( 'core/separator', $blocks[5]['blockName'] );
		$this->assertSame( 'core/quote', $blocks[6]['blockName'] );
	}

	// -------------------------------------------------------------------------
	// HTML comments skipped
	// -------------------------------------------------------------------------

	public function test_html_comments_skipped(): void {
		$blocks = Block_Tags::parse_all_elements(
			'<p>Before</p><!-- This is a comment --><p>After</p>'
		);
		$this->assertCount( 2, $blocks );
	}

	// -------------------------------------------------------------------------
	// Empty and edge cases
	// -------------------------------------------------------------------------

	public function test_empty_input(): void {
		$this->assertSame( array(), Block_Tags::parse_all_elements( '' ) );
	}

	public function test_whitespace_only(): void {
		$this->assertSame( array(), Block_Tags::parse_all_elements( '   ' ) );
	}

	public function test_unknown_html_tag_text_becomes_paragraph(): void {
		$blocks = Block_Tags::parse_all_elements( '<custom-element>Content</custom-element>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
	}
}
