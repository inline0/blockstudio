#!/usr/bin/env php
<?php
/**
 * Generate scoped Tailwind preflight CSS using native CSS @scope.
 *
 * @package Blockstudio
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

$blockstudio_root_dir = dirname( __DIR__ );

require_once $blockstudio_root_dir . '/lib/tailwindphp-autoload.php';

use function BlockstudioVendor\TailwindPHP\generate;

$blockstudio_css = generate(
	array(
		'content' => '',
		'css'     => '@scope ([data-blockstudio]) { @import "tailwindcss/preflight.css" layer(base); }',
		'minify'  => true,
	)
);

$blockstudio_output_path = $blockstudio_root_dir . '/includes/admin/assets/tailwind/preflight.css';
file_put_contents( $blockstudio_output_path, $blockstudio_css );

echo "Generated: $blockstudio_output_path\n";
echo 'Size: ' . strlen( $blockstudio_css ) . " bytes\n";
