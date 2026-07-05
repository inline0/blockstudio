<?php

use Blockstudio\Assets;
use Blockstudio\Block;
use Blockstudio\Build;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase {

	private array $filter_callbacks = array();

	private array $temporary_directories = array();

	protected function tearDown(): void {
		foreach ( $this->filter_callbacks as $filter_callback ) {
			remove_filter( $filter_callback[0], $filter_callback[1], $filter_callback[2] );
		}

		foreach ( $this->temporary_directories as $temporary_directory ) {
			$this->remove_directory( $temporary_directory );
		}

		$this->filter_callbacks = array();
		$this->temporary_directories = array();
		Assets::$force_editor_screen = false;
	}

	private function add_filter( string $name, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $name, $callback, $priority, $args );
		$this->filter_callbacks[] = array( $name, $callback, $priority );
	}

	private function create_temporary_asset( string $filename, string $content ): string {
		$directory = sys_get_temp_dir() . '/blockstudio-assets-' . uniqid( '', true );
		wp_mkdir_p( $directory );
		$this->temporary_directories[] = $directory;

		$path = $directory . '/' . $filename;
		file_put_contents( $path, $content );

		return $path;
	}

	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}

		rmdir( $directory );
	}

	public function test_bsui_inline_styles_are_wrapped_in_layer(): void {
		$path = $this->create_temporary_asset( 'style.inline.css', '[data-bsui-button] { color: red; }' );

		$output = Assets::render_inline(
			'style.inline.css',
			array( 'path' => $path ),
			array(
				'name' => 'bsui/button',
				'file' => array( 'dirname' => dirname( $path ) ),
			),
			true
		);

		$this->assertStringContainsString( '@layer bsui {', $output );
		$this->assertStringContainsString( '[data-bsui-button] { color: red; }', $output );
	}

	public function test_non_bsui_inline_styles_are_not_wrapped_in_layer(): void {
		$path = $this->create_temporary_asset( 'style.inline.css', '.custom-block { color: red; }' );

		$output = Assets::render_inline(
			'style.inline.css',
			array( 'path' => $path ),
			array(
				'name' => 'custom/block',
				'file' => array( 'dirname' => dirname( $path ) ),
			),
			true
		);

		$this->assertStringNotContainsString( '@layer bsui', $output );
		$this->assertStringContainsString( '.custom-block { color: red; }', $output );
	}

	public function test_button_variants_style_filter_is_appended_inside_layer(): void {
		$this->add_filter(
			'blockstudio/ui/button/variants-style',
			static function ( string $css, array $block ): string {
				if ( 'bsui/button' !== ( $block['name'] ?? '' ) ) {
					return $css;
				}

				return $css . '[data-bsui-button][data-variant="brand"] { color: hotpink; }';
			},
			10,
			2
		);

		$path = $this->create_temporary_asset( 'style.inline.css', '[data-bsui-button] { color: red; }' );

		$output = Assets::render_inline(
			'style.inline.css',
			array( 'path' => $path ),
			array(
				'name' => 'bsui/button',
				'file' => array( 'dirname' => dirname( $path ) ),
			),
			true
		);

		$this->assertMatchesRegularExpression( '/@layer bsui \{.*data-variant="brand".*\}/s', $output );
	}

	public function test_button_variant_options_can_be_extended_with_attributes_filter(): void {
		$this->add_filter(
			'blockstudio/blocks/attributes',
			static function ( array $attribute, array $block ): array {
				if ( 'bsui/button' === ( $block['name'] ?? '' ) && 'variant' === ( $attribute['id'] ?? '' ) ) {
					$attribute['options'][] = array(
						'label' => 'Brand',
						'value' => 'brand',
					);
				}

				return $attribute;
			},
			10,
			2
		);

		$attributes = array(
			'variant' => array(
				'id'      => 'variant',
				'type'    => 'select',
				'options' => array(
					array(
						'label' => 'Default',
						'value' => 'default',
					),
				),
			),
		);

		Build::filter_attributes( array( 'name' => 'bsui/button' ), $attributes, $attributes );

		$this->assertContains(
			array(
				'label' => 'Brand',
				'value' => 'brand',
			),
			$attributes['variant']['options']
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_plugin_bootstrap_registers_editor_asset_footer_once(): void {
		$this->assertSame( 1, $this->count_assets_admin_footer_callbacks() );
	}

	private function count_assets_admin_footer_callbacks(): int {
		global $wp_filter;

		if ( ! isset( $wp_filter['admin_footer'] ) ) {
			return 0;
		}

		$assets_file = wp_normalize_path( BLOCKSTUDIO_DIR . '/includes/classes/assets.php' );
		$count       = 0;

		foreach ( $wp_filter['admin_footer']->callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $callback ) {
				$callback_function = $callback['function'] ?? null;

				if (
					is_array( $callback_function ) &&
					Assets::class === ( $callback_function[0] ?? null ) &&
					'render_legacy_editor_assets_fallback' === ( $callback_function[1] ?? null )
				) {
					++$count;
					continue;
				}

				if ( ! $callback_function instanceof \Closure ) {
					continue;
				}

				$reflection    = new \ReflectionFunction( $callback_function );
				$callback_file = $reflection->getFileName();

				if ( false === $callback_file ) {
					continue;
				}

				if ( $assets_file === wp_normalize_path( $callback_file ) ) {
					++$count;
				}
			}
		}

		return $count;
	}

	public function test_get_interactivity_editor_assets_returns_string(): void {
		$result = Assets::get_interactivity_editor_assets();

		$this->assertIsString( $result );
	}

	public function test_legacy_editor_assets_fallback_defers_assets_until_non_iframed_canvas(): void {
		Assets::$force_editor_screen = true;

		ob_start();
		Assets::render_legacy_editor_assets_fallback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="blockstudio-legacy-editor-assets-fallback"', $output );
		$this->assertStringContainsString( 'iframe[name=', $output );
		$this->assertStringContainsString( 'editor-canvas', $output );
		$this->assertStringContainsString( '.block-editor-block-list__layout.is-root-container', $output );
		$this->assertStringContainsString( 'getSettings', $output );
		$this->assertStringContainsString( 'startsWith("blockstudio-")', $output );
		$this->assertStringContainsString( 'document.createElement("script")', $output );
		$this->assertStringNotContainsString( 'const h=', $output );
		$this->assertStringNotContainsString( '<style id=\'blockstudio-', $output );
		$this->assertStringNotContainsString( '<script type=\'module\'', $output );
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

	public function test_process_css_replaces_selector_placeholder_in_css_asset(): void {
		$path = $this->create_temporary_asset(
			'style.css',
			'%selector% { color: red; } %selector%.hero { display: grid; } %selector% > h1 { color: blue; }'
		);

		$compiled = Assets::process( $path, 'bs-test' );

		$this->assertIsString( $compiled );
		$this->assertFileExists( $compiled );

		$css = file_get_contents( $compiled );

		$this->assertMatchesRegularExpression( '/\.bs-test\s*\{\s*color:\s*red;?\s*\}/', $css );
		$this->assertMatchesRegularExpression( '/\.bs-test\.hero\s*\{\s*display:\s*grid;?\s*\}/', $css );
		$this->assertMatchesRegularExpression( '/\.bs-test\s*>\s*h1\s*\{\s*color:\s*blue;?\s*\}/', $css );
		$this->assertStringNotContainsString( '%selector%', $css );
	}

	public function test_process_css_replaces_selector_placeholder_before_scss_compile(): void {
		$path = $this->create_temporary_asset(
			'style.scss',
			'$color: red; %selector% { color: $color; }'
		);

		$compiled = Assets::process( $path, 'bs-test' );

		$this->assertIsString( $compiled );
		$this->assertFileExists( $compiled );

		$css = file_get_contents( $compiled );

		$this->assertMatchesRegularExpression( '/\.bs-test\s*\{\s*color:\s*red;?\s*\}/', $css );
		$this->assertStringNotContainsString( '%selector%', $css );
	}

	public function test_get_asset_dependency_paths_finds_local_scss_dependencies(): void {
		$path       = $this->create_temporary_asset(
			'style.scss',
			'@use "tokens"; .example { color: $color; }'
		);
		$dependency = dirname( $path ) . '/_tokens.scss';

		file_put_contents( $dependency, '$color: red;' );

		$this->assertContains(
			wp_normalize_path( $dependency ),
			Assets::get_asset_dependency_paths( $path )
		);
	}

	public function test_get_asset_version_changes_when_scss_dependency_changes(): void {
		$path       = $this->create_temporary_asset(
			'style.scss',
			'@use "tokens"; .example { color: $color; }'
		);
		$dependency = dirname( $path ) . '/_tokens.scss';

		file_put_contents( $dependency, '$color: red;' );

		$first = Assets::get_asset_version( $path );

		file_put_contents( $dependency, '$color: blue;' );
		touch( $dependency, time() + 2 );
		clearstatcache( true, $dependency );

		$this->assertNotSame( $first, Assets::get_asset_version( $path ) );
	}

	public function test_get_asset_version_changes_when_minify_setting_changes(): void {
		$path = $this->create_temporary_asset( 'script.js', 'console.log("asset");' );

		$this->add_filter( 'blockstudio/settings/assets/minify/js', static fn() => false );
		$unminified = Assets::get_asset_version( $path );

		$this->add_filter( 'blockstudio/settings/assets/minify/js', static fn() => true, 20 );
		$minified = Assets::get_asset_version( $path );

		$this->assertNotSame( $unminified, $minified );
	}

	public function test_preview_assets_bucket_by_file_extension(): void {
		$css = $this->create_temporary_asset( 'custom.css', '.preview { color: red; }' );
		$js  = $this->create_temporary_asset( 'style-switcher.js', 'console.log("preview");' );

		$block = array(
			'name'   => 'test/block',
			'assets' => array(
				'custom.css'        => array(
					'type' => 'file',
					'path' => $css,
					'key'  => filemtime( $css ),
				),
				'style-switcher.js' => array(
					'type' => 'file',
					'path' => $js,
					'key'  => filemtime( $js ),
				),
			),
		);

		$this->assertStringContainsString( "rel='stylesheet'", Assets::get_preview_assets( $block, true ) );
		$this->assertStringContainsString( '<script ', Assets::get_preview_assets( $block, false ) );
	}

	public function test_process_clears_cached_compiled_asset_lookup(): void {
		$path = $this->create_temporary_asset(
			'style.css',
			'%selector% { color: red; }'
		);

		$this->assertSame( $path, Assets::get_path( $path ) );

		$compiled = Assets::process( $path, 'bs-test' );

		$this->assertIsString( $compiled );
		$this->assertFileExists( $compiled );
		$this->assertSame( $compiled, Assets::get_path( $path ) );
	}

	public function test_process_css_keeps_scoped_selector_placeholder_on_root_selector(): void {
		$path = $this->create_temporary_asset(
			'style.scoped.css',
			'%selector% { color: red; } %selector%.hero { display: grid; } %selector% > h1 { color: blue; } .title { color: black; }'
		);

		$compiled = Assets::process( $path, 'bs-test' );

		$this->assertIsString( $compiled );
		$this->assertFileExists( $compiled );

		$css = file_get_contents( $compiled );

		$this->assertMatchesRegularExpression( '/\.bs-test\s*\{\s*color:\s*red;?\s*\}/', $css );
		$this->assertMatchesRegularExpression( '/\.bs-test\.hero\s*\{\s*display:\s*grid;?\s*\}/', $css );
		$this->assertMatchesRegularExpression( '/\.bs-test\s*>\s*h1\s*\{\s*color:\s*blue;?\s*\}/', $css );
		$this->assertMatchesRegularExpression( '/\.bs-test\s+\.title\s*\{/', $css );
		$this->assertStringNotContainsString( '.bs-test .bs-test', $css );
		$this->assertStringNotContainsString( '__blockstudio-selector-placeholder__', $css );
	}

	public function test_process_css_keeps_scoped_selector_placeholder_after_scss_compile(): void {
		$path = $this->create_temporary_asset(
			'style.scoped.scss',
			'$color: red; %selector%.hero { color: $color; } .title { color: blue; }'
		);

		$compiled = Assets::process( $path, 'bs-test' );

		$this->assertIsString( $compiled );
		$this->assertFileExists( $compiled );

		$css = file_get_contents( $compiled );

		$this->assertMatchesRegularExpression( '/\.bs-test\.hero\s*\{\s*color:\s*red;?\s*\}/', $css );
		$this->assertMatchesRegularExpression( '/\.bs-test\s+\.title\s*\{/', $css );
		$this->assertStringNotContainsString( '.bs-test .bs-test', $css );
		$this->assertStringNotContainsString( '__blockstudio-selector-placeholder__', $css );
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

	public function test_parse_output_keeps_frontend_assets_when_render_filter_replaces_output(): void {
		$block_name = 'blockstudio/assets';
		$blocks     = Build::data();

		if ( ! isset( $blocks[ $block_name ] ) ) {
			$this->markTestSkipped( "{$block_name} not registered." );
		}

		$block_data        = $blocks[ $block_name ];
		$expected_asset_id = null;

		foreach ( $block_data['assets'] ?? array() as $asset_name => $asset ) {
			if ( str_contains( $asset_name, 'editor' ) || str_contains( $asset_name, 'admin' ) ) {
				continue;
			}

			if ( ! Assets::is_css( $asset_name ) || 'inline' === $asset['type'] ) {
				continue;
			}

			$expected_asset_id = Assets::get_id( $asset_name, $block_data );
			break;
		}

		if ( null === $expected_asset_id ) {
			$this->markTestSkipped( "{$block_name} has no external frontend CSS asset." );
		}

		$this->add_filter(
			'blockstudio/blocks/render',
			function ( $html, $block ) use ( $block_name ) {
				if ( ( $block->name ?? '' ) === $block_name ) {
					return '<section class="sage-blade-render">Blade output</section>';
				}

				return $html;
			},
			20,
			2
		);

		$rendered = Block::render(
			array(
				'blockstudio' => array(
					'name'       => $block_name,
					'attributes' => array(),
				),
			)
		);

		$result = ( new Assets() )->parse_output(
			'<html><head></head><body>' . $rendered . '</body></html>'
		);

		$this->assertStringContainsString( 'sage-blade-render', $result );
		$this->assertStringContainsString( "id='{$expected_asset_id}'", $result );
	}

	public function test_maybe_reset_editor_styles_removes_wordpress_iframe_styles_without_editor_enhancements(): void {
		$this->add_filter(
			'blockstudio/settings/assets/reset/enabled',
			static function () {
				return true;
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/enhance',
			static function () {
				return false;
			}
		);

		$assets   = new Assets();
		$settings = array(
			'__unstableResolvedAssets' => array(
				'styles' => implode(
					'',
					array(
						'<link rel="stylesheet" href="https://example.test/wp-includes/css/dist/block-library/style.min.css?ver=6.9.4">',
						'<link rel="stylesheet" href="https://example.test/wp-includes/css/dist/block-library/editor.min.css?ver=6.9.4">',
						'<link rel="stylesheet" href="https://example.test/wp-includes/css/common.min.css?ver=6.9.4">',
						'<link rel="stylesheet" href="https://example.test/wp-includes/css/content.min.css?ver=6.9.4">',
						'<link rel="stylesheet" href="https://example.test/wp-includes/css/reset.min.css?ver=6.9.4">',
						'<link rel="stylesheet" href="https://example.test/wp-includes/css/classic.min.css?ver=6.9.4">',
						'<link rel="stylesheet" href="https://example.test/wp-includes/css/classic-themes.min.css?ver=6.9.4">',
						'<style>.keep{display:block}</style>',
					)
				),
			),
		);

		$result = $assets->maybe_reset_editor_styles( $settings );
		$styles = $result['__unstableResolvedAssets']['styles'];

		$this->assertStringNotContainsString( 'block-library/style.min.css', $styles );
		$this->assertStringNotContainsString( 'block-library/editor.min.css', $styles );
		$this->assertStringNotContainsString( 'common.min.css', $styles );
		$this->assertStringNotContainsString( 'content.min.css', $styles );
		$this->assertStringNotContainsString( 'reset.min.css', $styles );
		$this->assertStringNotContainsString( 'classic.min.css', $styles );
		$this->assertStringNotContainsString( 'classic-themes.min.css', $styles );
		$this->assertStringContainsString( '<style>.keep{display:block}</style>', $styles );
		$this->assertStringContainsString( 'blockstudio-reset-utility-layout', $styles );
		$this->assertStringContainsString( '.editor-styles-wrapper .flex{display:flex}', $styles );
		$this->assertStringContainsString( '.editor-styles-wrapper .inline-flex{display:inline-flex}', $styles );
		$this->assertStringContainsString( '.editor-styles-wrapper .grid{display:grid}', $styles );
		$this->assertStringContainsString( '.editor-styles-wrapper .inline-grid{display:inline-grid}', $styles );
		$this->assertStringContainsString( '.editor-styles-wrapper .absolute{position:absolute}', $styles );
		$this->assertStringContainsString( '.editor-styles-wrapper .fixed{position:fixed}', $styles );
		$this->assertStringContainsString( '.editor-styles-wrapper .sticky{position:sticky}', $styles );
		$this->assertStringNotContainsString( '.editor-styles-wrapper :where(.flex', $styles );
		$this->assertStringNotContainsString( '!important', $styles );
		$this->assertStringNotContainsString( 'blockstudio-editor-enhance', $styles );
		$this->assertStringNotContainsString( ':focus-visible{outline:none!important', $styles );
		$this->assertStringNotContainsString( '.is-hovered:not(.has-child-selected)::after', $styles );
		$this->assertStringNotContainsString( '.is-selected::after{border-color:#7c3aed}', $styles );
	}

	public function test_maybe_reset_editor_styles_does_not_add_utility_layout_styles_when_reset_disabled(): void {
		$this->add_filter(
			'blockstudio/settings/assets/reset/enabled',
			static function () {
				return false;
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/enhance',
			static function () {
				return false;
			}
		);

		$assets   = new Assets();
		$settings = array(
			'__unstableResolvedAssets' => array(
				'styles' => '<style>.keep{display:block}</style>',
			),
		);

		$result = $assets->maybe_reset_editor_styles( $settings );
		$styles = $result['__unstableResolvedAssets']['styles'];

		$this->assertSame( '<style>.keep{display:block}</style>', $styles );
		$this->assertStringNotContainsString( 'blockstudio-reset-utility-layout', $styles );
		$this->assertStringNotContainsString( '.editor-styles-wrapper .flex{display:flex}', $styles );
	}

	public function test_maybe_reset_editor_styles_adds_editor_enhancements_when_enabled(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/enhance',
			static function () {
				return true;
			}
		);

		$assets   = new Assets();
		$settings = array(
			'__unstableResolvedAssets' => array(
				'styles' => implode(
					'',
					array(
						'<link rel="stylesheet" href="https://example.test/wp-includes/css/dist/block-library/style.min.css?ver=6.9.4">',
						'<link rel="stylesheet" href="https://example.test/wp-includes/css/common.min.css?ver=6.9.4">',
						'<style>.keep{display:block}</style>',
					)
				),
			),
		);

		$result = $assets->maybe_reset_editor_styles( $settings );
		$styles = $result['__unstableResolvedAssets']['styles'];

		$this->assertStringContainsString( 'block-library/style.min.css', $styles );
		$this->assertStringContainsString( 'common.min.css', $styles );
		$this->assertStringContainsString( '<style>.keep{display:block}</style>', $styles );
		$this->assertStringContainsString( 'blockstudio-editor-enhance', $styles );
		$this->assertStringContainsString( 'html.blockstudio-editor-enhance-locked{overflow:hidden!important}', $styles );
		$this->assertStringContainsString( 'body.blockstudio-editor-enhance-locked{position:fixed!important', $styles );
		$this->assertStringContainsString( 'right:var(--blockstudio-editor-enhance-scrollbar-width,0px)!important;width:auto!important', $styles );
		$this->assertStringContainsString( '.editor-styles-wrapper .blockstudio-block{transition:opacity .25s ease}', $styles );
		$this->assertStringContainsString( '.blockstudio-editor-enhance-pending:not(.blockstudio-editor-enhance-ready) .blockstudio-block{visibility:hidden;opacity:0;pointer-events:none}', $styles );
		$this->assertStringContainsString( 'html.blockstudio-editor-enhance-pending:not(.blockstudio-editor-enhance-ready)::before', $styles );
		$this->assertStringContainsString( 'left:calc(50% - (var(--blockstudio-editor-enhance-scrollbar-width,0px) / 2))', $styles );
		$this->assertStringContainsString( 'html.blockstudio-editor-enhance-pending.blockstudio-editor-enhance-ready::before{opacity:0;visibility:hidden}', $styles );
		$this->assertStringContainsString( 'blockstudio-editor-enhance-spin', $styles );
		$this->assertStringContainsString( ':focus-visible{outline:none!important', $styles );
		$this->assertStringContainsString( ':where(.wp-block,.blockstudio-block){position:relative}', $styles );
		$this->assertStringContainsString( '.is-hovered:not(.has-child-selected)::after', $styles );
		$this->assertStringContainsString( '.is-highlighted:not(.has-child-selected)::after', $styles );
		$this->assertStringContainsString( '.is-selected::after{content:"";position:absolute;inset:0;border:1px solid rgb(142 142 142 / .65)', $styles );
		$this->assertStringContainsString( '.is-selected::after{border-color:#7c3aed}', $styles );
		$this->assertStringNotContainsString( '.has-child-selected{outline', $styles );
		$this->assertStringNotContainsString( '.is-highlighted{outline', $styles );
	}

	public function test_render_parent_editor_enhancement_styles_outputs_parent_lock_css_when_enabled(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/enhance',
			static function () {
				return true;
			}
		);

		Assets::$force_editor_screen = true;
		$assets                      = new Assets();

		ob_start();
		$assets->render_parent_editor_enhancement_styles();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="blockstudio-editor-enhance-parent"', $output );
		$this->assertStringContainsString( 'html.blockstudio-editor-enhance-locked{overflow:hidden!important}', $output );
		$this->assertStringContainsString( 'body.blockstudio-editor-enhance-locked{position:fixed!important', $output );
		$this->assertStringContainsString( 'right:var(--blockstudio-editor-enhance-scrollbar-width,0px)!important;width:auto!important', $output );
		$this->assertStringNotContainsString( '.blockstudio-block{visibility:hidden}', $output );
	}

	public function test_add_parent_editor_enhancement_body_class_locks_parent_body_when_enabled(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/enhance',
			static function () {
				return true;
			}
		);

		Assets::$force_editor_screen = true;
		$assets                      = new Assets();

		$classes = $assets->add_parent_editor_enhancement_body_class( 'wp-admin block-editor-page' );

		$this->assertStringContainsString( 'wp-admin block-editor-page', $classes );
		$this->assertStringContainsString( 'blockstudio-editor-enhance-locked', $classes );
		$this->assertSame(
			$classes,
			$assets->add_parent_editor_enhancement_body_class( $classes )
		);
	}

	public function test_add_parent_editor_enhancement_body_class_does_not_lock_parent_body_when_disabled(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/enhance',
			static function () {
				return false;
			}
		);

		Assets::$force_editor_screen = true;
		$assets                      = new Assets();

		$classes = $assets->add_parent_editor_enhancement_body_class( 'wp-admin block-editor-page' );

		$this->assertSame( 'wp-admin block-editor-page', $classes );
	}

	public function test_maybe_fullwidth_editor_removes_classic_styles_and_neutralizes_block_widths(): void {
		$this->add_filter(
			'blockstudio/settings/assets/reset/full_width',
			static function () {
				return array( 'page' );
			}
		);

		$assets   = new Assets();
		$settings = array(
			'__unstableResolvedAssets' => array(
				'styles' => '<link rel="stylesheet" href="classic.css"><style>.keep{display:block}</style>',
			),
		);
		$context  = (object) array(
			'post' => (object) array(
				'post_type' => 'page',
			),
		);

		$result = $assets->maybe_fullwidth_editor( $settings, $context );
		$styles = $result['__unstableResolvedAssets']['styles'];

		$this->assertStringNotContainsString( 'classic.css', $styles );
		$this->assertStringContainsString( 'blockstudio-fullwidth-editor', $styles );
		$this->assertStringContainsString( '.editor-styles-wrapper :where(.blockstudio-block):not([class*="max-w-"]){max-width:none}', $styles );
		$this->assertStringContainsString( 'margin-left:0!important;margin-right:0!important', $styles );
	}

	public function test_maybe_fullwidth_editor_leaves_unconfigured_post_types_unchanged(): void {
		$this->add_filter(
			'blockstudio/settings/assets/reset/full_width',
			static function () {
				return array( 'page' );
			}
		);

		$assets   = new Assets();
		$settings = array(
			'__unstableResolvedAssets' => array(
				'styles' => '<link rel="stylesheet" href="classic.css"><style>.keep{display:block}</style>',
			),
		);
		$context  = (object) array(
			'post' => (object) array(
				'post_type' => 'post',
			),
		);

		$this->assertSame( $settings, $assets->maybe_fullwidth_editor( $settings, $context ) );
	}

	public function test_compile_scss_supports_bootstrap_prelude(): void {
		$bootstrap_path = BLOCKSTUDIO_DIR . '/node_modules/bootstrap/scss';

		$this->assertDirectoryExists( $bootstrap_path );

		$this->add_filter(
			'blockstudio/assets/process/scss/import_paths',
			function ( $paths ) use ( $bootstrap_path ) {
				$paths[] = $bootstrap_path;
				return $paths;
			}
		);

		$this->add_filter(
			'blockstudio/assets/process/scss/prelude',
			function () {
				return '@import "functions";' . "\n"
					. '@import "variables";' . "\n"
					. '@import "variables-dark";' . "\n"
					. '@import "maps";' . "\n"
					. '@import "mixins";';
			},
			10,
			3
		);

		$result = Assets::compile_scss(
			'.button { @include media-breakpoint-up(lg) { color: $primary; } }',
			BLOCKSTUDIO_DIR . '/tests/theme/style.scss'
		);

		$this->assertStringContainsString( '@media (min-width: 992px)', $result );
		$this->assertStringContainsString( 'color: #0d6efd;', $result );
	}

	public function test_get_imported_modification_times_changes_when_prelude_dependency_changes(): void {
		$directory = sys_get_temp_dir() . '/blockstudio-assets-' . uniqid( '', true );
		wp_mkdir_p( $directory );

		$path = $directory . '/style.scss';
		file_put_contents( $path, '.button { color: $color; }' );
		file_put_contents( $directory . '/_tokens.scss', '$color: #0d6efd;' );

		$this->add_filter(
			'blockstudio/assets/process/scss/import_paths',
			function ( $paths ) use ( $directory ) {
				$paths[] = $directory;
				return $paths;
			}
		);

		$this->add_filter(
			'blockstudio/assets/process/scss/prelude',
			function () {
				return '@import "tokens";';
			},
			10,
			3
		);

		$before = Assets::get_imported_modification_times( $path, '' );

		sleep( 1 );
		file_put_contents( $directory . '/_tokens.scss', '$color: #6610f2;' );
		clearstatcache();

		$after = Assets::get_imported_modification_times( $path, '' );

		$this->assertNotSame( $before, $after );
	}
}
