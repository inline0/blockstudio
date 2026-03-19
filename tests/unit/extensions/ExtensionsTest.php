<?php

use Blockstudio\Build;
use Blockstudio\Extensions;
use PHPUnit\Framework\TestCase;

class ExtensionsTest extends TestCase {

	// Build::extensions() returns the registered extensions

	public function test_extensions_returns_array(): void {
		$extensions = Build::extensions();

		$this->assertIsArray( $extensions );
	}

	public function test_extensions_not_empty(): void {
		$extensions = Build::extensions();

		$this->assertNotEmpty( $extensions );
	}

	public function test_extension_has_name_property(): void {
		$extensions = Build::extensions();
		$first      = $extensions[0];

		$this->assertObjectHasProperty( 'name', $first );
	}

	public function test_extension_has_attributes_property(): void {
		$extensions = Build::extensions();
		$first      = $extensions[0];

		$this->assertObjectHasProperty( 'attributes', $first );
	}

	public function test_extension_name_is_string_or_array(): void {
		$extensions = Build::extensions();

		foreach ( $extensions as $ext ) {
			$this->assertTrue(
				is_string( $ext->name ) || is_array( $ext->name ),
				'Extension name must be a string or array'
			);
		}
	}

	public function test_extension_attributes_is_array(): void {
		$extensions = Build::extensions();

		foreach ( $extensions as $ext ) {
			$this->assertIsArray( $ext->attributes );
		}
	}

	// get_matches() with exact block name

	public function test_get_matches_returns_array(): void {
		$extensions = Build::extensions();
		$matches    = Extensions::get_matches( 'core/paragraph', $extensions );

		$this->assertIsArray( $matches );
	}

	public function test_get_matches_finds_wildcard_extension(): void {
		$extensions = Build::extensions();
		$matches    = Extensions::get_matches( 'core/paragraph', $extensions );

		$this->assertNotEmpty( $matches, 'core/paragraph should match core/* wildcard extension' );
	}

	public function test_get_matches_finds_core_heading(): void {
		$extensions = Build::extensions();
		$matches    = Extensions::get_matches( 'core/heading', $extensions );

		$this->assertNotEmpty( $matches, 'core/heading should match core/* wildcard extension' );
	}

	public function test_get_matches_finds_specific_block_target(): void {
		$extensions = Build::extensions();
		$matches    = Extensions::get_matches( 'blockstudio/type-text', $extensions );

		$this->assertNotEmpty( $matches, 'blockstudio/type-text should match a specific extension' );
	}

	public function test_get_matches_returns_empty_for_unmatched_block(): void {
		$extensions = Build::extensions();
		$matches    = Extensions::get_matches( 'nonexistent/block-name', $extensions );

		$this->assertEmpty( $matches );
	}

	public function test_get_matches_returns_empty_for_null_block_name(): void {
		$extensions = Build::extensions();
		$matches    = Extensions::get_matches( null, $extensions );

		$this->assertEmpty( $matches );
	}

	public function test_get_matches_returns_empty_for_empty_string(): void {
		$extensions = Build::extensions();
		$matches    = Extensions::get_matches( '', $extensions );

		$this->assertEmpty( $matches );
	}

	public function test_get_matches_with_empty_extensions_array(): void {
		$matches = Extensions::get_matches( 'core/paragraph', array() );

		$this->assertSame( array(), $matches );
	}

	// get_matches() with synthetic extension objects

	public function test_get_matches_exact_name_match(): void {
		$ext       = (object) array(
			'name'       => 'my/block',
			'attributes' => array(),
		);
		$matches   = Extensions::get_matches( 'my/block', array( $ext ) );

		$this->assertCount( 1, $matches );
		$this->assertSame( $ext, $matches[0] );
	}

	public function test_get_matches_exact_name_no_match(): void {
		$ext     = (object) array(
			'name'       => 'my/block',
			'attributes' => array(),
		);
		$matches = Extensions::get_matches( 'other/block', array( $ext ) );

		$this->assertEmpty( $matches );
	}

	public function test_get_matches_wildcard_prefix(): void {
		$ext     = (object) array(
			'name'       => 'core/*',
			'attributes' => array(),
		);
		$matches = Extensions::get_matches( 'core/paragraph', array( $ext ) );

		$this->assertCount( 1, $matches );
	}

	public function test_get_matches_wildcard_no_match(): void {
		$ext     = (object) array(
			'name'       => 'core/*',
			'attributes' => array(),
		);
		$matches = Extensions::get_matches( 'custom/block', array( $ext ) );

		$this->assertEmpty( $matches );
	}

	public function test_get_matches_array_of_names(): void {
		$ext     = (object) array(
			'name'       => array( 'core/paragraph', 'core/heading' ),
			'attributes' => array(),
		);
		$matches = Extensions::get_matches( 'core/heading', array( $ext ) );

		$this->assertCount( 1, $matches );
	}

	public function test_get_matches_array_of_names_no_match(): void {
		$ext     = (object) array(
			'name'       => array( 'core/paragraph', 'core/heading' ),
			'attributes' => array(),
		);
		$matches = Extensions::get_matches( 'core/image', array( $ext ) );

		$this->assertEmpty( $matches );
	}

	public function test_get_matches_array_with_wildcard(): void {
		$ext     = (object) array(
			'name'       => array( 'core/*', 'custom/block' ),
			'attributes' => array(),
		);
		$matches = Extensions::get_matches( 'core/list', array( $ext ) );

		$this->assertCount( 1, $matches );
	}

	public function test_get_matches_multiple_extensions(): void {
		$ext1 = (object) array(
			'name'       => 'core/*',
			'attributes' => array(),
		);
		$ext2 = (object) array(
			'name'       => array( 'core/paragraph' ),
			'attributes' => array(),
		);
		$ext3 = (object) array(
			'name'       => 'custom/block',
			'attributes' => array(),
		);

		$matches = Extensions::get_matches( 'core/paragraph', array( $ext1, $ext2, $ext3 ) );

		$this->assertCount( 2, $matches );
		$this->assertContains( $ext1, $matches );
		$this->assertContains( $ext2, $matches );
	}

	// parse_template()

	public function test_parse_template_replaces_placeholder(): void {
		$result = Extensions::parse_template(
			'text-{attributes.myField}',
			array( 'attributes' => array( 'myField' => 'hello' ) )
		);

		$this->assertSame( 'text-hello', $result );
	}

	public function test_parse_template_replaces_multiple_placeholders(): void {
		$result = Extensions::parse_template(
			'{attributes.a}-{attributes.b}',
			array( 'attributes' => array( 'a' => 'foo', 'b' => 'bar' ) )
		);

		$this->assertSame( 'foo-bar', $result );
	}

	public function test_parse_template_no_placeholders(): void {
		$result = Extensions::parse_template(
			'static-class',
			array( 'attributes' => array() )
		);

		$this->assertSame( 'static-class', $result );
	}

	public function test_parse_template_missing_value_returns_null_segment(): void {
		$result = Extensions::parse_template(
			'prefix-{attributes.missing}',
			array( 'attributes' => array() )
		);

		$this->assertSame( 'prefix-', $result );
	}

	// get() nested access

	public function test_get_top_level_key(): void {
		$result = Extensions::get( array( 'foo' => 'bar' ), 'foo' );

		$this->assertSame( 'bar', $result );
	}

	public function test_get_nested_key(): void {
		$result = Extensions::get(
			array( 'a' => array( 'b' => 'deep' ) ),
			'a.b'
		);

		$this->assertSame( 'deep', $result );
	}

	public function test_get_missing_key_returns_default(): void {
		$result = Extensions::get( array( 'a' => 1 ), 'b', 'fallback' );

		$this->assertSame( 'fallback', $result );
	}

	public function test_get_null_key_returns_target(): void {
		$data   = array( 'x' => 1 );
		$result = Extensions::get( $data, null );

		$this->assertSame( $data, $result );
	}

	public function test_get_on_object(): void {
		$obj     = (object) array( 'name' => 'test' );
		$result  = Extensions::get( $obj, 'name' );

		$this->assertSame( 'test', $result );
	}

	public function test_get_deeply_nested(): void {
		$result = Extensions::get(
			array( 'a' => array( 'b' => array( 'c' => 'found' ) ) ),
			'a.b.c'
		);

		$this->assertSame( 'found', $result );
	}

	public function test_get_non_traversable_returns_default(): void {
		$result = Extensions::get( 'just a string', 'any.path', 'default' );

		$this->assertSame( 'default', $result );
	}
}
