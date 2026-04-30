<?php

use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase {

	public function test_relative_plugin_path_normalizes_windows_path_separators(): void {
		$content_dir = 'D:/Users/example/Sites/site/wp-content';
		$plugin_dir  = 'D:\\Users\\example\\Sites\\site\\wp-content\\plugins\\blockstudio';

		$this->assertSame(
			$plugin_dir,
			str_replace( $content_dir, '', $plugin_dir )
		);

		$this->assertSame(
			'/plugins/blockstudio',
			blockstudio_get_relative_plugin_path( $content_dir, $plugin_dir )
		);
	}

	public function test_relative_plugin_path_preserves_normalized_paths(): void {
		$this->assertSame(
			'/plugins/blockstudio',
			blockstudio_get_relative_plugin_path(
				'/var/www/html/wp-content',
				'/var/www/html/wp-content/plugins/blockstudio'
			)
		);
	}
}
