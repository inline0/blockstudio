<?php

use Blockstudio\Icons;
use Blockstudio\Migrate;
use Blockstudio\Rest;
use PHPUnit\Framework\TestCase;

class IconFetchTest extends TestCase {

	private const HOOK = 'blockstudio/icons/fetch';

	private const FAILED = 'blockstudio_icons_fetch_failed';

	private const SET = '{"probe.svg":"<svg id=\"probe\"></svg>"}';

	private string $dir = '';

	private string $bundled = '';

	private string $version = '';

	private string $body = self::SET;

	private int $code = 200;

	private array $requests = array();

	protected function setUp(): void {
		$base          = sys_get_temp_dir() . '/bs-icon-fetch-' . uniqid();
		$this->dir     = $base . '/stored';
		$this->bundled = $base . '/bundled';
		$this->version = (string) get_option( 'blockstudio_version' );
		mkdir( $this->bundled, 0777, true );
		file_put_contents(
			$this->bundled . '.json',
			wp_json_encode(
				array(
					'probe-set'  => hash( 'sha256', self::SET ),
					'second-set' => hash( 'sha256', self::SET ),
				)
			)
		);
		Icons::use_bundled_dir( $this->bundled );
		add_filter( 'blockstudio/icons/dir', array( $this, 'dir' ) );
		add_filter( 'pre_http_request', array( $this, 'respond' ), 10, 3 );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		Icons::use_bundled_dir( null );
		remove_filter( 'blockstudio/icons/dir', array( $this, 'dir' ) );
		remove_filter( 'blockstudio/icons/dir', '__return_empty_string' );
		remove_filter( 'blockstudio/icons/url', array( $this, 'mirror' ) );
		remove_filter( 'pre_http_request', array( $this, 'respond' ) );
		wp_clear_scheduled_hook( self::HOOK, array( array() ) );
		wp_clear_scheduled_hook( self::HOOK, array( array( 'probe-set' ) ) );
		delete_option( self::FAILED );
		delete_transient( 'blockstudio_1_icon_' . md5( 'probe-setprobe' ) );
		update_option( 'blockstudio_version', $this->version );
		foreach ( array_merge( glob( $this->dir . '/*' ) ?: array(), array( $this->bundled . '.json' ) ) as $file ) {
			unlink( $file );
		}
		foreach ( array( $this->dir, $this->bundled, dirname( $this->dir ) ) as $dir ) {
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		}
	}

	public function dir(): string {
		return $this->dir;
	}

	public function mirror( string $url, string $name ): string {
		return 'https://mirror.example/' . $name . '.json';
	}

	public function respond( $pre, array $args, string $url ) {
		$this->requests[] = $url;
		if ( 200 === $this->code ) {
			file_put_contents( $args['filename'], $this->body );
		}

		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => $this->code,
				'message' => '',
			),
			'cookies'  => array(),
		);
	}

	private function probe(): string {
		return bs_icon(
			array(
				'set'    => 'probe',
				'subSet' => 'set',
				'icon'   => 'probe',
			)
		);
	}

	public function test_checksums_match_every_bundled_set(): void {
		Icons::use_bundled_dir( null );
		$sets = array();
		foreach ( glob( Icons::bundled_dir() . '/*.json' ) as $file ) {
			$sets[ basename( $file, '.json' ) ] = hash_file( 'sha256', $file );
		}

		$this->assertNotEmpty( $sets );
		$this->assertSame( $sets, Icons::manifest() );
	}

	public function test_picker_request_downloads_and_stores_a_missing_set(): void {
		$icons = ( new Rest() )->icons( array( 'set' => 'probe', 'subSet' => 'set' ) );

		$this->assertSame( array( 'probe.svg' => '<svg id="probe"></svg>' ), $icons );
		$this->assertSame(
			array( 'https://github.com/inline0/blockstudio/releases/download/v' . BLOCKSTUDIO_VERSION . '/blockstudio-icons-probe-set.json' ),
			$this->requests
		);
		$this->assertStringEqualsFile( $this->dir . '/probe-set.json', self::SET );

		( new Rest() )->icons( array( 'set' => 'probe', 'subSet' => 'set' ) );
		$this->assertCount( 1, $this->requests );
	}

	public function test_download_url_can_be_filtered(): void {
		add_filter( 'blockstudio/icons/url', array( $this, 'mirror' ), 10, 2 );

		$this->assertTrue( Icons::fetch( 'probe-set' ) );
		$this->assertSame( array( 'https://mirror.example/probe-set.json' ), $this->requests );
	}

	public function test_download_that_does_not_match_its_checksum_is_discarded(): void {
		$this->body = '{"probe.svg":"<svg onload=\"alert(1)\"></svg>"}';

		$result = ( new Rest() )->icons( array( 'set' => 'probe', 'subSet' => 'set' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( array(), glob( $this->dir . '/*' ) );
	}

	public function test_failed_response_stores_nothing(): void {
		$this->code = 404;

		$this->assertFalse( Icons::fetch( 'probe-set' ) );
		$this->assertSame( array(), glob( $this->dir . '/*' ) );
	}

	public function test_set_without_a_checksum_is_never_requested(): void {
		$this->assertFalse( Icons::fetch( 'unknown-set' ) );
		$this->assertInstanceOf( WP_Error::class, ( new Rest() )->icons( array( 'set' => 'unknown-set' ) ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_empty_directory_requests_nothing(): void {
		remove_filter( 'blockstudio/icons/dir', array( $this, 'dir' ) );
		add_filter( 'blockstudio/icons/dir', '__return_empty_string' );
		update_option( 'blockstudio_version', '7.7.0' );

		$this->assertFalse( Icons::fetch( 'probe-set' ) );
		$this->assertSame( '', $this->probe() );
		( new Migrate() )->check_and_update();

		$this->assertSame( array(), $this->requests );
		$this->assertFalse( wp_next_scheduled( self::HOOK, array( array( 'probe-set' ) ) ) );
		$this->assertFalse( wp_next_scheduled( self::HOOK, array( array() ) ) );
	}

	public function test_render_of_a_missing_set_schedules_the_download_without_a_request(): void {
		$this->assertSame( '', $this->probe() );
		$this->assertSame( array(), $this->requests );
		$this->assertIsInt( wp_next_scheduled( self::HOOK, array( array( 'probe-set' ) ) ) );

		Icons::fetch_sets( array( 'probe-set' ) );

		$this->assertSame( '<svg id="probe"></svg>', $this->probe() );
		$this->assertFileDoesNotExist( $this->dir . '/second-set.json' );
	}

	public function test_failed_background_download_is_recorded_and_retried_hourly(): void {
		$this->code = 500;

		Icons::fetch_sets();

		$this->assertCount( 1, $this->requests );
		$this->assertGreaterThan( time() - 60, (int) get_option( self::FAILED ) );
		$this->assertGreaterThan( time() + HOUR_IN_SECONDS - 60, wp_next_scheduled( self::HOOK, array( array() ) ) );

		$this->assertSame( '', $this->probe() );
		$this->assertFalse( wp_next_scheduled( self::HOOK, array( array( 'probe-set' ) ) ) );

		ob_start();
		wp_set_current_user( 1 );
		Icons::render_fetch_notice();
		$this->assertStringContainsString( 'notice-warning', ob_get_clean() );

		$this->code = 200;
		Icons::fetch_sets();

		$this->assertFalse( get_option( self::FAILED ) );
		$this->assertFileExists( $this->dir . '/probe-set.json' );
		$this->assertFileExists( $this->dir . '/second-set.json' );
		ob_start();
		Icons::render_fetch_notice();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_update_schedules_the_download_of_missing_sets(): void {
		update_option( 'blockstudio_version', '7.7.0' );

		( new Migrate() )->check_and_update();

		$this->assertIsInt( wp_next_scheduled( self::HOOK, array( array() ) ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_update_with_every_set_stored_schedules_nothing(): void {
		mkdir( $this->dir, 0777, true );
		file_put_contents( $this->dir . '/probe-set.json', self::SET );
		file_put_contents( $this->dir . '/second-set.json', self::SET );
		update_option( 'blockstudio_version', '7.7.0' );

		( new Migrate() )->check_and_update();

		$this->assertFalse( wp_next_scheduled( self::HOOK, array( array() ) ) );
	}

	public function test_first_activation_downloads_nothing(): void {
		delete_option( 'blockstudio_version' );

		( new Migrate() )->check_and_update();

		$this->assertFalse( wp_next_scheduled( self::HOOK, array( array() ) ) );
		$this->assertSame( array(), $this->requests );
	}
}
