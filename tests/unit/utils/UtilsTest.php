<?php

use Blockstudio\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase {

	// attributes() - data attributes mode

	public function test_attributes_renders_data_attributes(): void {
		$data   = array(
			'color' => 'blue',
			'size'  => 'large',
		);
		$result = Utils::attributes( $data );

		$this->assertStringContainsString( 'data-color="blue"', $result );
		$this->assertStringContainsString( 'data-size="large"', $result );
	}

	public function test_attributes_converts_camel_case_to_kebab(): void {
		$data   = array( 'backgroundColor' => 'red' );
		$result = Utils::attributes( $data );

		$this->assertStringContainsString( 'data-background-color="red"', $result );
	}

	public function test_attributes_skips_empty_values(): void {
		$data   = array(
			'color' => 'blue',
			'empty' => '',
			'zero'  => 0,
		);
		$result = Utils::attributes( $data );

		$this->assertStringContainsString( 'data-color="blue"', $result );
		$this->assertStringNotContainsString( 'data-empty', $result );
		$this->assertStringNotContainsString( 'data-zero', $result );
	}

	public function test_attributes_with_allowed_filter(): void {
		$data   = array(
			'color' => 'blue',
			'size'  => 'large',
			'shape' => 'round',
		);
		$result = Utils::attributes( $data, array( 'color', 'shape' ) );

		$this->assertStringContainsString( 'data-color="blue"', $result );
		$this->assertStringContainsString( 'data-shape="round"', $result );
		$this->assertStringNotContainsString( 'data-size', $result );
	}

	public function test_attributes_extracts_value_from_object(): void {
		$data   = array(
			'color' => array( 'value' => 'green' ),
		);
		$result = Utils::attributes( $data );

		$this->assertStringContainsString( 'data-color="green"', $result );
	}

	public function test_attributes_returns_empty_string_for_empty_data(): void {
		$this->assertSame( '', Utils::attributes( array() ) );
	}

	// attributes() - CSS variables mode

	public function test_attributes_css_variables_mode(): void {
		$data   = array(
			'color'   => 'blue',
			'spacing' => '1rem',
		);
		$result = Utils::attributes( $data, array(), true );

		$this->assertStringContainsString( '--color: blue;', $result );
		$this->assertStringContainsString( '--spacing: 1rem;', $result );
	}

	public function test_attributes_css_variables_camel_to_kebab(): void {
		$data   = array( 'fontSize' => '16px' );
		$result = Utils::attributes( $data, array(), true );

		$this->assertStringContainsString( '--font-size: 16px;', $result );
	}

	public function test_attributes_css_variables_skips_empty(): void {
		$data   = array(
			'color' => 'blue',
			'empty' => '',
		);
		$result = Utils::attributes( $data, array(), true );

		$this->assertStringContainsString( '--color: blue;', $result );
		$this->assertStringNotContainsString( '--empty', $result );
	}

	public function test_attributes_css_variables_with_allowed(): void {
		$data   = array(
			'color'   => 'blue',
			'size'    => '10px',
			'display' => 'flex',
		);
		$result = Utils::attributes( $data, array( 'color', 'display' ), true );

		$this->assertStringContainsString( '--color: blue;', $result );
		$this->assertStringContainsString( '--display: flex;', $result );
		$this->assertStringNotContainsString( '--size', $result );
	}

	// data_attributes()

	public function test_data_attributes_renders_simple_attributes(): void {
		$attrs  = array(
			array(
				'attribute' => 'alt',
				'value'     => 'A photo',
			),
			array(
				'attribute' => 'title',
				'value'     => 'Image',
			),
		);
		$result = Utils::data_attributes( $attrs );

		$this->assertStringContainsString( "alt='A photo'", $result );
		$this->assertStringContainsString( "title='Image'", $result );
	}

	public function test_data_attributes_with_null_returns_empty(): void {
		$result = Utils::data_attributes( null );
		$this->assertSame( '', $result );
	}

	public function test_data_attributes_with_empty_array_returns_empty(): void {
		$result = Utils::data_attributes( array() );
		$this->assertSame( '', $result );
	}

	// console_log()

	public function test_console_log_outputs_script_tag(): void {
		ob_start();
		Utils::console_log( array( 'test' => 'value' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script>', $output );
		$this->assertStringContainsString( 'console.log(', $output );
		$this->assertStringContainsString( '</script>', $output );
	}

	public function test_console_log_encodes_data_as_json(): void {
		ob_start();
		Utils::console_log( array( 'key' => 'val' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( '{"key":"val"}', $output );
	}

	public function test_console_log_with_string(): void {
		ob_start();
		Utils::console_log( 'hello' );
		$output = ob_get_clean();

		$this->assertStringContainsString( '"hello"', $output );
	}

	public function test_console_log_with_number(): void {
		ob_start();
		Utils::console_log( 42 );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'console.log(42)', $output );
	}
}
