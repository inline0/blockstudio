<?php
add_filter(
	'blockstudio/settings/tailwind/config',
	function () {
		return '';
	}
);

add_action(
	'init',
	function () {
		if ( class_exists( 'Blockstudio\Build' ) ) {
			Blockstudio\Build::init(
				array(
					'dir' => __DIR__ . '/interactions',
				)
			);
		}
	},
	PHP_INT_MAX - 2
);

add_filter( 'show_admin_bar', '__return_false' );
