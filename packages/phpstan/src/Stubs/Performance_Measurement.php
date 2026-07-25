<?php

namespace Blockstudio;

final class Performance_Measurement
{
    public static function enabled(): bool {}

    /** @return array<string, mixed> */
    public static function snapshot(): array {}
}
