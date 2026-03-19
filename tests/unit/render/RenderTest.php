<?php

use Blockstudio\Render;
use PHPUnit\Framework\TestCase;

class RenderTest extends TestCase {

	public function test_class_exists(): void {
		$this->assertTrue( class_exists( Render::class ) );
	}

	public function test_block_method_is_callable(): void {
		$this->assertTrue( method_exists( Render::class, 'block' ) );
	}

	public function test_block_returns_false_for_unknown_block_name(): void {
		$result = Render::block( 'blockstudio/nonexistent-block-xyz' );
		$this->assertFalse( $result );
	}

	public function test_block_returns_false_for_unknown_block_array(): void {
		$result = Render::block(
			array(
				'name' => 'blockstudio/nonexistent-block-xyz',
			)
		);
		$this->assertFalse( $result );
	}

	public function test_block_array_with_data_returns_false_for_unknown(): void {
		$result = Render::block(
			array(
				'name' => 'blockstudio/nonexistent-block-xyz',
				'data' => array( 'title' => 'Test' ),
			)
		);
		$this->assertFalse( $result );
	}
}
