<?php

use BlockstudioVendor\TailwindPHP\Minifier\CssMinifier;
use PHPUnit\Framework\TestCase;

class CssMinifierTest extends TestCase {

	public function test_preserves_spaces_around_plus_in_calc(): void {
		$css    = ':root { --px: calc(1rem + 0.5vw); }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( '1rem + 0.5vw', $result );
	}

	public function test_preserves_spaces_in_nested_clamp_calc(): void {
		$css    = ':root { --size: clamp(1.25rem, calc(1.106rem + 0.537vw), 1.75rem); }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( '1.106rem + 0.537vw', $result );
	}

	public function test_preserves_spaces_around_minus_in_calc(): void {
		$css    = '.foo { width: calc(100% - 2rem); }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( '100% - 2rem', $result );
	}

	public function test_strips_whitespace_around_braces(): void {
		$css    = '.foo { color: red; }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( '.foo{color:red}', $result );
	}

	public function test_strips_whitespace_around_colons(): void {
		$css    = '.foo { color : red ; }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( 'color:red', $result );
	}

	public function test_strips_whitespace_around_adjacent_sibling_combinator(): void {
		$css    = '.a + .b { color: red; }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( '.a+.b', $result );
	}

	public function test_strips_whitespace_around_child_combinator(): void {
		$css    = '.a > .b { color: red; }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( '.a>.b', $result );
	}

	public function test_strips_whitespace_around_general_sibling_combinator(): void {
		$css    = '.a ~ .b { color: red; }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( '.a~.b', $result );
	}

	public function test_handles_multiple_calc_expressions(): void {
		$css    = '.foo { margin: calc(1rem + 2px) calc(100% - 50px); }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( '1rem + 2px', $result );
		$this->assertStringContainsString( '100% - 50px', $result );
	}

	public function test_handles_deeply_nested_functions(): void {
		$css    = ':root { --x: max(1rem, min(2rem + 1vw, 3rem)); }';
		$result = CssMinifier::minify( $css );

		$this->assertStringContainsString( '2rem + 1vw', $result );
	}
}
