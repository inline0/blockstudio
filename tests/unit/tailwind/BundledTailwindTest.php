<?php
/**
 * Production TailwindPHP bundle regression coverage, without WordPress.
 *
 * @package Blockstudio
 */

use PHPUnit\Framework\TestCase;

class BundledTailwindTest extends TestCase {

	protected function setUp(): void {
		require_once dirname( __DIR__, 3 ) . '/lib/tailwindphp-autoload.php';
		require_once dirname( __DIR__, 3 ) . '/lib/tailwindphp/tailwindphp/src/Cli/Application.php';
	}

	public function test_bundle_reports_the_locked_186_release(): void {
		$this->assertSame( '1.8.6', \BlockstudioVendor\TailwindPHP\Cli\Application::VERSION );
	}

	public function test_minified_generation_preserves_zero_units_in_functions(): void {
		$css = \BlockstudioVendor\TailwindPHP\Tailwind::generate(
			array(
				'content' => '<div class="w-[var(--x,_0px)] h-[max(0px,_1rem)] max-h-[calc(100vh-10rem-var(--offset,0px))]"></div>',
				'minify'  => true,
			)
		);
		$this->assertStringContainsString( 'width:var(--x, 0px)', $css );
		$this->assertStringContainsString( 'height:max(0px, 1rem)', $css );
		$this->assertStringContainsString( 'var(--offset, 0px)', $css );
	}

	public function test_minifier_preserves_literals_and_function_units_in_repeated_rules(): void {
		$rule = '.top-\\[0px\\]{top:0px;width:var(--x, 0px);height:max(0px, 1rem);content:"0px";background:url("/img/0px.png")}';
		$css  = \BlockstudioVendor\TailwindPHP\Minifier\CssMinifier::minify( str_repeat( $rule, 1000 ) );
		$this->assertSame( 1000, substr_count( $css, 'width:var(--x, 0px)' ) );
		$this->assertSame( 1000, substr_count( $css, 'height:max(0px, 1rem)' ) );
		$this->assertSame( 1000, substr_count( $css, 'content:"0px"' ) );
		$this->assertSame( 1000, substr_count( $css, '/img/0px.png' ) );
		$this->assertSame( 1000, substr_count( $css, '.top-\\[0px\\]' ) );
		$this->assertStringContainsString( 'top:0;', $css );
	}
}
