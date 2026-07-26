<?php
/**
 * Constants PHPStan cannot see.
 *
 * BLOCKSTUDIO_URL is defined conditionally in blockstudio.php so a host can
 * override it before load. WPINC and WP_CONTENT_URL come from WordPress but are
 * absent from the bundled stubs.
 *
 * @package Blockstudio
 */

define( 'BLOCKSTUDIO_URL', '' );
define( 'WPINC', '' );
define( 'WP_CONTENT_URL', '' );
