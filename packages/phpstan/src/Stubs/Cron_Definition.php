<?php

namespace Blockstudio;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Cron_Definition
{
    public function __construct(
        public ?string $name = null,
        public string|Cron_Schedule|null $schedule = Cron_Schedule::Daily
    ) {}
}
