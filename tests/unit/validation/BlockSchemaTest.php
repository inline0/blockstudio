<?php

use Blockstudio\Block_Validator;
use Blockstudio\Field_Type_Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockSchemaTest extends TestCase {

	private function entry( array $field, string $source = 'block' ): array {
		$compiled = array( 'id' => $field['id'] ?? '', 'blockstudio' => true );
		if ( 'repeater' === $field['type'] ) {
			$compiled['attributes'] = array_map( fn( array $child ): array => $this->entry( $child, $source )['compiled'], $field['attributes'] ?? array() );
		}
		return array( 'field' => $field, 'source' => $source, 'compiled' => $compiled );
	}

	private function schema( array $definitions ): array {
		return ( new ReflectionMethod( Block_Validator::class, 'object_schema' ) )->invoke( null, $definitions );
	}

	#[DataProvider( 'invalid_attachment_ids' )]
	public function test_attachment_ids_cannot_be_negative( bool $multiple, mixed $value ): void {
		$schema = $this->schema( array( 'image' => array( $this->entry( array( 'type' => 'files', 'multiple' => $multiple ) ) ) ) );
		$this->assertInstanceOf( WP_Error::class, rest_validate_value_from_schema( array( 'image' => $value ), $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( array( 'image' => $multiple ? array( 0, 12 ) : 12 ), $schema ) );
	}

	public static function invalid_attachment_ids(): array {
		return array(
			'single' => array( false, -1 ),
			'multiple' => array( true, array( 12, -1 ) ),
		);
	}

	#[DataProvider( 'closed_empty_options' )]
	public function test_empty_options_do_not_become_unrestricted_in_wordpress( array $field, mixed $empty, mixed $invalid ): void {
		$schema = $this->schema( array( 'choice' => array( $this->entry( $field ) ) ) );
		$this->assertTrue( rest_validate_value_from_schema( array( 'choice' => $empty ), $schema ) );
		$this->assertInstanceOf( WP_Error::class, rest_validate_value_from_schema( array( 'choice' => $invalid ), $schema ) );
	}

	public static function closed_empty_options(): array {
		return array(
			'select' => array( array( 'type' => 'select', 'options' => array() ), null, 'anything' ),
			'checkbox' => array( array( 'type' => 'checkbox', 'options' => array() ), array(), array( 'anything' ) ),
			'closed palette' => array( array( 'type' => 'color', 'disableCustomColors' => true, 'options' => array() ), null, '#fff' ),
		);
	}

	public function test_disjoint_option_sets_only_allow_a_cleared_value(): void {
		$entries = array(
			$this->entry( array( 'type' => 'select', 'options' => array( 'a' ) ) ),
			$this->entry( array( 'type' => 'select', 'options' => array( 'b' ) ), 'extension' ),
		);
		foreach ( array( false, true ) as $multiple ) {
			foreach ( $entries as &$entry ) {
				$entry['field']['multiple'] = $multiple;
			}
			unset( $entry );
			$schema = $this->schema( array( 'choice' => $entries ) );
			$this->assertTrue( rest_validate_value_from_schema( array( 'choice' => $multiple ? array() : null ), $schema ) );
			$this->assertInstanceOf( WP_Error::class, rest_validate_value_from_schema( array( 'choice' => $multiple ? array( 'a' ) : 'a' ), $schema ) );
		}
	}

	public function test_shared_repeaters_combine_children_options_and_bounds(): void {
		$entries = array(
			$this->entry( array( 'type' => 'repeater', 'min' => 0, 'max' => 4, 'attributes' => array(
				array( 'id' => 'title', 'type' => 'text' ),
				array( 'id' => 'choice', 'type' => 'select', 'options' => array( 'a', 'b' ) ),
			) ) ),
			$this->entry( array( 'type' => 'repeater', 'min' => 1, 'max' => 2, 'attributes' => array(
				array( 'id' => 'extra', 'type' => 'text' ),
				array( 'id' => 'choice', 'type' => 'select', 'options' => array( 'b', 'c' ) ),
			) ), 'extension' ),
		);
		$schema = $this->schema( array( 'rows' => $entries ) );
		$row = array( 'title' => 'Title', 'extra' => 'Extension', 'choice' => 'b' );
		$this->assertSame( array( 'b' ), $schema['properties']['rows']['items']['properties']['choice']['enum'] );
		$this->assertSame( 1, $schema['properties']['rows']['minItems'] );
		$this->assertSame( 2, $schema['properties']['rows']['maxItems'] );
		$this->assertTrue( rest_validate_value_from_schema( array( 'rows' => array( $row ) ), $schema ) );
		foreach ( array( array(), array( $row, $row, $row ), array( array_replace( $row, array( 'choice' => 'a' ) ) ) ) as $invalid ) {
			$this->assertInstanceOf( WP_Error::class, rest_validate_value_from_schema( array( 'rows' => $invalid ), $schema ) );
		}
	}

	public function test_shared_numeric_options_use_renderers_key_matching(): void {
		$schema = $this->schema( array( 'choice' => array(
			$this->entry( array( 'type' => 'select', 'options' => array( 1, 2 ) ) ),
			$this->entry( array( 'type' => 'select', 'options' => array( '1' ) ), 'extension' ),
		) ) );
		$this->assertSame( array( 1 ), $schema['properties']['choice']['enum'] );
		$this->assertTrue( rest_validate_value_from_schema( array( 'choice' => 1 ), $schema ) );
		$this->assertInstanceOf( WP_Error::class, rest_validate_value_from_schema( array( 'choice' => 2 ), $schema ) );
	}

	public function test_empty_shared_options_keep_field_annotations(): void {
		$schema = $this->schema( array( 'choice' => array(
			$this->entry( array( 'type' => 'select', 'options' => array(), 'label' => 'Choice', 'help' => 'No available options' ) ),
			$this->entry( array( 'type' => 'select', 'options' => array( 'a' ) ), 'extension' ),
		) ) );
		$this->assertSame( 'Choice', $schema['properties']['choice']['title'] );
		$this->assertSame( 'No available options', $schema['properties']['choice']['description'] );
		$this->assertTrue( rest_validate_value_from_schema( array( 'choice' => null ), $schema ) );
		$this->assertInstanceOf( WP_Error::class, rest_validate_value_from_schema( array( 'choice' => 'a' ), $schema ) );
	}

	public function test_explicit_fields_win_over_file_size_companions(): void {
		$schema = $this->schema( array(
			'image__size' => array( $this->entry( array( 'type' => 'number' ) ) ),
			'image' => array( $this->entry( array( 'type' => 'files' ) ) ),
		) );
		$this->assertSame( 'number', $schema['properties']['image__size']['type'] );
		$this->assertTrue( rest_validate_value_from_schema( array( 'image' => 12, 'image__size' => 1 ), $schema ) );
		$this->assertInstanceOf( WP_Error::class, rest_validate_value_from_schema( array( 'image__size' => 'large' ), $schema ) );
	}

	#[DataProvider( 'array_definition_order' )]
	public function test_shared_custom_arrays_keep_item_constraints_in_either_order( bool $custom_first ): void {
		$registry = Field_Type_Registry::instance();
		$this->assertTrue( $registry->register( 'schema-test/list', array( 'attribute' => 'array' ) ) );
		try {
			$custom = array( 'type' => 'schema-test/list' );
			$attributes = array( 'type' => 'attributes' );
			$schema = $this->schema( array( 'pairs' => array(
				$this->entry( $custom_first ? $custom : $attributes ),
				$this->entry( $custom_first ? $attributes : $custom, 'extension' ),
			) ) );
			$this->assertTrue( rest_validate_value_from_schema( array( 'pairs' => array( array( 'attribute' => 'data-x', 'value' => 'x' ) ) ), $schema ) );
			$this->assertInstanceOf( WP_Error::class, rest_validate_value_from_schema( array( 'pairs' => array( 'not an attribute pair' ) ), $schema ) );
		} finally {
			$registry->unregister( 'schema-test/list' );
		}
	}

	public static function array_definition_order(): array {
		return array( 'custom field first' => array( true ), 'custom field last' => array( false ) );
	}
}
