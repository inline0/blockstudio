<?php

use Blockstudio\Ui;
use PHPUnit\Framework\TestCase;

/**
 * Tests for bundled UI settings.
 */
class UiTest extends TestCase {

	private array $filter_callbacks = array();

	protected function tearDown(): void {
		foreach ( $this->filter_callbacks as $cb ) {
			remove_filter( $cb[0], $cb[1], $cb[2] );
		}

		$this->filter_callbacks = array();
	}

	private function add_filter( string $name, callable $cb, int $priority = 10 ): void {
		add_filter( $name, $cb, $priority );
		$this->filter_callbacks[] = array( $name, $cb, $priority );
	}

	public function test_ui_is_disabled_by_default(): void {
		$this->assertFalse( Ui::enabled() );
	}

	public function test_ui_can_be_enabled_with_settings_filter(): void {
		$this->add_filter( 'blockstudio/settings/ui/enabled', '__return_true' );

		$this->assertTrue( Ui::enabled() );
	}

	public function test_directories_include_bundled_blocks_and_apps(): void {
		$directories = Ui::directories();

		$this->assertContains( BLOCKSTUDIO_DIR . '/includes/ui/blocks', $directories );
		$this->assertContains( BLOCKSTUDIO_DIR . '/includes/ui/apps', $directories );
	}

	public function test_bundled_path_detection_is_consumer_neutral(): void {
		$this->assertTrue(
			Ui::is_bundled_block(
				array(
					'path' => BLOCKSTUDIO_DIR . '/includes/ui/blocks/button/root/index.php',
				)
			)
		);
		$this->assertFalse(
			Ui::is_bundled_block(
				array(
					'path' => get_template_directory() . '/blockstudio/card/index.php',
				)
			)
		);
	}

	public function test_global_assets_are_emitted_only_for_ui_output(): void {
		$this->assertSame(
			array(
				'style'  => '',
				'script' => '',
			),
			Ui::global_assets( array(), '<div>Theme block</div>' )
		);

		$assets = Ui::global_assets( array(), '<button data-bsui-button>Button</button>' );

		$this->assertStringContainsString( '<style id="blockstudio-ui-global">', $assets['style'] );
		$this->assertStringContainsString( '<script id="blockstudio-ui-global-script">', $assets['script'] );
	}

	public function test_generated_examples_use_safe_source_urls(): void {
		$this->add_filter(
			'blockstudio/ui/inventory',
			static fn(): array => array(
				array(
					'slug'                => 'avatar',
					'title'               => 'Avatar',
					'rootName'            => 'bsui/avatar',
					'rootPath'            => BLOCKSTUDIO_DIR . '/includes/ui/blocks/avatar/root',
					'implementationNames' => array( 'bsui/avatar' ),
					'blocks'              => array(
						'bsui/avatar' => array(
							'name'       => 'bsui/avatar',
							'title'      => 'Avatar',
							'path'       => BLOCKSTUDIO_DIR . '/includes/ui/blocks/avatar/root',
							'family'     => 'avatar',
							'role'       => 'root',
							'parent'     => array(),
							'attributes' => array(
								'src' => array(
									'id'    => 'src',
									'type'  => 'text',
									'label' => 'Image URL',
								),
							),
						),
					),
				),
			)
		);

		$examples = Ui::examples();

		$this->assertSame( '#', $examples[0]['declaration']['attributes']['src'] ?? null );
	}
}
