<?php

use Blockstudio\Field_Type_Config;
use PHPUnit\Framework\TestCase;

class FieldTypeConfigTest extends TestCase {

	// TYPES constant

	public function test_types_contains_all_expected_field_types(): void {
		$expected = array(
			'code', 'date', 'datetime', 'text', 'textarea', 'unit', 'classes',
			'richtext', 'wysiwyg',
			'number', 'range',
			'toggle',
			'color', 'gradient', 'icon', 'link', 'radio', 'select',
			'repeater', 'checkbox', 'token', 'attributes',
			'files', 'group', 'tabs', 'message',
		);

		foreach ( $expected as $type ) {
			$this->assertArrayHasKey( $type, Field_Type_Config::TYPES, "Missing type: {$type}" );
		}
	}

	public function test_every_type_has_attribute_key(): void {
		foreach ( Field_Type_Config::TYPES as $type => $config ) {
			$this->assertArrayHasKey( 'attribute', $config, "Type '{$type}' missing 'attribute' key" );
		}
	}

	public function test_every_type_has_default_key(): void {
		foreach ( Field_Type_Config::TYPES as $type => $config ) {
			$this->assertArrayHasKey( 'default', $config, "Type '{$type}' missing 'default' key" );
		}
	}

	// get_attribute_type()

	public function test_get_attribute_type_for_string_types(): void {
		$this->assertSame( 'string', Field_Type_Config::get_attribute_type( 'text' ) );
		$this->assertSame( 'string', Field_Type_Config::get_attribute_type( 'textarea' ) );
		$this->assertSame( 'string', Field_Type_Config::get_attribute_type( 'code' ) );
		$this->assertSame( 'string', Field_Type_Config::get_attribute_type( 'date' ) );
		$this->assertSame( 'string', Field_Type_Config::get_attribute_type( 'datetime' ) );
		$this->assertSame( 'string', Field_Type_Config::get_attribute_type( 'unit' ) );
		$this->assertSame( 'string', Field_Type_Config::get_attribute_type( 'classes' ) );
		$this->assertSame( 'string', Field_Type_Config::get_attribute_type( 'richtext' ) );
		$this->assertSame( 'string', Field_Type_Config::get_attribute_type( 'wysiwyg' ) );
	}

	public function test_get_attribute_type_for_number_types(): void {
		$this->assertSame( 'number', Field_Type_Config::get_attribute_type( 'number' ) );
		$this->assertSame( 'number', Field_Type_Config::get_attribute_type( 'range' ) );
	}

	public function test_get_attribute_type_for_boolean_types(): void {
		$this->assertSame( 'boolean', Field_Type_Config::get_attribute_type( 'toggle' ) );
	}

	public function test_get_attribute_type_for_object_types(): void {
		$this->assertSame( 'object', Field_Type_Config::get_attribute_type( 'color' ) );
		$this->assertSame( 'object', Field_Type_Config::get_attribute_type( 'gradient' ) );
		$this->assertSame( 'object', Field_Type_Config::get_attribute_type( 'icon' ) );
		$this->assertSame( 'object', Field_Type_Config::get_attribute_type( 'link' ) );
		$this->assertSame( 'object', Field_Type_Config::get_attribute_type( 'radio' ) );
		$this->assertSame( 'object', Field_Type_Config::get_attribute_type( 'select' ) );
		$this->assertSame( 'object', Field_Type_Config::get_attribute_type( 'group' ) );
	}

	public function test_get_attribute_type_for_array_types(): void {
		$this->assertSame( 'array', Field_Type_Config::get_attribute_type( 'repeater' ) );
		$this->assertSame( 'array', Field_Type_Config::get_attribute_type( 'checkbox' ) );
		$this->assertSame( 'array', Field_Type_Config::get_attribute_type( 'token' ) );
		$this->assertSame( 'array', Field_Type_Config::get_attribute_type( 'attributes' ) );
	}

	public function test_get_attribute_type_for_files_returns_array_of_types(): void {
		$result = Field_Type_Config::get_attribute_type( 'files' );
		$this->assertIsArray( $result );
		$this->assertSame( array( 'number', 'object', 'array' ), $result );
	}

	public function test_get_attribute_type_for_null_attribute_types(): void {
		$this->assertNull( Field_Type_Config::get_attribute_type( 'tabs' ) );
		$this->assertNull( Field_Type_Config::get_attribute_type( 'message' ) );
	}

	public function test_get_attribute_type_for_unknown_type(): void {
		$this->assertNull( Field_Type_Config::get_attribute_type( 'nonexistent' ) );
	}

	// get_default_value()

	public function test_get_default_value_for_string_types(): void {
		$this->assertSame( '', Field_Type_Config::get_default_value( 'text' ) );
		$this->assertSame( '', Field_Type_Config::get_default_value( 'textarea' ) );
		$this->assertSame( '', Field_Type_Config::get_default_value( 'code' ) );
		$this->assertSame( '', Field_Type_Config::get_default_value( 'richtext' ) );
	}

	public function test_get_default_value_for_number_types(): void {
		$this->assertSame( 0, Field_Type_Config::get_default_value( 'number' ) );
		$this->assertSame( 0, Field_Type_Config::get_default_value( 'range' ) );
	}

	public function test_get_default_value_for_boolean_types(): void {
		$this->assertSame( false, Field_Type_Config::get_default_value( 'toggle' ) );
	}

	public function test_get_default_value_for_object_types(): void {
		$this->assertNull( Field_Type_Config::get_default_value( 'color' ) );
		$this->assertNull( Field_Type_Config::get_default_value( 'select' ) );
		$this->assertNull( Field_Type_Config::get_default_value( 'group' ) );
	}

	public function test_get_default_value_for_array_types(): void {
		$this->assertSame( array(), Field_Type_Config::get_default_value( 'repeater' ) );
		$this->assertSame( array(), Field_Type_Config::get_default_value( 'checkbox' ) );
		$this->assertSame( array(), Field_Type_Config::get_default_value( 'token' ) );
	}

	public function test_get_default_value_for_unknown_type(): void {
		$this->assertNull( Field_Type_Config::get_default_value( 'nonexistent' ) );
	}

	// is_string_type()

	public function test_is_string_type_true_for_all_string_types(): void {
		foreach ( Field_Type_Config::STRING_TYPES as $type ) {
			$this->assertTrue( Field_Type_Config::is_string_type( $type ), "Expected '{$type}' to be a string type" );
		}
	}

	public function test_is_string_type_false_for_non_string_types(): void {
		$this->assertFalse( Field_Type_Config::is_string_type( 'number' ) );
		$this->assertFalse( Field_Type_Config::is_string_type( 'toggle' ) );
		$this->assertFalse( Field_Type_Config::is_string_type( 'select' ) );
		$this->assertFalse( Field_Type_Config::is_string_type( 'repeater' ) );
		$this->assertFalse( Field_Type_Config::is_string_type( 'unknown' ) );
	}

	// is_number_type()

	public function test_is_number_type_true_for_all_number_types(): void {
		foreach ( Field_Type_Config::NUMBER_TYPES as $type ) {
			$this->assertTrue( Field_Type_Config::is_number_type( $type ), "Expected '{$type}' to be a number type" );
		}
	}

	public function test_is_number_type_false_for_non_number_types(): void {
		$this->assertFalse( Field_Type_Config::is_number_type( 'text' ) );
		$this->assertFalse( Field_Type_Config::is_number_type( 'toggle' ) );
		$this->assertFalse( Field_Type_Config::is_number_type( 'unknown' ) );
	}

	// is_boolean_type()

	public function test_is_boolean_type_true_for_toggle(): void {
		$this->assertTrue( Field_Type_Config::is_boolean_type( 'toggle' ) );
	}

	public function test_is_boolean_type_false_for_non_boolean_types(): void {
		$this->assertFalse( Field_Type_Config::is_boolean_type( 'text' ) );
		$this->assertFalse( Field_Type_Config::is_boolean_type( 'number' ) );
		$this->assertFalse( Field_Type_Config::is_boolean_type( 'checkbox' ) );
		$this->assertFalse( Field_Type_Config::is_boolean_type( 'unknown' ) );
	}

	// is_object_type()

	public function test_is_object_type_true_for_all_object_types(): void {
		foreach ( Field_Type_Config::OBJECT_TYPES as $type ) {
			$this->assertTrue( Field_Type_Config::is_object_type( $type ), "Expected '{$type}' to be an object type" );
		}
	}

	public function test_is_object_type_false_for_non_object_types(): void {
		$this->assertFalse( Field_Type_Config::is_object_type( 'text' ) );
		$this->assertFalse( Field_Type_Config::is_object_type( 'repeater' ) );
		$this->assertFalse( Field_Type_Config::is_object_type( 'toggle' ) );
		$this->assertFalse( Field_Type_Config::is_object_type( 'unknown' ) );
	}

	// is_array_type()

	public function test_is_array_type_true_for_all_array_types(): void {
		foreach ( Field_Type_Config::ARRAY_TYPES as $type ) {
			$this->assertTrue( Field_Type_Config::is_array_type( $type ), "Expected '{$type}' to be an array type" );
		}
	}

	public function test_is_array_type_false_for_non_array_types(): void {
		$this->assertFalse( Field_Type_Config::is_array_type( 'text' ) );
		$this->assertFalse( Field_Type_Config::is_array_type( 'select' ) );
		$this->assertFalse( Field_Type_Config::is_array_type( 'toggle' ) );
		$this->assertFalse( Field_Type_Config::is_array_type( 'unknown' ) );
	}

	// has_options()

	public function test_has_options_true_for_option_types(): void {
		foreach ( Field_Type_Config::OPTION_TYPES as $type ) {
			$this->assertTrue( Field_Type_Config::has_options( $type ), "Expected '{$type}' to have options" );
		}
	}

	public function test_has_options_false_for_non_option_types(): void {
		$this->assertFalse( Field_Type_Config::has_options( 'text' ) );
		$this->assertFalse( Field_Type_Config::has_options( 'number' ) );
		$this->assertFalse( Field_Type_Config::has_options( 'toggle' ) );
		$this->assertFalse( Field_Type_Config::has_options( 'repeater' ) );
		$this->assertFalse( Field_Type_Config::has_options( 'unknown' ) );
	}

	// is_multiple_option_type()

	public function test_is_multiple_option_type_true_for_checkbox(): void {
		$this->assertTrue( Field_Type_Config::is_multiple_option_type( 'checkbox' ) );
	}

	public function test_is_multiple_option_type_true_for_token(): void {
		$this->assertTrue( Field_Type_Config::is_multiple_option_type( 'token' ) );
	}

	public function test_is_multiple_option_type_false_for_select(): void {
		$this->assertFalse( Field_Type_Config::is_multiple_option_type( 'select' ) );
	}

	public function test_is_multiple_option_type_false_for_radio(): void {
		$this->assertFalse( Field_Type_Config::is_multiple_option_type( 'radio' ) );
	}

	public function test_is_multiple_option_type_false_for_non_option_types(): void {
		$this->assertFalse( Field_Type_Config::is_multiple_option_type( 'text' ) );
		$this->assertFalse( Field_Type_Config::is_multiple_option_type( 'unknown' ) );
	}

	// is_container_type()

	public function test_is_container_type_true_for_all_container_types(): void {
		foreach ( Field_Type_Config::CONTAINER_TYPES as $type ) {
			$this->assertTrue( Field_Type_Config::is_container_type( $type ), "Expected '{$type}' to be a container type" );
		}
	}

	public function test_is_container_type_false_for_non_container_types(): void {
		$this->assertFalse( Field_Type_Config::is_container_type( 'text' ) );
		$this->assertFalse( Field_Type_Config::is_container_type( 'select' ) );
		$this->assertFalse( Field_Type_Config::is_container_type( 'checkbox' ) );
		$this->assertFalse( Field_Type_Config::is_container_type( 'unknown' ) );
	}

	// produces_attribute()

	public function test_produces_attribute_false_for_message(): void {
		$this->assertFalse( Field_Type_Config::produces_attribute( 'message' ) );
	}

	public function test_produces_attribute_true_for_standard_types(): void {
		$standard_types = array( 'text', 'number', 'toggle', 'select', 'repeater', 'group', 'color' );

		foreach ( $standard_types as $type ) {
			$this->assertTrue( Field_Type_Config::produces_attribute( $type ), "Expected '{$type}' to produce an attribute" );
		}
	}

	public function test_produces_attribute_true_for_tabs(): void {
		$this->assertTrue( Field_Type_Config::produces_attribute( 'tabs' ) );
	}

	public function test_produces_attribute_true_for_unknown_type(): void {
		$this->assertTrue( Field_Type_Config::produces_attribute( 'unknown' ) );
	}

	// is_multiple_options()

	public function test_is_multiple_options_true_for_checkbox(): void {
		$this->assertTrue( Field_Type_Config::is_multiple_options( 'checkbox', array() ) );
	}

	public function test_is_multiple_options_true_for_token(): void {
		$this->assertTrue( Field_Type_Config::is_multiple_options( 'token', array() ) );
	}

	public function test_is_multiple_options_true_for_select_with_multiple_flag(): void {
		$field = array( 'multiple' => true );
		$this->assertTrue( Field_Type_Config::is_multiple_options( 'select', $field ) );
	}

	public function test_is_multiple_options_false_for_select_without_multiple_flag(): void {
		$this->assertFalse( Field_Type_Config::is_multiple_options( 'select', array() ) );
	}

	public function test_is_multiple_options_false_for_select_with_multiple_false(): void {
		$field = array( 'multiple' => false );
		$this->assertFalse( Field_Type_Config::is_multiple_options( 'select', $field ) );
	}

	public function test_is_multiple_options_false_for_radio(): void {
		$this->assertFalse( Field_Type_Config::is_multiple_options( 'radio', array() ) );
	}

	public function test_is_multiple_options_false_for_text(): void {
		$this->assertFalse( Field_Type_Config::is_multiple_options( 'text', array() ) );
	}

	public function test_is_multiple_options_checkbox_ignores_multiple_flag(): void {
		$field = array( 'multiple' => false );
		$this->assertTrue( Field_Type_Config::is_multiple_options( 'checkbox', $field ) );
	}

	public function test_is_multiple_options_token_ignores_multiple_flag(): void {
		$field = array( 'multiple' => false );
		$this->assertTrue( Field_Type_Config::is_multiple_options( 'token', $field ) );
	}

	// richtext/wysiwyg source config

	public function test_richtext_has_html_source(): void {
		$this->assertSame( 'html', Field_Type_Config::TYPES['richtext']['source'] );
	}

	public function test_wysiwyg_has_html_source(): void {
		$this->assertSame( 'html', Field_Type_Config::TYPES['wysiwyg']['source'] );
	}

	public function test_text_has_no_source(): void {
		$this->assertArrayNotHasKey( 'source', Field_Type_Config::TYPES['text'] );
	}

	// Consistency checks between constants and TYPES

	public function test_string_types_constant_matches_types_with_string_attribute(): void {
		foreach ( Field_Type_Config::STRING_TYPES as $type ) {
			$this->assertSame(
				'string',
				Field_Type_Config::TYPES[ $type ]['attribute'],
				"STRING_TYPES contains '{$type}' but TYPES maps it to a different attribute"
			);
		}
	}

	public function test_number_types_constant_matches_types_with_number_attribute(): void {
		foreach ( Field_Type_Config::NUMBER_TYPES as $type ) {
			$this->assertSame(
				'number',
				Field_Type_Config::TYPES[ $type ]['attribute'],
				"NUMBER_TYPES contains '{$type}' but TYPES maps it to a different attribute"
			);
		}
	}

	public function test_boolean_types_constant_matches_types_with_boolean_attribute(): void {
		foreach ( Field_Type_Config::BOOLEAN_TYPES as $type ) {
			$this->assertSame(
				'boolean',
				Field_Type_Config::TYPES[ $type ]['attribute'],
				"BOOLEAN_TYPES contains '{$type}' but TYPES maps it to a different attribute"
			);
		}
	}

	public function test_object_types_constant_matches_types_with_object_attribute(): void {
		foreach ( Field_Type_Config::OBJECT_TYPES as $type ) {
			$this->assertSame(
				'object',
				Field_Type_Config::TYPES[ $type ]['attribute'],
				"OBJECT_TYPES contains '{$type}' but TYPES maps it to a different attribute"
			);
		}
	}

	public function test_array_types_constant_matches_types_with_array_attribute(): void {
		foreach ( Field_Type_Config::ARRAY_TYPES as $type ) {
			$this->assertSame(
				'array',
				Field_Type_Config::TYPES[ $type ]['attribute'],
				"ARRAY_TYPES contains '{$type}' but TYPES maps it to a different attribute"
			);
		}
	}

	public function test_non_attribute_types_have_null_attribute_in_types(): void {
		foreach ( Field_Type_Config::NON_ATTRIBUTE_TYPES as $type ) {
			$this->assertNull(
				Field_Type_Config::TYPES[ $type ]['attribute'],
				"NON_ATTRIBUTE_TYPES contains '{$type}' but TYPES has non-null attribute"
			);
		}
	}
}
