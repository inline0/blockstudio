<?php

use Blockstudio\Assets;
use Blockstudio\Block;
use Blockstudio\Block_Registry;
use Blockstudio\Build;
use Blockstudio\Single_Flight;
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

	private function buffering_after_template_redirect(): bool {
		$assets = new Assets();
		$depth  = ob_get_level();

		$assets->maybe_buffer_output();

		$buffering = bs_is_buffering();

		$this->assertSame( $buffering, ob_get_level() > $depth );

		if ( ob_get_level() > $depth ) {
			ob_end_clean();
		}

		return $buffering;
	}

	private function create_temporary_asset( string $filename, string $content ): string {
		// Under wp-content so the asset resolves to a real URL; a path outside it
		// has none, and an asset without a URL no longer renders a tag.
		$directory = WP_CONTENT_DIR . '/blockstudio-assets-' . uniqid( '', true );
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
			$this->assertStringContainsString( 'r(v(n),f(n));n.dataset.wpProcessed="1"', $result );
			$this->assertStringContainsString( 'o.observe(document.documentElement', $result );
			$this->assertStringNotContainsString( 'o.observe(document.body', $result );
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

	public function test_nested_scss_edit_recompiles_and_invalidates_the_runtime_watch(): void {
		$path = $this->create_temporary_asset( 'global.scss', '@import "scss/wysiwyg"; .example { @include wysiwyg; }' );
		$directory = dirname( $path );
		wp_mkdir_p( $directory . '/scss' );
		wp_mkdir_p( $directory . '/mixins' );
		file_put_contents( $directory . '/scss/_wysiwyg.scss', '@import "../mixins/wysiwyg";' );
		$nested = $directory . '/mixins/_wysiwyg.scss';
		file_put_contents( $nested, '@mixin wysiwyg { color: red; }' );
		$dependencies = Assets::get_asset_dependency_paths( $path );
		$this->assertSame( array( $directory . '/scss/_wysiwyg.scss', $nested ), $dependencies );
		$watch = \Blockstudio\Build_Cache::create_watch_snapshot( array_merge( array( $path ), $dependencies ) );
		$first = Assets::process( $path, '' );
		$this->assertStringContainsString( 'red', file_get_contents( $first ) );

		file_put_contents( $nested, '@mixin wysiwyg { color: blue; }' );
		touch( $nested, time() + 2 );
		clearstatcache( true, $nested );
		$second = Assets::process( $path, '' );
		$this->assertNotSame( $first, $second );
		$this->assertStringContainsString( 'blue', file_get_contents( $second ) );
		$this->assertFalse( \Blockstudio\Build_Cache::is_watch_valid( $watch ) );
	}

	public function test_scss_dependency_graph_deduplicates_cycles_and_shared_imports(): void {
		$path = $this->create_temporary_asset( 'style.scss', '@import "a", "b"; @use "sass:math"; @import "https://example.com/external.css";' );
		$directory = dirname( $path );
		file_put_contents( $directory . '/_a.scss', '@forward "shared";' );
		file_put_contents( $directory . '/_b.scss', '@use "shared";' );
		file_put_contents( $directory . '/_shared.scss', '@import "style"; $color: red;' );
		$this->assertSame(
			array( $directory . '/_a.scss', $directory . '/_b.scss', $directory . '/_shared.scss' ),
			Assets::get_asset_dependency_paths( $path )
		);
	}

	public function test_scss_prelude_and_import_paths_include_transitive_dependencies(): void {
		$path = $this->create_temporary_asset( 'style.scss', '.example { color: $color; }' );
		$directory = dirname( $path ) . '/vendor';
		wp_mkdir_p( $directory . '/tokens' );
		file_put_contents( $directory . '/_theme.scss', '@import "tokens";' );
		file_put_contents( $directory . '/tokens/_index.scss', '@import "colors";' );
		file_put_contents( $directory . '/tokens/_colors.scss', '$color: red;' );
		$this->add_filter( 'blockstudio/assets/process/scss/import_paths', static fn() => array( $directory ) );
		$this->add_filter( 'blockstudio/assets/process/scss/prelude', static fn() => '@import "theme";' );
		$this->assertSame(
			array( $directory . '/_theme.scss', $directory . '/tokens/_index.scss', $directory . '/tokens/_colors.scss' ),
			Assets::get_asset_dependency_paths( $path )
		);
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

	public function test_parse_output_removes_block_comments(): void {
		$name = 'blockstudio/type-text';

		if ( ! isset( Build::data()[ $name ] ) ) {
			$this->markTestSkipped( "{$name} not registered." );
		}

		$html = '<html><head></head><body>' . Block::comment( $name ) . '<p>Content</p></body></html>';

		$result = ( new Assets() )->parse_output( $html );

		$this->assertStringNotContainsString( '<!--blockstudio/', $result );
		$this->assertStringContainsString( '<body><p>Content</p>', $result );
	}

	public function test_parse_output_keeps_comments_of_unregistered_blocks(): void {
		$name = 'blockstudio/type-text';

		if ( ! isset( Build::data()[ $name ] ) ) {
			$this->markTestSkipped( "{$name} not registered." );
		}

		$comment      = Block::comment( $name );
		$unregistered = '<!--blockstudio/not-registered/block-->';
		$html         = '<html><head></head><body>' . $comment . '<p>a</p>' . $unregistered . $comment . '<p>b</p></body></html>';

		$result = ( new Assets() )->parse_output( $html );

		$this->assertStringNotContainsString( $comment, $result );
		$this->assertSame( 1, substr_count( $result, $unregistered ) );
		$this->assertStringContainsString( '<body><p>a</p>' . $unregistered . '<p>b</p>', $result );
	}

	public function test_parse_output_prints_block_assets_only_for_a_comment_on_the_page(): void {
		$page = $this->inline_limit_page( array( 'one.css' => '.one { color: red; }' ) );

		try {
			$tag = Assets::render_tag( 'one.css', $page['assets']['one.css'], $page['data'] );

			$this->assertStringContainsString( $tag . '</head>', $this->parse_inline_limit_page( $page ) );

			foreach ( array( '', '<!--blockstudio/not-registered/block-->', '<!--blockstudio/' . $page['data']['name'] ) as $body ) {
				Assets::reset_request_state();

				$output = ( new Assets() )->parse_output( '<html><head></head><body>' . $body . '</body></html>' );

				$this->assertStringNotContainsString( $tag, $output );
			}
		} finally {
			$this->remove_inline_limit_page( $page );
		}
	}

	public function test_parse_output_emits_each_bundled_ui_global_once(): void {
		$name     = 'blockstudio-test/ui-global-dedupe';
		$registry = Block_Registry::instance();
		$path     = BLOCKSTUDIO_DIR . '/includes/ui/blocks/button/root/index.php';
		$data     = array(
			'name'   => $name,
			'path'   => $path,
			'file'   => array( 'dirname' => dirname( $path ) ),
			'assets' => array(
				'global-style.css' => array(
					'path'   => BLOCKSTUDIO_DIR . '/includes/ui/blocks/global-style.css',
					'type'   => 'file',
					'editor' => false,
				),
				'global-script.js' => array(
					'path'   => BLOCKSTUDIO_DIR . '/includes/ui/blocks/global-script.js',
					'type'   => 'file',
					'editor' => false,
				),
			),
		);

		$registry->register_block( $name, new \WP_Block_Type( $name ) );
		$registry->set_block_data( $name, $data );
		Assets::reset_request_state();

		try {
			$html   = '<html><head></head><body>' . Block::comment( $name ) . '<button data-bsui-button>Button</button></body></html>';
			$output = ( new Assets() )->parse_output( $html );

			$this->assertSame( 1, substr_count( $output, 'id="blockstudio-ui-global"' ) );
			$this->assertSame( 1, substr_count( $output, 'id="blockstudio-ui-global-script"' ) );
			$this->assertStringNotContainsString( 'global-style.css?', $output );
			$this->assertStringNotContainsString( 'global-script.js?', $output );
		} finally {
			$registry->remove_block( $name );
			Assets::reset_request_state();
		}
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
		// Under wp-content so the asset resolves to a real URL; a path outside it
		// has none, and an asset without a URL no longer renders a tag.
		$directory = WP_CONTENT_DIR . '/blockstudio-assets-' . uniqid( '', true );
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

	public function test_buffering_is_on_by_default_on_the_frontend(): void {
		$assets = new Assets();
		$depth  = ob_get_level();

		$assets->maybe_buffer_output();

		$started = ob_get_level() > $depth;

		if ( $started ) {
			ob_end_clean();
		}

		$this->assertTrue( $started );
	}

	public function test_buffer_enabled_filter_skips_whole_document_buffering(): void {
		$this->add_filter( 'blockstudio/buffer/enabled', static fn(): bool => false );

		$assets = new Assets();
		$depth  = ob_get_level();

		$assets->maybe_buffer_output();

		$started = ob_get_level() > $depth;

		if ( $started ) {
			ob_end_clean();
		}

		$this->assertFalse( $started );
	}

	public function test_is_buffering_reports_a_buffered_frontend_response(): void {
		$buffering = $this->buffering_after_template_redirect();

		$this->assertTrue( $buffering );
	}

	public function test_is_buffering_is_false_when_the_buffer_filter_is_off(): void {
		$this->add_filter( 'blockstudio/buffer/enabled', static fn(): bool => false );

		$this->assertFalse( $this->buffering_after_template_redirect() );
	}

	public function test_is_buffering_is_false_in_the_admin(): void {
		if ( ! function_exists( 'set_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}

		set_current_screen( 'dashboard' );

		try {
			$buffering = $this->buffering_after_template_redirect();
		} finally {
			set_current_screen( 'front' );
		}

		$this->assertFalse( $buffering );
	}

	public function test_is_buffering_follows_the_latest_decision(): void {
		$this->assertTrue( $this->buffering_after_template_redirect() );

		$this->add_filter( 'blockstudio/buffer/enabled', static fn(): bool => false );

		$this->assertFalse( $this->buffering_after_template_redirect() );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_is_buffering_is_false_before_the_buffer_decision(): void {
		$this->assertFalse( bs_is_buffering() );
	}

	public function test_is_buffering_does_not_repeat_the_filter_or_reconsider_an_open_buffer(): void {
		$calls = 0;
		$this->add_filter(
			'blockstudio/buffer/enabled',
			static function () use ( &$calls ): bool {
				++$calls;
				return true;
			}
		);
		$depth = ob_get_level();

		try {
			( new Assets() )->maybe_buffer_output();
			$this->add_filter( 'blockstudio/buffer/enabled', static fn(): bool => false, 20 );
			$this->assertTrue( bs_is_buffering() );
			$this->assertTrue( bs_is_buffering() );
			$this->assertSame( $depth + 1, ob_get_level() );
			$this->assertSame( 1, $calls );
		} finally {
			while ( ob_get_level() > $depth ) {
				ob_end_clean();
			}
		}
	}

	public function test_is_buffering_is_false_in_the_customizer_preview(): void {
		if ( ! class_exists( WP_Customize_Manager::class ) ) {
			require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
		}
		$previous = $GLOBALS['wp_customize'] ?? null;
		// Avoid installing the Customizer's theme-switching hooks in this request.
		$customizer = ( new ReflectionClass( WP_Customize_Manager::class ) )->newInstanceWithoutConstructor();
		( new ReflectionProperty( WP_Customize_Manager::class, 'previewing' ) )->setValue( $customizer, true );
		$GLOBALS['wp_customize'] = $customizer;

		try {
			$this->assertFalse( $this->buffering_after_template_redirect() );
		} finally {
			if ( null === $previous ) {
				unset( $GLOBALS['wp_customize'] );
			} else {
				$GLOBALS['wp_customize'] = $previous;
			}
		}
	}

	public function test_compile_once_publishes_a_cold_asset(): void {
		$compiled = $this->temporary_compiled_path();
		$calls    = 0;

		$published = $this->compile_once(
			$compiled,
			static function () use ( &$calls ): string {
				++$calls;

				return '.cold { color: red; }';
			}
		);

		$this->assertTrue( $published );
		$this->assertSame( 1, $calls );
		$this->assertFileExists( $compiled );
		$this->assertSame( '.cold { color: red; }', file_get_contents( $compiled ) );
	}

	public function test_compile_once_skips_the_compiler_when_the_asset_is_already_published(): void {
		$compiled = $this->temporary_compiled_path();
		$calls    = 0;

		wp_mkdir_p( dirname( $compiled ) );
		file_put_contents( $compiled, '.published { color: red; }' );

		$published = $this->compile_once(
			$compiled,
			static function () use ( &$calls ): string {
				++$calls;

				return '.duplicate { color: blue; }';
			}
		);

		$this->assertTrue( $published );
		$this->assertSame( 0, $calls );
		$this->assertSame( '.published { color: red; }', file_get_contents( $compiled ) );
	}

	public function test_compile_once_skips_the_compiler_when_another_request_published_under_the_lock(): void {
		$compiled = $this->temporary_compiled_path();
		$calls    = 0;

		wp_mkdir_p( dirname( $compiled ) );

		// flock() locks an open file description, so a second acquire() contends
		// here exactly as a concurrent request would.
		$lock = Single_Flight::acquire( $compiled . '.lock' );
		$this->assertIsResource( $lock );
		$this->assertFalse( Single_Flight::acquire( $compiled . '.lock' ) );

		file_put_contents( $compiled, '.published { color: red; }' );

		$published = $this->compile_once(
			$compiled,
			static function () use ( &$calls ): string {
				++$calls;

				return '.duplicate { color: blue; }';
			}
		);

		Single_Flight::release( $lock );

		$this->assertTrue( $published );
		$this->assertSame( 0, $calls );
		$this->assertSame( '.published { color: red; }', file_get_contents( $compiled ) );
	}

	public function test_compile_once_releases_the_lock_after_publishing(): void {
		$compiled = $this->temporary_compiled_path();

		$this->compile_once( $compiled, static fn (): string => '.cold { color: red; }' );

		$lock = Single_Flight::acquire( $compiled . '.lock' );

		$this->assertIsResource( $lock );

		Single_Flight::release( $lock );
	}

	public function test_parse_output_links_every_external_stylesheet_by_default(): void {
		$page = $this->inline_limit_page(
			array(
				'one.css'   => str_repeat( 'a', 100 ),
				'two.css'   => str_repeat( 'b', 3000 ),
				'three.css' => str_repeat( 'c', 200 ),
			)
		);

		try {
			$output = $this->parse_inline_limit_page( $page );
			$links  = '';

			foreach ( $page['assets'] as $key => $asset ) {
				$links .= Assets::render_tag( $key, $asset, $page['data'] );
			}

			$this->assertSame( 3, substr_count( $output, '<link' ) );
			$this->assertStringNotContainsString( '<style id=', $output );
			$this->assertStringContainsString( $links . '</head>', $output );
		} finally {
			$this->remove_inline_limit_page( $page );
		}
	}

	public function test_parse_output_inlines_the_smallest_stylesheets_within_the_limit_in_source_order(): void {
		$page = $this->inline_limit_page(
			array(
				'one.css'   => str_repeat( 'a', 100 ),
				'two.css'   => str_repeat( 'b', 3000 ),
				'three.css' => str_repeat( 'c', 200 ),
			)
		);

		$this->add_filter( 'blockstudio/settings/assets/inline_limit', static fn(): int => 500 );

		try {
			$output = $this->parse_inline_limit_page( $page );
			$one    = $page['ids']['one.css'];
			$two    = $page['ids']['two.css'];
			$three  = $page['ids']['three.css'];

			$this->assert_stylesheet_inlined( $one, $output );
			$this->assertStringContainsString( str_repeat( 'a', 100 ) . '</style>', $output );
			$this->assert_stylesheet_linked( $two, $output );
			$this->assert_stylesheet_inlined( $three, $output );
			$this->assert_stylesheet_not_linked( $one, $output );
			$this->assertStringNotContainsString( str_repeat( 'b', 3000 ), $output );

			$positions = array(
				strpos( $output, "id='$one'" ),
				strpos( $output, "id='$two'" ),
				strpos( $output, "id='$three'" ),
			);

			$this->assertTrue( $positions[0] < $positions[1] && $positions[1] < $positions[2] );
			$this->assertStringNotContainsString( "\0", $output );
		} finally {
			$this->remove_inline_limit_page( $page );
		}
	}

	public function test_parse_output_inlines_up_to_the_limit_inclusive(): void {
		$page = $this->inline_limit_page(
			array(
				'one.css' => str_repeat( 'a', 100 ),
				'two.css' => str_repeat( 'b', 200 ),
			)
		);

		try {
			$this->add_filter( 'blockstudio/settings/assets/inline_limit', static fn(): int => 300 );
			$both = $this->parse_inline_limit_page( $page );

			$this->assertSame( 2, substr_count( $both, '<style id=' ) );
			$this->assertStringNotContainsString( '<link', $both );

			remove_all_filters( 'blockstudio/settings/assets/inline_limit' );
			$this->add_filter( 'blockstudio/settings/assets/inline_limit', static fn(): int => 299 );
			Assets::reset_request_state();
			$one = $this->parse_inline_limit_page( $page );

			$this->assertSame( 1, substr_count( $one, '<style id=' ) );
			$this->assertSame( 1, substr_count( $one, '<link' ) );

			remove_all_filters( 'blockstudio/settings/assets/inline_limit' );
			$this->add_filter( 'blockstudio/settings/assets/inline_limit', static fn(): int => 99 );
			Assets::reset_request_state();
			$none = $this->parse_inline_limit_page( $page );

			$this->assertSame( 0, substr_count( $none, '<style id=' ) );
			$this->assertSame( 2, substr_count( $none, '<link' ) );
		} finally {
			remove_all_filters( 'blockstudio/settings/assets/inline_limit' );
			$this->remove_inline_limit_page( $page );
		}
	}

	public function test_parse_output_counts_a_shared_stylesheet_once_against_the_limit(): void {
		$page = $this->inline_limit_page(
			array(
				'one.css' => str_repeat( 'a', 100 ),
				'two.css' => str_repeat( 'b', 150 ),
			)
		);

		$second_name = 'blockstudio-test/inline-limit-second';
		$registry    = Block_Registry::instance();
		$registry->register_block( $second_name, new \WP_Block_Type( $second_name ) );
		$registry->set_block_data(
			$second_name,
			array(
				'name'   => $second_name,
				'path'   => $page['data']['path'],
				'file'   => $page['data']['file'],
				'assets' => array( 'one.css' => $page['assets']['one.css'] ),
			)
		);

		$this->add_filter( 'blockstudio/settings/assets/inline_limit', static fn(): int => 250 );

		try {
			$html   = '<html><head></head><body>' . Block::comment( $second_name ) . Block::comment( $page['data']['name'] ) . '</body></html>';
			$output = ( new Assets() )->parse_output( $html );

			$this->assertSame( 1, substr_count( $output, str_repeat( 'a', 100 ) ) );
			$this->assertSame( 1, substr_count( $output, str_repeat( 'b', 150 ) ) );
			$this->assertStringNotContainsString( '<link', $output );
		} finally {
			$registry->remove_block( $second_name );
			$this->remove_inline_limit_page( $page );
		}
	}

	public function test_parse_output_keeps_inline_assets_and_their_position_when_stylesheets_are_inlined(): void {
		$page = $this->inline_limit_page(
			array(
				'one.css'        => str_repeat( 'a', 100 ),
				'two.inline.css' => str_repeat( 'b', 150 ),
				'three.css'      => str_repeat( 'c', 5000 ),
			),
			array( 'two.inline.css' => 'inline' )
		);

		$this->add_filter( 'blockstudio/settings/assets/inline_limit', static fn(): int => 1000 );

		try {
			$output = $this->parse_inline_limit_page( $page );
			$ids    = $page['ids'];

			$this->assert_stylesheet_inlined( $ids['two.inline.css'], $output );
			$this->assert_stylesheet_linked( $ids['three.css'], $output );
			$this->assertTrue( strpos( $output, "id='{$ids['one.css']}'" ) < strpos( $output, "id='{$ids['two.inline.css']}'" ) );
			$this->assertTrue( strpos( $output, "id='{$ids['two.inline.css']}'" ) < strpos( $output, "id='{$ids['three.css']}'" ) );
		} finally {
			$this->remove_inline_limit_page( $page );
		}
	}

	public function test_parse_output_keeps_stylesheets_linked_when_inlining_would_change_them(): void {
		$page = $this->inline_limit_page(
			array(
				'relative.css' => '.a{background:url(img/a.png)}',
				'quoted.css'   => ".b{background:url( 'b.png' )}",
				'import.css'   => '@import "other.css";.c{color:red}',
				'close.css'    => '.d::after{content:"</style>"}',
				'imageset.css' => '.e{background:image-set("e.png" 1x)}',
				'escaped.css'  => '.e{background:u\\72l(relative.png)}',
				'comments.css' => '@import/**/"relative.css";',
				'charset.css'  => '@charset "iso-8859-1";.e{color:red}',
				'html.css'     => '.e::before{content:"<!--<style>"}',
				'absolute.css' => '.f{background:url(/wp-content/f.png)}.g{background:url("https://example.test/g.png")}.h{background:url(data:image/png;base64,AAAA)}.i{fill:url(#mask)}',
			)
		);

		$this->add_filter( 'blockstudio/settings/assets/inline_limit', static fn(): int => 40000 );

		try {
			$output = $this->parse_inline_limit_page( $page );

			foreach ( array( 'relative.css', 'quoted.css', 'import.css', 'close.css', 'imageset.css', 'escaped.css', 'comments.css', 'charset.css', 'html.css' ) as $file ) {
				$this->assert_stylesheet_linked( $page['ids'][ $file ], $output );
			}

			$this->assert_stylesheet_inlined( $page['ids']['absolute.css'], $output );
		} finally {
			$this->remove_inline_limit_page( $page );
		}
	}

	public function test_inline_limit_keeps_missing_files_linked_without_warnings(): void {
		$method = new ReflectionMethod( Assets::class, 'resolve_inline_slots' );
		$slot   = array( 'output' => Assets::resolve_output( '/missing-blockstudio-asset.css' ), 'id' => 'missing', 'tag' => '<link id="missing">' );
		$this->assertSame( array( 'slot' => $slot['tag'] ), $method->invoke( null, array( 'slot' => $slot ), 40000 ) );
	}

	public function test_parse_output_is_identical_with_resolved_outputs(): void {
		$page = $this->inline_limit_page(
			array(
				'one.css'        => str_repeat( 'a', 100 ),
				'two.css'        => str_repeat( 'b', 3000 ),
				'three.css'      => '',
				'four-inline.js' => 'console.log(1);',
			),
			array( 'four-inline.js' => 'inline' )
		);

		try {
			foreach ( array( 0, 500 ) as $limit ) {
				remove_all_filters( 'blockstudio/settings/assets/inline_limit' );
				$this->add_filter( 'blockstudio/settings/assets/inline_limit', static fn(): int => $limit );

				$from_disk = $this->parse_inline_limit_page( $page );

				Block_Registry::instance()->set_block_data( $page['data']['name'], Assets::with_resolved_outputs( $page['data'] ) );
				$from_records = $this->parse_inline_limit_page( $page );
				Block_Registry::instance()->set_block_data( $page['data']['name'], $page['data'] );

				$this->assertSame( $from_disk, $from_records );
			}
		} finally {
			$this->remove_inline_limit_page( $page );
		}
	}

	public function test_render_tag_reads_a_resolved_output_instead_of_the_disk(): void {
		$path  = $this->create_temporary_asset( 'style.css', '.a{color:red}' );
		$block = array( 'name' => 'blockstudio-test/resolved-output' );
		$asset = array(
			'path'   => $path,
			'type'   => 'external',
			'editor' => false,
		);

		$from_disk = Assets::render_tag( 'style.css', $asset, $block );
		$output    = Assets::resolve_output( $path );

		$this->assertSame( strlen( '.a{color:red}' ), $output['size'] );
		$this->assertSame( filemtime( $path ), $output['sourceMtime'] );

		unlink( $path );

		$this->assertNull( Assets::render_tag( 'style.css', $asset, $block ) );
		$this->assertSame( $from_disk, Assets::render_tag( 'style.css', $asset + array( 'output' => $output ), $block ) );
	}

	public function test_module_styles_render_from_resolved_outputs(): void {
		$block_path = $this->create_temporary_asset( 'block.json', '{}' );
		$script     = dirname( $block_path ) . '/script.js';
		$module_css = Assets::get_dist_folder( $block_path ) . '/modules/example/1.0.0.css';

		file_put_contents( $script, 'console.log(1);' );
		wp_mkdir_p( dirname( $module_css ) );
		file_put_contents( $module_css, '.module{color:red}' );
		$this->temporary_directories[] = Assets::get_dist_folder( $block_path );

		$block = array(
			'name'   => 'blockstudio-test/module-styles',
			'path'   => $block_path,
			'assets' => array(
				'script.js' => array(
					'path'   => $script,
					'type'   => 'external',
					'editor' => false,
				),
			),
		);

		$resolved = Assets::with_resolved_outputs( $block );

		$this->assertSame( array( wp_normalize_path( $module_css ) ), array_map( 'wp_normalize_path', array_keys( $resolved['moduleStyles'] ) ) );
		$this->assertArrayHasKey( 'output', $resolved['assets']['script.js'] );
		$this->assertSame( $block, Assets::without_resolved_outputs( $resolved ) );

		$ids       = array();
		$from_disk = '';
		Assets::get_module_css_assets( $block, $ids, $from_disk );

		$ids          = array();
		$from_records = '';
		Assets::get_module_css_assets( $resolved, $ids, $from_records );

		$this->assertStringContainsString( "id='blockstudio-blockstudio-test-module-styles-1-0-0-css'", $from_disk );
		$this->assertSame( $from_disk, $from_records );
	}

	public function test_cached_outputs_keep_url_and_disable_filters_request_local(): void {
		$path = $this->create_temporary_asset( 'style.css', '.a{color:red}' );
		$block = array( 'name' => 'blockstudio-test/cached-filters' );
		$data = array( 'path' => $path, 'output' => Assets::resolve_output( $path ) );
		$cdn = 'https://first.example.test/style.css';
		$url = static function () use ( &$cdn ): string { return $cdn; };
		$this->add_filter( 'blockstudio/files/url', $url );
		$tag = Assets::render_tag( 'style.css', $data, $block );
		$this->assertStringContainsString( $cdn, $tag );
		$cdn = 'https://second.example.test/style.css';
		$this->assertStringContainsString( $cdn, Assets::render_tag( 'style.css', $data, $block ) );
		$this->add_filter( 'blockstudio/assets/disable', static fn(): array => array( Assets::get_id( 'style.css', $block ) ) );
		$this->assertNull( Assets::render_tag( 'style.css', $data, $block ) );
	}

	public function test_cached_output_is_ignored_after_source_path_changes_or_metadata_is_malformed(): void {
		$one = $this->create_temporary_asset( 'one.css', '.one{}' );
		$two = $this->create_temporary_asset( 'two.css', '.two{}' );
		$block = array( 'name' => 'blockstudio-test/output-validation' );
		$expected = Assets::render_tag( 'two.css', array( 'path' => $two ), $block );
		$this->assertSame( $expected, Assets::render_tag( 'two.css', array( 'path' => $two, 'output' => Assets::resolve_output( $one ) ), $block ) );
		$this->assertSame( $expected, Assets::render_tag( 'two.css', array( 'path' => $two, 'output' => array( 'source' => wp_normalize_path( $two ), 'path' => $one ) ), $block ) );
	}

	public function test_missing_cached_inline_file_returns_no_markup_without_warnings(): void {
		$path = $this->create_temporary_asset( 'style.inline.css', '.inline{}' );
		$data = array( 'path' => $path, 'output' => Assets::resolve_output( $path ) );
		unlink( $path );
		$this->assertNull( Assets::render_inline( 'style.inline.css', $data, array( 'name' => 'blockstudio-test/missing-inline' ), true ) );
	}

	private function assert_stylesheet_inlined( string $id, string $output ): void {
		$this->assertMatchesRegularExpression( "/<style id='" . preg_quote( $id, '/' ) . "'\\s/", $output );
	}

	private function assert_stylesheet_linked( string $id, string $output ): void {
		$this->assertMatchesRegularExpression( "/<link rel='stylesheet'\\s+id='" . preg_quote( $id, '/' ) . "'/", $output );
	}

	private function assert_stylesheet_not_linked( string $id, string $output ): void {
		$this->assertDoesNotMatchRegularExpression( "/<link rel='stylesheet'\\s+id='" . preg_quote( $id, '/' ) . "'/", $output );
	}

	private function inline_limit_page( array $files, array $types = array() ): array {
		$name     = 'blockstudio-test/inline-limit';
		$registry = Block_Registry::instance();
		$data     = array(
			'name'   => $name,
			'assets' => array(),
		);
		$ids      = array();

		foreach ( $files as $filename => $contents ) {
			$path = $this->create_temporary_asset( $filename, $contents );

			if ( ! isset( $data['path'] ) ) {
				$data['path'] = $path;
				$data['file'] = array( 'dirname' => dirname( $path ) );
			}

			$data['assets'][ $filename ] = array(
				'path'   => $path,
				'type'   => $types[ $filename ] ?? 'external',
				'editor' => false,
			);
		}

		foreach ( array_keys( $files ) as $filename ) {
			$ids[ $filename ] = Assets::get_id( $filename, $data );
		}

		$this->add_filter(
			'blockstudio/assets/disable',
			static fn( array $ids ): array => array_merge( $ids, array( 'blockstudio-blockstudio-init-global-style-css' ) )
		);

		$registry->register_block( $name, new \WP_Block_Type( $name ) );
		$registry->set_block_data( $name, $data );
		Assets::reset_request_state();

		return array(
			'data'   => $data,
			'assets' => $data['assets'],
			'ids'    => $ids,
		);
	}

	private function parse_inline_limit_page( array $page ): string {
		Assets::reset_request_state();

		return ( new Assets() )->parse_output( '<html><head></head><body>' . Block::comment( $page['data']['name'] ) . '</body></html>' );
	}

	private function remove_inline_limit_page( array $page ): void {
		Block_Registry::instance()->remove_block( $page['data']['name'] );
		Assets::reset_request_state();
	}

	private function temporary_compiled_path(): string {
		$source = $this->create_temporary_asset( 'style.css', '.source { color: red; }' );

		return dirname( $source ) . '/_dist/style.css';
	}

	private function compile_once( string $compiled_filename, callable $compiler ): bool {
		$method = ( new \ReflectionClass( Assets::class ) )->getMethod( 'compile_once' );

		return (bool) $method->invoke( null, $compiled_filename, $compiler );
	}
}
