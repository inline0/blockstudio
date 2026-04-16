<?php

use Blockstudio\Api\Attributes\Cron;
use Blockstudio\Api\Cron\Schedule;

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
