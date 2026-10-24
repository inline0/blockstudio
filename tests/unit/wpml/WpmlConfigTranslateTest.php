<?php

use Blockstudio\Block_Registry;
use Blockstudio\Build;
use Blockstudio\Files;
use Blockstudio\Wpml_Config;
use PHPUnit\Framework\TestCase;

/**
 * "translate": false on the fields of a registered block and of its extension,
 * read through the registration path that `wp bs wpml config` uses.
 */
class WpmlConfigTranslateTest extends TestCase {

	private string $name = 'wpml-test/translate';
	private string $directory;
	private array $extensions;

	protected function setUp(): void {
		$this->extensions = Build::extensions();
		$this->directory  = sys_get_temp_dir() . '/bs-wpml-translate-' . uniqid();
		wp_mkdir_p( $this->directory . '/block' );
		wp_mkdir_p( $this->directory . '/extensions' );

		$this->write(
			'block/block.json',
			array(
				'name'        => $this->name,
				'title'       => 'Translate',
				'blockstudio' => array(
					'attributes' => array(
						array( 'id' => 'title', 'type' => 'text', 'label' => 'Title' ),
						array( 'id' => 'account', 'type' => 'text', 'label' => 'Account', 'translate' => false ),
						array(
							'id'         => 'contact',
							'type'       => 'group',
							'translate'  => false,
							'attributes' => array(
								array( 'id' => 'email', 'type' => 'text', 'label' => 'Email' ),
							),
						),
						array(
							'id'         => 'items',
							'type'       => 'repeater',
							'label'      => 'Items',
							'attributes' => array(
								array( 'id' => 'heading', 'type' => 'text', 'label' => 'Heading' ),
								array( 'id' => 'slug', 'type' => 'text', 'label' => 'Slug', 'translate' => false ),
							),
						),
					),
				),
			)
		);
		file_put_contents( $this->directory . '/block/index.php', '<div></div>' );
		$this->write(
			'extensions/anchor.json',
			array(
				'name'        => $this->name,
				'blockstudio' => array(
					'extend'     => true,
					'attributes' => array(
						array( 'id' => 'anchor', 'type' => 'text', 'label' => 'Anchor', 'translate' => false ),
						array( 'id' => 'caption', 'type' => 'text', 'label' => 'Caption' ),
					),
				),
			)
		);

		Build::init( array( 'dir' => $this->directory ) );
		register_block_type( Build::blocks()[ $this->name ] );
	}

	protected function tearDown(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( $this->name ) ) {
			unregister_block_type( $this->name );
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

	public function test_the_registered_block_and_its_extension_leave_out_fields_with_translate_false(): void {
		$blocks  = Wpml_Config::registered_blocks();
		$section = Wpml_Config::section( array( $this->name => $blocks[ $this->name ] ) );

		$this->assertSame( array(), $section['conflicts'] );
		$this->assertSame( array(), $section['skipped'] );

		$expected = <<<'XML'
		<gutenberg-block type="wpml-test/translate" translate="1" label="Translate">
			<key name="blockstudio">
				<key name="attributes">
					<key name="title" label="Title" />
					<key name="items">
						<key name="*">
							<key name="heading" label="Items: Heading" />
						</key>
					</key>
					<key name="caption" label="Caption" />
				</key>
			</key>
		</gutenberg-block>
XML;

		$this->assertStringContainsString( $expected, $section['xml'] );
	}
}
