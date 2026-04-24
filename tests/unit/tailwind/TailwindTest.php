<?php

use Blockstudio\Tailwind;
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

		$this->assertStringContainsString( 'blockstudio/tailwind/cache', $result );
	}

	public function test_get_cache_dir_is_inside_uploads(): void {
		$uploads = wp_upload_dir();
		$result  = Tailwind::get_cache_dir();

		$this->assertStringStartsWith( $uploads['basedir'], $result );
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
