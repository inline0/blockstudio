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

	public function test_normalize_accepts_root_layers_and_example_data(): void {
		$normalized = Render::normalize(
			array(
				'root'    => 'blockstudio/function-nested-render',
				'example' => array(
					'data'   => array( 'label' => 'Root label' ),
					'layers' => array(
						array(
							'name' => 'blockstudio/component-richtext-default',
							'data' => array( 'richtext' => 'Child label' ),
						),
					),
				),
			)
		);

		$this->assertSame( 'blockstudio/function-nested-render', $normalized[0]['name'] );
		$this->assertSame( array( 'label' => 'Root label' ), $normalized[0]['attributes'] );
		$this->assertSame( 'blockstudio/component-richtext-default', $normalized[0]['children'][0]['name'] );
		$this->assertSame( array( 'richtext' => 'Child label' ), $normalized[0]['children'][0]['attributes'] );
		$this->assertArrayNotHasKey( 'root', $normalized[0] );
		$this->assertArrayNotHasKey( 'example', $normalized[0] );
		$this->assertArrayNotHasKey( 'layers', $normalized[0] );
	}

	public function test_normalize_rejects_invalid_declarations(): void {
		$this->expectException( InvalidArgumentException::class );

		Render::normalize(
			array(
				'name'       => 'not-canonical',
				'attributes' => array(),
			)
		);
	}

	public function test_composition_returns_html_without_echoing(): void {
		if ( ! isset( Build::data()['blockstudio/function-nested-render'] ) ) {
			$this->markTestSkipped( 'Nested render fixture is not registered.' );
		}

		ob_start();
		$html   = Render::composition(
			array(
				'name'       => 'blockstudio/function-nested-render',
				'attributes' => array( 'label' => 'Programmatic composition' ),
			)
		);
		$echoed = ob_get_clean();

		$this->assertSame( '', $echoed );
		$this->assertStringContainsString( 'Programmatic composition', $html );
		$this->assertStringNotContainsString( '<RichText', $html );
	}

	public function test_document_returns_versioned_body_and_asset_contract(): void {
		if ( ! isset( Build::data()['blockstudio/function-nested-render'] ) ) {
			$this->markTestSkipped( 'Nested render fixture is not registered.' );
		}

		$document = Render::document(
			array(
				'name'       => 'blockstudio/function-nested-render',
				'attributes' => array( 'label' => 'Document composition' ),
			),
			array( 'title' => 'Programmatic document' )
		);

		$this->assertSame( Render::SCHEMA_VERSION, $document['schemaVersion'] );
		$this->assertStringContainsString( 'Document composition', $document['body'] );
		$this->assertStringContainsString( '<title>Programmatic document</title>', $document['html'] );
		$this->assertSame( array( 'blockstudio/function-nested-render' ), $document['blocks'] );
		$this->assertSame(
			array( 'head', 'footer', 'styles', 'scripts', 'modules', 'interactivity', 'ui', 'tailwind' ),
			array_keys( $document['assets'] )
		);
		$this->assertStringContainsString( 'blockstudio-tailwind', $document['assets']['tailwind'] );
		$this->assertSame( array(), $document['errors'] );
	}

	public function test_document_can_wrap_content_in_a_semantic_element(): void {
		$document = Render::document_from_html(
			'<p>Preview content</p>',
			array(),
			array(
				'contentElement'    => 'main',
				'contentClasses'    => array( 'canvas-preview', 'canvas-preview' ),
				'contentAttributes' => array(
					'data-preview' => 'true',
					'hidden'       => false,
				),
			)
		);

		$this->assertSame( '<p>Preview content</p>', $document['body'] );
		$this->assertStringContainsString(
			'<main data-preview="true" class="canvas-preview"><p>Preview content</p></main>',
			$document['html']
		);
	}

	public function test_document_ignores_an_unsafe_content_element(): void {
		$document = Render::document_from_html(
			'<p>Preview content</p>',
			array(),
			array( 'contentElement' => 'script' )
		);

		$this->assertStringNotContainsString( '<script><p>Preview content</p></script>', $document['html'] );
		$this->assertStringContainsString( '<body><p>Preview content</p></body>', $document['html'] );
	}

	public function test_document_closes_nested_template_dependencies(): void {
		$name       = 'blockstudio/type-block-tags-kitchen-sink';
		$dependency = 'blockstudio/type-block-tags-default';

		if ( ! isset( Build::data()[ $name ], Build::data()[ $dependency ] ) ) {
			$this->markTestSkipped( 'Block tag dependency fixtures are not registered.' );
		}

		add_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );

		try {
			Blockstudio\Batch_Render::reset();
			$document = Render::document( array( 'name' => $name ) );
		} finally {
			remove_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );
		}

		$this->assertSame( $name, $document['blocks'][0] );
		$this->assertContains( $dependency, $document['blocks'] );
		$this->assertSame( array(), $document['warnings'] );
	}

	public function test_document_returns_only_frontend_assets_for_selected_block(): void {
		$name = 'blockstudio/assets';

		if ( ! isset( Build::data()[ $name ] ) ) {
			$this->markTestSkipped( 'Asset fixture is not registered.' );
		}

		add_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );

		try {
			Blockstudio\Batch_Render::reset();
			$document = Render::document( array( 'name' => $name ) );
		} finally {
			remove_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );
		}

		$this->assertStringContainsString( 'test-scoped-css', $document['assets']['styles'] );
		$this->assertStringContainsString( 'test-view-js', $document['assets']['modules'] );
		$this->assertStringNotContainsString( 'test-editor', $document['assets']['head'] );
		$this->assertStringNotContainsString( 'test2-editor', $document['assets']['footer'] );
		$this->assertSame( '', $document['assets']['scripts'] );
		$this->assertSame( array(), $document['warnings'] );
	}

	public function test_document_returns_interactivity_bootstrap_for_selected_output(): void {
		$name = 'blockstudio/type-interactivity';

		if ( ! isset( Build::data()[ $name ] ) ) {
			$this->markTestSkipped( 'Interactivity fixture is not registered.' );
		}

		add_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );

		try {
			Blockstudio\Batch_Render::reset();
			$document = Render::document( array( 'name' => $name ) );
		} finally {
			remove_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );
		}

		$this->assertStringContainsString( '<script type="importmap">', $document['assets']['interactivity'] );
		$this->assertStringContainsString( '@wordpress\/interactivity', $document['assets']['interactivity'] );
		$this->assertStringContainsString( 'data-wp-interactive', $document['body'] );
		$this->assertSame( array(), $document['warnings'] );
	}

	public function test_document_from_html_returns_ui_globals_only_for_ui_markup(): void {
		add_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );

		try {
			$document = Render::document_from_html(
				'<button data-bsui-button>Button</button>'
			);
		} finally {
			remove_filter( 'blockstudio/settings/tailwind/enabled', '__return_false' );
		}

		$this->assertStringContainsString( 'blockstudio-ui-global', $document['assets']['ui']['style'] );
		$this->assertStringContainsString( 'blockstudio-ui-global-script', $document['assets']['ui']['script'] );
		$this->assertStringContainsString( $document['assets']['ui']['style'], $document['assets']['head'] );
		$this->assertStringContainsString( $document['assets']['ui']['script'], $document['assets']['footer'] );
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
