<?php

use Blockstudio\Build;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PostMetaSaveTest extends TestCase {

	private array $posts = array();
	private array $original_fields;
	private const META  = 'blockstudio_post_meta_save_regression';
	private const BLOCK = 'blockstudio/type-storage-meta';

	protected function setUp(): void {
		$block                 = Build::blocks()[ self::BLOCK ];
		$this->original_fields = $block->blockstudio['attributes'];
		$block->blockstudio['attributes'] = array(
			array( 'id' => 'value', 'type' => 'text', 'storage' => array( 'type' => 'postMeta', 'postMetaKey' => self::META ) ),
			array( 'id' => 'rows', 'type' => 'repeater', 'attributes' => array(
				array( 'id' => 'label', 'type' => 'text', 'storage' => array( 'type' => 'postMeta', 'postMetaKey' => self::META . '_rows' ) ),
			) ),
		);
	}

	protected function tearDown(): void {
		Build::blocks()[ self::BLOCK ]->blockstudio['attributes'] = $this->original_fields;
		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	private function content( array $values ): string {
		return serialize_block( array(
			'blockName' => self::BLOCK,
			'attrs' => array( 'blockstudio' => array( 'attributes' => $values ) ),
			'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array(),
		) );
	}

	// Saves are slashed the way the REST posts controller slashes them, so post_content keeps its backslashes.
	private function create_post( array $values ): int {
		$id = wp_insert_post( wp_slash( array( 'post_title' => 'Post meta save regression', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $this->content( $values ) ) ), true );
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		return $id;
	}

	private function update_post( int $id, array $values ): void {
		wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $this->content( $values ) ) ) );
	}

	public static function backslash_values(): array {
		return array(
			'single backslash' => array( 'a\\b' ),
			'double backslash' => array( 'a\\\\b' ),
			'quotes and backslash' => array( 'q"x\\y"z' ),
			'windows path' => array( 'C:\\Users\\me' ),
		);
	}

	#[DataProvider( 'backslash_values' )]
	public function test_post_meta_keeps_backslashes_on_every_save( string $value ): void {
		$id = $this->create_post( array( 'value' => $value ) );
		$this->assertSame( $value, parse_blocks( get_post( $id )->post_content )[0]['attrs']['blockstudio']['attributes']['value'] );
		$this->assertSame( $value, get_post_meta( $id, self::META, true ) );

		$this->update_post( $id, array( 'value' => $value ) );
		$this->assertSame( $value, get_post_meta( $id, self::META, true ) );
	}

	public function test_post_meta_keeps_backslashes_in_repeater_rows(): void {
		$rows = array( array( 'label' => 'a\\b' ), array( 'label' => 'c\\\\d' ) );
		$id   = $this->create_post( array( 'rows' => $rows ) );
		$this->assertSame( array( 'a\\b', 'c\\\\d' ), get_post_meta( $id, self::META . '_rows', true ) );
	}
}
