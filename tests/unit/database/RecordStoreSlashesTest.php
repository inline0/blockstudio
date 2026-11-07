<?php

use Blockstudio\Db\Meta_Record_Store;
use Blockstudio\Db\Post_Type_Record_Store;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecordStoreSlashesTest extends TestCase {

	private const KEY = 'unit_slashes:default';
	private array $post_ids = array();

	protected function setUp(): void {
		register_post_type( Post_Type_Record_Store::post_type_for_key( self::KEY ), array( 'public' => false ) );
	}

	protected function tearDown(): void {
		foreach ( $this->post_ids as $id ) {
			wp_delete_post( $id, true );
		}
		unregister_post_type( Post_Type_Record_Store::post_type_for_key( self::KEY ) );
	}

	#[DataProvider( 'backends' )]
	public function test_record_writes_preserve_existing_and_updated_backslashes( string $backend ): void {
		$schema = array( 'fields' => array( 'value' => array( 'type' => 'string' ), 'payload' => array( 'type' => 'object' ) ) );
		if ( 'meta' === $backend ) {
			$schema['postId'] = wp_insert_post( array( 'post_title' => 'Meta record slashes', 'post_status' => 'draft' ) );
			$this->post_ids[] = $schema['postId'];
			$store = new Meta_Record_Store( self::KEY, $schema );
		} else {
			$store = new Post_Type_Record_Store( self::KEY, $schema );
		}

		$data = array( 'value' => 'q"a\\b"c\\\\d', 'payload' => array( 'path' => 'a\\b', 'rows' => array( array( 'pattern' => '\\d+\\s' ) ) ) );
		$first = $store->create( $data );
		$second = $store->create( array( 'value' => 'Other' ) );
		if ( 'postType' === $backend ) {
			$this->post_ids = array_merge( $this->post_ids, array( $first['id'], $second['id'] ) );
		}
		$this->assertSame( $data, array_intersect_key( $store->get( $first['id'] ), $data ) );
		$store->update( $second['id'], array( 'value' => 'Unrelated update' ) );
		$this->assertSame( $data, array_intersect_key( $store->get( $first['id'] ), $data ) );
		$store->put( $first['id'], array( 'value' => 'new\\path\\' ) );
		$this->assertSame( 'new\\path\\', $store->get( $first['id'] )['value'] );
		$this->assertSame( $data['payload'], $store->get( $first['id'] )['payload'] );
		$store->delete( $second['id'] );
		$this->assertSame( 'new\\path\\', $store->get( $first['id'] )['value'] );
		$this->assertSame( $data['payload'], $store->get( $first['id'] )['payload'] );
	}

	public static function backends(): array {
		return array( 'meta' => array( 'meta' ), 'post type' => array( 'postType' ) );
	}
}
