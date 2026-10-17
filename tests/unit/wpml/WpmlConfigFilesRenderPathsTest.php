<?php

use Blockstudio\Block_Registry;
use Blockstudio\Build;
use Blockstudio\Files;
use Blockstudio\Wpml_Config;
use PHPUnit\Framework\TestCase;

/**
 * Attachment IDs declared for a registered block, converted on
 * render_block_data the way WPML converts post-ids keys, and checked against
 * what the template receives.
 */
class WpmlConfigFilesRenderPathsTest extends TestCase {

	private string $name = 'wpml-test/files';
	private string $directory;

	/**
	 * Attachment IDs of the original language and their translations.
	 */
	private array $translations = array(
		11 => 21,
		12 => 22,
		13 => 23,
	);

	private array $paths = array();

	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/bs-wpml-files-' . uniqid();
		wp_mkdir_p( $this->directory . '/block' );

		file_put_contents(
			$this->directory . '/block/block.json',
			wp_json_encode(
				array(
					'name'        => $this->name,
					'title'       => 'Files',
					'blockstudio' => array(
						'attributes' => array(
							array( 'id' => 'image', 'type' => 'files', 'label' => 'Image', 'returnFormat' => 'id' ),
							array( 'id' => 'gallery', 'type' => 'files', 'label' => 'Gallery', 'multiple' => true, 'returnFormat' => 'id' ),
							array(
								'id'         => 'slides',
								'type'       => 'repeater',
								'label'      => 'Slides',
								'attributes' => array(
									array( 'id' => 'image', 'type' => 'files', 'label' => 'Image', 'returnFormat' => 'id' ),
									array( 'id' => 'logos', 'type' => 'files', 'label' => 'Logos', 'multiple' => true, 'returnFormat' => 'id' ),
								),
							),
						),
					),
				)
			)
		);
		file_put_contents(
			$this->directory . '/block/index.php',
			<<<'PHP'
<div class="wpml-test" data-template="<?php echo esc_attr( wp_json_encode( array( 'image' => $a['image'], 'gallery' => $a['gallery'], 'slides' => $a['slides'] ) ) ); ?>"></div>
PHP
		);

		Build::init( array( 'dir' => $this->directory ) );
		register_block_type( Build::blocks()[ $this->name ] );
		add_filter( 'render_block_data', array( $this, 'convert_ids' ), -PHP_INT_MAX );
	}

	protected function tearDown(): void {
		remove_filter( 'render_block_data', array( $this, 'convert_ids' ), -PHP_INT_MAX );
		if ( WP_Block_Type_Registry::get_instance()->is_registered( $this->name ) ) {
			unregister_block_type( $this->name );
		}
		Block_Registry::instance()->remove_block( $this->name );
		Files::delete_all_files( $this->directory );
		parent::tearDown();
	}

	/**
	 * Convert the IDs at every declared path, as WPML does on render_block_data.
	 */
	public function convert_ids( array $block ): array {
		if ( $this->name !== ( $block['blockName'] ?? '' ) ) {
			return $block;
		}

		foreach ( $this->paths as $path ) {
			$block['attrs'] = $this->convert( $block['attrs'], $path );
		}

		return $block;
	}

	/**
	 * Follows WPML\PB\Gutenberg\ConvertIdsInBlock\BlockAttributes::convertByPath() in WPML 5.1.0:
	 * "*" matches every key of an array, and only a scalar value at the end of the path is converted.
	 */
	private function convert( $value, array $path ) {
		$key  = $path[0] ?? null;
		$next = array_slice( $path, 1 );

		if ( '*' === $key && is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				$value[ $index ] = $this->convert( $item, $next );
			}
		} elseif ( null !== $key && is_array( $value ) && isset( $value[ $key ] ) ) {
			$value[ $key ] = $this->convert( $value[ $key ], $next );
		} elseif ( array() === $next && is_scalar( $value ) ) {
			$value = $this->translations[ $value ] ?? $value;
		}

		return $value;
	}

	/**
	 * Get the path of every key declared as attachment IDs.
	 */
	private function id_paths( string $section ): array {
		$document = simplexml_load_string( Wpml_Config::document( $section ) );
		$paths    = array();

		foreach ( $document->xpath( '//key[@type="post-ids"]' ) as $key ) {
			$this->assertSame( 'attachment', (string) $key['sub-type'] );
			$path = array();

			for ( $node = $key; 'key' === $node->getName(); $node = $node->xpath( '..' )[0] ) {
				array_unshift( $path, (string) $node['name'] );
			}

			$paths[] = $path;
		}

		return $paths;
	}

	private function render( array $values ): array {
		$section     = Wpml_Config::section( array( $this->name => Wpml_Config::registered_blocks()[ $this->name ] ) );
		$this->paths = $this->id_paths( $section['xml'] );

		$html = do_blocks( '<!-- wp:' . $this->name . ' ' . wp_json_encode( array( 'blockstudio' => array( 'attributes' => $values ) ) ) . ' /-->' );
		$tags = new WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'wpml-test' ) ), $html );

		return json_decode( (string) $tags->get_attribute( 'data-template' ), true );
	}

	public function test_the_registered_block_declares_its_files_fields_as_attachment_ids(): void {
		$section = Wpml_Config::section( array( $this->name => Wpml_Config::registered_blocks()[ $this->name ] ) );

		$this->assertStringContainsString( '<gutenberg-block type="wpml-test/files" translate="0">', $section['xml'] );
		$this->assertSame(
			array(
				array( 'blockstudio', 'attributes', 'image' ),
				array( 'blockstudio', 'attributes', 'gallery', '*' ),
				array( 'blockstudio', 'attributes', 'slides', '*', 'image' ),
				array( 'blockstudio', 'attributes', 'slides', '*', 'logos', '*' ),
			),
			$this->id_paths( $section['xml'] )
		);
	}

	public function test_the_template_receives_the_converted_ids(): void {
		$template = $this->render(
			array(
				'image'   => 11,
				'gallery' => array( 11, 12 ),
				'slides'  => array(
					array(
						'image' => 13,
						'logos' => array( 12, 13 ),
					),
				),
			)
		);

		$this->assertSame( 21, $template['image'] );
		$this->assertSame( array( 21, 22 ), $template['gallery'] );
		$this->assertSame( 23, $template['slides'][0]['image'] );
		$this->assertSame( array( 22, 23 ), $template['slides'][0]['logos'] );
	}

	public function test_a_multiple_field_with_one_selected_file_stores_a_single_id_that_is_converted_too(): void {
		// The editor stores the ID itself, not a list, when one file is selected in a multiple field.
		$template = $this->render(
			array(
				'gallery' => 12,
				'slides'  => array( array( 'logos' => 13 ) ),
			)
		);

		$this->assertSame( array( 22 ), $template['gallery'] );
		$this->assertSame( array( 23 ), $template['slides'][0]['logos'] );
	}
}
