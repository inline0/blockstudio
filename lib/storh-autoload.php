<?php
/**
 * Storh autoload bootstrap.
 *
 * @package Blockstudio
 */

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'BlockstudioVendor\\Storh\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );

		if ( false !== strpos( $relative, '\\' ) || '' === $relative ) {
			return;
		}

		$file = __DIR__ . '/storh/storh/src/' . $relative . '.php';

		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);

require_once __DIR__ . '/storh/storh/src/DocStore.php';
