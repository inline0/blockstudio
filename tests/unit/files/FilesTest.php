<?php

use Blockstudio\Files;
use PHPUnit\Framework\TestCase;

class FilesTest extends TestCase {

	private string $tmp_dir;

	protected function setUp(): void {
		$this->tmp_dir = sys_get_temp_dir() . '/blockstudio-files-test-' . uniqid();
		mkdir( $this->tmp_dir, 0755, true );
	}

	protected function tearDown(): void {
		if ( is_dir( $this->tmp_dir ) ) {
			Files::delete_all_files( $this->tmp_dir );
		}
	}

	// get_render_template()

	public function test_get_render_template_finds_index_php(): void {
		$block_dir = $this->tmp_dir . '/block';
		mkdir( $block_dir );
		file_put_contents( $block_dir . '/index.php', '<?php // render' );
		file_put_contents( $block_dir . '/block.json', '{}' );

		$result = Files::get_render_template( $block_dir . '/block.json' );
		$this->assertSame( $block_dir . '/index.php', $result );
	}

	public function test_get_render_template_finds_index_twig(): void {
		$block_dir = $this->tmp_dir . '/block';
		mkdir( $block_dir );
		file_put_contents( $block_dir . '/index.twig', '{{ content }}' );
		file_put_contents( $block_dir . '/block.json', '{}' );

		$result = Files::get_render_template( $block_dir . '/block.json' );
		$this->assertSame( $block_dir . '/index.twig', $result );
	}

	public function test_get_render_template_finds_index_blade_php(): void {
		$block_dir = $this->tmp_dir . '/block';
		mkdir( $block_dir );
		file_put_contents( $block_dir . '/index.blade.php', '@extends("layout")' );
		file_put_contents( $block_dir . '/block.json', '{}' );

		$result = Files::get_render_template( $block_dir . '/block.json' );
		$this->assertSame( $block_dir . '/index.blade.php', $result );
	}

	public function test_get_render_template_prefers_index_php_over_others(): void {
		$block_dir = $this->tmp_dir . '/block';
		mkdir( $block_dir );
		file_put_contents( $block_dir . '/index.php', '<?php // render' );
		file_put_contents( $block_dir . '/index.twig', '{{ content }}' );
		file_put_contents( $block_dir . '/index.blade.php', '@extends("layout")' );
		file_put_contents( $block_dir . '/block.json', '{}' );

		$result = Files::get_render_template( $block_dir . '/block.json' );
		$this->assertSame( $block_dir . '/index.php', $result );
	}

	public function test_get_render_template_returns_false_for_missing_templates(): void {
		$block_dir = $this->tmp_dir . '/block';
		mkdir( $block_dir );
		file_put_contents( $block_dir . '/block.json', '{}' );

		$result = Files::get_render_template( $block_dir . '/block.json' );
		$this->assertFalse( $result );
	}

	public function test_get_render_template_returns_false_for_nonexistent_directory(): void {
		$result = Files::get_render_template( '/nonexistent/path/block.json' );
		$this->assertFalse( $result );
	}

	// is_directory_empty()

	public function test_is_directory_empty_returns_true_for_empty_dir(): void {
		$this->assertTrue( Files::is_directory_empty( $this->tmp_dir ) );
	}

	public function test_is_directory_empty_returns_false_for_non_empty_dir(): void {
		file_put_contents( $this->tmp_dir . '/file.txt', 'content' );
		$this->assertFalse( Files::is_directory_empty( $this->tmp_dir ) );
	}

	public function test_is_directory_empty_returns_false_for_non_directory(): void {
		$file = $this->tmp_dir . '/file.txt';
		file_put_contents( $file, 'content' );
		$this->assertFalse( Files::is_directory_empty( $file ) );
	}

	public function test_is_directory_empty_returns_false_for_nonexistent_path(): void {
		$this->assertFalse( Files::is_directory_empty( '/nonexistent/path' ) );
	}

	public function test_is_directory_empty_with_subdirectory(): void {
		mkdir( $this->tmp_dir . '/subdir' );
		$this->assertFalse( Files::is_directory_empty( $this->tmp_dir ) );
	}

	// get_files_with_extension()

	public function test_get_files_with_extension_finds_matching_files(): void {
		file_put_contents( $this->tmp_dir . '/a.css', 'body{}' );
		file_put_contents( $this->tmp_dir . '/b.css', 'p{}' );
		file_put_contents( $this->tmp_dir . '/c.js', 'var x;' );

		$result = Files::get_files_with_extension( $this->tmp_dir, 'css' );
		$this->assertCount( 2, $result );

		$basenames = array_map( 'basename', $result );
		sort( $basenames );
		$this->assertSame( array( 'a.css', 'b.css' ), $basenames );
	}

	public function test_get_files_with_extension_searches_recursively(): void {
		$sub = $this->tmp_dir . '/sub';
		mkdir( $sub );
		file_put_contents( $this->tmp_dir . '/top.php', '<?php' );
		file_put_contents( $sub . '/nested.php', '<?php' );

		$result = Files::get_files_with_extension( $this->tmp_dir, 'php' );
		$this->assertCount( 2, $result );
	}

	public function test_get_files_with_extension_returns_empty_for_no_matches(): void {
		file_put_contents( $this->tmp_dir . '/file.txt', 'text' );

		$result = Files::get_files_with_extension( $this->tmp_dir, 'css' );
		$this->assertSame( array(), $result );
	}

	public function test_get_files_with_extension_returns_empty_for_nonexistent_dir(): void {
		$result = Files::get_files_with_extension( '/nonexistent/dir', 'php' );
		$this->assertSame( array(), $result );
	}

	public function test_get_files_with_extension_returns_empty_for_empty_dir(): void {
		$result = Files::get_files_with_extension( $this->tmp_dir, 'php' );
		$this->assertSame( array(), $result );
	}

	// delete_all_files()

	public function test_delete_all_files_removes_contents(): void {
		file_put_contents( $this->tmp_dir . '/a.txt', 'a' );
		$sub = $this->tmp_dir . '/sub';
		mkdir( $sub );
		file_put_contents( $sub . '/b.txt', 'b' );

		$result = Files::delete_all_files( $this->tmp_dir );
		$this->assertTrue( $result );
		$this->assertFalse( is_dir( $this->tmp_dir ) );
	}

	public function test_delete_all_files_without_deleting_dir(): void {
		file_put_contents( $this->tmp_dir . '/a.txt', 'a' );

		$result = Files::delete_all_files( $this->tmp_dir, false );
		$this->assertTrue( $result );
		$this->assertTrue( is_dir( $this->tmp_dir ) );
		$this->assertTrue( Files::is_directory_empty( $this->tmp_dir ) );
	}

	public function test_delete_all_files_returns_false_for_nonexistent(): void {
		$result = Files::delete_all_files( '/nonexistent/path' );
		$this->assertFalse( $result );
	}

	// get_folder_structure_with_contents()

	public function test_get_folder_structure_with_contents(): void {
		file_put_contents( $this->tmp_dir . '/file.txt', 'hello' );
		$sub = $this->tmp_dir . '/sub';
		mkdir( $sub );
		file_put_contents( $sub . '/nested.txt', 'world' );

		$result = Files::get_folder_structure_with_contents( $this->tmp_dir );

		$this->assertIsArray( $result );
		$this->assertSame( 'hello', $result['file.txt'] );
		$this->assertSame( 'world', $result['sub']['nested.txt'] );
	}

	public function test_get_folder_structure_returns_empty_for_nonexistent(): void {
		$result = Files::get_folder_structure_with_contents( '/nonexistent/path' );
		$this->assertSame( array(), $result );
	}

	// get_relative_url()

	public function test_get_root_folder_returns_string(): void {
		$result = Files::get_root_folder();
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}
}
