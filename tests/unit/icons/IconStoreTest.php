<?php

use Blockstudio\Icons;
use Blockstudio\Migrate;
use Blockstudio\Rest;
use PHPUnit\Framework\TestCase;

class IconStoreTest extends TestCase {

	private const ICON_KEY = 'blockstudio_1_icon_';

	private string $dir = '';

	private string $version = '';

	protected function setUp(): void {
		$this->dir     = sys_get_temp_dir() . '/bs-icon-store-' . uniqid();
		$this->version = (string) get_option( 'blockstudio_version' );
		add_filter( 'blockstudio/icons/dir', array( $this, 'dir' ) );
		$this->clear_transients();
	}

	protected function tearDown(): void {
		remove_filter( 'blockstudio/icons/dir', array( $this, 'dir' ) );
		remove_filter( 'blockstudio/icons/dir', '__return_empty_string' );
		update_option( 'blockstudio_version', $this->version );
		$this->clear_transients();
		foreach ( glob( $this->dir . '/*' ) ?: array() as $file ) {
			unlink( $file );
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir );
		}
	}

	public function dir(): string {
		return $this->dir;
	}

	private function clear_transients(): void {
		delete_transient( self::ICON_KEY . md5( 'octiconsalert-16' ) );
		delete_transient( self::ICON_KEY . md5( 'only-storedprobe' ) );
	}

	private function store( string $set, array $icons ): void {
		wp_mkdir_p( $this->dir );
		file_put_contents( $this->dir . '/' . $set . '.json', wp_json_encode( $icons ) );
	}

	public function test_bundled_set_resolves_while_nothing_is_stored(): void {
		$this->assertSame( Icons::bundled_dir() . '/octicons.json', Icons::set_path( 'octicons' ) );
		$this->assertDirectoryDoesNotExist( $this->dir );
	}

	public function test_stores_every_bundled_set_unchanged(): void {
		Icons::store_bundled_sets();

		$bundled = glob( Icons::bundled_dir() . '/*.json' );
		$this->assertNotEmpty( $bundled );
		foreach ( $bundled as $file ) {
			$this->assertFileEquals( $file, $this->dir . '/' . basename( $file ) );
		}
		$this->assertSame( array(), glob( $this->dir . '/*.tmp' ) );
		$this->assertSame( $this->dir . '/octicons.json', Icons::set_path( 'octicons' ) );
	}

	public function test_repeated_runs_write_only_missing_or_changed_sets(): void {
		Icons::store_bundled_sets();
		$kept    = $this->dir . '/octicons.json';
		$changed = $this->dir . '/heroicons-outline.json';
		$missing = $this->dir . '/feather-icons.json';
		touch( $kept, 1000 );
		file_put_contents( $changed, '{}' );
		unlink( $missing );
		clearstatcache();

		Icons::store_bundled_sets();
		clearstatcache();

		$this->assertSame( 1000, filemtime( $kept ) );
		$this->assertFileEquals( Icons::bundled_dir() . '/heroicons-outline.json', $changed );
		$this->assertFileEquals( Icons::bundled_dir() . '/feather-icons.json', $missing );
	}

	public function test_stored_set_is_rendered_instead_of_the_bundled_one(): void {
		$this->store( 'octicons', array( 'alert-16.svg' => '<svg id="stored"></svg>' ) );

		$this->assertSame( '<svg id="stored"></svg>', bs_icon( array( 'set' => 'octicons', 'icon' => 'alert-16' ) ) );
		$this->assertSame( array( 'alert-16.svg' => '<svg id="stored"></svg>' ), ( new Rest() )->icons( array( 'set' => 'octicons' ) ) );
	}

	public function test_set_that_is_only_stored_resolves(): void {
		$this->store( 'only-stored', array( 'probe.svg' => '<svg id="probe"></svg>' ) );

		$this->assertSame( '<svg id="probe"></svg>', bs_icon( array( 'set' => 'only-stored', 'icon' => 'probe' ) ) );
		$this->assertSame( array( 'probe.svg' => '<svg id="probe"></svg>' ), ( new Rest() )->icons( array( 'set' => 'only-stored' ) ) );
	}

	public function test_stored_directory_cannot_be_escaped(): void {
		$this->store( 'octicons', array() );

		$this->assertSame( '', Icons::set_path( '../' . basename( $this->dir ) . '/octicons' ) );
	}

	public function test_empty_directory_keeps_sets_in_the_plugin(): void {
		remove_filter( 'blockstudio/icons/dir', array( $this, 'dir' ) );
		add_filter( 'blockstudio/icons/dir', '__return_empty_string' );

		Icons::store_bundled_sets();

		$this->assertSame( '', Icons::dir() );
		$this->assertDirectoryDoesNotExist( $this->dir );
		$this->assertSame( Icons::bundled_dir() . '/octicons.json', Icons::set_path( 'octicons' ) );
	}

	public function test_update_stores_the_bundled_sets(): void {
		update_option( 'blockstudio_version', '7.7.0' );

		( new Migrate() )->check_and_update();

		$this->assertFileEquals( Icons::bundled_dir() . '/octicons.json', $this->dir . '/octicons.json' );
	}

	public function test_first_activation_stores_the_bundled_sets(): void {
		delete_option( 'blockstudio_version' );

		( new Migrate() )->check_and_update();

		$this->assertFileEquals( Icons::bundled_dir() . '/octicons.json', $this->dir . '/octicons.json' );
	}
}
