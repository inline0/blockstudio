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
}
