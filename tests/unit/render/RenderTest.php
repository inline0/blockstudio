<?php

use Blockstudio\Render;
use Blockstudio\Build;
use PHPUnit\Framework\TestCase;

class RenderTest extends TestCase {

	public function test_class_exists(): void {
		$this->assertTrue( class_exists( Render::class ) );
	}

	public function test_block_method_is_callable(): void {
		$this->assertTrue( method_exists( Render::class, 'block' ) );
	}

	public function test_block_returns_false_for_unknown_block_name(): void {
		$result = Render::block( 'blockstudio/nonexistent-block-xyz' );
		$this->assertFalse( $result );
	}

	public function test_block_returns_false_for_unknown_block_array(): void {
		$result = Render::block(
			array(
				'name' => 'blockstudio/nonexistent-block-xyz',
			)
		);
		$this->assertFalse( $result );
	}

	public function test_block_array_with_data_returns_false_for_unknown(): void {
		$result = Render::block(
			array(
				'name' => 'blockstudio/nonexistent-block-xyz',
				'data' => array( 'title' => 'Test' ),
			)
		);
		$this->assertFalse( $result );
	}

	public function test_nested_render_helper_resolves_pseudo_components_in_editor_mode(): void {
		if ( ! isset( Build::data()['blockstudio/function-nested-render'] ) ) {
			$this->markTestSkipped( 'Nested render fixture is not registered.' );
		}

		$had_mode = array_key_exists( 'blockstudioMode', $_GET );
		$mode     = $_GET['blockstudioMode'] ?? null;

		try {
			$_GET['blockstudioMode'] = 'editor';

			ob_start();
			Render::block(
				array(
					'name' => 'blockstudio/function-nested-render',
					'data' => array(
						'label' => 'Nested editor label',
					),
				)
			);
			$editor_output = ob_get_clean();

			unset( $_GET['blockstudioMode'] );

			ob_start();
			Render::block(
				array(
					'name' => 'blockstudio/function-nested-render',
					'data' => array(
						'label' => 'Nested frontend label',
					),
				)
			);
			$frontend_output = ob_get_clean();
		} finally {
			if ( $had_mode ) {
				$_GET['blockstudioMode'] = $mode;
			} else {
				unset( $_GET['blockstudioMode'] );
			}
		}

		$this->assertStringContainsString( 'Nested editor label', $editor_output );
		$this->assertStringContainsString( 'Nested frontend label', $frontend_output );
		$this->assertStringNotContainsString( '<RichText', $editor_output );
		$this->assertStringNotContainsString( '<InnerBlocks', $editor_output );
		$this->assertStringNotContainsString( 'useblockprops="true"', $editor_output );
	}
}
