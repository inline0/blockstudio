<?php

use Blockstudio\Pattern_Discovery;
use Blockstudio\Pattern_Registry;
use Blockstudio\Patterns;
use PHPUnit\Framework\TestCase;

class PatternsTest extends TestCase {

	private string $patterns_path;

	protected function setUp(): void {
		$this->patterns_path = get_template_directory() . '/patterns';

		Patterns::reset();
		// WP 6.9 core triggers "Undefined array key" in blocks.php during pattern registration.
		@Patterns::init( array( 'force' => true ) );
	}

	protected function tearDown(): void {
		Patterns::reset();
	}

	// patterns()

	public function test_patterns_returns_array(): void {
		$patterns = Patterns::patterns();
		$this->assertIsArray( $patterns );
	}

	public function test_patterns_is_not_empty(): void {
		$patterns = Patterns::patterns();
		$this->assertNotEmpty( $patterns );
	}

	public function test_patterns_contains_test_pattern(): void {
		$patterns = Patterns::patterns();
		$this->assertArrayHasKey( 'test-pattern', $patterns );
	}

	public function test_patterns_contains_tailwind_pattern(): void {
		$patterns = Patterns::patterns();
		$this->assertArrayHasKey( 'test-pattern-tailwind', $patterns );
	}

	// get_pattern()

	public function test_get_pattern_returns_array_for_known_pattern(): void {
		$pattern = Patterns::get_pattern( 'test-pattern' );
		$this->assertIsArray( $pattern );
	}

	public function test_get_pattern_has_expected_title(): void {
		$pattern = Patterns::get_pattern( 'test-pattern' );
		$this->assertSame( 'Blockstudio E2E Test Pattern', $pattern['title'] );
	}

	public function test_get_pattern_has_expected_name(): void {
		$pattern = Patterns::get_pattern( 'test-pattern' );
		$this->assertSame( 'test-pattern', $pattern['name'] );
	}

	public function test_get_pattern_has_description(): void {
		$pattern = Patterns::get_pattern( 'test-pattern' );
		$this->assertSame( 'A test pattern for E2E testing', $pattern['description'] );
	}

	public function test_get_pattern_has_categories(): void {
		$pattern = Patterns::get_pattern( 'test-pattern' );
		$this->assertIsArray( $pattern['categories'] );
		$this->assertContains( 'featured', $pattern['categories'] );
	}

	public function test_get_pattern_has_keywords(): void {
		$pattern = Patterns::get_pattern( 'test-pattern' );
		$this->assertIsArray( $pattern['keywords'] );
		$this->assertContains( 'test', $pattern['keywords'] );
		$this->assertContains( 'blockstudio', $pattern['keywords'] );
	}

	public function test_get_pattern_has_template_path(): void {
		$pattern = Patterns::get_pattern( 'test-pattern' );
		$this->assertArrayHasKey( 'template_path', $pattern );
		$this->assertStringEndsWith( '/index.php', $pattern['template_path'] );
	}

	public function test_get_pattern_returns_null_for_unknown(): void {
		$pattern = Patterns::get_pattern( 'nonexistent-pattern' );
		$this->assertNull( $pattern );
	}

	// Pattern data structure

	public function test_pattern_data_has_required_keys(): void {
		$pattern       = Patterns::get_pattern( 'test-pattern' );
		$expected_keys = array( 'name', 'title', 'description', 'categories', 'keywords', 'template_path', 'json_path', 'directory', 'source_path' );

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $pattern, "Missing key: {$key}" );
		}
	}

	public function test_pattern_is_not_twig(): void {
		$pattern = Patterns::get_pattern( 'test-pattern' );
		$this->assertFalse( $pattern['is_twig'] );
	}

	public function test_pattern_is_not_blade(): void {
		$pattern = Patterns::get_pattern( 'test-pattern' );
		$this->assertFalse( $pattern['is_blade'] );
	}

	// Discovery

	public function test_discovery_finds_multiple_patterns(): void {
		$patterns = Patterns::patterns();
		$this->assertGreaterThanOrEqual( 2, count( $patterns ) );
	}

	public function test_discovery_finds_twig_pattern(): void {
		$patterns = Patterns::patterns();
		$this->assertArrayHasKey( 'test-pattern-twig', $patterns );
	}

	public function test_twig_pattern_has_is_twig_flag(): void {
		$pattern = Patterns::get_pattern( 'test-pattern-twig' );
		$this->assertTrue( $pattern['is_twig'] );
	}

	public function test_discovery_finds_blade_pattern(): void {
		$patterns = Patterns::patterns();
		$this->assertArrayHasKey( 'test-pattern-blade', $patterns );
	}

	public function test_blade_pattern_has_is_blade_flag(): void {
		$pattern = Patterns::get_pattern( 'test-pattern-blade' );
		$this->assertTrue( $pattern['is_blade'] );
	}

	// get_registered_paths()

	public function test_get_registered_paths_returns_array(): void {
		$paths = Patterns::get_registered_paths();
		$this->assertIsArray( $paths );
		$this->assertNotEmpty( $paths );
	}

	public function test_get_registered_paths_contains_theme_patterns_dir(): void {
		$paths = Patterns::get_registered_paths();
		$this->assertContains( $this->patterns_path, $paths );
	}

	// WordPress registration

	public function test_pattern_registered_with_wordpress(): void {
		$registered = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'blockstudio/test-pattern' );
		$this->assertIsArray( $registered );
		$this->assertSame( 'Blockstudio E2E Test Pattern', $registered['title'] );
	}

	public function test_registered_pattern_has_content(): void {
		$registered = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'blockstudio/test-pattern' );
		$this->assertNotEmpty( $registered['content'] );
	}

	// reset()

	public function test_reset_clears_patterns(): void {
		$this->assertNotEmpty( Patterns::patterns() );

		Patterns::reset();

		$this->assertEmpty( Patterns::patterns() );
	}
}
