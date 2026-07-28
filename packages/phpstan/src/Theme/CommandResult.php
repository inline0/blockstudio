<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Theme;

final readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public bool $timedOut = false
    ) {}
}
