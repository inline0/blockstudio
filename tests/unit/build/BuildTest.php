<?php

use Blockstudio\Build;
use PHPUnit\Framework\TestCase;

class BuildTest extends TestCase {

	// blocks()

	public function test_blocks_returns_array(): void {
		$blocks = Build::blocks();
		$this->assertIsArray( $blocks );
	}

	public function test_blocks_is_not_empty(): void {
		$blocks = Build::blocks();
		$this->assertNotEmpty( $blocks );
	}

	public function test_blocks_contains_text_block(): void {
		$blocks = Build::blocks();
		$this->assertArrayHasKey( 'blockstudio/type-text', $blocks );
	}

	public function test_blocks_values_are_wp_block_type(): void {
		$blocks = Build::blocks();
		foreach ( $blocks as $block ) {
			$this->assertInstanceOf( WP_Block_Type::class, $block );
			break;
		}
	}

	public function test_blocks_contain_multiple_test_blocks(): void {
		$blocks     = Build::blocks();
		$test_count = 0;
		foreach ( array_keys( $blocks ) as $name ) {
			if ( str_starts_with( $name, 'blockstudio/type-' ) ) {
				++$test_count;
			}
		}
		$this->assertGreaterThan( 5, $test_count );
	}

	// data()

	public function test_data_returns_array(): void {
		$data = Build::data();
		$this->assertIsArray( $data );
	}

	public function test_data_is_not_empty(): void {
		$data = Build::data();
		$this->assertNotEmpty( $data );
	}

	public function test_data_contains_text_block(): void {
		$data = Build::data();
		$this->assertArrayHasKey( 'blockstudio/type-text', $data );
	}

	public function test_data_entry_has_name(): void {
		$data  = Build::data();
		$entry = $data['blockstudio/type-text'];
		$this->assertArrayHasKey( 'name', $entry );
		$this->assertSame( 'blockstudio/type-text', $entry['name'] );
	}

	public function test_data_entry_has_instance(): void {
		$data = Build::data();
		$entry = $data['blockstudio/type-text'];
		$this->assertArrayHasKey( 'instance', $entry );
	}

	public function test_data_and_blocks_share_common_keys(): void {
		$data_keys   = array_keys( Build::data() );
		$blocks_keys = array_keys( Build::blocks() );
		$common      = array_intersect( $data_keys, $blocks_keys );

		$this->assertNotEmpty( $common );
		$this->assertGreaterThan( 10, count( $common ) );
	}

	// extensions()

	public function test_extensions_returns_array(): void {
		$extensions = Build::extensions();
		$this->assertIsArray( $extensions );
	}

	// overrides()

	public function test_overrides_returns_array(): void {
		$overrides = Build::overrides();
		$this->assertIsArray( $overrides );
	}

	// assets()

	public function test_assets_returns_array(): void {
		$assets = Build::assets();
		$this->assertIsArray( $assets );
	}

	public function test_assets_admin_returns_array(): void {
		$assets = Build::assets_admin();
		$this->assertIsArray( $assets );
	}

	public function test_assets_block_editor_returns_array(): void {
		$assets = Build::assets_block_editor();
		$this->assertIsArray( $assets );
	}

	public function test_assets_global_returns_array(): void {
		$assets = Build::assets_global();
		$this->assertIsArray( $assets );
	}

	// blade()

	public function test_blade_returns_array(): void {
		$blade = Build::blade();
		$this->assertIsArray( $blade );
	}

	// paths()

	public function test_paths_returns_array(): void {
		$paths = Build::paths();
		$this->assertIsArray( $paths );
	}

	public function test_paths_is_not_empty(): void {
		$paths = Build::paths();
		$this->assertNotEmpty( $paths );
	}

	// has_interactivity()

	public function test_has_interactivity_true_boolean(): void {
		$this->assertTrue( Build::has_interactivity( array( 'interactivity' => true ) ) );
	}

	public function test_has_interactivity_false_boolean(): void {
		$this->assertFalse( Build::has_interactivity( array( 'interactivity' => false ) ) );
	}

	public function test_has_interactivity_with_enqueue_true(): void {
		$this->assertTrue(
			Build::has_interactivity( array( 'interactivity' => array( 'enqueue' => true ) ) )
		);
	}

	public function test_has_interactivity_with_enqueue_false(): void {
		$this->assertFalse(
			Build::has_interactivity( array( 'interactivity' => array( 'enqueue' => false ) ) )
		);
	}

	public function test_has_interactivity_empty_array(): void {
		$this->assertFalse( Build::has_interactivity( array() ) );
	}

	public function test_has_interactivity_missing_key(): void {
		$this->assertFalse( Build::has_interactivity( array( 'attributes' => array() ) ) );
	}

	public function test_has_interactivity_empty_interactivity_array(): void {
		$this->assertFalse( Build::has_interactivity( array( 'interactivity' => array() ) ) );
	}

	// refresh_blocks()

	public function test_refresh_blocks_does_not_break_registry(): void {
		$blocks_before = Build::blocks();
		Build::refresh_blocks();
		$blocks_after = Build::blocks();

		$this->assertSame(
			array_keys( $blocks_before ),
			array_keys( $blocks_after )
		);
	}

	// Block type properties

	public function test_block_type_has_render_callback(): void {
		$blocks = Build::blocks();
		$block  = $blocks['blockstudio/type-text'];

		$this->assertNotNull( $block->render_callback );
	}

	public function test_block_type_has_attributes(): void {
		$blocks = Build::blocks();
		$block  = $blocks['blockstudio/type-text'];

		$this->assertIsArray( $block->attributes );
	}

	public function test_block_type_has_blockstudio_data(): void {
		$blocks = Build::blocks();
		$block  = $blocks['blockstudio/type-text'];

		$this->assertIsArray( $block->blockstudio );
		$this->assertArrayHasKey( 'attributes', $block->blockstudio );
	}
}
