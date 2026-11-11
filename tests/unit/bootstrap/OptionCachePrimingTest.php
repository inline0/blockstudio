<?php

use Blockstudio\Build_Cache;
use Blockstudio\Migrate;
use Blockstudio\Page_Registry;
use Blockstudio\Pages;
use Blockstudio\Plugin;
use Blockstudio\Runtime_Cache;
use Blockstudio\Settings;
use Blockstudio\Static_Prerender_Runtime;
use PHPUnit\Framework\TestCase;

class OptionCachePrimingTest extends TestCase {

	private const REQUEST_OPTIONS = array(
		'blockstudio_settings',
		'blockstudio_static_prerender_feature_state',
		'blockstudio_build_lock_cleanup_version',
		'blockstudio_pages_collection_manifests',
		'blockstudio_collection_post_types_signature',
		'blockstudio_populate_cache_version',
		'blockstudio_pages_template_for',
	);

	private array $queries = array();

	private array $stored = array();

	protected function setUp(): void {
		foreach ( self::REQUEST_OPTIONS as $option ) {
			$this->stored[ $option ] = get_option( $option, null );
			delete_option( $option );
		}
	}

	protected function tearDown(): void {
		remove_filter( 'query', array( $this, 'record_query' ) );

		foreach ( $this->stored as $option => $value ) {
			delete_option( $option );

			if ( null !== $value ) {
				add_option( $option, $value );
			}
		}
	}

	public function record_query( string $query ): string {
		if ( str_starts_with( ltrim( $query ), 'SELECT' ) ) {
			foreach ( self::REQUEST_OPTIONS as $option ) {
				if ( str_contains( $query, "'{$option}'" ) ) {
					$this->queries[] = $query;
					break;
				}
			}
		}

		return $query;
	}

	public function test_missing_request_options_are_read_with_one_query(): void {
		$this->start_request();
		add_filter( 'query', array( $this, 'record_query' ) );

		$this->read_request_options();

		$this->assertCount( 1, $this->queries, implode( "\n", $this->queries ) );
	}

	public function test_migrated_request_options_are_read_without_a_query(): void {
		$version = get_option( 'blockstudio_version' );
		delete_option( 'blockstudio_version' );

		try {
			( new Migrate() )->check_and_update();

			$this->assert_request_without_option_query();

			// The request above stored the cleanup version and the signature; these are the remaining writers.
			Build_Cache::mark_populate_cache_dirty( time() );
			$this->write_page_options();

			$this->assert_request_without_option_query();
		} finally {
			update_option( 'blockstudio_version', $version );
		}
	}

	public function test_populate_state_is_not_autoloaded_with_a_persistent_object_cache(): void {
		$using = wp_using_ext_object_cache( true );

		try {
			Build_Cache::mark_populate_cache_dirty( time() );
		} finally {
			wp_using_ext_object_cache( (bool) $using );
		}

		wp_cache_delete( 'alloptions', 'options' );

		$this->assertIsArray( get_option( 'blockstudio_populate_cache_version' ) );
		$this->assertArrayNotHasKey( 'blockstudio_populate_cache_version', wp_load_alloptions() );
	}

	public function test_page_option_writers_reconsider_autoload_when_values_grow_and_shrink(): void {
		foreach ( array( 'blockstudio_pages_collection_manifests', 'blockstudio_pages_template_for' ) as $option ) {
			foreach ( array( 'small', str_repeat( 'a', 200000 ), 'small again' ) as $content ) {
				$value = array( 'content' => $content );
				$this->invoke( Pages::class, 'store_collection_option', array( $option, $value ) );
				wp_cache_delete( 'alloptions', 'options' );
				$alloptions = wp_load_alloptions();

				$this->assertSame( strlen( $content ) < 150000, array_key_exists( $option, $alloptions ) );
				$this->assertSame( $value, get_option( $option ) );
			}
		}
	}

	private function assert_request_without_option_query(): void {
		$this->start_request();
		$this->assertSame( array(), array_diff( self::REQUEST_OPTIONS, array_keys( wp_load_alloptions() ) ), 'Every request option is autoloaded.' );

		$this->queries = array();
		add_filter( 'query', array( $this, 'record_query' ) );

		$this->read_request_options();

		remove_filter( 'query', array( $this, 'record_query' ) );
		$this->assertSame( array(), $this->queries );
	}

	// The page writers also fill request caches of Pages, which later tests must find unchanged.
	private function write_page_options(): void {
		$caches = array(
			new ReflectionProperty( Pages::class, 'collection_manifests_cache' ),
			new ReflectionProperty( Pages::class, 'collection_manifests_by_slug_cache' ),
		);
		$values = array_map( static fn( ReflectionProperty $cache ) => $cache->getValue(), $caches );

		$this->invoke( Pages::class, 'set_collection_manifests_cache', array( array() ) );
		$this->invoke( Pages::class, 'store_template_for_configuration', array( Page_Registry::instance() ) );

		foreach ( $caches as $index => $cache ) {
			$cache->setValue( null, $values[ $index ] );
		}
	}

	private function read_request_options(): void {
		$this->invoke( Plugin::get_instance(), 'prime_option_caches' );

		new Settings();
		$this->invoke( Static_Prerender_Runtime::class, 'reconcile_feature_state' );
		Runtime_Cache::init();
		$this->invoke( Pages::class, 'persisted_collection_manifests', array( array() ) );
		Pages::maybe_register_collection_post_types();
		Build_Cache::get_populate_cache_version();
		Pages::filter_template_for_post_type_args( array(), 'post' );
	}

	// A request without an object cache starts with the autoloaded options only.
	private function start_request(): void {
		wp_cache_delete( 'alloptions', 'options' );

		foreach ( self::REQUEST_OPTIONS as $option ) {
			wp_cache_delete( $option, 'options' );
		}

		$notoptions = wp_cache_get( 'notoptions', 'options' );

		if ( is_array( $notoptions ) ) {
			wp_cache_set( 'notoptions', array_diff_key( $notoptions, array_flip( self::REQUEST_OPTIONS ) ), 'options' );
		}
	}

	private function invoke( object|string $target, string $method, array $args = array() ): mixed {
		$reflection = new ReflectionMethod( $target, $method );

		return $reflection->invokeArgs( is_object( $target ) ? $target : null, $args );
	}
}
