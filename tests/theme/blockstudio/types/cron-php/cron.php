<?php

use Blockstudio\Cron_Definition as Cron;
use Blockstudio\Cron_Schedule;

return new class {
	#[Cron( schedule: Cron_Schedule::Hourly )]
	public function heartbeat(): void {
		update_option( 'blockstudio_test_cron_php_heartbeat', time() );
	}

	#[Cron]
	public function cleanupOldEntries(): void {
		update_option( 'blockstudio_test_cron_php_cleanup', time() );
	}
};
