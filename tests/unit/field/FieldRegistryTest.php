<?php

use Blockstudio\Field_Registry;
use PHPUnit\Framework\TestCase;

class FieldRegistryTest extends TestCase {

	protected function setUp(): void {
		Field_Registry::instance()->reset();
	}

	protected function tearDown(): void {
		Field_Registry::instance()->reset();
	}

	// instance()

	public function test_instance_returns_singleton(): void {
		$a = Field_Registry::instance();
		$b = Field_Registry::instance();

		$this->assertSame( $a, $b );
	}

	public function test_instance_returns_field_registry(): void {
		$this->assertInstanceOf( Field_Registry::class, Field_Registry::instance() );
	}

	// register() / get()

	public function test_register_and_get_field(): void {
		$definition = array(
			'attributes' => array(
				'title' => array( 'type' => 'string' ),
			),
		);

		Field_Registry::instance()->register( 'my-field', $definition );

		$this->assertSame( $definition, Field_Registry::instance()->get( 'my-field' ) );
	}

	public function test_get_returns_null_for_unknown_field(): void {
		$this->assertNull( Field_Registry::instance()->get( 'nonexistent' ) );
	}

	public function test_register_overwrites_existing_field(): void {
		$first = array(
			'attributes' => array( 'a' => array( 'type' => 'string' ) ),
		);
		$second = array(
			'attributes' => array( 'b' => array( 'type' => 'number' ) ),
		);

		$registry = Field_Registry::instance();
		$registry->register( 'my-field', $first );
		$registry->register( 'my-field', $second );

		$this->assertSame( $second, $registry->get( 'my-field' ) );
	}

	public function test_register_multiple_fields(): void {
		$registry = Field_Registry::instance();

		$field_a = array( 'attributes' => array( 'x' => array( 'type' => 'string' ) ) );
		$field_b = array( 'attributes' => array( 'y' => array( 'type' => 'number' ) ) );

		$registry->register( 'field-a', $field_a );
		$registry->register( 'field-b', $field_b );

		$this->assertSame( $field_a, $registry->get( 'field-a' ) );
		$this->assertSame( $field_b, $registry->get( 'field-b' ) );
	}

	public function test_register_with_empty_definition(): void {
		$registry = Field_Registry::instance();
		$registry->register( 'empty', array() );

		$this->assertSame( array(), $registry->get( 'empty' ) );
	}

	// has()

	public function test_has_returns_true_for_registered_field(): void {
		$registry = Field_Registry::instance();
		$registry->register( 'exists', array( 'attributes' => array() ) );

		$this->assertTrue( $registry->has( 'exists' ) );
	}

	public function test_has_returns_false_for_unknown_field(): void {
		$this->assertFalse( Field_Registry::instance()->has( 'missing' ) );
	}

	public function test_has_returns_false_after_reset(): void {
		$registry = Field_Registry::instance();
		$registry->register( 'temp', array( 'attributes' => array() ) );
		$registry->reset();

		$this->assertFalse( $registry->has( 'temp' ) );
	}

	// all()

	public function test_all_returns_empty_array_initially(): void {
		$this->assertSame( array(), Field_Registry::instance()->all() );
	}

	public function test_all_returns_all_registered_fields(): void {
		$registry = Field_Registry::instance();

		$field_a = array( 'attributes' => array( 'a' => array( 'type' => 'string' ) ) );
		$field_b = array( 'attributes' => array( 'b' => array( 'type' => 'number' ) ) );
		$field_c = array( 'attributes' => array( 'c' => array( 'type' => 'boolean' ) ) );

		$registry->register( 'alpha', $field_a );
		$registry->register( 'beta', $field_b );
		$registry->register( 'gamma', $field_c );

		$all = $registry->all();

		$this->assertCount( 3, $all );
		$this->assertArrayHasKey( 'alpha', $all );
		$this->assertArrayHasKey( 'beta', $all );
		$this->assertArrayHasKey( 'gamma', $all );
		$this->assertSame( $field_a, $all['alpha'] );
	}

	// reset()

	public function test_reset_clears_all_fields(): void {
		$registry = Field_Registry::instance();
		$registry->register( 'one', array( 'attributes' => array() ) );
		$registry->register( 'two', array( 'attributes' => array() ) );

		$registry->reset();

		$this->assertSame( array(), $registry->all() );
		$this->assertNull( $registry->get( 'one' ) );
		$this->assertNull( $registry->get( 'two' ) );
	}

	public function test_reset_allows_re_registration(): void {
		$registry = Field_Registry::instance();
		$registry->register( 'field', array( 'v' => 1 ) );
		$registry->reset();
		$registry->register( 'field', array( 'v' => 2 ) );

		$this->assertSame( array( 'v' => 2 ), $registry->get( 'field' ) );
	}

	// Edge cases

	public function test_register_with_special_characters_in_name(): void {
		$registry   = Field_Registry::instance();
		$definition = array( 'attributes' => array() );

		$registry->register( 'my-plugin/custom-field', $definition );

		$this->assertTrue( $registry->has( 'my-plugin/custom-field' ) );
		$this->assertSame( $definition, $registry->get( 'my-plugin/custom-field' ) );
	}

	public function test_register_preserves_complex_definition(): void {
		$definition = array(
			'name'       => 'address',
			'attributes' => array(
				'street'  => array( 'type' => 'string', 'default' => '' ),
				'city'    => array( 'type' => 'string', 'default' => '' ),
				'zip'     => array( 'type' => 'string', 'default' => '' ),
				'country' => array(
					'type'    => 'object',
					'default' => null,
				),
			),
			'meta' => array(
				'version' => '1.0',
			),
		);

		$registry = Field_Registry::instance();
		$registry->register( 'address', $definition );

		$this->assertSame( $definition, $registry->get( 'address' ) );
	}
}
