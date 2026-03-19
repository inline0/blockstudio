<?php

use Blockstudio\Block;
use Blockstudio\Build;
use PHPUnit\Framework\TestCase;

class BlockTest extends TestCase {

	protected function setUp(): void {
		$ref = new ReflectionClass( Block::class );

		$count = $ref->getProperty( 'count' );
		$count->setAccessible( true );
		$count->setValue( null, 0 );

		$count_by_block = $ref->getProperty( 'count_by_block' );
		$count_by_block->setAccessible( true );
		$count_by_block->setValue( null, array() );
	}

	// id()

	public function test_id_returns_string(): void {
		$id = Block::id( array( 'name' => 'test' ), array( 'text' => 'hello' ) );
		$this->assertIsString( $id );
	}

	public function test_id_starts_with_blockstudio_prefix(): void {
		$id = Block::id( array( 'name' => 'test' ), array() );
		$this->assertStringStartsWith( 'blockstudio-', $id );
	}

	public function test_id_has_12_char_hash(): void {
		$id = Block::id( array( 'name' => 'test' ), array() );
		$hash = substr( $id, strlen( 'blockstudio-' ) );
		$this->assertSame( 12, strlen( $hash ) );
	}

	public function test_id_is_unique_across_calls(): void {
		$id1 = Block::id( array( 'name' => 'test' ), array() );
		$id2 = Block::id( array( 'name' => 'test' ), array() );
		$this->assertNotSame( $id1, $id2 );
	}

	public function test_id_hash_is_hexadecimal(): void {
		$id   = Block::id( array( 'name' => 'test' ), array( 'text' => 'value' ) );
		$hash = substr( $id, strlen( 'blockstudio-' ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{12}$/', $hash );
	}

	// comment()

	public function test_comment_returns_html_comment(): void {
		$data = Build::data();
		$name = array_key_first( $data );

		$comment = Block::comment( $name );
		$this->assertStringStartsWith( '<!--blockstudio/', $comment );
		$this->assertStringEndsWith( '-->', $comment );
	}

	public function test_comment_contains_block_name(): void {
		$data = Build::data();
		$name = array_key_first( $data );
		$expected_name = $data[ $name ]['name'];

		$comment = Block::comment( $name );
		$this->assertStringContainsString( $expected_name, $comment );
	}

	public function test_comment_format_for_text_block(): void {
		$data = Build::data();
		if ( ! isset( $data['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$block_name = $data['blockstudio/type-text']['name'];
		$comment    = Block::comment( 'blockstudio/type-text' );
		$this->assertSame( '<!--blockstudio/' . $block_name . '-->', $comment );
	}

	// render() with a real test block

	public function test_render_returns_string_for_text_block(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$attributes = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'Hello World',
				),
			),
		);

		$result = Block::render( $attributes );
		$this->assertIsString( $result );
	}

	public function test_render_contains_block_comment(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$attributes = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'Test content',
				),
			),
		);

		$expected_comment = Block::comment( 'blockstudio/type-text' );
		$result           = Block::render( $attributes );
		$this->assertStringContainsString( $expected_comment, $result );
	}

	public function test_render_contains_template_output(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$attributes = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'Rendered text value',
				),
			),
		);

		$result = Block::render( $attributes );
		$this->assertStringContainsString( 'blockstudio-test__block', $result );
	}

	public function test_render_returns_false_without_block_name(): void {
		$result = Block::render( array() );
		$this->assertFalse( $result );
	}

	// Block index counting

	public function test_render_increments_block_index(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$attributes = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'First',
				),
			),
		);

		Block::render( $attributes );
		Block::render( $attributes );

		$ref = new ReflectionClass( Block::class );
		$count_by_block = $ref->getProperty( 'count_by_block' );
		$count_by_block->setAccessible( true );
		$counts = $count_by_block->getValue();

		$this->assertSame( 2, $counts['blockstudio/type-text'] );
	}

	public function test_render_tracks_total_count(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$text_attrs = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'Count test',
				),
			),
		);

		Block::render( $text_attrs );
		Block::render( $text_attrs );
		Block::render( $text_attrs );

		$ref   = new ReflectionClass( Block::class );
		$count = $ref->getProperty( 'count' );
		$count->setAccessible( true );

		$this->assertSame( 3, $count->getValue() );
	}

	// resolve_attribute_path()

	public function test_resolve_simple_path(): void {
		$attributes = array( 'title' => 'Hello' );
		$result     = Block::resolve_attribute_path( 'title', $attributes );
		$this->assertSame( 'Hello', $result );
	}

	public function test_resolve_nested_bracket_path(): void {
		$attributes = array(
			'items' => array(
				array( 'content' => 'First' ),
				array( 'content' => 'Second' ),
			),
		);
		$result = Block::resolve_attribute_path( 'items[1].content', $attributes );
		$this->assertSame( 'Second', $result );
	}

	public function test_resolve_nonexistent_path_returns_null(): void {
		$attributes = array( 'title' => 'Hello' );
		$result     = Block::resolve_attribute_path( 'nonexistent', $attributes );
		$this->assertNull( $result );
	}

	public function test_resolve_deeply_nested_path(): void {
		$attributes = array(
			'level1' => array(
				'level2' => array(
					'level3' => 'deep value',
				),
			),
		);
		$result = Block::resolve_attribute_path( 'level1.level2.level3', $attributes );
		$this->assertSame( 'deep value', $result );
	}
}
