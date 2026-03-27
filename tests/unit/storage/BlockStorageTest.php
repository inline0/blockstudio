<?php

use Blockstudio\Storage_Handlers\Block_Storage;
use Blockstudio\Interfaces\Storage_Handler_Interface;
use PHPUnit\Framework\TestCase;

class BlockStorageTest extends TestCase {

	private Block_Storage $handler;

	protected function setUp(): void {
		$this->handler = new Block_Storage();
	}

	// get_type()

	public function test_get_type_returns_block(): void {
		$this->assertSame( 'block', $this->handler->get_type() );
	}

	public function test_implements_storage_handler_interface(): void {
		$this->assertInstanceOf( Storage_Handler_Interface::class, $this->handler );
	}

	// register()

	public function test_register_is_noop(): void {
		$field = array(
			'id'   => 'title',
			'type' => 'text',
		);

		$this->handler->register( 'test/block', $field );

		$this->assertTrue( true, 'register() should complete without error' );
	}

	public function test_register_with_empty_field(): void {
		$this->handler->register( 'test/block', array() );

		$this->assertTrue( true, 'register() with empty field should complete without error' );
	}

	public function test_register_with_various_block_names(): void {
		$field = array( 'id' => 'title', 'type' => 'text' );

		$this->handler->register( 'core/paragraph', $field );
		$this->handler->register( 'my-plugin/custom-block', $field );
		$this->handler->register( '', $field );

		$this->assertTrue( true, 'register() should be a no-op for any block name' );
	}

	// get_key()

	public function test_get_key_returns_field_id(): void {
		$field = array( 'id' => 'title' );

		$this->assertSame( 'title', $this->handler->get_key( 'test/block', $field ) );
	}

	public function test_get_key_ignores_block_name(): void {
		$field = array( 'id' => 'content' );

		$result_a = $this->handler->get_key( 'test/block-a', $field );
		$result_b = $this->handler->get_key( 'test/block-b', $field );

		$this->assertSame( $result_a, $result_b );
		$this->assertSame( 'content', $result_a );
	}

	public function test_get_key_with_complex_field_id(): void {
		$field = array( 'id' => 'my_custom_field_123' );

		$this->assertSame( 'my_custom_field_123', $this->handler->get_key( 'test/block', $field ) );
	}

	public function test_get_key_returns_field_id_regardless_of_storage_config(): void {
		$field = array(
			'id'      => 'title',
			'storage' => array( 'type' => 'block', 'someKey' => 'ignored' ),
		);

		$this->assertSame( 'title', $this->handler->get_key( 'test/block', $field ) );
	}

	public function test_get_key_with_empty_field_id(): void {
		$field = array( 'id' => '' );

		$this->assertSame( '', $this->handler->get_key( 'test/block', $field ) );
	}
}
