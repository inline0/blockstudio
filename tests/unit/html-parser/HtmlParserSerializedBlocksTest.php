<?php

use Blockstudio\Html_Parser;
use PHPUnit\Framework\TestCase;

class HtmlParserSerializedBlocksTest extends TestCase {

	public function test_serialized_blocks_preserve_attributes_and_nested_blocks(): void {
		$source = '<!-- wp:query {"query":{"perPage":3,"postType":"post"}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->';
		$this->assertSame( parse_blocks( $source ), ( new Html_Parser() )->parse_to_array( $source ) );
		$this->assertSame( parse_blocks( $source ), parse_blocks( ( new Html_Parser() )->parse( $source ) ) );
	}

	public function test_serialized_blockstudio_and_unknown_blocks_are_not_dropped_or_rewrapped(): void {
		$source = '<!-- wp:blockstudio/type-text {"blockstudio":{"attributes":{"text":"Saved value"}},"__BLOCKSTUDIO_KEY":"content"} /--><!-- wp:unregistered/dynamic {"id":7} /-->';
		$this->assertSame( parse_blocks( $source ), ( new Html_Parser() )->parse_to_array( $source ) );
	}

	public function test_html_outside_serialized_blocks_remains_native_freeform_html(): void {
		$source = '<aside class="raw">Before</aside><!-- wp:paragraph {"className":"saved"} --><p class="saved">Saved</p><!-- /wp:paragraph --><footer>After</footer>';
		$this->assertSame( parse_blocks( $source ), ( new Html_Parser() )->parse_to_array( $source ) );
	}

	public function test_ordinary_html_comments_do_not_switch_to_serialized_mode(): void {
		$blocks = ( new Html_Parser() )->parse_to_array( '<!-- note --><p class="normal">Hello</p>' );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertSame( 'normal', $blocks[0]['attrs']['className'] );
	}
}
