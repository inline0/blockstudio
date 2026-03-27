<?php

use Blockstudio\Field_Discovery;
use PHPUnit\Framework\TestCase;

class FieldDiscoveryTest extends TestCase {

	private string $temp_dir;
	private Field_Discovery $discovery;

	protected function setUp(): void {
		$this->temp_dir  = sys_get_temp_dir() . '/blockstudio-field-discovery-' . uniqid();
		mkdir( $this->temp_dir, 0755, true );
		$this->discovery = new Field_Discovery();
	}

	protected function tearDown(): void {
		$this->remove_dir( $this->temp_dir );
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $dir );
	}

	private function write_field_json( string $subdir, array $data ): void {
		$dir = $this->temp_dir . '/' . $subdir;
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		file_put_contents( $dir . '/field.json', json_encode( $data ) );
	}

	// discover() with valid field.json

	public function test_discover_finds_single_field(): void {
		$this->write_field_json( 'address', array(
			'name'       => 'address',
			'attributes' => array(
				'street' => array( 'type' => 'string' ),
			),
		) );

		$fields = $this->discovery->discover( $this->temp_dir );

		$this->assertCount( 1, $fields );
		$this->assertArrayHasKey( 'address', $fields );
		$this->assertSame( 'address', $fields['address']['name'] );
	}

	public function test_discover_finds_multiple_fields(): void {
		$this->write_field_json( 'address', array(
			'name'       => 'address',
			'attributes' => array( 'street' => array( 'type' => 'string' ) ),
		) );
		$this->write_field_json( 'contact', array(
			'name'       => 'contact',
			'attributes' => array( 'email' => array( 'type' => 'string' ) ),
		) );

		$fields = $this->discovery->discover( $this->temp_dir );

		$this->assertCount( 2, $fields );
		$this->assertArrayHasKey( 'address', $fields );
		$this->assertArrayHasKey( 'contact', $fields );
	}

	public function test_discover_finds_nested_field_json(): void {
		$this->write_field_json( 'deep/nested/field', array(
			'name'       => 'deep-field',
			'attributes' => array( 'value' => array( 'type' => 'string' ) ),
		) );

		$fields = $this->discovery->discover( $this->temp_dir );

		$this->assertCount( 1, $fields );
		$this->assertArrayHasKey( 'deep-field', $fields );
	}

	public function test_discover_returns_full_field_data(): void {
		$data = array(
			'name'       => 'my-field',
			'attributes' => array(
				'title'   => array( 'type' => 'string', 'default' => '' ),
				'enabled' => array( 'type' => 'boolean', 'default' => false ),
			),
			'extra' => 'metadata',
		);
		$this->write_field_json( 'my-field', $data );

		$fields = $this->discovery->discover( $this->temp_dir );

		$this->assertSame( $data, $fields['my-field'] );
	}

	// discover() with invalid paths

	public function test_discover_returns_empty_for_nonexistent_path(): void {
		$fields = $this->discovery->discover( '/nonexistent/path/that/does/not/exist' );
		$this->assertSame( array(), $fields );
	}

	public function test_discover_returns_empty_for_empty_directory(): void {
		$fields = $this->discovery->discover( $this->temp_dir );
		$this->assertSame( array(), $fields );
	}

	// discover() with invalid field.json content

	public function test_discover_skips_field_json_without_name(): void {
		$this->write_field_json( 'no-name', array(
			'attributes' => array( 'value' => array( 'type' => 'string' ) ),
		) );

		$fields = $this->discovery->discover( $this->temp_dir );
		$this->assertSame( array(), $fields );
	}

	public function test_discover_skips_field_json_without_attributes(): void {
		$this->write_field_json( 'no-attrs', array(
			'name' => 'no-attrs',
		) );

		$fields = $this->discovery->discover( $this->temp_dir );
		$this->assertSame( array(), $fields );
	}

	public function test_discover_skips_field_json_with_empty_name(): void {
		$this->write_field_json( 'empty-name', array(
			'name'       => '',
			'attributes' => array( 'value' => array( 'type' => 'string' ) ),
		) );

		$fields = $this->discovery->discover( $this->temp_dir );
		$this->assertSame( array(), $fields );
	}

	public function test_discover_skips_field_json_with_empty_attributes(): void {
		$this->write_field_json( 'empty-attrs', array(
			'name'       => 'empty-attrs',
			'attributes' => array(),
		) );

		$fields = $this->discovery->discover( $this->temp_dir );
		$this->assertSame( array(), $fields );
	}

	public function test_discover_skips_invalid_json(): void {
		$dir = $this->temp_dir . '/broken';
		mkdir( $dir, 0755, true );
		file_put_contents( $dir . '/field.json', 'not valid json {{{' );

		$fields = $this->discovery->discover( $this->temp_dir );
		$this->assertSame( array(), $fields );
	}

	public function test_discover_skips_non_array_json(): void {
		$dir = $this->temp_dir . '/scalar';
		mkdir( $dir, 0755, true );
		file_put_contents( $dir . '/field.json', '"just a string"' );

		$fields = $this->discovery->discover( $this->temp_dir );
		$this->assertSame( array(), $fields );
	}

	// discover() ignores non field.json files

	public function test_discover_ignores_other_json_files(): void {
		$dir = $this->temp_dir . '/block';
		mkdir( $dir, 0755, true );
		file_put_contents( $dir . '/block.json', json_encode( array(
			'name'       => 'should-ignore',
			'attributes' => array( 'x' => array( 'type' => 'string' ) ),
		) ) );

		$fields = $this->discovery->discover( $this->temp_dir );
		$this->assertSame( array(), $fields );
	}

	public function test_discover_ignores_non_json_files(): void {
		$dir = $this->temp_dir . '/misc';
		mkdir( $dir, 0755, true );
		file_put_contents( $dir . '/field.txt', 'not json' );
		file_put_contents( $dir . '/field.php', '<?php // not json' );

		$fields = $this->discovery->discover( $this->temp_dir );
		$this->assertSame( array(), $fields );
	}

	// discover() mixed valid and invalid

	public function test_discover_returns_only_valid_fields(): void {
		$this->write_field_json( 'valid', array(
			'name'       => 'valid-field',
			'attributes' => array( 'title' => array( 'type' => 'string' ) ),
		) );
		$this->write_field_json( 'invalid-no-name', array(
			'attributes' => array( 'title' => array( 'type' => 'string' ) ),
		) );
		$this->write_field_json( 'invalid-no-attrs', array(
			'name' => 'no-attrs-field',
		) );

		$fields = $this->discovery->discover( $this->temp_dir );

		$this->assertCount( 1, $fields );
		$this->assertArrayHasKey( 'valid-field', $fields );
	}

	// discover() indexes by name, not directory

	public function test_discover_indexes_by_field_name_not_directory(): void {
		$this->write_field_json( 'some-directory', array(
			'name'       => 'actual-field-name',
			'attributes' => array( 'v' => array( 'type' => 'string' ) ),
		) );

		$fields = $this->discovery->discover( $this->temp_dir );

		$this->assertArrayHasKey( 'actual-field-name', $fields );
		$this->assertArrayNotHasKey( 'some-directory', $fields );
	}

	// discover() with duplicate names (last wins due to iterator order)

	public function test_discover_last_duplicate_name_wins(): void {
		$this->write_field_json( 'dir-a', array(
			'name'       => 'duplicate',
			'attributes' => array( 'source' => array( 'type' => 'string', 'default' => 'a' ) ),
		) );
		$this->write_field_json( 'dir-b', array(
			'name'       => 'duplicate',
			'attributes' => array( 'source' => array( 'type' => 'string', 'default' => 'b' ) ),
		) );

		$fields = $this->discovery->discover( $this->temp_dir );

		$this->assertCount( 1, $fields );
		$this->assertArrayHasKey( 'duplicate', $fields );
	}
}
