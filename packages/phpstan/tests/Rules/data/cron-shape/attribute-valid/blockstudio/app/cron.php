<?php

use Blockstudio\Cron_Definition as Cron;
use Blockstudio\Cron_Schedule;

return new class {
    #[Cron(schedule: Cron_Schedule::Hourly)]
    public function heartbeat(): void
    {
    }

    #[Cron]
    public function cleanupOldEntries(): void
    {
    }
};
