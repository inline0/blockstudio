<?php

use Blockstudio\Field_Type_Config;
use Blockstudio\Field_Type_Registry;
use PHPUnit\Framework\TestCase;

class FieldTypeRegistryTest extends TestCase {

	private Field_Type_Registry $registry;

	protected function setUp(): void {
		$this->registry = Field_Type_Registry::instance();
		$this->registry->reset();
	}

	protected function tearDown(): void {
		wp_dequeue_script( 'test-field-dimensions' );
		wp_deregister_script( 'test-field-dimensions' );
		wp_dequeue_script( 'test-field-unused' );
		wp_deregister_script( 'test-field-unused' );
		wp_dequeue_style( 'test-field-dimensions' );
		wp_deregister_style( 'test-field-dimensions' );
		wp_dequeue_style( 'test-field-unused' );
		wp_deregister_style( 'test-field-unused' );
		$this->registry->reset();
	}

	public function test_register_valid_custom_field_type(): void {
		$result = $this->registry->register(
			'test/dimensions',
			array(
				'attribute' => 'object',
				'default'   => array(),
			)
		);

		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->has( 'test/dimensions' ) );
		$this->assertTrue( $this->registry->is_custom_type( 'test/dimensions' ) );
		$this->assertSame( 'object', Field_Type_Config::get_attribute_type( 'test/dimensions' ) );
		$this->assertSame( array(), Field_Type_Config::get_default_value( 'test/dimensions' ) );
	}

	/**
	 * @dataProvider invalid_name_provider
	 */
	public function test_invalid_names_are_rejected( string $name ): void {
		$this->assertFalse(
			$this->registry->register(
				$name,
				array( 'attribute' => 'string' )
			)
		);

		$this->assertFalse( $this->registry->is_custom_type( $name ) );
	}

	public static function invalid_name_provider(): array {
		return array(
			'missing namespace'                  => array( 'dimensions' ),
			'camel case'                         => array( 'test/dimensionSingle' ),
			'custom namespace'                   => array( 'custom/dimensions' ),
			'blockstudio namespace'              => array( 'blockstudio/dimensions' ),
			'hyphenated namespace requires slash' => array( 'test-dimensions' ),
		);
	}

	public function test_invalid_definition_is_rejected(): void {
		$this->assertFalse( $this->registry->register( 'test/missing-attribute', array() ) );
		$this->assertFalse(
			$this->registry->register(
				'test/bad-attribute',
				array( 'attribute' => 'resource' )
			)
		);
	}

	public function test_duplicate_registration_is_rejected(): void {
		$definition = array( 'attribute' => 'string' );

		$this->assertTrue( $this->registry->register( 'test/text-options', $definition ) );
		$this->assertFalse( $this->registry->register( 'test/text-options', $definition ) );
	}

	public function test_helper_functions_feed_registry(): void {
		$this->assertTrue(
			bs_register_field_type(
				'test/helper',
				array( 'attribute' => 'boolean' )
			)
		);

		$this->assertTrue( $this->registry->is_custom_type( 'test/helper' ) );
		$this->assertSame( 'boolean', Field_Type_Config::get_attribute_type( 'test/helper' ) );
		$this->assertTrue( bs_unregister_field_type( 'test/helper' ) );
		$this->assertFalse( $this->registry->is_custom_type( 'test/helper' ) );
	}

	public function test_filter_registrations_feed_registry(): void {
		$filter = function ( array $types ): array {
			$types['test/from-filter'] = array(
				'attribute'     => 'array',
				'editor_script' => 'test-field-from-filter',
			);

			return $types;
		};

		add_filter( 'blockstudio/field_types', $filter );
		$this->registry->reset();

		try {
			$this->assertTrue( $this->registry->is_custom_type( 'test/from-filter' ) );
			$this->assertSame( 'array', Field_Type_Config::get_attribute_type( 'test/from-filter' ) );
		} finally {
			remove_filter( 'blockstudio/field_types', $filter );
		}
	}

	public function test_build_attribute_uses_definition_default_and_field_overrides(): void {
		$this->registry->register(
			'test/dimensions',
			array(
				'attribute' => 'object',
				'default'   => array( 'top' => 'sm' ),
			)
		);

		$attribute = $this->registry->build_attribute(
			array(
				'id'       => 'margin',
				'type'     => 'test/dimensions',
				'fallback' => array( 'top' => 'none' ),
				'set'      => array(
					array(
						'attribute' => 'class',
						'value'     => 'mt-{attributes.margin.top}',
					),
				),
			),
			'margin'
		);

		$this->assertSame( 'object', $attribute['type'] );
		$this->assertSame( 'test/dimensions', $attribute['field'] );
		$this->assertSame( array( 'top' => 'sm' ), $attribute['default'] );
		$this->assertSame( array( 'top' => 'none' ), $attribute['fallback'] );
		$this->assertSame( 'margin', $attribute['id'] );
		$this->assertArrayHasKey( 'set', $attribute );
	}

	public function test_produces_attribute_false_builds_no_attribute(): void {
		$this->registry->register(
			'test/display-only',
			array(
				'attribute'          => null,
				'produces_attribute' => false,
			)
		);

		$this->assertFalse( Field_Type_Config::produces_attribute( 'test/display-only' ) );
		$this->assertNull(
			$this->registry->build_attribute(
				array(
					'id'   => 'notice',
					'type' => 'test/display-only',
				),
				'notice'
			)
		);
	}

	public function test_mark_used_fields_tracks_nested_custom_types(): void {
		$this->registry->register( 'test/dimensions', array( 'attribute' => 'object' ) );
		$this->registry->register( 'test/text-options', array( 'attribute' => 'string' ) );

		$this->registry->mark_used_fields(
			array(
				array(
					'id'         => 'settings',
					'type'       => 'group',
					'attributes' => array(
						array( 'id' => 'margin', 'type' => 'test/dimensions' ),
					),
				),
				array(
					'type' => 'tabs',
					'tabs' => array(
						array(
							'attributes' => array(
								array( 'id' => 'mode', 'type' => 'test/text-options' ),
							),
						),
					),
				),
			)
		);

		$ref  = new ReflectionClass( $this->registry );
		$used = $ref->getProperty( 'used' );
		$used->setAccessible( true );

		$this->assertArrayHasKey( 'test/dimensions', $used->getValue( $this->registry ) );
		$this->assertArrayHasKey( 'test/text-options', $used->getValue( $this->registry ) );
	}

	public function test_enqueue_editor_assets_for_used_custom_field_type(): void {
		wp_register_script( 'test-field-dimensions', false, array( 'blockstudio-blocks' ), '1.0.0', true );
		wp_register_style( 'test-field-dimensions', false, array(), '1.0.0' );

		$this->registry->register(
			'test/dimensions',
			array(
				'attribute'     => 'object',
				'editor_script' => 'test-field-dimensions',
				'editor_style'  => 'test-field-dimensions',
			)
		);

		$this->registry->mark_used_fields(
			array(
				array(
					'id'   => 'margin',
					'type' => 'test/dimensions',
				),
			)
		);

		$this->registry->enqueue_editor_assets();

		$this->assertTrue( wp_script_is( 'test-field-dimensions', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'test-field-dimensions', 'enqueued' ) );
	}

	public function test_editor_assets_for_unused_custom_field_type_do_not_enqueue(): void {
		wp_register_script( 'test-field-unused', false, array( 'blockstudio-blocks' ), '1.0.0', true );
		wp_register_style( 'test-field-unused', false, array(), '1.0.0' );

		$this->registry->register(
			'test/unused',
			array(
				'attribute'     => 'string',
				'editor_script' => 'test-field-unused',
				'editor_style'  => 'test-field-unused',
			)
		);

		$this->registry->enqueue_editor_assets();

		$this->assertFalse( wp_script_is( 'test-field-unused', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'test-field-unused', 'enqueued' ) );
	}

	public function test_missing_editor_asset_handles_do_not_fatal(): void {
		$this->registry->register(
			'test/missing-assets',
			array(
				'attribute'     => 'string',
				'editor_script' => 'test-field-missing',
				'editor_style'  => 'test-field-missing',
			)
		);

		$this->registry->mark_used_fields(
			array(
				array(
					'id'   => 'missing',
					'type' => 'test/missing-assets',
				),
			)
		);

		$this->registry->enqueue_editor_assets();

		$this->assertFalse( wp_script_is( 'test-field-missing', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'test-field-missing', 'enqueued' ) );
	}

	public function test_custom_object_rest_schema_wraps_for_array_storage(): void {
		$this->registry->register(
			'test/dimensions',
			array(
				'attribute' => 'object',
				'storage'   => array(
					'rest_schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'string' ),
					),
				),
			)
		);

		$schema = $this->registry->get_storage_rest_schema(
			array(
				'id'   => 'dimensions',
				'type' => 'test/dimensions',
			),
			'array'
		);

		$this->assertSame( 'array', $schema['type'] );
		$this->assertSame( 'object', $schema['items']['type'] );
		$this->assertSame( array( 'type' => 'string' ), $schema['items']['additionalProperties'] );
	}

	public function test_generic_array_storage_schema_does_not_force_object_items(): void {
		$schema = $this->registry->get_storage_rest_schema(
			array(
				'id'   => 'tags',
				'type' => 'test/string-list',
			),
			'array'
		);

		$this->assertSame( array( 'type' => 'array', 'items' => array() ), $schema );
	}
}
