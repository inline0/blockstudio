<?php

use Blockstudio\Html_Parser;
use PHPUnit\Framework\TestCase;

class HtmlParserTest extends TestCase {

	private Html_Parser $parser;

	protected function setUp(): void {
		$this->parser = new Html_Parser();
	}

	// parse_to_array()

	public function test_parse_to_array_returns_empty_array_for_empty_input(): void {
		$result = $this->parser->parse_to_array( '' );

		$this->assertSame( array(), $result );
	}

	public function test_parse_to_array_returns_empty_array_for_whitespace(): void {
		$result = $this->parser->parse_to_array( '   ' );

		$this->assertSame( array(), $result );
	}

	public function test_parse_to_array_returns_array_for_simple_html(): void {
		$result = $this->parser->parse_to_array( '<p>Hello world</p>' );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_parse_to_array_produces_paragraph_block(): void {
		$result = $this->parser->parse_to_array( '<p>Hello world</p>' );

		$this->assertSame( 'core/paragraph', $result[0]['blockName'] );
	}

	public function test_parse_to_array_strips_php_tags(): void {
		$result = $this->parser->parse_to_array( '<?php echo "test"; ?><p>Content</p>' );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
		$this->assertSame( 'core/paragraph', $result[0]['blockName'] );
	}

	public function test_parse_to_array_produces_heading_block(): void {
		$result = $this->parser->parse_to_array( '<h2>Title</h2>' );

		$this->assertSame( 'core/heading', $result[0]['blockName'] );
		$this->assertSame( 2, $result[0]['attrs']['level'] );
	}

	public function test_parse_to_array_produces_multiple_blocks(): void {
		$html   = '<h2>Title</h2><p>Paragraph one</p><p>Paragraph two</p>';
		$result = $this->parser->parse_to_array( $html );

		$this->assertCount( 3, $result );
		$this->assertSame( 'core/heading', $result[0]['blockName'] );
		$this->assertSame( 'core/paragraph', $result[1]['blockName'] );
		$this->assertSame( 'core/paragraph', $result[2]['blockName'] );
	}

	// parse()

	public function test_parse_returns_empty_string_for_empty_input(): void {
		$result = $this->parser->parse( '' );

		$this->assertSame( '', $result );
	}

	public function test_parse_returns_empty_string_for_whitespace(): void {
		$result = $this->parser->parse( '   ' );

		$this->assertSame( '', $result );
	}

	public function test_parse_returns_string(): void {
		$result = $this->parser->parse( '<p>Hello</p>' );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_parse_returns_serialized_block_comment(): void {
		$result = $this->parser->parse( '<p>Hello</p>' );

		$this->assertStringContainsString( '<!-- wp:paragraph', $result );
		$this->assertStringContainsString( '<!-- /wp:paragraph -->', $result );
	}

	public function test_parse_heading_returns_serialized_heading_block(): void {
		$result = $this->parser->parse( '<h3>Heading</h3>' );

		$this->assertStringContainsString( '<!-- wp:heading', $result );
		$this->assertStringContainsString( '"level":3', $result );
		$this->assertStringContainsString( '<h3 class="wp-block-heading">Heading</h3>', $result );
	}

	public function test_parse_strips_php_tags(): void {
		$result = $this->parser->parse( '<?php echo "test"; ?><p>Clean</p>' );

		$this->assertStringNotContainsString( '<?php', $result );
		$this->assertStringContainsString( '<!-- wp:paragraph', $result );
	}

	public function test_parse_multiple_elements_returns_multiple_blocks(): void {
		$html   = '<p>First</p><p>Second</p>';
		$result = $this->parser->parse( $html );

		$count = substr_count( $result, '<!-- wp:paragraph' );
		$this->assertSame( 2, $count );
	}

	// from_settings()

	public function test_from_settings_returns_html_parser_instance(): void {
		$parser = Html_Parser::from_settings();

		$this->assertInstanceOf( Html_Parser::class, $parser );
	}

	// List elements

	public function test_parse_unordered_list(): void {
		$result = $this->parser->parse_to_array( '<ul><li>Item A</li><li>Item B</li></ul>' );

		$this->assertSame( 'core/list', $result[0]['blockName'] );
	}

	public function test_parse_ordered_list(): void {
		$result = $this->parser->parse_to_array( '<ol><li>First</li><li>Second</li></ol>' );

		$this->assertSame( 'core/list', $result[0]['blockName'] );
		$this->assertTrue( $result[0]['attrs']['ordered'] );
	}

	// Separator

	public function test_parse_hr_produces_separator(): void {
		$result = $this->parser->parse_to_array( '<hr>' );

		$this->assertSame( 'core/separator', $result[0]['blockName'] );
	}

	// Blockquote

	public function test_parse_blockquote(): void {
		$result = $this->parser->parse_to_array( '<blockquote><p>A quote</p></blockquote>' );

		$this->assertSame( 'core/quote', $result[0]['blockName'] );
	}

	// Image

	public function test_parse_img_produces_image_block(): void {
		$result = $this->parser->parse_to_array( '<img src="https://example.com/photo.jpg" alt="A photo">' );

		$this->assertSame( 'core/image', $result[0]['blockName'] );
		$this->assertSame( 'https://example.com/photo.jpg', $result[0]['attrs']['url'] );
	}
}
