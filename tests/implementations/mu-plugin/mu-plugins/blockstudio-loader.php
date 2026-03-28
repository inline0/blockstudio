<?php
/**
 * MU-Plugin loader for Blockstudio.
 *
 * wp-env maps this directory to wp-content/mu-plugins/.
 * The blockstudio directory is mapped separately via .wp-env.json.
 */

$blockstudio_path = __DIR__ . '/blockstudio/blockstudio.php';

if ( file_exists( $blockstudio_path ) ) {
	require_once $blockstudio_path;
}
