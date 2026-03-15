<?php
return array(
	'cleanup' => array(
		'schedule' => 'daily',
		'callback' => function () {
			update_option( 'blockstudio_test_cron_cleanup', time() );
		},
	),
	'ping'    => function () {
		update_option( 'blockstudio_test_cron_ping', time() );
	},
);
