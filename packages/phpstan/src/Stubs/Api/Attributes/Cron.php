<?php

namespace Blockstudio\Api\Attributes;

use Blockstudio\Api\Cron\Schedule;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Cron
{
    public function __construct(
        public ?string $name = null,
        public string|Schedule|null $schedule = Schedule::Daily
    ) {}
}
