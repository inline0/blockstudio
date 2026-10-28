<?php

use Blockstudio\Block;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * replace_components() resolves the InnerBlocks and RichText pseudo tags and
 * strips the component attributes from the remaining elements.
 */
class BlockReplaceComponentsTest extends TestCase {

	private const CLEANUP_ATTRIBUTES = array(
		'tag',
		'allowedBlocks',
		'defaultBlock',
		'directInsert',
		'prioritizedInserterBlocks',
		'renderAppender',
		'template',
		'templateInsertUpdatesSelection',
		'templateLock',
		'attribute',
		'placeholder',
		'allowedFormats',
		'autocompleters',
		'multiline',
		'preserveWhiteSpace',
		'withoutInteractiveFormatting',
	);

	private function replace( string $content, array $fields = array(), array $attributes = array(), string $inner_blocks = '' ): string {
		return Block::replace_components(
			$content,
			$inner_blocks,
			false,
			(object) array( 'attributes' => $fields ),
			$attributes,
			array(),
			array()
		);
	}

	/**
	 * @return array<int, array{tag:string,attributes:array<string,string|true>}>
	 */
	private function tags( string $html ): array {
		$processor = new WP_HTML_Tag_Processor( $html );
		$tags      = array();

		while ( $processor->next_tag() ) {
			$attributes = array();
			foreach ( $processor->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
				$attributes[ $name ] = $processor->get_attribute( $name );
			}
			$tags[] = array(
				'tag'        => strtolower( $processor->get_tag() ),
				'attributes' => $attributes,
			);
		}

		return $tags;
	}

	public function test_richtext_is_replaced_for_a_field_the_markup_names(): void {
		$output = $this->replace(
			'<div><RichText attribute="title" tag="h2" class="heading" /></div>',
			array( array( 'id' => 'title', 'type' => 'text' ) ),
			array( 'title' => 'Hello' )
		);

		$this->assertStringNotContainsString( '<RichText', $output );
		$this->assertStringContainsString( '>Hello</h2>', $output );
		$this->assertStringContainsString( 'class="heading"', $output );
	}

	public function test_richtext_is_replaced_for_each_field_the_markup_names(): void {
		$output = $this->replace(
			'<div><RichText attribute="first" tag="h2" /><RichText attribute="second" tag="p" /></div>',
			array(
				array( 'id' => 'first', 'type' => 'text' ),
				array( 'id' => 'unused', 'type' => 'text' ),
				array( 'id' => 'second', 'type' => 'text' ),
			),
			array(
				'first'  => 'One',
				'unused' => 'Never printed',
				'second' => 'Two',
			)
		);

		$this->assertStringContainsString( '<h2>One</h2>', $output );
		$this->assertStringContainsString( '<p>Two</p>', $output );
		$this->assertStringNotContainsString( 'Never printed', $output );
	}

	public function test_richtext_resolves_a_path_attribute(): void {
		$output = $this->replace(
			'<ul><li><RichText attribute="items[1].text" tag="span" /></li></ul>',
			array( array( 'id' => 'items', 'type' => 'repeater' ) ),
			array(
				'items' => array(
					array( 'text' => 'First' ),
					array( 'text' => 'Second' ),
				),
			)
		);

		$this->assertStringContainsString( '>Second</span>', $output );
		$this->assertStringNotContainsString( 'First', $output );
		$this->assertStringNotContainsString( '<RichText', $output );
	}

	public function test_richtext_for_a_field_the_block_does_not_declare_is_left_for_the_cleanup(): void {
		$output = $this->replace(
			'<div><RichText attribute="ghost" tag="p" />Kept</div>',
			array( array( 'id' => 'title', 'type' => 'text' ) ),
			array( 'title' => 'Not used' )
		);

		$this->assertStringNotContainsString( 'Not used', $output );
		$this->assertStringContainsString( 'Kept', $output );
		$this->assertStringNotContainsString( 'attribute=', $output );
	}

	public function test_richtext_field_is_skipped_when_its_type_is_richtext(): void {
		$output = $this->replace(
			'<div><RichText attribute="body" tag="div" /></div>',
			array( array( 'id' => 'body', 'type' => 'richtext' ) ),
			array( 'body' => '<p>Stored</p>' )
		);

		$this->assertStringNotContainsString( 'Stored', $output );
	}

	public function test_markup_without_richtext_ignores_the_declared_fields(): void {
		$output = $this->replace(
			'<div useBlockProps class="plain"><span>Text</span></div>',
			array( array( 'id' => 'title', 'type' => 'text' ) ),
			array( 'title' => 'Not used' )
		);

		$this->assertStringNotContainsString( 'Not used', $output );
		$this->assertStringContainsString( '<span>Text</span>', $output );
	}

	public function test_inner_blocks_are_replaced_next_to_richtext(): void {
		$output = $this->replace(
			'<section><RichText attribute="title" tag="h2" /><InnerBlocks class="inner" /></section>',
			array( array( 'id' => 'title', 'type' => 'text' ) ),
			array( 'title' => 'Heading' ),
			'<p>Nested $1</p>'
		);

		$this->assertStringContainsString( '>Heading</h2>', $output );
		$this->assertStringContainsString( '<p>Nested $1</p>', $output );
		$this->assertStringNotContainsString( '<InnerBlocks', $output );
	}

	public function test_content_without_inner_blocks_tag_is_not_given_the_nested_content(): void {
		$output = $this->replace(
			'<div useBlockProps><p>Static</p></div>',
			array(),
			array(),
			'<p>Nested</p>'
		);

		$this->assertStringNotContainsString( 'Nested', $output );
		$this->assertStringContainsString( '<p>Static</p>', $output );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function cleanup_attributes(): array {
		$cases = array();

		foreach ( self::CLEANUP_ATTRIBUTES as $name ) {
			$cases[ $name ] = array( $name );
		}

		return $cases;
	}

	#[DataProvider( 'cleanup_attributes' )]
	public function test_cleanup_removes_the_attribute_from_an_element_and_keeps_the_rest( string $name ): void {
		$output = $this->replace( '<div useBlockProps><div data-keep="1" ' . $name . '="value" class="x">Inner</div></div>' );
		$tags   = $this->tags( $output );

		$this->assertCount( 2, $tags );
		$this->assertArrayNotHasKey( strtolower( $name ), $tags[1]['attributes'] );
		$this->assertSame( '1', $tags[1]['attributes']['data-keep'] ?? null );
		$this->assertSame( 'x', $tags[1]['attributes']['class'] ?? null );
	}

	#[DataProvider( 'cleanup_attributes' )]
	public function test_cleanup_keeps_the_attribute_on_input_and_textarea( string $name ): void {
		$output = $this->replace( '<div useBlockProps><input ' . $name . '="value"><textarea ' . $name . '="value"></textarea></div>' );
		$tags   = $this->tags( $output );

		$this->assertSame( 'input', $tags[1]['tag'] );
		$this->assertSame( 'value', $tags[1]['attributes'][ strtolower( $name ) ] ?? null );
		$this->assertSame( 'textarea', $tags[2]['tag'] );
		$this->assertSame( 'value', $tags[2]['attributes'][ strtolower( $name ) ] ?? null );
	}

	public function test_cleanup_leaves_elements_without_component_attributes_untouched(): void {
		$output = $this->replace(
			'<div useBlockProps><p class="a" data-x="1">One</p><img src="a.png" alt=""><a href="/x" title="Link">Two</a><span>Three</span></div>'
		);
		$tags   = $this->tags( $output );

		$this->assertSame( 'p', $tags[1]['tag'] );
		$this->assertSame( array( 'class' => 'a', 'data-x' => '1' ), $tags[1]['attributes'] );
		$this->assertSame( array( 'src' => 'a.png', 'alt' => '' ), $tags[2]['attributes'] );
		$this->assertSame( array( 'href' => '/x', 'title' => 'Link' ), $tags[3]['attributes'] );
		$this->assertSame( array(), $tags[4]['attributes'] );
		$this->assertStringContainsString( '<span>Three</span>', $output );
	}

	public function test_cleanup_handles_elements_with_and_without_attributes_in_one_document(): void {
		$output = $this->replace(
			'<div useBlockProps><section><p>Bare</p><p placeholder="gone" class="b">Marked</p></section></div>'
		);
		$tags   = $this->tags( $output );

		$this->assertSame( array(), $tags[2]['attributes'] );
		$this->assertSame( array( 'class' => 'b' ), $tags[3]['attributes'] );
	}
}
