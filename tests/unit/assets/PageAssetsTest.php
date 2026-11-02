<?php
/**
 * Asset owner tests.
 *
 * @package Blockstudio
 */

// phpcs:disable WordPress.WP.AlternativeFunctions

use Blockstudio\Assets;
use Blockstudio\Block;
use PHPUnit\Framework\TestCase;

class PageAssetsTest extends TestCase {

	private string $directory;

	protected function setUp(): void {
		Assets::reset_request_state();
		$this->directory = wp_normalize_path( WP_CONTENT_DIR . '/blockstudio-page-assets-' . uniqid() );
		wp_mkdir_p( $this->directory );
	}

	protected function tearDown(): void {
		Assets::reset_request_state();
		$this->remove_directory( $this->directory );
	}

	public function test_register_owner_returns_a_marker_for_existing_files_only(): void {
		$script = $this->asset( 'script.js', 'console.log("page");' );

		$this->assertSame( '', Assets::register_owner( 'page-home', array( $this->directory . '/missing.js' ) ) );
		$this->assertSame( '', Assets::register_owner( 'page-home', array( $this->asset( 'notes.txt', 'text' ) ) ) );
		$this->assertSame( '', Assets::register_owner( '!!!', array( $script ) ) );
		$this->assertSame( '<!--blockstudio-assets/page-home-->', Assets::register_owner( 'Page Home', array( $script ) ) );
		$this->assertSame( '<!--blockstudio-assets/page-home-->', Assets::owner_marker( 'page-home' ) );
	}

	public function test_parse_output_emits_owner_assets_once_per_page(): void {
		$script = $this->asset( 'script.js', 'console.log("page");' );
		$style  = $this->asset( 'style.css', '.page{color:red}' );
		$marker = Assets::register_owner( 'page-home', array( $script, array( 'path' => $style ) ) );
		Assets::register_owner( 'layout-home', array( $script ) );

		$html = ( new Assets() )->parse_output(
			'<html><head></head><body>' . $marker . '<main>Page</main>' . $marker . Assets::owner_marker( 'layout-home' ) . '</body></html>'
		);

		$this->assertStringNotContainsString( '<!--blockstudio-assets/', $html );
		$this->assertSame( 1, substr_count( $html, "id='blockstudio-owner-page-home-script-js'" ) );
		$this->assertSame( 1, preg_match_all( "#<script[^>]*id='blockstudio-owner-#", $html ) );
		$this->assertSame( 1, substr_count( $html, "id='blockstudio-owner-page-home-style-css'" ) );
		$this->assertMatchesRegularExpression( "#<head>.*blockstudio-owner-page-home-style-css.*</head>#s", $html );
		$this->assertMatchesRegularExpression( "#<main>Page</main>.*<script type='module'[^>]*blockstudio-owner-page-home-script-js.*</body>#s", $html );
	}

	public function test_owner_assets_carry_block_asset_versions_or_an_explicit_version(): void {
		$script = $this->asset( 'script.js', 'console.log("page");' );
		$island = $this->asset( 'island.js', 'console.log("island");' );
		touch( $script, 1700000000 );
		clearstatcache();
		$marker = Assets::register_owner(
			'page-home',
			array(
				$script,
				array(
					'path'    => $island,
					'type'    => 'module',
					'version' => 'build 7',
				),
			)
		);

		$html = ( new Assets() )->parse_output( '<html><head></head><body>' . $marker . '</body></html>' );

		$this->assertMatchesRegularExpression( "#id='blockstudio-owner-page-home-script-js' src='[^']+\?ver=1700000000'#", $html );
		$this->assertMatchesRegularExpression( "#id='blockstudio-owner-page-home-island-js' src='[^']+\?ver=build%207'#", $html );
	}

	public function test_inline_naming_convention_prints_the_asset_inline(): void {
		$marker = Assets::register_owner( 'page-home', array( $this->asset( 'boot.inline.js', 'window.pageBooted = true;' ) ) );

		$html = ( new Assets() )->parse_output( '<html><head></head><body>' . $marker . '</body></html>' );

		$this->assertMatchesRegularExpression( "#<script id='blockstudio-owner-page-home-boot-inline-js'[^>]*type=\"module\"[^>]*>[^<]*pageBooted[^<]*</script></body>#", $html );
	}

	public function test_markers_without_a_registered_owner_are_removed(): void {
		$html = ( new Assets() )->parse_output( '<html><head></head><body><!--blockstudio-assets/page-gone--><p>Page</p></body></html>' );

		$this->assertStringNotContainsString( '<!--blockstudio-assets/', $html );
		$this->assertStringNotContainsString( 'blockstudio-owner-', $html );
		$this->assertStringContainsString( '<body><p>Page</p>', $html );
	}

	public function test_strip_comments_removes_owner_markers(): void {
		$this->assertSame(
			'<p>Page</p><!--blockstudio/not-a-block-->',
			Block::strip_comments( '<!--blockstudio-assets/page-home--><p>Page</p><!--blockstudio/not-a-block-->' )
		);
	}

	public function test_files_with_the_same_name_keep_separate_tags(): void {
		$first  = $this->asset( 'one/script.js', 'console.log(1);' );
		$second = $this->asset( 'two/script.js', 'console.log(2);' );
		$marker = Assets::register_owner( 'page-home', array( $first, $second ) );

		$html = ( new Assets() )->parse_output( '<html><head></head><body>' . $marker . '</body></html>' );

		$this->assertSame( 2, substr_count( $html, "id='blockstudio-owner-page-home-" ) );
	}

	/**
	 * Write an asset file.
	 *
	 * @param string $name     Relative file name.
	 * @param string $contents Contents.
	 *
	 * @return string Path.
	 */
	private function asset( string $name, string $contents ): string {
		$path = $this->directory . '/' . $name;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $contents );

		return $path;
	}

	/**
	 * Remove a temporary directory.
	 *
	 * @param string $path Path.
	 *
	 * @return void
	 */
	private function remove_directory( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $path );
	}
}
