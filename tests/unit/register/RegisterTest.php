<?php

use PHPUnit\Framework\TestCase;

class RegisterTest extends TestCase {

	// Test blocks are registered with WordPress

	public function test_text_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-text' ) );
	}

	public function test_number_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-number' ) );
	}

	public function test_checkbox_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-checkbox' ) );
	}

	public function test_switch_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-switch' ) );
	}

	public function test_select_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-select' ) );
	}

	public function test_range_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-range' ) );
	}

	public function test_textarea_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-textarea' ) );
	}

	public function test_date_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-date' ) );
	}

	public function test_toggle_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-toggle' ) );
	}

	public function test_link_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-link' ) );
	}

	public function test_repeater_block_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'blockstudio/type-repeater' ) );
	}

	// Component blocks should NOT be registered

	public function test_component_blocks_not_in_wp_registry(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$blocks   = Blockstudio\Build::blocks();

		foreach ( $blocks as $name => $block ) {
			if ( ! empty( $block->blockstudio['component'] ) ) {
				$this->assertFalse(
					$registry->is_registered( $name ),
					"Component block '$name' should not be registered with WordPress."
				);
			}
		}
	}

	// Registered block type properties

	public function test_registered_block_has_correct_name(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$block    = $registry->get_registered( 'blockstudio/type-text' );

		$this->assertInstanceOf( WP_Block_Type::class, $block );
		$this->assertSame( 'blockstudio/type-text', $block->name );
	}

	public function test_registered_block_has_render_callback(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$block    = $registry->get_registered( 'blockstudio/type-text' );

		$this->assertNotNull( $block->render_callback );
		$this->assertTrue( is_callable( $block->render_callback ) );
	}

	public function test_registered_block_has_title(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$block    = $registry->get_registered( 'blockstudio/type-text' );

		$this->assertSame( 'Native Text', $block->title );
	}

	public function test_registered_block_has_category(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$block    = $registry->get_registered( 'blockstudio/type-text' );

		$this->assertSame( 'blockstudio-test-native', $block->category );
	}

	// Multiple blocks are registered

	public function test_many_blockstudio_blocks_are_registered(): void {
		$registry   = WP_Block_Type_Registry::get_instance();
		$all_blocks = $registry->get_all_registered();
		$bs_count   = 0;

		foreach ( array_keys( $all_blocks ) as $name ) {
			if ( str_starts_with( $name, 'blockstudio/' ) ) {
				++$bs_count;
			}
		}

		$this->assertGreaterThan( 10, $bs_count );
	}

	// Non-existent block is not registered

	public function test_nonexistent_block_is_not_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertFalse( $registry->is_registered( 'blockstudio/does-not-exist' ) );
	}
}
