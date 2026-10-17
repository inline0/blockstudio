<?php

use Blockstudio\Block_Registry;
use Blockstudio\Build;
use Blockstudio\Files;
use Blockstudio\Wpml_Media;
use PHPUnit\Framework\TestCase;

/**
 * Attachments of Blockstudio fields reported to WPML Media Translation through
 * the wpml_ids_of_media_used_in_post filter.
 */
class WpmlMediaTest extends TestCase {

	private const FILTER = 'wpml_ids_of_media_used_in_post';

	private string $name = 'wpml-test/media';
	private string $directory;
	private array $extensions;
	private array $posts = array();

	protected function setUp(): void {
		$this->extensions = Build::extensions();
		$this->directory  = sys_get_temp_dir() . '/bs-wpml-media-' . uniqid();
		wp_mkdir_p( $this->directory . '/block' );
		wp_mkdir_p( $this->directory . '/extensions' );

		$this->write(
			'block/block.json',
			array(
				'name'        => $this->name,
				'title'       => 'Media',
				'blockstudio' => array(
					'attributes' => array(
						array( 'id' => 'heading', 'type' => 'text' ),
						array( 'id' => 'image', 'type' => 'files' ),
						array( 'id' => 'gallery', 'type' => 'files', 'multiple' => true ),
						array( 'id' => 'variant', 'type' => 'files', 'hidden' => true ),
						array( 'id' => 'count', 'type' => 'number' ),
						array(
							'id'         => 'cta',
							'type'       => 'group',
							'attributes' => array( array( 'id' => 'icon', 'type' => 'files' ) ),
						),
						array(
							'type' => 'tabs',
							'tabs' => array(
								array( 'attributes' => array( array( 'id' => 'background', 'type' => 'files' ) ) ),
							),
						),
						array(
							'id'         => 'cards',
							'type'       => 'repeater',
							'attributes' => array(
								array( 'id' => 'title', 'type' => 'text' ),
								array( 'id' => 'photo', 'type' => 'files' ),
							),
						),
					),
				),
			)
		);
		file_put_contents( $this->directory . '/block/index.php', '<div></div>' );
		$this->write(
			'extensions/paragraph.json',
			array(
				'name'        => 'core/paragraph',
				'blockstudio' => array(
					'extend'     => true,
					'attributes' => array( array( 'id' => 'texture', 'type' => 'files' ) ),
				),
			)
		);

		Build::init( array( 'dir' => $this->directory ) );
	}

	protected function tearDown(): void {
		remove_filter( self::FILTER, array( Wpml_Media::class, 'add_media_ids' ) );
		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		Block_Registry::instance()->remove_block( $this->name );
		// The registry has no API to remove an extension again.
		( new ReflectionProperty( Block_Registry::class, 'extensions' ) )->setValue( Block_Registry::instance(), $this->extensions );
		Files::delete_all_files( $this->directory );
		parent::tearDown();
	}

	private function write( string $file, array $json ): void {
		file_put_contents( $this->directory . '/' . $file, wp_json_encode( $json ) );
	}

	private function attachment(): int {
		$id = wp_insert_attachment(
			array(
				'post_title'     => 'Image ' . count( $this->posts ),
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);
		$this->posts[] = $id;

		return $id;
	}

	private function post( string $content ): int {
		$id = wp_insert_post(
			array(
				'post_title'   => 'WPML media',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $content,
			)
		);
		$this->posts[] = $id;

		return $id;
	}

	private function block( string $name, array $values, string $inner = '' ): string {
		$attrs = wp_json_encode( array( 'blockstudio' => array( 'attributes' => $values ) ) );

		return '' === $inner
			? "<!-- wp:$name $attrs /-->"
			: "<!-- wp:$name $attrs -->$inner<!-- /wp:$name -->";
	}

	public function test_the_filter_is_added_once_wpml_has_loaded(): void {
		// Plugin::init() runs before WPML fires wpml_loaded, in SitePress::init() on plugins_loaded.
		$this->assertSame( 10, has_action( 'wpml_loaded', array( Wpml_Media::class, 'register' ) ) );

		remove_filter( self::FILTER, array( Wpml_Media::class, 'add_media_ids' ) );
		$this->assertFalse( has_filter( self::FILTER, array( Wpml_Media::class, 'add_media_ids' ) ) );

		Wpml_Media::register();

		$this->assertSame( 10, has_filter( self::FILTER, array( Wpml_Media::class, 'add_media_ids' ) ) );
	}

	public function test_adds_the_attachments_of_files_fields_in_the_post_content(): void {
		$ids     = array();
		$ids[]   = $this->attachment();
		$found   = array_map( fn() => $this->attachment(), range( 1, 9 ) );
		$page    = $this->post( 'not an attachment' );
		$content = implode(
			"\n",
			array(
				'<!-- wp:image {"id":' . $ids[0] . '} --><figure class="wp-block-image"><img src="x.jpg" class="wp-image-' . $ids[0] . '"/></figure><!-- /wp:image -->',
				$this->block(
					$this->name,
					array(
						'heading'    => 'Hello',
						'image'      => $found[0],
						'gallery'    => array( $found[1], $found[2], $ids[0] ),
						'variant'    => $found[3],
						'count'      => $found[4],
						'cta_icon'   => $found[5],
						'background' => $page,
						'cards'      => array(
							array(
								'title' => 'One',
								'photo' => $found[6],
							),
							array( 'title' => 'Two' ),
						),
					)
				),
				$this->block(
					'core/group',
					array(),
					$this->block( $this->name, array( 'gallery' => $found[7] ) ) . $this->block( 'core/paragraph', array( 'texture' => $found[8] ), '<p>Text</p>' )
				),
			)
		);
		$post_id = $this->post( $content );

		Wpml_Media::register();

		// WPML passes the IDs it found itself (here the core image) and the post ID.
		$this->assertSame(
			array( $ids[0], $found[0], $found[1], $found[2], $found[3], $found[5], $found[6], $found[7], $found[8] ),
			apply_filters( self::FILTER, $ids, $post_id ),
			'files fields, a multiple field with one file, a hidden field, groups, repeater rows, inner blocks and an extension of a core block; the number and the page ID are left out'
		);
	}

	public function test_leaves_the_ids_unchanged_without_blockstudio_media(): void {
		$attachment = $this->attachment();

		Wpml_Media::register();

		$this->assertSame( array( 7 ), apply_filters( self::FILTER, array( 7 ), $this->post( '<p>Classic content</p>' ) ) );
		$this->assertSame( array( 7 ), apply_filters( self::FILTER, array( 7 ), $this->post( $this->block( $this->name, array( 'heading' => 'No image' ) ) ) ) );
		$this->assertSame( array(), apply_filters( self::FILTER, array(), $this->post( $this->block( 'acme/unknown', array( 'image' => $attachment ) ) ) ), 'values of blocks that Blockstudio does not know' );
		$this->assertSame( array(), apply_filters( self::FILTER, array(), 0 ), 'no post' );
		$this->assertSame( 'unchanged', Wpml_Media::add_media_ids( 'unchanged', 0 ) );
	}
}
