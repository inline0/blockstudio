<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Agents;

final readonly class Command
{
    public function __construct(
        public string $command,
        public string $purpose
    ) {}
}
