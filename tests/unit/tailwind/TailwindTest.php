<?php

use Blockstudio\Tailwind;
use Blockstudio\Page_Registry;
use Blockstudio\Runtime_Cache;
use Blockstudio\Settings;
use PHPUnit\Framework\TestCase;

class TailwindTest extends TestCase {

	public function test_get_cache_dir_returns_string(): void {
		$result = Tailwind::get_cache_dir();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_get_cache_dir_contains_blockstudio_path(): void {
		$result = Tailwind::get_cache_dir();

		$this->assertStringContainsString( 'blockstudio/cache/sites/network-', $result );
		$this->assertStringEndsWith( '/tailwind', $result );
	}

	public function test_get_cache_dir_is_inside_shared_runtime_root(): void {
		$result = Tailwind::get_cache_dir();

		$this->assertStringStartsWith( Runtime_Cache::root() . '/sites/', $result );
	}

	public function test_prune_cache_keeps_new_file_and_removes_stale_files(): void {
		$dir    = Tailwind::get_cache_dir();
		$prefix = 'unit-prune-' . uniqid();
		$files  = array(
			$dir . '/' . $prefix . '-one.css',
			$dir . '/' . $prefix . '-two.css',
			$dir . '/' . $prefix . '-three.css',
		);

		wp_mkdir_p( $dir );
		foreach ( $files as $index => $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing temporary Tailwind cache fixtures.
			file_put_contents( $file, '/* ' . $index . ' */' );
		}

		$filter = static fn(): int => 2;
		add_filter( 'blockstudio/tailwind/cache_max_files', $filter );

		try {
			$method = new ReflectionMethod( Tailwind::class, 'prune_cache' );
			$method->setAccessible( true );
			$method->invoke( null, $files[2] );

			$remaining = array_values( array_filter( $files, 'is_file' ) );

			$this->assertFileExists( $files[2] );
			$this->assertLessThanOrEqual( 2, count( $remaining ) );
		} finally {
			remove_filter( 'blockstudio/tailwind/cache_max_files', $filter );

			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing temporary Tailwind cache fixtures.
					unlink( $file );
				}
			}
		}
	}

	public function test_compile_returns_html_unchanged_when_tailwind_disabled(): void {
		$cb = function () {
			return false;
		};
		add_filter( 'blockstudio/settings/tailwind/enabled', $cb );
		$this->reset_settings();

		$tailwind = new Tailwind();
		$html     = '<html><head></head><body><p class="text-red-500">Hello</p></body></html>';
		$result   = $tailwind->compile( $html );

		$this->assertSame( $html, $result );

		remove_filter( 'blockstudio/settings/tailwind/enabled', $cb );
		$this->reset_settings();
	}

	public function test_compile_injects_style_tag_when_tailwind_enabled(): void {
		if ( ! Settings::get( 'tailwind/enabled' ) ) {
			$this->markTestSkipped( 'Tailwind is not enabled in test theme settings.' );
		}

		$tailwind = new Tailwind();
		$html     = '<html><head></head><body><p class="text-red-500">Hello</p></body></html>';
		$result   = $tailwind->compile( $html );

		$this->assertStringContainsString( '<style id="blockstudio-tailwind">', $result );
		$this->assertStringContainsString( '</head>', $result );
	}

	public function test_compile_does_not_run_twice(): void {
		if ( ! Settings::get( 'tailwind/enabled' ) ) {
			$this->markTestSkipped( 'Tailwind is not enabled in test theme settings.' );
		}

		$tailwind = new Tailwind();
		$html     = '<html><head></head><body><p class="flex">Hello</p></body></html>';

		$first  = $tailwind->compile( $html );
		$second = $tailwind->compile( $first );

		$this->assertSame( $first, $second );
	}

	public function test_request_reset_allows_a_long_lived_instance_to_compile_again(): void {
		if ( ! Settings::get( 'tailwind/enabled' ) ) {
			$this->markTestSkipped( 'Tailwind is not enabled in test theme settings.' );
		}

		$tailwind = new Tailwind();
		$first    = '<html><head></head><body><p class="flex">First</p></body></html>';
		$next     = '<html><head></head><body><p class="grid">Next</p></body></html>';

		$this->assertStringContainsString( '<style id="blockstudio-tailwind">', $tailwind->compile( $first ) );
		$this->assertSame( $next, $tailwind->compile( $next ) );

		Tailwind::reset_request_state();

		$this->assertStringContainsString( '<style id="blockstudio-tailwind">', $tailwind->compile( $next ) );
	}

	public function test_compile_editor_css_includes_registered_page_template_candidates(): void {
		$cb = function () {
			return true;
		};
		add_filter( 'blockstudio/settings/tailwind/enabled', $cb );
		$this->reset_settings();

		$registry       = Page_Registry::instance();
		$existing_pages = $registry->get_pages();
		$existing_posts = $registry->get_synced_posts();
		$existing_paths = $registry->get_paths();
		$template_path  = tempnam( sys_get_temp_dir(), 'blockstudio-page-tailwind' );

		$this->assertIsString( $template_path );

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a temporary fixture for this test.
			file_put_contents( $template_path, '<div class="gap-14 lg:gap-16"></div>' );

			$registry->register(
				'tailwind-page-template-test',
				array(
					'name'          => 'tailwind-page-template-test',
					'template_path' => $template_path,
					'source_path'   => 'tailwind-page-template-test',
				)
			);

			$css = Tailwind::compile_editor_css();

			$this->assertStringContainsString( '.gap-14', $css );
			$this->assertStringContainsString( '.lg\\:gap-16', $css );
		} finally {
			if ( is_string( $template_path ) && file_exists( $template_path ) ) {
				unlink( $template_path );
			}

			$registry->reset();
			foreach ( $existing_paths as $path ) {
				$registry->add_path( $path );
			}
			foreach ( $existing_pages as $name => $page ) {
				$registry->register( $name, $page );
			}
			foreach ( $existing_posts as $source_path => $post_id ) {
				$registry->set_synced_post( $source_path, $post_id );
			}

			remove_filter( 'blockstudio/settings/tailwind/enabled', $cb );
			$this->reset_settings();
		}
	}

	public function test_scoped_tailwind_compiles_arbitrary_length_font_size(): void {
		$css = $this->generate_scoped_tailwind_css( 'text-[15px]' );

		$this->assertStringContainsString( 'font-size:15px', $css );
	}

	public function test_scoped_tailwind_preserves_arbitrary_math_spacing_after_minification(): void {
		$css = $this->generate_scoped_tailwind_css(
			'h-[clamp(9.375rem,7.635rem_+_8.699vw,15.8125rem)] w-[calc(100%_+_10px)] mb-[min(1rem_+_2vw,3rem)]'
		);

		$this->assertStringContainsString( 'height:clamp(9.375rem, 7.635rem + 8.699vw, 15.8125rem)', $css );
		$this->assertStringContainsString( 'width:calc(100% + 10px)', $css );
		$this->assertStringContainsString( 'margin-bottom:min(1rem + 2vw, 3rem)', $css );
	}

	public function test_scoped_tailwind_decodes_encoded_ampersand_arbitrary_selectors(): void {
		$css = $this->generate_scoped_tailwind_css( '[&_svg:not([class*=size-])]:size-4' );

		$this->assertStringContainsString( 'svg:not([class*=size-])', $css );
		$this->assertStringContainsString( 'width:calc(var(--spacing) * 4)', $css );
		$this->assertStringContainsString( 'height:calc(var(--spacing) * 4)', $css );
	}

	public function test_scoped_tailwind_preserves_full_import_layer_order(): void {
		require_once BLOCKSTUDIO_DIR . '/lib/tailwindphp-autoload.php';

		$css = \BlockstudioVendor\TailwindPHP\Tailwind::generate(
			array(
				'content' => '<div class="text-red-500"></div>',
				'css'     => '@import "tailwindcss"; @layer base { .text-red-500 { color: blue; } }',
				'minify'  => false,
			)
		);

		$layer_order       = strpos( $css, '@layer theme, base, components, utilities;' );
		$base_layer        = strpos( $css, '@layer base' );
		$utilities_layer   = strpos( $css, '@layer utilities' );
		$custom_base_layer = strrpos( $css, '@layer base' );

		$this->assertNotFalse( $layer_order );
		$this->assertNotFalse( $base_layer );
		$this->assertNotFalse( $utilities_layer );
		$this->assertNotFalse( $custom_base_layer );

		$this->assertLessThan( $base_layer, $layer_order );
		$this->assertLessThan( $utilities_layer, $layer_order );
		$this->assertLessThan( $custom_base_layer, $layer_order );
		$this->assertMatchesRegularExpression( '/@layer utilities\s*\{[^}]*\.text-red-500/s', $css );
	}

	public function test_get_cdn_url_returns_empty_when_disabled(): void {
		$cb = function () {
			return false;
		};
		add_filter( 'blockstudio/settings/tailwind/enabled', $cb );
		$this->reset_settings();

		$result = Tailwind::get_cdn_url();
		$this->assertSame( '', $result );

		remove_filter( 'blockstudio/settings/tailwind/enabled', $cb );
		$this->reset_settings();
	}

	public function test_get_cdn_url_returns_url_when_enabled(): void {
		if ( ! Settings::get( 'tailwind/enabled' ) ) {
			$this->markTestSkipped( 'Tailwind is not enabled in test theme settings.' );
		}

		$result = Tailwind::get_cdn_url();
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'cdn.js', $result );
	}

	private function reset_settings(): void {
		$ref = new ReflectionClass( Settings::class );

		$instance = $ref->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$settings = $ref->getProperty( 'settings' );
		$settings->setAccessible( true );
		$settings->setValue( null, array() );

		$options = $ref->getProperty( 'settings_options' );
		$options->setAccessible( true );
		$options->setValue( null, array() );

		$json = $ref->getProperty( 'settings_json' );
		$json->setAccessible( true );
		$json->setValue( null, null );

		$filters = $ref->getProperty( 'settings_filters' );
		$filters->setAccessible( true );
		$filters->setValue( null, array() );

		$filters_values = $ref->getProperty( 'settings_filters_values' );
		$filters_values->setAccessible( true );
		$filters_values->setValue( null, array() );

		Settings::get_instance();
	}

	private function generate_scoped_tailwind_css( string $classes ): string {
		require_once BLOCKSTUDIO_DIR . '/lib/tailwindphp-autoload.php';

		return \BlockstudioVendor\TailwindPHP\Tailwind::generate(
			array(
				'content' => '<div class="' . esc_attr( $classes ) . '"></div>',
				'minify'  => true,
			)
		);
	}
}
