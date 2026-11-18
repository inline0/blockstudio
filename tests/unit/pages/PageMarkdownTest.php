<?php
/**
 * Private Markdown and YAML dependency regression tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Page_Markdown;
use Blockstudio\Runtime_Cache;
use PHPUnit\Framework\TestCase;

class PageMarkdownTest extends TestCase {

	public function test_private_yaml_preserves_nested_frontmatter_and_anchors(): void {
		$result = Page_Markdown::split_frontmatter( "---\ndefaults: &defaults\n  title: Hello\npage:\n  <<: *defaults\n  items: [one, two]\n---\nBody" );
		$this->assertSame( array( 'title' => 'Hello', 'items' => array( 'one', 'two' ) ), $result['data']['page'] );
		$this->assertSame( 'Body', $result['body'] );
		$this->assertSame( array(), Page_Markdown::parse_yaml( 'invalid: [unterminated' ) );
	}

	public function test_private_commonmark_renders_headings_and_gfm(): void {
		$html = Page_Markdown::to_html( "# Hello World\n\n| A | B |\n| --- | --- |\n| One | Two |\n\n- [x] Done\n\n~~Removed~~\n" );
		$this->assertStringContainsString( 'id="hello-world"', $html );
		$this->assertStringContainsString( '<table>', $html );
		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( '<del>Removed</del>', $html );
	}

	public function test_conversion_and_sanitizing_are_cached_by_content(): void {
		$markdown = "# Cached " . bin2hex( random_bytes( 6 ) ) . "\n\nBody with <script>x</script> text.\n";
		$before   = glob( Runtime_Cache::directory( 'page-markdown' ) . '/*.cache' );
		$first    = Page_Markdown::to_html( $markdown );
		$second   = Page_Markdown::to_html( $markdown );
		$after    = glob( Runtime_Cache::directory( 'page-markdown' ) . '/*.cache' );
		$this->assertSame( $first, $second );
		$this->assertSame( count( is_array( $before ) ? $before : array() ) + 1, count( is_array( $after ) ? $after : array() ) );
		$this->assertSame( Page_Markdown::sanitize_docs_html( $first ), Page_Markdown::sanitize_docs_html( $second ) );
		$this->assertStringNotContainsString( '<script>', Page_Markdown::sanitize_docs_html( $first ) );
	}
}
