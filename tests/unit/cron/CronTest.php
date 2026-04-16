<?php

use Blockstudio\Cron;
use PHPUnit\Framework\TestCase;

class CronTest extends TestCase {

	protected function setUp(): void {
		// Reset static state via reflection.
		$ref = new ReflectionClass( Cron::class );

		$jobs = $ref->getProperty( 'jobs' );
		$jobs->setAccessible( true );
		$jobs->setValue( null, array() );

		$loaded = $ref->getProperty( 'loaded' );
		$loaded->setAccessible( true );
		$loaded->setValue( null, false );
	}

	// hook_name()

	public function test_hook_name_format(): void {
		$ref    = new ReflectionClass( Cron::class );
		$method = $ref->getMethod( 'hook_name' );
		$method->setAccessible( true );

		$hook = $method->invoke( null, 'blockstudio/type-cron', 'cleanup' );
		$this->assertSame( 'blockstudio_cron_blockstudio_type_cron_cleanup', $hook );
	}

	public function test_hook_name_replaces_slashes_and_hyphens(): void {
		$ref    = new ReflectionClass( Cron::class );
		$method = $ref->getMethod( 'hook_name' );
		$method->setAccessible( true );

		$hook = $method->invoke( null, 'my-plugin/my-block', 'my-job' );
		$this->assertSame( 'blockstudio_cron_my_plugin_my_block_my-job', $hook );
	}

	// Test cron block exists in test theme

	public function test_cron_test_block_exists(): void {
		$cron_path = dirname( __DIR__, 2 ) . '/theme/blockstudio/types/cron/cron.php';
		$this->assertFileExists( $cron_path );
	}

	public function test_cron_test_block_returns_array(): void {
		$cron_path   = dirname( __DIR__, 2 ) . '/theme/blockstudio/types/cron/cron.php';
		$definitions = include $cron_path;
		$this->assertIsArray( $definitions );
	}

	public function test_php_native_cron_test_block_returns_object(): void {
		$cron_path   = dirname( __DIR__, 2 ) . '/theme/blockstudio/types/cron-php/cron.php';
		$definitions = include $cron_path;
		$this->assertIsObject( $definitions );
	}

	public function test_cron_test_block_has_cleanup_job(): void {
		$definitions = include dirname( __DIR__, 2 ) . '/theme/blockstudio/types/cron/cron.php';
		$this->assertArrayHasKey( 'cleanup', $definitions );
		$this->assertIsArray( $definitions['cleanup'] );
		$this->assertArrayHasKey( 'callback', $definitions['cleanup'] );
		$this->assertIsCallable( $definitions['cleanup']['callback'] );
		$this->assertSame( 'daily', $definitions['cleanup']['schedule'] );
	}

	public function test_cron_test_block_has_ping_job(): void {
		$definitions = include dirname( __DIR__, 2 ) . '/theme/blockstudio/types/cron/cron.php';
		$this->assertArrayHasKey( 'ping', $definitions );
		$this->assertIsCallable( $definitions['ping'] );
	}

	// load_block_cron()

	public function test_load_block_cron_with_array_definition(): void {
		$ref    = new ReflectionClass( Cron::class );
		$method = $ref->getMethod( 'load_block_cron' );
		$method->setAccessible( true );

		$cron_path = dirname( __DIR__, 2 ) . '/theme/blockstudio/types/cron/cron.php';

		$method->invoke( null, 'test/cron-block', array(
			'filesPaths' => array( $cron_path ),
		) );

		$jobs = $ref->getProperty( 'jobs' );
		$jobs->setAccessible( true );
		$all_jobs = $jobs->getValue( null );

		$this->assertArrayHasKey( 'test/cron-block', $all_jobs );
		$this->assertArrayHasKey( 'cleanup', $all_jobs['test/cron-block'] );
		$this->assertArrayHasKey( 'ping', $all_jobs['test/cron-block'] );
		$this->assertSame( 'daily', $all_jobs['test/cron-block']['cleanup']['schedule'] );
		$this->assertSame( 'daily', $all_jobs['test/cron-block']['ping']['schedule'] );
	}

	public function test_load_block_cron_with_attribute_definition(): void {
		$ref    = new ReflectionClass( Cron::class );
		$method = $ref->getMethod( 'load_block_cron' );
		$method->setAccessible( true );

		$cron_path = dirname( __DIR__, 2 ) . '/theme/blockstudio/types/cron-php/cron.php';

		$method->invoke( null, 'test/cron-block-php', array(
			'filesPaths' => array( $cron_path ),
		) );

		$jobs = $ref->getProperty( 'jobs' );
		$jobs->setAccessible( true );
		$all_jobs = $jobs->getValue( null );

		$this->assertArrayHasKey( 'test/cron-block-php', $all_jobs );
		$this->assertArrayHasKey( 'heartbeat', $all_jobs['test/cron-block-php'] );
		$this->assertArrayHasKey( 'cleanup_old_entries', $all_jobs['test/cron-block-php'] );
		$this->assertSame( 'hourly', $all_jobs['test/cron-block-php']['heartbeat']['schedule'] );
		$this->assertSame( 'daily', $all_jobs['test/cron-block-php']['cleanup_old_entries']['schedule'] );
	}

	public function test_load_block_cron_skips_missing_file(): void {
		$ref    = new ReflectionClass( Cron::class );
		$method = $ref->getMethod( 'load_block_cron' );
		$method->setAccessible( true );

		$method->invoke( null, 'test/missing', array(
			'filesPaths' => array( '/nonexistent/cron.php' ),
		) );

		$jobs     = $ref->getProperty( 'jobs' );
		$jobs->setAccessible( true );
		$all_jobs = $jobs->getValue( null );

		$this->assertEmpty( $all_jobs );
	}

	public function test_load_block_cron_skips_empty_files_paths(): void {
		$ref    = new ReflectionClass( Cron::class );
		$method = $ref->getMethod( 'load_block_cron' );
		$method->setAccessible( true );

		$method->invoke( null, 'test/empty', array(
			'filesPaths' => array(),
		) );

		$jobs     = $ref->getProperty( 'jobs' );
		$jobs->setAccessible( true );
		$all_jobs = $jobs->getValue( null );

		$this->assertEmpty( $all_jobs );
	}

	public function test_load_block_cron_skips_no_cron_file_in_paths(): void {
		$ref    = new ReflectionClass( Cron::class );
		$method = $ref->getMethod( 'load_block_cron' );
		$method->setAccessible( true );

		$method->invoke( null, 'test/no-cron', array(
			'filesPaths' => array( '/some/path/index.php', '/some/path/style.css' ),
		) );

		$jobs     = $ref->getProperty( 'jobs' );
		$jobs->setAccessible( true );
		$all_jobs = $jobs->getValue( null );

		$this->assertEmpty( $all_jobs );
	}

	// unschedule_all()

	public function test_unschedule_all_does_not_error_when_no_jobs(): void {
		Cron::unschedule_all();

		// Reaching this point without error is the assertion.
		$this->assertTrue( true );
	}
}
