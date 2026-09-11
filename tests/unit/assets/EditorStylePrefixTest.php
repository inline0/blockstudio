<?php

use Blockstudio\Assets;
use PHPUnit\Framework\TestCase;

class EditorStylePrefixTest extends TestCase {

	private function prefix( string $css ): string {
		return Assets::prefix_editor_styles( $css );
	}

	public function test_top_level_selectors_are_prefixed(): void {
		$this->assertSame(
			'.editor-styles-wrapper .flex{display:flex}',
			$this->prefix( '.flex{display:flex}' )
		);
	}

	public function test_every_member_of_a_selector_list_is_prefixed(): void {
		$this->assertSame(
			'.editor-styles-wrapper .a,.editor-styles-wrapper .b{color:red}',
			$this->prefix( '.a, .b{color:red}' )
		);
	}

	public function test_conditional_at_rules_prefix_the_rules_they_wrap(): void {
		$this->assertSame(
			'@media (min-width:600px){.editor-styles-wrapper .a{color:red}}',
			$this->prefix( '@media (min-width:600px){.a{color:red}}' )
		);
		$this->assertSame(
			'@supports (display:grid){.editor-styles-wrapper .g{display:grid}}',
			$this->prefix( '@supports (display:grid){.g{display:grid}}' )
		);
		$this->assertSame(
			'@layer base{.editor-styles-wrapper .a{color:red}}',
			$this->prefix( '@layer base{.a{color:red}}' )
		);
		$this->assertSame(
			'@container (min-width:400px){.editor-styles-wrapper .a{color:red}}',
			$this->prefix( '@container (min-width:400px){.a{color:red}}' )
		);
	}

	public function test_nested_conditional_at_rules_are_prefixed_once(): void {
		$this->assertSame(
			'@media screen{@supports (display:grid){.editor-styles-wrapper .x{color:red}}}',
			$this->prefix( '@media screen{@supports (display:grid){.x{color:red}}}' )
		);
	}

	public function test_descriptor_at_rules_are_copied_verbatim(): void {
		$keyframes = '@keyframes spin{from{opacity:0}to{opacity:1}}';
		$this->assertSame( $keyframes, $this->prefix( $keyframes ) );

		$vendor = '@-webkit-keyframes spin{0%{opacity:0}100%{opacity:1}}';
		$this->assertSame( $vendor, $this->prefix( $vendor ) );

		$font = '@font-face{font-family:"X";src:url(a.woff2)}';
		$this->assertSame( $font, $this->prefix( $font ) );

		$page = '@page{margin:1cm}';
		$this->assertSame( $page, $this->prefix( $page ) );

		$property = '@property --x{syntax:"<length>";inherits:false;initial-value:0px}';
		$this->assertSame( $property, $this->prefix( $property ) );
	}

	public function test_statement_at_rules_stay_at_the_top_level(): void {
		$this->assertSame(
			'@charset "UTF-8";.editor-styles-wrapper .a{color:red}',
			$this->prefix( '@charset "UTF-8";.a{color:red}' )
		);
		$this->assertSame(
			'@layer base,components;.editor-styles-wrapper .a{color:red}',
			$this->prefix( '@layer base,components;.a{color:red}' )
		);
	}

	public function test_root_and_body_selectors_keep_their_editor_mapping(): void {
		$this->assertSame( ':root{--a:1px}', $this->prefix( ':root{--a:1px}' ) );
		$this->assertSame( '.editor-styles-wrapper{margin:0}', $this->prefix( 'body{margin:0}' ) );
		$this->assertSame(
			'.editor-styles-wrapper html{font-size:16px}',
			$this->prefix( 'html{font-size:16px}' )
		);
	}

	public function test_braces_and_commas_inside_values_do_not_split_rules(): void {
		$this->assertSame(
			'.editor-styles-wrapper .a::after{content:"}"}',
			$this->prefix( '.a::after{content:"}"}' )
		);
		$this->assertSame(
			'.editor-styles-wrapper .a{background:url("x{y}.png")}',
			$this->prefix( '.a{background:url("x{y}.png")}' )
		);
		$this->assertSame(
			'.editor-styles-wrapper a[href="a,b"]{color:red}',
			$this->prefix( 'a[href="a,b"]{color:red}' )
		);
		$this->assertSame(
			'.editor-styles-wrapper :is(.a,.b) .c{color:red}',
			$this->prefix( ':is(.a,.b) .c{color:red}' )
		);
	}

	public function test_declaration_blocks_are_not_rewritten(): void {
		$css = ".a{--tpl:'.b{color:red}';color:var(--tpl)}";

		$this->assertSame( '.editor-styles-wrapper ' . $css, $this->prefix( $css ) );
	}

	// A cold editor request prefixes one stylesheet per registered block in the
	// same request. Compiling each one back through SASS to nest it allocated a
	// whole stylesheet AST and exhausted 128M hosts, which left nothing cached,
	// so the next request fatalled identically.
	public function test_prefixing_a_large_stylesheet_stays_within_a_small_allocation(): void {
		$css = '';
		for ( $index = 0; $index < 4000; $index++ ) {
			$css .= ".u-$index,.v-$index>.w{display:flex;padding:{$index}px;color:rgb(1,2,3)}\n";
			if ( 0 === $index % 10 ) {
				$css .= "@media (min-width:{$index}px){.m-$index{gap:{$index}px}}\n";
			}
		}

		$this->assertGreaterThan( 250000, strlen( $css ) );

		gc_collect_cycles();
		$before = memory_get_usage();
		$result = $this->prefix( $css );
		$used   = memory_get_usage() - $before;

		$this->assertStringContainsString( '.editor-styles-wrapper .u-0,', $result );
		$this->assertLessThan( 12 * 1024 * 1024, $used );
	}
}
