<?php

use Blockstudio\Assets;
use Blockstudio\Build;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase {

	public function test_get_interactivity_api_import_map_returns_importmap_script(): void {
		$result = Assets::get_interactivity_api_import_map();

		$this->assertStringContainsString( '<script type="importmap">', $result );
		$this->assertStringContainsString( '</script>', $result );
		$this->assertStringContainsString( '@wordpress/interactivity', $result );
	}

	public function test_get_interactivity_api_import_map_contains_preact(): void {
		$result = Assets::get_interactivity_api_import_map();

		$this->assertStringContainsString( 'preact', $result );
		$this->assertStringContainsString( 'preact/hooks', $result );
	}

	public function test_get_interactivity_api_import_map_contains_preact_signals(): void {
		$result = Assets::get_interactivity_api_import_map();

		$this->assertStringContainsString( '@preact/signals', $result );
		$this->assertStringContainsString( '@preact/signals-core', $result );
	}

	public function test_get_interactivity_api_import_map_resolves_path_placeholder(): void {
		$result = Assets::get_interactivity_api_import_map();

		$this->assertStringNotContainsString( '@path', $result );
		$this->assertStringContainsString( BLOCKSTUDIO_URL, $result );
	}

	public function test_get_interactivity_editor_assets_returns_string(): void {
		$result = Assets::get_interactivity_editor_assets();

		$this->assertIsString( $result );
	}

	public function test_get_interactivity_editor_assets_returns_script_tags_when_interactivity_blocks_exist(): void {
		$blocks              = Build::blocks();
		$has_interactivity   = false;

		foreach ( $blocks as $block ) {
			if ( Build::has_interactivity( $block->blockstudio ?? array() ) ) {
				$has_interactivity = true;
				break;
			}
		}

		$result = Assets::get_interactivity_editor_assets();

		if ( $has_interactivity ) {
			$this->assertStringContainsString( '<script', $result );
			$this->assertStringContainsString( '@wordpress/interactivity', $result );
		} else {
			$this->assertSame( '', $result );
		}
	}

	public function test_get_interactivity_importmap_returns_empty_when_no_interactivity_blocks(): void {
		$result = Assets::get_interactivity_importmap( array(), '<html></html>' );

		$this->assertSame( '', $result );
	}

	public function test_get_interactivity_importmap_returns_empty_when_no_module_tag_in_html(): void {
		$block              = new stdClass();
		$block->blockstudio = array( 'interactivity' => true );

		$result = Assets::get_interactivity_importmap(
			array( 'test/block' => $block ),
			'<html><head></head><body></body></html>'
		);

		$this->assertSame( '', $result );
	}

	public function test_get_interactivity_importmap_returns_importmap_when_module_tag_present(): void {
		$block              = new stdClass();
		$block->blockstudio = array( 'interactivity' => true );

		$html = '<script type="module" src="http://example.com/wp-includes/js/dist/interactivity.min.js" id="@wordpress/interactivity-js-module"></script>';

		$result = Assets::get_interactivity_importmap(
			array( 'test/block' => $block ),
			$html
		);

		$this->assertStringContainsString( '<script type="importmap">', $result );
		$this->assertStringContainsString( 'interactivity', $result );
		$this->assertStringContainsString( 'interactivity.min.js', $result );
	}

	public function test_is_css_returns_true_for_css_path(): void {
		$this->assertTrue( Assets::is_css( 'style.css' ) );
	}

	public function test_is_css_returns_true_for_scss_path(): void {
		$this->assertTrue( Assets::is_css( 'style.scss' ) );
	}

	public function test_is_css_returns_false_for_js_path(): void {
		$this->assertFalse( Assets::is_css( 'script.js' ) );
	}

	public function test_is_css_extension_returns_true_for_css(): void {
		$this->assertTrue( Assets::is_css_extension( 'css' ) );
	}

	public function test_is_css_extension_returns_true_for_scss(): void {
		$this->assertTrue( Assets::is_css_extension( 'scss' ) );
	}

	public function test_is_css_extension_returns_false_for_js(): void {
		$this->assertFalse( Assets::is_css_extension( 'js' ) );
	}

	public function test_get_id_returns_formatted_string(): void {
		$block = array( 'name' => 'test/my-block' );
		$id    = Assets::get_id( 'style.css', $block );

		$this->assertStringContainsString( 'blockstudio', $id );
		$this->assertStringContainsString( 'test', $id );
		$this->assertStringContainsString( 'my-block', $id );
		$this->assertStringNotContainsString( '/', $id );
	}

	public function test_parse_output_returns_string(): void {
		$assets = new Assets();
		$result = $assets->parse_output( '<html><head></head><body></body></html>' );

		$this->assertIsString( $result );
	}

	public function test_parse_output_preserves_body_and_head(): void {
		$assets = new Assets();
		$html   = '<html><head><title>Test</title></head><body><p>Content</p></body></html>';
		$result = $assets->parse_output( $html );

		$this->assertStringContainsString( '</head>', $result );
		$this->assertStringContainsString( '</body>', $result );
		$this->assertStringContainsString( '<p>Content</p>', $result );
	}
}
