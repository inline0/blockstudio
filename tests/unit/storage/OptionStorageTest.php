<?php

use Blockstudio\Storage_Handlers\Option_Storage;
use Blockstudio\Field_Type_Registry;
use PHPUnit\Framework\TestCase;

class OptionStorageTest extends TestCase {

	private array $registered_keys = array();

	protected function setUp(): void {
		Field_Type_Registry::instance()->reset();
	}

	protected function tearDown(): void {
		foreach ( $this->registered_keys as $key ) {
			unregister_setting( 'blockstudio', $key );
		}
		$this->registered_keys = array();
		Field_Type_Registry::instance()->reset();
	}

	public function test_register_array_field_has_items_schema(): void {
		$handler = new Option_Storage();

		$field = array(
			'id'      => 'links',
			'type'    => 'repeater',
			'storage' => array( 'type' => 'option', 'optionKey' => 'test_opt_links' ),
		);

		$this->registered_keys[] = 'test_opt_links';

		// Capture _doing_it_wrong notices.
		$notices = array();
		$capture = function ( $function_name ) use ( &$notices ) {
			$notices[] = $function_name;
		};
		add_action( 'doing_it_wrong_run', $capture );

		$handler->register( 'test/block', $field );

		remove_action( 'doing_it_wrong_run', $capture );

		$this->assertNotContains(
			'register_setting',
			$notices,
			'register_setting should not trigger _doing_it_wrong for array type'
		);

		// Verify the setting is registered with correct schema.
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'test_opt_links', $settings );
		$this->assertSame( 'array', $settings['test_opt_links']['type'] );

		$rest = $settings['test_opt_links']['show_in_rest'];
		$this->assertIsArray( $rest, 'show_in_rest should be an array with schema for array types' );
		$this->assertArrayHasKey( 'schema', $rest );
		$this->assertArrayHasKey( 'items', $rest['schema'] );
	}

	public function test_register_string_field_has_simple_show_in_rest(): void {
		$handler = new Option_Storage();

		$field = array(
			'id'      => 'logo',
			'type'    => 'text',
			'storage' => array( 'type' => 'option', 'optionKey' => 'test_opt_logo' ),
		);

		$this->registered_keys[] = 'test_opt_logo';
		$handler->register( 'test/block', $field );

		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'test_opt_logo', $settings );
		$this->assertTrue( $settings['test_opt_logo']['show_in_rest'] );
	}

	public function test_register_repeater_descendant_field_has_array_schema(): void {
		$handler = new Option_Storage();

		$field = array(
			'id'                              => 'label',
			'type'                            => 'text',
			'storage'                         => array( 'type' => 'option', 'optionKey' => 'test_opt_rep_label' ),
			'__blockstudio_storage_value_type' => 'array',
		);

		$this->registered_keys[] = 'test_opt_rep_label';
		$handler->register( 'test/block', $field );

		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'test_opt_rep_label', $settings );
		$this->assertSame( 'array', $settings['test_opt_rep_label']['type'] );
		$this->assertIsArray( $settings['test_opt_rep_label']['show_in_rest'] );
	}

	public function test_register_custom_object_field_has_object_schema(): void {
		$handler = new Option_Storage();

		Field_Type_Registry::instance()->register(
			'test/dimensions',
			array(
				'attribute' => 'object',
				'storage'   => array(
					'type'        => 'object',
					'rest_schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'string' ),
					),
				),
			)
		);

		$field = array(
			'id'      => 'margin',
			'type'    => 'test/dimensions',
			'storage' => array( 'type' => 'option', 'optionKey' => 'test_opt_custom_object' ),
		);

		$this->registered_keys[] = 'test_opt_custom_object';
		$handler->register( 'test/block', $field );

		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'test_opt_custom_object', $settings );
		$this->assertSame( 'object', $settings['test_opt_custom_object']['type'] );
		$this->assertIsArray( $settings['test_opt_custom_object']['show_in_rest'] );
		$this->assertSame(
			array( 'type' => 'string' ),
			$settings['test_opt_custom_object']['show_in_rest']['schema']['additionalProperties']
		);
	}

	public function test_register_custom_array_field_has_array_schema(): void {
		$handler = new Option_Storage();

		Field_Type_Registry::instance()->register(
			'test/token-list',
			array(
				'attribute' => 'array',
				'storage'   => array( 'type' => 'array' ),
			)
		);

		$field = array(
			'id'      => 'tokens',
			'type'    => 'test/token-list',
			'storage' => array( 'type' => 'option', 'optionKey' => 'test_opt_custom_array' ),
		);

		$this->registered_keys[] = 'test_opt_custom_array';
		$handler->register( 'test/block', $field );

		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'test_opt_custom_array', $settings );
		$this->assertSame( 'array', $settings['test_opt_custom_array']['type'] );
		$this->assertArrayHasKey( 'items', $settings['test_opt_custom_array']['show_in_rest']['schema'] );
	}
}
