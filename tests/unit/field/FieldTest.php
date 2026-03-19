<?php

use Blockstudio\Field;
use PHPUnit\Framework\TestCase;

class FieldTest extends TestCase {

	// group()

	public function test_group_extracts_prefixed_fields(): void {
		$attributes = array(
			'cta_text' => 'Learn More',
			'cta_url'  => 'https://example.com',
			'title'    => 'Page Title',
		);

		$result = Field::group( $attributes, 'cta' );

		$this->assertSame(
			array(
				'text' => 'Learn More',
				'url'  => 'https://example.com',
			),
			$result
		);
	}

	public function test_group_with_empty_input(): void {
		$result = Field::group( array(), 'cta' );
		$this->assertSame( array(), $result );
	}

	public function test_group_strips_prefix_correctly(): void {
		$attributes = array(
			'hero_heading'    => 'Welcome',
			'hero_subheading' => 'Hello world',
			'hero_image'      => 'photo.jpg',
		);

		$result = Field::group( $attributes, 'hero' );

		$this->assertArrayHasKey( 'heading', $result );
		$this->assertArrayHasKey( 'subheading', $result );
		$this->assertArrayHasKey( 'image', $result );
		$this->assertSame( 'Welcome', $result['heading'] );
		$this->assertSame( 'Hello world', $result['subheading'] );
		$this->assertSame( 'photo.jpg', $result['image'] );
	}

	public function test_group_does_not_include_unrelated_fields(): void {
		$attributes = array(
			'cta_text'    => 'Click',
			'header_text' => 'Title',
			'footer_text' => 'Copyright',
		);

		$result = Field::group( $attributes, 'cta' );

		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'text', $result );
		$this->assertArrayNotHasKey( 'header_text', $result );
	}

	public function test_group_with_no_matching_prefix(): void {
		$attributes = array(
			'title'   => 'Hello',
			'content' => 'World',
		);

		$result = Field::group( $attributes, 'cta' );
		$this->assertSame( array(), $result );
	}

	public function test_group_preserves_value_types(): void {
		$attributes = array(
			'settings_enabled' => true,
			'settings_count'   => 42,
			'settings_ratio'   => 3.14,
			'settings_items'   => array( 'a', 'b' ),
			'settings_label'   => 'Test',
		);

		$result = Field::group( $attributes, 'settings' );

		$this->assertSame( true, $result['enabled'] );
		$this->assertSame( 42, $result['count'] );
		$this->assertSame( 3.14, $result['ratio'] );
		$this->assertSame( array( 'a', 'b' ), $result['items'] );
		$this->assertSame( 'Test', $result['label'] );
	}

	public function test_group_with_single_field(): void {
		$attributes = array(
			'banner_visible' => true,
		);

		$result = Field::group( $attributes, 'banner' );

		$this->assertCount( 1, $result );
		$this->assertSame( true, $result['visible'] );
	}

	public function test_group_prefix_must_match_from_start(): void {
		$attributes = array(
			'not_cta_text' => 'Wrong',
			'cta_text'     => 'Right',
		);

		$result = Field::group( $attributes, 'cta' );

		$this->assertCount( 1, $result );
		$this->assertSame( 'Right', $result['text'] );
	}

	public function test_group_handles_null_values(): void {
		$attributes = array(
			'form_name'  => null,
			'form_email' => 'test@example.com',
		);

		$result = Field::group( $attributes, 'form' );

		$this->assertCount( 2, $result );
		$this->assertNull( $result['name'] );
		$this->assertSame( 'test@example.com', $result['email'] );
	}

	public function test_group_with_nested_underscores_in_field_name(): void {
		$attributes = array(
			'cta_button_text'  => 'Submit',
			'cta_button_color' => 'blue',
		);

		$result = Field::group( $attributes, 'cta' );

		$this->assertArrayHasKey( 'button_text', $result );
		$this->assertArrayHasKey( 'button_color', $result );
		$this->assertSame( 'Submit', $result['button_text'] );
	}
}
