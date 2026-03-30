<?php

use Blockstudio\Storage_Registry;
use Blockstudio\Interfaces\Storage_Handler_Interface;
use PHPUnit\Framework\TestCase;

class StorageRegistryTest extends TestCase {

	protected function setUp(): void {
		$ref = new ReflectionClass( Storage_Registry::class );

		$instance = $ref->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	// instance()

	public function test_instance_returns_singleton(): void {
		$a = Storage_Registry::instance();
		$b = Storage_Registry::instance();

		$this->assertSame( $a, $b );
	}

	public function test_instance_returns_storage_registry(): void {
		$this->assertInstanceOf( Storage_Registry::class, Storage_Registry::instance() );
	}

	// register_handler() / get_handler()

	public function test_register_and_get_handler(): void {
		$handler = $this->create_handler( 'block' );

		$registry = Storage_Registry::instance();
		$registry->register_handler( $handler );

		$this->assertSame( $handler, $registry->get_handler( 'block' ) );
	}

	public function test_get_handler_returns_null_for_unknown_type(): void {
		$registry = Storage_Registry::instance();

		$this->assertNull( $registry->get_handler( 'nonexistent' ) );
	}

	public function test_register_multiple_handlers(): void {
		$block_handler = $this->create_handler( 'block' );
		$meta_handler  = $this->create_handler( 'postMeta' );

		$registry = Storage_Registry::instance();
		$registry->register_handler( $block_handler );
		$registry->register_handler( $meta_handler );

		$this->assertSame( $block_handler, $registry->get_handler( 'block' ) );
		$this->assertSame( $meta_handler, $registry->get_handler( 'postMeta' ) );
	}

	public function test_register_handler_overwrites_existing(): void {
		$first  = $this->create_handler( 'block' );
		$second = $this->create_handler( 'block' );

		$registry = Storage_Registry::instance();
		$registry->register_handler( $first );
		$registry->register_handler( $second );

		$this->assertSame( $second, $registry->get_handler( 'block' ) );
	}

	// get_handlers()

	public function test_get_handlers_empty_initially(): void {
		$registry = Storage_Registry::instance();

		$this->assertSame( array(), $registry->get_handlers() );
	}

	public function test_get_handlers_returns_all_registered(): void {
		$block_handler  = $this->create_handler( 'block' );
		$option_handler = $this->create_handler( 'option' );

		$registry = Storage_Registry::instance();
		$registry->register_handler( $block_handler );
		$registry->register_handler( $option_handler );

		$handlers = $registry->get_handlers();

		$this->assertCount( 2, $handlers );
		$this->assertArrayHasKey( 'block', $handlers );
		$this->assertArrayHasKey( 'option', $handlers );
	}

	// get_storage_types()

	public function test_get_storage_types_defaults_to_block(): void {
		$registry = Storage_Registry::instance();
		$field    = array( 'id' => 'title' );

		$this->assertSame( array( 'block' ), $registry->get_storage_types( $field ) );
	}

	public function test_get_storage_types_with_single_type_string(): void {
		$registry = Storage_Registry::instance();
		$field    = array(
			'id'      => 'title',
			'storage' => array( 'type' => 'postMeta' ),
		);

		$this->assertSame( array( 'postMeta' ), $registry->get_storage_types( $field ) );
	}

	public function test_get_storage_types_with_array_of_types(): void {
		$registry = Storage_Registry::instance();
		$field    = array(
			'id'      => 'title',
			'storage' => array( 'type' => array( 'block', 'postMeta' ) ),
		);

		$this->assertSame( array( 'block', 'postMeta' ), $registry->get_storage_types( $field ) );
	}

	public function test_get_storage_types_without_type_key_defaults_to_block(): void {
		$registry = Storage_Registry::instance();
		$field    = array(
			'id'      => 'title',
			'storage' => array( 'key' => 'custom_key' ),
		);

		$this->assertSame( array( 'block' ), $registry->get_storage_types( $field ) );
	}

	// has_storage_type()

	public function test_has_storage_type_true_for_default_block(): void {
		$registry = Storage_Registry::instance();
		$field    = array( 'id' => 'title' );

		$this->assertTrue( $registry->has_storage_type( $field, 'block' ) );
	}

	public function test_has_storage_type_false_for_missing_type(): void {
		$registry = Storage_Registry::instance();
		$field    = array( 'id' => 'title' );

		$this->assertFalse( $registry->has_storage_type( $field, 'postMeta' ) );
	}

	public function test_has_storage_type_true_for_explicit_type(): void {
		$registry = Storage_Registry::instance();
		$field    = array(
			'id'      => 'title',
			'storage' => array( 'type' => array( 'block', 'postMeta' ) ),
		);

		$this->assertTrue( $registry->has_storage_type( $field, 'block' ) );
		$this->assertTrue( $registry->has_storage_type( $field, 'postMeta' ) );
		$this->assertFalse( $registry->has_storage_type( $field, 'option' ) );
	}

	// process_field()

	public function test_process_field_calls_handler_register(): void {
		$handler = $this->createMock( Storage_Handler_Interface::class );
		$handler->method( 'get_type' )->willReturn( 'block' );
		$handler->expects( $this->once() )
			->method( 'register' )
			->with( 'test/block', $this->arrayHasKey( 'id' ) );

		$registry = Storage_Registry::instance();
		$registry->register_handler( $handler );

		$field = array( 'id' => 'title', 'type' => 'text' );
		$registry->process_field( 'test/block', $field );
	}

	public function test_process_field_skips_unregistered_handler(): void {
		$registry = Storage_Registry::instance();

		$field = array(
			'id'      => 'title',
			'storage' => array( 'type' => 'nonexistent' ),
		);

		// Should not throw.
		$registry->process_field( 'test/block', $field );
		$this->assertTrue( true );
	}

	// process_block_fields()

	public function test_process_block_fields_skips_fields_without_id(): void {
		$handler = $this->createMock( Storage_Handler_Interface::class );
		$handler->method( 'get_type' )->willReturn( 'block' );
		$handler->expects( $this->never() )->method( 'register' );

		$registry = Storage_Registry::instance();
		$registry->register_handler( $handler );

		$fields = array(
			array( 'type' => 'text' ),
		);
		$registry->process_block_fields( 'test/block', $fields );
	}

	public function test_process_block_fields_processes_each_field(): void {
		$handler = $this->createMock( Storage_Handler_Interface::class );
		$handler->method( 'get_type' )->willReturn( 'block' );
		$handler->expects( $this->exactly( 2 ) )->method( 'register' );

		$registry = Storage_Registry::instance();
		$registry->register_handler( $handler );

		$fields = array(
			array( 'id' => 'title', 'type' => 'text' ),
			array( 'id' => 'content', 'type' => 'textarea' ),
		);
		$registry->process_block_fields( 'test/block', $fields );
	}

	public function test_process_block_fields_recurses_into_nested_fields(): void {
		$handler = $this->createMock( Storage_Handler_Interface::class );
		$handler->method( 'get_type' )->willReturn( 'block' );
		$handler->expects( $this->exactly( 3 ) )->method( 'register' );

		$registry = Storage_Registry::instance();
		$registry->register_handler( $handler );

		$fields = array(
			array(
				'id'     => 'group',
				'type'   => 'group',
				'fields' => array(
					array( 'id' => 'nested_a', 'type' => 'text' ),
					array( 'id' => 'nested_b', 'type' => 'text' ),
				),
			),
		);
		$registry->process_block_fields( 'test/block', $fields );
	}

	public function test_process_block_fields_with_empty_array(): void {
		$registry = Storage_Registry::instance();
		$registry->process_block_fields( 'test/block', array() );

		$this->assertTrue( true );
	}

	public function test_process_block_fields_marks_repeater_descendants_as_array_storage(): void {
		$registered_fields = array();

		$handler = $this->createMock( Storage_Handler_Interface::class );
		$handler->method( 'get_type' )->willReturn( 'block' );
		$handler->method( 'register' )->willReturnCallback(
			function ( string $block_name, array $field ) use ( &$registered_fields ): void {
				$registered_fields[] = $field;
			}
		);

		$registry = Storage_Registry::instance();
		$registry->register_handler( $handler );

		$fields = array(
			array(
				'id'         => 'items',
				'type'       => 'repeater',
				'attributes' => array(
					array( 'id' => 'label', 'type' => 'text' ),
				),
			),
		);

		$registry->process_block_fields( 'test/block', $fields );

		$nested_field = array_values(
			array_filter(
				$registered_fields,
				fn( array $field ): bool => 'label' === ( $field['id'] ?? '' )
			)
		)[0] ?? null;

		$this->assertNotNull( $nested_field );
		$this->assertSame( 'array', $nested_field['__blockstudio_storage_value_type'] ?? null );
	}

	private function create_handler( string $type ): Storage_Handler_Interface {
		$handler = $this->createMock( Storage_Handler_Interface::class );
		$handler->method( 'get_type' )->willReturn( $type );
		return $handler;
	}
}
