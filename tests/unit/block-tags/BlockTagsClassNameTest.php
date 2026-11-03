<?php

use Blockstudio\Block_Tags;
use Blockstudio\Html_Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockTagsClassNameTest extends TestCase {

	#[DataProvider( 'core_classes' )]
	public function test_core_classes_reach_attributes_and_saved_html( string $source, string $name, string $class, string $tag ): void {
		$blocks = ( new Html_Parser() )->parse_to_array( $source );
		$this->assertCount( 1, $blocks );
		$block = $blocks[0];
		$this->assertSame( $name, $block['blockName'] );
		$this->assertSame( $class, $block['attrs']['className'] );
		$this->assertArrayNotHasKey( 'class', $block['attrs'] );
		$this->assert_root_classes( $block['innerHTML'], $class, $tag );
		$this->assert_root_classes( implode( '', array_filter( $block['innerContent'], 'is_string' ) ), $class, $tag );
	}

	public static function core_classes(): array {
		return array(
			'mapped paragraph' => array( '<p class="paragraph-a">Hello</p>', 'core/paragraph', 'paragraph-a', 'P' ),
			'explicit paragraph' => array( '<block name="core/paragraph" className="paragraph-b">Hi</block>', 'core/paragraph', 'paragraph-b', 'P' ),
			'mapped heading' => array( '<h2 class="wp-block-heading title">Title</h2>', 'core/heading', 'wp-block-heading title', 'H2' ),
			'explicit heading' => array( '<block name="core/heading" level="3" className="title">Title</block>', 'core/heading', 'title', 'H3' ),
			'mapped group' => array( '<div class="group-a"><p>Child</p></div>', 'core/group', 'group-a', 'DIV' ),
			'semantic group' => array( '<article class="article-a"><p>Child</p></article>', 'core/group', 'article-a', 'ARTICLE' ),
			'explicit group' => array( '<block name="core/group" className="group-a"><p>Child</p></block>', 'core/group', 'group-a', 'DIV' ),
			'button' => array( '<block name="core/button" url="/join/" className="is-style-outline">Join</block>', 'core/button', 'is-style-outline', 'DIV' ),
			'buttons' => array( '<block name="core/buttons" className="actions"><block name="core/button">Join</block></block>', 'core/buttons', 'actions', 'DIV' ),
			'list control' => array( '<ul class="items"><li>Item</li></ul>', 'core/list', 'items', 'UL' ),
			'columns' => array( '<block name="core/columns" className="columns-a"><block name="core/column"><p>Child</p></block></block>', 'core/columns', 'columns-a', 'DIV' ),
			'separator' => array( '<hr class="divider" />', 'core/separator', 'divider', 'HR' ),
		);
	}

	public function test_explicit_class_name_takes_precedence_over_class_alias(): void {
		$block = Block_Tags::build_block_array( 'core/paragraph', array( 'class' => 'alias', 'className' => 'explicit' ), 'Hello' );
		$this->assertSame( array( 'className' => 'explicit' ), $block['attrs'] );
		$this->assertSame( '<p class="explicit">Hello</p>', $block['innerHTML'] );
	}

	public function test_classes_do_not_spread_to_child_roots_or_button_links(): void {
		$blocks = Block_Tags::parse_all_elements( '<block name="core/group" className="parent"><p class="child">Child</p><block name="core/button" className="is-style-outline">Join</block></block>' );
		$this->assert_root_classes( $blocks[0]['innerHTML'], 'parent', 'DIV' );
		$this->assertSame( 2, count( $blocks[0]['innerBlocks'] ) );
		$this->assertSame( 'child', $blocks[0]['innerBlocks'][0]['attrs']['className'] );
		$button = new WP_HTML_Tag_Processor( $blocks[0]['innerBlocks'][1]['innerHTML'] );
		$this->assertTrue( $button->next_tag( 'A' ) );
		$this->assertFalse( $button->has_class( 'is-style-outline' ) );
	}

	public function test_non_core_class_fields_are_not_renamed(): void {
		$block = Block_Tags::build_block_array( 'class-test/custom', array( 'class' => 'field-value' ) );
		$this->assertSame( array( 'class' => 'field-value' ), $block['attrs'] );
	}

	public function test_invalid_or_empty_class_names_do_not_change_saved_html(): void {
		foreach ( array( '', null, false, array( 'invalid' ) ) as $class ) {
			$block = Block_Tags::build_block_array( 'core/paragraph', array( 'className' => $class ), 'Hello' );
			$this->assertSame( '<p>Hello</p>', $block['innerHTML'] );
		}
	}

	public function test_class_tokens_are_escaped_without_creating_attributes(): void {
		$class = 'sm:hover:bg-red-500 quoted" &value';
		$block = Block_Tags::build_block_array( 'core/paragraph', array( 'className' => $class ), 'Hello' );
		$tag   = new WP_HTML_Tag_Processor( $block['innerHTML'] );
		$this->assertTrue( $tag->next_tag() );
		$this->assertSame( $class, $tag->get_attribute( 'class' ) );
		$this->assertNull( $tag->get_attribute( 'quoted' ) );
		$this->assertStringNotContainsString( '&amp;amp;', $block['innerHTML'] );
	}

	private function assert_root_classes( string $html, string $classes, string $tag ): void {
		$processor = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( $tag, $processor->get_tag() );
		$actual = preg_split( '/\s+/', (string) $processor->get_attribute( 'class' ) );
		$this->assertSame( array_values( array_unique( $actual ) ), $actual );
		foreach ( preg_split( '/\s+/', $classes ) as $class ) {
			$this->assertTrue( $processor->has_class( $class ), $html );
		}
	}
}
