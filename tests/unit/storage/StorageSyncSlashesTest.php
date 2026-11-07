<?php

use Blockstudio\Storage_Handlers\Option_Storage;
use Blockstudio\Storage_Handlers\Post_Meta_Storage;
use Blockstudio\Storage_Registry;
use Blockstudio\Storage_Sync;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StorageSyncSlashesTest extends TestCase {

	private int $post_id;
	private string $option_key;

	protected function setUp(): void {
		$this->post_id = wp_insert_post( array( 'post_title' => 'Storage slashes', 'post_status' => 'draft' ) );
		$this->option_key = 'bs_storage_slashes_' . $this->post_id;
		Storage_Registry::instance()->register_handler( new Post_Meta_Storage() );
		Storage_Registry::instance()->register_handler( new Option_Storage() );
	}

	protected function tearDown(): void {
		wp_delete_post( $this->post_id, true );
		delete_option( $this->option_key );
	}

	#[DataProvider( 'stored_values' )]
	public function test_meta_and_options_preserve_literal_values_on_resave( mixed $value ): void {
		$field = array(
			'id' => 'summary',
			'type' => 'text',
			'storage' => array( 'type' => array( 'block', 'postMeta', 'option' ), 'postMetaKey' => 'bs_summary', 'optionKey' => $this->option_key ),
		);
		$attrs = array( 'summary' => $value );
		$original = $attrs;
		$sync = new Storage_Sync();
		$method = new ReflectionMethod( $sync, 'sync_fields' );
		for ( $i = 0; $i < 2; ++$i ) {
			$method->invoke( $sync, $this->post_id, 'test/slashes', array( $field ), $attrs );
			$this->assertSame( $value, get_post_meta( $this->post_id, 'bs_summary', true ) );
			$this->assertSame( $value, get_option( $this->option_key ) );
			$this->assertSame( $original, $attrs );
		}
	}

	public static function stored_values(): array {
		return array(
			'one backslash' => array( 'a\\b' ),
			'two backslashes' => array( 'a\\\\b' ),
			'quotes and backslashes' => array( 'q"x\\y"z' ),
			'plain text' => array( 'abc' ),
			'trailing backslash' => array( 'last\\' ),
			'json' => array( '{"path":"a\\\\b","pattern":"\\\\d+"}' ),
			'nested arrays' => array( array( array( 'path' => 'a\\b', 'settings' => array( 'pattern' => '\\d+\\s' ) ), array( 'path' => 'c\\\\d' ) ) ),
		);
	}

	public function test_repeater_child_storage_preserves_each_row(): void {
		$fields = array( array( 'id' => 'rows', 'type' => 'repeater', 'attributes' => array(
			array( 'id' => 'path', 'type' => 'text', 'storage' => array( 'type' => 'postMeta', 'postMetaKey' => 'bs_paths' ) ),
		) ) );
		$attrs = array( 'rows' => array( array( 'path' => 'a\\b' ), array( 'path' => 'c\\\\d' ) ) );
		$method = new ReflectionMethod( Storage_Sync::class, 'sync_fields' );
		$method->invoke( new Storage_Sync(), $this->post_id, 'test/slashes', $fields, $attrs );
		$this->assertSame( array( 'a\\b', 'c\\\\d' ), get_post_meta( $this->post_id, 'bs_paths', true ) );
	}

	public function test_cleared_value_still_deletes_meta(): void {
		update_post_meta( $this->post_id, 'bs_summary', 'before' );
		$field = array( 'id' => 'summary', 'type' => 'text', 'storage' => array( 'type' => 'postMeta', 'postMetaKey' => 'bs_summary' ) );
		( new ReflectionMethod( Storage_Sync::class, 'sync_fields' ) )->invoke( new Storage_Sync(), $this->post_id, 'test/slashes', array( $field ), array( 'summary' => null ) );
		$this->assertFalse( metadata_exists( 'post', $this->post_id, 'bs_summary' ) );
	}
}
