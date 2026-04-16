<?php

use Blockstudio\Attributes\Cron;
use Blockstudio\Cron\Schedule;

return new class {
    #[Cron(schedule: Schedule::Hourly)]
    public function heartbeat(): void
    {
    }

    #[Cron]
    public function cleanupOldEntries(): void
    {
    }
};
