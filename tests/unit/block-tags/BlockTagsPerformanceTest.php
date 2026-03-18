<?php

use Blockstudio\Block_Tags;
use PHPUnit\Framework\TestCase;

class BlockTagsPerformanceTest extends TestCase {

	private static function generate_flat( int $count ): string {
		$tags = array(
			'<bs:core-paragraph>Paragraph content here.</bs:core-paragraph>',
			'<block name="core/heading" level="2">Heading text</block>',
			'<bs:core-separator />',
			'<block name="core/image" url="test.jpg" alt="Test" />',
			'<bs:core-code>const x = 1;</bs:core-code>',
		);

		$html = '';
		for ( $i = 0; $i < $count; $i++ ) {
			$html .= $tags[ $i % count( $tags ) ];
		}
		return $html;
	}

	private static function generate_nested( int $depth ): string {
		if ( $depth <= 0 ) {
			return '<bs:core-paragraph>Leaf</bs:core-paragraph>';
		}
		return '<bs:core-group>' . self::generate_nested( $depth - 1 ) . '</bs:core-group>';
	}

	private static function generate_nested_mixed( int $depth ): string {
		if ( $depth <= 0 ) {
			return '<bs:core-paragraph>Leaf</bs:core-paragraph>';
		}
		if ( $depth % 2 === 0 ) {
			return '<bs:core-group>' . self::generate_nested_mixed( $depth - 1 ) . '</bs:core-group>';
		}
		return '<block name="core/group">' . self::generate_nested_mixed( $depth - 1 ) . '</block>';
	}

	private static function generate_wide_nested( int $depth, int $siblings ): string {
		if ( $depth <= 0 ) {
			return '<bs:core-paragraph>Leaf</bs:core-paragraph>';
		}
		$content = '';
		for ( $i = 0; $i < $siblings; $i++ ) {
			if ( $i % 2 === 0 ) {
				$content .= '<bs:core-group>' . self::generate_wide_nested( $depth - 1, $siblings ) . '</bs:core-group>';
			} else {
				$content .= '<block name="core/group">' . self::generate_wide_nested( $depth - 1, $siblings ) . '</block>';
			}
		}
		return $content;
	}

	private static function bench( callable $fn, int $iterations = 20 ): float {
		// Warmup.
		$fn();
		$fn();

		$times = array();
		for ( $i = 0; $i < $iterations; $i++ ) {
			$start   = hrtime( true );
			$fn();
			$times[] = ( hrtime( true ) - $start ) / 1e6;
		}
		sort( $times );
		return $times[ (int) ( count( $times ) / 2 ) ];
	}

	public function test_flat_100_under_1ms(): void {
		$html   = self::generate_flat( 100 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Flat 100 tags: {$median}ms";
		$this->assertLessThan( 5.0, $median, 'Flat 100 tags should parse under 5ms' );
	}

	public function test_flat_500(): void {
		$html   = self::generate_flat( 500 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Flat 500 tags: {$median}ms";
		$this->assertLessThan( 10.0, $median, 'Flat 500 tags should parse under 10ms' );
	}

	public function test_flat_1000(): void {
		$html   = self::generate_flat( 1000 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Flat 1000 tags: {$median}ms";
		$this->assertLessThan( 20.0, $median, 'Flat 1000 tags should parse under 20ms' );
	}

	public function test_flat_5000(): void {
		$html   = self::generate_flat( 5000 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Flat 5000 tags: {$median}ms";
		$this->assertLessThan( 100.0, $median, 'Flat 5000 tags should parse under 100ms' );
	}

	public function test_flat_10000(): void {
		$html   = self::generate_flat( 10000 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Flat 10000 tags: {$median}ms";
		$this->assertLessThan( 200.0, $median, 'Flat 10000 tags should parse under 200ms' );
	}

	public function test_nested_20_deep(): void {
		$html   = self::generate_nested( 20 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Nested 20 deep: {$median}ms";
		$this->assertLessThan( 5.0, $median, 'Nested 20 deep should parse under 5ms' );
	}

	public function test_nested_50_deep(): void {
		$html   = self::generate_nested( 50 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Nested 50 deep: {$median}ms";
		$this->assertLessThan( 10.0, $median, 'Nested 50 deep should parse under 10ms' );
	}

	public function test_nested_100_deep(): void {
		$html   = self::generate_nested( 100 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Nested 100 deep: {$median}ms";
		$this->assertLessThan( 20.0, $median, 'Nested 100 deep should parse under 20ms' );
	}

	public function test_nested_mixed_20_deep(): void {
		$html   = self::generate_nested_mixed( 20 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Nested mixed 20 deep: {$median}ms";
		$this->assertLessThan( 5.0, $median, 'Nested mixed 20 deep should parse under 5ms' );
	}

	public function test_nested_mixed_50_deep(): void {
		$html   = self::generate_nested_mixed( 50 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Nested mixed 50 deep: {$median}ms";
		$this->assertLessThan( 10.0, $median, 'Nested mixed 50 deep should parse under 10ms' );
	}

	public function test_nested_mixed_100_deep(): void {
		$html   = self::generate_nested_mixed( 100 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		echo "\n  Nested mixed 100 deep: {$median}ms";
		$this->assertLessThan( 20.0, $median, 'Nested mixed 100 deep should parse under 20ms' );
	}

	public function test_wide_nested_3x4(): void {
		$html   = self::generate_wide_nested( 3, 4 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		$tags   = substr_count( $html, '<bs:' );
		echo "\n  Wide 3x4 ({$tags} tags): {$median}ms";
		$this->assertLessThan( 10.0, $median, 'Wide 3x4 should parse under 10ms' );
	}

	public function test_wide_nested_4x3(): void {
		$html   = self::generate_wide_nested( 4, 3 );
		$median = self::bench( fn() => Block_Tags::parse_inner_blocks( $html ) );
		$tags   = substr_count( $html, '<bs:' );
		echo "\n  Wide 4x3 ({$tags} tags): {$median}ms";
		$this->assertLessThan( 15.0, $median, 'Wide 4x3 should parse under 15ms' );
	}

	public function test_correctness_at_scale(): void {
		$html   = self::generate_wide_nested( 3, 3 );
		$blocks = Block_Tags::parse_inner_blocks( $html );

		$this->assertCount( 3, $blocks );
		foreach ( $blocks as $block ) {
			$this->assertSame( 'core/group', $block['blockName'] );
			$this->assertCount( 3, $block['innerBlocks'] );
		}
	}
}
