<?php

use Blockstudio\Attributes\Cron;
use Blockstudio\Cron\Schedule;

return new class {
	#[Cron( schedule: Schedule::Hourly )]
	public function heartbeat(): void {
		update_option( 'blockstudio_test_cron_php_heartbeat', time() );
	}

	#[Cron]
	public function cleanupOldEntries(): void {
		update_option( 'blockstudio_test_cron_php_cleanup', time() );
	}
};
