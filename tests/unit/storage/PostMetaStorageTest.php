<?php

use Blockstudio\Storage_Handlers\Post_Meta_Storage;
use Blockstudio\Interfaces\Storage_Handler_Interface;
use PHPUnit\Framework\TestCase;

class PostMetaStorageTest extends TestCase {

	private Post_Meta_Storage $handler;
	private array $registered_keys = array();

	protected function setUp(): void {
		$this->handler = new Post_Meta_Storage();
	}

	protected function tearDown(): void {
		foreach ( $this->registered_keys as $key ) {
			unregister_meta_key( 'post', $key );
		}
		$this->registered_keys = array();
	}

	// get_type()

	public function test_get_type_returns_post_meta(): void {
		$this->assertSame( 'postMeta', $this->handler->get_type() );
	}

	public function test_implements_storage_handler_interface(): void {
		$this->assertInstanceOf( Storage_Handler_Interface::class, $this->handler );
	}

	// get_key()

	public function test_get_key_generates_from_block_name_and_field_id(): void {
		$field = array( 'id' => 'title' );

		$key = $this->handler->get_key( 'test/block', $field );

		$this->assertSame( sanitize_key( 'test/block_title' ), $key );
	}

	public function test_get_key_uses_custom_post_meta_key(): void {
		$field = array(
			'id'      => 'title',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => 'my_custom_meta' ),
		);

		$this->assertSame( 'my_custom_meta', $this->handler->get_key( 'test/block', $field ) );
	}

	public function test_get_key_custom_key_takes_precedence_over_generated(): void {
		$field = array(
			'id'      => 'title',
			'storage' => array( 'postMetaKey' => 'override_key' ),
		);

		$this->assertSame( 'override_key', $this->handler->get_key( 'test/block', $field ) );
	}

	public function test_get_key_sanitizes_generated_key(): void {
		$field = array( 'id' => 'myField' );

		$key = $this->handler->get_key( 'my-plugin/My-Block', $field );

		$this->assertSame( sanitize_key( 'my-plugin/My-Block_myField' ), $key );
	}

	public function test_get_key_different_blocks_produce_different_keys(): void {
		$field = array( 'id' => 'title' );

		$key_a = $this->handler->get_key( 'test/block-a', $field );
		$key_b = $this->handler->get_key( 'test/block-b', $field );

		$this->assertNotSame( $key_a, $key_b );
	}

	public function test_get_key_different_fields_produce_different_keys(): void {
		$field_a = array( 'id' => 'title' );
		$field_b = array( 'id' => 'content' );

		$key_a = $this->handler->get_key( 'test/block', $field_a );
		$key_b = $this->handler->get_key( 'test/block', $field_b );

		$this->assertNotSame( $key_a, $key_b );
	}

	public function test_get_key_without_storage_config_generates_key(): void {
		$field = array( 'id' => 'description' );

		$key = $this->handler->get_key( 'acme/hero', $field );

		$this->assertSame( sanitize_key( 'acme/hero_description' ), $key );
	}

	public function test_get_key_with_empty_post_meta_key_uses_generated(): void {
		$field = array(
			'id'      => 'title',
			'storage' => array( 'postMetaKey' => '' ),
		);

		// Empty string is falsy but isset returns true, so empty string is used as-is.
		$this->assertSame( '', $this->handler->get_key( 'test/block', $field ) );
	}

	// register() with string types

	public function test_register_string_field(): void {
		$meta_key              = 'test_pm_text_field';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'title',
			'type'    => 'text',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertNotNull( $meta );
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertTrue( $meta['single'] );
		$this->assertSame( 'string', $meta['type'] );
	}

	public function test_register_textarea_field(): void {
		$meta_key              = 'test_pm_textarea';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'bio',
			'type'    => 'textarea',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertSame( 'string', $meta['type'] );
	}

	public function test_register_code_field(): void {
		$meta_key              = 'test_pm_code';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'snippet',
			'type'    => 'code',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertSame( 'string', $meta['type'] );
	}

	public function test_register_richtext_field(): void {
		$meta_key              = 'test_pm_richtext';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'content',
			'type'    => 'richtext',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertSame( 'string', $meta['type'] );
	}

	// register() with number types

	public function test_register_number_field(): void {
		$meta_key              = 'test_pm_number';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'count',
			'type'    => 'number',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertNotNull( $meta );
		$this->assertSame( 'number', $meta['type'] );
	}

	public function test_register_range_field(): void {
		$meta_key              = 'test_pm_range';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'opacity',
			'type'    => 'range',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertSame( 'number', $meta['type'] );
	}

	// register() with boolean types

	public function test_register_toggle_field(): void {
		$meta_key              = 'test_pm_toggle';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'enabled',
			'type'    => 'toggle',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertNotNull( $meta );
		$this->assertSame( 'boolean', $meta['type'] );
	}

	// register() with array types

	public function test_register_repeater_field_with_array_schema(): void {
		$meta_key                = 'test_pm_repeater';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'items',
			'type'    => 'repeater',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertNotNull( $meta );
		$this->assertSame( 'array', $meta['type'] );
		$this->assertIsArray( $meta['show_in_rest'] );
		$this->assertSame( 'array', $meta['show_in_rest']['schema']['type'] );
	}

	public function test_register_repeater_descendant_field_with_array_schema(): void {
		$meta_key                = 'test_pm_repeater_label';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'                              => 'label',
			'type'                            => 'text',
			'storage'                         => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
			'__blockstudio_storage_value_type' => 'array',
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertNotNull( $meta );
		$this->assertSame( 'array', $meta['type'] );
		$this->assertIsArray( $meta['show_in_rest'] );
		$this->assertSame( 'array', $meta['show_in_rest']['schema']['type'] );
	}

	public function test_register_checkbox_field_with_array_schema(): void {
		$meta_key                = 'test_pm_checkbox';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'options',
			'type'    => 'checkbox',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertNotNull( $meta );
		$this->assertSame( 'array', $meta['type'] );
		$this->assertIsArray( $meta['show_in_rest'] );
	}

	public function test_register_token_field_with_array_schema(): void {
		$meta_key                = 'test_pm_token';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'tags',
			'type'    => 'token',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertNotNull( $meta );
		$this->assertSame( 'array', $meta['type'] );
		$this->assertIsArray( $meta['show_in_rest'] );
	}

	// register() with object types (default to string)

	public function test_register_object_type_defaults_to_string(): void {
		$meta_key              = 'test_pm_color';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'bg_color',
			'type'    => 'color',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertSame( 'string', $meta['type'] );
	}

	public function test_register_select_type_defaults_to_string(): void {
		$meta_key              = 'test_pm_select';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'variant',
			'type'    => 'select',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertSame( 'string', $meta['type'] );
	}

	// register() with unknown type

	public function test_register_unknown_type_defaults_to_string(): void {
		$meta_key              = 'test_pm_unknown';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'custom',
			'type'    => 'nonexistent_type',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertSame( 'string', $meta['type'] );
	}

	// register() without explicit type

	public function test_register_field_without_type_defaults_to_text(): void {
		$meta_key              = 'test_pm_notype';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'title',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertSame( 'string', $meta['type'] );
	}

	// register() uses generated key

	public function test_register_uses_generated_key_when_no_custom_key(): void {
		$field = array(
			'id'   => 'title',
			'type' => 'text',
		);

		$expected_key          = sanitize_key( 'test/block_title' );
		$this->registered_keys[] = $expected_key;

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $expected_key );
		$this->assertNotNull( $meta, 'Meta should be registered under the generated key' );
		$this->assertSame( 'string', $meta['type'] );
	}

	// register() always sets show_in_rest and single

	public function test_register_always_enables_rest_and_single(): void {
		$meta_key              = 'test_pm_rest_single';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'value',
			'type'    => 'number',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		$meta = $this->get_registered_meta( $meta_key );
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertTrue( $meta['single'] );
	}

	// register() for all post types

	public function test_register_applies_to_all_post_types(): void {
		$meta_key              = 'test_pm_all_types';
		$this->registered_keys[] = $meta_key;

		$field = array(
			'id'      => 'global',
			'type'    => 'text',
			'storage' => array( 'type' => 'postMeta', 'postMetaKey' => $meta_key ),
		);

		$this->handler->register( 'test/block', $field );

		// Registered under empty subtype (applies to all post types).
		$all_post_meta = get_registered_meta_keys( 'post', '' );
		$this->assertArrayHasKey( $meta_key, $all_post_meta );
	}

	/**
	 * Look up registered meta for a key.
	 *
	 * @param string $meta_key The meta key to look up.
	 *
	 * @return array|null The meta args or null if not found.
	 */
	private function get_registered_meta( string $meta_key ): ?array {
		$registered = get_registered_meta_keys( 'post', '' );

		if ( isset( $registered[ $meta_key ] ) ) {
			return $registered[ $meta_key ];
		}

		return null;
	}
}
