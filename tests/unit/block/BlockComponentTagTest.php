<?php

use Blockstudio\Block;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockComponentTagTest extends TestCase {

	public static function rich_text_markup(): array {
		return array(
			'double quotes'         => array( '<RichText attribute="title" tag="h2" class="heading" />', '<h2 attribute="title" tag="h2" class="heading">Hello $1</h2>' ),
			'single quotes'         => array( "<RichText attribute='title' tag='h2' class='heading' />", '<h2 attribute="title" tag="h2" class="heading">Hello $1</h2>' ),
			'unquoted values'       => array( '<RichText attribute=title tag=h2 class=heading />', '<h2 attribute="title" tag="h2" class="heading">Hello $1</h2>' ),
			'spaced assignments'    => array( "<RichText\n attribute = 'title' tag = h2 class = \"heading\" />", '<h2 attribute="title" tag="h2" class="heading">Hello $1</h2>' ),
			'empty and boolean'     => array( "<RichText attribute='title' class='' hidden />", '<p attribute="title" class="" hidden>Hello $1</p>' ),
			'quoted angle bracket'  => array( "<RichText title='A > B' attribute='title' tag='h2' />", '<h2 title="A &gt; B" attribute="title" tag="h2">Hello $1</h2>' ),
			'unknown field'         => array( '<RichText attribute="missing" />', '<RichText attribute="missing" />' ),
			'field name prefix'     => array( '<RichText attribute=title-extra />', '<RichText attribute=title-extra />' ),
			'component name prefix' => array( '<RichTextExtra attribute="title" />', '<RichTextExtra attribute="title" />' ),
			'quoted fake selector'  => array( '<RichText title="attribute=title" attribute="missing" />', '<RichText title="attribute=title" attribute="missing" />' ),
		);
	}

	#[DataProvider( 'rich_text_markup' )]
	public function test_rich_text_attribute_syntax( string $content, string $expected ): void {
		Block::replace_custom_tag( $content, '', (object) array( 'name' => 'blockstudio/type-text' ), array( 'title' => 'Hello $1' ), 'RichText', 'title' );
		$this->assertSame( $expected, $content );
		$this->assertSame( PREG_NO_ERROR, preg_last_error() );
	}

	public function test_inner_blocks_read_single_quoted_and_unquoted_values(): void {
		$content = "<InnerBlocks tag='section' class=inner useBlockProps />";
		Block::replace_custom_tag( $content, '<p>Nested $1</p>', (object) array(), array(), 'InnerBlocks', 'InnerBlocks' );
		$this->assertSame( '<section tag="section" class="inner" useBlockProps><p>Nested $1</p></section>', $content );
	}

	public function test_long_quoted_attributes_do_not_exhaust_the_pattern_stack(): void {
		$value   = str_repeat( 'long attribute > value ', 2000 );
		$content = '<RichText title="' . $value . '" attribute="title" />';
		Block::replace_custom_tag( $content, '', (object) array(), array( 'title' => 'Kept' ), 'RichText', 'title' );
		$this->assertSame( '<p title="' . htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, null, false ) . '" attribute="title">Kept</p>', $content );
		$this->assertSame( PREG_NO_ERROR, preg_last_error() );
	}
}
